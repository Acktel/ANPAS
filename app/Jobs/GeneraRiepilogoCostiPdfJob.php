<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('pdf'); // coda dedicata
    }

    public function middleware(): array
    {
        // lock specifico; NO dontRelease per evitare scarti silenziosi
        $key = "pdf-riepilogo-costi-{$this->idAssociazione}-{$this->anno}";
        return [
            (new WithoutOverlapping($key))
                ->expireAfter(120),
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        // Stato per il polling UI
        DB::table('documenti_generati')->where('id', $this->documentoId)->update([
            'stato'      => 'processing',
            'updated_at' => now(),
        ]);

        // Definisci SEMPRE la variabile nello scope
        $associazioneNome = '';

        try {
            Log::debug('RIEPILOGO: start', [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);

            // Nome associazione come STRINGA
            $associazioneNome = (string) (DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->value('Associazione') ?? '');

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
            $tipologieIds = array_keys($sezioniTitoli);

            // Builder blocco (TOT o per singola convenzione)
            $buildBlock = function (int|string $idConvenzione) use ($tipologieIds, $sezioniTitoli) {
                $sezioni = [];
                $totPrev = 0.0;
                $totCons = 0.0;

                foreach ($tipologieIds as $tip) {
                    $rows = RiepilogoCosti::getByTipologia(
                        $tip,
                        $this->anno,
                        $this->idAssociazione,
                        $idConvenzione
                    ); // Collection di stdClass {descrizione, preventivo, consuntivo}

                    $sumPrev = (float) $rows->sum(fn($r) => (float) ($r->preventivo ?? 0));
                    $sumCons = (float) $rows->sum(fn($r) => (float) ($r->consuntivo ?? 0));

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

            // Blocco TOTALE
            [$totSez, $totPrev, $totCons] = $buildBlock('TOT');
            $blocks = [[
                'nome'     => 'TOTALE',
                'sezioni'  => $totSez,
                'totPrev'  => $totPrev,
                'totCons'  => $totCons,
            ]];
            $totaleTot = ['prev' => $totPrev, 'cons' => $totCons];

            // Blocchi per ciascuna convenzione
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

            // Mapping blocks -> pagine per la view compatta
            $pagine = [];
            foreach ($blocks as $b) {
                $sezioniCosti = [];
                foreach ($b['sezioni'] as $sec) {
                    $righe = [];
                    foreach ($sec['rows'] as $r) {
                        $righe[] = (object) [
                            'descrizione' => $r->descrizione ?? '',
                            'preventivo'  => (float) ($r->preventivo ?? 0),
                            'consuntivo'  => (float) ($r->consuntivo ?? 0),
                        ];
                    }

                    $sezioniCosti[] = [
                        'label'  => $sec['titolo'],
                        'righe'  => $righe,
                        'totali' => [
                            'preventivo' => (float) ($sec['sumPrev'] ?? 0),
                            'consuntivo' => (float) ($sec['sumCons'] ?? 0),
                        ],
                    ];
                }

                $pagine[] = [
                    'conv_label'    => $b['nome'],   // "TOTALE" o nome convenzione
                    'tab_generale'  => [],           // lasciato vuoto se non usi il blocco dati
                    'sezioni_costi' => $sezioniCosti,
                ];
            }

            Log::debug('RIEPILOGO: render view', ['view' => 'template.pdf_riepiloghi_dati_costi']);

            // Render PDF
            $pdf = Pdf::loadView('template.pdf_riepiloghi_dati_costi', [
                'anno'           => $this->anno,
                'idAssociazione' => $this->idAssociazione,
                'associazione'   => $associazioneNome, // <— allineato al nome che la view usa
                'totaleTot'      => $totaleTot,
                'pagine'         => $pagine,
            ])->setPaper('a4', 'landscape');
            Log::debug('RIEPILOGO: salvataggio PDF');

            // Salvataggio
            $filename = "riepilogo_costi_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
            $path     = "documenti/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            // Aggiorna il record documento
            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
                'stato'         => 'ready',
                'updated_at'    => now(),
            ]);

            Log::info('RIEPILOGO: generato OK', [
                'documentoId' => $this->documentoId,
                'path'        => $path,
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraRiepilogoCostiPdfJob error: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
                'trace'          => $e->getTraceAsString(),
            ]);

            // Stato errore per la UI
            DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                'stato'      => 'error',
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraRiepilogoCostiPdfJob failed callback: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);

        // Fallback stato
        DB::table('documenti_generati')->where('id', $this->documentoId)->update([
            'stato'      => 'error',
            'updated_at' => now(),
        ]);
    }
}
