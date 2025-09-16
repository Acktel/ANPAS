<?php

namespace App\Jobs;

use App\Services\RipartizioneCostiService;
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

class DistintaImputazioneCostiPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /** ID record in tabella documenti_generati */
    public int $documentoId;

    /**
     * Costruttore: riceve SOLO l'id del documento
     */
    public function __construct(int $documentoId)
    {
        $this->documentoId = $documentoId;
        // mettiamo tutti i job PDF sulla stessa coda
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        // segna "processing"
        DB::table('documenti_generati')
            ->where('id', $this->documentoId)
            ->update([
                'stato'      => 'processing',
                'updated_at' => now(),
            ]);

        // carico il record documento
        $doc = DB::table('documenti_generati')
            ->where('id', $this->documentoId)
            ->first();

        if (!$doc) {
            throw new \RuntimeException("Documento {$this->documentoId} non trovato");
        }

        $idAssociazione = (int) $doc->idAssociazione;
        $anno           = (int) $doc->idAnno;

        // dati tabella
        $payload     = RipartizioneCostiService::distintaImputazioneData($idAssociazione, $anno);
        $convenzioni = $payload['convenzioni'] ?? [];
        $righe       = $payload['data'] ?? [];

        // intestazione
        $associazione = (string) (DB::table('associazioni')
            ->where('idAssociazione', $idAssociazione)
            ->value('Associazione') ?? '');

        // render PDF
        $pdf = Pdf::loadView('template.distinta_imputazione_costi', [
            'anno'         => $anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'righe'        => $righe,
        ])->setPaper('a4', 'landscape');

        // salva su storage pubblico
        $path = sprintf(
            'documenti/distinta_imputazione_costi_%d_%d_%d.pdf',
            $idAssociazione,
            $anno,
            $this->documentoId
        );

        Storage::disk('public')->put($path, $pdf->output());

        // marca come pronto
        DB::table('documenti_generati')
            ->where('id', $this->documentoId)
            ->update([
                'percorso_file' => $path,
                'stato'         => 'ready',
                'generato_il'   => now(),
                'updated_at'    => now(),
            ]);
    }

    public function failed(Throwable $e): void
    {
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
}
