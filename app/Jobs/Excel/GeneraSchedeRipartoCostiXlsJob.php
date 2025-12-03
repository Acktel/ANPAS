<?php

namespace App\Jobs\Excel;

use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\CachedObjectStorage\PhpTemp as PSSPhpTemp;
use PhpOffice\PhpSpreadsheet\CachedObjectStorage\File as PSSFileCache;
use PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory;
use PhpOffice\PhpSpreadsheet\CachedObjectStorage\MemoryCache;
use PhpOffice\PhpSpreadsheet\CachedObjectStorage\PhpTemp;

use App\Support\Excel\PrintConfigurator;

use App\Models\Automezzo;
use App\Models\Associazione;
use App\Models\Convenzione;
use App\Models\AutomezzoKm;
use App\Models\AutomezzoServiziSvolti;
use App\Models\Dipendente;
use App\Models\RipartizionePersonale;
use App\Models\RapportoRicavo;
use App\Models\RipartizioneServizioCivile;
use App\Services\RipartizioneCostiService;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\CostiPersonale;
use App\Models\CostiMansioni;
use App\Models\CostiAutomezzi;
use App\Models\CostoMaterialeSanitario;
use App\Models\RipartizioneOssigeno;
use App\Models\CostoOssigeno;
use App\Models\Riepilogo;
use App\Models\RiepilogoCosti;
use App\Models\RotazioneMezzi;

class GeneraSchedeRipartoCostiXlsJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $tries   = 5;
    public $backoff = 15;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('excel');
    }

    public function middleware(): array {
        $key = "xls-schede-riparto-{$this->idAssociazione}-{$this->anno}-doc{$this->documentoId}";
        return [(new WithoutOverlapping($key))->expireAfter(300)->releaseAfter(15)];
    }

    private function setLogos(): void {
        $pick = function (array $candidates): ?string {
            foreach ($candidates as $p) {
                if ($p && is_file($p)) return $p;
            }
            return null;
        };

        $this->logos['left'] = $pick([
            public_path('storage/documenti/template_excel/logo_left.png')
        ]);

        $this->logos['right'] = $pick([
            public_path('storage/documenti/template_excel/logo_right.png')
        ]);

        if (!$this->logos['left'] || !$this->logos['right']) {
            Log::warning('Loghi non trovati', $this->logos);
        }
    }

    private function reinsertTemplateLogos(Worksheet $ws, array $tpl): void {

        $row = $tpl['startRow'] + 1;

        /* -------------------------------
     * LOGO SINISTRO
     * ----------------------------- */
        $targetColLeft = 2; // colonna B
        $offsetX       = -20;

        // Controllo sicurezza: non uscire a sx
        $colLetter = Coordinate::stringFromColumnIndex($targetColLeft);
        $colWidth  = $ws->getColumnDimension($colLetter)->getWidth();

        $safeOffsetX = $offsetX;

        // Evita offset se manda il logo "fuori" dal foglio
        if ($colWidth + $offsetX < 2) {       // <2px => troppo a sinistra
            $safeOffsetX = 0;                 // non sposto
        }

        // Evita offset se nella cella c'è del testo
        $cellValue = trim((string)$ws->getCell($colLetter . $row)->getValue());
        if ($cellValue !== '') {
            $safeOffsetX = 0;                 // cella occupata → non sposto
        }

        // Applica logo SX
        $this->placeLogoAtRow(
            $ws,
            $tpl,
            $this->logos['left'],
            $row,
            $targetColLeft,
            64,           // altezza
            $safeOffsetX, // offset X controllato
            28,
            1,
            22
        );


        /* -------------------------------
     * LOGO DESTRO
     * ----------------------------- */
        $targetColRight = 8; // colonna H
        $offsetX        = -20;

        $colLetterR     = Coordinate::stringFromColumnIndex($targetColRight);
        $colWidthR      = $ws->getColumnDimension($colLetterR)->getWidth();
        $safeOffsetR    = $offsetX;

        if ($colWidthR + $offsetX < 2) {
            $safeOffsetR = 0;                 // non sposto
        }

        $cellValueR = trim((string)$ws->getCell($colLetterR . $row)->getValue());
        if ($cellValueR !== '') {
            $safeOffsetR = 0;
        }

        $this->placeLogoAtRow(
            $ws,
            $tpl,
            $this->logos['right'],
            $row,
            $targetColRight,
            64,
            $safeOffsetR,
            28,
            1,
            22
        );
    }

    public function handle(): void {
        $this->setLogos();
        $this->initSpreadsheetCache();

        Log::info('SchedeRipartoCosti V3: START', [
            'documentoId' => $this->documentoId,
            'idAss'       => $this->idAssociazione,
            'anno'        => $this->anno,
        ]);

        $disk = Storage::disk('public');

        try {
            /* ======================================================
            * DATI BASE
            * ====================================================== */
            $associazione = Associazione::getById($this->idAssociazione);
            $nomeAss = (string)($associazione->Associazione ?? '');
            $slugAss = $this->slugify($nomeAss);

            $automezzi   = Automezzo::getByAssociazione($this->idAssociazione, $this->anno)
                ->sortBy('idAutomezzo')->values();
            $convenzioni = Convenzione::getByAssociazioneAnno($this->idAssociazione, $this->anno);

            Log::info('Dati base caricati', [
                'automezzi'    => $automezzi->count(),
                'convenzioni'  => $convenzioni->count(),
            ]);

            /* ======================================================
            * CREA WORKBOOK PRINCIPALE
            * ====================================================== */
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SCHEDE DI RIPARTO DEI COSTI');
            $sheet->getDefaultRowDimension()->setRowHeight(14);

            $phBase = [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ];
            $noLogos = ['left' => null, 'right' => null];

            /* ======================================================
             * BLOCCO 1 → 8
            * ====================================================== */
            // ---- [B1] KM ------------------------------------------------
            $kmMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/KmPercorsi.xlsx'));
            $this->reinsertTemplateLogos($sheet, $kmMeta);
            $this->replacePlaceholdersEverywhere($sheet, $phBase);
            $endKm = $this->blockKm($sheet, $kmMeta, $automezzi, $convenzioni, $noLogos);

            // ---- [B2] SERVIZI ------------------------------------------
            $srvMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/ServiziSvolti.xlsx'), $endKm + 2);
            $this->reinsertTemplateLogos($sheet, $srvMeta);
            $this->replacePlaceholdersEverywhere($sheet, $phBase);
            $endSrv = $this->blockServizi($sheet, $srvMeta, $automezzi, $convenzioni, $noLogos);

            // ---- [B3] RICAVI -------------------------------------------
            $ricMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/Costi_Ricavi.xlsx'), $endSrv + 2);
            $this->reinsertTemplateLogos($sheet, $ricMeta);
            $this->replacePlaceholdersEverywhere($sheet, $phBase);
            [$endRic, $ricaviMap] = $this->blockRicavi($sheet, $ricMeta, $convenzioni, $noLogos);

            // ---- [B4] AUTISTI/BAR --------------------------------------
            $abMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/CostiPersonale_autisti.xlsx'), $endRic + 2);
            $this->reinsertTemplateLogos($sheet, $abMeta);
            $this->replacePlaceholdersEverywhere($sheet, $phBase);
            $endAB = $this->blockAutistiBarellieri($sheet, $abMeta, $convenzioni, $noLogos);

            // ---- [B5] VOLONTARI ----------------------------------------
            $volMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/CostiPersonale_volontari.xlsx'), $endAB + 2);
            $this->reinsertTemplateLogos($sheet, $volMeta);
            $this->replacePlaceholdersEverywhere($sheet, $phBase);
            $endVol = $this->blockVolontari($sheet, $volMeta, $convenzioni, $ricaviMap, $noLogos);

            // ---- [B6] SERVIZIO CIVILE ----------------------------------
            $scMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/CostiPersonale_ServizioCivile.xlsx'), $endVol + 2);
            $this->reinsertTemplateLogos($sheet, $scMeta);
            $this->replacePlaceholdersEverywhere($sheet, $phBase);
            $endSC = $this->blockServizioCivile($sheet, $scMeta, $convenzioni, $noLogos);

            // ---- [B7] DISTINTA SERVIZI ---------------------------------
            $msMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/DistintaServiziSvolti.xlsx'), $endSC + 2);
            $this->reinsertTemplateLogos($sheet, $msMeta);
            $this->replacePlaceholdersEverywhere($sheet, $phBase);
            $endMS = $this->blockDistintaServizi($sheet, $msMeta, $automezzi, $convenzioni, $noLogos);

            // ---- [B8] ROTAZIONE MEZZI ----------------------------------
            $hasRotazione = collect($convenzioni)->contains(
                fn($c) =>
                RipartizioneCostiService::isRegimeRotazione($c->idConvenzione)
            );
            if ($hasRotazione) {
                $rotMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/RotazioneMezzi.xlsx'), $endMS + 2);
                $this->reinsertTemplateLogos($sheet, $rotMeta);
                $this->replacePlaceholdersEverywhere($sheet, $phBase);
                $this->BlockRotazioneMezzi($sheet, $rotMeta, $automezzi, $convenzioni, $this->idAssociazione, $this->anno);
            }

            /*======================================================
             FOGLIO 2 → 4
             ====================================================== */

            // ---- [F2] COSTI DIPENDENTI ---------------------------------
            $sheetRip = $spreadsheet->createSheet();
            $sheetRip->setTitle('DIST.RIPARTO COSTI DIPENDENTI');

            // A&B (prima tabella)
            $ripMeta = $this->appendTemplate(
                $sheetRip,
                $disk->path('documenti/template_excel/DistintaCostiPersonale_Autisti.xlsx')
            );
            $this->reinsertTemplateLogos($sheetRip, $ripMeta);
            $this->replacePlaceholdersEverywhere($sheetRip, $phBase);
            $this->blockRipartoCostiDipendentiAB($sheetRip, $ripMeta, $convenzioni, $noLogos);
            $this->forceHeaderText($sheetRip, $ripMeta, $nomeAss, $this->anno);

            // Mansioni aggiuntive (tabelle separate sotto, una per template)
            $mansioniTpl = [
                5 => 'DistintaCostiPersonale_CoordAmm.xlsx',
                6 => 'DistintaCostiPersonale_CordTec.xlsx',
                7 => 'DistintaCostiPersonale_Amministrativi.xlsx',
                2 => 'DistintaCostiPersonale_Logistica.xlsx',
                3 => 'DistintaCostiPersonale_Pulizia.xlsx',
                4 => 'DistintaCostiPersonale_Altro.xlsx',
            ];

            $rowCursor = $sheetRip->getHighestRow() + 2;

            foreach ($mansioniTpl as $idQ => $fileName) {
                $abs = $disk->path('documenti/template_excel/' . $fileName);
                if (!is_file($abs)) {
                    Log::warning('Template mansione non trovato', ['idQualifica' => $idQ, 'file' => $abs]);
                    continue;
                }

                // Appendo il template alla riga corrente e reinserisco i loghi del template
                $tplMeta = $this->appendTemplate($sheetRip, $abs, $rowCursor);
                $this->reinsertTemplateLogos($sheetRip, $tplMeta);
                $this->replacePlaceholdersEverywhere($sheetRip, $phBase);

                // Compilo la tabella della mansione e aggiorno il cursore (con 1 riga di spazio)
                $rowCursor = $this->blockDistintaCostiPerMansione($sheetRip, $tplMeta, (int)$idQ, $noLogos) + 2;

                // (opzionale) Forza testo header anche per i blocchi mansioni
                $this->forceHeaderText($sheetRip, $tplMeta, $nomeAss, $this->anno);
            }

            // ---- [F3] AUTOMEZZI ----------------------------------------
            $sheetAuto = $spreadsheet->createSheet();
            $sheetAuto->setTitle('DISTINTA RIPARTO AUTOMEZZI');
            $autoMeta = $this->appendTemplate($sheetAuto, $disk->path('documenti/template_excel/CostiAutomezzi_Sanitaria.xlsx'));
            $this->reinsertTemplateLogos($sheetAuto, $autoMeta);
            $this->replacePlaceholdersEverywhere($sheetAuto, $phBase);
            $this->blockRipartoAutomezzi($sheetAuto, $autoMeta, $noLogos);

            // ---- [F4] RADIO ---------------------------------------------

            $sheetRadio = $spreadsheet->createSheet();
            $sheetRadio->setTitle('DISTINTA RIPARTO COSTI RADIO');
            $radioMeta = $this->appendTemplate($sheetRadio, $disk->path('documenti/template_excel/DistintaCosti_Radio.xlsx'));
            $this->reinsertTemplateLogos($sheetRadio, $radioMeta);
            $this->replacePlaceholdersEverywhere($sheetRadio, $phBase);
            $this->blockCostiRadio($sheetRadio, $radioMeta, $noLogos);

            /* ======================================================
            * FOGLI EXTRA E CONSUNTIVI
            * ====================================================== */

            // Imputazione Sanitario
            $this->safeCall(
                'Imputazione Sanitario',
                fn() =>
                $this->creaFoglioImputazioneSanitario(
                    $spreadsheet,
                    public_path('storage/documenti/template_excel/ImputazioneCosti_Sanitario.xlsx'),
                    $nomeAss,
                    $this->idAssociazione,
                    $this->anno
                )
            );


            // Imputazione Ossigeno
            $this->safeCall(
                'Imputazione Ossigeno',
                fn() =>
                $this->creaFoglioImputazioneOssigeno($spreadsheet, $this->idAssociazione, $this->anno)
            );

            // Riepilogo Auto-Radio-San
            $this->safeCall(
                'Riepilogo Auto-Radio-San',
                fn() =>
                $this->creaFoglioRiepilogoAutoRadioSan(
                    $spreadsheet,
                    $this->idAssociazione,
                    $this->anno,
                    public_path('storage/documenti/template_excel/RiepilogoAutomezzi_Sanitaria_Radio.xlsx')
                )
            );

            // Distinta Imputazione Costi
            $this->safeCall(
                'Distinta Imputazione Costi',
                fn() =>
                $this->addDistintaImputazioneCostiSheet($spreadsheet, $this->idAssociazione, $this->anno)
            );

            // Fogli singoli automezzo
            $this->safeCall('Fogli per singolo automezzo', function () use ($spreadsheet, $disk, $nomeAss) {
                $tplAutoPath = $disk->path('documenti/template_excel/Costi_AUTO1.xlsx');
                $autos = DB::table('automezzi')
                    ->where('idAssociazione', $this->idAssociazione)
                    ->where('idAnno', $this->anno)
                    ->orderBy('Targa')
                    ->get(['idAutomezzo', 'Targa', 'CodiceIdentificativo']);
                foreach ($autos as $auto) {
                    $this->creaFoglioCostiPerAutomezzo(
                        $spreadsheet,
                        $tplAutoPath,
                        $nomeAss,
                        $this->idAssociazione,
                        $this->anno,
                        $auto
                    );
                }
            });

            // Fogli per convenzione (TAB.1 + TAB.2)
            $this->safeCall('Fogli per convenzione (Tabella 1 + 2)', function () use ($spreadsheet, $nomeAss) {
                $tplConvenzionePath = public_path('storage/documenti/template_excel/RiepilogoDati.xlsx');

                if (!is_file($tplConvenzionePath)) {
                    Log::warning('Template RiepilogoDati.xlsx non trovato', ['path' => $tplConvenzionePath]);
                    return;
                }

                // Punto di inserimento (subito dopo l’anchor, altrimenti in coda)
                $anchorTitle = 'DISTINTA IMPUTAZIONE COSTI';
                $anchor   = $spreadsheet->getSheetByName($anchorTitle);
                $insertAt = $anchor ? ($spreadsheet->getIndex($anchor) + 1) : $spreadsheet->getSheetCount();

                // Convenzioni ordinate
                $convenzioni = DB::table('convenzioni')
                    ->select('idConvenzione', 'Convenzione')
                    ->where('idAssociazione', $this->idAssociazione)
                    ->where('idAnno', $this->anno)
                    ->orderBy('ordinamento')
                    ->orderBy('idConvenzione')
                    ->get();

                foreach ($convenzioni as $conv) {
                    try {
                        // Titolo foglio unico (max 31 char)
                        $title = $this->uniqueSheetTitle($spreadsheet, (string) $conv->Convenzione);

                        // Crea il foglio subito all’indice calcolato
                        $ws = new Worksheet($spreadsheet, $title);
                        $spreadsheet->addSheet($ws, $insertAt++);

                        // Applica template
                        $this->appendTemplate($ws, $tplConvenzionePath, 1);

                        // INSERISCO I LOGHI
                        $this->insertLogosAtRow(
                            $ws,
                            [
                                'left'  => public_path('storage/documenti/template_excel/logo_left.png'),
                                'right' => public_path('storage/documenti/template_excel/logo_right.png'),
                            ],
                            1, // riga dove metti i loghi
                            10 // colonna dove ancorare il logo destro
                        );
                        // Placeholder header
                        $this->replacePlaceholdersEverywhere($ws, [
                            'nome_associazione' => $nomeAss,
                            'ASSOCIAZIONE'      => $nomeAss,
                            'anno_riferimento'  => (string) $this->anno,
                            'ANNO'              => (string) $this->anno,
                            'nome_convenzione'  => (string) $conv->Convenzione,
                            'convenzione'       => (string) $conv->Convenzione,
                        ]);

                        // TAB.1: Voce / Preventivo / Consuntivo (sequenziale)
                        $this->fillTabellaRiepilogoDatiSequential(
                            $ws,
                            (int) $this->idAssociazione,
                            (int) $this->anno,
                            (int) $conv->idConvenzione
                        );

                        // TAB.2: RIEPILOGO COSTI (sezioni 2..11) sotto la prima tabella
                        $this->fillRiepilogoCostiSottoPrimaTabella(
                            $ws,
                            (int) $this->idAssociazione,
                            (int) $this->anno,
                            (int) $conv->idConvenzione
                        );
                    } catch (Throwable $e) {
                        Log::warning('Foglio convenzione (Tabella 1+2) saltato', [
                            'conv' => (string) $conv->Convenzione,
                            'err'  => $e->getMessage(),
                        ]);
                        continue;
                    }
                }
            });

            /* ======================================================
            * FORMATTAZIONE GENERALE E STAMPA
            * ====================================================== */

            foreach ($spreadsheet->getAllSheets() as $ws) {
                try {
                    PrintConfigurator::forceLandscapeCenteredMinScale($ws, 50, true);
                    PrintConfigurator::compactBodyOnly($ws, 2, 14.0, null, false, 1);
                    PrintConfigurator::configureScrolling($ws, null);
                    $ws->setShowGridlines(false);
                    // Worksheet does not provide getDefaultStyle(); use the parent Spreadsheet default style
                    $ws->getParent()->getDefaultStyle()->getAlignment()->setShrinkToFit(false);
                    // (1) Font minimo su TUTTO, numeri inclusi
                    PrintConfigurator::enforceMinimumFontSize($ws, 10.0, true);

                    // (2) Blocca shrink e imposta font MIN su header + prima colonna
                    PrintConfigurator::enforceHeaderAndFirstColReadable(
                        $ws,
                        10,   // min font
                        1,    // prima colonna = A
                        1,    // header da riga 1
                        8,    // header fino a riga 8
                        12.0  // larghezza minima colonna A
                    );

                    // (3) 🔒 NON TOCCARE LE COLONNE A–D (servono al riepilogo costi)
                    PrintConfigurator::fitBodyColumns(
                        $ws,
                        2,      // corpo (parte da riga 2)
                        1,      // first col
                        40,     // last col (larghissimo, non importa)
                        1.6,    // padding
                        7.0,    // min width
                        26.0,   // max width
                        [1, 2, 3, 4]   // ⛔ ESCLUDI A, B, C, D
                    );
                } catch (Throwable $e) {
                    Log::warning('[FORMAT] Errore configurazione foglio', [
                        'sheet' => $ws->getTitle(),
                        'msg'   => $e->getMessage(),
                    ]);
                }
            }

            //FORZA FONT MINIMO
            PrintConfigurator::enforceMinimumFontSizeForWorkbook($spreadsheet, 10);

            /* ======================================================
            * SALVATAGGIO
            * ====================================================== */
            $lastColL = trim((string)$sheet->getHighestDataColumn());
            $lastRow  = (int)$sheet->getHighestDataRow();
            if ($lastColL && $lastRow > 1) {
                $sheet->getPageSetup()->setPrintArea("A1:{$lastColL}{$lastRow}");
            }

            $baseFilename = sprintf('DISTINTAIMPUTAZIONECOSTI_%s_%d.xlsx', $slugAss ?: 'export', $this->anno);
            [$destRel, $finalFilename] = $this->safeSaveSpreadsheet($spreadsheet, $baseFilename, 'documenti');

            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update([
                    'percorso_file' => $destRel,
                    'nome_file'     => $finalFilename,
                    'stato'         => 'ready',
                    'generato_il'   => now(),
                    'updated_at'    => now(),
                ]);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();

            Log::info('SchedeRipartoCosti V3: COMPLETATO', ['file' => $finalFilename]);
        } catch (Throwable $e) {
            Log::error('SchedeRipartoCosti V3: EXCEPTION', [
                'documentoId' => $this->documentoId,
                'msg'         => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);
            if (method_exists($this, 'fail')) {
                $this->fail($e);
            }
        }
    }

    /* ======================================================
    * Utility per blocchi extra con logging unificato
    * ====================================================== */
    private function safeCall(string $label, callable $fn): void {
        try {
            $fn();
            Log::info("[FX] {$label} completato");
        } catch (Throwable $e) {
            Log::warning("[FX] Errore {$label}", [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /** KM */
    private function blockKm(Worksheet $sheet, array $tpl, $automezzi, $convenzioni, array $logos): int {
        [$headerRow, $columns] = $this->detectKmHeaderAndCols($sheet, $tpl);

        // Reindex convenzioni per avere i=0..n-1
        $convList   = collect($convenzioni)->values();
        $usedPairs  = max(1, $convList->count());

        $firstPairCol = $columns['KMTOT'] + 1;
        $lastUsedCol  = max($columns['KMTOT'], $firstPairCol + ($usedPairs * 2) - 1);

        // Loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // Titoli convenzioni (riga header-1)
        foreach ($convList as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string)$c->Convenzione);
        }

        // Dati
        $kmGrouped = Cache::remember(
            "rip:kmGroups:ass:{$this->idAssociazione}:anno:{$this->anno}",
            now()->addMinutes(30),
            fn() => AutomezzoKm::getGroupedByAutomezzoAndConvenzione($this->anno, Auth::user(), $this->idAssociazione)
        );

        $sampleRow  = $headerRow + 1;
        $lastColLetter = ($lastUsedCol > 0)
            ? Coordinate::stringFromColumnIndex($lastUsedCol)
            : 'A';
        $styleRange = "A{$sampleRow}:{$lastColLetter}{$sampleRow}";

        $totalRow = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $rows = [];

        $totKmAll    = 0.0;
        $totKmByConv = [];
        foreach ($convList as $c) $totKmByConv[(int)$c->idConvenzione] = 0.0;

        foreach ($automezzi as $idx => $a) {
            // Somma km del mezzo su tutte le convenzioni
            $kmTot = 0;
            foreach ($convList as $c) {
                $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                $kmTot += (int)($kmGrouped->get($key)?->sum('KMPercorsi') ?? 0);
            }
            $totKmAll += $kmTot;

            $r = [
                ['col' => $columns['PROGR'],  'val' => 'AUTO ' . ($idx + 1)],
                ['col' => $columns['TARGA'],  'val' => (string)$a->Targa, 'type' => DataType::TYPE_STRING],
                ['col' => $columns['CODICE'], 'val' => (string)($a->CodiceIdentificativo ?? ''), 'type' => DataType::TYPE_STRING],
                // KM totali del mezzo: intero (no decimali)
                ['col' => $columns['KMTOT'],  'val' => $kmTot, 'fmt' => NumberFormat::FORMAT_NUMBER],
            ];

            foreach ($convList as $i => $c) {
                $kmCol = $firstPairCol + ($i * 2);
                $pcCol = $kmCol + 1;

                $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                $km  = (int)($kmGrouped->get($key)?->sum('KMPercorsi') ?? 0);
                $p   = $kmTot > 0 ? ($km / $kmTot) : 0.0;

                // KM per conv: intero; % centrata
                $r[] = ['col' => $kmCol, 'val' => $km, 'fmt' => NumberFormat::FORMAT_NUMBER];
                $r[] = ['col' => $pcCol, 'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];

                $totKmByConv[(int)$c->idConvenzione] += $km;
            }
            $rows[] = $r;
        }

        // Inserisci righe prima del TOTALE
        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // Riga TOTALE: per-conv km e %, chiusura a 100%
        $pairSum = 0.0;
        foreach ($convList as $i => $c) {
            $kmCol = $firstPairCol + ($i * 2);
            $pcCol = $kmCol + 1;

            $v = (float)$totKmByConv[(int)$c->idConvenzione];

            $sheet->setCellValueByColumnAndRow($kmCol, $totRowNew, $v);
            $sheet->getStyleByColumnAndRow($kmCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $p = ($i < ($usedPairs - 1) && $totKmAll > 0) ? ($v / $totKmAll) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Totale colonna KMTOT + merge "TOTALE"
        $sheet->setCellValueByColumnAndRow($columns['KMTOT'], $totRowNew, $totKmAll);
        $sheet->getStyleByColumnAndRow($columns['KMTOT'], $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        // Bordi & header & pulizia orizzontale (usa versioni retro-compat se le hai patchate)
        $this->thickBorderRow($sheet, $headerRow,  $lastUsedCol);  // equivale a 1..lastUsedCol
        $this->thickBorderRow($sheet, $totRowNew,   $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)
            ->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol); // equivale a 1..lastUsedCol
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        // Nascondi coppie extra (se il template ne prevede di più)
        //$this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);
        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** SERVIZI */
    private function blockServizi(Worksheet $sheet, array $tpl, $automezzi, $convenzioni, array $logos): int {
        [$headerRow, $columns] = $this->detectServiziHeaderAndCols($sheet, $tpl);

        // Reindicizza per avere i = 0..n-1
        $convList  = collect($convenzioni)->values();
        $usedPairs = max(1, $convList->count());

        $firstPairCol = $columns['TOTSRV'] + 1;
        $lastUsedCol  = max($columns['TOTSRV'], $firstPairCol + ($usedPairs * 2) - 1);

        // loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // titoli convenzioni
        foreach ($convList as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string)$c->Convenzione);
        }

        // dati
        $serviziGrouped = AutomezzoServiziSvolti::getGroupedByAutomezzoAndConvenzione($this->anno, $this->idAssociazione);
        $sampleRow  = $headerRow + 1;
        $lastColLetter = ($lastUsedCol > 0)
            ? Coordinate::stringFromColumnIndex($lastUsedCol)
            : 'A';
        $styleRange = "A{$sampleRow}:{$lastColLetter}{$sampleRow}";

        $totalRow = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $rows = [];

        $totAll    = 0;
        $totByConv = [];
        foreach ($convList as $c) $totByConv[(int)$c->idConvenzione] = 0;

        foreach ($automezzi as $idx => $a) {
            // totale servizi per mezzo
            $totVeicolo = 0;
            foreach ($convList as $c) {
                $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                $totVeicolo += (int)($serviziGrouped->get($key)?->first()->NumeroServizi ?? 0);
            }
            $totAll += $totVeicolo;

            $r = [
                ['col' => $columns['PROGR'],  'val' => 'AUTO ' . ($idx + 1)],
                ['col' => $columns['TARGA'],  'val' => (string)$a->Targa, 'type' => DataType::TYPE_STRING],
                ['col' => $columns['CODICE'], 'val' => (string)($a->CodiceIdentificativo ?? ''), 'type' => DataType::TYPE_STRING],
                ['col' => $columns['TOTSRV'], 'val' => $totVeicolo, 'fmt' => NumberFormat::FORMAT_NUMBER], // intero
            ];

            foreach ($convList as $i => $c) {
                $nCol = $firstPairCol + ($i * 2);
                $pcCol = $nCol + 1;

                $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                $n   = (int)($serviziGrouped->get($key)?->first()->NumeroServizi ?? 0);
                $p   = $totVeicolo > 0 ? ($n / $totVeicolo) : 0.0;

                $r[] = ['col' => $nCol,  'val' => $n, 'fmt' => NumberFormat::FORMAT_NUMBER]; // intero
                $r[] = ['col' => $pcCol, 'val' => $p, 'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];

                $totByConv[(int)$c->idConvenzione] += $n;
            }

            $rows[] = $r;
        }

        // inserisci prima del TOTALE
        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // riga TOTALE: chiusura % a 100%
        $pairSum = 0.0;
        foreach ($convList as $i => $c) {
            $nCol = $firstPairCol + ($i * 2);
            $pcCol = $nCol + 1;
            $v = (int)$totByConv[(int)$c->idConvenzione];

            $sheet->setCellValueByColumnAndRow($nCol, $totRowNew, $v);
            $sheet->getStyleByColumnAndRow($nCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $p = ($i < ($usedPairs - 1) && $totAll > 0) ? ($v / $totAll) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // totale complessivo e merge "TOTALE"
        $sheet->setCellValueByColumnAndRow($columns['TOTSRV'], $totRowNew, $totAll);
        $sheet->getStyleByColumnAndRow($columns['TOTSRV'], $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        // bordi/header (firme nuove con firstCol=1)
        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  1, $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)
            ->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, 1, $lastUsedCol);

        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);
        //$this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);
        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** RICAVI (una sola riga dati nel template, niente riga “Totale ricavi” da creare/spostare) */
    private function blockRicavi(Worksheet $sheet, array $tpl, $convenzioni, array $logos): array {
        // locateRicaviHeader deve restituire:
        // ['headerRow','convTitleRow','dataRow','firstPairCol','totCol']
        $meta = $this->locateRicaviHeader($sheet, $tpl['startRow']);

        // Reindicizza le convenzioni (0..n-1)
        $convList   = collect($convenzioni)->values();
        $usedPairs  = max(1, $convList->count());
        $lastUsedCol = max($meta['totCol'], $meta['firstPairCol'] + ($usedPairs * 2) - 1);

        // Loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // Dati ricavi
        $ricaviMap = RapportoRicavo::mapByAssociazione($this->anno, $this->idAssociazione);
        $totRicavi = array_sum(array_map('floatval', $ricaviMap));

        // Titoli convenzioni (sopra banda fucsia)
        foreach ($convList as $i => $c) {
            $col = $meta['firstPairCol'] + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $meta['convTitleRow'], (string)$c->Convenzione);
        }

        // Riga DATI (quella già presente nel template)
        $pairSum = 0.0;
        foreach ($convList as $i => $c) {
            $rimCol = $meta['firstPairCol'] + ($i * 2);   // colonna “RIMBORSO”
            $pctCol = $rimCol + 1;                        // colonna “%”
            $val    = (float)($ricaviMap[(int)$c->idConvenzione] ?? 0.0);

            // percentuale: ultima colonna prende il delta per chiudere a 100%
            $pct = ($i < ($usedPairs - 1) && $totRicavi > 0.0)
                ? ($val / $totRicavi)
                : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) {
                $pairSum += $pct;
            }

            // Scritture + formati
            $sheet->setCellValueByColumnAndRow($rimCol, $meta['dataRow'], $val);
            $sheet->getStyleByColumnAndRow($rimCol, $meta['dataRow'])
                ->getNumberFormat()->setFormatCode('#,##0.00');

            $sheet->setCellValueByColumnAndRow($pctCol, $meta['dataRow'], $pct);
            $sheet->getStyleByColumnAndRow($pctCol, $meta['dataRow'])
                ->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pctCol, $meta['dataRow'])
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Totale esercizio nella colonna del template
        $sheet->setCellValueByColumnAndRow($meta['totCol'], $meta['dataRow'], $totRicavi);
        $sheet->getStyleByColumnAndRow($meta['totCol'], $meta['dataRow'])
            ->getNumberFormat()->setFormatCode('#,##0.00');

        // Stili: header + riga dati in grassetto, firme nuove per i bordi
        $this->thickBorderRow($sheet, $meta['headerRow'], 1, $lastUsedCol);
        $sheet->getStyle(
            'A' . $meta['dataRow'] . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $meta['dataRow']
        )->getFont()->setBold(true);
        $this->thickOutline($sheet, $meta['headerRow'], $meta['dataRow'], 1, $lastUsedCol);

        // Qualità vita: autosize e nascondi coppie non usate
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);
        //$this->hideUnusedConventionColumns($sheet, $meta['headerRow'], $meta['firstPairCol'], $usedPairs);
        // Non esiste riga “Totale ricavi” separata: ritorno la fine del blocco e la mappa
        return [max($meta['dataRow'], $tpl['endRow']), $ricaviMap];
    }

    /**
     * SERVIZI A&B: Autisti/Barellieri (ore per dipendente + tabellina prev/cons A&B)
     */
    private function blockAutistiBarellieri(Worksheet $sheet, array $tpl, $convenzioni, array $logos): int {
        [$headerRow, $cols] = $this->detectABHeaderAndCols($sheet, $tpl);

        // Reindicizza convenzioni
        $convList     = collect($convenzioni)->values();
        $firstPairCol = $cols['TOTORE'] + 1;
        $usedPairs    = max(1, $convList->count());
        $lastUsedCol  = max($cols['TOTORE'], $firstPairCol + ($usedPairs * 2) - 1);

        // Loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // Titoli convenzioni
        foreach ($convList as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string)$c->Convenzione);
        }

        /* ---------------------- TABELLINA PREV/CONS ---------------------- */

        $prevRow = $headerRow - 4;
        $consRow = $headerRow - 3;

        $convIds = $convList->pluck('idConvenzione')->map(fn($v) => (int)$v)->all();

        // Consuntivo voce 6001
        $consByVoce   = RipartizioneCostiService::consuntiviPerVoceByConvenzione($this->idAssociazione, $this->anno);
        $consByConvAB = array_fill_keys($convIds, 0.0);

        if (!empty($consByVoce[6001])) {
            foreach ($convIds as $cid) {
                $consByConvAB[$cid] = round((float)($consByVoce[6001][$cid] ?? 0.0), 2);
            }
        }

        // Preventivo voce 6001
        $prevByConvAB = array_fill_keys($convIds, 0.0);
        $idRiepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->value('idRiepilogo');

        if ($idRiepilogo) {
            $rowsPrev = DB::table('riepilogo_dati')
                ->where('idRiepilogo', $idRiepilogo)
                ->where('idVoceConfig', 6001)
                ->whereIn('idConvenzione', $convIds)
                ->select('idConvenzione', DB::raw('SUM(preventivo) AS prev'))
                ->groupBy('idConvenzione')
                ->get();

            foreach ($rowsPrev as $r) {
                $prevByConvAB[(int)$r->idConvenzione] = round((float)$r->prev, 2);
            }
        }

        // Scrittura tabella prev/cons
        foreach ($convList as $i => $c) {
            $impCol = $firstPairCol + ($i * 2);
            $pcCol  = $impCol + 1;
            $cid    = (int)$c->idConvenzione;

            // PREVENTIVO
            $sheet->setCellValueByColumnAndRow($impCol, $prevRow, $prevByConvAB[$cid] ?? 0.0);
            $sheet->getStyleByColumnAndRow($impCol, $prevRow)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValueByColumnAndRow($pcCol, $prevRow, null);

            // CONSUNTIVO
            $sheet->setCellValueByColumnAndRow($impCol, $consRow, $consByConvAB[$cid] ?? 0.0);
            $sheet->getStyleByColumnAndRow($impCol, $consRow)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValueByColumnAndRow($pcCol, $consRow, null);
        }

        // Evidenzia
        $sheet->getStyle(
            Coordinate::stringFromColumnIndex($firstPairCol) . $prevRow . ':' .
                Coordinate::stringFromColumnIndex($lastUsedCol)  . $prevRow
        )->getFont()->setBold(true);

        $sheet->getStyle(
            Coordinate::stringFromColumnIndex($firstPairCol) . $consRow . ':' .
                Coordinate::stringFromColumnIndex($lastUsedCol)  . $consRow
        )->getFont()->setBold(true);

        /* ---------------------- TABELLA GRANDE ---------------------- */

        $dip = Dipendente::getAutistiEBarellieri($this->anno, $this->idAssociazione);
        $rip = RipartizionePersonale::getAll($this->anno, null, $this->idAssociazione)->groupBy('idDipendente');

        $totalRow  = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $sampleRow = $headerRow + 1;

        $lastColLetter = Coordinate::stringFromColumnIndex($lastUsedCol);
        $styleRange = "A{$sampleRow}:{$lastColLetter}{$sampleRow}";

        $rows      = [];
        $totOreAll = 0.0;
        $totByConv = array_fill_keys($convIds, 0.0);

        foreach ($dip as $i => $d) {
            $serv   = $rip->get($d->idDipendente) ?? collect();
            $oreTot = (float)$serv->sum('OreServizio');
            $totOreAll += $oreTot;

            $r = [
                ['col' => $cols['IDX'], 'val' => 'DIPENDENTE N. ' . ($i + 1)],
                ['col' => $cols['NOME'] ?? 2, 'val' => trim(($d->DipendenteCognome ?? '') . ' ' . ($d->DipendenteNome ?? ''))],
                ['col' => $cols['TOTORE'], 'val' => $oreTot, 'fmt' => NumberFormat::FORMAT_NUMBER],
            ];

            foreach ($convList as $j => $c) {
                $impCol = $firstPairCol + ($j * 2);
                $pcCol  = $impCol + 1;

                $ore = (float)($serv->firstWhere('idConvenzione', $c->idConvenzione)->OreServizio ?? 0);
                $p   = $oreTot > 0 ? ($ore / $oreTot) : 0;

                $r[] = ['col' => $impCol, 'val' => $ore, 'fmt' => NumberFormat::FORMAT_NUMBER];
                $r[] = ['col' => $pcCol,  'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];

                $totByConv[(int)$c->idConvenzione] += $ore;
            }

            $rows[] = $r;
        }

        // Inserimento righe
        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // FIX NOMI (wrap)
        $firstDataRow = $headerRow + 1;
        for ($k = 0; $k < $off; $k++) {
            $rowIndex = $firstDataRow + $k;
            $alignment = $sheet->getStyleByColumnAndRow($cols['NOME'] ?? 2, $rowIndex)->getAlignment();
            $alignment->setWrapText(true);
            $alignment->setShrinkToFit(false);
            $alignment->setVertical(Alignment::VERTICAL_CENTER);
        }

        /* ---------------------- FIX SHRINK GLOBALE ---------------------- */
        $startRowFix = $headerRow + 1;
        $endRowFix   = $totRowNew;
        $startColFix = 1;
        $endColFix   = $lastUsedCol;

        for ($r = $startRowFix; $r <= $endRowFix; $r++) {
            for ($c = $startColFix; $c <= $endColFix; $c++) {
                $al = $sheet->getStyleByColumnAndRow($c, $r)->getAlignment();
                $al->setShrinkToFit(false);
                $al->setWrapText(true);
                $al->setVertical(Alignment::VERTICAL_CENTER);
            }
        }
        /* --------------------------------------------------------------- */

        // Griglia + outline
        $gridRange = $this->col($cols['IDX']) . $headerRow . ':' .
            Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew;
        $this->applyGridWithOuterBorder($sheet, $gridRange);

        // Riga TOTALE
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');
        $sheet->setCellValue("A{$totRowNew}", 'TOTALE');
        $sheet->setCellValue("B{$totRowNew}", null);

        $sheet->setCellValueByColumnAndRow($cols['TOTORE'], $totRowNew, $totOreAll);

        $pairSum = 0.0;
        foreach ($convList as $i => $c) {
            $impCol = $firstPairCol + ($i * 2);
            $pcCol  = $impCol + 1;

            $ore = $totByConv[(int)$c->idConvenzione];

            $sheet->setCellValueByColumnAndRow($impCol, $totRowNew, $ore);
            $sheet->getStyleByColumnAndRow($impCol, $totRowNew)
                ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $p = ($i < ($usedPairs - 1) && $totOreAll > 0)
                ? ($ore / $totOreAll)
                : max(0.0, 1.0 - $pairSum);

            if ($i < ($usedPairs - 1)) {
                $pairSum += $p;
            }

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)
                ->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew, 1, $lastUsedCol);

        $sheet->getStyle('A' . $totRowNew . ':' .
            Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)
            ->getFont()->setBold(true);

        $this->thickOutline($sheet, $headerRow, $totRowNew, 1, $lastUsedCol);

        // Autosize
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** VOLONTARI */
    private function blockVolontari(
        Worksheet $sheet,
        array $tpl,
        $convenzioni,
        array $ricaviMap,
        array $logos
    ): int {
        // detectVolontariHeader deve restituire: [headerRow, firstPairCol, labelCol]
        [$headerRow, $firstPairCol, $labelCol] = $this->detectVolontariHeader($sheet, $tpl);

        // Se la detect non fornisce la colonna etichetta, la deduco dal template
        if (empty($labelCol)) {
            $labelCol = $this->detectLabelMergedColumn($sheet, $headerRow);
        }

        // Reindicizza convenzioni (0..n-1) per evitare buchi negli indici
        $convList  = collect($convenzioni)->values();
        $usedPairs = max(1, $convList->count());

        // Assicura che la prima coppia parta almeno dopo la colonna etichetta
        $firstPairCol = max((int) $firstPairCol, $labelCol + 1);

        // Ultima colonna usata: coppie (rimborso, %) + sicurezza sul labelCol
        $lastUsedCol  = max($labelCol, $firstPairCol + ($usedPairs * 2) - 1);

        // Loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // Intestazioni convenzioni (sopra l’header) → spostate di 1 colonna a destra
        foreach ($convList as $i => $c) {
            $base = ($firstPairCol + 1) + ($i * 2);
            $sheet->setCellValueByColumnAndRow($base, $headerRow - 1, (string) $c->Convenzione);
        }

        // Base dati ricavi
        $totRicavi = array_sum(array_map('floatval', $ricaviMap));

        // Riga dati (subito sotto header)
        $dataRow = $headerRow + 1;

        // RANGE stile della riga campione
        $lastColLetter = ($lastUsedCol > 0)
            ? Coordinate::stringFromColumnIndex($lastUsedCol)
            : 'A';
        $styleRange = "A{$dataRow}:{$lastColLetter}{$dataRow}";

        // 🔹 Unisco le prime 4 celle orizzontali per la “prima colonna”
        //    (es. A..D nella riga dati, come nel template)
        $sheet->mergeCellsByColumnAndRow($labelCol, $dataRow, $labelCol + 3, $dataRow);

        // 1) Totale esercizio nella colonna etichetta mergiata
        $rowData = [[
            'col' => $labelCol,
            'val' => $totRicavi,
            'fmt' => '#,##0.00',
        ]];

        // 2) Coppie RIMBORSO / % per ciascuna convenzione
        $pairSum = 0.0;
        foreach ($convList as $i => $c) {
            // sposto TUTTO di 1 colonna a destra per riallineare alle intestazioni reali
            $base = ($firstPairCol + 1) + ($i * 2);
            $imp  = (float) ($ricaviMap[(int) $c->idConvenzione] ?? 0.0);

            // L’ultima % chiude a 100% gestendo gli arrotondamenti
            $pct = ($i < ($usedPairs - 1) && $totRicavi > 0.0)
                ? ($imp / $totRicavi)
                : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) {
                $pairSum += $pct;
            }

            $rowData[] = ['col' => $base,     'val' => $imp, 'fmt' => '#,##0.00'];
            $rowData[] = ['col' => $base + 1, 'val' => $pct, 'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
        }

        // Inserisce la riga dati PRIMA della riga placeholder
        $this->insertRowsBeforeTotal($sheet, $dataRow, [$rowData], $styleRange);

        // Bordi/header/outline con nuove firme (firstColIdx=1)
        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $dataRow,    1, $lastUsedCol);
        $sheet->getStyle('A' . $dataRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $dataRow)
            ->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $dataRow, 1, $lastUsedCol);

        // Qualità vita
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        return max($dataRow, $tpl['endRow'] + 1);
    }


    /** SERVIZIO CIVILE */
    private function blockServizioCivile(
        Worksheet $sheet,
        array $tpl,
        $convenzioni,
        array $logos
    ): int {
        // detectServizioCivileHeader deve restituire: [headerRow, firstPairCol, labelCol]
        [$headerRow, $firstPairCol, $labelCol] = $this->detectServizioCivileHeader($sheet, $tpl);

        // Se la detect non fornisce labelCol, lo deduco dal template
        if (empty($labelCol)) {
            $labelCol = $this->detectLabelMergedColumn($sheet, $headerRow);
        }

        // Reindicizza convenzioni (0..n-1)
        $convList  = collect($convenzioni)->values();
        $usedPairs = max(1, $convList->count());

        // La prima coppia deve stare almeno dopo la colonna etichetta
        $firstPairCol = max((int) $firstPairCol, $labelCol + 1);

        // Ultima colonna usata: coppie (unità, %) + sicurezza su labelCol
        $lastUsedCol  = max($labelCol, $firstPairCol + ($usedPairs * 2) - 1);

        // Loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // Intestazioni convenzioni (sopra l’header) → spostate di 1 col a destra
        foreach ($convList as $i => $c) {
            $base = ($firstPairCol + 1) + ($i * 2);
            $sheet->setCellValueByColumnAndRow($base, $headerRow - 1, (string) $c->Convenzione);
        }

        // Ore SC per convenzione
        $idsConv = $convList->pluck('idConvenzione')->map(fn($v) => (int) $v)->all();
        $rowsSC  = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $this->idAssociazione)
            ->where('c.idAnno', $this->anno)
            ->where('ds.idDipendente', RipartizioneServizioCivile::ID_SERVIZIO_CIVILE)
            ->whereIn('ds.idConvenzione', $idsConv)
            ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as ore'))
            ->groupBy('ds.idConvenzione')
            ->get();

        $oreByConv = array_fill_keys($idsConv, 0.0);
        $totOre    = 0.0;
        foreach ($rowsSC as $r) {
            $oreByConv[(int) $r->idConvenzione] = (float) $r->ore;
            $totOre += (float) $r->ore;
        }

        // Riga dati (subito sotto header)
        $dataRow = $headerRow + 1;

        $lastColLetter = ($lastUsedCol > 0)
            ? Coordinate::stringFromColumnIndex($lastUsedCol)
            : 'A';
        $styleRange = "A{$dataRow}:{$lastColLetter}{$dataRow}";

        // 🔹 Merge prime 4 celle orizzontali per la colonna etichetta
        $sheet->mergeCellsByColumnAndRow($labelCol, $dataRow, $labelCol + 3, $dataRow);

        // 1) Totale nell'etichetta
        $rowData = [[
            'col' => $labelCol,
            'val' => $totOre,
            'fmt' => NumberFormat::FORMAT_NUMBER,
        ]];

        // 2) Coppie UNITA' / %
        $pairSum = 0.0;
        foreach ($convList as $i => $c) {
            // spostati di +1 colonna
            $base = ($firstPairCol + 1) + ($i * 2);
            $ore  = (float) ($oreByConv[(int) $c->idConvenzione] ?? 0.0);

            // Ultima % chiude a 100% per arrotondamenti
            $p = ($i < ($usedPairs - 1) && $totOre > 0.0)
                ? ($ore / $totOre)
                : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) {
                $pairSum += $p;
            }

            $rowData[] = ['col' => $base,     'val' => $ore, 'fmt' => NumberFormat::FORMAT_NUMBER];
            $rowData[] = ['col' => $base + 1, 'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
        }

        // Inserisci mantenendo la riga placeholder
        $this->insertRowsBeforeTotal($sheet, $dataRow, [$rowData], $styleRange);

        // Stili/bordi aggiornati
        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $dataRow,    1, $lastUsedCol);
        $sheet->getStyle('A' . $dataRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $dataRow)
            ->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $dataRow, 1, $lastUsedCol);

        // Qualità vita
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        return max($dataRow, $tpl['endRow'] + 1);
    }

    /** DISTINTA SERVIZI (Materiale Sanitario) */
    private function blockDistintaServizi(
        Worksheet $sheet,
        array $tpl,
        $automezzi,
        $convenzioni,
        array $logos
    ): int {
        // 0) Dati già pronti dal model
        $dati   = RipartizioneMaterialeSanitario::getRipartizione($this->idAssociazione, $this->anno);
        $conv   = collect($dati['convenzioni']);        // elenco convenzioni (oggetti con idConvenzione, Convenzione, ...)
        $righe  = collect($dati['righe']);              // righe per automezzo + 'totale'
        $totInc = (int) ($dati['totale_inclusi'] ?? 0); // totale servizi inclusi nel riparto

        // 1) Header & colonne
        [$headerRow, $cols] = $this->detectDistintaHeaderAndCols($sheet, $tpl);
        $firstPairCol = $cols['TOTSRVANNO'] + 1;
        $usedPairs    = max(1, $conv->count());
        $lastUsedCol  = max($cols['TOTSRVANNO'], $firstPairCol + ($usedPairs * 2) - 1);

        // 2) Loghi & titoli convenzioni
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);
        foreach ($conv as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string) $c->Convenzione);
        }

        // 3) Stile riga campione e riga 'TOTALE' predisposta
        $sampleRow  = $headerRow + 1;
        $lastColLetter = ($lastUsedCol > 0)
            ? Coordinate::stringFromColumnIndex($lastUsedCol)
            : 'A';
        $styleRange = "A{$sampleRow}:{$lastColLetter}{$sampleRow}";

        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];

        // 4) Righe per ogni automezzo (salta la riga 'totale' del model)
        $rows    = [];
        $idxAuto = 0;

        foreach ($righe as $key => $r) {
            if (!empty($r['is_totale'])) continue; // riga totale del model: la uso dopo

            $idxAuto++;
            $isIncluded  = !empty($r['incluso_riparto']);
            $totVeicolo  = (int) ($r['totale'] ?? 0);
            $valori      = is_array($r['valori'] ?? null) ? $r['valori'] : [];

            $row = [
                ['col' => $cols['PROGR'],      'val' => 'AUTO ' . $idxAuto],
                ['col' => $cols['TARGA'],      'val' => (string) ($r['Targa'] ?? '')],
                ['col' => $cols['CODICE'],     'val' => (string) ($r['CodiceIdentificativo'] ?? '')],
                ['col' => $cols['CONTEGGIO'],  'val' => $isIncluded ? 'SI' : 'NO', 'type' => DataType::TYPE_STRING],
                ['col' => $cols['TOTSRVANNO'], 'val' => $totVeicolo, 'fmt' => NumberFormat::FORMAT_NUMBER],
            ];

            foreach ($conv as $i => $c) {
                $nCol = $firstPairCol + ($i * 2);
                $pcCol = $nCol + 1;

                $n = (int) ($valori[$c->idConvenzione] ?? 0);
                $p = $totVeicolo > 0 ? ($n / $totVeicolo) : 0.0;

                $row[] = ['col' => $nCol,  'val' => $n, 'fmt' => NumberFormat::FORMAT_NUMBER];
                $row[] = ['col' => $pcCol, 'val' => $p, 'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
            }

            $rows[] = $row;
        }

        // 5) Inserisci righe
        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // 6) Riga TOTALE (solo inclusi) – difesa su null
        $rigaTotModel = $righe->get('totale');
        $valTot = is_array($rigaTotModel['valori'] ?? null) ? $rigaTotModel['valori'] : [];

        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');
        $sheet->setCellValueByColumnAndRow($cols['TOTSRVANNO'], $totRowNew, $totInc);

        $pairSum = 0.0;
        foreach ($conv as $i => $c) {
            $nCol = $firstPairCol + ($i * 2);
            $pcCol = $nCol + 1;

            $v = (int) ($valTot[$c->idConvenzione] ?? 0);
            $sheet->setCellValueByColumnAndRow($nCol, $totRowNew, $v);
            $sheet->getStyleByColumnAndRow($nCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $p = ($i < ($usedPairs - 1) && $totInc > 0) ? ($v / $totInc) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // 7) Stili & pulizia
        // >>> thickBorderRow ORA richiede firstColIdx e lastColIdx
        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  1, $lastUsedCol);

        // Outline + griglia interna
        $this->gridWithOutline(
            $sheet,
            'A' . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew
        );

        // Nascondi coppie VAL/% non usate
        //$this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);
        // % centrate per ogni coppia (riga dati → totale)
        for ($i = 0; $i < $usedPairs; $i++) {
            $pcCol = $firstPairCol + ($i * 2) + 1;
            $sheet->getStyleByColumnAndRow($pcCol, $headerRow + 1, $pcCol, $totRowNew)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Freeze: blocca header e le colonne fisse fino a TOTSRVANNO
        $sheet->freezePaneByColumnAndRow($firstPairCol, $headerRow + 1);

        // Auto-size uniforme
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol, 9.5);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** DIST.RIPARTO COSTI DIPENDENTI – sezione Autisti & Barellieri */
    private function blockRipartoCostiDipendentiAB(
        Worksheet $sheet,
        array     $tpl,
        $convenzioni,
        array     $logos
    ): int {

        /* ============================================================
       HEADER + COLONNE
    ============================================================ */
        [$headerRow, $cols] = $this->detectRipartoABHeaderAndCols($sheet, $tpl);

        $convList  = collect($convenzioni)->values();
        $usedPairs = max(1, $convList->count());

        $firstPairCol = $cols['TOTALE'] + 1;
        $lastUsedCol  = $firstPairCol + $usedPairs - 1;
        $lastColLetter = Coordinate::stringFromColumnIndex($lastUsedCol);

        /* ============================================================
       LOGHI + HEADER CONVENZIONI
    ============================================================ */
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        foreach ($convList as $i => $c) {
            $col = $firstPairCol + $i;
            $sheet->setCellValueExplicitByColumnAndRow($col, $headerRow, $c->Convenzione, DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($col, $headerRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setWrapText(true);
        }

        /* ============================================================
       SELEZIONE DIPENDENTI AUTISTI & BARELLIERI
    ============================================================ */

        $dipAll = Dipendente::getByAssociazione($this->idAssociazione, $this->anno);

        $qualPivot = DB::table('dipendenti_qualifiche')
            ->select('idDipendente', 'idQualifica')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($rows) => $rows->pluck('idQualifica')->map(fn($v) => (int)$v)->toArray());

        $qAB = Dipendente::Q_AUTISTA_ID;

        $dip = $dipAll->filter(function ($d) use ($qualPivot, $qAB) {
            $qs = $qualPivot[$d->idDipendente] ?? [];
            return in_array($qAB, $qs, true);
        })->values();

        /* ============================================================
       COSTI + ORE
    ============================================================ */

        $oreG = RipartizionePersonale::getAll($this->anno, null, $this->idAssociazione)
            ->groupBy('idDipendente');

        $costi = DB::table('costi_personale')
            ->where('idAnno', $this->anno)
            ->whereIn('idDipendente', $dip->pluck('idDipendente')->all())
            ->selectRaw('
            idDipendente,
            COALESCE(Retribuzioni,0)              + COALESCE(costo_diretto_Retribuzioni,0)      AS Retribuzioni,
            COALESCE(OneriSocialiInps,0)          + COALESCE(costo_diretto_OneriSocialiInps,0)  AS OneriSocialiInps,
            COALESCE(OneriSocialiInail,0)         + COALESCE(costo_diretto_OneriSocialiInail,0) AS OneriSocialiInail,
            COALESCE(TFR,0)                       + COALESCE(costo_diretto_TFR,0)               AS TFR,
            COALESCE(Consulenze,0)                + COALESCE(costo_diretto_Consulenze,0)        AS Consulenze
        ')
            ->get()
            ->keyBy('idDipendente');

        /* ============================================================
       RIGA CAMPIONE + RIGA TOTALE
    ============================================================ */

        $dataRow  = $headerRow + 1;
        $totalRow = $this->findRowByLabel($sheet, 'TOTALE', $dataRow, $tpl['endRow']) ?? $tpl['endRow'];

        // Prelevo lo stile della riga campione
        $sampleStyle = $sheet->getStyle("A{$dataRow}:{$lastColLetter}{$dataRow}")->exportArray();

        /* ============================================================
       VARIABILI TOTALI GLOBALI
    ============================================================ */

        $totRetr = $totInps = $totInail = $totTfr = $totCons = $totAll = 0.0;
        $totByConvCents = array_fill_keys(
            $convList->pluck('idConvenzione')->map(fn($v) => (int)$v)->all(),
            0
        );

        /* ============================================================
       SCRITTURA RIGHE DIPENDENTI
    ============================================================ */

        $rowIndex = $dataRow; // prima riga dati

        foreach ($dip as $i => $d) {

            $cp = $costi->get($d->idDipendente);

            // costi anche se null
            $retr  = $cp->Retribuzioni      ?? 0;
            $inps  = $cp->OneriSocialiInps  ?? 0;
            $inail = $cp->OneriSocialiInail ?? 0;
            $tfr   = $cp->TFR               ?? 0;
            $cons  = $cp->Consulenze        ?? 0;

            $coeff = CostiMansioni::coeffFor($this->anno, $d->idDipendente, $qAB);
            if ($coeff < 1) $coeff = 1;

            $retr  *= $coeff;
            $inps  *= $coeff;
            $inail *= $coeff;
            $tfr   *= $coeff;
            $cons  *= $coeff;

            $totEuro  = $retr + $inps + $inail + $tfr + $cons;
            $totCents = (int)round($totEuro * 100);

            // Totali globali
            $totRetr += $retr;
            $totInps += $inps;
            $totInail += $inail;
            $totTfr  += $tfr;
            $totCons += $cons;
            $totAll  += $totEuro;

            // Applica stile campione
            $sheet->getStyle("A{$rowIndex}:{$lastColLetter}{$rowIndex}")
                ->applyFromArray($sampleStyle);

            // Scrittura dati base
            $sheet->setCellValueByColumnAndRow($cols['IDX'],     $rowIndex, $i + 1);
            $sheet->setCellValueByColumnAndRow($cols['COGNOME'], $rowIndex, trim($d->DipendenteCognome . ' ' . $d->DipendenteNome));
            $sheet->setCellValueByColumnAndRow($cols['RETR'],    $rowIndex, round($retr, 2));
            $sheet->setCellValueByColumnAndRow($cols['INPS'],    $rowIndex, round($inps, 2));
            $sheet->setCellValueByColumnAndRow($cols['INAIL'],   $rowIndex, round($inail, 2));
            $sheet->setCellValueByColumnAndRow($cols['TFR'],     $rowIndex, round($tfr, 2));
            $sheet->setCellValueByColumnAndRow($cols['CONS'],    $rowIndex, round($cons, 2));
            $sheet->setCellValueByColumnAndRow($cols['TOTALE'],  $rowIndex, round($totEuro, 2));

            // Formato numerico
            foreach ([$cols['RETR'], $cols['INPS'], $cols['INAIL'], $cols['TFR'], $cols['CONS'], $cols['TOTALE']] as $cc) {
                $sheet->getStyleByColumnAndRow($cc, $rowIndex)
                    ->getNumberFormat()->setFormatCode('#,##0.00');
            }

            /* -----------------------------
           RIPARTO ORE SU CONVENZIONI
        ----------------------------- */
            $oreRec = $oreG->get($d->idDipendente, collect());
            $oreTot = (float)$oreRec->sum('OreServizio');

            $prov = array_fill_keys(array_keys($totByConvCents), 0);

            if ($totCents > 0 && $oreTot > 0) {
                $rem = [];
                $sumProv = 0;

                foreach ($convList as $c) {
                    $ore = (float)($oreRec->firstWhere('idConvenzione', $c->idConvenzione)->OreServizio ?? 0);
                    $quota = ($totCents * $ore) / $oreTot;
                    $p = (int)floor($quota);

                    $prov[$c->idConvenzione] = $p;
                    $rem[$c->idConvenzione]  = $quota - $p;
                    $sumProv += $p;
                }

                $diff = $totCents - $sumProv;
                if ($diff > 0) {
                    arsort($rem);
                    foreach (array_keys($rem) as $idC) {
                        if ($diff <= 0) break;
                        $prov[$idC]++;
                        $diff--;
                    }
                }
            }

            foreach ($convList as $j => $c) {
                $col = $firstPairCol + $j;
                $val = round(($prov[$c->idConvenzione] ?? 0) / 100, 2);
                $sheet->setCellValueByColumnAndRow($col, $rowIndex, $val);
                $totByConvCents[$c->idConvenzione] += ($prov[$c->idConvenzione] ?? 0);
            }

            $rowIndex++;
        }

        /* ============================================================
       RIGA TOTALE
    ============================================================ */

        $totRowNew = $rowIndex;

        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        $sheet->setCellValueByColumnAndRow($cols['RETR'],   $totRowNew, round($totRetr, 2));
        $sheet->setCellValueByColumnAndRow($cols['INPS'],   $totRowNew, round($totInps, 2));
        $sheet->setCellValueByColumnAndRow($cols['INAIL'],  $totRowNew, round($totInail, 2));
        $sheet->setCellValueByColumnAndRow($cols['TFR'],    $totRowNew, round($totTfr, 2));
        $sheet->setCellValueByColumnAndRow($cols['CONS'],   $totRowNew, round($totCons, 2));
        $sheet->setCellValueByColumnAndRow($cols['TOTALE'], $totRowNew, round($totAll, 2));

        foreach ($convList as $i => $c) {
            $col = $firstPairCol + $i;
            $val = round(($totByConvCents[$c->idConvenzione] ?? 0) / 100, 2);
            $sheet->setCellValueByColumnAndRow($col, $totRowNew, $val);
        }

        /* ============================================================
       APPLICA STILI FINALI AUTOMATICI
    ============================================================ */

        $this->applyTablePolish(
            $sheet,
            $headerRow,
            1,
            $lastUsedCol,
            $dataRow,
            $totRowNew - 1,
            $totRowNew
        );

        return max($totRowNew, $tpl['endRow']);
    }






    /**
     * DISTINTA COSTI PERSONALE – tabella per UNA mansione (qualifica ID fisso)
     * - Usa il template passato in $tpl
     * - Filtra i dipendenti per qualifica $idQualifica
     * - Applica il coefficiente = percentuale(idQualifica,idDip,anno)/100 se il dipendente ha più qualifiche
     * - Somma le colonne e scrive la riga TOTALE
     */
    private function blockDistintaCostiPerMansione(
        Worksheet $sheet,
        array $tpl,
        int $idQualifica,
        array $logos
    ): int {
        // 1) Header + colonne (INPS/INAIL separati)
        [$headerRow, $cols] = $this->detectMansioneHeaderAndCols($sheet, $tpl);

        $lastUsedCol = $cols['TOTALE'];
        if (!empty($logos['left']) || !empty($logos['right'])) {
            $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);
        }

        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // 2) Dati base
        $dip = Dipendente::getByAssociazione($this->idAssociazione, $this->anno)
            ->filter(function ($d) use ($idQualifica) {
                $ids = DB::table('dipendenti_qualifiche')
                    ->where('idDipendente', $d->idDipendente)
                    ->pluck('idQualifica')->map(fn($v) => (int)$v)->toArray();
                return in_array($idQualifica, $ids, true);
            })->values();

        // costi (base + diretti), già con INPS/INAIL separati
        $costi = DB::table('costi_personale')
            ->where('idAnno', $this->anno)
            ->whereIn('idDipendente', $dip->pluck('idDipendente')->all())
            ->selectRaw('
            idDipendente,
            COALESCE(Retribuzioni,0)              + COALESCE(costo_diretto_Retribuzioni,0)      AS Retribuzioni,
            COALESCE(OneriSocialiInps,0)          + COALESCE(costo_diretto_OneriSocialiInps,0)  AS OneriSocialiInps,
            COALESCE(OneriSocialiInail,0)         + COALESCE(costo_diretto_OneriSocialiInail,0) AS OneriSocialiInail,
            COALESCE(TFR,0)                       + COALESCE(costo_diretto_TFR,0)               AS TFR,
            COALESCE(Consulenze,0)                + COALESCE(costo_diretto_Consulenze,0)        AS Consulenze
        ')
            ->get()->keyBy('idDipendente');

        // percentuali di QUESTA qualifica (idDipendente => %)
        $pctByDip = CostiMansioni::getPercentualiByQualifica($idQualifica, $this->anno); // array

        // 3) Dove scrivere
        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $dataRow    = $headerRow + 1; // prima riga utile: SUBITO sotto l'header
        $lastColLetter = ($lastUsedCol > 0)
            ? Coordinate::stringFromColumnIndex($lastUsedCol)
            : 'A';
        $styleRange = "A{$dataRow}:{$lastColLetter}{$dataRow}";

        $rows = []; // dalla 2ª riga in poi
        $totRetr = $totInps = $totInail = $totTfr = $totCons = $totAll = 0.0;

        $writeRow = function (int $idx, $d) use ($costi, $pctByDip, $cols) {
            $cp = $costi->get($d->idDipendente);
            if (!$cp) return [];

            // coefficiente: se il dip ha più qualifiche, prendi la % salvata per questa qualifica; altrimenti 1.0
            $numQ  = DB::table('dipendenti_qualifiche')->where('idDipendente', $d->idDipendente)->count();
            $coeff = ($numQ > 1) ? (float)($pctByDip[$d->idDipendente] ?? 0) / 100.0 : 1.0;
            if ($coeff <= 0) $coeff = 0.0;

            $retr  = (float)$cp->Retribuzioni      * $coeff;
            $inps  = (float)$cp->OneriSocialiInps  * $coeff;
            $inail = (float)$cp->OneriSocialiInail * $coeff;
            $tfr   = (float)$cp->TFR               * $coeff;
            $cons  = (float)$cp->Consulenze        * $coeff;

            $tot = $retr + $inps + $inail + $tfr + $cons;

            return [
                ['col' => $cols['IDX'],     'val' => ($idx + 1)],
                ['col' => $cols['COGNOME'], 'val' => trim(($d->DipendenteCognome ?? '') . ' ' . ($d->DipendenteNome ?? ''))],
                ['col' => $cols['RETR'],    'val' => round($retr,  2), 'fmt' => '#,##0.00'],
                ['col' => $cols['INPS'],    'val' => round($inps,  2), 'fmt' => '#,##0.00'],
                ['col' => $cols['INAIL'],   'val' => round($inail, 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['TFR'],     'val' => round($tfr,   2), 'fmt' => '#,##0.00'],
                ['col' => $cols['CONS'],    'val' => round($cons,  2), 'fmt' => '#,##0.00'],
                ['col' => $cols['TOTALE'],  'val' => round($tot,   2), 'fmt' => '#,##0.00'],
            ];
        };

        // 4) Scrittura (prima riga sulla riga campione, poi inserisco le altre)
        $i = 0;
        foreach ($dip as $d) {
            $cells = $writeRow($i, $d);
            if (!$cells) {
                $i++;
                continue;
            }

            // totali progressivi
            $totRetr  += $cells[2]['val']; // RETR
            $totInps  += $cells[3]['val']; // INPS
            $totInail += $cells[4]['val']; // INAIL
            $totTfr   += $cells[5]['val']; // TFR
            $totCons  += $cells[6]['val']; // CONS
            $totAll   += $cells[7]['val']; // TOTALE

            if ($i === 0) {
                foreach ($cells as $c) {
                    $sheet->setCellValueByColumnAndRow($c['col'], $dataRow, $c['val']);
                    if (!empty($c['fmt'])) {
                        $sheet->getStyleByColumnAndRow($c['col'], $dataRow)
                            ->getNumberFormat()->setFormatCode($c['fmt']);
                    }
                }
            } else {
                $rows[] = $cells;
            }
            $i++;
        }

        if (!empty($rows)) {
            $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        }
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // 5) Riga TOTALE
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');
        $sheet->setCellValueByColumnAndRow($cols['RETR'],   $totRowNew, round($totRetr, 2));
        $sheet->setCellValueByColumnAndRow($cols['INPS'],   $totRowNew, round($totInps, 2));
        $sheet->setCellValueByColumnAndRow($cols['INAIL'],  $totRowNew, round($totInail, 2));
        $sheet->setCellValueByColumnAndRow($cols['TFR'],    $totRowNew, round($totTfr, 2));
        $sheet->setCellValueByColumnAndRow($cols['CONS'],   $totRowNew, round($totCons, 2));
        $sheet->setCellValueByColumnAndRow($cols['TOTALE'], $totRowNew, round($totAll, 2));
        foreach ([$cols['RETR'], $cols['INPS'], $cols['INAIL'], $cols['TFR'], $cols['CONS'], $cols['TOTALE']] as $cc) {
            $sheet->getStyleByColumnAndRow($cc, $totRowNew)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // 6) Stili — usa le NUOVE firme (firstColIdx, lastColIdx)
        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  1, $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, 1, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        // forza header testo per nome ass./anno
        $this->forceHeaderText($sheet, $tpl, session('nome_associazione') ?? 'ASSOCIAZIONE', $this->anno);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /**
     * DISTINTA RIPARTO AUTOMEZZI
     * Compila la tabella usando i dati di costi_automezzi per l’associazione/anno selezionati.
     */
    private function blockRipartoAutomezzi(
        Worksheet $sheet,
        array $tpl,
        array $logos
    ): int {
        [$headerRow, $cols] = $this->detectAutomezziHeaderAndCols($sheet, $tpl);

        // Calcolo robusto dell’ultima colonna usata
        $lastUsedCol = 0;
        if (is_array($cols)) {
            foreach ($cols as $v) {
                if (is_int($v) && $v > 0) {
                    $lastUsedCol = max($lastUsedCol, $v);
                }
            }
        }
        if ($lastUsedCol <= 0) {
            Log::warning('⚠️ blockRipartoAutomezzi: lastUsedCol non valido, forzato a 1');
            $lastUsedCol = 1;
        }

        foreach ($cols as $v) {
            if (is_int($v)) $lastUsedCol = max($lastUsedCol, $v);
        }

        // Loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 2, $lastUsedCol);

        // Dati base
        $automezzi = Automezzo::getByAssociazione($this->idAssociazione, $this->anno)
            ->sortBy('idAutomezzo')->values();

        $costi = CostiAutomezzi::getAllByAnno($this->anno)
            ->whereIn('idAutomezzo', $automezzi->pluck('idAutomezzo')->all())
            ->keyBy('idAutomezzo');

        // Ancore
        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $sampleRow  = $headerRow + 1;
        $lastColLetter = Coordinate::stringFromColumnIndex($lastUsedCol);
        $styleRange = "A{$sampleRow}:{$lastColLetter}{$sampleRow}";

        // Accumulatori totali (riga TOTALE)
        $acc = [
            'LEASING' => 0,
            'ASSIC' => 0,
            'MAN_ORD' => 0,
            'MAN_STRA' => 0,
            'RIMB_ASS' => 0,
            'PULIZIA' => 0,
            'CARB' => 0,
            'ADDITIVI' => 0,
            'RIMB_UTF' => 0,
            'INTERESSI' => 0,
            'ALTRI' => 0,
            'MAN_SAN' => 0,
            'LEAS_SAN' => 0,
            'AMM_MEZZI' => 0,
            'AMM_SAN' => 0,
            'TOTALE' => 0,
        ];

        $rows = [];
        $i = 0;

        foreach ($automezzi as $a) {
            $c = (array)($costi->get($a->idAutomezzo) ?? []);
            $get = fn($k) => (float)($c[$k] ?? 0);

            $val = [
                'LEASING'   => $get('LeasingNoleggio'),
                'ASSIC'     => $get('Assicurazione'),
                'MAN_ORD'   => $get('ManutenzioneOrdinaria'),
                'MAN_STRA'  => $get('ManutenzioneStraordinaria'),
                'RIMB_ASS'  => $get('RimborsiAssicurazione'),
                'PULIZIA'   => $get('PuliziaDisinfezione'),
                'CARB'      => $get('Carburanti'),
                'ADDITIVI'  => $get('Additivi'),
                'RIMB_UTF'  => $get('RimborsiUTF'),
                'INTERESSI' => $get('InteressiPassivi'),
                'ALTRI'     => $get('AltriCostiMezzi'),
                'MAN_SAN'   => $get('ManutenzioneSanitaria'),
                'LEAS_SAN'  => $get('LeasingSanitaria'),
                'AMM_MEZZI' => $get('AmmortamentoMezzi'),
                'AMM_SAN'   => $get('AmmortamentoSanitaria'),
            ];
            $rowTot = array_sum($val);
            foreach ($val as $k => $v) $acc[$k] += $v;
            $acc['TOTALE'] += $rowTot;

            $cells = [
                ['col' => $cols['IDX'],     'val' => 'AUTO ' . (++$i)],
                ['col' => $cols['TARGA'],   'val' => (string)$a->Targa,               'type' => DataType::TYPE_STRING],
                ['col' => $cols['CODICE'],  'val' => (string)($a->CodiceIdentificativo ?? ''), 'type' => DataType::TYPE_STRING],
                ['col' => $cols['LEASING'],  'val' => round($val['LEASING'],  2), 'fmt' => '#,##0.00'],
                ['col' => $cols['ASSIC'],    'val' => round($val['ASSIC'],    2), 'fmt' => '#,##0.00'],
                ['col' => $cols['MAN_ORD'],  'val' => round($val['MAN_ORD'],  2), 'fmt' => '#,##0.00'],
                ['col' => $cols['MAN_STRA'], 'val' => round($val['MAN_STRA'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['RIMB_ASS'], 'val' => round($val['RIMB_ASS'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['PULIZIA'],  'val' => round($val['PULIZIA'],  2), 'fmt' => '#,##0.00'],
                ['col' => $cols['CARB'],     'val' => round($val['CARB'],     2), 'fmt' => '#,##0.00'],
                ['col' => $cols['ADDITIVI'], 'val' => round($val['ADDITIVI'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['RIMB_UTF'], 'val' => round($val['RIMB_UTF'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['INTERESSI'], 'val' => round($val['INTERESSI'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['ALTRI'],    'val' => round($val['ALTRI'],    2), 'fmt' => '#,##0.00'],
                ['col' => $cols['MAN_SAN'],  'val' => round($val['MAN_SAN'],  2), 'fmt' => '#,##0.00'],
                ['col' => $cols['LEAS_SAN'], 'val' => round($val['LEAS_SAN'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['AMM_MEZZI'], 'val' => round($val['AMM_MEZZI'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['AMM_SAN'],  'val' => round($val['AMM_SAN'],  2), 'fmt' => '#,##0.00'],
            ];

            if ($i === 1) {
                foreach ($cells as $cell) {
                    $sheet->setCellValueByColumnAndRow($cell['col'], $sampleRow, $cell['val']);
                    if (!empty($cell['type'])) {
                        $sheet->getCellByColumnAndRow($cell['col'], $sampleRow)
                            ->setValueExplicit($cell['val'], $cell['type']);
                    }
                    if (!empty($cell['fmt'])) {
                        $sheet->getStyleByColumnAndRow($cell['col'], $sampleRow)
                            ->getNumberFormat()->setFormatCode($cell['fmt']);
                    }
                }
            } else {
                $rows[] = $cells;
            }
        }

        if (!empty($rows)) {
            $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        }
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // Riga TOTALE (riga, non colonna)
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');
        foreach (['LEASING', 'ASSIC', 'MAN_ORD', 'MAN_STRA', 'RIMB_ASS', 'PULIZIA', 'CARB', 'ADDITIVI', 'RIMB_UTF', 'INTERESSI', 'ALTRI', 'MAN_SAN', 'LEAS_SAN', 'AMM_MEZZI', 'AMM_SAN'] as $k) {
            if (!empty($cols[$k])) {
                $sheet->setCellValueByColumnAndRow($cols[$k], $totRowNew, round($acc[$k], 2));
                $sheet->getStyleByColumnAndRow($cols[$k], $totRowNew)->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        // Stili (NUOVE firme)
        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  1, $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)
            ->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, 1, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /**
     * DISTINTA RIPARTO COSTI RADIO
     * Divide in parti uguali i totali di costi_radio fra gli automezzi dell’associazione/anno.
     * Gestisce l’eventuale colonna “spacer” vuota tra MONT e LOCA nel template.
     */
    private function blockCostiRadio(Worksheet $sheet, array $tpl, array $logos): int {
        // 1) Header & mappa colonne del template
        [$headerRow, $cols] = $this->detectCostiRadioHeaderAndCols($sheet, $tpl);

        // ultima colonna visiva (ignora spacer)
        $candidateCols = [];
        foreach (['IDX', 'TARGA', 'MANT', 'MONT', 'LOCA', 'AMMO'] as $k) {
            if (isset($cols[$k]) && is_int($cols[$k])) {
                $candidateCols[] = $cols[$k];
            }
        }
        $lastVisualCol = !empty($candidateCols) ? max($candidateCols) : 1;

        // 1.1) Spacer tra MONT e LOCA
        if (isset($cols['MONT'], $cols['LOCA']) && $cols['LOCA'] >= ($cols['MONT'] + 2)) {
            $spacerCol = $cols['MONT'] + 1;
            $sheet->getColumnDimensionByColumn($spacerCol)->setVisible(false);
            $sheet->getColumnDimensionByColumn($spacerCol)->setWidth(2);
        }
        /*--------------------------------------------------------------
        | 2) LOGHI: sposta SOLO quelli di intestazione di 20px a sinistra
        *--------------------------------------------------------------*/
        $logoRow = $tpl['startRow'] + 1;   // di solito 2

        $leftColLetter  = 'B';   // LOGO SINISTRO è in B2
        $rightColLetter = 'H';   // LOGO DESTRO è in G2 (non H!)

        foreach ($sheet->getDrawingCollection() as $drawing) {
            if (!$drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                continue;
            }

            $coord = $drawing->getCoordinates();

            if ($coord === $leftColLetter . $logoRow || $coord === $rightColLetter . $logoRow) {
                // sposta di 20px verso sinistra
                $drawing->setOffsetX($drawing->getOffsetX() - 90);
            }
        }

        // 3) Dati base
        $automezzi = Automezzo::getByAssociazione($this->idAssociazione, $this->anno)->values();
        $numAut    = max(count($automezzi), 1);

        $tot = DB::table('costi_radio')
            ->where('idAnno', $this->anno)
            ->where('idAssociazione', $this->idAssociazione)
            ->first();

        $T_MANT = (float)($tot->ManutenzioneApparatiRadio   ?? 0);
        $T_MONT = (float)($tot->MontaggioSmontaggioRadio118 ?? 0);
        $T_LOCA = (float)($tot->LocazionePonteRadio         ?? 0);
        $T_AMMO = (float)($tot->AmmortamentoImpiantiRadio   ?? 0);

        $Q_MANT = $T_MANT / $numAut;
        $Q_MONT = $T_MONT / $numAut;
        $Q_LOCA = $T_LOCA / $numAut;
        $Q_AMMO = $T_AMMO / $numAut;

        // 4) Dove scrivere
        $totalRow  = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $sampleRow = $headerRow + 1; // prima riga utile sotto header

        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastVisualCol) . $sampleRow;

        // 5) Righe automezzi
        $rows = [];
        $i = 0;
        foreach ($automezzi as $a) {
            $cells = [
                ['col' => $cols['IDX'],   'val' => 'AUTO ' . (++$i)],
                ['col' => $cols['TARGA'], 'val' => (string)$a->Targa, 'type' => DataType::TYPE_STRING],
                ['col' => $cols['MANT'],  'val' => round($Q_MANT, 2), 'fmt'  => '#,##0.00'],
                ['col' => $cols['MONT'],  'val' => round($Q_MONT, 2), 'fmt'  => '#,##0.00'],
                ['col' => $cols['LOCA'],  'val' => round($Q_LOCA, 2), 'fmt'  => '#,##0.00'],
                ['col' => $cols['AMMO'],  'val' => round($Q_AMMO, 2), 'fmt'  => '#,##0.00'],
            ];

            if ($i === 1) {
                // prima riga: usa la riga campione già presente
                foreach ($cells as $c) {
                    if (!empty($c['type'])) {
                        $sheet->setCellValueExplicitByColumnAndRow($c['col'], $sampleRow, $c['val'], $c['type']);
                    } else {
                        $sheet->setCellValueByColumnAndRow($c['col'], $sampleRow, $c['val']);
                    }
                    if (!empty($c['fmt'])) {
                        $sheet->getStyleByColumnAndRow($c['col'], $sampleRow)
                            ->getNumberFormat()->setFormatCode($c['fmt']);
                    }
                }
            } else {
                $rows[] = $cells;
            }
        }

        // inserisci righe aggiuntive
        if (!empty($rows)) {
            $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        }
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // riga finale visibile/auto-height
        $sheet->getRowDimension($totRowNew)->setVisible(true);
        $sheet->getRowDimension($totRowNew)->setRowHeight(-1);

        // 6) Riga TOTALE
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        $sheet->setCellValueByColumnAndRow($cols['MANT'], $totRowNew, round($T_MANT, 2));
        $sheet->setCellValueByColumnAndRow($cols['MONT'], $totRowNew, round($T_MONT, 2));
        $sheet->setCellValueByColumnAndRow($cols['LOCA'], $totRowNew, round($T_LOCA, 2));
        $sheet->setCellValueByColumnAndRow($cols['AMMO'], $totRowNew, round($T_AMMO, 2));

        foreach ([$cols['MANT'], $cols['MONT'], $cols['LOCA'], $cols['AMMO']] as $cc) {
            $sheet->getStyleByColumnAndRow($cc, $totRowNew)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyleByColumnAndRow($cc, $totRowNew)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // stile totale
        $rowRange = 'A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastVisualCol) . $totRowNew;
        $sheet->getStyle($rowRange)->getFont()->setBold(true);
        $sheet->getStyle($rowRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THICK],
            ],
        ]);

        // 7) Box in alto (F4-F5)
        $totBil = round($T_MANT + $T_MONT + $T_LOCA + $T_AMMO, 2);

        $sheet->setCellValue('F4', $totBil);
        $sheet->getStyle('F4')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->setCellValue('F5', $numAut);
        $sheet->getStyle('F5')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $this->setValueRightOfLabel($sheet, 'NUMERO TOTALE AUTOMEZZI', $numAut);
        $this->setValueRightOfLabel($sheet, 'TOTALI A BILANCIO', $totBil, '#,##0.00');

        // 8) Cornici
        $this->thickBorderRow($sheet, $headerRow, 1, $lastVisualCol);
        $this->thickOutline($sheet, $headerRow, $totRowNew, 1, $lastVisualCol);

        // 9) autosize
        $this->autosizeUsedColumns($sheet, 1, $lastVisualCol);

        return max($totRowNew, $tpl['endRow'] + $off);
    }


    /* ===================== UTILS PER BLOCCHI ===================== */
    private function detectKmHeaderAndCols(Worksheet $sheet, array $tpl): array {
        $headerRow = $this->findHeaderRowKm($sheet, $tpl['startRow'], $tpl['endRow']);
        $cols = ['PROGR' => 1, 'TARGA' => 2, 'CODICE' => 3, 'KMTOT' => 4];

        for ($c = 1; $c <= 80; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($t === 'TARGA') $cols['TARGA'] = $c;
            if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $cols['CODICE'] = $c;
            if ($t === "KM. TOTALI PERCORSI NELL'ANNO" || $t === "KM. TOTALI PERCORSI NELL' ANNO" || str_starts_with($t, 'KM. TOTALI')) $cols['KMTOT'] = $c;
        }
        return [$headerRow, $cols];
    }

    /**
     * Riconosce l’header della “Distinta Costi Radio” e ritorna [headerRow, cols].
     * TOTALE è una riga, non una colonna.
     */
    private function detectCostiRadioHeaderAndCols(Worksheet $sheet, array $tpl): array {
        // trova una riga con “TARGA” e almeno una delle 4 voci
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 220, $tpl['endRow']); $r++) {
            $hitsTarga = $hitsVoci = 0;
            for ($c = 1; $c <= 120; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'TARGA') $hitsTarga++;
                if (str_contains($t, 'MANUTENZIONE') || str_contains($t, 'MONTAGGIO') || str_contains($t, 'LOCAZIONE') || str_contains($t, 'AMMORTAMENTO')) {
                    $hitsVoci++;
                }
            }
            if ($hitsTarga > 0 && $hitsVoci > 0) {
                $headerRow = $r;
                break;
            }
        }

        $cols = ['IDX' => 1, 'TARGA' => 2, 'MANT' => null, 'MONT' => null, 'LOCA' => null, 'AMMO' => null];
        for ($c = 1; $c <= 120; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($t === 'TARGA') $cols['TARGA'] = $c;
            if (str_contains($t, 'MANUTENZIONE') && str_contains($t, 'APPARATI')) $cols['MANT'] = $c;
            if (str_contains($t, 'MONTAGGIO')   && str_contains($t, '118'))       $cols['MONT'] = $c;
            if (str_contains($t, 'LOCAZIONE')   && str_contains($t, 'PONTE'))     $cols['LOCA'] = $c;
            if (str_contains($t, 'AMMORTAMENTO') && str_contains($t, 'IMPIANTI'))  $cols['AMMO'] = $c;
        }

        // fallback ragionevoli se qualcosa manca
        $cursor = $cols['TARGA'];
        foreach (['MANT', 'MONT', 'LOCA', 'AMMO'] as $k) {
            if (!$cols[$k]) $cols[$k] = ++$cursor;
        }

        $cols['IDX'] = max(1, $cols['TARGA'] - 1);
        return [$headerRow, $cols];
    }


    private function detectServiziHeaderAndCols(Worksheet $sheet, array $tpl): array {
        $headerRow = $this->findHeaderRowServizi($sheet, $tpl['startRow'], $tpl['endRow']);
        $cols = ['PROGR' => 1, 'TARGA' => 2, 'CODICE' => 3, 'TOTSRV' => 4];
        for ($c = 1; $c <= 80; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($t === 'TARGA') $cols['TARGA'] = $c;
            if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $cols['CODICE'] = $c;
            if (str_starts_with($t, 'TOTALI NUMERO SERVIZI')) $cols['TOTSRV'] = $c;
        }
        return [$headerRow, $cols];
    }

    private function detectABHeaderAndCols(Worksheet $sheet, array $tpl): array {
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 500, $tpl['endRow']); $r++) {
            $hits = 0;
            for ($c = 1; $c <= 100; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if (in_array($t, ['DIPENDENTE', 'COGNOME DEL DIPENDENTE', 'NOME E COGNOME', 'ORE DI SERVIZIO']) || str_starts_with($t, 'ORE TOTALI')) $hits++;
            }
            if ($hits >= 3) {
                $headerRow = $r;
                break;
            }
        }
        $cols = ['IDX' => 1, 'NOME' => null, 'TOTORE' => null];
        for ($c = 1; $c <= 120; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($t === 'COGNOME DEL DIPENDENTE' || $t === 'DIPENDENTE' || $t === 'NOME E COGNOME') $cols['NOME'] = $c;
            if (str_contains($t, 'ORE TOTALI')) $cols['TOTORE'] = $c;
        }
        if (!$cols['TOTORE']) $cols['TOTORE'] = ($cols['NOME'] ?? 2) + 1;
        return [$headerRow, $cols];
    }

    private function detectVolontariHeader(Worksheet $sheet, array $tpl): array {
        // 1) Trova la riga header (dove compaiono PERSONALE o %)
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 60, $tpl['endRow']); $r++) {
            for ($c = 1; $c <= 80; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'PERSONALE' || $t === '%') {
                    $headerRow = $r;
                    break 2;
                }
            }
        }

        // 2) Colonna etichetta (PERSONALE) se presente
        $labelCol = 1;
        for ($c = 1; $c <= 120; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($t === 'PERSONALE') {
                $labelCol = $c;
                break;
            }
        }

        // 3) Prima coppia disponibile (UNITÀ / %) subito a destra della label
        $firstPairCol = $labelCol + 1;
        for ($c = $firstPairCol; $c <= 80; $c++) {
            $vL = (string)$sheet->getCellByColumnAndRow($c,     $headerRow)->getValue();
            $vR = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRow)->getValue();
            if ($vL !== '' || $vR !== '') {
                $firstPairCol = $c;
                break;
            }
        }

        return [$headerRow, $firstPairCol, $labelCol];
    }

    private function detectServizioCivileHeader(Worksheet $sheet, array $tpl): array {
        // trova la riga header (quella dove compaiono PERSONALE | UNITA' DI SERVIZIO | %)
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 80, $tpl['endRow']); $r++) {
            $hits = 0;
            for ($c = 1; $c <= 120; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if (in_array($t, ['PERSONALE', "UNITA' DI SERVIZIO", '%'])) $hits++;
            }
            if ($hits >= 2) {
                $headerRow = $r;
                break;
            }
        }

        // colonna della label (PERSONALE)
        $labelCol = 1;
        for ($c = 1; $c <= 120; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($t === 'PERSONALE') {
                $labelCol = $c;
                break;
            }
        }

        // prima coppia valori (UNITÀ / %)
        $firstPairCol = null;
        for ($c = $labelCol + 1; $c <= 120; $c++) {
            $vL = (string)$sheet->getCellByColumnAndRow($c,     $headerRow)->getValue();
            $vR = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRow)->getValue();
            if ($vL !== '' || $vR !== '') {
                $firstPairCol = $c;
                break;
            }
        }

        return [$headerRow, $firstPairCol ?? ($labelCol + 1), $labelCol];
    }

    private function locateRicaviHeader(Worksheet $sheet, int $startRow): array {
        for ($r = $startRow; $r <= $startRow + 200; $r++) {
            $firstPairCol = null;
            $totCol = null;
            for ($c = 1; $c <= 80; $c++) {
                $txt = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($firstPairCol === null && $txt === 'RIMBORSO') $firstPairCol = $c;
                if ($totCol === null && $txt !== '' && str_starts_with($txt, 'TOTALE RICAVI')) $totCol = $c;
            }
            if ($firstPairCol !== null) {
                return [
                    'headerRow'    => $r,
                    'convTitleRow' => max(1, $r - 1),
                    'dataRow'      => $r + 1,
                    'firstPairCol' => $firstPairCol,
                    'totCol'       => $totCol ?? max(1, $firstPairCol - 1),
                ];
            }
        }
        return [
            'headerRow'    => $startRow + 2,
            'convTitleRow' => $startRow + 1,
            'dataRow'      => $startRow + 1,
            'firstPairCol' => 6,
            'totCol'       => 4,
        ];
    }

    /** Inserisce N righe vuote dopo una riga. Ritorna la nuova riga finale (afterRow + n). */
    private function addSpacerRows(Worksheet $sheet, int $afterRow, int $n = 2): int {
        if ($n > 0) $sheet->insertNewRowBefore($afterRow + 1, $n);
        return $afterRow + $n;
    }

    /** Unisce A:B sulla riga totale e scrive la label (default 'TOTALE') nella colonna A. */
    private function mergeTotalAB(Worksheet $sheet, int $row, string $label = 'TOTALE'): void {
        // Calcola range A:B della riga corrente
        $range = "A{$row}:B{$row}";

        // Se il range è già mergiato, non rifarlo
        foreach ($sheet->getMergeCells() as $existing) {
            if (strtoupper($existing) === strtoupper($range)) {
                // già presente → evitiamo di crearne un duplicato
                $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
                $sheet->setCellValue("B{$row}", null);
                return;
            }
        }

        // Se esistono merge che si sovrappongono (es. A1:B2), smontali
        foreach ($sheet->getMergeCells() as $existing) {
            [$start, $end] = explode(':', $existing);
            [$c1, $r1] = Coordinate::coordinateFromString($start);
            [$c2, $r2] = Coordinate::coordinateFromString($end);

            $r1 = (int)$r1;
            $r2 = (int)$r2;

            if ($row >= $r1 && $row <= $r2 && (in_array($c1, ['A', 'B']) || in_array($c2, ['A', 'B']))) {
                $sheet->unmergeCells($existing);
            }
        }

        // Esegui il merge “pulito”
        $sheet->mergeCells($range);
        $sheet->setCellValueExplicit("A{$row}", strtoupper($label), DataType::TYPE_STRING);
        $sheet->setCellValue("B{$row}", null);
    }

    /**
     * Nasconde tutte le coppie VAL/% oltre quelle realmente usate.
     * Niente scan: mi fido di usedPairs.
     */
    private function hideUnusedConventionColumns(
        Worksheet $s,
        int $headerRow,
        int $firstPairCol, // prima colonna KM della prima convenzione
        int $usedPairs     // quante convenzioni effettive (ogni conv = 2 colonne: KM + %)
    ): void {
        // 1) Unhide le colonne effettivamente usate
        $lastUsedCol = $firstPairCol + ($usedPairs * 2) - 1;
        for ($c = $firstPairCol; $c <= $lastUsedCol; $c++) {
            $s->getColumnDimensionByColumn($c)->setVisible(true);
            // opzionale: assegna larghezza minima per evitare ####
            $s->getColumnDimensionByColumn($c)->setAutoSize(false);
            $s->getColumnDimensionByColumn($c)->setWidth(11.5);
        }

        // 2) Nascondi solo quelle DOPO
        $maxC = Coordinate::columnIndexFromString($s->getHighestColumn());
        for ($c = $lastUsedCol + 1; $c <= $maxC; $c++) {
            $s->getColumnDimensionByColumn($c)->setVisible(false);
        }
    }

    /**
     * Inserisce N righe prima di $totalRow, copiando stile da $styleRange,
     * e scrive i valori secondo la mappa generata dai blocchi.
     */
    private function insertRowsBeforeTotal(Worksheet $sheet, int $totalRow, array $rowsData, string $styleRange): void {
        if (empty($rowsData)) return;

        $count = count($rowsData);
        $sheet->insertNewRowBefore($totalRow, $count);

        [$a1, $a2] = explode(':', $styleRange);
        $sCol = Coordinate::columnIndexFromString(preg_replace('/\d+$/', '', $a1));
        $eCol = Coordinate::columnIndexFromString(preg_replace('/\d+$/', '', $a2));

        for ($i = 0; $i < $count; $i++) {
            $targetRow = $totalRow + $i;
            $sheet->duplicateStyle($sheet->getStyle($styleRange), Coordinate::stringFromColumnIndex($sCol) . $targetRow . ':' . Coordinate::stringFromColumnIndex($eCol) . $targetRow);

            foreach ($rowsData[$i] as $cell) {
                $col = (int)$cell['col'];
                $val = $cell['val'] ?? null;
                $type = $cell['type'] ?? null;
                $fmt  = $cell['fmt']  ?? null;
                $al   = $cell['align'] ?? null;

                if ($type) $sheet->setCellValueExplicitByColumnAndRow($col, $targetRow, $val, $type);
                else       $sheet->setCellValueByColumnAndRow($col, $targetRow, $val);

                if ($fmt) $sheet->getStyleByColumnAndRow($col, $targetRow)->getNumberFormat()->setFormatCode($fmt);
                if ($al)  $sheet->getStyleByColumnAndRow($col, $targetRow)->getAlignment()->setHorizontal($al);
            }
        }
    }



    /* ===================== COMMON HELPERS (stili, loghi, template, ecc.) ===================== */

    private function slugify(string $s): string {
        $s = preg_replace('/[^\pL\d]+/u', '_', $s);
        $s = trim($s, '_');
        $s = preg_replace('/_+/', '_', $s);
        return strtolower($s ?: 'export');
    }

    private function replacePlaceholdersEverywhere(Worksheet $s, array $map): void {
        if (!$map) return;
        $maxR = $s->getHighestRow();
        $maxC = Coordinate::columnIndexFromString($s->getHighestColumn());

        // Precompila regex per chiavi con vari delimitatori
        $rx = [];
        foreach ($map as $k => $v) {
            $q = preg_quote((string)$k, '/');
            $rx[$k] = [
                "/\\{\\{\\s*{$q}\\s*\\}\\}/ui",
                "/\\[\\[\\s*{$q}\\s*\\]\\]/ui",
                "/<<\\s*{$q}\\s*>>/ui",
                "/%\\s*{$q}\\s*%/ui",
            ];
        }

        for ($r = 1; $r <= $maxR; $r++) {
            for ($c = 1; $c <= $maxC; $c++) {
                $cell = $s->getCellByColumnAndRow($c, $r);
                $val  = $cell->getValue();
                $text = $val instanceof RichText ? $val->getPlainText() : (is_string($val) ? $val : null);
                if ($text === null || $text === '') continue;

                $orig = $text;
                foreach ($rx as $k => $patterns) {
                    foreach ($patterns as $p) $text = preg_replace($p, (string)$map[$k], $text);
                    $text = str_replace($k, (string)$map[$k], $text); // fallback “chiave nuda”
                }
                $text = preg_replace('/\s{2,}/', ' ', $text);

                if ($text !== $orig) $cell->setValueExplicit($text, DataType::TYPE_STRING);
            }
        }
    }

    /** Trova la riga che contiene (case-insensitive) $label nel range dato. */
    private function findRowByLabel(Worksheet $sheet, string $needle, int $fromRow, int $toRow): ?int {
        // normalizzo l'ago con la stessa logica usata per le etichette
        $needle = $this->canonLabel($needle);
        if ($needle === '') return null;

        // non scansiono più di quanto serve
        $maxCol = min(
            150,
            Coordinate::columnIndexFromString($sheet->getHighestColumn())
        );

        for ($r = $fromRow; $r <= $toRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $raw = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                $txt = $this->canonLabel($raw);
                if ($txt !== '' && str_contains($txt, $needle)) {
                    return $r;
                }
            }
        }
        return null;
    }

    private function insertLogosAtRow(Worksheet $sheet, array $images, int $row, ?int $rightColIdx = null): void {
        $row = max(1, $row);

        // Normalizza path (devono essere assoluti)
        $leftPath  = isset($images['left'])  ? (string)$images['left']  : '';
        $rightPath = isset($images['right']) ? (string)$images['right'] : '';

        // LOGO SX
        if ($leftPath !== '' && is_file($leftPath)) {
            $d = new Drawing();
            $d->setName('Logo Left');
            $d->setPath($leftPath);
            $d->setResizeProportional(true);
            $d->setHeight(60);                // ~45pt
            $d->setCoordinates('B' . $row);   // ancoraggio
            $d->setOffsetX(5);
            $d->setOffsetY(5);
            $d->setWorksheet($sheet);
        }

        // LOGO DX
        if ($rightPath !== '' && is_file($rightPath)) {
            // Se non specificato, usa l’ultima colonna dati
            if (!$rightColIdx) {
                $maxColIdx   = Coordinate::columnIndexFromString($sheet->getHighestDataColumn() ?: 'A');
                // mettilo nell’ultima colonna utile (almeno J per sicurezza)
                $rightColIdx = max(10, $maxColIdx);
            }
            $rightColL = Coordinate::stringFromColumnIndex($rightColIdx);

            $d2 = new Drawing();
            $d2->setName('Logo Right');
            $d2->setPath($rightPath);
            $d2->setResizeProportional(true);
            $d2->setHeight(60);
            $d2->setCoordinates($rightColL . $row);
            $d2->setOffsetX(5);
            $d2->setOffsetY(5);
            $d2->setWorksheet($sheet);
        }

        // Alza la riga per ospitare i loghi (pt, non px)
        $sheet->getRowDimension($row)->setRowHeight(48);
    }


    /**
     * Trova la prima cella che contiene esattamente $label (case insensitive)
     * e scrive il valore nella cella immediatamente a destra.
     * Se $fmt è passato, applica il formato numerico.
     */
    private function setValueRightOfLabel(Worksheet $sheet, string $label, $value, ?string $fmt = null): void {
        $maxRow = $sheet->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $needle = mb_strtoupper(trim($label));

        for ($r = 1; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                if ($v instanceof RichText) $v = $v->getPlainText();
                if (is_string($v) && mb_strtoupper(trim($v)) === $needle) {
                    $sheet->setCellValueByColumnAndRow($c + 1, $r, $value);
                    if ($fmt) {
                        $sheet->getStyleByColumnAndRow($c + 1, $r)->getNumberFormat()->setFormatCode($fmt);
                    }
                    return;
                }
            }
        }
    }

    /* ===================== HEADER FINDERS RICICLATI ===================== */



    private function forceHeaderText(Worksheet $sheet, array $tpl, string $nomeAss, int $anno): void {
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $top    = $tpl['startRow'];            // intestazione del template
        $bottom = min($top + 8, $tpl['endRow']); // prime righe dell’header

        for ($r = $top; $r <= $bottom; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $v = (string) ($cell->getValue() instanceof RichText
                    ? $cell->getValue()->getPlainText()
                    : $cell->getValue());

                // rimpiazzo forzato dei pattern noti
                if (
                    stripos($v, '{{nome_associazione}}') !== false
                    || stripos($v, '[[nome_associazione]]') !== false
                    || stripos($v, '%nome_associazione%') !== false
                ) {
                    $cell->setValueExplicit($nomeAss, DataType::TYPE_STRING);
                }

                if (stripos($v, 'consuntivo') !== false && (stripos($v, '{{anno}}') !== false
                    || stripos($v, '[[anno_riferimento]]') !== false
                    || stripos($v, '%anno_riferimento%') !== false)) {
                    $cell->setValueExplicit('CONSUNTIVO ' . $anno, DataType::TYPE_STRING);
                }
            }
        }
    }

    /**
     * Crea un NUOVO foglio partendo dal template e compila la tabella
     * “MATERIALE SANITARIO DI CONSUMO”.
     */
    private function creaFoglioImputazioneSanitario(
        Spreadsheet $wb,
        string $templatePath,
        string $nomeAssociazione,
        int $idAssociazione,
        int $anno
    ): void {
        try {

            /*--------------------------------------------------------------
        | 1) CREA FOGLIO + TEMPLATE
        *--------------------------------------------------------------*/
            $s = $wb->createSheet();
            $s->setTitle('MATERIALE SANITARIO DI CONSUMO');

            $tpl = $this->appendTemplate($s, $templatePath, 1);


            /*--------------------------------------------------------------
        | 2) LOGHI — posizioni FISSE (B2 e E2)
        *--------------------------------------------------------------*/
            if (!empty($this->logos['left'])) {
                $this->placeLogoAtRow(
                    $s,
                    $tpl,
                    $this->logos['left'],
                    2,       // row
                    2,       // col B
                    90,
                    12,  // width/height
                    0,
                    0     // offset
                );
            }

            if (!empty($this->logos['right'])) {
                $this->placeLogoAtRow(
                    $s,
                    $tpl,
                    $this->logos['right'],
                    2,       // row
                    5,       // col E
                    110,
                    16, // width/height
                    0,
                    0
                );
            }


            /*--------------------------------------------------------------
        | 3) PLACEHOLDERS HEADER
        *--------------------------------------------------------------*/
            $this->replacePlaceholdersEverywhere($s, [
                'nome_associazione' => $nomeAssociazione,
                'ASSOCIAZIONE'      => $nomeAssociazione,
                'anno_riferimento'  => (string)$anno,
                'ANNO'              => (string)$anno,
            ]);


            /*--------------------------------------------------------------
        | 4) RECUPERO DATI
        *--------------------------------------------------------------*/
            $totBilancio = (float)(CostoMaterialeSanitario::getTotale($idAssociazione, $anno) ?? 0);

            $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);

            $rip  = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);
            $ripM = [];
            foreach ($rip['righe'] ?? [] as $r) {
                if (empty($r['is_totale'])) {
                    $ripM[(int)$r['idAutomezzo']] = $r;
                }
            }

            $convASL = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
                ->filter(fn($c) => (int)$c->materiale_fornito_asl === 1)
                ->pluck('idConvenzione')->map(fn($v) => (int)$v)->all();
            $convASL = array_flip($convASL);

            $kmGroups = AutomezzoKm::getGroupedByAutomezzoAndConvenzione(
                $anno,
                Auth::user(),
                $idAssociazione
            );


            /*--------------------------------------------------------------
        | 5) SERVIZI ADJUSTED
        *--------------------------------------------------------------*/
            $rows = [];
            $totAdj = 0;

            foreach ($automezzi as $auto) {
                $idA = (int)$auto->idAutomezzo;

                $r    = $ripM[$idA] ?? null;
                $incl = $r ? !empty($r['incluso_riparto']) : false;
                $val  = $r ? (array)($r['valori'] ?? []) : [];

                $adj = 0;
                if ($incl) {
                    foreach ($val as $idConv => $numServ) {
                        $n = (int)$numServ;

                        if (isset($convASL[(int)$idConv])) {
                            $key = $idA . '-' . (int)$idConv;
                            $km  = $kmGroups->has($key)
                                ? (float)$kmGroups->get($key)->sum('KMPercorsi')
                                : 0;
                            $n = max(0, $n - (int)round($km));
                        }

                        $adj += $n;
                    }

                    $totAdj += $adj;
                }

                $rows[] = [
                    'targa'   => (string)$auto->Targa,
                    'servizi' => $incl ? $adj : 0,
                    'incluso' => $incl
                ];
            }


            /*--------------------------------------------------------------
        | 6) TABLE HEADER DETECTION
        *--------------------------------------------------------------*/
            [$hdrRow, $cTarga, $cN, $cPerc, $cImp] = $this->locateImputazioneSanitarioHeader($s);

            $r = $hdrRow + 1;
            $sumServ = 0;
            $sumImp  = 0;
            $last    = $r;


            /*--------------------------------------------------------------
        | 7) SCRITTURA RIGHE
        *--------------------------------------------------------------*/
            foreach ($rows as $i => $rw) {
                $s->setCellValueByColumnAndRow($cTarga - 1, $r, 'AUTO ' . ($i + 1));
                $s->setCellValueByColumnAndRow($cTarga,     $r, $rw['targa']);
                $s->setCellValueByColumnAndRow($cN,         $r, $rw['servizi']);

                $pct = ($rw['incluso'] && $totAdj > 0)
                    ? ($rw['servizi'] / $totAdj)
                    : 0;

                $imp = $rw['incluso'] ? round($totBilancio * $pct, 2) : 0;

                $s->setCellValueByColumnAndRow($cPerc, $r, $pct);
                $s->setCellValueByColumnAndRow($cImp, $r, $imp);

                if ($rw['incluso']) {
                    $sumServ += $rw['servizi'];
                    $sumImp  += $imp;
                }

                $this->copyRowStyle($s, $hdrRow + 1, $r);

                $last = $r;
                $r++;
            }


            /*--------------------------------------------------------------
        | 8) RIGA TOTALE
        *--------------------------------------------------------------*/
            $rTot = $last + 1;

            $s->setCellValueByColumnAndRow($cTarga - 1, $rTot, 'TOTALE');
            $s->setCellValueByColumnAndRow($cN,        $rTot, $sumServ);

            $delta = round($totBilancio - $sumImp, 2);
            if (abs($delta) >= 0.01) {
                for ($rr = $last; $rr >= $hdrRow + 1; $rr--) {
                    $n0 = (float)$s->getCellByColumnAndRow($cN, $rr)->getCalculatedValue();
                    if ($n0 > 0) {
                        $v0 = (float)$s->getCellByColumnAndRow($cImp, $rr)->getCalculatedValue();
                        $s->setCellValueByColumnAndRow($cImp, $rr, round($v0 + $delta, 2));
                        $sumImp = round($sumImp + $delta, 2);
                        break;
                    }
                }
            }

            $s->setCellValueByColumnAndRow($cImp, $rTot, $sumImp);


            /*--------------------------------------------------------------
        | 9) BOX IN TESTA
        *--------------------------------------------------------------*/
            $this->writeTopBoxesImputazioneSanitario($s, $totBilancio, $totAdj);


            /*--------------------------------------------------------------
        | 10) E5: totale servizi
        *--------------------------------------------------------------*/
            $totAnn = AutomezzoServiziSvolti::getTotaleByAssociazioneAnno($idAssociazione, $anno);
            $s->setCellValue('E5', $totAnn);
            $s->getStyle('E5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);


            /*--------------------------------------------------------------
        | 11) ALLINEAMENTI FINALI (RESISTENTI AL PRINTCONFIGURATOR)
        *--------------------------------------------------------------*/

            // colonne AUTO + TARGA → SX
            for ($rr = $hdrRow + 1; $rr <= $last; $rr++) {
                $s->getStyleByColumnAndRow($cTarga - 1, $rr)->getAlignment()->setHorizontal('left');
                $s->getStyleByColumnAndRow($cTarga, $rr)->getAlignment()->setHorizontal('left');
            }

            // colonne numeriche → DX (tutto)
            for ($rr = $hdrRow + 1; $rr <= $rTot; $rr++) {
                $s->getStyleByColumnAndRow($cN,   $rr)->getAlignment()->setHorizontal('right');
                $s->getStyleByColumnAndRow($cPerc, $rr)->getAlignment()->setHorizontal('right');
                $s->getStyleByColumnAndRow($cImp, $rr)->getAlignment()->setHorizontal('right');
            }

            // riga totale intera → destra per numeri, sinistra per “TOTALE”
            $s->getStyleByColumnAndRow($cTarga - 1, $rTot)->getAlignment()->setHorizontal('left');


            /*--------------------------------------------------------------
        | 12) FORMATTAZIONE (% + €)
        *--------------------------------------------------------------*/
            $this->formatAsPercent($s, $hdrRow + 1, $last, $cPerc);
            $this->formatAsCurrency($s, $hdrRow + 1, $rTot, $cImp);


            /*--------------------------------------------------------------
        | 13) STILE RIGA TOTALE
        *--------------------------------------------------------------*/
            $lastColL = Coordinate::stringFromColumnIndex($cImp);
            $s->getStyle("A{$rTot}:{$lastColL}{$rTot}")
                ->getFont()->setBold(true);

            $s->getStyle("A{$rTot}:{$lastColL}{$rTot}")->applyFromArray([
                'borders' => [
                    'top'   => ['borderStyle' => 'medium'],
                    'bottom' => ['borderStyle' => 'medium'],
                ]
            ]);
        } catch (\Throwable $e) {
            Log::warning('Errore foglio sanitario', ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
        }
    }

    /** Cerca l’header e restituisce: [row, colTarga, colN, colPerc, colImp] */
    /**
     * Cerca con precisione la riga dell’header e restituisce:
     * [rowHeader, colTarga, colN, colPerc, colImp]
     *
     * Riconosce la struttura:
     *   TARGA  |  N.SERVIZI ... |  PERCENTUALE ... | IMPORTO
     */
    private function locateImputazioneSanitarioHeader(Worksheet $s): array {
        for ($r = 1; $r <= 120; $r++) {
            for ($c = 1; $c <= 20; $c++) {

                $val = strtoupper(trim((string)$s->getCellByColumnAndRow($c, $r)->getValue()));

                if ($val === 'TARGA') {

                    // controlla che esista la colonna successiva: N.SERVIZI
                    $vServ = strtoupper(trim((string)$s->getCellByColumnAndRow($c + 1, $r)->getValue()));
                    if (!str_contains($vServ, 'SERVIZ')) {
                        continue; // non è un header valido → ignora
                    }

                    // colonna dopo: percentuale
                    $vPerc = strtoupper(trim((string)$s->getCellByColumnAndRow($c + 2, $r)->getValue()));
                    if (
                        !str_contains($vPerc, '%') &&
                        !str_contains($vPerc, 'PERC') &&
                        !str_contains($vPerc, 'RIPART')
                    ) {
                        continue;
                    }

                    // ultima colonna: importo
                    $vImp = strtoupper(trim((string)$s->getCellByColumnAndRow($c + 3, $r)->getValue()));
                    if (!str_contains($vImp, 'IMPORT')) {
                        continue;
                    }

                    // Ok! Header trovato e verificato
                    return [$r, $c, $c + 1, $c + 2, $c + 3];
                }
            }
        }

        // fallback se il template fosse cambiato
        return [12, 3, 4, 5, 6];
    }


    /** Scrive i due “riquadri” in alto: totale bilancio (giallo) e totale servizi (grigio). */
    private function writeTopBoxesImputazioneSanitario(Worksheet $s, float $totBilancio, float $totServizi): void {
        // 1) “TOTALE A BILANCIO” -> cella a destra dell’etichetta
        $cellBil = $this->findCellByText($s, 'TOTALE A BILANCIO');
        $destCol = null;
        if ($cellBil) {
            [$c, $r] = $cellBil;
            $destCol = $c + 1;
            $s->setCellValueByColumnAndRow($destCol, $r, round($totBilancio, 2));
        }

        // 2) “NUMERO TOTALE SERVIZI EFFETTUATI NELL'ESERCIZIO”
        $cellServ = $this->findCellByText($s, 'NUMERO TOTALE SERVIZI EFFETTUATI');
        if ($cellServ) {
            [$c, $r] = $cellServ;

            // se ho già la colonna (quella del bilancio), uso quella; altrimenti uso c+6 come fallback (template classico)
            $targetCol = $destCol ?: ($c + 6);

            // cerca una cella “vuota” nelle 6 righe sotto nella stessa colonna (gestisce le celle unite del template)
            $targetRow = null;
            for ($rr = $r; $rr <= $r + 6; $rr++) {
                $v = (string)$s->getCellByColumnAndRow($targetCol, $rr)->getValue();
                if ($v === '' || is_numeric($v)) {
                    $targetRow = $rr;
                    break;
                }
            }
            if (!$targetRow) $targetRow = $r + 4; // fallback sul layout standard

            $s->setCellValueByColumnAndRow($targetCol, $targetRow, round($totServizi, 0));
        }
    }


    /**
     * Crea il foglio "IMPUTAZIONE COSTI OSSIGENO"
     * (stessa pipeline del foglio "Materiale sanitario")
     */
    private function creaFoglioImputazioneOssigeno(Spreadsheet $wb, int $idAssociazione, int $anno): void {
        try {
            // --- Dati base
            $totBilancio = (float) CostoOssigeno::getTotale($idAssociazione, $anno);
            $automezzi   = Automezzo::getByAssociazione($idAssociazione, $anno);
            $rip         = RipartizioneOssigeno::getRipartizione($idAssociazione, $anno);
            $totServizi  = RipartizioneOssigeno::getTotaleServizi($automezzi, $anno);

            // --- Template
            $tplPath = public_path('storage/documenti/template_excel/ImputazioneCosti_Ossigeno.xlsx');

            // 1) Crea un foglio VERO del workbook e incolla il template
            $sheet = $wb->createSheet();
            $sheet->setTitle('OSSIGENO');                 // titola SUBITO
            $sheet->getDefaultRowDimension()->setRowHeight(14);
            $this->appendTemplate($sheet, $tplPath, 1);   // copia celle/stili/merge

            // 2) Sposta il foglio subito dopo "MATERIALE SANITARIO DI CONSUMO" (se esiste)
            $after = $wb->getSheetByName('MATERIALE SANITARIO DI CONSUMO');
            if ($after) {
                $posAfter = $wb->getIndex($after) + 1;
                // rimuovi il foglio appena creato dalla posizione corrente e reinseriscilo
                $currentIndex = $wb->getIndex($sheet);
                $wb->removeSheetByIndex($currentIndex);
                $wb->addSheet($sheet, min($posAfter, $wb->getSheetCount()));
                $wb->setActiveSheetIndex($wb->getIndex($sheet));
            }

            // 3) Placeholder testata
            $nomeAssoc = (string) DB::table('associazioni')
                ->where('idAssociazione', $idAssociazione)
                ->value('Associazione');

            $this->replacePlaceholdersEverywhere($sheet, [
                'ASSOCIAZIONE'      => $nomeAssoc,
                'nome_associazione' => $nomeAssoc,
                'ANNO'              => (string) $anno,
                'anno_riferimento'  => (string) $anno,
            ]);

            // 4) Colonne (layout come “sanitario”: TARGA, N, %, IMPORTO)
            [$hdrRow, $colTarga, $colN, $colPerc, $colImp] = $this->locateImputazioneSanitarioHeader($sheet);

            // 5) Righe
            $r        = $hdrRow + 1;
            $sommaImp = 0.0;

            foreach ($rip['righe'] as $row) {
                if (!empty($row['is_totale'])) continue;

                $incluso = !empty($row['incluso_riparto']);
                $n       = $incluso ? (float) ($row['totale'] ?? 0) : 0.0;
                $pct     = ($incluso && $totServizi > 0) ? ($n / $totServizi) : 0.0;
                $imp     = $incluso ? round($totBilancio * $pct, 2) : 0.0;

                // "AUTO n" è la colonna a sinistra della TARGA
                $sheet->setCellValueByColumnAndRow($colTarga - 1, $r, 'AUTO ' . ($r - $hdrRow));
                $sheet->setCellValueByColumnAndRow($colTarga,     $r, (string)($row['Targa'] ?? ''));
                $sheet->setCellValueByColumnAndRow($colN,         $r, $n);
                $sheet->setCellValueByColumnAndRow($colPerc,      $r, $pct);
                $sheet->setCellValueByColumnAndRow($colImp,       $r, $imp);

                $sommaImp += $imp;

                // stile uguale alla riga campione
                $this->copyRowStyle($sheet, $hdrRow + 1, $r);
                $r++;
            }

            // 6) Riallineamento centesimi
            $delta = round($totBilancio - $sommaImp, 2);
            if (abs($delta) >= 0.01) {
                for ($rr = $r - 1; $rr >= ($hdrRow + 1); $rr--) {
                    $serv = (float)$sheet->getCellByColumnAndRow($colN, $rr)->getCalculatedValue();
                    if ($serv > 0) {
                        $val = (float)$sheet->getCellByColumnAndRow($colImp, $rr)->getCalculatedValue();
                        $sheet->setCellValueByColumnAndRow($colImp, $rr, round($val + $delta, 2));
                        $sommaImp = round($sommaImp + $delta, 2);
                        break;
                    }
                }
            }

            // 7) Riga totale
            $rowTot = $r;
            $sheet->setCellValueByColumnAndRow($colTarga - 1, $rowTot, 'TOTALE');
            $sheet->setCellValueByColumnAndRow($colN,        $rowTot, $totServizi);
            $sheet->setCellValueByColumnAndRow($colPerc,     $rowTot, 1);
            $sheet->setCellValueByColumnAndRow($colImp,      $rowTot, $sommaImp);

            // 8) Box in alto + formati
            $this->writeTopBoxesImputazioneSanitario($sheet, $totBilancio, $totServizi);
            // Scrivi NUMERO TOTALE SERVIZI (E5)
            $sheet->setCellValue('E5', $totServizi);
            $sheet->getStyle('E5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('E5')->getNumberFormat()->setFormatCode('#,##0');

            $this->formatAsPercent($sheet, $hdrRow + 1, $rowTot, $colPerc);
            $this->formatAsCurrency($sheet, $hdrRow + 1, $rowTot, $colImp);
            // === FINITURE DI STILE (come gli altri fogli), usando il template incollato ===
            $firstCol     = $colTarga - 1; 
            $lastCol      = $colImp;
            $firstDataRow = $hdrRow + 1;
            $lastDataRow  = $rowTot - 1;

            $this->applyTablePolish(
                $sheet,
                $hdrRow,
                $firstCol,
                $lastCol,
                $firstDataRow,
                $lastDataRow,
                $rowTot,
                $colPerc // centra la colonna %
            );
        } catch (Throwable $e) {
            Log::warning('Foglio Imputazione OSSIGENO: errore non bloccante', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }


    /**
     * Crea il foglio "RIEPILOGO COSTI AUTO-RADIO-SAN."
     * Usa il template e i calcoli consolidati della RipartizioneCostiService::calcolaTabellaTotale(...)
     */
    private function creaFoglioRiepilogoAutoRadioSan(
        Spreadsheet $wb,
        int $idAssociazione,
        int $anno,
        string $templatePath
    ): void {
        // 0) intestazione
        $nomeAssoc = (string) DB::table('associazioni')
            ->where('idAssociazione', $idAssociazione)
            ->value('Associazione');

        // 1) foglio + template
        $sheet = $wb->createSheet();
        $sheet->setTitle('RIEPILOGO COSTI AUTO-RADIO-SAN.');
        $sheet->getDefaultRowDimension()->setRowHeight(14);
        $this->appendTemplate($sheet, $templatePath, 1);

        // 2) placeholder
        $this->replacePlaceholdersEverywhere($sheet, [
            'ASSOCIAZIONE'      => $nomeAssoc,
            'nome_associazione' => $nomeAssoc,
            'ANNO'              => (string)$anno,
            'anno_riferimento'  => (string)$anno,
        ]);

        // 3) dati
        $convMap  = Cache::remember(
            "rip:convMap:ass:{$idAssociazione}:anno:{$anno}",
            now()->addMinutes(30),
            fn() => RipartizioneCostiService::convenzioni($idAssociazione, $anno)
        );
        $convNomi = array_values($convMap);

        $tabella  = Cache::remember(
            "rip:tabTot:ass:{$idAssociazione}:anno:{$anno}",
            now()->addMinutes(30),
            fn() => RipartizioneCostiService::calcolaTabellaTotale($idAssociazione, $anno)
        );

        // 4) header tabella
        [$headerRow, $firstCol] = $this->locateRiepilogoHeader($sheet);

        // 5) intestazioni colonne (conv) + TOTALE
        $colStart = $firstCol + 1;
        $col = $colStart;
        foreach ($convNomi as $nome) {
            $sheet->setCellValueByColumnAndRow($col, $headerRow, $nome);
            $col++;
        }
        $colTot = $col;
        $sheet->setCellValueByColumnAndRow($colTot, $headerRow, 'TOTALE');

        // 6) righe dati
        $r = $headerRow + 1;
        $firstDataRow = $r;

        foreach ($tabella as $riga) {
            $voce = (string)($riga['voce'] ?? '');
            if ($voce === '') continue;

            // descrizione
            $sheet->setCellValueByColumnAndRow($firstCol, $r, $voce);

            // valori per convenzione (NUMERIC!)
            $col = $colStart;
            $somma = 0.0;
            foreach ($convNomi as $nomeC) {
                $val = (float)($riga[$nomeC] ?? 0.0);
                $sheet->setCellValueExplicitByColumnAndRow($col, $r, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $somma += $val;
                $col++;
            }

            // totale orizzontale con riallineamento centesimi sull'ultima conv
            $totDich = round((float)($riga['totale'] ?? $somma), 2);
            $delta   = round($totDich - round($somma, 2), 2);
            if (abs($delta) >= 0.01 && !empty($convNomi)) {
                $lastCol = $colStart + count($convNomi) - 1;
                $lastVal = (float)$sheet->getCellByColumnAndRow($lastCol, $r)->getValue();
                $sheet->setCellValueExplicitByColumnAndRow(
                    $lastCol,
                    $r,
                    round($lastVal + $delta, 2),
                    DataType::TYPE_NUMERIC
                );
                $somma = round($somma + $delta, 2);
            }
            $sheet->setCellValueExplicitByColumnAndRow(
                $colTot,
                $r,
                $somma,
                DataType::TYPE_NUMERIC
            );

            // stile riga
            $this->copyRowStyle($sheet, $headerRow + 1, $r);
            $r++;
        }

        $lastDataRow = $r - 1;

        // 7) formato numerico corpo
        $this->formatAsCurrency($sheet, $firstDataRow, $lastDataRow, $colStart, $colTot);

        // 8) riga TOTALI (verticale) + angolo
        $totalRow = $lastDataRow + 1;
        $sheet->setCellValueByColumnAndRow($firstCol, $totalRow, 'TOTALI');

        for ($c = $colStart; $c <= $colTot; $c++) {
            $L = Coordinate::stringFromColumnIndex($c);
            $sheet->setCellValue(
                "{$L}{$totalRow}",
                sprintf('=SUM(%s%d:%s%d)', $L, $firstDataRow, $L, $lastDataRow)
            );
        }

        // stile totali
        $this->copyRowStyle($sheet, $headerRow + 1, $totalRow);
        $sheet->getStyle('A' . $totalRow . ':' . Coordinate::stringFromColumnIndex($colTot) . $totalRow)
            ->getFont()->setBold(true);
        $this->formatAsCurrency($sheet, $totalRow, $totalRow, $colStart, $colTot);

        // bordo spesso sopra i TOTALI
        $sheet->getStyle('A' . $totalRow . ':' . Coordinate::stringFromColumnIndex($colTot) . $totalRow)
            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        // 9) adatta larghezze (header compreso) per evitare testi tagliati
        PrintConfigurator::fitBodyColumns($sheet, $headerRow, $firstCol, $colTot, 2.0, 9.0, 48.0, []);

        // 10) area di stampa
        $lastColLetter = Coordinate::stringFromColumnIndex($colTot);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColLetter}{$totalRow}");
    }


    /**
     * Crea un foglio di dettaglio per *un* automezzo usando il template Costi_AUTO1.xlsx
     * Nome foglio: "TARGA - CODICE"
     * I dati sono quelli di RipartizioneCostiService::calcolaRipartizioneTabellaFinale()
     * filtrati per idAutomezzo.
     */
    private function creaFoglioCostiPerAutomezzo(
        Spreadsheet $wb,
        string $templatePath,
        string $nomeAssociazione,
        int $idAssociazione,
        int $anno,
        object $auto
    ): void {
        // 1) Crea foglio e incolla template
        $s = $wb->createSheet();
        $titolo = trim(sprintf('%s - %s', (string)$auto->Targa, (string)$auto->CodiceIdentificativo));
        $titolo = mb_substr($titolo, 0, 31, 'UTF-8');
        if ($wb->sheetNameExists($titolo)) {
            $i = 2;
            while ($wb->sheetNameExists($titolo . '(' . $i . ')')) $i++;
            $titolo .= '(' . $i . ')';
        }
        $s->setTitle($titolo);

        $this->appendTemplate($s, $templatePath, 1);

        // 2) Placeholder header (aggiunti i nuovi segnaposto lowercase)
        $this->replacePlaceholdersEverywhere($s, [
            'nome_associazione'      => $nomeAssociazione,
            'ASSOCIAZIONE'           => $nomeAssociazione,
            'anno_riferimento'       => (string)$anno,
            'ANNO'                   => (string)$anno,
            'TARGA'                  => (string)$auto->Targa,
            'targa'                  => (string)$auto->Targa,
            'CODICE'                 => (string)$auto->CodiceIdentificativo,
            'codiceIdentificativo'   => (string)$auto->CodiceIdentificativo,
            'codiceidentificativo'   => (string)$auto->CodiceIdentificativo,
        ]);

        // 3) Convenzioni e dati del singolo mezzo
        $convMap   = RipartizioneCostiService::convenzioni($idAssociazione, $anno);
        $convIds   = array_keys($convMap);
        $convNames = array_values($convMap);

        $rows = RipartizioneCostiService::calcolaRipartizioneTabellaFinale(
            $idAssociazione,
            $anno,
            (int)$auto->idAutomezzo
        );
        // rows: ['voce' => string, 'totale' => float, <nome conv> => float, ...]

        // 4) Individua header e colonne del template
        //    (intestazione contiene "TOTALE COSTI DA RIPARTIRE", ultima colonna = "TOTALE")
        [$hdrRow, $colVoce, $colTotRip, $colFirstConv, $colTotCol] = $this->locateHeaderAutoDetail($s);

        // === Etichetta in testa: "TARGA - CODICE" in colonna Voce dell’header
        $labelAuto = trim(sprintf('%s - %s', (string)$auto->Targa, (string)$auto->CodiceIdentificativo));
        $s->setCellValueByColumnAndRow($colVoce, $hdrRow, $labelAuto);

        // 5) Header convenzioni
        $c = $colFirstConv;
        foreach ($convNames as $name) {
            $s->setCellValueByColumnAndRow($c, $hdrRow, $name);
            $c++;
        }
        $s->setCellValueByColumnAndRow($colTotCol, $hdrRow, 'TOTALE');

        // 6) Righe dati
        $startDataRow = $hdrRow + 1;
        $r = $startDataRow;
        $lastDataRow = $r;

        foreach ($rows as $row) {
            // salta l'eventuale riga "TOTALI" della sorgente
            $isTot = isset($row['voce']) && mb_strtoupper($row['voce'], 'UTF-8') === 'TOTALI';
            if ($isTot) continue;

            // voce
            $s->setCellValueByColumnAndRow($colVoce,   $r, (string)$row['voce']);
            // totale costi da ripartire
            $s->setCellValueByColumnAndRow($colTotRip, $r, (float)$row['totale']);

            // importi per convenzione
            $c = $colFirstConv;
            foreach ($convNames as $name) {
                $val = (float)($row[$name] ?? 0.0);
                $s->setCellValueByColumnAndRow($c, $r, $val);
                $c++;
            }

            // totale riga a destra
            $s->setCellValueByColumnAndRow($colTotCol, $r, (float)$row['totale']);

            // stile riga
            $this->copyRowStyle($s, $startDataRow, $r);

            $lastDataRow = $r;
            $r++;
        }

        // 7) Riga "TOTALI" (somme per colonna)
        $rowTot = $lastDataRow + 1;
        $s->setCellValueByColumnAndRow($colVoce,   $rowTot, 'TOTALI');

        // totale colonna "TOTALE COSTI DA RIPARTIRE"
        $s->setCellValueByColumnAndRow(
            $colTotRip,
            $rowTot,
            "=SUM(" . $this->col($colTotRip) . $startDataRow . ":" . $this->col($colTotRip) . $lastDataRow . ")"
        );

        // somme per ciascuna convenzione
        $c = $colFirstConv;
        while ($c < $colTotCol) {
            $s->setCellValueByColumnAndRow(
                $c,
                $rowTot,
                "=SUM(" . $this->col($c) . $startDataRow . ":" . $this->col($c) . $lastDataRow . ")"
            );
            $c++;
        }

        // totale finale a destra
        $s->setCellValueByColumnAndRow(
            $colTotCol,
            $rowTot,
            "=SUM(" . $this->col($colTotCol) . $startDataRow . ":" . $this->col($colTotCol) . $lastDataRow . ")"
        );

        // 8) Formati valuta per tutta la tabella (da "tot ripartire" fino a "TOTALE")
        $this->formatAsCurrency($s, $startDataRow, $rowTot, $colTotRip);
        for ($cc = $colFirstConv; $cc <= $colTotCol; $cc++) {
            $this->formatAsCurrency($s, $startDataRow, $rowTot, $cc);
        }

        // 9) **Bordo spesso + grassetto** su TUTTA la riga "TOTALI"
        $fromColLetter = $this->col($colVoce);
        $toColLetter   = $this->col($colTotCol);
        $rowRange      = "{$fromColLetter}{$rowTot}:{$toColLetter}{$rowTot}";

        $s->getStyle($rowRange)->getFont()->setBold(true);
        $s->getStyle($rowRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THICK],
            ],
        ]);
    }

    protected function addDistintaImputazioneCostiSheet(Spreadsheet $spreadsheet, int $idAssociazione, int $anno): void {
        // 1) Dati dal service
        $payload  = RipartizioneCostiService::distintaImputazioneData($idAssociazione, $anno);
        $righe    = $payload['data'] ?? [];
        $convNomi = $payload['convenzioni'] ?? [];
        if (empty($righe) || empty($convNomi)) return;

        // 2) Sezioni (tipologia 2→11)
        $sezioneName = [
            2  => 'Automezzi ed attrezzature sanitarie',
            3  => 'Attrezzatura sanitaria',
            4  => 'Telecomunicazioni',
            5  => 'Costi di gestione della struttura',
            6  => 'Costo del personale',
            7  => 'Materiale sanitario di consumo',
            8  => 'Costi amministrativi',
            9  => 'Quote di ammortamento',
            10 => 'Beni strumentali < 516 €',
            11 => 'Altri Costi'
        ];

        // 3) Crea foglio in coda
        $sheet = new Worksheet($spreadsheet, 'DISTINTA IMPUTAZIONE COSTI');
        $spreadsheet->addSheet($sheet, $spreadsheet->getSheetCount());

        // Stili rapidi
        $headFill    = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']];
        $subHeadFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']];
        $secFill     = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']];
        $thinBorder  = ['borders'  => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]]];

        // 4) Header (r1: titoli; r2: sottotitoli convenzioni)
        $col = 1; // A
        $sheet->setCellValueByColumnAndRow($col++, 1, 'VOCE');                                            // A
        $sheet->setCellValueByColumnAndRow($col++, 1, 'IMPORTO TOTALE COME DA BILANCIO CONSUNTIVO');           // B
        $sheet->setCellValueByColumnAndRow($col++, 1, 'COSTI DI DIRETTA IMPUTAZIONE');            // C
        $sheet->setCellValueByColumnAndRow($col++, 1, 'TOTALE COSTI RIPARTITI');              // D  <<< NUOVA COLONNA FISSA

        // Convenzioni a 3 sotto-colonne: Diretti / Sconto (amm.) / Indiretti
        foreach ($convNomi as $convName) {
            $c1 = $col;            // Diretti
            $c2 = $col + 1;        // Sconto
            $c3 = $col + 2;        // Indiretti
            $sheet->mergeCellsByColumnAndRow($c1, 1, $c3, 1);
            $sheet->setCellValueByColumnAndRow($c1, 1, $convName);
            $sheet->setCellValueByColumnAndRow($c1, 2, 'DIRETTI');
            $sheet->setCellValueByColumnAndRow($c2, 2, 'SCONTO');
            $sheet->setCellValueByColumnAndRow($c3, 2, 'INDIRETTI');
            $col += 3;
        }
        $lastCol = $col - 1;

        // Stile header
        $sheet->getStyleByColumnAndRow(1, 1, $lastCol, 1)->getFill()->applyFromArray($headFill);
        $sheet->getStyleByColumnAndRow(1, 2, $lastCol, 2)->getFill()->applyFromArray($subHeadFill);
        $sheet->getStyleByColumnAndRow(1, 1, $lastCol, 2)->getFont()->setBold(true);
        $sheet->getStyleByColumnAndRow(1, 1, $lastCol, 2)->applyFromArray($thinBorder);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getStyleByColumnAndRow(1, 1, $lastCol, 2)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // 5) Righe dati — rispetta ordine da service
        $row = 3;
        $currentSection = null;

        foreach ($righe as $riga) {
            $sezId = (int)($riga['sezione_id'] ?? 0);
            if ($sezId < 2 || $sezId > 11) continue;

            // Riga titolo sezione quando cambia
            if ($currentSection !== $sezId) {
                $currentSection = $sezId;
                $sheet->mergeCellsByColumnAndRow(1, $row, $lastCol, $row);
                $sheet->setCellValueByColumnAndRow(1, $row, mb_strtoupper($sezioneName[$sezId] ?? "Sezione $sezId", 'UTF-8'));
                $sheet->getStyleByColumnAndRow(1, $row, $lastCol, $row)->getFill()->applyFromArray($secFill);
                $sheet->getStyleByColumnAndRow(1, $row, $lastCol, $row)->getFont()->setBold(true);
                $sheet->getStyleByColumnAndRow(1, $row, $lastCol, $row)->applyFromArray($thinBorder);
                $row++;
            }

            // RIGA VOCE
            $c = 1;

            // 1) VOCE MAIUSCOLA + CENTRATA
            $sheet->setCellValueByColumnAndRow($c, $row, mb_strtoupper((string)$riga['voce'], 'UTF-8'));

            $sheet->getStyleByColumnAndRow($c, $row)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $c++;
            $sheet->setCellValueByColumnAndRow($c++, $row, (float)($riga['bilancio'] ?? 0));
            $sheet->setCellValueByColumnAndRow($c++, $row, (float)($riga['diretta']  ?? 0));
            $sheet->setCellValueByColumnAndRow($c++, $row, (float)($riga['totale']   ?? 0));


            // Convenzioni
            foreach ($convNomi as $convName) {
                $cell = $riga[$convName] ?? ['diretti' => 0, 'ammortamento' => 0, 'indiretti' => 0];
                $dir = (float)($cell['diretti']      ?? 0);
                $amm = (float)($cell['ammortamento'] ?? 0);
                $ind = (float)($cell['indiretti']    ?? 0);

                $sheet->setCellValueByColumnAndRow($c++, $row, $dir);
                $sheet->setCellValueByColumnAndRow($c++, $row, $amm);
                $sheet->setCellValueByColumnAndRow($c++, $row, $ind);
            }

            $row++;
        }

        // 6) Bordi e formati
        $lastRow = $row - 1;
        $sheet->getStyleByColumnAndRow(1, 1, $lastCol, $lastRow)->applyFromArray($thinBorder);

        // Formato numerico su tutte le colonne da B in poi
        for ($cc = 2; $cc <= $lastCol; $cc++) {
            $sheet->getStyleByColumnAndRow($cc, 3, $cc, $lastRow)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyleByColumnAndRow($cc, 3, $cc, $lastRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        /*
        |--------------------------------------------------------------------------
        | 6.1) RIGA TOTALE FINALE (CORRETTA)
        |--------------------------------------------------------------------------
        */
        $totRow = $lastRow + 1;

        // Etichetta (NON mergiare, rimane solo in A)
        $sheet->setCellValueByColumnAndRow(1, $totRow, 'TOTALE');
        $sheet->getStyle("A{$totRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Somme solo sulle colonne numeriche
        for ($cc = 2; $cc <= $lastCol; $cc++) {

            // Skippa colonne testo (se ci sono)
            $colL = Coordinate::stringFromColumnIndex($cc);

            $sheet->setCellValue(
                "{$colL}{$totRow}",
                "=SUM({$colL}3:{$colL}{$lastRow})"
            );

            $sheet->getStyle("{$colL}{$totRow}")
                ->getNumberFormat()->setFormatCode('#,##0.00');

            $sheet->getStyle("{$colL}{$totRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Stile riga totale
        $sheet->getStyle("A{$totRow}:" . Coordinate::stringFromColumnIndex($lastCol) . $totRow)
            ->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF2CC']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_MEDIUM
                    ]
                ],
            ]);

        $lastRow = $totRow;

        /*
        |--------------------------------------------------------------------------
        | 7) Freeze pane
        |--------------------------------------------------------------------------
        */
        $sheet->freezePaneByColumnAndRow(5, 3);

        /*
        |--------------------------------------------------------------------------
        | 8) AutoSize colonne
        |--------------------------------------------------------------------------
        */
        $sheet->getColumnDimension('A')->setWidth(55);
        for ($cc = 2; $cc <= $lastCol; $cc++) {
            $sheet->getColumnDimensionByColumn($cc)->setAutoSize(true);
        }

        /*
        |--------------------------------------------------------------------------
        | 9) Impostazioni di stampa
        |--------------------------------------------------------------------------
        */
        $ps = $sheet->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $ps->setFitToWidth(1);
        $ps->setFitToHeight(0);
        $ps->setHorizontalCentered(true);

        $margins = $sheet->getPageMargins();
        $margins->setTop(0.4);
        $margins->setBottom(0.6);
        $margins->setLeft(0.4);
        $margins->setRight(0.4);
        $margins->setHeader(0.2);
        $margins->setFooter(0.2);

        // Ripeti header (r1-2) in stampa
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 2);

        /*
        |--------------------------------------------------------------------------
        | Area di stampa AGGIORNATA
        |--------------------------------------------------------------------------
        */
        $lastColL = Coordinate::stringFromColumnIndex($lastCol);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColL}{$lastRow}");
    }

    private function writeTabellaTipologia1(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione,
        int $rowStartHint = 1
    ): int {
        $map = $this->detectPrevConsHeader($ws);
        $r   = max($rowStartHint, $map['firstDataRow']);

        // Dati filtrati per la SINGOLA convenzione
        $rows = Riepilogo::getForDataTable($anno, $idAssociazione, $idConvenzione);

        foreach ($rows as $row) {
            $ws->setCellValueByColumnAndRow($map['colVoce'], $r, (string)$row['descrizione']);

            // PREVENTIVO
            if (is_numeric($row['preventivo'])) {
                $ws->setCellValueExplicitByColumnAndRow(
                    $map['colPrev'],
                    $r,
                    (float)$row['preventivo'],
                    DataType::TYPE_NUMERIC
                );
                $ws->getStyleByColumnAndRow($map['colPrev'], $r)->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $ws->setCellValueByColumnAndRow($map['colPrev'], $r, (string)$row['preventivo']);
            }

            // CONSUNTIVO
            if (is_numeric($row['consuntivo'])) {
                $ws->setCellValueExplicitByColumnAndRow(
                    $map['colCons'],
                    $r,
                    (float)$row['consuntivo'],
                    DataType::TYPE_NUMERIC
                );
                $ws->getStyleByColumnAndRow($map['colCons'], $r)->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $ws->setCellValueByColumnAndRow($map['colCons'], $r, (string)$row['consuntivo']);
            }

            // % SCOSTAMENTO se nel template esiste la colonna
            if ($map['colScost']) {
                $prev = is_numeric($row['preventivo']) ? (float)$row['preventivo'] : 0.0;
                $cons = is_numeric($row['consuntivo']) ? (float)$row['consuntivo'] : 0.0;
                $pct  = $prev > 0 ? (($cons - $prev) / $prev) : null;
                if ($pct !== null) {
                    $ws->setCellValueByColumnAndRow($map['colScost'], $r, $pct);
                    $ws->getStyleByColumnAndRow($map['colScost'], $r)->getNumberFormat()->setFormatCode('0.00%');
                }
            }

            // bordino riga sull’ampiezza dell’header
            $leftL  = Coordinate::stringFromColumnIndex($map['colVoce']);
            $rightL = Coordinate::stringFromColumnIndex($map['lastHeaderCol']);
            $this->box($ws, "{$leftL}{$r}:{$rightL}{$r}");

            $r++;
        }

        return $r; // prima riga libera dopo la tabella
    }


    /** Formatta come valuta una o più colonne (col singolo o range colStart..colEnd) tra le righe r1..r2. */
    private function formatAsCurrency(Worksheet $s, int $r1, int $r2, int $colStart, ?int $colEnd = null): void {
        $colEnd ??= $colStart;
        for ($c = $colStart; $c <= $colEnd; $c++) {
            $range = Coordinate::stringFromColumnIndex($c) . $r1 . ':' .
                Coordinate::stringFromColumnIndex($c) . $r2;
            $s->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    private function addSheetAfter(
        Spreadsheet $wb,
        Worksheet $sheet,
        string $title,
        string $afterName
    ): int {
        // elimina eventuale foglio con stesso nome
        $existing = $wb->getSheetByName($title);
        if ($existing) {
            $wb->removeSheetByIndex($wb->getIndex($existing));
        }

        $sheet->setTitle($title);
        $sheet->getDefaultRowDimension()->setRowHeight(14);

        $after = $wb->getSheetByName($afterName);
        $index = $after ? ($wb->getIndex($after) + 1) : $wb->getSheetCount();

        // inserisco direttamente all'indice voluto (niente setSheetOrder)
        $wb->addSheet($sheet, $index);
        $wb->setActiveSheetIndex($index);
        return $index;
    }

    private function creaFoglioConsuntivoConvenzione(
        Spreadsheet $master,
        string $templatePath,
        string $nomeAssociazione,
        int $idAssociazione,
        int $anno,
        int $idConvenzione,
        string $nomeConvenzione,
        int $insertIndex
    ): void {
        $tplWb    = IOFactory::load($templatePath);
        $tplSheet = $tplWb->getSheet(0);

        $newSheet = clone $tplSheet;
        $master->addSheet($newSheet, $insertIndex);

        // Titolo foglio = nome convenzione (max 31 char)
        $title = mb_substr($nomeConvenzione, 0, 31);
        $newSheet->setTitle($title !== '' ? $title : 'CONVENZIONE');

        // Placeholder nella testata del template
        $this->replacePlaceholdersEverywhere($newSheet, [
            'nome_associazione' => $nomeAssociazione,
            'anno_riferimento'  => (string)$anno,
            'nome_convenzione'  => (string)$nomeConvenzione,
            'convenzione'       => (string)$nomeConvenzione,
        ]);

        // Scrivi SOLO la Tabella 1 (Tipologia 1) sotto l’header PREVENTIVO/CONSUNTIVO
        $this->writeTabellaTipologia1($newSheet, $idAssociazione, $anno, $idConvenzione);
    }

    /** Converte indice colonna numerico in lettera (A..Z..AA...) */
    private function col(int $idx): string {
        return Coordinate::stringFromColumnIndex($idx);
    }

    /** Trova la prima cella che contiene un testo (case-insensitive). */
    private function findCellByText(Worksheet $s, string $label): ?array {
        $maxR = $s->getHighestRow();
        $maxC = Coordinate::columnIndexFromString($s->getHighestColumn());
        $needle = mb_strtoupper(trim($label));
        for ($r = 1; $r <= $maxR; $r++) {
            for ($c = 1; $c <= $maxC; $c++) {
                $v = $s->getCellByColumnAndRow($c, $r)->getValue();
                if ($v instanceof RichText) $v = $v->getPlainText();
                if (!is_string($v) || $v === '') continue;
                if (mb_strtoupper(trim($v)) === $needle) return [$c, $r];
            }
        }
        return null;
    }

    private function formatAsPercent(Worksheet $s, int $r1, int $r2, int $col): void {
        $range = Coordinate::stringFromColumnIndex($col) . $r1 . ':' .
            Coordinate::stringFromColumnIndex($col) . $r2;
        $s->getStyle($range)->getNumberFormat()->setFormatCode('0.00%');
    }

    /** Pulisce un titolo di foglio da caratteri vietati e spazi doppi */
    private function sanitizeSheetTitle(string $t): string {
        // Vietati in Excel: : \ / ? * [ ]
        $t = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]+/', ' ', $t) ?? '';
        $t = trim(preg_replace('/\\s+/', ' ', $t) ?? '');
        return $t === '' ? 'Foglio' : $t;
    }

    /** Genera un titolo univoco (<=31 char), robusto a UTF-8, usando sheetNameExists() */
    private function uniqueSheetTitle(Spreadsheet $wb, string $base): string {
        $base = $this->sanitizeSheetTitle($base);
        $base = mb_substr($base, 0, 31, 'UTF-8'); // clamp iniziale

        $exists = fn(string $name) => $wb->sheetNameExists($name);

        if (!$exists($base)) {
            return $base;
        }

        // Prova con " (2)", " (3)", ... accorciando in modo multibyte-safe
        for ($i = 2; $i < 1000; $i++) {
            $suffix   = ' (' . $i . ')';
            $maxBase  = 31 - mb_strlen($suffix, 'UTF-8');
            $candidate = mb_substr($base, 0, max(1, $maxBase), 'UTF-8') . $suffix;
            if (!$exists($candidate)) {
                return $candidate;
            }
        }

        // Fallback estremo (non dovrebbe mai servire)
        return mb_substr($base, 0, 25, 'UTF-8') . ' ' . substr(uniqid('', false), 0, 5);
    }


    /** Tabella 2: Tipologie 2..11 (per sezione: titolo + voci sotto, ordine DB, nessun totale finale) */
    private function writeTabellaCostiSezioni(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione,
        int $rowStart
    ): void {
        $r = $rowStart;

        // titolo macro
        $ws->mergeCells("A{$r}:C{$r}");
        $ws->setCellValue("A{$r}", 'RIEPILOGO COSTI PER SEZIONE (Tipologie 2..11)');
        $this->styleSezione($ws, "A{$r}:C{$r}");
        $r++;

        // intestazione colonne (aderente al template)
        $ws->setCellValue("A{$r}", 'Voce');
        $ws->setCellValue("B{$r}", 'Preventivo');
        $ws->setCellValue("C{$r}", 'Consuntivo');
        $this->styleHeader($ws, "A{$r}:C{$r}");
        $r++;

        $labels = [
            2 => 'Automezzi',
            3 => 'Attrezzatura sanitaria',
            4 => 'Telecomunicazioni',
            5 => 'Costi gestione struttura',
            6 => 'Costo del personale',
            7 => 'Materiale sanitario di consumo',
            8 => 'Costi amministrativi',
            9 => 'Quote di ammortamento',
            10 => 'Beni strumentali < 516,00 €',
        ];

        for ($tip = 2; $tip <= 11; $tip++) {
            // titolo sezione (riga piena, poi righe voci)
            $ws->mergeCells("A{$r}:C{$r}");
            $ws->setCellValue("A{$r}", strtoupper($labels[$tip] ?? "Sezione {$tip}"));
            $this->styleSezione($ws, "A{$r}:C{$r}");
            $r++;

            $righe = RiepilogoCosti::getByTipologia($tip, $anno, $idAssociazione, $idConvenzione); // ordine DB già rispettato

            foreach ($righe as $rr) {
                // NB: getByTipologia fonde “telefonia fissa/mobile” in “utenze telefoniche”
                $ws->setCellValue("A{$r}", (string)$rr->descrizione);
                $ws->setCellValueExplicit("B{$r}", (float)$rr->preventivo,  DataType::TYPE_NUMERIC);
                $ws->setCellValueExplicit("C{$r}", (float)$rr->consuntivo,  DataType::TYPE_NUMERIC);
                $this->box($ws, "A{$r}:C{$r}");
                $this->formatNum($ws, "B{$r}:C{$r}");
                $r++;
            }

            // riga vuota tra sezioni
            $r++;
        }
    }

    /* ========================= STILI & UTILITY ========================= */



    /** ======================== TABELLONE VOCE/PREV/CONS ================= */
    private function fillTabellaVocePrevCons(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione
    ): void {
        // 1) Individua header e colonne (C/E/F)
        $hdr = $this->detectPrevConsHeader($ws);
        $colVoce = $hdr['colVoce']; // C
        $colPrev = $hdr['colPrev']; // E
        $colCons = $hdr['colCons']; // F
        $first   = $hdr['firstDataRow'];

        // 2) Indicizza le righe del template per etichetta (colonna C)
        $labelToRow = $this->buildLabelIndex($ws, $colVoce, $first);

        // 3) Recupera le voci dal DB (solo per la convenzione)
        $rowsAll = Riepilogo::getForDataTable($anno, $idAssociazione, $idConvenzione);

        // 4) Filtra SOLO le voci 1001–1031
        $rows = array_values(array_filter($rowsAll, fn($r) => (int)$r['voce_id'] >= 1001 && (int)$r['voce_id'] <= 1031));

        // 5) Mappa e scrivi nei punti corretti del template
        $writtenRows = [];
        foreach ($rows as $row) {
            $label = mb_strtoupper(trim((string)$row['descrizione']), 'UTF-8');
            $rigaTemplate = $labelToRow[$label] ?? null;

            if (!$rigaTemplate) {
                Log::debug('fillTabellaVocePrevConsMapped: voce non trovata nel template', [
                    'voce_id' => $row['voce_id'],
                    'label'   => $label,
                ]);
                continue;
            }

            // Scrivi numeri in E/F
            $prev = is_numeric($row['preventivo']) ? (float)$row['preventivo'] : null;
            $cons = is_numeric($row['consuntivo']) ? (float)$row['consuntivo'] : null;

            if ($prev !== null) {
                $ws->setCellValueExplicitByColumnAndRow($colPrev, $rigaTemplate, $prev, DataType::TYPE_NUMERIC);
            } else {
                $ws->setCellValueByColumnAndRow($colPrev, $rigaTemplate, null);
            }

            if ($cons !== null) {
                $ws->setCellValueExplicitByColumnAndRow($colCons, $rigaTemplate, $cons, DataType::TYPE_NUMERIC);
            } else {
                $ws->setCellValueByColumnAndRow($colCons, $rigaTemplate, null);
            }

            $writtenRows[] = $rigaTemplate;
        }

        // 6) Formatta numeri
        if ($writtenRows) {
            $minR = min($writtenRows);
            $maxR = max($writtenRows);
            $this->formatNum($ws, $this->col($colPrev) . $minR . ':' . $this->col($colPrev) . $maxR);
            $this->formatNum($ws, $this->col($colCons) . $minR . ':' . $this->col($colCons) . $maxR);
        }

        Log::info('fillTabellaVocePrevConsMapped completata', [
            'idAssociazione' => $idAssociazione,
            'anno' => $anno,
            'idConvenzione' => $idConvenzione,
            'righe_scritti' => count($writtenRows),
        ]);
    }

    /** Header finder per il template: cerca “Voce/Preventivo/Consuntivo” proprio in A,B,C. */
    private function findHeaderVocePrevConsABC(Worksheet $ws): int {
        $maxR = min(200, $ws->getHighestRow());
        for ($r = 1; $r <= $maxR; $r++) {
            $a = mb_strtoupper(trim((string)($ws->getCell("A{$r}")->getValue() ?? '')));
            $b = mb_strtoupper(trim((string)($ws->getCell("B{$r}")->getValue() ?? '')));
            $c = mb_strtoupper(trim((string)($ws->getCell("C{$r}")->getValue() ?? '')));
            if ($a === 'VOCE' && str_starts_with($b, 'PREVENTIVO') && str_starts_with($c, 'CONSUNTIVO')) {
                return $r;
            }
        }
        // fallback prudente: in alto
        return 6;
    }

    private function creaFogliRiepilogoDatiPerConvenzione(
        Spreadsheet $wb,
        string $templatePath,     // es: storage_path('app/template_excel/RiepilogoDati.xlsx')
        int $idAssociazione,
        int $anno,
        string $anchorTitle = 'DISTINTA IMPUTAZIONE COSTI'
    ): void {
        if (!is_file($templatePath)) {
            Log::warning('RiepilogoDati: template non trovato', ['path' => $templatePath]);
            return;
        }

        // Recupero intestazioni
        $nomeAssoc = (string) DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione');

        // Convenzioni ordinate
        $convenzioni = Riepilogo::getConvenzioniForAssAnno($idAssociazione, $anno); // -> map id/text

        if ($convenzioni->isEmpty()) {
            Log::info('RiepilogoDati: nessuna convenzione per associazione/anno', compact('idAssociazione', 'anno'));
            return;
        }

        // Punto d’inserimento (subito dopo l’anchor, altrimenti in coda)
        $insertAt = $wb->getSheetByName($anchorTitle)
            ? ($wb->getIndex($wb->getSheetByName($anchorTitle)) + 1)
            : $wb->getSheetCount();

        // Carico UNA sola volta il template e ne clono il primo sheet
        $tplWb    = IOFactory::load($templatePath);
        $tplSheet = $tplWb->getSheet(0);

        foreach ($convenzioni as $conv) {
            try {
                $new = clone $tplSheet;

                // Titolo foglio = nome convenzione (max 31 e univoco)
                $titleBase = mb_substr((string)$conv->text, 0, 31, 'UTF-8');
                $title     = $this->uniqueSheetTitle($wb, $titleBase ?: 'CONVENZIONE');
                $new->setTitle($title);

                // Inserisco il foglio
                $wb->addSheet($new, $insertAt);
                $insertAt++;

                // Placeholder in testata
                $this->replacePlaceholdersEverywhere($new, [
                    'nome_associazione' => $nomeAssoc,
                    'ASSOCIAZIONE'      => $nomeAssoc,
                    'anno_riferimento'  => (string)$anno,
                    'ANNO'              => (string)$anno,
                    'nome_convenzione'  => (string)$conv->text,
                    'convenzione'       => (string)$conv->text,
                ]);

                // Compilo la tabella Voce/Preventivo/Consuntivo
                $this->fillTabellaRiepilogoDatiDaDB(
                    $new,
                    $idAssociazione,
                    $anno,
                    (int)$conv->id          // idConvenzione
                );
            } catch (Throwable $e) {
                Log::warning('RiepilogoDati: errore nel foglio convenzione', [
                    'conv' => (int)$conv->id,
                    'nome' => (string)$conv->text,
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $tplWb->disconnectWorksheets();
    }

    /**
     * Compila la tabella Voce/Preventivo/Consuntivo nel foglio template
     * usando i dati della convenzione (voci 1001..1031).
     */
    private function fillTabellaRiepilogoDatiDaDB(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione
    ): void {
        // 1) Prendo le righe già pronte per la UI (include voci "calcolate")
        $rowsAll = Riepilogo::getForDataTable($anno, $idAssociazione, $idConvenzione);

        // 2) Filtro SOLO voci 1001..1031
        $rows = array_values(array_filter($rowsAll, function ($r) {
            $id = (int)($r['voce_id'] ?? 0);
            return $id >= 1001 && $id <= 1031;
        }));

        if (!$rows) return;

        // 3) Individuo header e colonne del template
        $map = $this->detectPrevConsHeader($ws); // già presente nel tuo codice
        $colVoce = $map['colVoce']; // colonna "Voce"
        $colPrev = $map['colPrev']; // colonna "Preventivo"
        $colCons = $map['colCons']; // colonna "Consuntivo"
        $first   = $map['firstDataRow'];

        // 4) Indicizzo le righe del template per etichetta (colonna "Voce")
        $labelToRow = $this->buildLabelIndex($ws, $colVoce, $first);

        // 5) Scrivo i valori
        $writtenRows = [];
        foreach ($rows as $r) {
            $key = $this->canonLabel($r['descrizione'] ?? '');
            $tr  = $labelToRow[$key] ?? null;
            if (!$tr) {
                Log::debug('RiepilogoDati: voce non trovata nel template', [
                    'voce_id' => $r['voce_id'] ?? null,
                    'label'   => $key,
                ]);
                continue;
            }

            $prev = is_numeric($r['preventivo']) ? (float)$r['preventivo'] : null;
            $cons = is_numeric($r['consuntivo']) ? (float)$r['consuntivo'] : null;

            $prev !== null
                ? $ws->setCellValueExplicitByColumnAndRow($colPrev, $tr, $prev, DataType::TYPE_NUMERIC)
                : $ws->setCellValueByColumnAndRow($colPrev, $tr, null);

            $cons !== null
                ? $ws->setCellValueExplicitByColumnAndRow($colCons, $tr, $cons, DataType::TYPE_NUMERIC)
                : $ws->setCellValueByColumnAndRow($colCons, $tr, null);

            $writtenRows[] = $tr;
        }

        // 6) Formato numerico (italiano) sulle righe toccate
        if ($writtenRows) {
            $minR = min($writtenRows);
            $maxR = max($writtenRows);
            $this->formatNum($ws, $this->col($colPrev) . $minR . ':' . $this->col($colPrev) . $maxR);
            $this->formatNum($ws, $this->col($colCons) . $minR . ':' . $this->col($colCons) . $maxR);
        }
    }

    // 2) Modifica buildLabelIndex per usare canonLabel()
    private function buildLabelIndex(
        Worksheet $ws,
        int $colVoce,
        int $startRow = 1,
        int $maxScan  = 500
    ): array {
        $maxRow = min($ws->getHighestRow(), $startRow + $maxScan);
        $index  = [];

        for ($r = $startRow; $r <= $maxRow; $r++) {
            $raw = $ws->getCellByColumnAndRow($colVoce, $r)->getValue();
            $txt = $this->canonLabel($raw);
            if ($txt === '' || $txt === 'VOCE') continue;

            // prima occorrenza vince
            if (!isset($index[$txt])) {
                $index[$txt] = $r;
            }
        }
        return $index;
    }


    // 1) Aggiungi questo helper nella classe Job
    private function canonLabel($v): string {
        if ($v instanceof RichText) {
            $v = $v->getPlainText();
        }
        if (!is_string($v)) return '';

        // uniforma whitespace e rimuovi CR/LF
        $v = str_replace(["\r\n", "\r", "\n"], ' ', $v);

        // rimuovi tutto ciò che è tra parentesi (es. "(stima)")
        $v = preg_replace('/\((?>[^()]+|(?R))*\)/u', '', $v);

        // rimuovi eventuale " - LOTTO X"
        $v = preg_replace('/\s*-\s*LOTTO\s+\d+\s*$/iu', '', $v);

        // normalizza le varianti "per la <nome conv>" -> "per la convenzione"
        $v = preg_replace('/\s+PER\s+LA\s+.+$/iu', ' PER LA CONVENZIONE', $v);

        // normalizza "per l'associazione ..." lasciando solo la radice
        $v = preg_replace("/\s+PER\s+L['’]ASSOCIAZIONE.*$/iu", " PER L'ASSOCIAZIONE", $v);

        // compatta spazi e porta in upper
        $v = preg_replace('/\s+/u', ' ', trim($v));
        return mb_strtoupper($v, encoding: 'UTF-8');
    }

    /**
     * Compila la tabella principale:
     * N° | Voce | PREVENTIVO | CONSUNTIVO
     */
    private function fillTabellaRiepilogoDatiSequential(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione
    ): void {

        /* ------------------------------------------------------
     * 1) Recupero dati
     * ------------------------------------------------------ */
        $all = Riepilogo::getForDataTable($anno, $idAssociazione, $idConvenzione);

        // Filtra solo voci 1001..1031
        $rows = array_values(array_filter($all, function ($r) {
            $id = (int)($r['voce_id'] ?? 0);
            return ($id >= 1001 && $id <= 1031);
        }));

        if (!$rows) {
            return;
        }

        /* ------------------------------------------------------
     * 2) Individua header e colonne
     * ------------------------------------------------------ */
        $hdr   = $this->detectPrevConsHeader($ws);

        $r     = $hdr['firstDataRow'];
        $cNum  = $hdr['colNum'];    // colonna A
        $cVoce = $hdr['colVoce'];   // colonna B
        $cPrev = $hdr['colPrev'];   // colonna C
        $cCons = $hdr['colCons'];   // colonna D

        /* ------------------------------------------------------
     * 3) Fissa larghezze colonne (NO autosize)
     * ------------------------------------------------------ */
        $ws->getColumnDimension($this->col($cVoce))->setAutoSize(false);
        $ws->getColumnDimension($this->col($cPrev))->setAutoSize(false);
        $ws->getColumnDimension($this->col($cCons))->setAutoSize(false);

        $ws->getColumnDimension($this->col($cVoce))->setWidth(45);
        $ws->getColumnDimension($this->col($cPrev))->setWidth(18);
        $ws->getColumnDimension($this->col($cCons))->setWidth(18);

        /* ------------------------------------------------------
     * 4) Scrittura righe
     * ------------------------------------------------------ */
        $firstWritten = null;
        $lastWritten  = null;

        $counter = 1;

        foreach ($rows as $row) {

            // N°
            $ws->setCellValueByColumnAndRow($cNum, $r, $counter);

            // VOCE
            $voce = (string)($row['descrizione'] ?? '');
            $ws->setCellValueByColumnAndRow($cVoce, $r, $voce);

            // Stile voce: wrapText + NO shrink
            $al = $ws->getStyleByColumnAndRow($cVoce, $r)->getAlignment();
            $al->setWrapText(true);
            $al->setShrinkToFit(false);
            $al->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $al->setVertical(Alignment::VERTICAL_TOP);

            // Forza auto-height
            $ws->getRowDimension($r)->setRowHeight(-1);

            // PREVENTIVO
            $prev = is_numeric($row['preventivo']) ? (float)$row['preventivo'] : null;
            $ws->setCellValueExplicitByColumnAndRow(
                $cPrev,
                $r,
                $prev,
                DataType::TYPE_NUMERIC
            );

            // CONSUNTIVO
            $cons = is_numeric($row['consuntivo']) ? (float)$row['consuntivo'] : null;
            $ws->setCellValueExplicitByColumnAndRow(
                $cCons,
                $r,
                $cons,
                DataType::TYPE_NUMERIC
            );

            // Range scritto
            if ($firstWritten === null) {
                $firstWritten = $r;
            }
            $lastWritten = $r;

            $r++;
            $counter++;
        }

        if ($firstWritten === null) {
            return;
        }

        /* ------------------------------------------------------
     * 5) Format numerico
     * ------------------------------------------------------ */
        $this->formatNum(
            $ws,
            $this->col($cPrev) . $firstWritten . ':' . $this->col($cPrev) . $lastWritten
        );

        $this->formatNum(
            $ws,
            $this->col($cCons) . $firstWritten . ':' . $this->col($cCons) . $lastWritten
        );

        /* ------------------------------------------------------
     * 6) Applica stile tabella
     * ------------------------------------------------------ */
        $headerRow = $hdr['firstDataRow'] - 1;

        $this->styleRiepilogoTable(
            $ws,
            $headerRow,
            $lastWritten,
            $cNum,
            $cVoce,
            $cPrev,
            $cCons
        );
    }

    /** Tabella RIEPILOGO COSTI sotto la prima tabella */
    /** Tabella RIEPILOGO COSTI sotto la prima tabella */
    private function fillRiepilogoCostiSottoPrimaTabella(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione
    ): void {

        $startRow = $ws->getHighestDataRow() + 2;

        /* --- TITOLO --- */
        $ws->setCellValue("B{$startRow}", 'RIEPILOGO COSTI');
        $ws->mergeCells("B{$startRow}:F{$startRow}");
        $ws->getStyle("B{$startRow}:F{$startRow}")
            ->getFont()->setBold(true)->setSize(12);

        $startRow += 2;

        /* --- HEADER --- */
        $ws->fromArray(
            ['N°', 'Voce', 'PREVENTIVO', 'CONSUNTIVO', '% SCOSTAMENTO'],
            null,
            "A{$startRow}"
        );

        $ws->getStyle("A{$startRow}:F{$startRow}")->getFont()->setBold(true);
        $ws->getStyle("A{$startRow}:F{$startRow}")
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        $ws->getStyle("A{$startRow}:F{$startRow}")
            ->getAlignment()->setShrinkToFit(false);

        $startRow++;
        $firstDataRow = $startRow;

        /* --- COLONNE --- */
        $ws->getColumnDimension('A')->setWidth(7);
        $ws->getColumnDimension('B')->setWidth(30);
        $ws->getColumnDimension('C')->setWidth(30);
        $ws->getColumnDimension('D')->setWidth(18);
        $ws->getColumnDimension('E')->setWidth(18);
        $ws->getColumnDimension('F')->setWidth(18);

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
            $ws->getColumnDimension($col)->setAutoSize(false);
        }

        /* --- MAPPA SEZIONI --- */
        $mapSezioni = [
            2  => 'Automezzi',
            3  => 'Attrezzatura Sanitaria',
            4  => 'Telecomunicazioni',
            5  => 'Costi gestione struttura',
            6  => 'Costo del personale',
            7  => 'Materiale sanitario di consumo',
            8  => 'Costi amministrativi',
            9  => 'Quote di ammortamento',
            10 => 'Beni Strumentali < 516,00 €',
            11 => 'Altri Costi',
        ];

        $sectionNumber = 1;

        foreach (range(2, 11) as $tipologia) {

            /* --- RIGA SEZIONE --- */
            $rowSezione = $startRow;

            $ws->setCellValue("A{$rowSezione}", "{$sectionNumber}");
            $ws->setCellValue("B{$rowSezione}", $mapSezioni[$tipologia]);

            // MERGE B–C (NON oltre)
            $ws->mergeCells("B{$rowSezione}:C{$rowSezione}");

            $ws->getStyle("A{$rowSezione}:F{$rowSezione}")
                ->getFont()->setBold(true);

            $ws->getStyle("A{$rowSezione}:F{$rowSezione}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEFEFEF');

            // wrap text
            $al = $ws->getStyle("B{$rowSezione}")->getAlignment();
            $al->setWrapText(true);
            $al->setShrinkToFit(false);
            $al->setVertical(Alignment::VERTICAL_TOP);

            $ws->getRowDimension($rowSezione)->setRowHeight(-1);

            $startRow++;

            /* --- RIGHE DELLA SEZIONE --- */
            $rows = RiepilogoCosti::getByTipologia($tipologia, $anno, $idAssociazione, $idConvenzione);

            $sumPrev = 0;
            $sumCons = 0;
            $i = 1;

            foreach ($rows as $r) {

                $descr = (string)$r->descrizione;
                $prev  = (float)$r->preventivo;
                $cons  = (float)$r->consuntivo;

                $ws->setCellValue("A{$startRow}", "{$sectionNumber}.{$i}");
                $ws->setCellValue("B{$startRow}", $descr);

                // MERGE B–C
                $ws->mergeCells("B{$startRow}:C{$startRow}");

                // wrap
                $al2 = $ws->getStyle("B{$startRow}")->getAlignment();
                $al2->setWrapText(true);
                $al2->setShrinkToFit(false);
                $al2->setVertical(Alignment::VERTICAL_TOP);

                // valori
                $ws->setCellValueExplicit("D{$startRow}", $prev, DataType::TYPE_NUMERIC);
                $ws->setCellValueExplicit("E{$startRow}", $cons, DataType::TYPE_NUMERIC);

                $ws->setCellValue(
                    "F{$startRow}",
                    "=IF(D{$startRow}=0,0,(E{$startRow}-D{$startRow})/D{$startRow})"
                );

                $ws->getStyle("D{$startRow}:E{$startRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                $ws->getStyle("F{$startRow}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

                $ws->getRowDimension($startRow)->setRowHeight(-1);

                $sumPrev += $prev;
                $sumCons += $cons;

                $i++;
                $startRow++;
            }

            /* --- SUBTOTAL SEZIONE --- */
            $ws->setCellValue("D{$rowSezione}", $sumPrev);
            $ws->setCellValue("E{$rowSezione}", $sumCons);
            $ws->setCellValue(
                "F{$rowSezione}",
                "=IF(D{$rowSezione}=0,0,(E{$rowSezione}-D{$rowSezione})/D{$rowSezione})"
            );

            $ws->getStyle("D{$rowSezione}:E{$rowSezione}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $ws->getStyle("F{$rowSezione}")
                ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

            $sectionNumber++;
            $startRow++;
        }

        $lastDataRow = $startRow - 1;

        /* --- ALLINEAMENTO NUMERI --- */
        $ws->getStyle("D{$firstDataRow}:F{$lastDataRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        /* --- BORDO TOTALE --- */
        $this->applyGridWithOuterBorder(
            $ws,
            "A" . ($firstDataRow - 2) . ":F{$lastDataRow}"
        );
    }





    /** Converte '12,34%' | '12.34%' | '12.34' in 0.1234 */
    /** Converte input in frazione (0..1). Gestisce %, punti percentuali e basis points. */
    private function percentToFloat($v): float {
        $s = trim((string)$v);
        // togli spazi e NBSP
        $s = str_replace([' ', "\xC2\xA0"], '', $s);

        // c'è un simbolo di percentuale?
        $hasPercent = str_ends_with($s, '%');
        $s = rtrim($s, '%');

        // normalizza separatori decimali/migliaia (it/en)
        // rimuovi punti come separatore migliaia, poi usa punto decimale
        $s = preg_replace('/[.\s]/u', '', $s);
        $s = str_replace(',', '.', $s);

        if (!is_numeric($s)) return 0.0;
        $num = (float)$s;
        $abs = abs($num);

        if ($hasPercent) {
            // "280,00%" -> 280 -> 2.8 (Excel con formato % mostrerà 280,00%)
            return $num / 100.0;
        }

        // Se è già frazione (0..1) lasciala così
        if ($abs <= 1.0) return $num;

        // Heuristics:
        //  - >= 10000 => basis points (9875 -> 0.9875 ; 28000 -> 2.8)
        //  - altrimenti => punti percentuali (98.75 -> 0.9875 ; 280 -> 2.8)
        return ($abs >= 10000.0) ? ($num / 10000.0) : ($num / 100.0);
    }

    /** Bordo sottile su tutta l’area + bordo spesso esterno */
    private function applyGridWithOuterBorder(Worksheet $ws, string $range): void {
        $all = [
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];
        $outer = [
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM],
            ],
        ];
        $ws->getStyle($range)->applyFromArray($all);
        $ws->getStyle($range)->applyFromArray($outer);
    }
    private function BlockRotazioneMezzi(
        Worksheet $sheet,
        array $tpl,
        $automezzi,
        $convenzioni,
        int $idAssociazione,
        int $anno
    ): int {
        // 1) Header/colonne dal template (D = KMTOT; convenzioni da E in poi)
        [$headerRow, $columns] = $this->detectKmHeaderAndCols($sheet, $tpl);
        $firstPairCol = $columns['KMTOT'] + 1; // deve essere E
        $rowHeaderConvenzioni = $headerRow - 1;

        // 2) Filtra convenzioni in vero regime di ROTAZIONE
        $convList = collect($convenzioni)->values()->filter(
            fn($c) => RipartizioneCostiService::isRegimeRotazione((int)$c->idConvenzione)
        )->values();

        if ($convList->isEmpty()) {
            Log::warning('RotazioneMezzi: nessuna convenzione in regime di rotazione.');
            return $tpl['endRow'];
        }

        $convIds     = $convList->pluck('idConvenzione')->map(fn($v) => (int)$v)->all();
        $usedPairs   = count($convIds);
        $lastUsedCol = $firstPairCol + ($usedPairs * 2) - 1;

        // 2bis) Logo coerente con handle
        //$this->placeLogoAtRow($sheet, $tpl, $this->logos['left'] ?? null, $tpl['startRow'] + 2, 2, 64, 0, 36, 2, 26);

        // 3) Titoli convenzioni (sopra banda)
        foreach ($convList as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $rowHeaderConvenzioni, (string)$c->Convenzione);
        }

        // 4) Dati km
        $kmTotByConv = DB::table('automezzi_km as ak')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
            ->whereIn('ak.idConvenzione', $convIds)
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->select('ak.idConvenzione', DB::raw('SUM(ak.KMPercorsi) AS km'))
            ->groupBy('ak.idConvenzione')
            ->pluck('km', 'ak.idConvenzione')
            ->map(fn($v) => (int) round((float)$v))
            ->all();

        $kmByMezzoConv = DB::table('automezzi_km as ak')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
            ->whereIn('ak.idConvenzione', $convIds)
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->select('ak.idAutomezzo', 'ak.idConvenzione', DB::raw('SUM(ak.KMPercorsi) AS km'))
            ->groupBy('ak.idAutomezzo', 'ak.idConvenzione')
            ->get()
            ->groupBy(fn($r) => (int)$r->idAutomezzo)
            ->map(function ($rows) {
                $m = [];
                foreach ($rows as $r) $m[(int)$r->idConvenzione] = (int) round((float)$r->km);
                return $m;
            })
            ->all();

        // 5) Somma KM per mezzo SOLO sulle conv in rotazione
        $kmTotByMezzo = [];
        foreach ($kmByMezzoConv as $idM => $byConv) {
            $s = 0;
            foreach ($convIds as $cid) $s += (int) ($byConv[$cid] ?? 0);
            $kmTotByMezzo[(int)$idM] = $s;
        }

        // 6) Preparazione scrittura righe
        $sampleRow  = $headerRow + 1;
        $lastColLetter = ($lastUsedCol > 0)
            ? Coordinate::stringFromColumnIndex($lastUsedCol)
            : 'A';
        $styleRange = "A{$sampleRow}:{$lastColLetter}{$sampleRow}";

        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $rows       = [];
        $totKmColByConv = array_fill_keys($convIds, 0);

        // Filtra i mezzi che hanno km>0 su almeno una convenzione in rotazione
        $automezzi = collect($automezzi)
            ->filter(fn($a) => isset($kmByMezzoConv[(int)$a->idAutomezzo]) && array_sum($kmByMezzoConv[(int)$a->idAutomezzo]) > 0)
            ->values()
            ->all();

        foreach ($automezzi as $idx => $a) {
            $idM   = (int)$a->idAutomezzo;
            $kmTot = (int)($kmTotByMezzo[$idM] ?? 0);

            $r = [
                ['col' => $columns['PROGR'],  'val' => 'AUTO ' . ($idx + 1)],
                ['col' => $columns['TARGA'],  'val' => (string)$a->Targa, 'type' => DataType::TYPE_STRING],
                ['col' => $columns['CODICE'], 'val' => (string)($a->CodiceIdentificativo ?? ''), 'type' => DataType::TYPE_STRING],
                ['col' => $columns['KMTOT'],  'val' => $kmTot, 'fmt' => NumberFormat::FORMAT_NUMBER],
            ];

            foreach (array_values($convIds) as $i => $cid) {
                $kmCol = $firstPairCol + ($i * 2);
                $pcCol = $kmCol + 1;
                $km  = (int)($kmByMezzoConv[$idM][$cid] ?? 0);
                $den = (int)($kmTotByConv[$cid] ?? 0);
                $pct = $den > 0 ? ($km / $den) : 0.0;

                $r[] = ['col' => $kmCol, 'val' => $km, 'fmt' => NumberFormat::FORMAT_NUMBER];
                $r[] = ['col' => $pcCol, 'val' => $pct, 'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];

                $totKmColByConv[$cid] += $km;
            }

            $rows[] = $r;
        }

        // 7) Inserisci righe PRIMA della riga "TOTALE"
        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // 8) Riga TOTALE
        $sumKmAll = array_sum($kmTotByMezzo);
        $sheet->setCellValueByColumnAndRow($columns['KMTOT'], $totRowNew, $sumKmAll);
        $sheet->getStyleByColumnAndRow($columns['KMTOT'], $totRowNew)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

        foreach (array_values($convIds) as $i => $cid) {
            $kmCol = $firstPairCol + ($i * 2);
            $pcCol = $kmCol + 1;
            $sheet->setCellValueByColumnAndRow($kmCol, $totRowNew, (int)$totKmColByConv[$cid]);
            $sheet->getStyleByColumnAndRow($kmCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, 1);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        // 9) Bordature / outline
        $this->thickBorderRow($sheet, $headerRow, 1, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  1, $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)
            ->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, 1, $lastUsedCol);

        // 10) Taglio colonne oltre convenzioni
        //$this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);

        Log::info('RotazioneMezzi: scritto blocco', [
            'hdrTopRow'     => $rowHeaderConvenzioni - 1,
            'hdrSubRow'     => $headerRow,
            'firstDataRow'  => $headerRow + 1,
            'lastDataRow'   => $totRowNew - 1,
            'totalRow'      => $totRowNew,
            'convenzioni'   => $convList->pluck('Convenzione')->all(),
        ]);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    private function clearTotaleBilancioFill(Worksheet $sheet): void {
        $pos = $this->findCellByText($sheet, 'TOTALE A BILANCIO');
        if (!$pos) return;

        [$cLabel, $r] = $pos;
        // etichetta + cella importo (subito a destra, spesso unita/merge)
        $cStart = $cLabel;
        $cEnd   = $cLabel + 1;

        $range = Coordinate::stringFromColumnIndex($cStart) . $r . ':' .
            Coordinate::stringFromColumnIndex($cEnd)   . $r;

        // niente fill (o bianco se preferisci)
        $sheet->getStyle($range)->getFill()->setFillType(
            Fill::FILL_NONE
        );
        // se vuoi proprio “bianco”:
        // $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
    }


    private function safeMergeRange(Worksheet $ws, string $a1): void {
        $a1 = strtoupper(str_replace(' ', '', $a1));
        if ($a1 === '' || isset($ws->getMergeCells()[$a1])) return;
        [$from, $to] = explode(':', $a1) + [null, null];
        if (!$to) return;
        [$c1, $r1] = Coordinate::coordinateFromString($from);
        [$c2, $r2] = Coordinate::coordinateFromString($to);
        if ($r1 > $r2) [$r1, $r2] = [$r2, $r1];
        if (Coordinate::columnIndexFromString($c1) > Coordinate::columnIndexFromString($c2)) [$c1, $c2] = [$c2, $c1];
        if ($c1 === $c2 && $r1 === $r2) return; // 1x1
        $ws->mergeCells("{$c1}{$r1}:{$c2}{$r2}");
    }

    private function safeMergeByIdx(Worksheet $ws, int $c1, int $r1, int $c2, int $r2): void {
        if ($r1 > $r2) [$r1, $r2] = [$r2, $r1];
        if ($c1 > $c2) [$c1, $c2] = [$c2, $c1];
        if ($c1 === $c2 && $r1 === $r2) return;
        $this->safeMergeRange(
            $ws,
            Coordinate::stringFromColumnIndex($c1) . $r1 . ':' . Coordinate::stringFromColumnIndex($c2) . $r2
        );
    }


    // opzionale: ripulisce eventuali duplicati residui
    private function dedupeMerges(Worksheet $ws): void {
        $ranges = array_keys($ws->getMergeCells());
        if (empty($ranges)) return;

        // 1) Smonta tutti i merge attuali
        foreach ($ranges as $rng) {
            try {
                $ws->unmergeCells($rng);
            } catch (Throwable $e) { /* ignore */
            }
        }

        // 2) Normalizza i range e ri-applica senza duplicati
        $seen = [];
        foreach ($ranges as $rng) {
            $rng = strtoupper(str_replace(' ', '', $rng));
            if (!str_contains($rng, ':')) continue;

            try {
                [$a, $b]   = explode(':', $rng, 2);
                [$c1, $r1] = Coordinate::coordinateFromString($a);
                [$c2, $r2] = Coordinate::coordinateFromString($b);

                $r1 = (int)$r1;
                $r2 = (int)$r2;
                if ($r1 > $r2) [$r1, $r2] = [$r2, $r1];

                $i1 = Coordinate::columnIndexFromString($c1);
                $i2 = Coordinate::columnIndexFromString($c2);
                if ($i1 > $i2) [$i1, $i2] = [$i2, $i1];

                // evita merge 1x1
                if ($i1 === $i2 && $r1 === $r2) continue;

                $norm = Coordinate::stringFromColumnIndex($i1) . $r1 . ':' .
                    Coordinate::stringFromColumnIndex($i2) . $r2;

                if (isset($seen[$norm])) continue;
                $seen[$norm] = true;

                $ws->mergeCells($norm);
            } catch (Throwable $e) {
                // range malformato: skip
                continue;
            }
        }
    }

    private function placeHeaderImage(
        Worksheet $ws,
        string $imgPath,
        string $anchorCell,          // es. "B2" (ma gestiamo anche input sporchi)
        int $rowSpan = 2,
        float $targetHeightPx = 70,
        int $offsetX = 6,
        int $offsetY = 10
    ): void {
        if (!is_file($imgPath)) return;

        // --- Parsifica l'anchor in modo robusto ---
        // Accetta "B2", "B", "2", "b2", "B02" ecc.
        $anchorCell = strtoupper(trim($anchorCell));
        $colLetters = 'B';  // default di sicurezza
        $rowNumber  = 2;    // default di sicurezza

        if (preg_match('/^([A-Z]+)?\s*([0-9]+)?$/', $anchorCell, $m)) {
            if (!empty($m[1])) $colLetters = $m[1];
            if (!empty($m[2])) $rowNumber  = (int)$m[2];
        }

        // Forza valori minimi validi
        $rowNumber = max(1, (int)$rowNumber);
        // Se per assurdo è arrivato qualcosa senza colonna, tieni 'B'
        if (!preg_match('/^[A-Z]+$/', $colLetters)) {
            $colLetters = 'B';
        }

        // --- Calcola l'ultima colonna reale (>= A) ---
        $lastColIdx = max(1, Coordinate::columnIndexFromString($ws->getHighestColumn()));
        $lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

        // --- Calcola riga finale del banner ---
        $endRow = $rowNumber + max(1, $rowSpan) - 1;

        // --- Mergia fascia header e alza righe banner ---
        $ws->mergeCells("{$colLetters}{$rowNumber}:{$lastCol}{$endRow}");
        for ($r = $rowNumber; $r <= $endRow; $r++) {
            $ws->getRowDimension($r)->setRowHeight(28); // ~28pt ciascuna
        }

        // --- Inserisci il logo con offset ---
        $drawing = new Drawing();
        $drawing->setName('HeaderLogo');
        $drawing->setPath($imgPath);
        $drawing->setHeight($targetHeightPx);
        $drawing->setCoordinates($colLetters . $rowNumber); // ora è sempre valido (es. "B2")
        $drawing->setOffsetX($offsetX);
        $drawing->setOffsetY($offsetY);
        $drawing->setWorksheet($ws);
    }

    private function addBannerAboveBlockAndPlaceLogo(
        Worksheet $ws,
        array &$tpl,
        ?string $imgPath,
        int $bannerRows = 3,
        int $targetHeightPx = 64
    ): void {
        if ($bannerRows < 1) $bannerRows = 1;

        $start = max(1, (int)($tpl['startRow'] ?? 1));

        // 1) Inserisci righe PRIMA del blocco
        $ws->insertNewRowBefore($start, $bannerRows);

        // 2) Aggiorna i meta del blocco (tutto scende)
        $tpl['startRow'] = $start + $bannerRows;
        if (isset($tpl['endRow'])) $tpl['endRow'] += $bannerRows;

        // 3) Rendi la fascia “alta” e a tutta larghezza
        $lastColIdx = max(1, Coordinate::columnIndexFromString($ws->getHighestColumn()));
        $lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);
        $fromRow    = $start;
        $toRow      = $start + $bannerRows - 1;

        // Alza le righe della fascia
        for ($r = $fromRow; $r <= $toRow; $r++) {
            $ws->getRowDimension($r)->setRowHeight(28);
        }
        // Merge su tutta la larghezza del foglio
        $ws->mergeCells("B{$fromRow}:{$lastCol}{$toRow}");

        // 4) Logo ancorato all’inizio fascia
        if ($imgPath && is_file($imgPath)) {
            $d = new Drawing();
            $d->setName('HeaderLogo');
            $d->setPath($imgPath);
            $d->setHeight($targetHeightPx);
            $d->setCoordinates('B' . $fromRow);
            $d->setOffsetX(6);
            $d->setOffsetY(8);
            $d->setWorksheet($ws);
        }
    }

    /**I/O & Template */
    /**
     * Copia il template nel foglio mantenendo stili/merge/larghezze/altezza/visibilità.
     * Ritorna meta: ['startRow'=>int, 'endRow'=>int]
     */
    private function appendTemplate(Worksheet $dst, string $templateAbs, int $rowCursor = 1): array {
        $type   = IOFactory::identify($templateAbs);
        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(false);

        $tpl = $reader->load($templateAbs);
        $src = $tpl->getSheet(0);

        $maxRow = $src->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($src->getHighestColumn());

        // === Copia colonne ===
        for ($c = 1; $c <= $maxCol; $c++) {
            $colL   = Coordinate::stringFromColumnIndex($c);
            $srcDim = $src->getColumnDimension($colL);
            $dstDim = $dst->getColumnDimension($colL);

            $dstDim->setAutoSize(false);
            $w = $srcDim->getWidth();
            if ($w !== null) {
                $dstDim->setWidth($w);
            }
            $dstDim->setVisible($srcDim->getVisible());
        }

        // === Copia celle + stili ===
        for ($r = 1; $r <= $maxRow; $r++) {
            $dstR  = $rowCursor + $r - 1;
            $srcRow = $src->getRowDimension($r);
            $dstRow = $dst->getRowDimension($dstR);
            $dstRow->setRowHeight($srcRow->getRowHeight());
            $dstRow->setVisible($srcRow->getVisible());

            for ($c = 1; $c <= $maxCol; $c++) {
                $srcCell = $src->getCellByColumnAndRow($c, $r);
                $dstCell = $dst->getCellByColumnAndRow($c, $dstR);

                // Copia valore e tipo
                $dstCell->setValueExplicit($srcCell->getValue(), $srcCell->getDataType());

                // Copia stile se esiste
                $styleArr = $src->getStyleByColumnAndRow($c, $r)->exportArray();
                if (!empty($styleArr)) {
                    $dst->getStyleByColumnAndRow($c, $dstR)->applyFromArray($styleArr);
                }
            }
        }

        // === Copia merge in modo “safe” ===
        foreach ($src->getMergeCells() as $merge) {
            try {
                // Esempio: A1:C3 → [ [1,1], [3,3] ]
                [$start, $end] = Coordinate::rangeBoundaries($merge);
                [$c1, $r1] = $start;
                [$c2, $r2] = $end;

                $newR1 = $r1 + $rowCursor - 1;
                $newR2 = $r2 + $rowCursor - 1;

                // Range valido?
                if ($c1 <= 0 || $c2 <= 0 || $newR1 <= 0 || $newR2 <= 0) continue;

                // Range string (es. "A10:C12")
                $range = Coordinate::stringFromColumnIndex($c1) . $newR1 . ':' .
                    Coordinate::stringFromColumnIndex($c2) . $newR2;

                // Evita duplicati o sovrapposizioni
                $already = false;
                foreach ($dst->getMergeCells() as $existing) {
                    if (strtoupper($existing) === strtoupper($range)) {
                        $already = true;
                        break;
                    }
                }
                if ($already) continue;

                $dst->mergeCells($range);
            } catch (Throwable $e) {
                Log::warning('appendTemplate: merge skip', ['tpl' => $templateAbs, 'msg' => $e->getMessage()]);
                continue;
            }
        }

        $start = $rowCursor;
        $end   = $rowCursor + $maxRow - 1;

        $tpl->disconnectWorksheets();
        unset($tpl);

        return ['startRow' => $start, 'endRow' => $end];
    }

    private function safeSaveSpreadsheet(Spreadsheet $spreadsheet, string $baseFilename, string $dirRel = 'documenti', int $retries = 3, int $sleepMs = 300): array {
        $disk = Storage::disk('public');
        if (!$disk->exists($dirRel)) $disk->makeDirectory($dirRel);

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . uniqid('riparto_', true) . '.xlsx';
        $writer  = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($tmpPath);

        $finalRel = $dirRel . '/' . $baseFilename;
        $finalAbs = $disk->path($finalRel);

        for ($i = 0; $i <= $retries; $i++) {
            try {
                if (file_exists($finalAbs)) @unlink($finalAbs);
                if (@rename($tmpPath, $finalAbs)) return [$finalRel, $baseFilename];
            } catch (Throwable $e) {
                Log::warning('safeSaveSpreadsheet retry', ['target' => $finalAbs, 'try' => $i, 'error' => $e->getMessage()]);
            }
            if ($i === $retries) {
                $altName = pathinfo($baseFilename, PATHINFO_FILENAME) . '_' . now()->format('Ymd_His') . '.' . pathinfo($baseFilename, PATHINFO_EXTENSION);
                $altRel  = $dirRel . '/' . $altName;
                $altAbs  = $disk->path($altRel);
                if (@rename($tmpPath, $altAbs)) return [$altRel, $altName];
            } else {
                usleep($sleepMs * 1000);
            }
        }
        $stream = fopen($tmpPath, 'rb');
        if ($stream !== false) {
            $ok = $disk->put($finalRel, $stream);
            fclose($stream);
            @unlink($tmpPath);
            if ($ok) return [$finalRel, $baseFilename];
        }
        @unlink($tmpPath);
        throw new \RuntimeException('Impossibile salvare il file Excel su disco pubblico.');
    }

    private function setupSpreadsheetCaching(): void {
        try {
            $tmpDir = storage_path('app/phpss_cache');
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

            // Nuove versioni (>= 1.29): Settings::setCache(new PhpTemp(...)) o FileCache
            if (method_exists(\PhpOffice\PhpSpreadsheet\Settings::class, 'setCache')) {
                if (class_exists(\PhpOffice\PhpSpreadsheet\CachedObjectStorage\PhpTemp::class)) {
                    \PhpOffice\PhpSpreadsheet\Settings::setCache(
                        new \PhpOffice\PhpSpreadsheet\CachedObjectStorage\PhpTemp([
                            'memoryCacheSize' => '64MB',
                        ])
                    );
                    \Log::info('PhpSpreadsheet: cache = PhpTemp (64MB).');
                    return;
                }
                if (class_exists(\PhpOffice\PhpSpreadsheet\CachedObjectStorage\File::class)) {
                    \PhpOffice\PhpSpreadsheet\Settings::setCache(
                        new \PhpOffice\PhpSpreadsheet\CachedObjectStorage\File([
                            'dir' => $tmpDir,
                        ])
                    );
                    \Log::info('PhpSpreadsheet: cache = File (dir=' . $tmpDir . ').');
                    return;
                }
            }

            // Legacy (1.24–1.28): factory
            if (class_exists(\PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::class)) {
                $ok = \PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::initialize(
                    \PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::cache_to_phpTemp,
                    ['memoryCacheSize' => '64MB']
                );
                if (!$ok) {
                    $ok = \PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::initialize(
                        \PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::cache_to_discISAM,
                        ['dir' => $tmpDir]
                    );
                    if ($ok) {
                        Log::info('PhpSpreadsheet (legacy): cache = discISAM (dir=' . $tmpDir . ').');
                        return;
                    }
                } else {
                    Log::info('PhpSpreadsheet (legacy): cache = phpTemp (64MB).');
                    return;
                }
            }

            Log::info('PhpSpreadsheet: nessun caching disponibile; continuo senza setup esplicito.');
        } catch (Throwable $e) {
            Log::warning('PhpSpreadsheet cache setup failed', ['err' => $e->getMessage()]);
        }
    }

    /**
     * Trova la riga header "N° | Voce | PREVENTIVO | CONSUNTIVO"
     * e le colonne corrispondenti.
     *
     * Ritorna:
     *  - colNum       (es. 1 → A)
     *  - colVoce      (es. 2 → B)
     *  - colPrev      (es. 3 → C)
     *  - colCons      (es. 4 → D)
     *  - firstDataRow (prima riga dati sotto l'header)
     */
    private function detectPrevConsHeader(Worksheet $ws): array {
        $maxR = $ws->getHighestDataRow();
        $maxC = Coordinate::columnIndexFromString(
            $ws->getHighestDataColumn()
        );

        for ($r = 1; $r <= $maxR; $r++) {

            $colNum  = null;
            $colVoce = null;
            $colPrev = null;
            $colCons = null;

            for ($c = 1; $c <= $maxC; $c++) {

                // Usa canonLabel per evitare problemi con merge/CRLF/spazi
                $v = $this->canonLabel($ws->getCellByColumnAndRow($c, $r)->getValue());

                if (in_array($v, ['N', 'N°', '#'], true)) {
                    $colNum = $c;
                } elseif ($v === 'VOCE') {
                    $colVoce = $c;
                } elseif ($v === 'PREVENTIVO' || $v === 'PREV') {
                    $colPrev = $c;
                } elseif ($v === 'CONSUNTIVO' || $v === 'CONS') {
                    $colCons = $c;
                }
            }

            // se la riga contiene almeno VOCE + PREV + CONS → è l’header giusto
            if ($colVoce && $colPrev && $colCons) {

                // se manca la colonna numero, la creo in automatico prima della VOCE
                if (!$colNum) {
                    $colNum = max(1, $colVoce - 1);
                }

                return [
                    'colNum'       => $colNum,
                    'colVoce'      => $colVoce,
                    'colPrev'      => $colPrev,
                    'colCons'      => $colCons,
                    'firstDataRow' => $r + 1,
                ];
            }
        }

        throw new RuntimeException(
            "Header PREVENTIVO/CONSUNTIVO non trovato nel foglio " . $ws->getTitle()
        );
    }


    /**
     * Trova la riga/colonna di inizio tabella del template “Riepilogo …”
     * Ritorna [rowIndex, firstColIndex] dove firstCol è la colonna della cella "TOTALE AUTO".
     */
    private function locateRiepilogoHeader(Worksheet $s): array {
        $maxR = $s->getHighestDataRow();
        $maxC = Coordinate::columnIndexFromString($s->getHighestDataColumn());

        for ($r = 1; $r <= $maxR; $r++) {
            for ($c = 1; $c <= $maxC; $c++) {
                $v = trim((string) $s->getCellByColumnAndRow($c, $r)->getValue());
                if (mb_stripos($v, 'TOTALE AUTO') !== false) {
                    return [$r, $c];
                }
            }
        }
        // fallback: riga 6, colonna 1 (A6) se non trovato
        return [6, 1];
    }

    /**
     * Ritorna [ $hdrRow, $colVoce, $colTotRip, $colFirstConv, $colTotCol ]
     * cercando nel template l'intestazione con:
     * - colonna voce (testo come "LEASING/NOLEGGIO..." ecc)
     * - colonna "TOTALE COSTI DA RIPARTIRE"
     * - un blocco di colonne convenzioni
     * - ultima colonna "TOTALE"
     */
    private function locateHeaderAutoDetail(Worksheet $s): array {
        $maxRow = min(40, $s->getHighestRow());
        $maxCol = Coordinate::columnIndexFromString($s->getHighestColumn());

        $hdrRow = 0;
        $colVoce = 0;
        $colTotRip = 0;
        $colFirstConv = 0;
        $colTotCol = 0;

        for ($r = 1; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = trim((string)$s->getCellByColumnAndRow($c, $r)->getValue());
                $vn = mb_strtoupper($v, 'UTF-8');

                if ($vn === 'TOTALE COSTI DA RIPARTIRE') {
                    $hdrRow     = $r;
                    $colTotRip  = $c;
                    // ipotizza che la colonna VOCE sia a sinistra (prima colonna non vuota a sx)
                    $colVoce = max(1, $c - 1);
                    while ($colVoce > 1) {
                        $pv = trim((string)$s->getCellByColumnAndRow($colVoce - 1, $r)->getValue());
                        if ($pv === '') break;
                        $colVoce--;
                    }
                    // trova "TOTALE" a destra
                    $cc = $c + 1;
                    while ($cc <= $maxCol) {
                        $x = trim((string)$s->getCellByColumnAndRow($cc, $r)->getValue());
                        if (mb_strtoupper($x, 'UTF-8') === 'TOTALE') {
                            $colTotCol = $cc;
                            break;
                        }
                        $cc++;
                    }
                    // prima convenzione = subito dopo "TOTALE COSTI DA RIPARTIRE"
                    $colFirstConv = $c + 1;
                    if ($hdrRow && $colTotCol) return [$hdrRow, $colVoce, $colTotRip, $colFirstConv, $colTotCol];
                }
            }
        }

        // fallback conservativo (in caso non trovasse i testi)
        if (!$hdrRow) {
            $hdrRow = 7;
        }
        if (!$colVoce) {
            $colVoce = 1;
        }
        if (!$colTotRip) {
            $colTotRip = 2;
        }
        if (!$colFirstConv) {
            $colFirstConv = 3;
        }
        if (!$colTotCol) {
            $colTotCol = $colFirstConv + 8;
        } // 8 conv di default
        return [$hdrRow, $colVoce, $colTotRip, $colFirstConv, $colTotCol];
    }

    /**
     * Riconosce header/colonne del template costi automezzi.
     * Tiene conto di celle header unite (controlla anche la riga sotto)
     * e dà priorità alle voci “sanitarie” per evitare falsi match.
     */
    private function detectAutomezziHeaderAndCols(Worksheet $sheet, array $tpl): array {
        // 1) Trova una riga plausibile di header (TARGA + CODICE)
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 220, $tpl['endRow']); $r++) {
            $hasTarga = $hasCodice = false;
            for ($c = 1; $c <= 200; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'TARGA') $hasTarga = true;
                if (str_contains($t, 'CODICE') && str_contains($t, 'IDENTIFICAT')) $hasCodice = true;
            }
            if ($hasTarga && $hasCodice) {
                $headerRow = $r;
                break;
            }
        }

        $norm = fn($v) => preg_replace('/\s+/', ' ', mb_strtoupper(trim((string)$v)));
        $label = function (int $col) use ($sheet, $headerRow, $norm): string {
            $a = $norm($sheet->getCellByColumnAndRow($col, $headerRow)->getValue());
            $b = $norm($sheet->getCellByColumnAndRow($col, $headerRow + 1)->getValue());
            return $a !== '' ? $a : $b;
        };

        // 2) Pattern per ciascuna colonna (senza “TOTALE”: è una riga)
        $patterns = [
            'TARGA'      => '/^TARGA$/',
            'CODICE'     => '/CODICE.*IDENTIFICAT/',
            'LEAS_SAN'   => '/\bLEASING\b.*ATTREZZAT.*SANITAR/',
            'MAN_SAN'    => '/MANUTENZIONE.*ATTREZZAT.*SANITAR/',
            'AMM_SAN'    => '/AMMORTAMENTO.*ATTREZZAT.*SANITAR/',
            'ASSIC'      => '/^ASSICURAZIONE$/',
            'MAN_ORD'    => '/MANUTENZIONE.*ORDINARIA/',
            'MAN_STRA'   => '/MANUTENZIONE.*STRAORDINARIA/',
            'RIMB_ASS'   => '/RIMBORS.*ASSICUR/',
            'PULIZIA'    => '/(PULIZIA|DISINFEZIONE).*AUTOMEZZI?/',
            'CARB'       => '/^CARBURANTI$/',
            'ADDITIVI'   => '/^ADDITIVI$/',
            'RIMB_UTF'   => '/RIMBORSI.*UTF/',
            'INTERESSI'  => '/INTERESSI.*(FIN\.?TO|LEASING|NOLEGGIO)?/',
            'ALTRI'      => '/ALTRI.*COSTI.*MEZZI/',
            'AMM_MEZZI'  => '/AMMORTAMENTO.*(AUTOMEZZI|MEZZI)/',
            'LEASING'    => '/\b(LEASING|NOLEGGIO)\b(?!.*SANITAR)/', // non sanitaria
        ];

        // 3) Scansione e mapping (prima le sanitarie)
        $order = [
            'TARGA',
            'CODICE',
            'LEAS_SAN',
            'MAN_SAN',
            'AMM_SAN',
            'LEASING',
            'ASSIC',
            'MAN_ORD',
            'MAN_STRA',
            'RIMB_ASS',
            'PULIZIA',
            'CARB',
            'ADDITIVI',
            'RIMB_UTF',
            'INTERESSI',
            'ALTRI',
            'AMM_MEZZI',
        ];

        $cols = array_fill_keys($order, null);
        $cols['IDX'] = 1;

        for ($c = 1; $c <= 200; $c++) {
            $txt = $label($c);
            if ($txt === '') continue;
            foreach ($order as $k) {
                if ($cols[$k] !== null) continue;
                if (preg_match($patterns[$k], $txt)) {
                    $cols[$k] = (int)$c;
                    break;
                }
            }
        }

        // 4) Colonne minime obbligatorie (se manca, meglio fallire)
        $must = ['TARGA', 'CODICE', 'LEASING', 'ASSIC', 'MAN_ORD', 'MAN_STRA', 'RIMB_ASS'];

        foreach ($must as $k) {
            if (empty($cols[$k])) {
                Log::warning("Template Excel: colonna non trovata: {$k} (uso colonna 1 come fallback)");
                $cols[$k] = 1;
            }
        }

        // 5) Posizione colonna indice (a sinistra di TARGA)
        $cols['IDX'] = max(1, $cols['TARGA'] - 1);

        Log::debug('Mappa colonne COSTI AUTOMEZZI (senza TOTALE-colonna)', $cols);
        return [$headerRow, $cols];
    }

    /** Trova header e colonne fisse del blocco A&B con INPS/INAIL separati */
    private function detectRipartoABHeaderAndCols(Worksheet $sheet, array $tpl): array {
        // cerca una riga che contenga almeno alcune intestazioni note
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 220, $tpl['endRow']); $r++) {
            $hits = 0;
            for ($c = 1; $c <= 120; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if (in_array($t, ['COGNOME', 'COGNOME E NOME', 'NOME E COGNOME'])) $hits++;
                if ($t === 'RETRIBUZIONI') $hits++;
                if (str_contains($t, 'ONERI') || str_contains($t, 'INPS')) $hits++; // per compatibilità
                if (str_contains($t, 'INAIL')) $hits++;
                if ($t === 'TFR') $hits++;
                if (str_starts_with($t, 'CONSULENZE')) $hits++;
                if ($t === 'TOTALE') $hits++;
            }
            if ($hits >= 4) {
                $headerRow = $r;
                break;
            }
        }

        // mappa colonne (cerco specifico INPS/INAIL; fallback se non trovati)
        $cols = ['IDX' => 1, 'COGNOME' => 2, 'RETR' => 3, 'INPS' => null, 'INAIL' => null, 'TFR' => null, 'CONS' => null, 'TOTALE' => null];
        for ($c = 1; $c <= 160; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if (in_array($t, ['COGNOME', 'COGNOME E NOME', 'NOME E COGNOME'])) $cols['COGNOME'] = $c;
            elseif ($t === 'RETRIBUZIONI')                                       $cols['RETR']   = $c;
            elseif (str_contains($t, 'INPS'))                                    $cols['INPS']   = $c;
            elseif (str_contains($t, 'INAIL'))                                   $cols['INAIL']  = $c;
            elseif ($t === 'TFR')                                                $cols['TFR']    = $c;
            elseif (str_starts_with($t, 'CONSULENZE'))                           $cols['CONS']   = $c;
            elseif ($t === 'TOTALE')                                             $cols['TOTALE'] = $c;
        }

        // fallback: se il template è vecchio (colonna "ONERI"), prova a splittare in due colonne adiacenti
        if (is_null($cols['INPS']) || is_null($cols['INAIL'])) {
            for ($c = 1; $c <= 160; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
                if (str_contains($t, 'ONERI')) {
                    $cols['INPS']  = $c;
                    $cols['INAIL'] = $c + 1;
                    break;
                }
            }
        }

        // fallback ulteriori
        if (is_null($cols['TFR']))    $cols['TFR']    = max($cols['RETR'], $cols['INPS'] ?? 0, $cols['INAIL'] ?? 0) + 1;
        if (is_null($cols['CONS']))   $cols['CONS']   = ($cols['TFR'] ?? 0) + 1;
        if (is_null($cols['TOTALE'])) $cols['TOTALE'] = ($cols['CONS'] ?? 0) + 1;

        // indice (prima colonna a sinistra della colonna nome)
        $cols['IDX'] = max(1, ($cols['COGNOME'] ?? 2) - 1);

        return [$headerRow, $cols];
    }

    /**
     * Header/colonne per i template singola mansione (no convenzioni).
     * Riconosce INPS/INAIL separati. Compatibile con vecchi template che hanno “ONERI SOCIALI”.
     */
    private function detectMansioneHeaderAndCols(Worksheet $sheet, array $tpl): array {
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 220, $tpl['endRow']); $r++) {
            $hits = 0;
            for ($c = 1; $c <= 120; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if (in_array($t, ['COGNOME', 'COGNOME E NOME', 'NOME'])) $hits++;
                if ($t === 'RETRIBUZIONI') $hits++;
                if (str_contains($t, 'ONERI')) $hits++;           // INPS/INAIL o colonna unica “ONERI SOCIALI”
                if ($t === 'TFR') $hits++;
                if (str_starts_with($t, 'CONSULENZE') || str_contains($t, 'SORVEGLIANZA')) $hits++;
                if ($t === 'TOTALE') $hits++;
            }
            if ($hits >= 4) {
                $headerRow = $r;
                break;
            }
        }

        $cols = ['IDX' => 1, 'COGNOME' => 2, 'RETR' => 3, 'INPS' => null, 'INAIL' => null, 'TFR' => null, 'CONS' => null, 'TOTALE' => null];
        for ($c = 1; $c <= 160; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if (in_array($t, ['COGNOME', 'COGNOME E NOME', 'NOME'])) $cols['COGNOME'] = $c;
            elseif ($t === 'RETRIBUZIONI')                           $cols['RETR']   = $c;
            elseif (str_contains($t, 'INPS'))                        $cols['INPS']   = $c;
            elseif (str_contains($t, 'INAIL'))                       $cols['INAIL']  = $c;
            elseif ($t === 'TFR')                                    $cols['TFR']    = $c;
            elseif (str_starts_with($t, 'CONSULENZE') || str_contains($t, 'SORVEGLIANZA')) $cols['CONS'] = $c;
            elseif ($t === 'TOTALE')                                 $cols['TOTALE'] = $c;
        }

        // fallback: se trovata colonna unica “ONERI SOCIALI”, splitta su due adiacenti
        if (is_null($cols['INPS']) || is_null($cols['INAIL'])) {
            for ($c = 1; $c <= 160; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
                if (str_contains($t, 'ONERI')) {
                    $cols['INPS'] = $c;
                    $cols['INAIL'] = $c + 1;
                    break;
                }
            }
        }
        if (is_null($cols['TFR']))    $cols['TFR']    = max($cols['RETR'], $cols['INPS'] ?? 0, $cols['INAIL'] ?? 0) + 1;
        if (is_null($cols['CONS']))   $cols['CONS']   = ($cols['TFR'] ?? 0) + 1;
        if (is_null($cols['TOTALE'])) $cols['TOTALE'] = ($cols['CONS'] ?? 0) + 1;

        $cols['IDX'] = max(1, ($cols['COGNOME'] ?? 2) - 1);
        return [$headerRow, $cols];
    }

    private function detectDistintaHeaderAndCols(Worksheet $sheet, array $tpl): array {
        // trova una riga che contenga contemporaneamente TARGA, CODICE, "TOTALI NUMERO SERVIZI" ecc.
        $headerRow = $tpl['startRow'] + 2;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 220, $tpl['endRow']); $r++) {
            $hits = 0;
            for ($c = 1; $c <= 120; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'TARGA') $hits++;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $hits++;
                if (str_contains($t, 'CONTEGGIO') && str_contains($t, 'RIPARTIZIONE')) $hits++;
                if (str_starts_with($t, 'TOTALI NUMERO SERVIZI')) $hits++;
            }
            if ($hits >= 3) {
                $headerRow = $r;
                break;
            }
        }

        // mappa delle colonne “fisse”
        $cols = ['PROGR' => 1, 'TARGA' => 2, 'CODICE' => 3, 'CONTEGGIO' => 4, 'TOTSRVANNO' => 5];
        for ($c = 1; $c <= 120; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($t === 'TARGA') $cols['TARGA'] = $c;
            elseif ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $cols['CODICE'] = $c;
            elseif (str_contains($t, 'CONTEGGIO')) $cols['CONTEGGIO'] = $c;
            elseif (str_starts_with($t, 'TOTALI NUMERO SERVIZI')) $cols['TOTSRVANNO'] = $c;
        }
        // prima colonna non determinata? fai fallback ragionevole
        if (!$cols['TOTSRVANNO']) $cols['TOTSRVANNO'] = max($cols['CODICE'], $cols['CONTEGGIO']) + 1;
        return [$headerRow, $cols];
    }

    private function findHeaderRowKm(Worksheet $sheet, int $fromRow, int $toRow): ?int {
        for ($r = $fromRow; $r <= $toRow; $r++) {
            $seenTarga = $seenCodice = $seenKmTot = false;
            for ($c = 1; $c <= 120; $c++) {
                $v = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                if ($v instanceof RichText) $v = $v->getPlainText();
                $v = mb_strtoupper(trim((string)$v));
                if ($v === '') continue;
                if (!$seenTarga   && str_contains($v, 'TARGA'))          $seenTarga   = true;
                if (!$seenCodice  && str_contains($v, 'CODICE'))         $seenCodice  = true;
                if (!$seenKmTot   && str_contains($v, 'KM. TOTALI'))     $seenKmTot   = true;
                if ($seenTarga && $seenCodice && $seenKmTot) return $r;
            }
        }
        return null;
    }

    private function findHeaderRowServizi(Worksheet $sheet, int $startFromRow, int $stopRow = null): int {
        $stopRow = $stopRow ?: ($startFromRow + 400);
        for ($r = $startFromRow; $r <= $stopRow; $r++) {
            $hasT = $hasC = $hasTot = false;
            for ($c = 1; $c <= 60; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'TARGA') $hasT = true;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $hasC = true;
                if (str_starts_with($t, 'TOTALI NUMERO SERVIZI')) $hasTot = true;
            }
            if ($hasT && $hasC && $hasTot) return $r;
        }
        return $startFromRow + 10;
    }

    private function findHeaderRowRicavi(Worksheet $sheet, int $startFromRow, int $stopRow = null): int {
        $stopRow = $stopRow ?: ($startFromRow + 100);
        for ($r = $startFromRow; $r <= $stopRow; $r++) {
            for ($c = 1; $c <= 80; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t !== '' && str_starts_with($t, 'TOTALE RICAVI')) return $r;
            }
        }
        return $startFromRow + 20;
    }

    /** Trova due righe in cui compaiono di seguito le celle "Voce", "Preventivo", "Consuntivo" (A,B,C) */
    private function findTwoHeaderRowsVocePrevCons(Worksheet $ws): array {
        $hits = [];
        // scandisco un’area ampia del template
        for ($r = 1; $r <= 200; $r++) {
            $a = trim((string)($ws->getCell("A{$r}")->getValue() ?? ''));
            $b = trim((string)($ws->getCell("B{$r}")->getValue() ?? ''));
            $c = trim((string)($ws->getCell("C{$r}")->getValue() ?? ''));
            if (
                strcasecmp($a, 'Voce') === 0 &&
                strcasecmp($b, 'Preventivo') === 0 &&
                strcasecmp($c, 'Consuntivo') === 0
            ) {
                $hits[] = $r;
                if (count($hits) === 2) break;
            }
        }

        $h1 = $hits[0] ?? 0;
        $h2 = $hits[1] ?? 0;

        // se la 2ª non è stata trovata, provo una seconda ricerca più “elastica” (in qualunque colonna vicina)
        if ($h2 === 0) {
            for ($r = max(1, $h1 + 10); $r <= 220; $r++) {
                $rowVals = [];
                for ($c = 1; $c <= 8; $c++) {
                    $rowVals[] = trim((string)($ws->getCellByColumnAndRow($c, $r)->getValue() ?? ''));
                }
                if (
                    in_array('Voce', $rowVals, true) &&
                    in_array('Preventivo', $rowVals, true) &&
                    in_array('Consuntivo', $rowVals, true)
                ) {
                    $h2 = $r;
                    break;
                }
            }
        }

        return [$h1, $h2];
    }

    /*** STYLING  ***/
    /** Applica griglia interna + outline esterno spesso su un range. */
    private function gridWithOutline(Worksheet $s, string $a1Range): void {
        $s->getStyle($a1Range)->applyFromArray([
            'borders' => [
                'inside'  => ['borderStyle' => Border::BORDER_THIN],
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM],
            ],
        ]);
    }

    /**
     * Applica gli stili coerenti a una tabella appena scritta.
     * - Header: clona lo stile dalla 1ª riga dati, wrap ON, no shrink, font minimo 10pt
     * - Totale: stile riga campione + grassetto + bordo spesso
     * - Griglia/outline, freeze pane, autosize colonne
     * - Prima colonna: wrap ON, no shrink, left/top, larghezza minima
     */
    private function applyTablePolish(
        Worksheet $s,
        int $hdrRow,        // riga header
        int $firstCol,      // prima colonna usata (indice)
        int $lastCol,       // ultima colonna usata (indice)
        int $firstDataRow,  // prima riga dati
        int $lastDataRow,   // ultima riga dati (esclusa la riga totale)
        int $totalRow,      // riga "TOTALE"
        ?int $centerPctCol = null // colonna % da centrare (opzionale)
    ): void {
        // 0) Riferimenti utili
        $colL = fn(int $i) => Coordinate::stringFromColumnIndex($i);

        // 1) Header = stile riga campione (1ª riga dati) + wrap ON + no shrink + font minimo 10
        $sampleRow = max($firstDataRow, $hdrRow + 1);
        for ($c = $firstCol; $c <= $lastCol; $c++) {
            $s->duplicateStyle($s->getStyleByColumnAndRow($c, $sampleRow), $colL($c) . $hdrRow);
        }
        $hdrRange = $colL($firstCol) . $hdrRow . ':' . $colL($lastCol) . $hdrRow;
        $s->getStyle($hdrRange)->getAlignment()
            ->setWrapText(true)
            ->setShrinkToFit(false)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $hdrFont = $s->getStyle($hdrRange)->getFont();
        if ($hdrFont->getSize() < 10) $hdrFont->setSize(10);
        // altezza riga header sufficiente per il wrap
        $hdrH = (float) $s->getRowDimension($hdrRow)->getRowHeight();
        if ($hdrH <= 0 || $hdrH < 24) $s->getRowDimension($hdrRow)->setRowHeight(24);

        // bordo spesso su header
        $this->thickBorderRow($s, $hdrRow, $firstCol, $lastCol);

        // 2) Riga TOTALE = stile riga campione + grassetto + bordo spesso
        $this->copyRowStyle($s, $sampleRow, $totalRow);
        $s->getStyle($colL($firstCol) . $totalRow . ':' . $colL($lastCol) . $totalRow)
            ->getFont()->setBold(true);
        $this->thickBorderRow($s, $totalRow, $firstCol, $lastCol);

        // 3) Griglia interna + outline esterno
        $this->gridWithOutline(
            $s,
            $colL($firstCol) . $hdrRow . ':' . $colL($lastCol) . $totalRow
        );

        // 4) Centra la colonna % (se fornita)
        if ($centerPctCol) {
            $s->getStyle($colL($centerPctCol) . $firstDataRow . ':' . $colL($centerPctCol) . $totalRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // 5) Freeze: header + colonne fisse
        $s->freezePaneByColumnAndRow($firstCol + 1, $hdrRow + 1);

        // 6) Prima colonna (etichette/“voci”): wrap ON, no shrink, left/top, min width
        $firstColRange = $colL($firstCol) . $hdrRow . ':' . $colL($firstCol) . $totalRow;
        $s->getStyle($firstColRange)->getAlignment()
            ->setWrapText(true)
            ->setShrinkToFit(false)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP);
        $dim = $s->getColumnDimension($colL($firstCol));
        $dim->setAutoSize(false);
        if (($dim->getWidth() ?? 0) < 12) $dim->setWidth(12);

        // 7) Autosize delle altre colonne (lascia intatta la 1ª che abbiamo forzato)
        //    NB: il tuo autosizeUsedColumns imposta AutoSize(true) – va bene per 2..lastCol
        if ($firstCol < $lastCol) {
            $this->autosizeUsedColumns($s, $firstCol + 1, $lastCol, 9.5);
        }
    }


    // Compat: accetta sia (sheet, topRow, bottomRow, lastColIdx)
    // sia (sheet, topRow, bottomRow, firstColIdx, lastColIdx)


    private function autosizeUsedColumns(Worksheet $sheet, int $fromColIdx, int $toColIdx, float $minWidth = 9.5): void {
        for ($c = $fromColIdx; $c <= $toColIdx; $c++) {
            $colL = Coordinate::stringFromColumnIndex($c);
            $dim  = $sheet->getColumnDimension($colL);
            // scegli una sola modalità:
            $dim->setAutoSize(true);
            // rimuovi il blocco che rilegge width e lo forza: non è affidabile
        }
    }

    // Compat: (sheet, row, lastColIdx)  oppure (sheet, row, firstColIdx, lastColIdx)
    private function thickBorderRow(Worksheet $sheet, int $row, int $a, ?int $b = null): void {
        if ($b === null) {
            $firstColIdx = 1;
            $lastColIdx  = $a;
        } else {
            $firstColIdx = $a;
            $lastColIdx  = $b;
        }
        $rng = Coordinate::stringFromColumnIndex($firstColIdx) . $row . ':' .
            Coordinate::stringFromColumnIndex($lastColIdx)  . $row;
        $sheet->getStyle($rng)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THICK);
    }

    // Compat: (sheet, topRow, bottomRow, lastColIdx)  oppure (sheet, topRow, bottomRow, firstColIdx, lastColIdx)
    private function thickOutline(
        Worksheet $sheet,
        int $topRow,
        int $bottomRow,
        int $a,
        ?int $b = null
    ): void {
        if ($b === null) {
            $firstColIdx = 1;
            $lastColIdx  = $a;
        } else {
            $firstColIdx = $a;
            $lastColIdx  = $b;
        }
        $topL = Coordinate::stringFromColumnIndex($firstColIdx) . $topRow;
        $botR = Coordinate::stringFromColumnIndex($lastColIdx)  . $bottomRow;
        $sheet->getStyle("{$topL}:{$botR}")
            ->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
    }

    /** Copia lo stile della riga $fromRow sulla riga $toRow (colonne usate). */
    private function copyRowStyle(Worksheet $s, int $fromRow, int $toRow): void {
        $maxC = Coordinate::columnIndexFromString($s->getHighestColumn());
        for ($c = 1; $c <= $maxC; $c++) {
            $s->duplicateStyle(
                $s->getStyleByColumnAndRow($c, $fromRow),
                Coordinate::stringFromColumnIndex($c) . $toRow
            );
        }
    }

    private function applyPrintSetup(Worksheet $ws): void {
        // Rileva area valida
        $lastCol = $ws->getHighestDataColumn();
        $lastRow = (int) $ws->getHighestDataRow();

        // Se il foglio è vuoto o senza dati, salta la definizione
        if (!$lastCol || $lastRow < 1) {
            Log::warning('applyPrintSetup: foglio vuoto, nessuna area di stampa impostata.', [
                'sheet' => $ws->getTitle(),
                'lastCol' => $lastCol,
                'lastRow' => $lastRow,
            ]);
            return;
        }

        // Cancella eventuale area precedente
        $ws->getPageSetup()->setPrintArea(null);

        // Imposta l’area di stampa valida
        $range = "A1:{$lastCol}{$lastRow}";
        $ws->getPageSetup()->setPrintArea($range);

        // Imposta layout
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $ws->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ws->getPageSetup()->setFitToWidth(1);
        $ws->getPageSetup()->setFitToHeight(0);

        // Margini (in pollici)
        $ws->getPageMargins()->setTop(0.5);
        $ws->getPageMargins()->setBottom(0.5);
        $ws->getPageMargins()->setLeft(0.5);
        $ws->getPageMargins()->setRight(0.5);

        // Centra orizzontalmente
        $ws->getPageSetup()->setHorizontalCentered(true);

        // Ripeti header (se esistono almeno 2 righe)
        if ($lastRow >= 2) {
            $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, min(2, $lastRow));
        }
    }

    private function styleHeader(Worksheet $ws, string $range): void {
        $ws->getStyle($range)->getFont()->setBold(true);
        $ws->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
        $ws->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function styleSezione(Worksheet $ws, string $range): void {
        $ws->getStyle($range)->getFont()->setBold(true)->setSize(11);
        $ws->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        $ws->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function box(Worksheet $ws, string $range): void {
        $ws->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function formatNum(Worksheet $ws, string $range): void {
        // formato 1.234,56
        $ws->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
    }
    private function styleRiepilogoTable(
        Worksheet $ws,
        int $headerRow,   // riga dell’intestazione "N° / Voce / PREV / CONS"
        int $lastRow,     // ultima riga scritta
        int $cNum,        // colonna N°
        int $cVoce,
        int $cPrev,
        int $cCons
    ): void {

        // Range completo da N° → Consuntivo
        $rng = $this->col($cNum) . $headerRow . ':' . $this->col($cCons) . $lastRow;

        // Bordi (outline spesso, inside sottile)
        $ws->getStyle($rng)->applyFromArray([
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_THICK],
                'inside'  => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // Header in grassetto e centrato
        $hdrRange = $this->col($cNum) . $headerRow . ':' . $this->col($cCons) . $headerRow;

        $ws->getStyle($hdrRange)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Allinea numeri a destra (solo Prev/Cons)
        $ws->getStyle(
            $this->col($cPrev) . ($headerRow + 1) . ':' . $this->col($cCons) . $lastRow
        )->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }



    /*** CACHE *****/
    function ripClearCache(int $ass, int $anno): void {
        $prefix = "rip:";
        foreach (
            [
                "convMap:ass:{$ass}:anno:{$anno}",
                "tabTot:ass:{$ass}:anno:{$anno}",
                "distinta:ass:{$ass}:anno:{$anno}",
                "kmGroups:ass:{$ass}:anno:{$anno}",
                // eventuali chiavi per automezzo: pulisci tutto l'anno
            ] as $key
        ) Cache::forget($prefix . $key);
        // Se vuoi “sparare largo”, puoi scorrere e cancellare per pattern via file-cache, ma è overkill:
        // meglio centralizzare qui tutte le chiavi usate.
    }
    private function initSpreadsheetCache(): void {
        try {
            $tmpDir = storage_path('app/phpss_cache');
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0775, true);
            }

            // Nuove versioni (Settings::setCache disponibile)
            if (method_exists(Settings::class, 'setCache')) {
                if (class_exists(PSSPhpTemp::class)) {
                    Settings::setCache(new PSSPhpTemp([
                        'memoryCacheSize' => '64MB',
                    ]));
                    Log::info('PhpSpreadsheet cache: PhpTemp (64MB).');
                    return;
                }
                if (class_exists(PSSFileCache::class)) {
                    Settings::setCache(new PSSFileCache([
                        'dir' => $tmpDir,
                    ]));
                    Log::info('PhpSpreadsheet cache: File (' . $tmpDir . ').');
                    return;
                }
            }

            // Legacy (1.24–1.28): factory statica
            if (class_exists(CachedObjectStorageFactory::class)) {
                $ok = CachedObjectStorageFactory::initialize(
                    CachedObjectStorageFactory::cache_to_phpTemp,
                    ['memoryCacheSize' => '64MB']
                );
                if (!$ok) {
                    $ok = CachedObjectStorageFactory::initialize(
                        CachedObjectStorageFactory::cache_to_discISAM,
                        ['dir' => $tmpDir]
                    );
                    if ($ok) {
                        Log::info('PhpSpreadsheet (legacy): discISAM (' . $tmpDir . ').');
                        return;
                    }
                } else {
                    Log::info('PhpSpreadsheet (legacy): phpTemp (64MB).');
                    return;
                }
            }

            Log::info('PhpSpreadsheet: nessun cell cache attivato (continuo senza).');
        } catch (Throwable $e) {
            Log::warning('PhpSpreadsheet cache init failed', ['err' => $e->getMessage()]);
        }
    }

    private function placeLogoAtRow(
        Worksheet $ws,
        array $tpl,
        ?string $logoPath,
        int $row,
        int $col = 2,
        int $heightPx = 90,           // leggermente più alto per riempire bene il banner
        int $offsetY = 25,            // alzato un po' (meno schiacciato in basso)
        float $logoRowHeightPt = 40.0, // riga logo più alta
        int $belowRows = 2,           // una riga sotto di respiro
        float $belowRowHeightPt = 40.0 // altezza più ampia del padding
    ): void {
        if (!$logoPath || !is_file($logoPath)) {
            return;
        }

        $drawing = new Drawing();
        $drawing->setWorksheet($ws);
        $drawing->setPath($logoPath);
        $drawing->setHeight($heightPx);

        $targetRow = max($tpl['startRow'], $row);
        $coord = Coordinate::stringFromColumnIndex($col) . $targetRow;
        $drawing->setCoordinates($coord);

        if ($offsetY !== 0) {
            $drawing->setOffsetY($offsetY);
        }

        // Altezza della riga del logo
        if ($logoRowHeightPt > 0) {
            $ws->getRowDimension($targetRow)->setRowHeight($logoRowHeightPt);
        }

        // Altezza delle righe sottostanti (spazio visivo extra)
        if ($belowRows > 0 && $belowRowHeightPt > 0) {
            for ($i = 1; $i <= $belowRows; $i++) {
                $ws->getRowDimension($targetRow + $i)->setRowHeight($belowRowHeightPt);
            }
        }
    }
    private function detectLabelMergedColumn(Worksheet $sheet, int $headerRow): int {
        // parole chiave che compaiono nella cella etichetta
        $candidates = ['SERVIZIO', 'UNITA', 'VOLONTARI', 'CIVILE'];

        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($col = 1; $col <= $maxCol; $col++) {
            $val = strtoupper(trim((string) $sheet->getCellByColumnAndRow($col, $headerRow)->getValue()));
            if ($val === '') {
                continue;
            }

            foreach ($candidates as $c) {
                if (strpos($val, $c) !== false) {
                    return $col;    // colonna etichetta reale del template
                }
            }
        }

        // se proprio non trova niente, butta eccezione: template rotto
        throw new \RuntimeException("Impossibile trovare la colonna etichetta alla riga {$headerRow}");
    }
}
