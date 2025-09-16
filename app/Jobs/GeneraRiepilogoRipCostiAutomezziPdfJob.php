<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

use App\Models\DocumentoGenerato;
use App\Services\RipartizioneCostiService;

class GeneraRiepilogoRipCostiAutomezziPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) { $this->onQueue('pdf'); }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("pdf-rip-costi-automezzi-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        try {
            $associazione = DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->first();

            // intestazioni convenzioni in ordine
            $convenzioni = RipartizioneCostiService::convenzioni($this->idAssociazione, $this->anno); // [id=>nome]
            $convNomi    = array_values($convenzioni);

            // 1) tabella TOTALE (somma su tutti i mezzi)
            $rowsTotali = RipartizioneCostiService::calcolaTabellaTotale($this->idAssociazione, $this->anno);
            $colTotals  = array_fill_keys($convNomi, 0.0);
            $grandTot   = 0.0;
            foreach ($rowsTotali as $r) {
                foreach ($convNomi as $c) $colTotals[$c] += (float)($r[$c] ?? 0);
                $grandTot += (float)($r['totale'] ?? 0);
            }

            // 2) elenco mezzi inclusi e loro tabelle
            $mezzi = DB::table('automezzi')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->where('incluso_riparto', 1)
                ->orderBy('CodiceIdentificativo')
                ->get(['idAutomezzo','Targa','CodiceIdentificativo']);

            $sezioniMezzo = []; // array di sezioni: per ogni mezzo -> ['intest'=>..., 'righe'=>...]
            foreach ($mezzi as $m) {
                $righe = RipartizioneCostiService::calcolaRipartizioneTabellaFinale(
                    $this->idAssociazione, $this->anno, (int)$m->idAutomezzo
                );
                // calcolo totali colonna per sicurezza
                $totCol = array_fill_keys($convNomi, 0.0);
                $totTot = 0.0;
                foreach ($righe as $r) {
                    foreach ($convNomi as $c) $totCol[$c] += (float)($r[$c] ?? 0);
                    $totTot += (float)($r['totale'] ?? 0);
                }

                $sezioniMezzo[] = [
                    'targa'  => (string)$m->Targa,
                    'codice' => (string)($m->CodiceIdentificativo ?? ''),
                    'righe'  => $righe,
                    'totCol' => $totCol,
                    'totTot' => $totTot,
                ];
            }

            $pdf = Pdf::loadView('template.rip_costi_automezzi_unico', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                // intestazioni
                'convenzioni'  => $convNomi,
                // sezione 1: totale
                'rowsTotali'   => $rowsTotali,
                'colTotals'    => $colTotals,
                'grandTot'     => $grandTot,
                // sezione 2: per mezzo
                'sezioniMezzo' => $sezioniMezzo,
            ])->setPaper('a4', 'landscape');

            $filename = "rip_costi_automezzi_COMPLETO_{$this->idAssociazione}_{$this->anno}_".now()->timestamp.".pdf";
            $path = "documenti/{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
            ]);

        } catch (Throwable $e) {
            Log::error('GeneraRiepilogoRipCostiAutomezziPdfJob error: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraRiepilogoRipCostiAutomezziPdfJob failed: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
