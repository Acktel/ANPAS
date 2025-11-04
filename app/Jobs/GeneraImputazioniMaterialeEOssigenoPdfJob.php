<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
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

class GeneraImputazioniMaterialeEOssigenoPdfJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('pdf');
    }

    public function middleware(): array {
        return [
            (new WithoutOverlapping("pdf-imp-mat-oss-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        $lock = Cache::lock("pdf-imp-mat-oss-{$this->idAssociazione}-{$this->anno}", 180);
        if (! $lock->get()) return;

        try {
            $associazione = DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->first();

            // ===== Materiale sanitario =====
            $ripMat       = RipartizioneMaterialeSanitario::getRipartizione($this->idAssociazione, $this->anno);
            $totBilMat    = (float) CostoMaterialeSanitario::getTotale($this->idAssociazione, $this->anno);
            $totInclusi   = (int)   ($ripMat['totale_inclusi'] ?? 0);

            // Prepara quote per LRM in centesimi
            $quoteCents   = []; // idAutomezzo => cents (provvisori)
            $remainders   = []; // idAutomezzo => remainder
            $sumProv      = 0;
            $totCents     = (int) round($totBilMat * 100, 0, PHP_ROUND_HALF_UP);

            // costruisci righe base (solo incluse: il model le ha già filtrate)
            $rowsMatBody = [];
            foreach ($ripMat['righe'] as $key => $riga) {
                if (!empty($riga['is_totale'])) continue; // salto totale, lo aggiungo dopo

                $idM = (int)$riga['idAutomezzo'];
                $n   = (int)($riga['totale'] ?? 0);

                // percentuale "di visualizzazione"
                $perc = ($totInclusi > 0) ? round($n / $totInclusi * 100, 2) : 0.00;

                // quota in centesimi con floor + remainder
                if ($totCents > 0 && $totInclusi > 0 && $n > 0) {
                    $q = ($totCents * $n) / $totInclusi;
                    $p = (int) floor($q);
                    $quoteCents[$idM] = $p;
                    $remainders[$idM] = $q - $p;
                    $sumProv += $p;
                } else {
                    $quoteCents[$idM] = 0;
                    $remainders[$idM] = 0.0;
                }

                $rowsMatBody[$idM] = [
                    'Targa'       => (string)($riga['Targa'] ?? ''),
                    'n_servizi'   => $n,
                    'percentuale' => $perc,
                    // importo finale lo metto dopo la redistribuzione
                ];
            }

            // Largest Remainder: ridistribuisco i centesimi mancanti
            $diff = $totCents - $sumProv;
            if ($diff > 0 && !empty($remainders)) {
                arsort($remainders, SORT_NUMERIC); // dal remainder più grande al più piccolo
                foreach (array_keys($remainders) as $idM) {
                    if ($diff <= 0) break;
                    $quoteCents[$idM] += 1;
                    $diff--;
                }
            }

            // ora posso fissare gli importi in € sulle righe
            $rowsMat = [];
            foreach ($rowsMatBody as $idM => $row) {
                $imp = round(($quoteCents[$idM] ?? 0) / 100, 2);
                $rowsMat[] = $row + [
                    'importo'   => $imp,
                    'is_totale' => 0,
                ];
            }

            // riga TOTALE materiale: 100%, importo = totale bilancio
            $rowsMat[] = [
                'Targa'       => 'TOTALE',
                'n_servizi'   => array_sum(array_column($rowsMatBody, 'n_servizi')),
                'percentuale' => 100.00,
                'importo'     => round($totBilMat, 2),
                'is_totale'   => -1,
            ];

            // ===== Ossigeno ===== (stessa logica centesimi)
            $ripOss       = RipartizioneOssigeno::getRipartizione($this->idAssociazione, $this->anno);
            $totBilOss    = (float) CostoOssigeno::getTotale($this->idAssociazione, $this->anno);
            $totInclusiO  = (int)   ($ripOss['totale_inclusi'] ?? 0);
            $quoteOCents  = [];
            $remO         = [];
            $sumProvO     = 0;
            $totOCents    = (int) round($totBilOss * 100, 0, PHP_ROUND_HALF_UP);
            $rowsOBody    = [];

            foreach ($ripOss['righe'] as $riga) {
                if (!empty($riga['is_totale'])) continue;
                $idM = (int)$riga['idAutomezzo'];
                $n   = (int)($riga['totale'] ?? 0);
                $perc = ($totInclusiO > 0) ? round($n / $totInclusiO * 100, 2) : 0.00;

                if ($totOCents > 0 && $totInclusiO > 0 && $n > 0) {
                    $q = ($totOCents * $n) / $totInclusiO;
                    $p = (int) floor($q);
                    $quoteOCents[$idM] = $p;
                    $remO[$idM]        = $q - $p;
                    $sumProvO         += $p;
                } else {
                    $quoteOCents[$idM] = 0;
                    $remO[$idM]        = 0.0;
                }

                $rowsOBody[$idM] = [
                    'Targa'       => (string)($riga['Targa'] ?? ''),
                    'n_servizi'   => $n,
                    'percentuale' => $perc,
                ];
            }

            $diffO = $totOCents - $sumProvO;
            if ($diffO > 0 && !empty($remO)) {
                arsort($remO, SORT_NUMERIC);
                foreach (array_keys($remO) as $idM) {
                    if ($diffO <= 0) break;
                    $quoteOCents[$idM] += 1;
                    $diffO--;
                }
            }

            $rowsOss = [];
            foreach ($rowsOBody as $idM => $row) {
                $imp = round(($quoteOCents[$idM] ?? 0) / 100, 2);
                $rowsOss[] = $row + [
                    'importo'   => $imp,
                    'is_totale' => 0,
                ];
            }
            $rowsOss[] = [
                'Targa'       => 'TOTALE',
                'n_servizi'   => array_sum(array_column($rowsOBody, 'n_servizi')),
                'percentuale' => 100.00,
                'importo'     => round($totBilOss, 2),
                'is_totale'   => -1,
            ];

            // ===== Render & save
            $pdf = Pdf::loadView('template.imputazioni_materiale_ossigeno', [
                'anno'         => $this->anno,
                'associazione' => $associazione,
                'mat' => [
                    'titolo'          => 'IMPUTAZIONE COSTI MATERIALE SANITARIO DI CONSUMO',
                    'totale_bilancio' => round($totBilMat, 2),
                    'rows'            => $rowsMat,
                    'tot_servizi'     => array_sum(array_column($rowsMatBody, 'n_servizi')),
                ],
                'oss' => [
                    'titolo'          => 'IMPUTAZIONE COSTI OSSIGENO',
                    'totale_bilancio' => round($totBilOss, 2),
                    'rows'            => $rowsOss,
                    'tot_servizi'     => array_sum(array_column($rowsOBody, 'n_servizi')),
                ],
            ])->setPaper('a4', 'portrait');

            $filename = "imputazioni_materiale_ossigeno_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
            $path = "documenti/{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            $doc->update([
                'nome_file'     => $filename,
                'percorso_file' => $path,
                'generato_il'   => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('GeneraImputazioniMaterialeEOssigenoPdfJob failed: ' . $e->getMessage(), [
                'documentoId'    => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno'           => $this->anno,
            ]);
            $this->fail($e);
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $e): void {
        Log::error('GeneraImputazioniMaterialeEOssigenoPdfJob failed: ' . $e->getMessage(), [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
        ]);
    }
}
