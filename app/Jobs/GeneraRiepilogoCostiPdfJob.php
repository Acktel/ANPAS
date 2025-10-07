<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batchable;
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

use App\Models\DocumentoGenerato;
use App\Models\RiepilogoCosti;

class GeneraRiepilogoCostiPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /** Evita retry multipli su errori logici */
    public $tries = 1;
    /** DomPDF può richiedere tempo */
    public $timeout = 600;
    /** Niente backoff tra retry (tanto tries=1) */
    public $backoff = 0;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('pdf');
    }

    public function middleware(): array
    {
        $key = "pdf-riepilogo-costi-{$this->idAssociazione}-{$this->anno}";
        return [
            (new WithoutOverlapping($key))->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        // Imposta subito lo stato, utile per il polling UI
        DB::table('documenti_generati')->where('id', $this->documentoId)->update([
            'stato'      => 'processing',
            'updated_at' => now(),
        ]);

        $associazioneNome = '';

        try {
            // Carico documento
            /** @var DocumentoGenerato $doc */
            $doc = DocumentoGenerato::findOrFail($this->documentoId);

            // Nome associazione (stringa semplice)
            $associazioneNome = (string) (DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->value('Associazione') ?? '');

            // Verifica che la view esista (se no l’eccezione è brutta)
            $viewName = 'template.pdf_riepiloghi_dati_costi';
            if (! view()->exists($viewName)) {
                throw new \RuntimeException("View '{$viewName}' non trovata.");
            }

            // Titoli sezioni
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

            // Helper: costruisce blocco sezioni per TOT o singola convenzione
            $buildBlock = function (int|string $idConvenzione) use ($tipologieIds, $sezioniTitoli) {
                $sezioni = [];
                $totPrev = 0.0;
                $totCons = 0.0;

                foreach ($tipologieIds as $tip) {
                    // ATTENZIONE: assicurati che questo metodo ritorni una Collection
                    $rows = RiepilogoCosti::getByTipologia(
                        $tip,
                        $this->anno,
                        $this->idAssociazione,
                        $idConvenzione
                    );

                    // Hard-cast numeri per evitare “string to float”
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

            // Blocchi per convenzione
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

            // Mappatura per la view
            $pagine = [];
            foreach ($blocks as $b) {
                $sezioniCosti = [];
                foreach ($b['sezioni'] as $sec) {
                    $righe = [];
                    foreach ($sec['rows'] as $r) {
                        $righe[] = (object) [
                            'descrizione' => (string) ($r->descrizione ?? ''),
                            'preventivo'  => (float)  ($r->preventivo  ?? 0),
                            'consuntivo'  => (float)  ($r->consuntivo  ?? 0),
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
                    'conv_label'    => $b['nome'],
                    'tab_generale'  => [],
                    'sezioni_costi' => $sezioniCosti,
                ];
            }

            // Render PDF
            $pdf = Pdf::loadView($viewName, [
                'anno'           => $this->anno,
                'idAssociazione' => $this->idAssociazione,
                // Passo sia stringa che oggetto per massima compatibilità con la view
                'associazione'   => $associazioneNome,
                'associazioneObj'=> (object)['Associazione' => $associazioneNome],
                'totaleTot'      => $totaleTot,
                'pagine'         => $pagine,
            ])->setPaper('a4', 'landscape');

            // Salvataggio
            $filename = "riepilogo_costi_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
            $path     = "documenti/{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            // Aggiorno documento
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
            Log::error('GeneraRiepilogoCostiPdfJob error', [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
                'message'        => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            // Stato errore per la UI
            DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                'stato'      => 'error',
                'updated_at' => now(),
            ]);

            // Segnalo il fallimento al worker (evita rethrow che poi ti stampa solo MaxAttemptsExceeded)
            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraRiepilogoCostiPdfJob failed callback: '.$e->getMessage(), [
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
