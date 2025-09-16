<?php

namespace App\Jobs;

use App\Models\Riepilogo;
use App\Models\RiepilogoCosti;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Bus\Batchable;
use Throwable;

class RiepiloghiDatiECostiPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno
    ) {
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        // mark processing
        DB::table('documenti_generati')
            ->where('id', $this->documentoId)
            ->update(['stato' => 'processing', 'updated_at' => now()]);

        try {
            // intestazione
            $associazione = (string) DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->value('Associazione');

            // convenzioni ordinate
            $convMap = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('ordinamento')->orderBy('idConvenzione')
                ->pluck('Convenzione', 'idConvenzione');

            // prima TOTALE, poi ogni convenzione
            $targets = [['label' => 'TOTALE', 'idConv' => 'TOT']];
            foreach ($convMap as $id => $nome) {
                $targets[] = ['label' => (string) $nome, 'idConv' => (int) $id];
            }

            // label sezioni costi 2..11
            $sectionLabels = [
                2  => 'Automezzi',
                3  => 'Attrezzatura Sanitaria',
                4  => 'Telecomunicazioni',
                5  => 'Costi gestione struttura',
                6  => 'Costo del personale',
                7  => 'Materiale sanitario di consumo',
                8  => 'Costi amministrativi',
                9  => 'Quote di ammortamento',
                10 => 'Beni Strumentali inferiori a 516,00 euro',
                11 => 'Altri costi',
            ];

            // costruisci le "pagine"
            $pagine = [];
            foreach ($targets as $t) {
                // Tabella 1: TUTTE le voci tipologia = 1
                $tabGenerale = Riepilogo::getForDataTable(
                    $this->anno,
                    $this->idAssociazione,
                    $t['idConv'] // 'TOT' o id int
                );

                // Tabella 2: costi per sezione (2..11)
                $sezioni = [];
                foreach ($sectionLabels as $tipologiaId => $label) {
                    $righeSezione = RiepilogoCosti::getByTipologia(
                        idTipologia: $tipologiaId,
                        anno: $this->anno,
                        idAssociazione: $this->idAssociazione,
                        idConvenzione: $t['idConv']
                    );

                    // totali sezione
                    $totPrev = 0.0;
                    $totCons = 0.0;
                    foreach ($righeSezione as $r) {
                        $totPrev += (float) ($r->preventivo ?? 0);
                        $totCons += (float) ($r->consuntivo ?? 0);
                    }

                    $sezioni[] = [
                        'label'  => $label,
                        'righe'  => $righeSezione, // Collection
                        'totali' => ['preventivo' => $totPrev, 'consuntivo' => $totCons],
                    ];
                }

                $pagine[] = [
                    'conv_label'    => $t['label'],
                    'tab_generale'  => $tabGenerale,  // array di righe (UI index)
                    'sezioni_costi' => $sezioni,      // array di sezioni
                ];
            }

            // render view
            $html = view('template.pdf_riepiloghi_dati_costi', [
                'associazione' => $associazione,
                'anno'         => $this->anno,
                'pagine'       => $pagine,
            ])->render();

            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

            $path = sprintf(
                'documenti/riepiloghi_dati_costi_%d_%d_%d.pdf',
                $this->idAssociazione,
                $this->anno,
                $this->documentoId
            );

            Storage::disk('public')->put($path, $pdf->output());

            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update([
                    'percorso_file' => $path,
                    'stato'         => 'ready',
                    'generato_il'   => now(),
                    'updated_at'    => now(),
                ]);
        } catch (Throwable $e) {
            Log::error('RiepiloghiDatiECostiPdfJob failed: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);

            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update(['stato' => 'error', 'updated_at' => now()]);

            throw $e;
        }
    }
}
