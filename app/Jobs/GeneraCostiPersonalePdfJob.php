<?php
// app/Jobs/GeneraCostiPersonalePdfJob.php

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
use App\Models\Dipendente;
use App\Models\CostiPersonale;
use App\Models\RipartizionePersonale;

class GeneraCostiPersonalePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /** Evitiamo retry infiniti sui bug logici */
    public $tries = 1;
    /** Il render PDF può richiedere più tempo */
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
            (new WithoutOverlapping("pdf-costi-personale-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)
                ->dontRelease(),
        ];
    }

    public function handle(): void
    {
        try {
            /** @var DocumentoGenerato $doc */
            $doc = DocumentoGenerato::findOrFail($this->documentoId);

            $associazione = DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->first();

            // Convenzioni come Collection di oggetti { idConvenzione, Convenzione }
            $convenzioni = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('Convenzione')
                ->get(['idConvenzione', 'Convenzione']);

            // Dipendenti dell’associazione/anno
            $dip = Dipendente::getByAssociazione($this->idAssociazione, $this->anno);

            // Costi per anno, keyBy idDipendente
            $costi = CostiPersonale::getAllByAnno($this->anno)->keyBy('idDipendente');

            // Ripartizioni (in coda: niente Auth -> filtra per associazione)
            $rip = RipartizionePersonale::getAll(
                anno: $this->anno,
                user: null,
                idAssociazioneFiltro: $this->idAssociazione
            )->groupBy('idDipendente');

            // Row base: garantisce che ogni riga abbia sempre tutte le chiavi attese
            $BASE_ROW = [
                'Dipendente'         => '',
                'Retribuzioni'       => 0.0,
                'OneriSocialiInps'   => 0.0,
                'OneriSocialiInail'  => 0.0,
                'TFR'                => 0.0,
                'Consulenze'         => 0.0,
                'Totale'             => 0.0,
                'conv'               => [],
            ];

            // Helper: normalizza una riga costi (gestisce null e schema vecchio)
            $norm = function (object|array|null $row): array {
                $src = (array) ($row ?? []);

                // mapping schema vecchio -> nuovo
                // - se esiste 'OneriSociali' unico (vecchio), attribuiscilo a INPS
                $inps  = (float)($src['OneriSocialiInps']  ?? $src['oneri_sociali_inps']  ?? $src['OneriSociali'] ?? $src['oneri_sociali'] ?? 0);
                $inail = (float)($src['OneriSocialiInail'] ?? $src['oneri_sociali_inail'] ?? 0);

                return [
                    'Retribuzioni'      => (float)($src['Retribuzioni'] ?? $src['retribuzioni'] ?? 0),
                    'OneriSocialiInps'  => $inps,
                    'OneriSocialiInail' => $inail,
                    'TFR'               => (float)($src['TFR'] ?? $src['tfr'] ?? 0),
                    'Consulenze'        => (float)($src['Consulenze'] ?? $src['consulenze'] ?? 0),
                ];
            };

            // ———— GRUPPI QUALIFICHE
            $isAB = function ($d) {
                $q  = mb_strtolower($d->Qualifica ?? '');
                $lv = mb_strtolower($d->LivelloMansione ?? '');
                return str_contains($q, 'autist') || str_contains($q, 'barell') || str_contains($lv, 'c4');
            };

            $gruppoAB = $dip->filter($isAB);
            $altri    = $dip->reject($isAB)->groupBy(fn ($d) => trim($d->Qualifica ?? 'Altro'));

            // ———— TABELLA A&B (importi per convenzione)
            $abRows = [];
            $abTotalsPerConv = array_fill_keys($convenzioni->pluck('idConvenzione')->all(), 0.0);
            $abTotals = [
                'Retribuzioni'     => 0.0,
                'OneriSocialiInps' => 0.0,
                'OneriSocialiInail'=> 0.0,
                'TFR'              => 0.0,
                'Consulenze'       => 0.0,
                'Totale'           => 0.0,
            ];

            foreach ($gruppoAB as $d) {
                $c = $norm($costi->get($d->idDipendente));

                $retrib = $c['Retribuzioni'];
                $inps   = $c['OneriSocialiInps'];
                $inail  = $c['OneriSocialiInail'];
                $tfr    = $c['TFR'];
                $cons   = $c['Consulenze'];
                $tot    = $retrib + $inps + $inail + $tfr + $cons;

                $row = array_replace($BASE_ROW, [
                    'Dipendente'         => trim(($d->DipendenteCognome ?? '') . ' ' . ($d->DipendenteNome ?? '')),
                    'Retribuzioni'       => $retrib,
                    'OneriSocialiInps'   => $inps,
                    'OneriSocialiInail'  => $inail,
                    'TFR'                => $tfr,
                    'Consulenze'         => $cons,
                    'Totale'             => $tot,
                ]);

                $ripD   = $rip->get($d->idDipendente, collect());
                $oreTot = max(0.0, (float)$ripD->sum('OreServizio'));

                foreach ($convenzioni as $cconv) {
                    $riga = $ripD->firstWhere('idConvenzione', $cconv->idConvenzione);
                    $perc = ($oreTot > 0 && $riga) ? ($riga->OreServizio / $oreTot) : 0.0;
                    $imp  = round($tot * $perc, 2);

                    $row['conv'][$cconv->idConvenzione] = $imp;
                    $abTotalsPerConv[$cconv->idConvenzione] += $imp;
                }

                foreach ($abTotals as $k => $_) {
                    $abTotals[$k] += $row[$k];
                }

                $abRows[] = $row;
            }

            $abRowsTotal = array_replace($BASE_ROW, [
                'Dipendente'         => 'TOTALE',
                'Retribuzioni'       => $abTotals['Retribuzioni'],
                'OneriSocialiInps'   => $abTotals['OneriSocialiInps'],
                'OneriSocialiInail'  => $abTotals['OneriSocialiInail'],
                'TFR'                => $abTotals['TFR'],
                'Consulenze'         => $abTotals['Consulenze'],
                'Totale'             => $abTotals['Totale'],
                'conv'               => $abTotalsPerConv,
                'is_total'           => true,
            ]);

            // ———— TABELLE SEMPLICI (una per qualifica)
            $blocchiSemplici = [];
            foreach ($altri as $qualifica => $lista) {
                $rows = [];
                $tot  = [
                    'Retribuzioni'     => 0.0,
                    'OneriSocialiInps' => 0.0,
                    'OneriSocialiInail'=> 0.0,
                    'TFR'              => 0.0,
                    'Consulenze'       => 0.0,
                    'Totale'           => 0.0,
                ];

                foreach ($lista as $d) {
                    $c = $norm($costi->get($d->idDipendente));

                    $retrib = $c['Retribuzioni'];
                    $inps   = $c['OneriSocialiInps'];
                    $inail  = $c['OneriSocialiInail'];
                    $tfr    = $c['TFR'];
                    $cons   = $c['Consulenze'];
                    $totale = $retrib + $inps + $inail + $tfr + $cons;

                    $r = array_replace($BASE_ROW, [
                        'Dipendente'         => trim(($d->DipendenteCognome ?? '') . ' ' . ($d->DipendenteNome ?? '')),
                        'Retribuzioni'       => $retrib,
                        'OneriSocialiInps'   => $inps,
                        'OneriSocialiInail'  => $inail,
                        'TFR'                => $tfr,
                        'Consulenze'         => $cons,
                        'Totale'             => $totale,
                    ]);

                    foreach ($tot as $k => $_) {
                        $tot[$k] += $r[$k];
                    }

                    $rows[] = $r;
                }

                $rows[] = array_replace($BASE_ROW, [
                    'Dipendente' => 'TOTALE',
                    'is_total'   => true,
                ] + $tot);

                $blocchiSemplici[] = [
                    'titolo' => $qualifica ?: 'Altro',
                    'rows'   => $rows,
                ];
            }

            // ———— Render PDF
            $pdf = Pdf::loadView('template.costi_personale', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                'convenzioni'  => $convenzioni, // Collection id+nome
                'ab'           => ['rows' => $abRows, 'tot' => $abRowsTotal],
                'semplici'     => $blocchiSemplici,
            ])->setPaper('a4', 'landscape');

            $filename = "costi_personale_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
            $path     = "documenti/{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraCostiPersonalePdfJob failed: ' . $e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
                'trace'          => $e->getTraceAsString(),
            ]);
            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraCostiPersonalePdfJob failed (failed callback): ' . $e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
