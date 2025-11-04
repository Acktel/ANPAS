<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

use App\Services\RipartizioneCostiService;

class GeneraRotazioneMezziPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /** Child id in tabella documenti_generati */
    public int $documentoId;

    /** Coerenza con gli altri job PDF */
    public $tries   = 1;
    public $timeout = 1000;
    public $backoff = 0;

    public function __construct(
        int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId
    ) {
        $this->documentoId = $documentoId;
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        // Segna subito "processing"
        DB::table('documenti_generati')->where('id', $this->documentoId)->update([
            'stato'      => 'processing',
            'updated_at' => now(),
        ]);

        // 🔒 lock compatibile col tuo stack (niente WithoutOverlapping)
        $lockKey = "pdf-rotazione-mezzi-{$this->idAssociazione}-{$this->anno}";
        $lock    = Cache::lock($lockKey, 180);

        if (! $lock->get()) {
            Log::warning("GeneraRotazioneMezziPdfJob già in esecuzione: {$lockKey}");
            // Non segno errore: lascio il child “queued/processing” altrimenti sporchi il bundle.
            return;
        }

        try {
            $doc = DB::table('documenti_generati')->where('id', $this->documentoId)->first();
            if (! $doc) {
                throw new \RuntimeException("Documento {$this->documentoId} non trovato");
            }

            $associazione = (string) (DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->value('Associazione') ?? '');

            // 1) Convenzioni in vero regime di ROTAZIONE
            $convList = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('ordinamento')->orderBy('idConvenzione')
                ->get(['idConvenzione', 'Convenzione'])
                ->filter(fn($c) => RipartizioneCostiService::isRegimeRotazione((int)$c->idConvenzione))
                ->values();

            $convIds  = $convList->pluck('idConvenzione')->map(fn($v)=>(int)$v)->all();
            $convById = $convList->pluck('Convenzione', 'idConvenzione')->map(fn($v)=>(string)$v)->all();

            // Se non c’è rotazione, produciamo comunque 1 pagina esplicativa (così il merge non salta)
            if (empty($convIds)) {
                $pdf = Pdf::loadView('template.rotazione_mezzi_empty', [
                    'anno'         => $this->anno,
                    'associazione' => $associazione,
                ])->setPaper('a4', 'landscape');

                $path = sprintf('documenti/rotazione_mezzi_%d_%d_%d.pdf',
                    $this->idAssociazione, $this->anno, $this->documentoId);

                Storage::disk('public')->put($path, $pdf->output());

                DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                    'percorso_file' => $path,
                    'stato'         => 'ready',
                    'generato_il'   => now(),
                    'updated_at'    => now(),
                ]);

                Log::warning('RotazioneMezzi: nessuna convenzione in regime di rotazione.');
                return;
            }

            // 2) KM totali per convenzione (denominatori delle %)
            $kmTotByConv = DB::table('automezzi_km as ak')
                ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
                ->whereIn('ak.idConvenzione', $convIds)
                ->where('c.idAssociazione', $this->idAssociazione)
                ->where('c.idAnno', $this->anno)
                ->select('ak.idConvenzione', DB::raw('SUM(ak.KMPercorsi) AS km'))
                ->groupBy('ak.idConvenzione')
                ->pluck('km', 'ak.idConvenzione')
                ->map(fn($v) => (int) round((float) $v))
                ->all();

            // 3) KM per (mezzo, convenzione) limitati alle conv in rotazione
            $kmByMezzoConv = DB::table('automezzi_km as ak')
                ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
                ->whereIn('ak.idConvenzione', $convIds)
                ->where('c.idAssociazione', $this->idAssociazione)
                ->where('c.idAnno', $this->anno)
                ->select('ak.idAutomezzo', 'ak.idConvenzione', DB::raw('SUM(ak.KMPercorsi) AS km'))
                ->groupBy('ak.idAutomezzo', 'ak.idConvenzione')
                ->get()
                ->groupBy(fn($r) => (int)$r->idAutomezzo)
                ->map(function ($rows) {
                    $m = [];
                    foreach ($rows as $r) $m[(int)$r->idConvenzione] = (int) round((float)$r->km);
                    return $m;
                })
                ->all();

            // 4) Automezzi con almeno 1 km sulle conv in rotazione (recupero anagrafica)
            $mezziIds = array_keys($kmByMezzoConv);
            if (empty($mezziIds)) {
                // Stesso fallback di cui sopra
                $pdf = Pdf::loadView('template.rotazione_mezzi_empty', [
                    'anno'         => $this->anno,
                    'associazione' => $associazione,
                ])->setPaper('a4', 'landscape');

                $path = sprintf('documenti/rotazione_mezzi_%d_%d_%d.pdf',
                    $this->idAssociazione, $this->anno, $this->documentoId);

                Storage::disk('public')->put($path, $pdf->output());

                DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                    'percorso_file' => $path,
                    'stato'         => 'ready',
                    'generato_il'   => now(),
                    'updated_at'    => now(),
                ]);

                Log::warning('RotazioneMezzi: nessun mezzo con KM sulle convenzioni in rotazione.');
                return;
            }

            $automezzi = DB::table('automezzi')
                ->whereIn('idAutomezzo', $mezziIds)
                ->orderBy('idAutomezzo')
                ->get(['idAutomezzo', 'Targa', 'CodiceIdentificativo']);

            // 5) Somma KM per mezzo SOLO sulle conv in rotazione
            $kmTotByMezzo = [];
            foreach ($kmByMezzoConv as $idM => $byConv) {
                $s = 0;
                foreach ($convIds as $cid) $s += (int) ($byConv[$cid] ?? 0);
                $kmTotByMezzo[(int)$idM] = $s;
            }

            // 6) Totali di colonna per riga TOTALE
            $totKmColByConv = array_fill_keys($convIds, 0);

            // 7) Costruzione righe per la view: per ogni mezzo, KM e %
            $rows = [];
            $progressivo = 0;

            foreach ($automezzi as $a) {
                $idM = (int)$a->idAutomezzo;

                // filtra mezzi senza km effettivi sulle conv in rotazione
                if (!isset($kmTotByMezzo[$idM]) || $kmTotByMezzo[$idM] <= 0) {
                    continue;
                }
                $progressivo++;

                $riga = [
                    'progressivo' => 'AUTO ' . $progressivo,
                    'targa'       => (string) $a->Targa,
                    'codice'      => (string) ($a->CodiceIdentificativo ?? ''),
                    'km_tot'      => (int) ($kmTotByMezzo[$idM] ?? 0),
                    'per_conv'    => [], // [idConv => ['km' => int, 'pct' => float]]
                ];

                foreach ($convIds as $cid) {
                    $km  = (int) ($kmByMezzoConv[$idM][$cid] ?? 0);
                    $den = (int) ($kmTotByConv[$cid] ?? 0);
                    $pct = $den > 0 ? ($km / $den) : 0.0;

                    $riga['per_conv'][$cid] = [
                        'km'  => $km,
                        'pct' => $pct, // 0..1
                    ];

                    $totKmColByConv[$cid] += $km;
                }

                $rows[] = $riga;
            }

            // 8) Totali
            $sumKmAll = array_sum($kmTotByMezzo);
            $totale = [
                'km_tot'   => (int)$sumKmAll,
                'per_conv' => [], // [idConv => ['km' => int, 'pct' => 1.0]]
            ];
            foreach ($convIds as $cid) {
                $totale['per_conv'][$cid] = [
                    'km'  => (int)($totKmColByConv[$cid] ?? 0),
                    'pct' => 1.0,
                ];
            }

            // 9) Render PDF (usa un Blade dedicato)
            $pdf = Pdf::loadView('template.rotazione_mezzi', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                'convenzioni'  => $convById,   // [idConv => label] nell’ordine originale
                'rows'         => $rows,       // righe come sopra
                'totale'       => $totale,     // footer totale
            ])->setPaper('a4', 'landscape');

            $path = sprintf(
                'documenti/rotazione_mezzi_%d_%d_%d.pdf',
                $this->idAssociazione,
                $this->anno,
                $this->documentoId
            );

            Storage::disk('public')->put($path, $pdf->output());

            DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                'percorso_file' => $path,
                'stato'         => 'ready',
                'generato_il'   => now(),
                'updated_at'    => now(),
            ]);

            Log::info('RotazioneMezzi PDF generato', [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
                'rows'           => count($rows),
                'conv'           => array_values($convById),
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraRotazioneMezziPdfJob error', [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
                'message'        => $e->getMessage(),
            ]);

            DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                'stato'      => 'error',
                'updated_at' => now(),
            ]);

            $this->fail($e);
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraRotazioneMezziPdfJob failed: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);

        DB::table('documenti_generati')->where('id', $this->documentoId)->update([
            'stato'      => 'error',
            'updated_at' => now(),
        ]);
    }
}
