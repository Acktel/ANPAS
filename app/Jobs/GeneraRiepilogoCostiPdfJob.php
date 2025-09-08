<?php

// app/Jobs/GeneraRiepilogoCostiPdfJob.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DocumentoGenerato;
use App\Models\RiepilogoCosti;

class GeneraRiepilogoCostiPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        // assegna la coda senza ridefinire la property $queue
        $this->onQueue('pdf');
        // se vuoi: $this->onConnection('database'); // o 'redis'
    }

    /**
     * Evita sovrapposizioni: un solo job per associazione alla volta.
     * (richiede un cache driver funzionante)
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("pdf-riepilogo-costi-{$this->idAssociazione}"))
                ->expireAfter(300) // lock max 5 minuti
                ->dontRelease(),   // se c'è overlap scarta invece di ritentare
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        try {
            // --- intestazione ---
            $associazione = DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->first();

            // titoli sezioni (2..11)
            $sezioniTitoli = [
                2  => 'Automezzi',
                3  => 'Attrezzatura Sanitaria',
                4  => 'Telecomunicazioni',
                5  => 'Costi gestione struttura',
                6  => 'Costo del personale',
                7  => 'Materiale sanitario di consumo',
                8  => 'Costi amministrativi',
                9  => 'Quote di ammortamento',
                10 => 'Beni Strumentali < 516€',
                11 => 'Altri costi',
            ];
            $ids = array_keys($sezioniTitoli);

            // helper per comporre un blocco (TOT o singola convenzione)
            $buildBlock = function (int|string $idConvenzione) use ($ids, $sezioniTitoli) {
                $sezioni = [];
                $totPrev = 0.0;
                $totCons = 0.0;

                foreach ($ids as $tip) {
                    $rows = RiepilogoCosti::getByTipologia(
                        $tip,
                        $this->anno,
                        $this->idAssociazione,
                        $idConvenzione
                    );

                    // $rows è una Collection
                    $sumPrev = (float) $rows->sum(fn($r) => (float) $r->preventivo);
                    $sumCons = (float) $rows->sum(fn($r) => (float) $r->consuntivo);

                    $totPrev += $sumPrev;
                    $totCons += $sumCons;

                    $sezioni[$tip] = [
                        'titolo'  => $sezioniTitoli[$tip],
                        'rows'    => $rows,
                        'sumPrev' => $sumPrev,
                        'sumCons' => $sumCons,
                    ];
                }

                return [$sezioni, $totPrev, $totCons];
            };

            // blocco TOTALE
            [$totSez, $totPrev, $totCons] = $buildBlock('TOT');
            $blocks = [[
                'nome'     => 'TOTALE',
                'sezioni'  => $totSez,
                'totPrev'  => $totPrev,
                'totCons'  => $totCons,
            ]];
            $totaleTot = ['prev' => $totPrev, 'cons' => $totCons];

            // blocchi per ciascuna convenzione
            $convenzioni = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('ordinamento')
                ->orderBy('idConvenzione')
                ->get(['idConvenzione', 'Convenzione']);

            foreach ($convenzioni as $c) {
                [$sez, $p, $cns] = $buildBlock((int) $c->idConvenzione);
                $blocks[] = [
                    'nome'     => $c->Convenzione,
                    'sezioni'  => $sez,
                    'totPrev'  => $p,
                    'totCons'  => $cns,
                ];
            }

            // render PDF con la tua view
            $pdf = Pdf::loadView('template.riepilogo_costi', [
                'anno'           => $this->anno,
                'idAssociazione' => $this->idAssociazione,
                'associazione'   => $associazione,
                'totaleTot'      => $totaleTot,
                'blocks'         => $blocks,
            ])->setPaper('a4', 'landscape');

            // salvataggio su storage/public/documenti
            $filename = "riepilogo_costi_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
            $path     = "documenti/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            // aggiorna il record documento (solo colonne esistenti)
            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
            ]);
        } catch (Throwable $e) {
            // logga e rilancia: così finisce anche in failed_jobs
            Log::error('GeneraRiepilogoCostiPdfJob error: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        // Facoltativo: log aggiuntivo quando Laravel marca il job come failed
        Log::error('GeneraRiepilogoCostiPdfJob failed callback: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
