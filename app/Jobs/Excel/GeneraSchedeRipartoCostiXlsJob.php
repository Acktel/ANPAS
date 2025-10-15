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
use Throwable;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

use App\Services\RipartizioneCostiService;
use App\Models\Automezzo;
use App\Models\AutomezzoServiziSvolti;
use App\Models\RapportoRicavo;

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
        return [
            (new WithoutOverlapping($key))
                ->expireAfter(300)
                ->releaseAfter(15),
        ];
    }

    /* ===================== Helpers ===================== */

    private function slugify(string $s): string {
        $s = preg_replace('/[^\pL\d]+/u', '_', $s);
        $s = trim($s, '_');
        $s = preg_replace('/_+/', '_', $s);
        return strtolower($s ?: 'export');
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
        return $startFromRow + 30;
    }

    private function findHeaderRowPersonale(Worksheet $sheet, int $startFromRow, int $stopRow = null): int {
        $stopRow = $stopRow ?: ($startFromRow + 500);
        for ($r = $startFromRow; $r <= $stopRow; $r++) {
            $hasName = $hasTot = false;
            for ($c = 1; $c <= 80; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'COGNOME DEL DIPENDENTE') $hasName = true;
                if (str_starts_with($t, 'ORE TOTALI ANNUE')) $hasTot = true;
            }
            if ($hasName && $hasTot) return $r;
        }
        return $startFromRow + 60;
    }

    private function findHeaderRowRicavi(Worksheet $sheet, int $startFromRow, int $stopRow = null): int {
        $stopRow = $stopRow ?: ($startFromRow + 300);
        for ($r = $startFromRow; $r <= $stopRow; $r++) {
            for ($c = 1; $c <= 80; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t !== '' && str_starts_with($t, 'TOTALE RICAVI')) return $r;
            }
        }
        return $startFromRow + 20;
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
        $hr = $this->findHeaderRowRicavi($sheet, $startRow);
        $tot = 4;
        for ($c = 1; $c <= 80; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $hr)->getValue()));
            if ($t !== '' && str_starts_with($t, 'TOTALE RICAVI')) {
                $tot = $c;
                break;
            }
        }
        return [
            'headerRow'    => $hr,
            'convTitleRow' => max(1, $hr - 1),
            'dataRow'      => $hr + 1,
            'firstPairCol' => $tot + 1,
            'totCol'       => $tot,
        ];
    }

    private function getPairLeftCols(Worksheet $sheet, int $headerRowPers): array {
        $cols = [];
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($c = 1; $c <= $maxCol; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowPers)->getValue()));
            if ($t === 'ORE DI SERVIZIO') $cols[] = $c;
        }
        return $cols;
    }

    private function hideEmptyRowsBetween(Worksheet $sheet, int $fromRow, int $toRow, int $keepTop = 2, int $keepBottom = 2): void {
        if ($toRow <= $fromRow) return;
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($r = $fromRow; $r <= $toRow; $r++) {
            if (($r - $fromRow) < $keepTop || ($toRow - $r) < $keepBottom) {
                $sheet->getRowDimension($r)->setVisible(true);
                continue;
            }
            $empty = true;
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                if ($v !== null && $v !== '' && $v !== 0 && $v !== '0') {
                    $empty = false;
                    break;
                }
            }
            $sheet->getRowDimension($r)->setVisible($empty ? false : true);
        }
    }

    private function cutAfterTotalRow(Worksheet $sheet, int $totalRow, int $maxScan = 400): void {
        $last = min($sheet->getHighestRow(), $totalRow + $maxScan);
        for ($r = $totalRow + 1; $r <= $last; $r++) {
            $sheet->getRowDimension($r)->setVisible(false);
        }
    }

    private function safeSaveSpreadsheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $baseFilename, string $dirRel = 'documenti', int $retries = 3, int $sleepMs = 300): array {
        $disk = Storage::disk('public');
        if (!$disk->exists($dirRel)) $disk->makeDirectory($dirRel);

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . uniqid('riparto_', true) . '.xlsx';
        $writer  = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($tmpPath);

        $finalRel  = $dirRel . '/' . $baseFilename;
        $finalAbs  = $disk->path($finalRel);

        for ($i = 0; $i <= $retries; $i++) {
            try {
                if (file_exists($finalAbs)) @unlink($finalAbs);
                if (@rename($tmpPath, $finalAbs)) return [$finalRel, $baseFilename];
            } catch (\Throwable $e) {
                Log::warning('safeSaveSpreadsheet retry', ['target' => $finalAbs, 'try' => $i, 'error' => $e->getMessage()]);
            }
            if ($i === $retries) {
                $altName = pathinfo($baseFilename, PATHINFO_FILENAME) . '_' . now()->format('Ymd_His') . '.' . pathinfo($baseFilename, PATHINFO_EXTENSION);
                $altRel = $dirRel . '/' . $altName;
                $altAbs = $disk->path($altRel);
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

    /**
     * Incolla un template XLSX nello sheet di destinazione, preservando stili/merge/drawings.
     * Ritorna [startRow, endRow].
     */
    private function appendTemplateSheet(Worksheet $dst, string $templateAbs, int $rowCursor): array {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $tpl = $reader->load($templateAbs);
        $src = $tpl->getActiveSheet();

        $maxRow = $src->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($src->getHighestColumn());

        // colonne
        for ($c = 1; $c <= $maxCol; $c++) {
            $colL = Coordinate::stringFromColumnIndex($c);
            $dst->getColumnDimension($colL)->setWidth($src->getColumnDimension($colL)->getWidth());
            $dst->getColumnDimension($colL)->setVisible($src->getColumnDimension($colL)->getVisible());
            $dst->getColumnDimension($colL)->setAutoSize(false);
        }

        // celle + stile
        for ($r = 1; $r <= $maxRow; $r++) {
            $dstR = $rowCursor + $r - 1;
            $dst->getRowDimension($dstR)->setRowHeight($src->getRowDimension($r)->getRowHeight());
            $dst->getRowDimension($dstR)->setVisible($src->getRowDimension($r)->getVisible());

            for ($c = 1; $c <= $maxCol; $c++) {
                $srcCell = $src->getCellByColumnAndRow($c, $r);
                $dstCell = $dst->getCellByColumnAndRow($c, $dstR);
                $dstCell->setValueExplicit($srcCell->getValue(), $srcCell->getDataType());
                $dst->duplicateStyle($src->getStyleByColumnAndRow($c, $r), $dstCell->getCoordinate());
            }
        }

        // merge
        foreach ($src->getMergeCells() as $merge) {
            [$c1, $r1, $c2, $r2] = Coordinate::rangeBoundaries($merge);
            $dst->mergeCellsByColumnAndRow($c1, $r1 + $rowCursor - 1, $c2, $r2 + $rowCursor - 1);
        }

        // drawings (loghi)
        foreach ($src->getDrawingCollection() as $drawing) {
            $clone = clone $drawing;
            $clone->setWorksheet(null);
            [$colL, $r] = Coordinate::coordinateFromString($drawing->getCoordinates());
            $clone->setCoordinates($colL . ($r + $rowCursor - 1));
            $clone->setWorksheet($dst);
        }

        $start = $rowCursor;
        $end   = $rowCursor + $maxRow - 1;

        $tpl->disconnectWorksheets();
        unset($tpl);

        return [$start, $end];
    }

    private function findVolontariHeader(Worksheet $sheet, int $startFromRow): array {
        $maxRow = min($sheet->getHighestRow(), $startFromRow + 400);
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($r = max(1, $startFromRow); $r <= $maxRow; $r++) {
            $cntOre = 0;
            $cntPerc = 0;
            for ($c = 1; $c <= $maxCol; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'ORE DI SERVIZIO') $cntOre++;
                if ($t === '%') $cntPerc++;
            }
            if ($cntOre >= 2 && $cntPerc >= 2) return ['headerRow' => $r, 'convTitleRow' => max(1, $r - 1)];
        }
        return ['headerRow' => $startFromRow + 10, 'convTitleRow' => $startFromRow + 9];
    }

    /* ===================== Handle ===================== */
    public function handle(): void {
        Log::info('SchedeRipartoCosti: handle START', [
            'documentoId' => $this->documentoId,
            'idAss'       => $this->idAssociazione,
            'anno'        => $this->anno,
        ]);

        try {
            $this->cleanupOldGeneratedFiles();

            DB::table('documenti_generati')->where('id', $this->documentoId)
                ->update(['stato' => 'processing', 'updated_at' => now()]);

            $anno  = $this->anno;
            $idAss = $this->idAssociazione;

            // dati base
            $associazione = (string) DB::table('associazioni')->where('idAssociazione', $idAss)->value('Associazione') ?? '';
            $automezzi    = Automezzo::getByAssociazione($idAss, $anno)->sortBy('idAutomezzo')->values();
            $convenzioni  = DB::table('convenzioni')->select('idConvenzione', 'Convenzione')
                ->where('idAssociazione', $idAss)->where('idAnno', $anno)
                ->orderBy('ordinamento')->orderBy('idConvenzione')->get()->values();
            $numConv = $convenzioni->count();
            $colL = fn(int $i) => Coordinate::stringFromColumnIndex($i);

            // === workbook vuoto + foglio unico
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SCHEDE DI RIPARTO DEI COSTI');

            // === append templates così come sono ===
            $disk = Storage::disk('public');
            $paths = [
                'km'        => 'documenti/template_excel/KmPercorsi.xlsx',
                'servizi'   => 'documenti/template_excel/ServiziSvolti.xlsx',
                'ricavi'    => 'documenti/template_excel/Costi_Ricavi.xlsx',
                'autisti'   => 'documenti/template_excel/CostiPersonale_autisti.xlsx',
                'volontari' => 'documenti/template_excel/CostiPersonale_volontari.xlsx',
            ];
            foreach ($paths as $rel) if (!$disk->exists($rel)) throw new \RuntimeException("Manca template: $rel");

            $rowCursor = 1;
            [$kmStart, $kmEnd]             = $this->appendTemplateSheet($sheet, $disk->path($paths['km']),        $rowCursor);
            $rowCursor = $kmEnd + 2;
            [$srvStart, $srvEnd]           = $this->appendTemplateSheet($sheet, $disk->path($paths['servizi']),   $rowCursor);
            $rowCursor = $srvEnd + 2;
            [$ricStart, $ricEnd]           = $this->appendTemplateSheet($sheet, $disk->path($paths['ricavi']),    $rowCursor);
            $rowCursor = $ricEnd + 2;
            [$autStart, $autEnd]           = $this->appendTemplateSheet($sheet, $disk->path($paths['autisti']),   $rowCursor);
            $rowCursor = $autEnd + 2;
            [$volStart, $volEnd]           = $this->appendTemplateSheet($sheet, $disk->path($paths['volontari']), $rowCursor);
            $rowCursor = $volEnd + 2;

            // segnaposto su tutto il foglio
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $associazione,
                'anno_riferimento'  => $anno,
            ]);

            /* ===================== 1) KM (dentro blocco copincolla) ===================== */
            $headerRowKm = $this->findHeaderRowKm($sheet, $kmStart, $kmEnd);
            $colKm = ['PROGR' => 1, 'TARGA' => 2, 'CODICE' => 3, 'KMTOT' => 4];
            for ($c = 1; $c <= 60; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowKm)->getValue()));
                if ($t === 'TARGA') $colKm['TARGA'] = $c;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $colKm['CODICE'] = $c;
                if (str_starts_with($t, 'KM. TOTALI')) $colKm['KMTOT'] = $c;
            }
            $firstPairColKm = $colKm['KMTOT'] + 1;
            $convTitleRowKm = max($kmStart, $headerRowKm - 1);

            // quante coppie presenti nel template
            $maxPairsKm = 0;
            for ($c = $firstPairColKm; $c <= $firstPairColKm + 200; $c += 2) {
                $l = (string)$sheet->getCellByColumnAndRow($c, $headerRowKm)->getValue();
                $r = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRowKm)->getValue();
                if ($l === '' && $r === '') break;
                $maxPairsKm++;
            }
            $usedPairsKm = min($numConv, $maxPairsKm);

            // intestazioni convenzioni
            for ($i = 0; $i < $usedPairsKm; $i++) {
                $kmCol = $firstPairColKm + ($i * 2);
                $sheet->setCellValueByColumnAndRow($kmCol, $convTitleRowKm, (string)$convenzioni[$i]->Convenzione);
            }
            // nascondo colonne non usate
            for ($i = $usedPairsKm; $i < $maxPairsKm; $i++) {
                $kmCol = $firstPairColKm + ($i * 2);
                $sheet->getColumnDimension($colL($kmCol))->setVisible(false);
                $sheet->getColumnDimension($colL($kmCol + 1))->setVisible(false);
            }

            // dati
            $kmGrouped = DB::table('automezzi_km as k')
                ->join('automezzi as a', 'a.idAutomezzo', '=', 'k.idAutomezzo')
                ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
                ->where('a.idAssociazione', $idAss)->where('a.idAnno', $anno)->where('c.idAnno', $anno)
                ->select('k.idAutomezzo', 'k.idConvenzione', 'k.KMPercorsi')->get()
                ->groupBy(fn($r) => $r->idAutomezzo . '-' . $r->idConvenzione);

            $startKm = $headerRowKm + 1;
            $rowIdxKm = 0;
            $totKmAll = 0.0;
            $totKmByConv = [];
            foreach ($convenzioni as $conv) $totKmByConv['c' . $conv->idConvenzione] = 0.0;

            foreach ($automezzi as $a) {
                $row = $startKm + $rowIdxKm++;
                $kmTot = 0.0;
                foreach ($convenzioni as $c) {
                    $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                    if ($kmGrouped->has($key)) $kmTot += (float)$kmGrouped->get($key)->first()->KMPercorsi;
                }
                $totKmAll += $kmTot;

                $sheet->setCellValueByColumnAndRow($colKm['PROGR'], $row, 'AUTO ' . $rowIdxKm);
                $sheet->setCellValueExplicitByColumnAndRow($colKm['TARGA'], $row, (string)$a->Targa, DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($colKm['CODICE'], $row, (string)($a->CodiceIdentificativo ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueByColumnAndRow($colKm['KMTOT'], $row, $kmTot);
                $sheet->getStyleByColumnAndRow($colKm['KMTOT'], $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                $pair = 0;
                foreach ($convenzioni as $c) {
                    if ($pair >= $usedPairsKm) break;
                    $kmCol = $firstPairColKm + ($pair * 2);
                    $pcCol = $kmCol + 1;
                    $pair++;
                    $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                    $km = $kmGrouped->has($key) ? (float)$kmGrouped->get($key)->first()->KMPercorsi : 0.0;
                    $p  = $kmTot > 0 ? ($km / $kmTot) : 0.0;
                    $sheet->setCellValueByColumnAndRow($kmCol, $row, $km);
                    $sheet->getStyleByColumnAndRow($kmCol, $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->setCellValueByColumnAndRow($pcCol, $row, $p);
                    $sheet->getStyleByColumnAndRow($pcCol, $row)->getNumberFormat()->setFormatCode('0.00%');
                    $sheet->getStyleByColumnAndRow($pcCol, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $totKmByConv['c' . $c->idConvenzione] += $km;
                }
            }

            // TOTALE + compatta/occulta righe inutilizzate del template
            $totalRowKm = $startKm + $rowIdxKm;
            $sheet->setCellValueByColumnAndRow($colKm['PROGR'], $totalRowKm, 'TOTALE');
            $sheet->setCellValueByColumnAndRow($colKm['KMTOT'], $totalRowKm, $totKmAll);
            $sheet->getStyleByColumnAndRow($colKm['KMTOT'], $totalRowKm)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            $pair = 0;
            $sum = 0.0;
            $last = max(0, $numConv - 1);
            foreach ($convenzioni as $i => $c) {
                if ($pair >= $usedPairsKm) break;
                $kmCol = $firstPairColKm + ($pair * 2);
                $pcCol = $kmCol + 1;
                $pair++;
                $v = (float)($totKmByConv['c' . $c->idConvenzione] ?? 0.0);
                $sheet->setCellValueByColumnAndRow($kmCol, $totalRowKm, $v);
                $sheet->getStyleByColumnAndRow($kmCol, $totalRowKm)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                $p = ($i < $last && $totKmAll > 0) ? ($v / $totKmAll) : max(0.0, 1.0 - $sum);
                if ($i < $last) $sum += $p;
                $sheet->setCellValueByColumnAndRow($pcCol, $totalRowKm, $p);
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowKm)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowKm)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $lastUsedColKm = max($colKm['KMTOT'], $firstPairColKm + ($usedPairsKm * 2) - 1);
            $sheet->getStyle('A' . $totalRowKm . ':' . $colL($lastUsedColKm) . $totalRowKm)->getFont()->setBold(true);

            // nascondo l’avanzo tra ultima riga dati e riga “TOTALE”, mantenendo bordi/stili del template
            $this->hideEmptyRowsBetween($sheet, $startKm + $rowIdxKm, $kmEnd - 1, 0, max(0, ($kmEnd - $totalRowKm)));

            /* ===================== 2) SERVIZI ===================== */
            $headerRowServ = $this->findHeaderRowServizi($sheet, $srvStart, $srvEnd);
            $colSrv = ['PROGR' => 1, 'TARGA' => 2, 'CODICE' => 3, 'TOTSRV' => 4];
            for ($c = 1; $c <= 60; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowServ)->getValue()));
                if ($t === 'TARGA') $colSrv['TARGA'] = $c;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $colSrv['CODICE'] = $c;
                if (str_starts_with($t, 'TOTALI NUMERO SERVIZI')) $colSrv['TOTSRV'] = $c;
            }
            $firstPairColSrv = $colSrv['TOTSRV'] + 1;
            $convTitleRowSrv = max($srvStart, $headerRowServ - 1);
            $maxPairsSrv = 0;
            for ($c = $firstPairColSrv; $c <= $firstPairColSrv + 200; $c += 2) {
                $l = (string)$sheet->getCellByColumnAndRow($c, $headerRowServ)->getValue();
                $r = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRowServ)->getValue();
                if ($l === '' && $r === '') break;
                $maxPairsSrv++;
            }
            $usedPairsSrv = min($numConv, $maxPairsSrv);
            for ($i = 0; $i < $usedPairsSrv; $i++) {
                $col = $firstPairColSrv + ($i * 2);
                $sheet->setCellValueByColumnAndRow($col, $convTitleRowSrv, (string)$convenzioni[$i]->Convenzione);
            }
            for ($i = $usedPairsSrv; $i < $maxPairsSrv; $i++) {
                $col = $firstPairColSrv + ($i * 2);
                $sheet->getColumnDimension($colL($col))->setVisible(false);
                $sheet->getColumnDimension($colL($col + 1))->setVisible(false);
            }

            $serviziGrouped = AutomezzoServiziSvolti::getGroupedByAutomezzoAndConvenzione($anno, $idAss);
            $startSrv = $headerRowServ + 1;
            $rowSrvIdx = 0;
            $totSrvAll = 0;
            $totSrvByConv = [];
            foreach ($convenzioni as $conv) $totSrvByConv['c' . $conv->idConvenzione] = 0;

            foreach ($automezzi as $a) {
                $row = $startSrv + $rowSrvIdx++;
                $totVeicolo = 0;
                foreach ($convenzioni as $c) {
                    $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                    $n = (int)($serviziGrouped->get($key)?->first()->NumeroServizi ?? 0);
                    $totVeicolo += $n;
                }
                $totSrvAll += $totVeicolo;

                $sheet->setCellValueByColumnAndRow($colSrv['PROGR'], $row, 'AUTO ' . $rowSrvIdx);
                $sheet->setCellValueByColumnAndRow($colSrv['TARGA'], $row, (string)$a->Targa);
                $sheet->setCellValueByColumnAndRow($colSrv['CODICE'], $row, (string)($a->CodiceIdentificativo ?? ''));
                $sheet->setCellValueByColumnAndRow($colSrv['TOTSRV'], $row, $totVeicolo);
                $sheet->getStyleByColumnAndRow($colSrv['TOTSRV'], $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

                $pair = 0;
                foreach ($convenzioni as $c) {
                    if ($pair >= $usedPairsSrv) break;
                    $nCol = $firstPairColSrv + ($pair * 2);
                    $pcCol = $nCol + 1;
                    $pair++;

                    $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                    $n = (int)($serviziGrouped->get($key)?->first()->NumeroServizi ?? 0);
                    $p = $totVeicolo > 0 ? ($n / $totVeicolo) : 0.0;

                    $sheet->setCellValueByColumnAndRow($nCol, $row, $n);
                    $sheet->getStyleByColumnAndRow($nCol, $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                    $sheet->setCellValueByColumnAndRow($pcCol, $row, $p);
                    $sheet->getStyleByColumnAndRow($pcCol, $row)->getNumberFormat()->setFormatCode('0.00%');
                    $sheet->getStyleByColumnAndRow($pcCol, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $totSrvByConv['c' . $c->idConvenzione] += $n;
                }
            }

            $totalRowSrv = $startSrv + $rowSrvIdx;
            $sheet->setCellValueByColumnAndRow($colSrv['PROGR'], $totalRowSrv, 'TOTALE');
            $sheet->setCellValueByColumnAndRow($colSrv['TOTSRV'], $totalRowSrv, $totSrvAll);
            $sheet->getStyleByColumnAndRow($colSrv['TOTSRV'], $totalRowSrv)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $pair = 0;
            $sum = 0.0;
            $last = max(0, $numConv - 1);
            foreach ($convenzioni as $i => $c) {
                if ($pair >= $usedPairsSrv) break;
                $nCol = $firstPairColSrv + ($pair * 2);
                $pcCol = $nCol + 1;
                $pair++;
                $v = (int)($totSrvByConv['c' . $c->idConvenzione] ?? 0);
                $sheet->setCellValueByColumnAndRow($nCol, $totalRowSrv, $v);
                $p = ($i < $last && $totSrvAll > 0) ? ($v / $totSrvAll) : max(0.0, 1.0 - $sum);
                if ($i < $last) $sum += $p;
                $sheet->setCellValueByColumnAndRow($pcCol, $totalRowSrv, $p);
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowSrv)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowSrv)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $lastUsedColSrv = max($colSrv['TOTSRV'], $firstPairColSrv + ($usedPairsSrv * 2) - 1);
            $sheet->getStyle('A' . $totalRowSrv . ':' . $colL($lastUsedColSrv) . $totalRowSrv)->getFont()->setBold(true);

            $this->hideEmptyRowsBetween($sheet, $startSrv + $rowSrvIdx, $srvEnd - 1, 0, max(0, ($srvEnd - $totalRowSrv)));

            /* ===================== 3) RICAVI ===================== */
            $ricHead = $this->locateRicaviHeader($sheet, $ricStart);
            $headerRowRic    = $ricHead['headerRow'];
            $convTitleRowRic = $ricHead['convTitleRow'];
            $dataRowRic      = $ricHead['dataRow'];
            $firstPairColRic = $ricHead['firstPairCol'];
            $totCol          = $ricHead['totCol'];

            $maxPairsRic = 0;
            for ($c = $firstPairColRic; $c <= $firstPairColRic + 200; $c += 2) {
                $hdrL = (string)$sheet->getCellByColumnAndRow($c, $headerRowRic)->getValue();
                $hdrR = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRowRic)->getValue();
                $ttl  = (string)$sheet->getCellByColumnAndRow($c, $convTitleRowRic)->getValue();
                if ($hdrL === '' && $hdrR === '' && $ttl === '') break;
                $maxPairsRic++;
            }
            if ($maxPairsRic === 0) $maxPairsRic = $numConv;
            $usedPairsRic = min($numConv, $maxPairsRic);

            for ($i = 0; $i < $usedPairsRic; $i++) {
                $rCol = $firstPairColRic + ($i * 2);
                $sheet->setCellValueByColumnAndRow($rCol, $convTitleRowRic, (string)$convenzioni[$i]->Convenzione);
            }
            for ($i = $usedPairsRic; $i < $maxPairsRic; $i++) {
                $rCol = $firstPairColRic + ($i * 2);
                $sheet->getColumnDimension($colL($rCol))->setVisible(false);
                $sheet->getColumnDimension($colL($rCol + 1))->setVisible(false);
            }

            $ricaviMap = RapportoRicavo::mapByAssociazione($anno, $idAss);
            $totRicavi = array_sum(array_map('floatval', $ricaviMap));
            $sheet->setCellValueByColumnAndRow($totCol, $dataRowRic, $totRicavi);
            $sheet->getStyleByColumnAndRow($totCol, $dataRowRic)->getNumberFormat()->setFormatCode('#,##0.00');

            $pair = 0;
            $sum = 0.0;
            $last = max(0, $numConv - 1);
            foreach ($convenzioni as $i => $c) {
                if ($pair >= $usedPairsRic) break;
                $rCol = $firstPairColRic + ($pair * 2);
                $pCol = $rCol + 1;
                $pair++;
                $rimborso = (float)($ricaviMap[$c->idConvenzione] ?? 0.0);
                $sheet->setCellValueByColumnAndRow($rCol, $dataRowRic, $rimborso);
                $sheet->getStyleByColumnAndRow($rCol, $dataRowRic)->getNumberFormat()->setFormatCode('#,##0.00');

                $p = ($i < $last && $totRicavi > 0) ? ($rimborso / $totRicavi) : max(0.0, 1.0 - $sum);
                if ($i < $last) $sum += $p;
                $sheet->setCellValueByColumnAndRow($pCol, $dataRowRic, $p);
                $sheet->getStyleByColumnAndRow($pCol, $dataRowRic)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyleByColumnAndRow($pCol, $dataRowRic)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $this->cutAfterTotalRow($sheet, $dataRowRic, 50); // compatta fine blocco ricavi

            /* ===================== 4) PERSONALE AUTISTI (6001) ===================== */
            $headerRowPers = $this->findHeaderRowPersonale($sheet, $autStart, $autEnd);
            $colP = ['PROGR' => 1, 'NOME' => 2, 'TOT' => 3];
            for ($c = 1; $c <= 100; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowPers)->getValue()));
                if ($t === 'COGNOME DEL DIPENDENTE') $colP['NOME'] = $c;
                if (str_starts_with($t, 'ORE TOTALI ANNUE')) $colP['TOT'] = $c;
            }
            $colP['PROGR'] = max(1, $colP['NOME'] - 1);
            $pairColsPers  = $this->getPairLeftCols($sheet, $headerRowPers);
            $usedPairsPers = min($convenzioni->count(), count($pairColsPers));
            $convTitleRowPers = max($autStart, $headerRowPers - 1);

            for ($i = 0; $i < $usedPairsPers; $i++) {
                $col = $pairColsPers[$i];
                $sheet->setCellValueByColumnAndRow($col, $convTitleRowPers, (string)$convenzioni[$i]->Convenzione);
            }
            for ($i = $usedPairsPers; $i < count($pairColsPers); $i++) {
                $col = $pairColsPers[$i];
                $sheet->getColumnDimension($colL($col))->setVisible(false);
                $sheet->getColumnDimension($colL($col + 1))->setVisible(false);
            }

            // PREV/CONS
            $prevRow  = $headerRowPers + 1;
            $consuRow = $prevRow + 1;
            $startRow = $consuRow + 2;

            $dist = RipartizioneCostiService::distintaImputazioneData($idAss, $anno);
            $consuPerConv = [];
            if (!empty($dist['data'])) {
                foreach ($dist['data'] as $r) {
                    if ((int)($r['idVoceConfig'] ?? 0) === 6001) {
                        foreach ($convenzioni as $conv) {
                            $nome = $conv->Convenzione;
                            $dir  = (float)($r[$nome]['diretti']   ?? 0);
                            $ind  = (float)($r[$nome]['indiretti'] ?? 0);
                            $consuPerConv[(int)$conv->idConvenzione] = round($dir + $ind, 2);
                        }
                        break;
                    }
                }
            }
            $prevPerConv = DB::table('costi_diretti')
                ->select('idConvenzione', DB::raw('SUM(COALESCE(bilancio_consuntivo,0)) as prev'))
                ->where('idAssociazione', $idAss)->where('idAnno', $anno)->where('idVoceConfig', 6001)
                ->groupBy('idConvenzione')->pluck('prev', 'idConvenzione')->toArray();

            for ($i = 0; $i < $usedPairsPers; $i++) {
                $cId = (int)$convenzioni[$i]->idConvenzione;
                $col = $pairColsPers[$i];
                $sheet->setCellValueByColumnAndRow($col, $prevRow,  (float)($prevPerConv[$cId]  ?? 0));
                $sheet->getStyleByColumnAndRow($col, $prevRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->setCellValueByColumnAndRow($col, $consuRow, (float)($consuPerConv[$cId] ?? 0));
                $sheet->getStyleByColumnAndRow($col, $consuRow)->getNumberFormat()->setFormatCode('#,##0.00');
            }

            // dati dipendenti
            $dip = DB::table('dipendenti as d')
                ->join('dipendenti_qualifiche as dq', 'dq.idDipendente', '=', 'd.idDipendente')
                ->join('qualifiche as q', 'q.id', '=', 'dq.idQualifica')
                ->where('d.idAssociazione', $idAss)->where('d.idAnno', $anno)
                ->where(function ($w) {
                    $w->whereRaw('LOWER(q.nome) LIKE ?', ['%autist%'])
                        ->orWhereRaw('LOWER(q.nome) LIKE ?', ['%barell%']);
                })
                ->select('d.idDipendente', 'd.DipendenteCognome as Cognome', 'd.DipendenteNome as Nome')
                ->distinct()->orderBy('d.idDipendente')->get();

            $oreRaw = DB::table('dipendenti_servizi as ds')
                ->join('dipendenti as d', 'd.idDipendente', '=', 'ds.idDipendente')
                ->where('d.idAssociazione', $idAss)->where('d.idAnno', $anno)
                ->select('ds.idDipendente', 'ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as ore'))
                ->groupBy('ds.idDipendente', 'ds.idConvenzione')->get()->groupBy('idDipendente');

            $rowIdx = 0;
            $totOreAll = 0;
            $totPerConv = array_fill_keys($convenzioni->pluck('idConvenzione')->map(fn($x) => (int)$x)->all(), 0);

            foreach ($dip as $dRow) {
                $r   = $startRow + $rowIdx++;
                $ore = $oreRaw->get($dRow->idDipendente, collect());
                $map = [];
                $sum = 0;
                foreach ($convenzioni as $conv) {
                    $idc = (int)$conv->idConvenzione;
                    $val = (float)($ore->firstWhere('idConvenzione', $idc)->ore ?? 0);
                    $map[$idc] = $val;
                    $sum += $val;
                    $totPerConv[$idc] += $val;
                }
                $totOreAll += $sum;

                $sheet->setCellValueByColumnAndRow($colP['PROGR'], $r, 'DIPENDENTE N. ' . ($rowIdx));
                $sheet->setCellValueByColumnAndRow($colP['NOME'],  $r, (string)$dRow->Cognome);
                $sheet->setCellValueByColumnAndRow($colP['TOT'],   $r, $sum);

                for ($i = 0; $i < $usedPairsPers; $i++) {
                    $nCol = $pairColsPers[$i];
                    $pCol = $nCol + 1;
                    $v = (float)$map[(int)$convenzioni[$i]->idConvenzione];
                    $p = $sum > 0 ? ($v / $sum) : 0.0;
                    $sheet->setCellValueByColumnAndRow($nCol, $r, $v);
                    $sheet->setCellValueByColumnAndRow($pCol, $r, $p);
                    $sheet->getStyleByColumnAndRow($pCol, $r)->getNumberFormat()->setFormatCode('0.00%');
                }
            }
            $totalRowPers = $startRow + $rowIdx;
            $sheet->setCellValueByColumnAndRow($colP['PROGR'], $totalRowPers, 'Totale');
            $sheet->setCellValueByColumnAndRow($colP['NOME'],  $totalRowPers, '');
            $sheet->setCellValueByColumnAndRow($colP['TOT'],   $totalRowPers, $totOreAll);

            $percSum = 0.0;
            $lastIdx = max(0, $usedPairsPers - 1);
            for ($i = 0; $i < $usedPairsPers; $i++) {
                $nCol = $pairColsPers[$i];
                $pCol = $nCol + 1;
                $v = (float)$totPerConv[(int)$convenzioni[$i]->idConvenzione];
                $sheet->setCellValueByColumnAndRow($nCol, $totalRowPers, $v);
                $p = ($i < $lastIdx && $totOreAll > 0) ? ($v / $totOreAll) : max(0.0, 1.0 - $percSum);
                if ($i < $lastIdx) $percSum += $p;
                $sheet->setCellValueByColumnAndRow($pCol, $totalRowPers, $p);
                $sheet->getStyleByColumnAndRow($pCol, $totalRowPers)->getNumberFormat()->setFormatCode('0.00%');
            }
            $lastUsedColPers = max($colP['TOT'], ($pairColsPers[$usedPairsPers - 1] ?? $colP['TOT']) + 1);
            $sheet->getStyle('A' . $totalRowPers . ':' . $colL($lastUsedColPers) . $totalRowPers)->getFont()->setBold(true);

            $this->hideEmptyRowsBetween($sheet, $startRow + $rowIdx, $autEnd - 1, 0, max(0, ($autEnd - $totalRowPers)));

            /* ===================== 5) PERSONALE VOLONTARI ===================== */
            $found = $this->findVolontariHeader($sheet, $volStart);
            $headerRowVol    = $found['headerRow'];
            $convTitleRowVol = $found['convTitleRow'];
            $pairColsVol     = $this->getPairLeftCols($sheet, $headerRowVol);
            $usedPairsVol    = min($convenzioni->count(), count($pairColsVol));

            for ($i = 0; $i < $usedPairsVol; $i++) {
                $col = $pairColsVol[$i];
                $sheet->setCellValueByColumnAndRow($col, $convTitleRowVol, (string)$convenzioni[$i]->Convenzione);
            }
            for ($i = $usedPairsVol; $i < count($pairColsVol); $i++) {
                $col = $pairColsVol[$i];
                $sheet->getColumnDimension($colL($col))->setVisible(false);
                $sheet->getColumnDimension($colL($col + 1))->setVisible(false);
            }

            $dataRowVol  = $headerRowVol + 1;
            $totalRowVol = $dataRowVol + 1;
            $sheet->setCellValueByColumnAndRow(1, $dataRowVol, 'SERVIZIO VOLONTARIO');

            $serviziByConv = DB::table('automezzi_servizi as s')
                ->join('automezzi as a', 'a.idAutomezzo', '=', 's.idAutomezzo')
                ->where('a.idAssociazione', $idAss)->where('a.idAnno', $anno)
                ->whereIn('s.idConvenzione', $convenzioni->pluck('idConvenzione'))
                ->select('s.idConvenzione', DB::raw('SUM(s.NumeroServizi) AS n'))
                ->groupBy('s.idConvenzione')
                ->pluck('n', 's.idConvenzione')
                ->toArray();

            $totServiziAss = 0.0;
            foreach ($convenzioni as $c) $totServiziAss += (float)($serviziByConv[$c->idConvenzione] ?? 0);

            $sumPerc = 0.0;
            $lastIdx = max(0, $usedPairsVol - 1);
            for ($i = 0; $i < $usedPairsVol; $i++) {
                $col = $pairColsVol[$i];
                $idC = (int)$convenzioni[$i]->idConvenzione;
                $ore = (float)($serviziByConv[$idC] ?? 0.0);
                $sheet->setCellValueByColumnAndRow($col, $dataRowVol, $ore);
                $p = ($i < $lastIdx && $totServiziAss > 0) ? ($ore / $totServiziAss) : max(0.0, 1.0 - $sumPerc);
                if ($i < $lastIdx) $sumPerc += $p;
                $sheet->setCellValueByColumnAndRow($col + 1, $dataRowVol, $p);
                $sheet->getStyleByColumnAndRow($col + 1, $dataRowVol)->getNumberFormat()->setFormatCode('0.00%');

                // riga totale
                $sheet->setCellValueByColumnAndRow($col,     $totalRowVol, $ore);
                $sheet->setCellValueByColumnAndRow($col + 1, $totalRowVol, $p);
                $sheet->getStyleByColumnAndRow($col + 1, $totalRowVol)->getNumberFormat()->setFormatCode('0.00%');
            }
            $lastUsedColVol = max(($pairColsVol[$usedPairsVol - 1] ?? 1) + 1, 2);
            $sheet->getStyle('A' . $totalRowVol . ':' . $colL($lastUsedColVol) . $totalRowVol)->getFont()->setBold(true);
            $this->cutAfterTotalRow($sheet, $totalRowVol, 600);

            /* ===== SALVATAGGIO ===== */
            $slug = $this->slugify($associazione);
            $baseFilename = sprintf('SCHEDE_RIPARTO_COSTI_%s_%d.xlsx', $slug, $anno);
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
            Log::error('SchedeRipartoCosti: exception', [
                'documentoId'     => $this->documentoId,
                'idAssociazione'  => $this->idAssociazione,
                'anno'            => $this->anno,
                'msg'             => $e->getMessage(),
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
                'trace'           => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function cleanupOldGeneratedFiles(): void {
        try {
            $cutoff = now()->subDays(7);
            $disk = Storage::disk('public');
            $oldDocs = DB::table('documenti_generati')->whereIn('stato', ['ready', 'error'])->where(function ($q) use ($cutoff) {
                $q->where(function ($q) use ($cutoff) {
                    $q->where('stato', 'ready')->whereNotNull('generato_il')->where('generato_il', '<', $cutoff);
                })->orWhere(function ($q) use ($cutoff) {
                    $q->where('stato', 'error')->whereNotNull('updated_at')->where('updated_at', '<', $cutoff);
                });
            })->get(['id', 'percorso_file', 'stato']);
            $deletedFiles = 0;
            foreach ($oldDocs as $doc) {
                if (!empty($doc->percorso_file) && $disk->exists($doc->percorso_file)) {
                    if ($disk->delete($doc->percorso_file)) $deletedFiles++;
                }
                DB::table('documenti_generati')->where('id', $doc->id)->delete();
            }
            Log::info('Cleanup documenti generati (7 giorni) completato', ['cutoff' => $cutoff->toDateTimeString(), 'rimossi_db' => $oldDocs->count(), 'file_cancellati' => $deletedFiles,]);
        } catch (\Throwable $e) {
            Log::warning('Errore cleanup vecchi documenti: ' . $e->getMessage());
        }
    }
    public function failed(Throwable $e): void {
        Log::error('GeneraSchedeRipartoCostiXlsJob failed', [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
            'error'          => $e->getMessage(),
            'file'           => $e->getFile(),
            'line'           => $e->getLine(),
            'trace'          => $e->getTraceAsString(),
        ]);

        DB::table('documenti_generati')->where('id', $this->documentoId)
            ->update(['stato' => 'error', 'updated_at' => now()]);
    }
}
