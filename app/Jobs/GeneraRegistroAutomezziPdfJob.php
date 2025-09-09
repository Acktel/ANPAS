<?php

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
use App\Models\Automezzo;

class GeneraRegistroAutomezziPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('pdf'); // stessa coda del riepilogo
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("pdf-reg-automezzi-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)
                ->dontRelease(),
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

            $automezzi = Automezzo::getByAssociazione($this->idAssociazione, $this->anno);

            $pdf = Pdf::loadView('template.registro_automezzi', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                'automezzi'    => $automezzi,
            ])->setPaper('a4','landscape');

            $filename = "registro_automezzi_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
            $path     = "documenti/{$filename}";

            Storage::disk('public')->put($path, $pdf->output());

            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraRegistroAutomezziPdfJob error: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);
            throw $e;
        }
    }
}
