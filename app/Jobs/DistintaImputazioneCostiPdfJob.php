<?php

namespace App\Jobs;

use App\Services\RipartizioneCostiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DistintaImputazioneCostiPdfJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /** ID record in tabella documenti_generati */
    public int $documentoId;

    /** Retries/backoff/timeout sensati per DomPDF */
    public $tries   = 5;
    public $backoff = [10, 30, 60, 120];
    public $timeout = 1200;

    public function __construct(int $documentoId) {
        $this->documentoId = $documentoId;
        $this->onQueue('pdf');
    }

    public function handle(): void {
        $lockKey = "pdf-distinta-{$this->documentoId}";
        $lock = Cache::lock($lockKey, 180); // TTL 3 minuti

        if (! $lock->get()) {
            Log::warning("DistintaImputazioneCostiPdfJob già in esecuzione per {$this->documentoId}");
            return;
        }

        try {

            // Stato iniziale (passo da DB diretto così bypasso fillable)
            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update([
                    'stato'      => 'processing',
                    'updated_at' => now(),
                ]);

            $doc = DB::table('documenti_generati')->where('id', $this->documentoId)->first();
            if (!$doc) {
                throw new \RuntimeException("Documento {$this->documentoId} non trovato");
            }

            $idAssociazione = (int) $doc->idAssociazione;
            $anno           = (int) $doc->idAnno;

            // ===== CARICO DISTINTA (PASS-THROUGH, niente ricalcoli) =====
            $payload = RipartizioneCostiService::distintaImputazioneData($idAssociazione, $anno);
            $convenzioni = array_values($payload['convenzioni'] ?? []); // array di nomi (ordine UI)
            $righeRaw    = $payload['data'] ?? [];

            // Adapter minimale: cast numeri, crea "totali per convenzione" senza ricalcolare le ripartizioni
            $righe = [];
            foreach ($righeRaw as $r) {
                // campi fissi riga
                $row = [
                    'idVoceConfig' => (int)   ($r['idVoceConfig'] ?? 0),
                    'voce'         => (string)($r['voce'] ?? ''),
                    'sezione_id'   => (int)   ($r['sezione_id'] ?? 0),
                    'bilancio'     => (float) ($r['bilancio'] ?? 0),
                    'diretta'      => (float) ($r['diretta'] ?? 0),
                    'totale'       => (float) ($r['totale'] ?? 0), // questo è già “sum-late-round” lato service
                    'per_conv'     => [],                           // qui metto diretti/ammortamento/indiretti per conv
                ];

                // totali per convenzione (dir-amm+ind) in stile “somma tardi”
                $sumPerConv = 0.0;

                foreach ($convenzioni as $convName) {
                    $cell = $r[$convName] ?? ['diretti' => 0, 'ammortamento' => 0, 'indiretti' => 0];
                    $dir  = (float) ($cell['diretti']      ?? 0);
                    $amm  = (float) ($cell['ammortamento'] ?? 0);
                    $ind  = (float) ($cell['indiretti']    ?? 0);

                    $totConv = $dir - $amm + $ind; // NON arrotondo qui: sommo “tardi”
                    $row['per_conv'][$convName] = [
                        'diretti'      => round($dir, 2),
                        'ammortamento' => round($amm, 2),
                        'indiretti'    => round($ind, 2),
                        'totale'       => $totConv,          // grezzo ora
                    ];

                    $sumPerConv += $totConv;
                }

                // chiudi in stile Excel: arrotonda solo alla fine
                $row['totale_per_conv'] = $this->sumLateRound(array_map(
                    fn($v) => (float) $v['totale'],
                    $row['per_conv']
                ));

                // Non tocco $row['totale'] che arriva dal service (già coerente con UI).
                $righe[] = $row;
            }

            // intestazione
            $associazione = (string) (DB::table('associazioni')
                ->where('idAssociazione', $idAssociazione)
                ->value('Associazione') ?? '');

            // render PDF – la view deve aspettarsi esattamente questa struttura (coerente con UI)
            $pdf = Pdf::loadView('template.distinta_imputazione_costi', [
                'anno'         => $anno,
                'associazione' => $associazione,
                'convenzioni'  => $convenzioni, // array di NOME convenzione in ordine UI
                'righe'        => $righe,       // pass-through + totali per conv calcolati in “late round”
            ])->setPaper('a4', 'landscape');

            // salva su storage pubblico
            $filename = sprintf(
                'distinta_imputazione_costi_%d_%d_%d.pdf',
                $idAssociazione,
                $anno,
                $this->documentoId
            );
            $path = 'documenti/' . $filename;

            Storage::disk('public')->put($path, $pdf->output());

            // marca come pronto (e salva anche il nome file, così la UI può mostrarlo)
            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update([
                    'nome_file'     => $filename,
                    'percorso_file' => $path,
                    'stato'         => 'ready',
                    'generato_il'   => now(),
                    'updated_at'    => now(),
                ]);
        } finally {
            Log::error('DistintaImputazioneCostiPdfJob failed', [
                'lock' => $lock
            ]);
            optional($lock)->release();
        }
    }

    public function failed(Throwable $e): void {
        Log::error('DistintaImputazioneCostiPdfJob failed', [
            'documentoId' => $this->documentoId,
            'error'       => $e->getMessage(),
        ]);

        DB::table('documenti_generati')
            ->where('id', $this->documentoId)
            ->update([
                'stato'      => 'error',
                'updated_at' => now(),
            ]);
    }

    /** Somma “tardi”: nessun arrotondamento sugli addendi; arrotonda solo alla fine. */
    private function sumLateRound(array $vals): float {
        $s = 0.0;
        foreach ($vals as $v) $s += (float) $v;
        return round($s, 2, PHP_ROUND_HALF_UP);
    }
}
