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
use App\Models\Automezzo;
use App\Models\CostoOssigeno;
use App\Models\CostoMaterialeSanitario;
use App\Models\RipartizioneOssigeno;
use App\Models\RipartizioneMaterialeSanitario;

class GeneraImputazioniMaterialeEOssigenoPdfJob implements ShouldQueue
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
            (new WithoutOverlapping("pdf-imp-mat-oss-{$this->idAssociazione}-{$this->anno}"))
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

        // automezzi dell’associazione/anno
        $automezzi = Automezzo::getByAssociazione($this->idAssociazione, $this->anno);
        $numAutomezzi = max(count($automezzi), 1);

        // ----- Materiale sanitario
        $ripMat = RipartizioneMaterialeSanitario::getRipartizione($this->idAssociazione, $this->anno);
        $totBilMat = (float) CostoMaterialeSanitario::getTotale($this->idAssociazione, $this->anno);
        $totInclusiMat = (float) ($ripMat['totale_inclusi'] ?? 0);

        $rowsMat = [];
        foreach ($ripMat['righe'] as $riga) {
            if (!empty($riga['is_totale'])) {
                $rowsMat[] = [
                    'Targa'       => 'TOTALE',
                    'n_servizi'   => (int)($riga['totale'] ?? 0),
                    'percentuale' => $totInclusiMat > 0 ? round(($riga['totale'] ?? 0) / $totInclusiMat * 100, 2) : 0,
                    'importo'     => $totBilMat,
                    'is_totale'   => -1,
                ];
                continue;
            }

            $incluso = (bool)($riga['incluso_riparto'] ?? false);
            $n = (int)($riga['totale'] ?? 0);
            $perc = $incluso && $totInclusiMat > 0 ? round($n / $totInclusiMat * 100, 2) : 0;
            $imp  = $incluso && $totInclusiMat > 0 ? round($n / $totInclusiMat * $totBilMat, 2) : 0;

            $rowsMat[] = [
                'Targa'       => (string)($riga['Targa'] ?? ''),
                'n_servizi'   => $n,
                'percentuale' => $perc,
                'importo'     => $imp,
                'is_totale'   => 0,
            ];
        }

        // ----- Ossigeno
        $ripOss = RipartizioneOssigeno::getRipartizione($this->idAssociazione, $this->anno);
        $totBilOss = (float) CostoOssigeno::getTotale($this->idAssociazione, $this->anno);
        $totInclusiOss = (float) ($ripOss['totale_inclusi'] ?? 0);

        $rowsOss = [];
        foreach ($ripOss['righe'] as $riga) {
            if (!empty($riga['is_totale'])) {
                $rowsOss[] = [
                    'Targa'       => 'TOTALE',
                    'n_servizi'   => (int)($riga['totale'] ?? 0),
                    'percentuale' => 100,
                    'importo'     => $totBilOss,
                    'is_totale'   => -1,
                ];
                continue;
            }

            $incluso = (bool)($riga['incluso_riparto'] ?? false);
            $n = (int)($riga['totale'] ?? 0);
            $perc = $incluso && $totInclusiOss > 0 ? round($n / $totInclusiOss * 100, 2) : 0;
            $imp  = $incluso && $totInclusiOss > 0 ? round($n / $totInclusiOss * $totBilOss, 2) : 0;

            $rowsOss[] = [
                'Targa'       => (string)($riga['Targa'] ?? ''),
                'n_servizi'   => $n,
                'percentuale' => $perc,
                'importo'     => $imp,
                'is_totale'   => 0,
            ];
        }

        // render
        $pdf = Pdf::loadView('template.imputazioni_materiale_ossigeno', [
            'anno'         => $this->anno,
            'associazione' => $associazione,

            'mat' => [
                'titolo'          => 'IMPUTAZIONE COSTI MATERIALE SANITARIO DI CONSUMO',
                'totale_bilancio' => $totBilMat,
                'rows'            => $rowsMat,
                'tot_servizi'     => array_reduce($rowsMat, fn($c,$r)=>$c+($r['n_servizi']??0), 0),
            ],

            'oss' => [
                'titolo'          => 'IMPUTAZIONE COSTI OSSIGENO',
                'totale_bilancio' => $totBilOss,
                'rows'            => $rowsOss,
                'tot_servizi'     => array_reduce($rowsOss, fn($c,$r)=>$c+($r['n_servizi']??0), 0),
            ],
        ])->setPaper('a4','portrait');

        $filename = "imputazioni_materiale_ossigeno_{$this->idAssociazione}_{$this->anno}_".now()->timestamp.".pdf";
        $path = "documenti/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        $doc->update([
            'nome_file'     => $filename,
            'percorso_file' => $path,
            'generato_il'   => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraImputazioniMaterialeEOssigenoPdfJob failed: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
