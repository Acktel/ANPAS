<?php

namespace App\Jobs;

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
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DocumentoGenerato;

class GeneraRipVolontariScnPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /** ID dipendenti fittizi */
    private const FAKE_VOLONTARI_ID = 999999;
    private const FAKE_SCN_ID       = 999998;

    /** Qualifiche fallback (cambia se i tuoi ID sono diversi) */
    private const QUALIFICA_VOLONTARI_ID = 15;
    private const QUALIFICA_SCN_ID       = 16;

    /** Coda dedicata */
    protected string $queue = 'pdf';

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue($this->queue);
    }

    public function handle(): void
    {
        // Lock applicativo (evita job concorrenti sullo stesso ass/anno)
        $lockKey = "pdf-rip-vol-scn-{$this->idAssociazione}-{$this->anno}";
        $lock = Cache::lock($lockKey, 300); // 5 minuti

        if (! $lock->get()) {
            Log::warning("GeneraRipVolontariScnPdfJob: lock attivo, skip ({$lockKey})");
            return;
        }

        try {
            /** @var DocumentoGenerato $doc */
            $doc = DocumentoGenerato::findOrFail($this->documentoId);

            // intestazioni
            $associazione = DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->first();

            // convenzioni anno + associazione (ordine stabile)
            $convenzioni = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('ordinamento')
                ->orderBy('idConvenzione')
                ->get(['idConvenzione','Convenzione']);

            // Helper: aggrega ore per convenzione usando un idDipendente fittizio
            $aggByFakeId = function (int $fakeId) {
                return DB::table('dipendenti_servizi as ds')
                    ->join('convenzioni as c','c.idConvenzione','=','ds.idConvenzione')
                    ->where('ds.idDipendente', $fakeId)
                    ->where('c.idAssociazione', $this->idAssociazione)
                    ->where('c.idAnno', $this->anno)
                    ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as OreServizio'))
                    ->groupBy('ds.idConvenzione')
                    ->pluck('OreServizio', 'idConvenzione'); // Collection
            };

            // Helper: fallback dinamico per qualifica (somma ore di tutti i dip con quella qualifica)
            $aggByQualifica = function (int $idQualifica) {
                return DB::table('dipendenti_servizi as ds')
                    ->join('dipendenti as d', 'd.idDipendente', '=', 'ds.idDipendente')
                    ->join('dipendenti_qualifiche as dq', 'dq.idDipendente', '=', 'd.idDipendente')
                    ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
                    ->where('dq.idQualifica', $idQualifica)
                    ->where('c.idAssociazione', $this->idAssociazione)
                    ->where('c.idAnno', $this->anno)
                    ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as OreServizio'))
                    ->groupBy('ds.idConvenzione')
                    ->pluck('OreServizio', 'idConvenzione');
            };

            // === VOLONTARI ===
            $aggVol = $aggByFakeId(self::FAKE_VOLONTARI_ID);
            if (($aggVol->sum() ?? 0) <= 0) {
                $aggVol = $aggByQualifica(self::QUALIFICA_VOLONTARI_ID);
            }

            $totVol = (float) ($aggVol->sum() ?? 0);
            $rowVol = [
                'label'     => "ORE TOTALI DI SERVIZIO VOLONTARIO",
                'OreTotali' => $totVol,
            ];
            foreach ($convenzioni as $c) {
                $k   = 'c'.$c->idConvenzione;
                $ore = (float) ($aggVol[$c->idConvenzione] ?? 0);
                $rowVol[$k.'_ore']     = $ore;
                $rowVol[$k.'_percent'] = $totVol > 0 ? round($ore / $totVol * 100, 2) : 0.0;
            }

            // === SERVIZIO CIVILE ===
            $aggScn = $aggByFakeId(self::FAKE_SCN_ID);
            if (($aggScn->sum() ?? 0) <= 0) {
                $aggScn = $aggByQualifica(self::QUALIFICA_SCN_ID);
            }

            $totScn = (float) ($aggScn->sum() ?? 0);
            $rowScn = [
                'label'     => "UNITÀ TOTALI DI SERVIZIO CIVILE NAZIONALE",
                'OreTotali' => $totScn,
            ];
            foreach ($convenzioni as $c) {
                $k   = 'c'.$c->idConvenzione;
                $ore = (float) ($aggScn[$c->idConvenzione] ?? 0);
                $rowScn[$k.'_ore']     = $ore;
                $rowScn[$k.'_percent'] = $totScn > 0 ? round($ore / $totScn * 100, 2) : 0.0;
            }

            // render
            $pdf = Pdf::loadView('template.rip_volontari_scn', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                'convenzioni'  => $convenzioni,
                'volontari'    => $rowVol,
                'scn'          => $rowScn,
            ])->setPaper('a4','landscape');

            $filename = "ripartizione_volontari_scn_{$this->idAssociazione}_{$this->anno}_".now()->timestamp.".pdf";
            $path     = "documenti/{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraRipVolontariScnPdfJob failed: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraRipVolontariScnPdfJob failed (failed callback): '.$e->getMessage(), [
            'documentoId'=>$this->documentoId,
            'assoc'=>$this->idAssociazione,
            'anno'=>$this->anno,
        ]);
    }
}
