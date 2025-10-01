<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Models\DocumentoGenerato;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class GeneraCostiRadioPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
    public $tries = 1;        
    public $timeout = 600;    

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
            (new WithoutOverlapping("pdf-costi-radio-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->first();

        // automezzi dell’associazione/anno (per riparto uniforme)
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('CodiceIdentificativo')
            ->get(['Targa','CodiceIdentificativo']);

        $n = max($automezzi->count(), 1);

        $tot = DB::table('costi_radio')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->first();

        $totali = [
            'ManutenzioneApparatiRadio'   => (float)($tot?->ManutenzioneApparatiRadio   ?? 0),
            'MontaggioSmontaggioRadio118' => (float)($tot?->MontaggioSmontaggioRadio118 ?? 0),
            'LocazionePonteRadio'         => (float)($tot?->LocazionePonteRadio         ?? 0),
            'AmmortamentoImpiantiRadio'   => (float)($tot?->AmmortamentoImpiantiRadio   ?? 0),
        ];

        // optional: logga se non c’è record
        if (!$tot) {
            \Log::warning('costi_radio: nessun record', [
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);
        }


        // righe dettaglio per automezzo: riparto uniforme
        $rows = [];
        foreach ($automezzi as $a) {
            $rows[] = [
                'Targa'                      => (string)($a->Targa ?? ''),
                'Codice'                     => (string)($a->CodiceIdentificativo ?? ''),
                'ManutenzioneApparatiRadio'  => round($totali['ManutenzioneApparatiRadio'] / $n, 2),
                'MontaggioSmontaggioRadio118'=> round($totali['MontaggioSmontaggioRadio118'] / $n, 2),
                'LocazionePonteRadio'        => round($totali['LocazionePonteRadio'] / $n, 2),
                'AmmortamentoImpiantiRadio'  => round($totali['AmmortamentoImpiantiRadio'] / $n, 2),
            ];
        }

        $pdf = Pdf::loadView('template.costi_radio', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'rows'         => $rows,
            'tot'          => $totali,
        ])->setPaper('a4','landscape');

        $filename = "distinta_costi_radio_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
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
        Log::error('GeneraCostiRadioPdfJob.php failed: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
