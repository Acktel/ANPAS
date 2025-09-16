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
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DocumentoGenerato;
use App\Models\RapportoRicavo;

class GeneraRapportiRicaviPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

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
        return [
            (new WithoutOverlapping("pdf-rapporti-ricavi-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        // Intestazione
        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->first();

        // Convenzioni (ordine stabile)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione','Convenzione')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get();

        // Ricavi per associazione/anno con join convenzioni
        $ricavi = RapportoRicavo::getWithConvenzioni($this->anno, $this->idAssociazione);
        $totale = (float) $ricavi->sum('Rimborso');

        // riga unica
        $row = [
            'TotaleEsercizio' => $totale,
        ];
        foreach ($convenzioni as $c) {
            $key = 'c'.$c->idConvenzione;
            $val = (float) optional($ricavi->firstWhere('idConvenzione', $c->idConvenzione))->Rimborso ?? 0.0;
            $row["{$key}_rimborso"] = $val;
            $row["{$key}_percent"]  = $totale > 0 ? round($val / $totale * 100, 2) : 0.0;
        }

        // render pdf
        $pdf = Pdf::loadView('template.rapporti_ricavi', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'row'          => $row,
        ])->setPaper('a4','landscape');

        $filename = "rapporti_ricavi_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
        $path     = "documenti/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        $doc->update([
            'nome_file'     => $filename,
            'percorso_file' => $path,
            'generato_il'   => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraRapportiRicaviPdfJob failed: '.$e->getMessage(), [
            'documentoId'=>$this->documentoId,'assoc'=>$this->idAssociazione,'anno'=>$this->anno
        ]);
    }
}
