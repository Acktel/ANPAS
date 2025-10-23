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

    /* ===================== HANDLE ===================== */
    public function handle(): void {
        Log::info('SchedeRipartoCosti V3: START', [
            'documentoId' => $this->documentoId,
            'idAss' => $this->idAssociazione,
            'anno' => $this->anno,
        ]);

        $disk = Storage::disk('public');

        try {
            $associazione = Associazione::getById($this->idAssociazione);
            $nomeAss = (string)($associazione->Associazione ?? '');
            $slugAss = $this->slugify($nomeAss);

            // dati condivisi
            $automezzi   = Automezzo::getByAssociazione($this->idAssociazione, $this->anno)->sortBy('idAutomezzo')->values();
            $convenzioni = Convenzione::getByAnno($this->anno, $this->idAssociazione);
            $numConv     = $convenzioni->count();

            // workbook
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SCHEDE DI RIPARTO DEI COSTI');

            // loghi (manteniamo immagini dei template)
            $logos = [
                'left'  => $disk->exists('documenti/template_excel/logo_left.png')  ? $disk->path('documenti/template_excel/logo_left.png')  : null,
                'right' => $disk->exists('documenti/template_excel/logo_right.png') ? $disk->path('documenti/template_excel/logo_right.png') : null,
            ];

            /* ===================== BLOCCO 1: KM ===================== */
            $kmMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/KmPercorsi.xlsx'));
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);
            $endKm = $this->blockKm($sheet, $kmMeta, $automezzi, $convenzioni, $logos);

            /* ===================== BLOCCO 2: SERVIZI ===================== */
            $srvMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/ServiziSvolti.xlsx'), $endKm + 1);
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);
            $endSrv = $this->blockServizi($sheet, $srvMeta, $automezzi, $convenzioni, $logos);

            /* ===================== BLOCCO 3: RICAVI ===================== */
            $ricMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/Costi_Ricavi.xlsx'), $endSrv + 1);
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);
            [$endRic, $ricaviMap] = $this->blockRicavi($sheet, $ricMeta, $convenzioni, $logos);

            /* ===================== BLOCCO 4: PERSONALE AUTISTI/BARELLIERI (ore) + PREV/CONS ===================== */
            $abMeta  = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/CostiPersonale_autisti.xlsx'), $endRic + 1);
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);
            $endAB = $this->blockAutistiBarellieri($sheet, $abMeta, $convenzioni, $logos);

            /* ===================== BLOCCO 5: VOLONTARI (consuntivo = % ricavi) ===================== */
            $volMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/CostiPersonale_volontari.xlsx'), $endAB + 1);
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);
            $endVol = $this->blockVolontari($sheet, $volMeta, $convenzioni, $ricaviMap, $logos);

            /* ===================== BLOCCO 6: SERVIZIO CIVILE (ore) ===================== */
            $scMeta = $this->appendTemplate($sheet, $disk->path('documenti/template_excel/CostiPersonale_ServizioCivile.xlsx'), $endVol + 1);
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);
            $endSC = $this->blockServizioCivile($sheet, $scMeta, $convenzioni, $logos);

            /* ===================== BLOCCO 7: DISTINTA SERVIZI (Materiale Sanitario) ===================== */
            $msMeta = $this->appendTemplate(
                $sheet,
                $disk->path('documenti/template_excel/DistintaServiziSvolti.xlsx'),
                $endSC + 1
            );
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);
            $endMS = $this->blockDistintaServizi($sheet, $msMeta, $automezzi, $convenzioni, $logos);

            /* ===================== NUOVO FOGLIO: DIST.RIPARTO COSTI DIPENDENTI (A&B) ===================== */
            $sheetRip = $spreadsheet->createSheet();
            $sheetRip->setTitle('DIST.RIPARTO COSTI DIPENDENTI');

            // carico il template nel nuovo foglio
            $ripMeta = $this->appendTemplate($sheetRip, $disk->path('documenti/template_excel/DistintaCostiPersonale_Autisti.xlsx'), 1);

            // placeholder
            $this->replacePlaceholdersEverywhere($sheetRip, [
                'nome_associazione' => $nomeAss,
                'anno_riferimento'  => (string)$this->anno,
            ]);

            // compilo tabella Autisti & Barellieri
            $this->blockRipartoCostiDipendentiAB($sheetRip, $ripMeta, $convenzioni, $logos);
            $this->forceHeaderText($sheetRip, $ripMeta, $nomeAss, $this->anno);
            // mappa ID qualifica -> nome file template (adatta i nomi ai tuoi file esatti)
            $mansioniTpl = [
                5 => 'DistintaCostiPersonale_CoordAmm.xlsx',      // COORDINATORE AMMINISTRATIVO
                6 => 'DistintaCostiPersonale_CordTec.xlsx',       // COORDINATORE TECNICO
                7 => 'DistintaCostiPersonale_Amministrativi.xlsx', // IMPIEGATO AMMINISTRATIVO
                2 => 'DistintaCostiPersonale_Logistica.xlsx',     // ADDETTO LOGISTICA
                3 => 'DistintaCostiPersonale_Pulizia.xlsx',       // ADDETTO PULIZIA
                4 => 'DistintaCostiPersonale_Altro.xlsx',         // ALTRO (se non esiste, salta)
            ];

            // cursore: riga successiva all’ultimo blocco scritto sul foglio "DIST.RIPARTO..."
            $rowCursor = $sheetRip->getHighestRow() + 2;

            foreach ($mansioniTpl as $idQ => $fileName) {
                $abs = $disk->path('documenti/template_excel/' . $fileName);
                if (!file_exists($abs)) {
                    continue;
                } // se il template non c'è, passa oltre

                // appendo il template della mansione
                $tplMeta = $this->appendTemplate($sheetRip, $abs, $rowCursor);

                // placeholder nome associatione & anno nell’header del blocco
                $this->replacePlaceholdersEverywhere($sheetRip, [
                    'nome_associazione' => (string)($associazione->Associazione ?? ''),
                    'anno_riferimento'  => (string)$this->anno,
                ]);

                // popolo la tabella della mansione con le percentuali corrette
                $rowCursor = $this->blockDistintaCostiPerMansione($sheetRip, $tplMeta, $idQ, $logos) + 1;
            }

            /* ===================== NUOVO FOGLIO: DISTINTA RIPARTO AUTOMEZZI ===================== */
            $sheetAuto = $spreadsheet->createSheet();
            $sheetAuto->setTitle('DISTINTA RIPARTO AUTOMEZZI');

            $autoMeta = $this->appendTemplate(
                $sheetAuto,
                $disk->path('documenti/template_excel/CostiAutomezzi_Sanitaria.xlsx'),
                1
            );

            // placeholder header
            $this->replacePlaceholdersEverywhere($sheetAuto, [
                'nome_associazione' => (string)($associazione->Associazione ?? ''),
                'anno_riferimento'  => (string)$this->anno,
            ]);

            $this->blockRipartoAutomezzi($sheetAuto, $autoMeta, $logos);
            $this->forceHeaderText($sheetAuto, $autoMeta, $nomeAss, $this->anno);

            /* ===================== NUOVO FOGLIO: DISTINTA RIPARTO COSTI RADIO ===================== */
            $sheetRadio = $spreadsheet->createSheet();
            $sheetRadio->setTitle('DISTINTA RIPARTO COSTI RADIO');

            $radioMeta = $this->appendTemplate(
                $sheetRadio,
                $disk->path('documenti/template_excel/DistintaCosti_Radio.xlsx'),
                1
            );

            // placeholder intestazione
            $this->replacePlaceholdersEverywhere($sheetRadio, [
                'nome_associazione' => (string)($associazione->Associazione ?? ''),
                'anno_riferimento'  => (string)$this->anno,
            ]);

            // compila la tabella
            $this->blockCostiRadio($sheetRadio, $radioMeta, $logos);

            /* ===================== NUOVO FOGLIO: IMPUTAZIONE COSTI MS (CONSUMO) ===================== */
            try {
                $this->creaFoglioImputazioneSanitario(
                    $spreadsheet,
                    public_path('storage/documenti/template_excel/ImputazioneCosti_Sanitario.xlsx'),
                    $nomeAss,
                    $this->idAssociazione,
                    $this->anno
                );
            } catch (Throwable $e) {
                Log::warning('Foglio Imputazione MS: errore non bloccante', [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            /* ===================== NUOVO FOGLIO: IMPUTAZIONE COSTI OSSIGENO ===================== */
            try {
                $this->creaFoglioImputazioneOssigeno(
                    $spreadsheet,
                    $this->idAssociazione,
                    $this->anno
                );
            } catch (Throwable $e) {
                Log::warning('Foglio Imputazione OSSIGENO: errore non bloccante', [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            /* ===================== NUOVO FOGLIO: RIEPILOGO COSTI AUTO-RADIO-SAN. ===================== */
            try {
                $this->creaFoglioRiepilogoAutoRadioSan(
                    $spreadsheet,
                    $this->idAssociazione,
                    $this->anno,
                    public_path('storage/documenti/template_excel/RiepilogoAutomezzi_Sanitaria_Radio.xlsx')
                );
            } catch (Throwable $e) {
                Log::warning('Foglio RIEPILOGO COSTI AUTO-RADIO-SAN.: errore non bloccante', [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            /* ===================== FOGLI PER SINGOLO AUTOMEZZO ===================== */
            try {
                $tplAutoPath = $disk->path('documenti/template_excel/Costi_AUTO1.xlsx');

                // solo i mezzi inclusi nel riparto (oppure togli il ->where se li vuoi proprio tutti)
                $autos = DB::table('automezzi')
                    ->where('idAssociazione', $this->idAssociazione)
                    ->where('idAnno', $this->anno)
                    ->orderBy('Targa')
                    ->get(['idAutomezzo', 'Targa', 'CodiceIdentificativo']);

                foreach ($autos as $auto) {
                    $this->creaFoglioCostiPerAutomezzo(
                        $spreadsheet,
                        $tplAutoPath,
                        $nomeAss,                    // per header
                        $this->idAssociazione,
                        $this->anno,
                        $auto
                    );
                }
            } catch (Throwable $e) {
                Log::warning('Fogli per singolo automezzo: errore non bloccante', [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            /*==================== NUOVO FOGLIO: DISTINTA IMPUTAZIONE COSTI ===================== */
            try {
                $this->addDistintaImputazioneCostiSheet(
                    $spreadsheet,
                    $this->idAssociazione,
                    $this->anno
                );
            } catch (Throwable $e) {
                Log::warning('Foglio RIEPILOGO COSTI AUTO-RADIO-SAN.: errore non bloccante', [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),

                    'line' => $e->getLine(),
                ]);
            }
            /* ===================== FOGLI PER CONVENZIONE (TAB.1 + TAB.2 RIEPILOGO COSTI) ===================== */
            try {
                $tplConvenzionePath = public_path('storage/documenti/template_excel/RiepilogoDati.xlsx');

                if (is_file($tplConvenzionePath)) {
                    // 0) intestazioni
                    $nomeAss = (string) DB::table('associazioni')
                        ->where('idAssociazione', $this->idAssociazione)
                        ->value('Associazione');

                    // 1) inserisci i fogli subito dopo la DISTINTA (se non c'è, in coda)
                    $anchorTitle = 'DISTINTA IMPUTAZIONE COSTI';
                    $anchor      = $spreadsheet->getSheetByName($anchorTitle);
                    $insertAt    = $anchor ? ($spreadsheet->getIndex($anchor) + 1)
                        : $spreadsheet->getSheetCount();

                    // 2) convenzioni ordinate come in DB
                    $convenzioni = DB::table('convenzioni')
                        ->select('idConvenzione', 'Convenzione')
                        ->where('idAssociazione', $this->idAssociazione)
                        ->where('idAnno', $this->anno)
                        ->orderBy('ordinamento')
                        ->orderBy('idConvenzione')
                        ->get();

                    // 3) un foglio per ogni convenzione
                    foreach ($convenzioni as $conv) {
                        try {
                            // 3.1) crea foglio vuoto con titolo univoco
                            $title = $this->uniqueSheetTitle($spreadsheet, (string) $conv->Convenzione);
                            $ws    = new Worksheet($spreadsheet, $title);
                            $spreadsheet->addSheet($ws, $insertAt++);

                            // 3.2) incolla il template della prima tabella
                            $this->appendTemplate($ws, $tplConvenzionePath, 1);

                            // 3.3) placeholder testata (copre varie forme)
                            $this->replacePlaceholdersEverywhere($ws, [
                                'nome_associazione' => $nomeAss,
                                'ASSOCIAZIONE'      => $nomeAss,
                                'anno_riferimento'  => (string) $this->anno,
                                'ANNO'              => (string) $this->anno,
                                'nome_convenzione'  => (string) $conv->Convenzione,
                                'convenzione'       => (string) $conv->Convenzione,
                            ]);

                            // 3.4) TAB.1 — Voce / Preventivo / Consuntivo (voci 1001..1031)
                            $this->fillTabellaRiepilogoDatiSequential(
                                $ws,
                                (int) $this->idAssociazione,
                                (int) $this->anno,
                                (int) $conv->idConvenzione
                            );

                            // 3.5) TAB.2 — RIEPILOGO COSTI (sezioni 2..11) sotto la prima tabella
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
                } else {
                    Log::warning('Template RiepilogoDati.xlsx non trovato', ['path' => $tplConvenzionePath]);
                }
            } catch (Throwable $e) {
                Log::warning('Errore blocco “fogli per convenzione”', [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }




            /* ===================== FINALIZZAZIONE E SALVATAGGIO FILE ===================== */

            // stampa/print area sul foglio principale
            $lastColL = $sheet->getHighestColumn();
            $lastRow  = $sheet->getHighestRow();
            $sheet->getPageSetup()->setPrintArea("A1:{$lastColL}{$lastRow}");

            // save
            $baseFilename = sprintf('DISTINTAIMPUTAZIONECOSTI_%s_%d.xlsx', $slugAss ?: 'export', $this->anno);
            [$destRel, $finalFilename] = $this->safeSaveSpreadsheet($spreadsheet, $baseFilename, 'documenti');

            DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                'percorso_file' => $destRel,
                'nome_file'     => $finalFilename,
                'stato'         => 'ready',
                'generato_il'   => now(),
                'updated_at'    => now(),
            ]);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();
        } catch (Throwable $e) {
            Log::error('SchedeRipartoCosti V3: exception', [
                'documentoId' => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno' => $this->anno,
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            DB::table('documenti_generati')->where('id', $this->documentoId)
                ->update(['stato' => 'error', 'updated_at' => now()]);
            throw $e;
        }
    }

    /* ===================== BLOCCHI (ritornano SEMPRE l’ultima riga usata) ===================== */

    /** KM */
    private function blockKm(Worksheet $sheet, array $tpl, $automezzi, $convenzioni, array $logos): int {
        [$headerRow, $columns] = $this->detectKmHeaderAndCols($sheet, $tpl);
        $firstPairCol = $columns['KMTOT'] + 1;
        $usedPairs = max(1, $convenzioni->count());

        // loghi
        $lastUsedCol = max($columns['KMTOT'], $firstPairCol + ($usedPairs * 2) - 1);
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        // titoli convenzioni
        foreach ($convenzioni as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string)$c->Convenzione);
        }

        // dati
        $kmGrouped = AutomezzoKm::getGroupedByAutomezzoAndConvenzione($this->anno, null, $this->idAssociazione);
        $sampleRow = $headerRow + 1;
        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow;

        $totalRow = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $rows = [];

        $totKmAll = 0.0;
        $totKmByConv = [];
        foreach ($convenzioni as $c) $totKmByConv[(int)$c->idConvenzione] = 0.0;

        foreach ($automezzi as $idx => $a) {
            $kmTot = 0;
            foreach ($convenzioni as $c) {
                $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                $kmTot += (int)($kmGrouped->get($key)?->sum('KMPercorsi') ?? 0);
            }
            $totKmAll += $kmTot;

            $r = [
                ['col' => $columns['PROGR'],  'val' => 'AUTO ' . ($idx + 1)],
                ['col' => $columns['TARGA'],  'val' => (string)$a->Targa, 'type' => DataType::TYPE_STRING],
                ['col' => $columns['CODICE'], 'val' => (string)($a->CodiceIdentificativo ?? ''), 'type' => DataType::TYPE_STRING],
                ['col' => $columns['KMTOT'],  'val' => $kmTot, 'fmt' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1],
            ];

            foreach ($convenzioni as $i => $c) {
                $kmCol = $firstPairCol + ($i * 2);
                $pcCol = $kmCol + 1;
                $key   = $a->idAutomezzo . '-' . $c->idConvenzione;
                $km    = (int)($kmGrouped->get($key)?->sum('KMPercorsi') ?? 0);
                $p     = $kmTot > 0 ? ($km / $kmTot) : 0.0;

                $r[] = ['col' => $kmCol, 'val' => $km, 'fmt' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
                $r[] = ['col' => $pcCol, 'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];

                $totKmByConv[(int)$c->idConvenzione] += $km;
            }
            $rows[] = $r;
        }

        // insert prima del TOTALE
        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off = count($rows);
        $totRowNew = $totalRow + $off;

        // totale + merge A:B
        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $kmCol = $firstPairCol + ($i * 2);
            $pcCol = $kmCol + 1;
            $v     = (float)$totKmByConv[(int)$c->idConvenzione];

            $sheet->setCellValueByColumnAndRow($kmCol, $totRowNew, $v);
            $sheet->getStyleByColumnAndRow($kmCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            $p = ($i < ($usedPairs - 1) && $totKmAll > 0) ? ($v / $totKmAll) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->setCellValueByColumnAndRow($columns['KMTOT'], $totRowNew, $totKmAll);
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        // bordi & header & pulizia orizzontale
        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);
        // nascondi coppie extra
        $this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** SERVIZI */
    private function blockServizi(Worksheet $sheet, array $tpl, $automezzi, $convenzioni, array $logos): int {
        [$headerRow, $columns] = $this->detectServiziHeaderAndCols($sheet, $tpl);
        $firstPairCol = $columns['TOTSRV'] + 1;
        $usedPairs = max(1, $convenzioni->count());

        $lastUsedCol = max($columns['TOTSRV'], $firstPairCol + ($usedPairs * 2) - 1);
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        foreach ($convenzioni as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string)$c->Convenzione);
        }

        $serviziGrouped = AutomezzoServiziSvolti::getGroupedByAutomezzoAndConvenzione($this->anno, $this->idAssociazione);
        $sampleRow  = $headerRow + 1;
        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow;

        $totalRow = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $rows = [];

        $totAll = 0;
        $totByConv = [];
        foreach ($convenzioni as $c) $totByConv[(int)$c->idConvenzione] = 0;

        foreach ($automezzi as $idx => $a) {
            $totVeicolo = 0;
            foreach ($convenzioni as $c) {
                $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                $totVeicolo += (int)($serviziGrouped->get($key)?->first()->NumeroServizi ?? 0);
            }
            $totAll += $totVeicolo;

            $r = [
                ['col' => $columns['PROGR'],  'val' => 'AUTO ' . ($idx + 1)],
                ['col' => $columns['TARGA'],  'val' => (string)$a->Targa],
                ['col' => $columns['CODICE'], 'val' => (string)($a->CodiceIdentificativo ?? '')],
                ['col' => $columns['TOTSRV'], 'val' => $totVeicolo, 'fmt' => NumberFormat::FORMAT_NUMBER],
            ];

            foreach ($convenzioni as $i => $c) {
                $nCol = $firstPairCol + ($i * 2);
                $pcCol = $nCol + 1;
                $key  = $a->idAutomezzo . '-' . $c->idConvenzione;
                $n    = (int)($serviziGrouped->get($key)?->first()->NumeroServizi ?? 0);
                $p    = $totVeicolo > 0 ? ($n / $totVeicolo) : 0.0;

                $r[] = ['col' => $nCol,  'val' => $n, 'fmt' => NumberFormat::FORMAT_NUMBER];
                $r[] = ['col' => $pcCol, 'val' => $p, 'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];

                $totByConv[(int)$c->idConvenzione] += $n;
            }
            $rows[] = $r;
        }

        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off = count($rows);
        $totRowNew = $totalRow + $off;

        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $nCol = $firstPairCol + ($i * 2);
            $pcCol = $nCol + 1;
            $v = (int)$totByConv[(int)$c->idConvenzione];

            $sheet->setCellValueByColumnAndRow($nCol, $totRowNew, $v);
            $sheet->getStyleByColumnAndRow($nCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $p = ($i < ($usedPairs - 1) && $totAll > 0) ? $v / $totAll : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->setCellValueByColumnAndRow($columns['TOTSRV'], $totRowNew, $totAll);
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);
        $this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** RICAVI (ritorna [endRow, ricaviMap]) */
    private function blockRicavi(Worksheet $sheet, array $tpl, $convenzioni, array $logos): array {
        $meta = $this->locateRicaviHeader($sheet, $tpl['startRow']);
        $usedPairs = max(1, $convenzioni->count());
        $lastUsedCol = max($meta['totCol'], $meta['firstPairCol'] + ($usedPairs * 2) - 1);

        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        $ricaviMap = RapportoRicavo::mapByAssociazione($this->anno, $this->idAssociazione);
        $totRicavi = array_sum(array_map('floatval', $ricaviMap));

        // titoli convenzioni
        foreach ($convenzioni as $i => $c) {
            $col = $meta['firstPairCol'] + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $meta['convTitleRow'], (string)$c->Convenzione);
        }

        // riga dati da inserire prima del totale (la riga predisposta è meta['dataRow'])
        $rowData = [];
        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $rCol = $meta['firstPairCol'] + ($i * 2);
            $pCol = $rCol + 1;
            $imp  = (float)($ricaviMap[(int)$c->idConvenzione] ?? 0.0);
            $p    = ($i < ($usedPairs - 1) && $totRicavi > 0) ? ($imp / $totRicavi) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $rowData[] = ['col' => $rCol, 'val' => $imp, 'fmt' => '#,##0.00'];
            $rowData[] = ['col' => $pCol, 'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
        }
        $rowData[] = ['col' => $meta['totCol'], 'val' => $totRicavi, 'fmt' => '#,##0.00'];

        $styleRange = 'A' . $meta['dataRow'] . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $meta['dataRow'];
        $this->insertRowsBeforeTotal($sheet, $meta['dataRow'], [$rowData], $styleRange);

        // riga “TOTALE RICAVI” scende di 1
        $totRowNew = $meta['dataRow'] + 1;
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE RICAVI');

        $this->thickBorderRow($sheet, $meta['headerRow'], $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,         $lastUsedCol);
        $sheet->getStyle('A' . ($meta['dataRow']) . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . ($meta['dataRow']))->getFont()->setBold(true);
        $this->thickOutline($sheet, $meta['headerRow'], $totRowNew, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);
        // pulizia orizzontale
        $this->hideUnusedConventionColumns($sheet, $meta['headerRow'], $meta['firstPairCol'], $usedPairs);

        return [max($totRowNew, $tpl['endRow'] + 1), $ricaviMap];
    }

    /** AUTISTI & BARELLIERI (tabella ore + prev/cons) */
    private function blockAutistiBarellieri(Worksheet $sheet, array $tpl, $convenzioni, array $logos): int {
        [$headerRow, $cols] = $this->detectABHeaderAndCols($sheet, $tpl);
        $firstPairCol = $cols['TOTORE'] + 1;
        $usedPairs = max(1, $convenzioni->count());

        $lastUsedCol = max($cols['TOTORE'], $firstPairCol + ($usedPairs * 2) - 1);
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        // intestazioni convenzioni
        foreach ($convenzioni as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string)$c->Convenzione);
        }

        // PREVENTIVO/CONSUNTIVO subito sotto header
        $prevCons = RipartizioneCostiService::personalePrevConsPerConvenzione($this->idAssociazione, $this->anno);
        $prevByConv = $prevCons['preventivo'] ?? [];
        $consByConv = $prevCons['consuntivo'] ?? [];
        $totPrev = array_sum(array_map('floatval', $prevByConv));
        $totCons = array_sum(array_map('floatval', $consByConv));

        $styleRow = 'A' . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . ($headerRow + 1);
        $rowsPC = [];

        // PREVENTIVO
        $rPrev = [['col' => max(1, $cols['NOME'] ?? 1), 'val' => 'PREVENTIVO ' . $this->anno . ' - COSTO PERSONALE']];
        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $impCol = $firstPairCol + ($i * 2);
            $pcCol  = $impCol + 1;
            $imp    = (float)($prevByConv[(int)$c->idConvenzione] ?? 0.0);
            $p      = ($i < ($usedPairs - 1) && $totPrev > 0) ? ($imp / $totPrev) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $rPrev[] = ['col' => $impCol, 'val' => $imp, 'fmt' => '#,##0.00'];
            $rPrev[] = ['col' => $pcCol,  'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
        }
        $rowsPC[] = $rPrev;

        // CONSUNTIVO
        $rCons = [['col' => max(1, $cols['NOME'] ?? 1), 'val' => 'CONSUNTIVO ' . $this->anno . ' - COSTO PERSONALE']];
        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $impCol = $firstPairCol + ($i * 2);
            $pcCol  = $impCol + 1;
            $imp    = (float)($consByConv[(int)$c->idConvenzione] ?? 0.0);
            $p      = ($i < ($usedPairs - 1) && $totCons > 0) ? ($imp / $totCons) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $rCons[] = ['col' => $impCol, 'val' => $imp, 'fmt' => '#,##0.00'];
            $rCons[] = ['col' => $pcCol,  'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
        }
        $rowsPC[] = $rCons;
        $anchorRow = $headerRow + 1;
        $this->insertRowsBeforeTotal($sheet, $anchorRow, $rowsPC, $styleRow);
        $this->thickBorderRow($sheet, $anchorRow,     $lastUsedCol);
        $this->thickBorderRow($sheet, $anchorRow + 1, $lastUsedCol);
        $sheet->getStyle('A' . $anchorRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . ($anchorRow + 1))->getFont()->setBold(true);

        // ORE per dipendente
        $dip = Dipendente::getAutistiEBarellieri($this->anno, $this->idAssociazione);
        $rip = RipartizionePersonale::getAll($this->anno, null, $this->idAssociazione)->groupBy('idDipendente');

        $totalRow = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $sampleRow = $headerRow + 1;
        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow;

        $rows = [];
        $totOreAll = 0.0;
        $totByConv = [];
        foreach ($convenzioni as $c) $totByConv[(int)$c->idConvenzione] = 0.0;

        foreach ($dip as $i => $d) {
            $serv = $rip->get($d->idDipendente) ?? collect();
            $oreTot = (float)$serv->sum('OreServizio');
            $totOreAll += $oreTot;

            $r = [
                ['col' => $cols['IDX'],  'val' => 'DIPENDENTE N. ' . ($i + 1)],
                ['col' => $cols['NOME'] ?? 2, 'val' => trim(($d->DipendenteCognome ?? '') . ' ' . ($d->DipendenteNome ?? ''))],
                ['col' => $cols['TOTORE'], 'val' => $oreTot, 'fmt' => NumberFormat::FORMAT_NUMBER],
            ];

            foreach ($convenzioni as $j => $c) {
                $impCol = $firstPairCol + ($j * 2);
                $pcCol  = $impCol + 1;
                $ore    = (float)($serv->firstWhere('idConvenzione', $c->idConvenzione)->OreServizio ?? 0);
                $p      = $oreTot > 0 ? ($ore / $oreTot) : 0.0;

                $r[] = ['col' => $impCol, 'val' => $ore, 'fmt' => NumberFormat::FORMAT_NUMBER];
                $r[] = ['col' => $pcCol,  'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];

                $totByConv[(int)$c->idConvenzione] += $ore;
            }
            $rows[] = $r;
        }

        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off = count($rows);
        $totRowNew = $totalRow + $off;

        // totale + merge A:B
        $sheet->setCellValueByColumnAndRow($cols['NOME'] ?? 1, $totRowNew, 'TOTALE'); // label prima del merge
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');

        $sheet->setCellValueByColumnAndRow($cols['TOTORE'], $totRowNew, $totOreAll);
        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $impCol = $firstPairCol + ($i * 2);
            $pcCol  = $impCol + 1;
            $ore    = (float)$totByConv[(int)$c->idConvenzione];

            $sheet->setCellValueByColumnAndRow($impCol, $totRowNew, $ore);
            $sheet->getStyleByColumnAndRow($impCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $p = ($i < ($usedPairs - 1) && $totOreAll > 0) ? ($ore / $totOreAll) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);
        $this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** VOLONTARI */
    private function blockVolontari(Worksheet $sheet, array $tpl, $convenzioni, array $ricaviMap, array $logos): int {
        [$headerRow, $firstPairCol] = $this->detectVolontariHeader($sheet, $tpl);
        $usedPairs = max(1, $convenzioni->count());
        $lastUsedCol = $firstPairCol + ($usedPairs * 2) - 1;

        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        foreach ($convenzioni as $i => $c) {
            $base = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($base, $headerRow - 1, (string)$c->Convenzione);
        }

        $totRicavi = array_sum(array_map('floatval', $ricaviMap));
        $rowData = [['col' => 1, 'val' => 'Totale volontari']];
        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $base = $firstPairCol + ($i * 2);
            $val  = (float)($ricaviMap[(int)$c->idConvenzione] ?? 0.0);
            $p    = ($i < ($usedPairs - 1) && $totRicavi > 0) ? ($val / $totRicavi) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $rowData[] = ['col' => $base,     'val' => $val, 'fmt' => '#,##0.00'];
            $rowData[] = ['col' => $base + 1, 'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
        }

        $sampleRow  = $headerRow + 1;
        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow;

        // nel template la riga sample è placeholder: uso insertRowsBeforeTotal con 1 riga
        $this->insertRowsBeforeTotal($sheet, $sampleRow, [$rowData], $styleRange);

        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $sampleRow,  $lastUsedCol);
        $sheet->getStyle('A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $sampleRow, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);
        // pulizia orizzontale
        $this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);

        return max($sampleRow, $tpl['endRow'] + 1);
    }

    /** SERVIZIO CIVILE */
    private function blockServizioCivile(Worksheet $sheet, array $tpl, $convenzioni, array $logos): int {
        [$headerRow, $firstPairCol, $labelCol] = $this->detectServizioCivileHeader($sheet, $tpl);
        $usedPairs  = max(1, $convenzioni->count());
        $lastUsedCol = $firstPairCol + ($usedPairs * 2) - 1;

        // loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        // intestazioni convenzioni (riga sopra l’header delle coppie)
        foreach ($convenzioni as $i => $c) {
            $base = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($base, $headerRow - 1, (string)$c->Convenzione);
        }

        // ore per convenzione (id fittizio servizio civile)
        $rowsSC = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $this->idAssociazione)
            ->where('c.idAnno', $this->anno)
            ->where('ds.idDipendente', RipartizioneServizioCivile::ID_SERVIZIO_CIVILE)
            ->whereIn('ds.idConvenzione', $convenzioni->pluck('idConvenzione')->all())
            ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as ore'))
            ->groupBy('ds.idConvenzione')
            ->get();

        $oreByConv = array_fill_keys($convenzioni->pluck('idConvenzione')->map(fn($v) => (int)$v)->all(), 0.0);
        $totOre = 0.0;
        foreach ($rowsSC as $r) {
            $oreByConv[(int)$r->idConvenzione] = (float)$r->ore;
            $totOre += (float)$r->ore;
        }

        // riga dati (sotto l’header)
        $dataRow   = $headerRow + 1;
        $lastColL  = Coordinate::stringFromColumnIndex($lastUsedCol);
        $styleRange = 'A' . $dataRow . ':' . $lastColL . $dataRow;

        // scrivo la label nella colonna di "PERSONALE" (non in A fissa!)
        $rowData = [['col' => $labelCol, 'val' => 'Totale servizio civile']];

        // coppie UNITA’ / %
        $pairSum = 0.0;
        foreach ($convenzioni as $i => $c) {
            $base = $firstPairCol + ($i * 2);
            $ore  = (float)$oreByConv[(int)$c->idConvenzione];
            $p    = ($i < ($usedPairs - 1) && $totOre > 0) ? ($ore / $totOre) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $rowData[] = ['col' => $base,     'val' => $ore, 'fmt' => NumberFormat::FORMAT_NUMBER];  // UNITA'
            $rowData[] = ['col' => $base + 1, 'val' => $p,   'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER]; // %
        }

        // nel template la riga dataRow è il placeholder → duplica stile e scrivi
        $this->insertRowsBeforeTotal($sheet, $dataRow, [$rowData], $styleRange);

        // enfasi + bordo spesso a tutto il blocco + autosize anti-######
        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $dataRow,    $lastUsedCol);
        $sheet->getStyle('A' . $dataRow . ':' . $lastColL . $dataRow)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $dataRow, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        // nascondi coppie non utilizzate dal template
        $this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);

        return max($dataRow, $tpl['endRow'] + 1);
    }

    /** DISTINTA SERVIZI (Materiale Sanitario)*/
    private function blockDistintaServizi(Worksheet $sheet, array $tpl, $automezzi, $convenzioni, array $logos): int {
        // 0) Prendo i dati già pronti dal model
        $dati = RipartizioneMaterialeSanitario::getRipartizione($this->idAssociazione, $this->anno);
        $conv   = collect($dati['convenzioni']);   // elenco convenzioni
        $righe  = collect($dati['righe']);         // righe per automezzo + 'totale'
        $totInc = (int)($dati['totale_inclusi'] ?? 0);

        // 1) Header & colonne
        [$headerRow, $cols] = $this->detectDistintaHeaderAndCols($sheet, $tpl);
        $firstPairCol = $cols['TOTSRVANNO'] + 1;
        $usedPairs    = max(1, $conv->count());
        $lastUsedCol  = max($cols['TOTSRVANNO'], $firstPairCol + ($usedPairs * 2) - 1);

        // 2) Loghi & titoli convenzioni
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);
        foreach ($conv as $i => $c) {
            $col = $firstPairCol + ($i * 2);
            $sheet->setCellValueByColumnAndRow($col, $headerRow - 1, (string)$c->Convenzione);
        }

        // 3) Stile riga campione e riga 'TOTALE' predisposta
        $sampleRow  = $headerRow + 1;
        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow;
        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];

        // 4) Costruisco righe per ogni automezzo (escludo la riga 'totale' del model)
        $rows = [];
        $idxAuto = 0;
        foreach ($righe as $key => $r) {
            if (!empty($r['is_totale'])) continue; // salta la riga totale del model
            $idxAuto++;

            $isIncluded  = !empty($r['incluso_riparto']);
            $totVeicolo  = (int)($r['totale'] ?? 0);
            $valori      = $r['valori'] ?? [];

            $row = [
                ['col' => $cols['PROGR'],      'val' => 'AUTO ' . $idxAuto],
                ['col' => $cols['TARGA'],      'val' => (string)($r['Targa'] ?? '')],
                ['col' => $cols['CODICE'],     'val' => (string)($r['CodiceIdentificativo'] ?? '')],
                ['col' => $cols['CONTEGGIO'],  'val' => $isIncluded ? 'SI' : 'NO', 'type' => DataType::TYPE_STRING],
                ['col' => $cols['TOTSRVANNO'], 'val' => $totVeicolo, 'fmt' => NumberFormat::FORMAT_NUMBER],
            ];

            foreach ($conv as $i => $c) {
                $nCol  = $firstPairCol + ($i * 2);
                $pcCol = $nCol + 1;
                $n     = (int)($valori[$c->idConvenzione] ?? 0);
                $p     = $totVeicolo > 0 ? ($n / $totVeicolo) : 0.0;

                $row[] = ['col' => $nCol,  'val' => $n, 'fmt' => NumberFormat::FORMAT_NUMBER];
                $row[] = ['col' => $pcCol, 'val' => $p, 'fmt' => '0.00%', 'align' => Alignment::HORIZONTAL_CENTER];
            }
            $rows[] = $row;
        }

        // 5) Inserisci righe
        $this->insertRowsBeforeTotal($sheet, $totalRow, $rows, $styleRange);
        $off       = count($rows);
        $totRowNew = $totalRow + $off;

        // 6) Riga TOTALE (usa la riga 'totale' del model: solo inclusi)
        $rigaTotModel = $righe->get('totale');
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');
        $sheet->setCellValueByColumnAndRow($cols['TOTSRVANNO'], $totRowNew, $totInc);

        $pairSum = 0.0;
        foreach ($conv as $i => $c) {
            $nCol  = $firstPairCol + ($i * 2);
            $pcCol = $nCol + 1;
            $v     = (int)($rigaTotModel['valori'][$c->idConvenzione] ?? 0);

            $sheet->setCellValueByColumnAndRow($nCol, $totRowNew, $v);
            $sheet->getStyleByColumnAndRow($nCol, $totRowNew)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $p = ($i < ($usedPairs - 1) && $totInc > 0) ? ($v / $totInc) : max(0.0, 1.0 - $pairSum);
            if ($i < ($usedPairs - 1)) $pairSum += $p;

            $sheet->setCellValueByColumnAndRow($pcCol, $totRowNew, $p);
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyleByColumnAndRow($pcCol, $totRowNew)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // 7) Stili & pulizia
        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
        $this->hideUnusedConventionColumns($sheet, $headerRow, $firstPairCol, $usedPairs);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /** DIST.RIPARTO COSTI DIPENDENTI – sezione Autisti & Barellieri */
    private function blockRipartoCostiDipendentiAB(
        Worksheet $sheet,
        array $tpl,
        $convenzioni,
        array $logos
    ): int {
        // header/colonne
        [$headerRow, $cols] = $this->detectRipartoABHeaderAndCols($sheet, $tpl);
        $firstPairCol = $cols['TOTALE'] + 1;                 // 1 col per convenzione (importo)
        $usedPairs    = max(1, $convenzioni->count());
        $lastUsedCol  = max($cols['TOTALE'], $firstPairCol + ($usedPairs - 1));

        // loghi + intestazioni convenzioni
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);
        foreach ($convenzioni as $i => $c) {
            $col = $firstPairCol + $i;
            $sheet->setCellValueExplicitByColumnAndRow($col, $headerRow, (string)$c->Convenzione, DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($col, $headerRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        }

        // dati base
        $dip  = Dipendente::getAutistiEBarellieri($this->anno, $this->idAssociazione);
        $oreG = RipartizionePersonale::getAll($this->anno, null, $this->idAssociazione)->groupBy('idDipendente');

        // costi: base + diretti (INPS/INAIL separati)
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

        // % di mansione per "AUTISTA SOCCORRITORE" (ID fisso = 1)
        $pctAB = DB::table('costi_personale_mansioni')
            ->where('idAnno', $this->anno)
            ->where('idQualifica', Dipendente::Q_AUTISTA_ID) // <<< ID fisso
            ->pluck('percentuale', 'idDipendente')
            ->map(fn($v) => (float)$v)
            ->toArray();

        // numero qualifiche per dipendente (serve per decidere se applicare il coeff)
        $qCount = DB::table('dipendenti_qualifiche')
            ->whereIn('idDipendente', $dip->pluck('idDipendente')->all())
            ->select('idDipendente', DB::raw('COUNT(*) as n'))
            ->groupBy('idDipendente')
            ->pluck('n', 'idDipendente')
            ->toArray();

        // righe
        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $dataRow    = $headerRow + 1; // usa riga campione → nessuna riga vuota
        $styleRange = 'A' . $dataRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $dataRow;

        $rows = []; // dalla 2ª riga in poi
        $totRetr = $totInps = $totInail = $totTfr = $totCons = $totAll = 0.0;
        $totByConvCents = [];
        foreach ($convenzioni as $c) $totByConvCents[(int)$c->idConvenzione] = 0;

        $i = 0;
        foreach ($dip as $d) {
            $cp = $costi->get($d->idDipendente);
            if (!$cp) {
                $i++;
                continue;
            }

            // coefficiente: applica % solo se il dipendente ha >1 qualifica (come nel web)
            $hasMultiQual = ((int)($qCount[$d->idDipendente] ?? 1)) > 1;
            $coeff = $hasMultiQual ? max(0.0, (($pctAB[$d->idDipendente] ?? 0.0) / 100.0)) : 1.0;

            // quote di costo (già con diretti) * coeff A&B
            $retr  = (float)$cp->Retribuzioni     * $coeff;
            $inps  = (float)$cp->OneriSocialiInps * $coeff;
            $inail = (float)$cp->OneriSocialiInail * $coeff;
            $tfr   = (float)$cp->TFR              * $coeff;
            $cons  = (float)$cp->Consulenze       * $coeff;

            $totEuro  = $retr + $inps + $inail + $tfr + $cons;
            $totCents = (int)round($totEuro * 100, 0, PHP_ROUND_HALF_UP);

            $totRetr += $retr;
            $totInps += $inps;
            $totInail += $inail;
            $totTfr  += $tfr;
            $totCons += $cons;
            $totAll   += $totEuro;

            // celle fisse
            $cells = [
                ['col' => $cols['IDX'],     'val' => ($i + 1)],
                ['col' => $cols['COGNOME'], 'val' => trim(($d->DipendenteCognome ?? '') . ' ' . ($d->DipendenteNome ?? ''))],
                ['col' => $cols['RETR'],    'val' => round($retr, 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['INPS'],    'val' => round($inps, 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['INAIL'],   'val' => round($inail, 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['TFR'],     'val' => round($tfr,  2),  'fmt' => '#,##0.00'],
                ['col' => $cols['CONS'],    'val' => round($cons, 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['TOTALE'],  'val' => round($totEuro, 2), 'fmt' => '#,##0.00'],
            ];

            // riparto in CENTESIMI (con ridistribuzione residui)
            $oreRec = $oreG->get($d->idDipendente, collect());
            $oreTot = (float)$oreRec->sum('OreServizio');

            $prov = [];
            $rem = [];
            $sumProv = 0;
            if ($totCents > 0 && $oreTot > 0) {
                foreach ($convenzioni as $c) {
                    $ore = (float)($oreRec->firstWhere('idConvenzione', $c->idConvenzione)->OreServizio ?? 0);
                    $quota = ($totCents * $ore) / $oreTot;
                    $p = (int)floor($quota);
                    $prov[$c->idConvenzione] = $p;
                    $rem[$c->idConvenzione]  = $quota - $p;
                    $sumProv += $p;
                }
                $diff = $totCents - $sumProv;
                if ($diff > 0) {
                    uksort($rem, function ($a, $b) use ($rem) {
                        if ($rem[$a] == $rem[$b]) return $a <=> $b;
                        return ($rem[$a] > $rem[$b]) ? -1 : 1;
                    });
                    foreach (array_keys($rem) as $idC) {
                        if ($diff <= 0) break;
                        $prov[$idC] += 1;
                        $diff--;
                    }
                }
            } else {
                foreach ($convenzioni as $c) $prov[$c->idConvenzione] = 0;
            }

            // scrittura importi per convenzione (in €) + accumulo totali in centesimi
            foreach ($convenzioni as $j => $c) {
                $col = $firstPairCol + $j;
                $valEuro = round(($prov[$c->idConvenzione] ?? 0) / 100, 2);
                $cells[] = ['col' => $col, 'val' => $valEuro, 'fmt' => '#,##0.00'];
                $totByConvCents[(int)$c->idConvenzione] += ($prov[$c->idConvenzione] ?? 0);
            }

            // prima riga: compila la riga campione; poi accumula
            if ($i === 0) {
                foreach ($cells as $cell) {
                    $sheet->setCellValueByColumnAndRow($cell['col'], $dataRow, $cell['val']);
                    if (!empty($cell['fmt'])) {
                        $sheet->getStyleByColumnAndRow($cell['col'], $dataRow)
                            ->getNumberFormat()->setFormatCode($cell['fmt']);
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

        // TOTALE (INPS/INAIL separati)
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
        // totali convenzione (da centesimi → €)
        foreach ($convenzioni as $i => $c) {
            $col = $firstPairCol + $i;
            $sheet->setCellValueByColumnAndRow($col, $totRowNew, round(($totByConvCents[(int)$c->idConvenzione] ?? 0) / 100, 2));
            $sheet->getStyleByColumnAndRow($col, $totRowNew)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // stili
        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        // nascondi eventuali colonne extra del template
        for ($i = $usedPairs; $i < 200; $i++) {
            $col = $firstPairCol + $i;
            $colL = Coordinate::stringFromColumnIndex($col);
            if ($sheet->getColumnDimension($colL)->getWidth() === -1) break;
            $sheet->getColumnDimension($colL)->setVisible(false);
        }

        $this->forceHeaderText($sheet, $tpl, session('nome_associazione') ?? 'ASSOCIAZIONE', $this->anno);
        return max($totRowNew, $tpl['endRow'] + $off);
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
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

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
        $styleRange = 'A' . $dataRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $dataRow;

        $rows = []; // dalla 2ª riga in poi
        $totRetr = $totInps = $totInail = $totTfr = $totCons = $totAll = 0.0;

        $writeRow = function (int $idx, $d) use ($costi, $pctByDip, $cols) {
            $cp = $costi->get($d->idDipendente);
            if (!$cp) return [];

            // coefficiente: se il dip ha più qualifiche, prendi la % salvata per questa qualifica; altrimenti 1.0
            $numQ = DB::table('dipendenti_qualifiche')->where('idDipendente', $d->idDipendente)->count();
            $coeff = ($numQ > 1) ? (float)($pctByDip[$d->idDipendente] ?? 0) / 100.0 : 1.0;
            if ($coeff <= 0) $coeff = 0.0;

            $retr  = (float)$cp->Retribuzioni     * $coeff;
            $inps  = (float)$cp->OneriSocialiInps * $coeff;
            $inail = (float)$cp->OneriSocialiInail * $coeff;
            $tfr   = (float)$cp->TFR              * $coeff;
            $cons  = (float)$cp->Consulenze       * $coeff;

            $tot = $retr + $inps + $inail + $tfr + $cons;

            return [
                ['col' => $cols['IDX'],     'val' => ($idx + 1)],
                ['col' => $cols['COGNOME'], 'val' => trim(($d->DipendenteCognome ?? '') . ' ' . ($d->DipendenteNome ?? ''))],
                ['col' => $cols['RETR'],    'val' => round($retr, 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['INPS'],    'val' => round($inps, 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['INAIL'],   'val' => round($inail, 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['TFR'],     'val' => round($tfr, 2),   'fmt' => '#,##0.00'],
                ['col' => $cols['CONS'],    'val' => round($cons, 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['TOTALE'],  'val' => round($tot, 2),   'fmt' => '#,##0.00'],
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
            $totRetr += $cells[2]['val']; // RETR
            $totInps += $cells[3]['val']; // INPS
            $totInail += $cells[4]['val']; // INAIL
            $totTfr  += $cells[5]['val']; // TFR
            $totCons += $cells[6]['val']; // CONS
            $totAll  += $cells[7]['val']; // TOTALE

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

        // 6) Stili
        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
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
        $lastUsedCol = max($cols); // nessuna “TOTALE” di colonna

        // Loghi
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        // Dati base
        $automezzi = Automezzo::getByAssociazione($this->idAssociazione, $this->anno)
            ->sortBy('idAutomezzo')->values();

        $costi = CostiAutomezzi::getAllByAnno($this->anno)
            ->whereIn('idAutomezzo', $automezzi->pluck('idAutomezzo')->all())
            ->keyBy('idAutomezzo');

        // Ancore
        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $sampleRow  = $headerRow + 1;
        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow;

        // Accumulatori totali (per la riga TOTALE in fondo – riga, non colonna)
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
                ['col' => $cols['IDX'],    'val' => 'AUTO ' . (++$i)],
                ['col' => $cols['TARGA'],  'val' => (string)$a->Targa],
                ['col' => $cols['CODICE'], 'val' => (string)($a->CodiceIdentificativo ?? '')],
                ['col' => $cols['LEASING'], 'val' => round($val['LEASING'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['ASSIC'],  'val' => round($val['ASSIC'], 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['MAN_ORD'], 'val' => round($val['MAN_ORD'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['MAN_STRA'], 'val' => round($val['MAN_STRA'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['RIMB_ASS'], 'val' => round($val['RIMB_ASS'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['PULIZIA'], 'val' => round($val['PULIZIA'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['CARB'],   'val' => round($val['CARB'], 2),   'fmt' => '#,##0.00'],
                ['col' => $cols['ADDITIVI'], 'val' => round($val['ADDITIVI'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['RIMB_UTF'], 'val' => round($val['RIMB_UTF'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['INTERESSI'], 'val' => round($val['INTERESSI'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['ALTRI'],  'val' => round($val['ALTRI'], 2),  'fmt' => '#,##0.00'],
                ['col' => $cols['MAN_SAN'], 'val' => round($val['MAN_SAN'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['LEAS_SAN'], 'val' => round($val['LEAS_SAN'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['AMM_MEZZI'], 'val' => round($val['AMM_MEZZI'], 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['AMM_SAN'], 'val' => round($val['AMM_SAN'], 2), 'fmt' => '#,##0.00'],
            ];

            if ($i === 1) {
                foreach ($cells as $cell) {
                    $sheet->setCellValueByColumnAndRow($cell['col'], $sampleRow, $cell['val']);
                    if (!empty($cell['fmt'])) {
                        $sheet->getStyleByColumnAndRow($cell['col'], $sampleRow)->getNumberFormat()->setFormatCode($cell['fmt']);
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

        // Stili
        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle('A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew)->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

        return max($totRowNew, $tpl['endRow'] + $off);
    }

    /**
     * DISTINTA RIPARTO COSTI RADIO
     * Compila la tabella dividendo in parti uguali i totali di costi_radio
     * fra gli automezzi dell’associazione/anno.
     */
    private function blockCostiRadio(Worksheet $sheet, array $tpl, array $logos): int {
        // 1) Header & mappa colonne del template
        [$headerRow, $cols] = $this->detectCostiRadioHeaderAndCols($sheet, $tpl);
        $lastUsedCol = max($cols);

        // 2) Loghi (rispetta template)
        $this->insertLogosAtRow($sheet, $logos, $tpl['startRow'] + 1, $lastUsedCol);

        // 3) Dati base
        $automezzi = Automezzo::getByAssociazione($this->idAssociazione, $this->anno)->values();
        $numAut    = max(count($automezzi), 1);

        $tot = DB::table('costi_radio')
            ->where('idAnno', $this->anno)
            ->where('idAssociazione', $this->idAssociazione)
            ->first();

        // normalizza totali (0 se mancanti)
        $T_MANT = (float)($tot->ManutenzioneApparatiRadio   ?? 0);
        $T_MONT = (float)($tot->MontaggioSmontaggioRadio118 ?? 0);
        $T_LOCA = (float)($tot->LocazionePonteRadio         ?? 0);
        $T_AMMO = (float)($tot->AmmortamentoImpiantiRadio   ?? 0);

        // quota per automezzo (divisione equa)
        $Q_MANT = $T_MANT / $numAut;
        $Q_MONT = $T_MONT / $numAut;
        $Q_LOCA = $T_LOCA / $numAut;
        $Q_AMMO = $T_AMMO / $numAut;

        // 4) Dove scrivere
        $totalRow   = $this->findRowByLabel($sheet, 'TOTALE', $headerRow + 1, $tpl['endRow']) ?? $tpl['endRow'];
        $sampleRow  = $headerRow + 1; // prima riga utile sotto header
        $styleRange = 'A' . $sampleRow . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $sampleRow;

        // 5) Righe automezzi
        $rows = [];
        $i = 0;
        foreach ($automezzi as $a) {
            $cells = [
                ['col' => $cols['IDX'],    'val' => 'AUTO ' . (++$i)],
                ['col' => $cols['TARGA'],  'val' => (string)$a->Targa],
                ['col' => $cols['MANT'],   'val' => round($Q_MANT, 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['MONT'],   'val' => round($Q_MONT, 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['LOCA'],   'val' => round($Q_LOCA, 2), 'fmt' => '#,##0.00'],
                ['col' => $cols['AMMO'],   'val' => round($Q_AMMO, 2), 'fmt' => '#,##0.00'],
            ];

            if ($i === 1) {
                // prima riga: usa la riga campione già presente
                foreach ($cells as $c) {
                    $sheet->setCellValueByColumnAndRow($c['col'], $sampleRow, $c['val']);
                    if (!empty($c['fmt'])) {
                        $sheet->getStyleByColumnAndRow($c['col'], $sampleRow)
                            ->getNumberFormat()->setFormatCode($c['fmt']);
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

        // 6) Riga TOTALE (somma dei totali di bilancio)
        $this->mergeTotalAB($sheet, $totRowNew, 'TOTALE');
        $sheet->setCellValueByColumnAndRow($cols['MANT'], $totRowNew, round($T_MANT, 2));
        $sheet->setCellValueByColumnAndRow($cols['MONT'], $totRowNew, round($T_MONT, 2));
        $sheet->setCellValueByColumnAndRow($cols['LOCA'], $totRowNew, round($T_LOCA, 2));
        $sheet->setCellValueByColumnAndRow($cols['AMMO'], $totRowNew, round($T_AMMO, 2));
        foreach ([$cols['MANT'], $cols['MONT'], $cols['LOCA'], $cols['AMMO']] as $cc) {
            $sheet->getStyleByColumnAndRow($cc, $totRowNew)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // 7) Scrivi i box in alto (se presenti nel template)
        $this->setValueRightOfLabel($sheet, 'NUMERO TOTALE AUTOMEZZI', $numAut);
        $this->setValueRightOfLabel($sheet, 'TOTALI A BILANCIO', round($T_MANT + $T_MONT + $T_LOCA + $T_AMMO, 2), '#,##0.00');

        // 8) Stili
        $this->thickBorderRow($sheet, $headerRow, $lastUsedCol);
        $this->thickBorderRow($sheet, $totRowNew,  $lastUsedCol);
        $sheet->getStyle(
            'A' . $totRowNew . ':' . Coordinate::stringFromColumnIndex($lastUsedCol) . $totRowNew
        )->getFont()->setBold(true);
        $this->thickOutline($sheet, $headerRow, $totRowNew, $lastUsedCol);
        $this->autosizeUsedColumns($sheet, 1, $lastUsedCol);

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
        $headerRow = $tpl['startRow'] + 1;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 120, $tpl['endRow']); $r++) {
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
        $headerRow = $tpl['startRow'] + 1;
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
        $headerRow = $tpl['startRow'] + 1;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 60, $tpl['endRow']); $r++) {
            for ($c = 1; $c <= 80; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'PERSONALE' || $t === '%') {
                    $headerRow = $r;
                    break 2;
                }
            }
        }
        // prima coppia disponibile
        $firstPairCol = 2;
        for ($c = 2; $c <= 80; $c++) {
            $v  = (string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue();
            $v2 = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRow)->getValue();
            if ($v !== '' || $v2 !== '') {
                $firstPairCol = $c;
                break;
            }
        }
        return [$headerRow, $firstPairCol];
    }

    private function detectServizioCivileHeader(Worksheet $sheet, array $tpl): array {
        // trova la riga header (quella dove compaiono PERSONALE | UNITA' DI SERVIZIO | %)
        $headerRow = $tpl['startRow'] + 1;
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
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
        $sheet->setCellValue("B{$row}", null);
    }

    /**
     * Nasconde tutte le colonne (a coppie VAL/%)
     * oltre le convenzioni utilizzate in header.
     */
    private function hideUnusedConventionColumns(
        Worksheet $sheet,
        int $headerRow,
        int $firstPairCol,
        int $usedPairs,
        int $maxScan = 220
    ): void {
        $maxPairs = 0;
        for ($c = $firstPairCol; $c <= $firstPairCol + $maxScan; $c += 2) {
            $l = (string)$sheet->getCellByColumnAndRow($c,     $headerRow)->getValue();
            $r = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRow)->getValue();
            $ttl = (string)$sheet->getCellByColumnAndRow($c, $headerRow - 1)->getValue();
            if ($l === '' && $r === '' && $ttl === '') break;
            $maxPairs++;
        }
        if ($maxPairs < $usedPairs) $maxPairs = $usedPairs;

        for ($i = $usedPairs; $i < $maxPairs; $i++) {
            $col = $firstPairCol + ($i * 2);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setVisible(false);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col + 1))->setVisible(false);
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

    /**
     * Riconosce header/colonne del template costi automezzi.
     * Tiene conto di celle header unite (controlla anche la riga sotto)
     * e dà priorità alle voci “sanitarie” per evitare falsi match.
     */
    private function detectAutomezziHeaderAndCols(Worksheet $sheet, array $tpl): array {
        // 1) Trova una riga plausibile di header (TARGA + CODICE)
        $headerRow = $tpl['startRow'] + 1;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 120, $tpl['endRow']); $r++) {
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
                throw new \RuntimeException("Template Excel: colonna non trovata: {$k}. Controlla l’intestazione nel file.");
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
        $headerRow = $tpl['startRow'] + 1;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 120, $tpl['endRow']); $r++) {
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


    /* ===================== COMMON HELPERS (stili, loghi, template, ecc.) ===================== */

    private function slugify(string $s): string {
        $s = preg_replace('/[^\pL\d]+/u', '_', $s);
        $s = trim($s, '_');
        $s = preg_replace('/_+/', '_', $s);
        return strtolower($s ?: 'export');
    }

    private function thickBorderRow(Worksheet $sheet, int $row, int $lastColIdx): void {
        $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex($lastColIdx) . $row)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THICK);
    }

    private function findHeaderRowKm(Worksheet $sheet, int $startFromRow = 1, int $stopRow = null): int {
        $stopRow = $stopRow ?: ($startFromRow + 400);
        for ($r = max(1, $startFromRow); $r <= $stopRow; $r++) {
            $hasT = $hasC = $hasK = false;
            for ($c = 1; $c <= 60; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'TARGA') $hasT = true;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $hasC = true;
                if (str_starts_with($t, 'KM. TOTALI')) $hasK = true;
            }
            if ($hasT && $hasC && $hasK) return $r;
        }
        return $startFromRow + 8;
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

    private function replacePlaceholdersEverywhere(Worksheet $sheet, array $placeholders): void {
        if (empty($placeholders)) return;

        $maxRow = $sheet->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        foreach ($placeholders as $key => $value) {
            $key   = (string)$key;
            $value = (string)$value;

            // pattern che accettano spazi fra i delimitatori e la chiave
            $patterns[$key] = [
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/ui',
                '/\[\[\s*' . preg_quote($key, '/') . '\s*\]\]/ui',
                '/<<\s*'   . preg_quote($key, '/') . '\s*>>/ui',
                '/%\s*'    . preg_quote($key, '/') . '\s*%/ui',
            ];
        }

        for ($r = 1; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $val  = $cell->getValue();

                // estrai testo anche da RichText
                $text = $val instanceof RichText
                    ? $val->getPlainText()
                    : (is_string($val) ? $val : null);

                if ($text === null || $text === '') continue;

                $orig = $text;

                // 1) sostituisci le forme con delimitatori (spazi tollerati)
                foreach ($patterns as $k => $regexes) {
                    foreach ($regexes as $rx) {
                        $text = preg_replace($rx, $placeholders[$k], $text);
                    }
                    // 2) fallback: sostituisci la chiave “nuda” se presente
                    //    (es. qualcuno ha scritto solo nome_associazione)
                    $text = str_replace($k, $placeholders[$k], $text);
                }

                // 3) compatta spazi multipli generati dal replace
                $text = preg_replace('/\s{2,}/', ' ', $text);

                if ($text !== $orig) {
                    $cell->setValueExplicit($text, DataType::TYPE_STRING);
                }
            }
        }
    }

    private function findRowByLabel(Worksheet $sheet, string $label, int $fromRow = 1, ?int $toRow = null): ?int {
        $toRow  = $toRow ?: $sheet->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $labelU = mb_strtoupper(trim($label));

        for ($r = $fromRow; $r <= $toRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                if ($v instanceof RichText) $v = $v->getPlainText();
                if (is_string($v) && mb_strtoupper(trim($v)) === $labelU) return $r;
            }
        }
        return null;
    }

    private function insertLogosAtRow(Worksheet $sheet, array $images, int $row, ?int $rightColIdx = null): void {
        // SX
        if (!empty($images['left']) && file_exists($images['left'])) {
            $d = new Drawing();
            $d->setName('Logo Left');
            $d->setPath($images['left']);
            $d->setHeight(60);
            $d->setCoordinates('B' . $row);
            $d->setOffsetX(5);
            $d->setOffsetY(2);
            $d->setWorksheet($sheet);
        }
        // DX
        if (!empty($images['right']) && file_exists($images['right'])) {
            if (!$rightColIdx) {
                $maxColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());
                $rightColIdx = max(12, $maxColIdx - 4);
            }
            $rightColL = Coordinate::stringFromColumnIndex($rightColIdx);

            $d = new Drawing();
            $d->setName('Logo Right');
            $d->setPath($images['right']);
            $d->setHeight(60);
            $d->setCoordinates($rightColL . $row);
            $d->setOffsetX(5);
            $d->setOffsetY(2);
            $d->setWorksheet($sheet);
        }
        $sheet->getRowDimension($row)->setRowHeight(48);
    }

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

        // colonne
        for ($c = 1; $c <= $maxCol; $c++) {
            $colL   = Coordinate::stringFromColumnIndex($c);
            $srcDim = $src->getColumnDimension($colL);
            $dstDim = $dst->getColumnDimension($colL);
            $dstDim->setAutoSize(false);
            $w = $srcDim->getWidth();
            if ($w !== null) $dstDim->setWidth($w);
            $dstDim->setVisible($srcDim->getVisible());
        }

        // celle + stili
        for ($r = 1; $r <= $maxRow; $r++) {
            $dstR = $rowCursor + $r - 1;
            $srcRow = $src->getRowDimension($r);
            $dstRow = $dst->getRowDimension($dstR);
            $dstRow->setRowHeight($srcRow->getRowHeight());
            $dstRow->setVisible($srcRow->getVisible());

            for ($c = 1; $c <= $maxCol; $c++) {
                $srcCell = $src->getCellByColumnAndRow($c, $r);
                $dstCell = $dst->getCellByColumnAndRow($c, $dstR);

                $dstCell->setValueExplicit($srcCell->getValue(), $srcCell->getDataType());

                $styleArr = $src->getStyleByColumnAndRow($c, $r)->exportArray();
                if (!empty($styleArr)) {
                    $dst->getStyleByColumnAndRow($c, $dstR)->applyFromArray($styleArr);
                }
            }
        }

        // merge
        foreach ($src->getMergeCells() as $merge) {
            [$start, $end] = Coordinate::rangeBoundaries($merge);
            [$c1, $r1] = $start;
            [$c2, $r2] = $end;
            $dst->mergeCellsByColumnAndRow($c1, $r1 + $rowCursor - 1, $c2, $r2 + $rowCursor - 1);
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

    private function thickOutline(Worksheet $sheet, int $topRow, int $bottomRow, int $lastColIdx): void {
        $topL = 'A' . $topRow;
        $botR = Coordinate::stringFromColumnIndex($lastColIdx) . $bottomRow;
        $sheet->getStyle("$topL:$botR")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
    }

    private function autosizeUsedColumns(Worksheet $sheet, int $fromColIdx, int $toColIdx, float $minWidth = 9.5): void {
        for ($c = $fromColIdx; $c <= $toColIdx; $c++) {
            $colL = Coordinate::stringFromColumnIndex($c);
            $dim  = $sheet->getColumnDimension($colL);
            $dim->setAutoSize(true);
            // assicura una larghezza minima per prevenire ######
            if (($dim->getWidth() ?? 0) < $minWidth) {
                $dim->setAutoSize(false);
                $dim->setWidth($minWidth);
            }
        }
    }

    private function detectDistintaHeaderAndCols(Worksheet $sheet, array $tpl): array {
        // trova una riga che contenga contemporaneamente TARGA, CODICE, "TOTALI NUMERO SERVIZI" ecc.
        $headerRow = $tpl['startRow'] + 1;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 120, $tpl['endRow']); $r++) {
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

    /**
     * Header/colonne per i template singola mansione (no convenzioni).
     * Riconosce INPS/INAIL separati. Compatibile con vecchi template che hanno “ONERI SOCIALI”.
     */
    private function detectMansioneHeaderAndCols(Worksheet $sheet, array $tpl): array {
        $headerRow = $tpl['startRow'] + 1;
        for ($r = $tpl['startRow']; $r <= min($tpl['startRow'] + 120, $tpl['endRow']); $r++) {
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
     * Crea un NUOVO foglio partendo dal template e compila la tabella.
     */
    private function creaFoglioImputazioneSanitario(
        Spreadsheet $wb,
        string $templatePath,
        string $nomeAssociazione,
        int $idAssociazione,
        int $anno
    ): void {
        // --- 1) crea un foglio vuoto e incolla il template (niente addExternalSheet) ---
        $s = $wb->createSheet();
        $s->setTitle('MATERIALE SANITARIO DI CONSUMO');
        $this->appendTemplate($s, $templatePath, 1);

        // --- 2) header placeholders ---
        $this->replacePlaceholdersEverywhere($s, [
            'nome_associazione' => $nomeAssociazione,
            'ASSOCIAZIONE'      => $nomeAssociazione,
            'anno_riferimento'  => (string)$anno,
            'ANNO'              => (string)$anno,
        ]);

        // --- 3) dati base come nella pagina CORRETTA ---
        $totBilancio = (float) (CostoMaterialeSanitario::getTotale($idAssociazione, $anno) ?? 0);

        // righe “grezze” con n.servizi per conv (solo mezzi inclusi)
        $rip = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);
        $righe = collect($rip['righe'] ?? [])
            ->reject(fn($r) => !empty($r['is_totale']))
            ->values();

        // convenzioni e flag materiale fornito ASL
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);
        $convFlagYes = $convenzioni
            ->filter(fn($c) => (int)($c->materiale_fornito_asl ?? 0) === 1)
            ->pluck('idConvenzione')->map(fn($v) => (int)$v)->all();
        $convFlagSet = array_flip($convFlagYes); // set O(1)

        // KM per (automezzo, convenzione)
        $kmGroups = AutomezzoKm::getGroupedByAutomezzoAndConvenzione($anno, Auth::user(), $idAssociazione);

        // --- 4) servizi ADJUSTED per ogni mezzo (= servizi - km se materiale_fornito_asl=1) ---
        $rows = [];
        $totServAdjusted = 0.0;

        foreach ($righe as $r) {
            $incl = !empty($r['incluso_riparto']);
            $val  = (array)($r['valori'] ?? []);
            $idA  = (int)($r['idAutomezzo'] ?? 0);

            $adj = 0.0;
            if ($incl) {
                foreach ($val as $idConv => $numServ) {
                    $n = (int)$numServ;
                    if (isset($convFlagSet[(int)$idConv])) {
                        $key = $idA . '-' . (int)$idConv;
                        $km  = $kmGroups->has($key) ? (float)$kmGroups->get($key)->sum('KMPercorsi') : 0.0;
                        $n   = max(0, $n - (int)round($km));
                    }
                    $adj += $n;
                }
                $totServAdjusted += $adj;
            }

            $rows[] = [
                'targa'   => (string)($r['Targa'] ?? ''),
                'servizi' => $incl ? $adj : 0.0,
                'incluso' => $incl,
            ];
        }

        // --- 5) scrittura tabella ---
        [$hdrRow, $colTarga, $colN, $colPerc, $colImp] = $this->locateImputazioneSanitarioHeader($s);

        $r = $hdrRow + 1;
        $sumServ = 0.0;
        $sumImp = 0.0;
        $lastDataRow = $r;

        foreach ($rows as $i => $row) {
            $s->setCellValueByColumnAndRow($colTarga - 1, $r, 'AUTO ' . ($i + 1));
            $s->setCellValueByColumnAndRow($colTarga,     $r, $row['targa']);
            $s->setCellValueByColumnAndRow($colN,         $r, $row['servizi']);

            $pct = ($row['incluso'] && $totServAdjusted > 0) ? ($row['servizi'] / $totServAdjusted) : 0.0;
            $imp = $row['incluso'] ? round($totBilancio * $pct, 2) : 0.0;

            $s->setCellValueByColumnAndRow($colPerc, $r, $pct);
            $s->setCellValueByColumnAndRow($colImp,  $r, $imp);

            if ($row['incluso']) {
                $sumServ += $row['servizi'];
                $sumImp += $imp;
            }

            $this->copyRowStyle($s, $hdrRow + 1, $r);
            $lastDataRow = $r;
            $r++;
        }

        // riga TOTALE + allineamento centesimi
        $rowTot = $lastDataRow + 1;
        $s->setCellValueByColumnAndRow($colTarga - 1, $rowTot, 'TOTALE');
        $s->setCellValueByColumnAndRow($colN,        $rowTot, $sumServ);

        $delta = round($totBilancio - $sumImp, 2);
        if (abs($delta) >= 0.01) {
            for ($rr = $lastDataRow; $rr >= ($hdrRow + 1); $rr--) {
                $n = (float)$s->getCellByColumnAndRow($colN, $rr)->getCalculatedValue();
                if ($n > 0) {
                    $v = (float)$s->getCellByColumnAndRow($colImp, $rr)->getCalculatedValue();
                    $s->setCellValueByColumnAndRow($colImp, $rr, round($v + $delta, 2));
                    $sumImp = round($sumImp + $delta, 2);
                    break;
                }
            }
        }
        $s->setCellValueByColumnAndRow($colImp, $rowTot, $sumImp);

        // --- 6) header boxes + formati ---
        $this->writeTopBoxesImputazioneSanitario($s, $totBilancio, $totServAdjusted);
        $this->formatAsPercent($s, $hdrRow + 1, $lastDataRow, $colPerc);
        $this->formatAsCurrency($s, $hdrRow + 1, $rowTot,     $colImp);
    }


    /** Cerca l’header e restituisce: [row, colTarga, colN, colPerc, colImp] */
    private function locateImputazioneSanitarioHeader(Worksheet $s): array {
        for ($r = 1; $r <= 80; $r++) {
            for ($c = 1; $c <= 12; $c++) {
                $v = strtoupper(trim((string) $s->getCellByColumnAndRow($c, $r)->getValue()));
                if ($v === 'TARGA') {
                    // assumo struttura: [AUTO] [TARGA] [N.SERVIZI...] [% RIPARTO] [IMPORTO]
                    $colTarga = $c;
                    $colN     = $c + 1;
                    $colPerc  = $c + 2;
                    $colImp   = $c + 3;
                    return [$r, $colTarga, $colN, $colPerc, $colImp];
                }
            }
        }
        // fallback sicuro
        return [12, 3, 4, 5, 6];
    }

    /** Sostituisce tutti i segnaposto {{...}} nel foglio. */
    private function replaceAll(Worksheet $s, array $map): void {
        $maxR = $s->getHighestRow();
        $maxC = Coordinate::columnIndexFromString($s->getHighestColumn());
        for ($r = 1; $r <= $maxR; $r++) {
            for ($c = 1; $c <= $maxC; $c++) {
                $val = (string) $s->getCellByColumnAndRow($c, $r)->getValue();
                if ($val === '') continue;
                $orig = $val;
                foreach ($map as $k => $v) $val = str_replace($k, $v, $val);
                if ($val !== $orig) $s->setCellValueByColumnAndRow($c, $r, $val);
            }
        }
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
            $nomeAssoc = (string) \DB::table('associazioni')
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
            $this->formatAsPercent($sheet, $hdrRow + 1, $rowTot, $colPerc);
            $this->formatAsCurrency($sheet, $hdrRow + 1, $rowTot, $colImp);
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
        // 0) dati di intestazione
        $nomeAssoc = (string) DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione');

        // 1) crea foglio vuoto, appende il template e lo titola
        $sheet = $wb->createSheet();
        $sheet->setTitle('RIEPILOGO COSTI AUTO-RADIO-SAN.');
        $this->appendTemplate($sheet, $templatePath, 1);

        // 2) placeholder intestazione template (come negli altri fogli)
        $this->replacePlaceholdersEverywhere($sheet, [
            'ASSOCIAZIONE'      => $nomeAssoc,
            'nome_associazione' => $nomeAssoc,
            'ANNO'              => (string) $anno,
            'anno_riferimento'  => (string) $anno,
        ]);

        // 3) convenzioni ordinate e tabella dati totale
        $convMap   = RipartizioneCostiService::convenzioni($idAssociazione, $anno);
        $convNomi  = array_values($convMap);
        $tabella   = RipartizioneCostiService::calcolaTabellaTotale($idAssociazione, $anno);
        // $tabella = [
        //   ['voce'=>'ASSICURAZIONI','totale'=>123.45,'ASL TO4...'=>12.3, ...],
        //   ...,
        //   ['voce'=>'TOTALI', ...]
        // ];

        // 4) localizza header righe/colonne nel template:
        // cerco la cella che contiene "TOTALE AUTO" (prima riga della tabella)
        [$headerRow, $firstCol] = $this->locateRiepilogoHeader($sheet); // (es. headerRow=6, firstCol=1 per col A)

        // 5) scrivo l’header convenzioni e l’ultima colonna "TOTALE"
        //    layout atteso: | A=“TOTALE AUTO” | B..(B+n-1)=Nomi convenzioni | successiva = “TOTALE”
        $colStart = $firstCol + 1;
        $col = $colStart;
        foreach ($convNomi as $nome) {
            $sheet->setCellValueByColumnAndRow($col, $headerRow, $nome);
            $col++;
        }
        $colTot = $col;
        $sheet->setCellValueByColumnAndRow($colTot, $headerRow, 'TOTALE');

        // 6) scrittura righe dati
        $r = $headerRow + 1;
        foreach ($tabella as $riga) {
            $voce = (string) ($riga['voce'] ?? '');
            if ($voce === '') continue;

            // Colonna descrizione (prima colonna della tabella, sotto "TOTALE AUTO")
            $sheet->setCellValueByColumnAndRow($firstCol, $r, $voce);

            // Valori per colonna convenzione
            $col = $colStart;
            $somma = 0.0;
            foreach ($convNomi as $nomeC) {
                $val = (float) ($riga[$nomeC] ?? 0.0);
                $sheet->setCellValueByColumnAndRow($col, $r, $val);
                $somma += $val;
                $col++;
            }

            // Colonna "TOTALE": uso il 'totale' della riga, con riallineamento centesimi
            $totDich = round((float) ($riga['totale'] ?? $somma), 2);
            $delta   = round($totDich - round($somma, 2), 2);
            if (abs($delta) >= 0.01 && !empty($convNomi)) {
                // spingo delta sull’ultima convenzione della riga
                $lastCol = $colStart + count($convNomi) - 1;
                $lastVal = (float) $sheet->getCellByColumnAndRow($lastCol, $r)->getCalculatedValue();
                $sheet->setCellValueByColumnAndRow($lastCol, $r, round($lastVal + $delta, 2));
                $somma = round($somma + $delta, 2);
            }
            $sheet->setCellValueByColumnAndRow($colTot, $r, $somma);

            // Copia stile riga campione (la prima riga dati sotto l’header nel template)
            $this->copyRowStyle($sheet, $headerRow + 1, $r);

            $r++;
        }

        // 7) formattazioni: valuta su tutte le celle numeriche della tabella
        //    (dalla prima riga dati fino all’ultima riga scritta)
        $lastRow = $r - 1;
        $this->formatAsCurrency($sheet, $headerRow + 1, $lastRow, $colStart, $colTot);

        // 8) opzionale: area di stampa sull’intera tabella
        $lastColLetter = Coordinate::stringFromColumnIndex($colTot);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColLetter}{$lastRow}");
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
        // 1) crea foglio vuoto e incolla template
        $s = $wb->createSheet();
        $titolo = trim(sprintf('%s - %s', (string)$auto->Targa, (string)$auto->CodiceIdentificativo));
        // Excel max 31 char + evita duplicati
        $titolo = mb_substr($titolo, 0, 31, 'UTF-8');
        if ($wb->sheetNameExists($titolo)) {
            $i = 2;
            while ($wb->sheetNameExists($titolo . '(' . $i . ')')) $i++;
            $titolo .= '(' . $i . ')';
        }
        $s->setTitle($titolo);

        $this->appendTemplate($s, $templatePath, 1);

        // 2) placeholder header (nel template hai header con anno/associazione/targa/codice)
        $this->replacePlaceholdersEverywhere($s, [
            'nome_associazione' => $nomeAssociazione,
            'ASSOCIAZIONE'      => $nomeAssociazione,
            'anno_riferimento'  => (string)$anno,
            'ANNO'              => (string)$anno,
            'TARGA'             => (string)$auto->Targa,
            'CODICE'            => (string)$auto->CodiceIdentificativo,
        ]);

        // 3) convenzioni (ordinate come da riepilogo) e dati del singolo mezzo
        $convMap   = RipartizioneCostiService::convenzioni($idAssociazione, $anno);
        $convIds   = array_keys($convMap);
        $convNames = array_values($convMap);

        $rows = RipartizioneCostiService::calcolaRipartizioneTabellaFinale(
            $idAssociazione,
            $anno,
            (int)$auto->idAutomezzo
        );
        // $rows è un array di righe:
        // ['voce' => string, 'totale' => float, <nome conv 1> => float, ..., <nome conv N> => float]

        // 4) individua header e colonne nel template
        //    (nel tuo Costi_AUTO1.xlsx l’intestazione contiene "TOTALE COSTI DA RIPARTIRE"
        //     e l’ultima colonna è "TOTALE")
        [$hdrRow, $colVoce, $colTotRip, $colFirstConv, $colTotCol] = $this->locateHeaderAutoDetail($s);

        // 5) scrivi i nomi delle convenzioni in header (sostituiscono i "L. -")
        $c = $colFirstConv;
        foreach ($convNames as $name) {
            $s->setCellValueByColumnAndRow($c, $hdrRow, $name);
            $c++;
        }
        // ultima colonna header = TOTALE
        $s->setCellValueByColumnAndRow($colTotCol, $hdrRow, 'TOTALE');

        // 6) righe dati
        $startDataRow = $hdrRow + 1;
        $r = $startDataRow;
        $lastDataRow = $r;

        foreach ($rows as $row) {
            // mantieni eventuale riga finale "TOTALI" del servizio come ultima riga
            $isTot = isset($row['voce']) && mb_strtoupper($row['voce'], 'UTF-8') === 'TOTALI';
            if ($isTot) continue;

            // voce
            $s->setCellValueByColumnAndRow($colVoce,   $r, (string)$row['voce']);
            // totale costi da ripartire (colonna accanto alla voce)
            $s->setCellValueByColumnAndRow($colTotRip, $r, (float)$row['totale']);

            // importi per convenzione nelle colonne di mezzo
            $c = $colFirstConv;
            foreach ($convNames as $name) {
                $val = (float)($row[$name] ?? 0.0);
                $s->setCellValueByColumnAndRow($c, $r, $val);
                $c++;
            }

            // colonna TOTALE a destra (uguale a totale riga o somma conv)
            $s->setCellValueByColumnAndRow($colTotCol, $r, (float)$row['totale']);

            // stile riga (copio lo stile della prima riga dati del template)
            $this->copyRowStyle($s, $startDataRow, $r);

            $lastDataRow = $r;
            $r++;
        }

        // 7) riga "TOTALI" (somme per colonna)
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

        // 8) formati valuta per tutta la tabella (da "tot ripartire" fino a totale)
        $this->formatAsCurrency($s, $startDataRow, $rowTot, $colTotRip);
        for ($cc = $colFirstConv; $cc <= $colTotCol; $cc++) {
            $this->formatAsCurrency($s, $startDataRow, $rowTot, $cc);
        }
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
        ];

        // 3) Crea foglio in coda
        $sheet = new Worksheet($spreadsheet, 'DISTINTA IMPUTAZIONE COSTI');
        $spreadsheet->addSheet($sheet, $spreadsheet->getSheetCount());

        // Stili
        $headFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']];
        $subHeadFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']];
        $secFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']];
        $thinBorder = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]]];

        // 4) Header (r1: titoli; r2: sottotitoli convenzioni)
        $col = 1; // A
        $sheet->setCellValueByColumnAndRow($col++, 1, 'VOCE');
        $sheet->setCellValueByColumnAndRow($col++, 1, 'Importo Totale da Bilancio (Cons.)');
        $sheet->setCellValueByColumnAndRow($col++, 1, 'Costi di Diretta Imputazione');

        $convStartCol = $col;
        foreach ($convNomi as $convName) {
            $c1 = $col;
            $c2 = $col + 1;
            $c3 = $col + 2;
            $sheet->mergeCellsByColumnAndRow($c1, 1, $c3, 1);
            $sheet->setCellValueByColumnAndRow($c1, 1, $convName);
            $sheet->setCellValueByColumnAndRow($c1, 2, 'Diretti');
            $sheet->setCellValueByColumnAndRow($c2, 2, 'Sconto (Amm.)');
            $sheet->setCellValueByColumnAndRow($c3, 2, 'Indiretti');
            $col += 3;
        }

        // Stile header
        $sheet->getStyleByColumnAndRow(1, 1, $col - 1, 1)->getFill()->applyFromArray($headFill);
        $sheet->getStyleByColumnAndRow(1, 2, $col - 1, 2)->getFill()->applyFromArray($subHeadFill);
        $sheet->getStyleByColumnAndRow(1, 1, $col - 1, 2)->getFont()->setBold(true);
        $sheet->getStyleByColumnAndRow(1, 1, $col - 1, 2)->applyFromArray($thinBorder);

        // Larghezze
        $sheet->getColumnDimension('A')->setWidth(60); // VOCE
        $sheet->getColumnDimension('B')->setWidth(22); // Bilancio
        $sheet->getColumnDimension('C')->setWidth(24); // Diretta

        // 5) Righe dati — NESSUN ordinamento aggiuntivo (rispetta ordine da DB / service)
        $row = 3;
        $currentSection = null;

        foreach ($righe as $riga) {
            $sezId = (int)($riga['sezione_id'] ?? 0);
            if ($sezId < 2 || $sezId > 11) continue;

            // Riga titolo sezione (solo quando cambia)
            if ($currentSection !== $sezId) {
                $currentSection = $sezId;
                $sheet->mergeCellsByColumnAndRow(1, $row, $col - 1, $row);
                $sheet->setCellValueByColumnAndRow(1, $row, strtoupper($sezioneName[$sezId] ?? "Sezione $sezId"));
                $sheet->getStyleByColumnAndRow(1, $row, $col - 1, $row)->getFill()->applyFromArray($secFill);
                $sheet->getStyleByColumnAndRow(1, $row, $col - 1, $row)->getFont()->setBold(true);
                $row++;
            }

            // RIGA VOCE
            $c = 1;
            $sheet->setCellValueByColumnAndRow($c++, $row, (string)$riga['voce']);                  // VOCE
            $sheet->setCellValueByColumnAndRow($c++, $row, (float)($riga['bilancio'] ?? 0));        // Bilancio (cons.)
            $sheet->setCellValueByColumnAndRow($c++, $row, (float)($riga['diretta'] ?? 0));         // Diretta = diretto - ammortamento

            $sumDiretti = 0.0;
            $sumSconto = 0.0;
            $sumInd = 0.0;
            foreach ($convNomi as $convName) {
                $cell = $riga[$convName] ?? ['diretti' => 0, 'ammortamento' => 0, 'indiretti' => 0];
                $dir = (float)($cell['diretti'] ?? 0);
                $amm = (float)($cell['ammortamento'] ?? 0);
                $ind = (float)($cell['indiretti'] ?? 0);

                $sheet->setCellValueByColumnAndRow($c++, $row, $dir);
                $sheet->setCellValueByColumnAndRow($c++, $row, $amm);
                $sheet->setCellValueByColumnAndRow($c++, $row, $ind);

                $sumDiretti += $dir;
                $sumSconto += $amm;
                $sumInd += $ind;
            }

            $row++;
        }

        // Bordi e formati
        $sheet->getStyleByColumnAndRow(1, 1, $col - 1, $row - 1)->applyFromArray($thinBorder);
        for ($cc = 2; $cc <= $col - 1; $cc++) {
            $sheet->getStyleByColumnAndRow($cc, 3, $cc, $row - 1)
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Freeze: dopo le 3 colonne fisse (VOCE, Bilancio, Diretta)
        $sheet->freezePaneByColumnAndRow(4, 3);
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


    private function formatAsCurrency(Worksheet $s, int $r1, int $r2, int $col): void {
        $range = Coordinate::stringFromColumnIndex($col) . $r1 . ':' .
            Coordinate::stringFromColumnIndex($col) . $r2;
        // formato € italiano (puoi cambiarlo)
        $s->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
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

        $after = $wb->getSheetByName($afterName);
        $index = $after ? ($wb->getIndex($after) + 1) : $wb->getSheetCount();

        // inserisco direttamente all'indice voluto (niente setSheetOrder)
        $wb->addSheet($sheet, $index);
        $wb->setActiveSheetIndex($index);
        return $index;
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
            'nome_convenzione'  => (string)$conv->Convenzione,
            'convenzione'       => (string)$conv->Convenzione,
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

    /** Trova la riga header “Voce / Preventivo / Consuntivo” e relative colonne.
     * Ritorna: headerRow, firstDataRow, colVoce, colPrev, colCons.
     */
    private function detectPrevConsHeader(Worksheet $ws): array {
        $maxR = $ws->getHighestRow();
        $maxC = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestColumn());

        $norm = function ($v) {
            if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) $v = $v->getPlainText();
            return is_string($v) ? mb_strtoupper(trim($v), 'UTF-8') : '';
        };

        for ($r = 1; $r <= $maxR; $r++) {
            $colVoce = $colPrev = $colCons = 0;
            for ($c = 1; $c <= $maxC; $c++) {
                $t = $norm($ws->getCellByColumnAndRow($c, $r)->getValue());
                if ($t === 'VOCE'       && !$colVoce) $colVoce = $c;
                if ($t === 'PREVENTIVO' && !$colPrev) $colPrev = $c;
                if ($t === 'CONSUNTIVO' && !$colCons) $colCons = $c;
            }
            if ($colPrev && $colCons) {
                if (!$colVoce) {
                    // prima non-vuota a sinistra dei numerici
                    for ($c = min($colPrev, $colCons) - 1; $c >= 1; $c--) {
                        $v = $ws->getCellByColumnAndRow($c, $r)->getValue();
                        if ($v !== null && $v !== '') {
                            $colVoce = $c;
                            break;
                        }
                    }
                    if (!$colVoce) $colVoce = 3; // fallback: C
                }
                return [
                    'headerRow'    => $r,
                    'firstDataRow' => $r + 1,
                    'colVoce'      => $colVoce,
                    'colPrev'      => $colPrev,
                    'colCons'      => $colCons,
                ];
            }
        }
        // Fallback coerente col tuo template: C/E/F
        return ['headerRow' => 6, 'firstDataRow' => 7, 'colVoce' => 3, 'colPrev' => 5, 'colCons' => 6];
    }


    /**
     * Crea un foglio per OGNI convenzione usando un UNICO template
     * e compila SOLTANTO la Tabella 1 (Voce / Preventivo / Consuntivo).
     * I fogli vengono inseriti subito dopo il foglio-ancora ($anchorTitle).
     */
    /**
     * Crea un foglio per OGNI convenzione usando il template passato
     * e compila SOLTANTO la Tabella 1 (Voce / Preventivo / Consuntivo).
     * I fogli vengono inseriti subito dopo il foglio anchor (default: "DISTINTA IMPUTAZIONE COSTI").
     */
    private function creaFogliPerConvenzioni(
        Spreadsheet $spreadsheet,
        string $tplConvenzionePath,
        string $nomeAssociazione,
        int $idAssociazione,
        int $anno,
        string $anchorTitle = 'DISTINTA IMPUTAZIONE COSTI'
    ): void {
        // 0) Template esiste?
        if (!is_file($tplConvenzionePath)) {
            Log::warning('creaFogliPerConvenzioni: template non trovato', ['path' => $tplConvenzionePath]);
            return;
        }

        // 1) Individua l’indice di inserimento (subito dopo l’anchor)
        $anchorSheet = $spreadsheet->getSheetByName($anchorTitle);
        $insertAt = $anchorSheet
            ? ($spreadsheet->getIndex($anchorSheet) + 1)
            : $spreadsheet->getSheetCount();

        // 2) Convenzioni ordinate come da DB
        $convenzioni = \DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get();

        // 3) Carica UNA volta il template e clona il suo primo foglio per ogni convenzione
        $tplWb    = IOFactory::load($tplConvenzionePath);
        $tplSheet = $tplWb->getSheet(0);

        foreach ($convenzioni as $conv) {
            try {
                // 3.a) Clona il foglio del template
                $newSheet = clone $tplSheet;

                // 3.b) Titolo foglio = nome convenzione (31 char max, univoco)
                $title = $this->uniqueSheetTitle($spreadsheet, mb_substr((string)$conv->Convenzione, 0, 31));
                $newSheet->setTitle($title !== '' ? $title : 'CONVENZIONE');

                // 3.c) Inserisci subito dopo l’anchor
                $spreadsheet->addSheet($newSheet, $insertAt);
                $insertAt++;

                // 3.d) Placeholder header
                $this->replacePlaceholdersEverywhere($ws, [
                    'nome_associazione' => $nomeAssociazione,
                    'anno_riferimento'  => (string)$anno,
                    'nome_convenzione'  => (string)$conv->Convenzione,
                    'convenzione'       => (string)$conv->Convenzione,
                ]);

                // 3.e) Compila **solo la Tabella 1** (Voce/Preventivo/Consuntivo)
                //     La funzione si auto-localizza sull’header corretto.
                $this->writeTabellaTipologia1(
                    $newSheet,
                    $idAssociazione,
                    $anno,
                    (int)$conv->idConvenzione
                );
            } catch (Throwable $e) {
                Log::warning('Fogli per convenzione (Tabella 1): errore non bloccante', [
                    'idConvenzione' => (int)$conv->idConvenzione,
                    'conv'          => (string)$conv->Convenzione,
                    'msg'           => $e->getMessage(),
                    'file'          => $e->getFile(),
                    'line'          => $e->getLine(),
                ]);
                // continua con la prossima convenzione
            }
        }

        // pulizia
        $tplWb->disconnectWorksheets();
    }

    /** Genera un titolo di foglio univoco all’interno del workbook (max 31 char). */
    private function uniqueSheetTitle(Spreadsheet $wb, string $base): string {
        $title = trim($base) ?: 'Foglio';
        $title = mb_substr($title, 0, 31, 'UTF-8');

        // Evita caratteri non permessi in Excel
        $title = str_replace(['\\', '/', '?', '*', '[', ']'], '-', $title);

        if (!$wb->sheetNameExists($title)) {
            return $title;
        }
        // Aggiunge (2), (3), ... finché è unico
        $i = 2;
        while ($wb->sheetNameExists(mb_substr($title, 0, 31 - (strlen((string)$i) + 2)) . '(' . $i . ')')) {
            $i++;
            if ($i > 99) break;
        }
        $baseTrunc = mb_substr($title, 0, 31 - (strlen((string)$i) + 2));
        return $baseTrunc . '(' . $i . ')';
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
                $ws->setCellValueExplicit("B{$r}", (float)$rr->preventivo,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $ws->setCellValueExplicit("C{$r}", (float)$rr->consuntivo,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $this->box($ws, "A{$r}:C{$r}");
                $this->formatNum($ws, "B{$r}:C{$r}");
                $r++;
            }

            // riga vuota tra sezioni
            $r++;
        }
    }

    /* ========================= STILI & UTILITY ========================= */

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
                $ws->setCellValueExplicitByColumnAndRow($colPrev, $rigaTemplate, $prev, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $ws->setCellValueByColumnAndRow($colPrev, $rigaTemplate, null);
            }

            if ($cons !== null) {
                $ws->setCellValueExplicitByColumnAndRow($colCons, $rigaTemplate, $cons, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
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
        return mb_strtoupper($v, 'UTF-8');
    }

    /**
     * Compila la tabella VOCE / PREVENTIVO / CONSUNTIVO scrivendo in sequenza
     * (senza cercare le righe per etichetta). Usa le voci 1001..1031
     * della singola convenzione.
     */
    private function fillTabellaRiepilogoDatiSequential(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione
    ): void {
        // 1) Dati già aggregati per la UI (includono voci calcolate)
        $all = Riepilogo::getForDataTable($anno, $idAssociazione, $idConvenzione);

        // 2) Filtra range 1001..1031 mantenendo l’ordine di riepilogo_voci_config
        $rows = array_values(array_filter($all, static function ($r) {
            $id = (int)($r['voce_id'] ?? 0);
            return $id >= 1001 && $id <= 1031;
        }));

        if (!$rows) {
            Log::info('RiepilogoDati: nessuna voce 1001..1031 per la convenzione', compact('idAssociazione', 'anno', 'idConvenzione'));
            return;
        }

        // 3) Individua header (usa il tuo detectPrevConsHeader già presente)
        $hdr    = $this->detectPrevConsHeader($ws); // deve restituire colVoce, colPrev, colCons, firstDataRow
        $r      = $hdr['firstDataRow'];
        $cVoce  = $hdr['colVoce'];
        $cPrev  = $hdr['colPrev'];
        $cCons  = $hdr['colCons'];

        // 4) Scrittura sequenziale
        $firstWritten = null;
        $lastWritten  = null;

        foreach ($rows as $row) {
            // ATTENZIONE: NON aggiungiamo mai il nome convenzione nella voce
            $voce = (string)($row['descrizione'] ?? '');

            $prev = is_numeric($row['preventivo']) ? (float)$row['preventivo'] : null;
            $cons = is_numeric($row['consuntivo']) ? (float)$row['consuntivo'] : null;

            // Voce (testo libero)
            $ws->setCellValueByColumnAndRow($cVoce, $r, $voce);

            // Preventivo
            if ($prev !== null) {
                $ws->setCellValueExplicitByColumnAndRow($cPrev, $r, $prev, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $ws->setCellValueByColumnAndRow($cPrev, $r, null);
            }

            // Consuntivo
            if ($cons !== null) {
                $ws->setCellValueExplicitByColumnAndRow($cCons, $r, $cons, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $ws->setCellValueByColumnAndRow($cCons, $r, null);
            }

            $firstWritten ??= $r;
            $lastWritten    = $r;
            $r++;
        }

        // 5) Formatta i numeri sulle righe scritte
        if ($firstWritten !== null) {
            $this->formatNum($ws, $this->col($cPrev) . $firstWritten . ':' . $this->col($cPrev) . $lastWritten);
            $this->formatNum($ws, $this->col($cCons) . $firstWritten . ':' . $this->col($cCons) . $lastWritten);

            $headerRow = $hdr['firstDataRow'] - 1; // l’header è la riga subito sopra i dati
            $this->styleRiepilogoTable($ws, $headerRow, $lastWritten, $cVoce, $cPrev, $cCons);
        }
    }

    private function styleRiepilogoTable(
        Worksheet $ws,
        int $headerRow,   // riga dell’intestazione "Voce / Preventivo / Consuntivo"
        int $lastRow,     // ultima riga dati scritta
        int $cVoce,
        int $cPrev,
        int $cCons
    ): void {
        $rng = $this->col($cVoce) . $headerRow . ':' . $this->col($cCons) . $lastRow;

        // outline spesso, interno sottile
        $ws->getStyle($rng)->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THICK,
                ],
                'inside'  => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // opzionale: header in grassetto e centrato
        $hdrRange = $this->col($cVoce) . $headerRow . ':' . $this->col($cCons) . $headerRow;
        $ws->getStyle($hdrRange)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // opzionale: allinea numeri a destra
        $ws->getStyle($this->col($cPrev) . ($headerRow + 1) . ':' . $this->col($cCons) . $lastRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * Inserisce la tabella "RIEPILOGO COSTI" immediatamente sotto la prima tabella
     * e la compila per tutte le sezioni 2..11 (Automezzi..Altri costi).
     */
    /**
     * Inserisce la tabella "RIEPILOGO COSTI" immediatamente sotto la prima tabella
     * e la compila per tutte le sezioni 2..11 (Automezzi..Altri costi).
     */
    private function fillRiepilogoCostiSottoPrimaTabella(
        Worksheet $ws,
        int $idAssociazione,
        int $anno,
        int $idConvenzione
    ): void {
        // Punto di inserimento (dopo la prima tabella)
        $startRow = $ws->getHighestDataRow() + 2;

        // Titolo tabella
        $ws->setCellValueByColumnAndRow(1, $startRow, 'RIEPILOGO COSTI');
        $ws->mergeCells("A{$startRow}:D{$startRow}");
        $ws->getStyle("A{$startRow}:D{$startRow}")->getFont()->setBold(true)->setSize(12);
        $startRow += 2;

        // Header
        $ws->setCellValue("A{$startRow}", 'Voce');
        $ws->setCellValue("B{$startRow}", 'PREVENTIVO');
        $ws->setCellValue("C{$startRow}", 'CONSUNTIVO');
        $ws->setCellValue("D{$startRow}", 'SCOSTAMENTO');
        $ws->getStyle("A{$startRow}:D{$startRow}")->getFont()->setBold(true);
        $ws->getStyle("A{$startRow}:D{$startRow}")
        ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        // <<< AGGIUNGI QUESTO BLOCCO
        foreach (['A','B','C','D'] as $col) {
            $dim = $ws->getColumnDimension($col);
            $dim->setVisible(true);
            // se la larghezza è 0 / non definita, metti una larghezza minima decente
            if (($dim->getWidth() ?? 0) <= 0) {
                $dim->setAutoSize(false);
                $dim->setWidth(12); // qualsiasi valore >= 10 va bene
            }
        }
        // >>>

        $startRow++;

        $firstDataRow = $startRow;

        // Sezioni 2..11
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
        ];

        foreach (range(2, 11) as $tipologia) {
            // Intestazione sezione
            $ws->setCellValue("A{$startRow}", $mapSezioni[$tipologia] ?? "Sezione {$tipologia}");
            $ws->mergeCells("A{$startRow}:D{$startRow}");
            $ws->getStyle("A{$startRow}:D{$startRow}")->getFont()->setBold(true);
            $ws->getStyle("A{$startRow}:D{$startRow}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEFEFEF');
            $startRow++;

            // Righe della sezione (stessa logica della view)
            $rows = RiepilogoCosti::getByTipologia($tipologia, $anno, $idAssociazione, $idConvenzione);

            foreach ($rows as $r) {
                $descr = (string) ($r->descrizione ?? '');
                $prev  = (float)  ($r->preventivo  ?? 0);
                $cons  = (float)  ($r->consuntivo  ?? 0);

                $ws->setCellValue("A{$startRow}", $descr);
                $ws->setCellValueExplicit("B{$startRow}", $prev, DataType::TYPE_NUMERIC);
                $ws->setCellValueExplicit("C{$startRow}", $cons, DataType::TYPE_NUMERIC);

                // SCOSTAMENTO calcolato in Excel: =SE(Bn=0;0;(Cn-Bn)/Bn)
                $ws->setCellValue("D{$startRow}", "=IF(B{$startRow}=0,0,(C{$startRow}-B{$startRow})/B{$startRow})");

                $startRow++;
            }

            // Riga vuota tra sezioni
            $startRow++;
        }

        $lastDataRow = $startRow - 1;

        // Formati: € su B:C, % su D
        $ws->getStyle("B{$firstDataRow}:C{$lastDataRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle("D{$firstDataRow}:D{$lastDataRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        // Allineamento numeri a destra (facoltativo ma consigliato)
        $ws->getStyle("B{$firstDataRow}:D{$lastDataRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Bordatura tabellare (include header sezione)
        $this->applyGridWithOuterBorder($ws, "A" . ($firstDataRow - 2) . ":D{$lastDataRow}");
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
}
