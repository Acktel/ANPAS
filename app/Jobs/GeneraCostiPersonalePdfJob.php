<?php
// app/Jobs/GeneraCostiPersonalePdfJob.php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

use App\Models\DocumentoGenerato;
use App\Models\Dipendente;
use App\Models\CostiPersonale;
use App\Models\RipartizionePersonale;
use App\Models\CostiMansioni;

class GeneraCostiPersonalePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $tries   = 1;
    public $timeout = 600;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        // Stato processing
        DB::table('documenti_generati')->where('id', $this->documentoId)->update([
            'stato'      => 'processing',
            'updated_at' => now(),
        ]);

        $lockKey = "pdf-costi-personale-{$this->idAssociazione}-{$this->anno}";
        $lock    = Cache::lock($lockKey, 180);

        try {
            if (! $lock->get()) {
                Log::warning("GeneraCostiPersonalePdfJob: lock attivo ($lockKey), salto esecuzione.");
                return;
            }

            /** @var DocumentoGenerato $doc */
            $doc = DocumentoGenerato::findOrFail($this->documentoId);

            $associazione = DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->first();

            // Convenzioni (id+nome) ordinate
            $convenzioni = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('ordinamento')->orderBy('idConvenzione')
                ->get(['idConvenzione', 'Convenzione']);

            $convIds  = $convenzioni->pluck('idConvenzione')->all();
            $convById = $convenzioni->pluck('Convenzione', 'idConvenzione')->map(fn($v)=>(string)$v)->all();

            // Dipendenti dell’associazione/anno
            $dip = Dipendente::getByAssociazione($this->idAssociazione, $this->anno);

            // Costi anno (somma base+diretti) keyBy idDipendente
            $costi = CostiPersonale::getAllByAnno($this->anno)->keyBy('idDipendente');

            // Ripartizioni servizi per convenzione, filtrate per associazione
            $rip = RipartizionePersonale::getAll(
                anno: $this->anno,
                user: null,
                idAssociazioneFiltro: $this->idAssociazione
            )->groupBy('idDipendente');

            // Pivot qualifiche e nomi
            $qualPivot = DB::table('dipendenti_qualifiche')
                ->select('idDipendente','idQualifica')
                ->get()
                ->groupBy('idDipendente')
                ->map(fn($rows)=>$rows->pluck('idQualifica')->map(fn($v)=>(int)$v)->all());

            $nomiQual = DB::table('qualifiche')->pluck('nome', 'id')->map(fn($v)=>(string)$v);

            // Helper normalizzazione costi (accetta schema misto)
            $normRow = function (object|array|null $row): array {
                $src = (array) ($row ?? []);
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

            // Matcher A&B: nome qualifica o livello “C4” in LivelloMansione
            $isABName = function (string $q): bool {
                $q = mb_strtolower($q);
                return str_contains($q, 'autist') || str_contains($q, 'barell');
            };

            $isABDip = function ($d) use ($isABName): bool {
                $lv = mb_strtolower((string)($d->LivelloMansione ?? ''));
                return str_contains($lv, 'c4')
                    || $isABName((string)($d->Qualifica ?? ''));
            };

            // ======== BLOCCO A&B (quota dei costi attribuita alle qualifiche A/B) ========
            $BASE_ROW = [
                'Dipendente'         => '',
                'Retribuzioni'       => 0.0,
                'OneriSocialiInps'   => 0.0,
                'OneriSocialiInail'  => 0.0,
                'TFR'                => 0.0,
                'Consulenze'         => 0.0,
                'Totale'             => 0.0,
                'conv'               => [], // idConv => importo €
            ];

            $abRows = [];
            $abTotalsPerConvCents = array_fill_keys($convIds, 0); // centesimi
            $abTotals = [
                'Retribuzioni'     => 0.0,
                'OneriSocialiInps' => 0.0,
                'OneriSocialiInail'=> 0.0,
                'TFR'              => 0.0,
                'Consulenze'       => 0.0,
                'Totale'           => 0.0,
            ];

            foreach ($dip as $d) {
                $idDip = (int)$d->idDipendente;

                // costi totali del dipendente (base + diretti) dalle select calcolate
                $c = $normRow($costi->get($idDip));
                $retrib = $c['Retribuzioni'];
                $inps   = $c['OneriSocialiInps'];
                $inail  = $c['OneriSocialiInail'];
                $tfr    = $c['TFR'];
                $cons   = $c['Consulenze'];
                $totDip = $retrib + $inps + $inail + $tfr + $cons;

                if ($totDip <= 0) {
                    continue;
                }

                // percentuali mansioni del dipendente: [idQualifica => pct]
                $pctByQ = CostiMansioni::getPercentuali($idDip, $this->anno); // può essere vuoto
                $dipQualIds = $qualPivot[$idDip] ?? [];

                // normalizza percentuali:
                // - se pctByQ presente: usale solo per le qualifiche effettivamente collegate, normalizzate a 100
                // - se assente:
                //      • 0 qualifiche: 100% “Altro” (no A&B)
                //      • 1 qualifica: 100% su quella
                //      • N>1 qualifiche: equi-riparto
                $eff = [];
                if (!empty($pctByQ)) {
                    $sum = 0.0;
                    foreach ($dipQualIds as $qId) {
                        $p = (float)($pctByQ[$qId] ?? 0.0);
                        if ($p > 0) { $eff[$qId] = $p; $sum += $p; }
                    }
                    if ($sum > 0) {
                        foreach ($eff as $qId => $p) $eff[$qId] = $p * (100.0 / $sum);
                    }
                } else {
                    if (count($dipQualIds) === 1) {
                        $eff[$dipQualIds[0]] = 100.0;
                    } elseif (count($dipQualIds) > 1) {
                        $share = 100.0 / count($dipQualIds);
                        foreach ($dipQualIds as $qId) $eff[$qId] = $share;
                    } else {
                        // nessuna qualifica: attribuisco 100% a “Altro” (non A&B)
                        $eff = [];
                    }
                }

                // quota A&B = somma percentuali delle qualifiche che matchano A&B
                $abPct = 0.0;
                foreach ($eff as $qId => $p) {
                    $name = (string)($nomiQual[$qId] ?? '');
                    if ($isABName($name)) $abPct += $p;
                }
                // Se non c’è match sui nomi, mantieni compatibilità col vecchio rilevatore dipendente
                if ($abPct == 0.0 && $isABDip($d)) {
                    $abPct = 100.0; // tutto A&B se il dip è marcatamente A&B a livello record
                }

                if ($abPct <= 0.0) {
                    continue; // nessuna quota A&B per questo dipendente
                }

                $coeff = $abPct / 100.0;

                $retrib_ab = $retrib * $coeff;
                $inps_ab   = $inps   * $coeff;
                $inail_ab  = $inail  * $coeff;
                $tfr_ab    = $tfr    * $coeff;
                $cons_ab   = $cons   * $coeff;
                $tot_ab    = $retrib_ab + $inps_ab + $inail_ab + $tfr_ab + $cons_ab;

                // Ripartizione per convenzione in CENTESIMI secondo OreServizio
                $ripD    = $rip->get($idDip, collect());
                $oreTot  = max(0.0, (float)$ripD->sum('OreServizio'));
                $totCents = (int) round($tot_ab * 100, 0, PHP_ROUND_HALF_UP);

                $prov = []; $rem = []; $sumProv = 0;
                if ($totCents > 0 && $oreTot > 0) {
                    foreach ($convIds as $cid) {
                        $ore = (float) optional($ripD->firstWhere('idConvenzione', $cid))->OreServizio ?? 0.0;
                        $quota = ($totCents * $ore) / $oreTot;
                        $p = (int) floor($quota);
                        $prov[$cid] = $p;
                        $rem[$cid]  = $quota - $p;
                        $sumProv   += $p;
                    }
                    // ridistribuzione residui
                    $diff = $totCents - $sumProv;
                    if ($diff > 0) {
                        uasort($rem, fn($a,$b) => $a===$b ? 0 : ($a>$b ? -1 : 1));
                        foreach (array_keys($rem) as $cid) {
                            if ($diff <= 0) break;
                            $prov[$cid] += 1;
                            $diff--;
                        }
                    }
                } else {
                    foreach ($convIds as $cid) $prov[$cid] = 0;
                }

                // Monta riga A&B
                $row = array_replace($BASE_ROW, [
                    'Dipendente'         => trim(($d->DipendenteCognome ?? '').' '.($d->DipendenteNome ?? '')),
                    'Retribuzioni'       => round($retrib_ab, 2),
                    'OneriSocialiInps'   => round($inps_ab,   2),
                    'OneriSocialiInail'  => round($inail_ab,  2),
                    'TFR'                => round($tfr_ab,    2),
                    'Consulenze'         => round($cons_ab,   2),
                    'Totale'             => round($tot_ab,    2),
                ]);

                foreach ($convIds as $cid) {
                    $euro = round(($prov[$cid] ?? 0) / 100, 2);
                    $row['conv'][$cid] = $euro;
                    $abTotalsPerConvCents[$cid] += (int) ($prov[$cid] ?? 0);
                }

                // totali colonna A&B
                foreach ($abTotals as $k => $_) $abTotals[$k] += $row[$k];

                $abRows[] = $row;
            }

            // Riga totale A&B
            $abRowsTotal = array_replace($BASE_ROW, [
                'Dipendente'         => 'TOTALE',
                'Retribuzioni'       => round($abTotals['Retribuzioni'], 2),
                'OneriSocialiInps'   => round($abTotals['OneriSocialiInps'], 2),
                'OneriSocialiInail'  => round($abTotals['OneriSocialiInail'], 2),
                'TFR'                => round($abTotals['TFR'], 2),
                'Consulenze'         => round($abTotals['Consulenze'], 2),
                'Totale'             => round($abTotals['Totale'], 2),
                'is_total'           => true,
                'conv'               => array_map(fn($c)=>round($c/100,2), $abTotalsPerConvCents),
            ]);

            // ======== BLOCCHI “ALTRI” (una tabella per qualifica, con quota per quella qualifica) ========
            $blocchiSemplici = []; // array di ['titolo'=>nomeQualifica, 'rows'=>[]]
            $accRowsByQual   = []; // idQualifica => rows[]
            $accTotalsByQual = []; // idQualifica => [colonne numeriche]

            foreach ($dip as $d) {
                $idDip = (int)$d->idDipendente;

                $c = $normRow($costi->get($idDip));
                $base = [
                    'retrib' => $c['Retribuzioni'],
                    'inps'   => $c['OneriSocialiInps'],
                    'inail'  => $c['OneriSocialiInail'],
                    'tfr'    => $c['TFR'],
                    'cons'   => $c['Consulenze'],
                ];
                $totDip = array_sum($base);
                if ($totDip <= 0) continue;

                // percentuali effettive per qualifiche del dipendente (come sopra)
                $pctByQ = CostiMansioni::getPercentuali($idDip, $this->anno);
                $dipQualIds = $qualPivot[$idDip] ?? [];

                $eff = [];
                if (!empty($pctByQ)) {
                    $sum = 0.0;
                    foreach ($dipQualIds as $qId) {
                        $p = (float)($pctByQ[$qId] ?? 0.0);
                        if ($p > 0) { $eff[$qId] = $p; $sum += $p; }
                    }
                    if ($sum > 0) foreach ($eff as $qId => $p) $eff[$qId] = $p * (100.0 / $sum);
                } else {
                    if (count($dipQualIds) === 1) {
                        $eff[$dipQualIds[0]] = 100.0;
                    } elseif (count($dipQualIds) > 1) {
                        $share = 100.0 / count($dipQualIds);
                        foreach ($dipQualIds as $qId) $eff[$qId] = $share;
                    } else {
                        // senza qualifiche: salta, non abbiamo un blocco “qualifica”
                        continue;
                    }
                }

                // rip dati servizi del dipendente (per split convenzione)
                $ripD    = $rip->get($idDip, collect());
                $oreTot  = max(0.0, (float)$ripD->sum('OreServizio'));

                foreach ($eff as $qId => $p) {
                    $name = (string)($nomiQual[$qId] ?? 'Altro');
                    // escludi A&B: le loro quote sono già nel blocco A&B
                    $nameLower = mb_strtolower($name);
                    $isABQual  = str_contains($nameLower, 'autist') || str_contains($nameLower, 'barell');
                    if ($isABQual) continue;

                    $coeff = $p / 100.0;

                    $retrib_q = $base['retrib'] * $coeff;
                    $inps_q   = $base['inps']   * $coeff;
                    $inail_q  = $base['inail']  * $coeff;
                    $tfr_q    = $base['tfr']    * $coeff;
                    $cons_q   = $base['cons']   * $coeff;
                    $tot_q    = $retrib_q + $inps_q + $inail_q + $tfr_q + $cons_q;

                    // riparto per convenzione in centesimi
                    $totCents = (int) round($tot_q * 100, 0, PHP_ROUND_HALF_UP);
                    $prov = []; $rem = []; $sumProv = 0;

                    if ($totCents > 0 && $oreTot > 0) {
                        foreach ($convIds as $cid) {
                            $ore = (float) optional($ripD->firstWhere('idConvenzione', $cid))->OreServizio ?? 0.0;
                            $quota = ($totCents * $ore) / $oreTot;
                            $pInt = (int) floor($quota);
                            $prov[$cid] = $pInt;
                            $rem[$cid]  = $quota - $pInt;
                            $sumProv   += $pInt;
                        }
                        $diff = $totCents - $sumProv;
                        if ($diff > 0) {
                            uasort($rem, fn($a,$b)=>$a===$b?0:($a>$b?-1:1));
                            foreach (array_keys($rem) as $cid) {
                                if ($diff<=0) break;
                                $prov[$cid] += 1;
                                $diff--;
                            }
                        }
                    } else {
                        foreach ($convIds as $cid) $prov[$cid] = 0;
                    }

                    // inizializza contenitori della qualifica
                    $accRowsByQual[$qId]   ??= [];
                    $accTotalsByQual[$qId] ??= [
                        'Retribuzioni'=>0.0,'OneriSocialiInps'=>0.0,'OneriSocialiInail'=>0.0,'TFR'=>0.0,'Consulenze'=>0.0,'Totale'=>0.0,
                        'perConvCents' => array_fill_keys($convIds, 0),
                    ];

                    $row = [
                        'Dipendente'        => trim(($d->DipendenteCognome ?? '').' '.($d->DipendenteNome ?? '')),
                        'Retribuzioni'      => round($retrib_q, 2),
                        'OneriSocialiInps'  => round($inps_q,   2),
                        'OneriSocialiInail' => round($inail_q,  2),
                        'TFR'               => round($tfr_q,    2),
                        'Consulenze'        => round($cons_q,   2),
                        'Totale'            => round($tot_q,    2),
                        'conv'              => [],
                    ];
                    foreach ($convIds as $cid) {
                        $euro = round(($prov[$cid] ?? 0)/100, 2);
                        $row['conv'][$cid] = $euro;
                        $accTotalsByQual[$qId]['perConvCents'][$cid] += (int) ($prov[$cid] ?? 0);
                    }

                    foreach (['Retribuzioni','OneriSocialiInps','OneriSocialiInail','TFR','Consulenze','Totale'] as $k) {
                        $accTotalsByQual[$qId][$k] += $row[$k];
                    }

                    $accRowsByQual[$qId][] = $row;
                }
            }

            // monta blocchi semplici (per ogni qualifica non A&B)
            foreach ($accRowsByQual as $qId => $rows) {
                $name = (string)($nomiQual[$qId] ?? 'Altro');

                $totals = $accTotalsByQual[$qId];
                $rows[] = [
                    'Dipendente'        => 'TOTALE',
                    'Retribuzioni'      => round($totals['Retribuzioni'], 2),
                    'OneriSocialiInps'  => round($totals['OneriSocialiInps'], 2),
                    'OneriSocialiInail' => round($totals['OneriSocialiInail'], 2),
                    'TFR'               => round($totals['TFR'], 2),
                    'Consulenze'        => round($totals['Consulenze'], 2),
                    'Totale'            => round($totals['Totale'], 2),
                    'is_total'          => true,
                    'conv'              => array_map(fn($c)=>round($c/100,2), $totals['perConvCents']),
                ];

                $blocchiSemplici[] = [
                    'titolo' => $name,
                    'rows'   => $rows,
                ];
            }

            // Render PDF
            $pdf = Pdf::loadView('template.costi_personale', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                'convenzioni'  => $convenzioni, // Collection id+nome (ordine DB)
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
                'stato'         => 'ready',
                'updated_at'    => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraCostiPersonalePdfJob failed: '.$e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
                'trace'          => $e->getTraceAsString(),
            ]);

            DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                'stato'      => 'error',
                'updated_at' => now(),
            ]);

            $this->fail($e);
        } finally {
            try { $lock?->release(); } catch (LockTimeoutException) {}
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraCostiPersonalePdfJob failed (callback): '.$e->getMessage(), [
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
