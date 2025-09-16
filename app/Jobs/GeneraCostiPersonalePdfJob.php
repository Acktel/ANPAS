<?php
// app/Jobs/GeneraCostiPersonalePdfJob.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;
use App\Models\DocumentoGenerato;
use App\Models\Dipendente;
use App\Models\CostiPersonale;
use App\Models\Convenzione;
use App\Models\RipartizionePersonale;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class GeneraCostiPersonalePdfJob implements ShouldQueue
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
            (new WithoutOverlapping("pdf-costi-personale-{$this->idAssociazione}-{$this->anno}"))
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

        // Convenzioni ordinate (per la tabella A&B)
        $convenzioni = Convenzione::getByAssociazioneAnno($this->idAssociazione, $this->anno)
            ->sortBy('idConvenzione')
            ->values(['idConvenzione','Convenzione']);

        // Dipendenti dell’associazione/anno
        $dip = Dipendente::getByAssociazione($this->idAssociazione, $this->anno);

        // Mappa costi e ripartizioni
        $costi = CostiPersonale::getAllByAnno($this->anno)->keyBy('idDipendente');
        $rip   = RipartizionePersonale::getAll($this->anno, auth()->user(), $this->idAssociazione)
                 ->groupBy('idDipendente'); // -> Collection keyed by idDipendente

        // ———— GRUPPI QUALIFICHE
        $isAB = function($d) {
            $q = mb_strtolower($d->Qualifica ?? '');
            $lv= mb_strtolower($d->LivelloMansione ?? '');
            return str_contains($q,'autist') || str_contains($q,'barell') || str_contains($lv,'c4');
        };

        $gruppoAB = $dip->filter($isAB);
        $altri     = $dip->reject($isAB)->groupBy(fn($d) => trim($d->Qualifica ?? 'Altro'));

        // ———— TABELLA A&B (importi per convenzione)
        $abRows = [];
        $abTotalsPerConv = array_fill_keys($convenzioni->pluck('idConvenzione')->all(), 0.0);
        $abTotals = ['Retribuzioni'=>0.0,'OneriSociali'=>0.0,'TFR'=>0.0,'Consulenze'=>0.0,'Totale'=>0.0];

        foreach ($gruppoAB as $d) {
            $c = $costi->get($d->idDipendente);
            $retrib = (float)($c->Retribuzioni ?? 0);
            $oneri  = (float)($c->OneriSociali ?? 0);
            $tfr    = (float)($c->TFR ?? 0);
            $cons   = (float)($c->Consulenze ?? 0);
            $tot    = $retrib + $oneri + $tfr + $cons;

            $row = [
                'Dipendente'   => trim(($d->DipendenteCognome ?? '').' '.($d->DipendenteNome ?? '')),
                'Retribuzioni' => $retrib,
                'OneriSociali' => $oneri,
                'TFR'          => $tfr,
                'Consulenze'   => $cons,
                'Totale'       => $tot,
                'conv'         => [],
            ];

            $ripD = $rip->get($d->idDipendente, collect());
            $oreTot = max(0.0, (float)$ripD->sum('OreServizio'));

            foreach ($convenzioni as $cconv) {
                $riga = $ripD->firstWhere('idConvenzione', $cconv->idConvenzione);
                $perc = ($oreTot > 0 && $riga) ? ($riga->OreServizio / $oreTot) : 0.0;
                $imp  = round($tot * $perc, 2);

                $row['conv'][$cconv->idConvenzione] = $imp;
                $abTotalsPerConv[$cconv->idConvenzione] += $imp;
            }

            foreach ($abTotals as $k => $v) $abTotals[$k] += $row[$k];
            $abRows[] = $row;
        }

        $abRowsTotal = [
            'Dipendente'   => 'TOTALE',
            'Retribuzioni' => $abTotals['Retribuzioni'],
            'OneriSociali' => $abTotals['OneriSociali'],
            'TFR'          => $abTotals['TFR'],
            'Consulenze'   => $abTotals['Consulenze'],
            'Totale'       => $abTotals['Totale'],
            'conv'         => $abTotalsPerConv,
            'is_total'     => true,
        ];

        // ———— TABELLE SEMPLICI (una per qualifica)
        $blocchiSemplici = [];
        foreach ($altri as $qualifica => $lista) {
            $rows = [];
            $tot  = ['Retribuzioni'=>0.0,'OneriSociali'=>0.0,'TFR'=>0.0,'Consulenze'=>0.0,'Totale'=>0.0];

            foreach ($lista as $d) {
                $c = $costi->get($d->idDipendente);
                $retrib = (float)($c->Retribuzioni ?? 0);
                $oneri  = (float)($c->OneriSociali ?? 0);
                $tfr    = (float)($c->TFR ?? 0);
                $cons   = (float)($c->Consulenze ?? 0);
                $totale = $retrib + $oneri + $tfr + $cons;

                $r = [
                    'Dipendente'   => trim(($d->DipendenteCognome ?? '').' '.($d->DipendenteNome ?? '')),
                    'Retribuzioni' => $retrib,
                    'OneriSociali' => $oneri,
                    'TFR'          => $tfr,
                    'Consulenze'   => $cons,
                    'Totale'       => $totale,
                ];
                foreach ($tot as $k=>$_) $tot[$k] += $r[$k];
                $rows[] = $r;
            }

            $rows[] = array_merge(['Dipendente' => 'TOTALE', 'is_total' => true], $tot);

            $blocchiSemplici[] = [
                'titolo' => $qualifica ?: 'Altro',
                'rows'   => $rows,
            ];
        }

        // ———— Render PDF
        $pdf = Pdf::loadView('template.costi_personale', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'ab'           => ['rows' => $abRows, 'tot' => $abRowsTotal],
            'semplici'     => $blocchiSemplici,
        ])->setPaper('a4','landscape');

        $filename = "costi_personale_{$this->idAssociazione}_{$this->anno}_".now()->timestamp.".pdf";
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
        Log::error('GeneraCostiPersonalePdfJob.php failed: '.$e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
