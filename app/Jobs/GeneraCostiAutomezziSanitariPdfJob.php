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
use Illuminate\Bus\Batchable;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

use App\Models\DocumentoGenerato;

class GeneraCostiAutomezziSanitariPdfJob implements ShouldQueue
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
            (new WithoutOverlapping("pdf-costi-automezzi-sanitari-{$this->idAssociazione}-{$this->anno}"))
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

            // Automezzi dell'associazione per l'anno richiesto (inclusi nel riparto)
            $rowsDB = DB::table('automezzi as a')
                ->leftJoin('costi_automezzi as c', function ($j) {
                    $j->on('c.idAutomezzo', '=', 'a.idAutomezzo');
                })
                ->where('a.idAssociazione', $this->idAssociazione)
                ->where('a.idAnno', $this->anno)
                ->orderBy('a.CodiceIdentificativo')
                ->get([
                    'a.idAutomezzo',
                    'a.Targa',
                    'a.CodiceIdentificativo',
                    // costi:
                    'c.LeasingNoleggio',
                    'c.Assicurazione',
                    'c.ManutenzioneOrdinaria',
                    'c.ManutenzioneStraordinaria',
                    'c.RimborsiAssicurazione',
                    'c.PuliziaDisinfezione',
                    'c.Carburanti',
                    'c.RimborsiUTF',
                    'c.Additivi',
                    'c.InteressiPassivi',
                    'c.AltriCostiMezzi',
                    'c.ManutenzioneSanitaria',
                    'c.LeasingSanitaria',
                    'c.AmmortamentoMezzi',
                    'c.AmmortamentoSanitaria',
                ]);

            // TOTALE per colonne
            $tot = [
                'LeasingNoleggio'        => 0.0,
                'Assicurazione'          => 0.0,
                'ManutenzioneOrdinaria'  => 0.0,
                'ManutenzioneStraordinaria' => 0.0,
                'RimborsiAssicurazione'  => 0.0,
                'PuliziaDisinfezione'    => 0.0,
                'Carburanti'             => 0.0,
                'RimborsiUTF'            => 0.0,
                'Additivi'               => 0.0,
                'InteressiPassivi'       => 0.0,
                'AltriCostiMezzi'        => 0.0,
                'ManutenzioneSanitaria'  => 0.0,
                'LeasingSanitaria'       => 0.0,
                'AmmortamentoMezzi'      => 0.0,
                'AmmortamentoSanitaria'  => 0.0,
            ];

            $rows = [];
            foreach ($rowsDB as $r) {
                // cast + default 0
                $map = fn($v) => (float) ($v ?? 0);

                $row = [
                    'Targa'                 => (string) ($r->Targa ?? ''),
                    'Codice'                => (string) ($r->CodiceIdentificativo ?? ''),
                    'LeasingNoleggio'       => $map($r->LeasingNoleggio),
                    'Assicurazione'         => $map($r->Assicurazione),
                    'ManutenzioneOrdinaria' => $map($r->ManutenzioneOrdinaria),
                    'ManutenzioneStraordinaria' => $map($r->ManutenzioneStraordinaria),
                    'RimborsiAssicurazione' => $map($r->RimborsiAssicurazione),
                    'PuliziaDisinfezione'   => $map($r->PuliziaDisinfezione),
                    'Carburanti'            => $map($r->Carburanti),
                    'RimborsiUTF'           => $map($r->RimborsiUTF),
                    'Additivi'              => $map($r->Additivi),
                    'InteressiPassivi'      => $map($r->InteressiPassivi),
                    'AltriCostiMezzi'       => $map($r->AltriCostiMezzi),
                    'ManutenzioneSanitaria' => $map($r->ManutenzioneSanitaria),
                    'LeasingSanitaria'      => $map($r->LeasingSanitaria),
                    'AmmortamentoMezzi'     => $map($r->AmmortamentoMezzi),
                    'AmmortamentoSanitaria' => $map($r->AmmortamentoSanitaria),
                ];

                foreach (array_keys($tot) as $k) {
                    $tot[$k] += $row[$k];
                }

                $rows[] = $row;
            }

            // riga totale
            $rowsTot = array_merge([
                'Targa'  => 'TOTALE',
                'Codice' => '',
                'is_totale' => true,
            ], $tot);

            $pdf = Pdf::loadView('template.costi_automezzi_sanitari', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                'rows'         => $rows,
                'tot'          => $rowsTot,
            ])->setPaper('a4', 'landscape');

            $filename = "distinta_costi_automezzi_sanitari_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
            $path     = "documenti/{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraCostiAutomezziSanitariPdfJob error: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraCostiAutomezziSanitariPdfJob failed: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
