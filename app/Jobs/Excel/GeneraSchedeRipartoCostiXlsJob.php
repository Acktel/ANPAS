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

use App\Services\RipartizioneCostiService;
use App\Models\Automezzo;
use App\Models\AutomezzoServiziSvolti;
use App\Models\RapportoRicavo;

class GeneraSchedeRipartoCostiXlsJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $tries = 1;
    public $backoff = 0;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('excel');
    }

    public function middleware(): array {
        $key = "xls-schede-riparto-{$this->idAssociazione}-{$this->anno}";
        return [(new WithoutOverlapping($key))->expireAfter(300)];
    }

    /* ============ Helpers ============ */

    private function slugify(string $s): string {
        $s = preg_replace('/[^\pL\d]+/u', '_', $s);
        $s = trim($s, '_');
        $s = preg_replace('/_+/', '_', $s);
        return strtolower($s ?: 'export');
    }

    private function findHeaderRowKm(Worksheet $sheet): int {
        for ($r = 1; $r <= 200; $r++) {
            $hasT = $hasC = $hasK = false;
            for ($c = 1; $c <= 60; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'TARGA') $hasT = true;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $hasC = true;
                if (str_starts_with($t, 'KM. TOTALI')) $hasK = true;
            }
            if ($hasT && $hasC && $hasK) return $r;
        }
        return 8;
    }

    private function findHeaderRowServizi(Worksheet $sheet, int $startFromRow = 1): int {
        for ($r = max(1, $startFromRow); $r <= $startFromRow + 300; $r++) {
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

    /** Header della tabella PERSONALE: “COGNOME DEL DIPENDENTE / ORE TOTALI ANNUE ...” */
    private function findHeaderRowPersonale(Worksheet $sheet, int $startFromRow = 1): int {
        for ($r = max(1, $startFromRow); $r <= $startFromRow + 400; $r++) {
            $hasName = $hasTot = false;
            for ($c = 1; $c <= 80; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t === 'COGNOME DEL DIPENDENTE') $hasName = true;
                if (str_starts_with($t, 'ORE TOTALI ANNUE')) $hasTot = true;
            }
            if ($hasName && $hasTot) return $r;
        }
        return $startFromRow + 60; // fallback prudente
    }

    /** Trova le righe "PREV" e "CONSU/CONSUNTIVO" sopra il titolo viola
     *  della tabella Personale. Cerca in una finestra ampia e applica un
     *  fallback (due righe sopra al titolo) se non trova etichette testuali.
     */
    /** Trova righe e colonna etichetta di PREV / CONSU sopra la tabella Personale. */
    private function findPrevConsuRows(Worksheet $sheet, int $convTitleRow): array {
        $prevRow = null;
        $consuRow = null;
        $labelColPrev = 1;
        $labelColConsu = 1;

        // cerca da 1 a 40 righe sopra il titolo viola
        $from = max(1, $convTitleRow - 40);
        for ($r = $convTitleRow - 1; $r >= $from; $r--) {
            for ($c = 1; $c <= 30; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if (!$prevRow && ($t === 'PREV' || $t === 'PREVENTIVO')) {
                    $prevRow = $r;
                    $labelColPrev = $c;
                }
                if (!$consuRow && ($t === 'CONSU' || $t === 'CONSUNTIVO')) {
                    $consuRow = $r;
                    $labelColConsu = $c;
                }
            }
            if ($prevRow && $consuRow) break;
        }

        // fallback sicuro: due righe appena sopra il titolo
        if (!$consuRow) {
            $consuRow = max(1, $convTitleRow - 2);
            $labelColConsu = 1;
        }
        if (!$prevRow) {
            $prevRow  = max(1, $consuRow - 1);
            $labelColPrev  = 1;
        }

        if ($prevRow > $consuRow) {
            [$prevRow, $consuRow] = [$consuRow, $prevRow];
        }

        return [$prevRow, $consuRow, $labelColPrev, $labelColConsu];
    }


    /** Nuovo: individua header della 3ª tabella a partire dal testo “RIMBORSO”. */
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
        // fallback: usa il vecchio “TOTALE RICAVI”
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
            'headerRow' => $hr,
            'convTitleRow' => max(1, $hr - 1),
            'dataRow' => $hr + 1,
            'firstPairCol' => $tot + 1,
            'totCol' => $tot,
        ];
    }

    private function findHeaderRowRicavi(Worksheet $sheet, int $startFromRow = 1): int {
        for ($r = max(1, $startFromRow); $r <= $startFromRow + 300; $r++) {
            for ($c = 1; $c <= 80; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($t !== '' && str_starts_with($t, 'TOTALE RICAVI')) return $r;
            }
        }
        return $startFromRow + 20;
    }

    /** Nuovo: sostituisce i segnaposto sull’intero foglio (non solo in alto). */
    private function replacePlaceholdersEverywhere(Worksheet $sheet, array $map): void {
        $src = ['{{nome_associazione}}', '{{ nome_associazione }}', '{{anno_riferimento}}', '{{ anno_riferimento }}'];
        $dst = [
            (string)($map['nome_associazione'] ?? ''),
            (string)($map['nome_associazione'] ?? ''),
            (string)($map['anno_riferimento'] ?? ''),
            (string)($map['anno_riferimento'] ?? ''),
        ];
        $maxRow = $sheet->getHighestRow();
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($r = 1; $r <= $maxRow; $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $val  = $cell->getValue();
                if (!is_string($val) || $val === '') continue;
                $new = str_replace($src, $dst, $val);
                if ($new !== $val) $cell->setValueExplicit($new, DataType::TYPE_STRING);
            }
        }
    }

    /** Nasconde colonne di convenzioni in eccesso (non tocca i bordi, solo visibilità). */
    private function hideUnusedConvColumns(Worksheet $sheet, int $firstPairCol, int $usedPairs, int $maxPairs): void {
        $startHide = $firstPairCol + ($usedPairs * 2);
        $endHide   = $firstPairCol + ($maxPairs * 2) - 1;
        for ($c = $startHide; $c <= $endHide; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($letter)->setVisible(false);
        }
    }

    /** Nasconde righe vuote tra fine tabella corrente e l’inizio della prossima. */
    /** Nasconde solo le righe completamente vuote tra due blocchi,
     *  lasciando visibili almeno N righe di spazio sopra/sotto. */
    private function hideEmptyRowsBetween(Worksheet $sheet, int $fromRow, int $toRow, int $keepTop = 2, int $keepBottom = 2): void {
        if ($toRow <= $fromRow) return;

        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($r = $fromRow; $r <= $toRow; $r++) {
            // conserva un po’ di aria all’inizio/fine
            if (($r - $fromRow) < $keepTop || ($toRow - $r) < $keepBottom) {
                $sheet->getRowDimension($r)->setVisible(true);
                continue;
            }

            // riga “vuota” = tutte le celle senza valore
            $empty = true;
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                if ($v !== null && $v !== '' && $v !== 0 && $v !== '0') {
                    $empty = false;
                    break;
                }
            }

            // i titoli viola hanno testo -> resteranno visibili
            $sheet->getRowDimension($r)->setVisible($empty ? false : true);
        }
    }

    /* ============ Handle ============ */
    /* ============ Handle ============ */
    public function handle(): void {
        try {
            DB::table('documenti_generati')->where('id', $this->documentoId)
                ->update(['stato' => 'processing', 'updated_at' => now()]);

            $templateRel = 'documenti/template_excel/DISTINTAIMPUTAZIONE.xlsx';
            if (!Storage::disk('public')->exists($templateRel)) {
                throw new \RuntimeException('Manca DISTINTAIMPUTAZIONE.xlsx (usa XLSX).');
            }
            $templateAbs = Storage::disk('public')->path($templateRel);

            $anno  = $this->anno;
            $idAss = $this->idAssociazione;

            $automezzi   = Automezzo::getByAssociazione($idAss, $anno)->sortBy('idAutomezzo')->values();
            $convenzioni = DB::table('convenzioni')->select('idConvenzione', 'Convenzione')
                ->where('idAssociazione', $idAss)->where('idAnno', $anno)
                ->orderBy('ordinamento')->orderBy('idConvenzione')->get()->values();
            $numConv = $convenzioni->count();

            // Reader in modalità completa (con stili/merge)
            $firstFixedCols = 4;
            $maxColIdx = max(120, $firstFixedCols + ($numConv * 2) + 20);
            $maxRow    = 1200;
            $filter = new class($maxRow, $maxColIdx) implements IReadFilter {
                public function __construct(private int $maxRow, private int $maxColIdx) {
                }
                public function readCell($columnAddress, $row, $worksheetName = '') {
                    if ($row < 1 || $row > $this->maxRow) return false;
                    $colIdx = Coordinate::columnIndexFromString($columnAddress);
                    return $colIdx >= 1 && $colIdx <= $this->maxColIdx;
                }
            };

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $reader->setLoadSheetsOnly(['SCHEDE DI RIPARTO DEI COSTI']);
            $reader->setReadFilter($filter);

            $spreadsheet = $reader->load($templateAbs);
            $sheet       = $spreadsheet->getActiveSheet();

            /* Segnaposto OVUNQUE */
            $associazione = (string) DB::table('associazioni')->where('idAssociazione', $idAss)->value('Associazione') ?? '';
            $this->replacePlaceholdersEverywhere($sheet, [
                'nome_associazione' => $associazione,
                'anno_riferimento'  => $anno,
            ]);

            /* ===================== 1) KM ===================== */
            $headerRowKm = $this->findHeaderRowKm($sheet);
            $colKm = ['PROGR' => 1, 'TARGA' => 2, 'CODICE' => 3, 'KMTOT' => 4];
            for ($c = 1; $c <= 60; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowKm)->getValue()));
                if ($t === 'TARGA') $colKm['TARGA'] = $c;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $colKm['CODICE'] = $c;
                if (str_starts_with($t, 'KM. TOTALI')) $colKm['KMTOT'] = $c;
            }
            $firstPairColKm = $colKm['KMTOT'] + 1;
            $convTitleRowKm = max(1, $headerRowKm - 1);

            $maxPairsKm = 0;
            for ($c = $firstPairColKm; $c <= $firstPairColKm + 200; $c += 2) {
                $l = (string)$sheet->getCellByColumnAndRow($c, $headerRowKm)->getValue();
                $r = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRowKm)->getValue();
                if ($l === '' && $r === '') break;
                $maxPairsKm++;
            }
            $usedPairsKm = min($numConv, $maxPairsKm);

            for ($i = 0; $i < $usedPairsKm; $i++) {
                $kmCol = $firstPairColKm + ($i * 2);
                $sheet->setCellValueByColumnAndRow($kmCol, $convTitleRowKm, (string)$convenzioni[$i]->Convenzione);
                if (trim((string)$sheet->getCellByColumnAndRow($kmCol, $headerRowKm)->getValue()) === '') {
                    $sheet->setCellValueByColumnAndRow($kmCol, $headerRowKm, 'KM. PERCORSI');
                }
                if (trim((string)$sheet->getCellByColumnAndRow($kmCol + 1, $headerRowKm)->getValue()) === '') {
                    $sheet->setCellValueByColumnAndRow($kmCol + 1, $headerRowKm, '%');
                }
            }
            for ($i = $usedPairsKm; $i < $maxPairsKm; $i++) {
                $kmCol = $firstPairColKm + ($i * 2);
                $sheet->setCellValueExplicitByColumnAndRow($kmCol, $convTitleRowKm, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($kmCol + 1, $convTitleRowKm, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($kmCol, $headerRowKm, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($kmCol + 1, $headerRowKm, '', DataType::TYPE_STRING);
            }
            $this->hideUnusedConvColumns($sheet, $firstPairColKm, $usedPairsKm, $maxPairsKm);

            // Dati KM
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
                    // scrivo % solo se la cella NON ha formula
                    $pcCell = $sheet->getCellByColumnAndRow($pcCol, $row);
                    $cur = (string)$pcCell->getValue();
                    if ($cur === '' || $cur[0] !== '=') {
                        $pcCell->setValue($p);
                    }
                    $sheet->getStyleByColumnAndRow($pcCol, $row)->getNumberFormat()->setFormatCode('0.00%');
                    $sheet->getStyleByColumnAndRow($pcCol, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $totKmByConv['c' . $c->idConvenzione] += $km;
                }
            }
            // Totali riga KM
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
                // rispetta eventuale formula già presente
                $pcCell = $sheet->getCellByColumnAndRow($pcCol, $totalRowKm);
                if (!is_string($pcCell->getValue()) || $pcCell->getValue()[0] !== '=') {
                    $pcCell->setValue($p);
                }
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowKm)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowKm)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $lastUsedColKm = max($colKm['KMTOT'], $firstPairColKm + ($usedPairsKm * 2) - 1);
            $sheet->getStyle('A' . $totalRowKm . ':' . Coordinate::stringFromColumnIndex($lastUsedColKm) . $totalRowKm)->getFont()->setBold(true);

            /* Spazio tra KM e SERVIZI */
            $headerRowServ = $this->findHeaderRowServizi($sheet, $totalRowKm + 2);
            $this->hideEmptyRowsBetween($sheet, $totalRowKm + 1, $headerRowServ - 1, 2, 2);

            /* ===================== 2) SERVIZI ===================== */
            $colSrv = ['PROGR' => 1, 'TARGA' => 2, 'CODICE' => 3, 'TOTSRV' => 4];
            for ($c = 1; $c <= 60; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowServ)->getValue()));
                if ($t === 'TARGA') $colSrv['TARGA'] = $c;
                if ($t === 'CODICE IDENTIFICATIVO' || $t === 'CODICE IDENTIFICATIVO IVO') $colSrv['CODICE'] = $c;
                if (str_starts_with($t, 'TOTALI NUMERO SERVIZI')) $colSrv['TOTSRV'] = $c;
            }
            $firstPairColSrv = $colSrv['TOTSRV'] + 1;
            $convTitleRowSrv = max(1, $headerRowServ - 1);

            $maxPairsSrv = 0;
            for ($c = $firstPairColSrv; $c <= $firstPairColSrv + 200; $c += 2) {
                $l = (string)$sheet->getCellByColumnAndRow($c, $headerRowServ)->getValue();
                $r = (string)$sheet->getCellByColumnAndRow($c + 1, $headerRowServ)->getValue();
                if ($l === '' && $r === '') break;
                $maxPairsSrv++;
            }
            $usedPairsSrv = min($numConv, $maxPairsSrv);

            for ($i = 0; $i < $usedPairsSrv; $i++) {
                $nCol = $firstPairColSrv + ($i * 2);
                $sheet->setCellValueByColumnAndRow($nCol, $convTitleRowSrv, (string)$convenzioni[$i]->Convenzione);
                if (trim((string)$sheet->getCellByColumnAndRow($nCol, $headerRowServ)->getValue()) === '')
                    $sheet->setCellValueByColumnAndRow($nCol, $headerRowServ, 'N. SERVIZI SVOLTI');
                if (trim((string)$sheet->getCellByColumnAndRow($nCol + 1, $headerRowServ)->getValue()) === '')
                    $sheet->setCellValueByColumnAndRow($nCol + 1, $headerRowServ, '%');
            }
            for ($i = $usedPairsSrv; $i < $maxPairsSrv; $i++) {
                $nCol = $firstPairColSrv + ($i * 2);
                $sheet->setCellValueExplicitByColumnAndRow($nCol, $convTitleRowSrv, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($nCol + 1, $convTitleRowSrv, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($nCol, $headerRowServ, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($nCol + 1, $headerRowServ, '', DataType::TYPE_STRING);
            }
            $this->hideUnusedConvColumns($sheet, $firstPairColSrv, $usedPairsSrv, $maxPairsSrv);

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
                $sheet->setCellValueExplicitByColumnAndRow($colSrv['TARGA'], $row, (string)$a->Targa, DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($colSrv['CODICE'], $row, (string)($a->CodiceIdentificativo ?? ''), DataType::TYPE_STRING);
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
                    // scrivi % solo se la cella non ha formula
                    $pcCell = $sheet->getCellByColumnAndRow($pcCol, $row);
                    $cur = (string)$pcCell->getValue();
                    if ($cur === '' || $cur[0] !== '=') $pcCell->setValue($p);
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
                $sheet->getStyleByColumnAndRow($nCol, $totalRowSrv)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                $p = ($i < $last && $totSrvAll > 0) ? ($v / $totSrvAll) : max(0.0, 1.0 - $sum);
                if ($i < $last) $sum += $p;
                $pcCell = $sheet->getCellByColumnAndRow($pcCol, $totalRowSrv);
                if (!is_string($pcCell->getValue()) || $pcCell->getValue()[0] !== '=') $pcCell->setValue($p);
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowSrv)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyleByColumnAndRow($pcCol, $totalRowSrv)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $lastUsedColSrv = max($colSrv['TOTSRV'], $firstPairColSrv + ($usedPairsSrv * 2) - 1);
            $sheet->getStyle('A' . $totalRowSrv . ':' . Coordinate::stringFromColumnIndex($lastUsedColSrv) . $totalRowSrv)->getFont()->setBold(true);

            /* Spazio tra SERVIZI e RICAVI */
            $ricHead = $this->locateRicaviHeader($sheet, $totalRowSrv + 2);
            $this->hideEmptyRowsBetween($sheet, $totalRowSrv + 1, $ricHead['headerRow'] - 1, 2, 2);

            /* ===================== 3) IMPUTAZIONE RICAVI ===================== */
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
                if (trim((string)$sheet->getCellByColumnAndRow($rCol, $headerRowRic)->getValue()) === '') $sheet->setCellValueByColumnAndRow($rCol, $headerRowRic, 'RIMBORSO');
                if (trim((string)$sheet->getCellByColumnAndRow($rCol + 1, $headerRowRic)->getValue()) === '') $sheet->setCellValueByColumnAndRow($rCol + 1, $headerRowRic, '%');
            }
            for ($i = $usedPairsRic; $i < $maxPairsRic; $i++) {
                $rCol = $firstPairColRic + ($i * 2);
                $sheet->setCellValueExplicitByColumnAndRow($rCol, $convTitleRowRic, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($rCol + 1, $convTitleRowRic, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($rCol, $headerRowRic, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($rCol + 1, $headerRowRic, '', DataType::TYPE_STRING);
            }
            $this->hideUnusedConvColumns($sheet, $firstPairColRic, $usedPairsRic, $maxPairsRic);

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
                $pcCell = $sheet->getCellByColumnAndRow($pCol, $dataRowRic);
                if (!is_string($pcCell->getValue()) || $pcCell->getValue()[0] !== '=') $pcCell->setValue($p);
                $sheet->getStyleByColumnAndRow($pCol, $dataRowRic)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyleByColumnAndRow($pCol, $dataRowRic)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            /* ===================== 4) PERSONALE DIPENDENTE ===================== */
            $headerRowPers = $this->findHeaderRowPersonale($sheet, ($dataRowRic ?? 1) + 4);

            // colonne fisse
            $colP = ['PROGR' => 1, 'NOME' => 2, 'TOT' => 3];
            for ($c = 1; $c <= 100; $c++) {
                $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowPers)->getValue()));
                if ($t === 'COGNOME DEL DIPENDENTE') $colP['NOME'] = $c;
                if (str_starts_with($t, 'ORE TOTALI ANNUE')) $colP['TOT'] = $c;
            }
            $colP['PROGR'] = max(1, $colP['NOME'] - 1);

            // coppie reali ORE/% lette dal template
            $pairColsPers  = $this->getPairLeftCols($sheet, $headerRowPers);
            $usedPairsPers = min($convenzioni->count(), count($pairColsPers));
            $convTitleRowPers = max(1, $headerRowPers - 1);

            // intestazioni convenzioni e (se serve) ORE/% su header
            for ($i = 0; $i < $usedPairsPers; $i++) {
                $col = $pairColsPers[$i];
                $sheet->setCellValueByColumnAndRow($col, $convTitleRowPers, (string)$convenzioni[$i]->Convenzione);
                $h1 = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($col,     $headerRowPers)->getValue()));
                $h2 = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($col + 1, $headerRowPers)->getValue()));
                if ($h1 === '') $sheet->setCellValueByColumnAndRow($col,     $headerRowPers, 'ORE DI SERVIZIO');
                if ($h2 === '') $sheet->setCellValueByColumnAndRow($col + 1, $headerRowPers, '%');
            }
            for ($i = $usedPairsPers; $i < count($pairColsPers); $i++) {
                $col = $pairColsPers[$i];
                $sheet->setCellValueExplicitByColumnAndRow($col,     $convTitleRowPers, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($col + 1, $convTitleRowPers, '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($col,     $headerRowPers,    '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicitByColumnAndRow($col + 1, $headerRowPers,    '', DataType::TYPE_STRING);
            }

            // PREV / CONSU allineati alle coppie reali
            [$prevRowTop, $consuRowTop, $labelColPrev, $labelColConsu] = $this->findPrevConsuRows($sheet, $convTitleRowPers);
            if ($prevRowTop <= 0 || $prevRowTop >= $convTitleRowPers)  $prevRowTop  = max(1, $convTitleRowPers - 3);
            if ($consuRowTop <= 0 || $consuRowTop >= $convTitleRowPers) $consuRowTop = max(1, $convTitleRowPers - 2);

            // Compatta lo spazio sotto il titolo azzurro (elimina/occulta solo le righe effettivamente vuote)
            $this->hideEmptyRowsBetween($sheet, max(1, $convTitleRowPers - 40), $prevRowTop - 1, 0, 0);

            // valori PREV/CONSU per convenzione
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
                if ($prevRowTop) {
                    $sheet->setCellValueByColumnAndRow($col, $prevRowTop, (float)($prevPerConv[$cId] ?? 0));
                    $sheet->getStyleByColumnAndRow($col, $prevRowTop)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                if ($consuRowTop) {
                    $sheet->setCellValueByColumnAndRow($col, $consuRowTop, (float)($consuPerConv[$cId] ?? 0));
                    $sheet->getStyleByColumnAndRow($col, $consuRowTop)->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
            // Totali PREV/CONSU nello slot tra etichetta e prima coppia reale
            $firstPairColReal = $pairColsPers[0] ?? ($colP['TOT'] + 1);
            $prevTot  = array_sum(array_map('floatval', $prevPerConv));
            $consuTot = array_sum(array_map('floatval', $consuPerConv));
            $totColPrev  = $this->pickTotalSlotCol($sheet, $prevRowTop,  $labelColPrev,  $firstPairColReal);
            $totColConsu = $this->pickTotalSlotCol($sheet, $consuRowTop, $labelColConsu, $firstPairColReal);
            if ($prevRowTop) {
                $sheet->setCellValueByColumnAndRow($totColPrev, $prevRowTop, $prevTot);
                $sheet->getStyleByColumnAndRow($totColPrev, $prevRowTop)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            if ($consuRowTop) {
                $sheet->setCellValueByColumnAndRow($totColConsu, $consuRowTop, $consuTot);
                $sheet->getStyleByColumnAndRow($totColConsu, $consuRowTop)->getNumberFormat()->setFormatCode('#,##0.00');
            }

            /* Tabella grande: dipendenti × (ORE,%) */
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

            $startRow  = $headerRowPers + 1;
            $rowIdx    = 0;
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

                $sheet->setCellValueByColumnAndRow($colP['PROGR'], $r, 'DIPENDENTE N. ' . $rowIdx);
                $sheet->setCellValueExplicitByColumnAndRow($colP['NOME'], $r, (string)($dRow->Cognome), DataType::TYPE_STRING);
                $sheet->setCellValueByColumnAndRow($colP['TOT'],  $r, $sum);
                $sheet->getStyleByColumnAndRow($colP['TOT'], $r)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

                // scrivo ORE e lascio che le % (se con formula) si calcolino da sole
                for ($i = 0; $i < $usedPairsPers; $i++) {
                    $nCol = $pairColsPers[$i];
                    $pCol = $nCol + 1;

                    $v = (float)$map[(int)$convenzioni[$i]->idConvenzione];
                    $p = $sum > 0 ? ($v / $sum) : 0.0;

                    $sheet->setCellValueByColumnAndRow($nCol, $r, $v);
                    $sheet->getStyleByColumnAndRow($nCol, $r)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

                    $pcCell = $sheet->getCellByColumnAndRow($pCol, $r);
                    $cur = (string)$pcCell->getValue();
                    if ($cur === '' || $cur[0] !== '=') $pcCell->setValue($p); // non toccare se è formula
                    $sheet->getStyleByColumnAndRow($pCol, $r)->getNumberFormat()->setFormatCode('0.00%');
                    $sheet->getStyleByColumnAndRow($pCol, $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }

            // riga Totale personale
            $totalRowPers = $startRow + $rowIdx;
            $sheet->setCellValueByColumnAndRow($colP['PROGR'], $totalRowPers, 'Totale');
            $sheet->setCellValueByColumnAndRow($colP['NOME'],  $totalRowPers, '');
            $sheet->setCellValueByColumnAndRow($colP['TOT'],   $totalRowPers, $totOreAll);
            $sheet->getStyleByColumnAndRow($colP['TOT'], $totalRowPers)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            $percSum = 0.0;
            $lastIdx = max(0, $convenzioni->count() - 1);
            for ($i = 0; $i < $usedPairsPers; $i++) {
                $nCol = $pairColsPers[$i];
                $pCol = $nCol + 1;

                $v = (float)$totPerConv[(int)$convenzioni[$i]->idConvenzione];
                $sheet->setCellValueByColumnAndRow($nCol, $totalRowPers, $v);
                $sheet->getStyleByColumnAndRow($nCol, $totalRowPers)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

                $p = ($i < $lastIdx && $totOreAll > 0) ? ($v / $totOreAll) : max(0.0, 1.0 - $percSum);
                if ($i < $lastIdx) $percSum += $p;
                $pcCell = $sheet->getCellByColumnAndRow($pCol, $totalRowPers);
                if (!is_string($pcCell->getValue()) || $pcCell->getValue()[0] !== '=') $pcCell->setValue($p);
                $sheet->getStyleByColumnAndRow($pCol, $totalRowPers)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyleByColumnAndRow($pCol, $totalRowPers)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $lastUsedColPers = max($colP['TOT'], ($pairColsPers[$usedPairsPers - 1] ?? $colP['TOT']) + 1);
            $lastColLetterPers = Coordinate::stringFromColumnIndex($lastUsedColPers);
            $sheet->getStyle("A{$totalRowPers}:{$lastColLetterPers}{$totalRowPers}")->getFont()->setBold(true);

            /* ===== Salvataggio ===== */
            $slug = $this->slugify($associazione);
            $filename = sprintf('SCHEDE_RIPARTO_COSTI_%s_%d.xlsx', $slug, $anno);
            $destRel = 'documenti/' . $filename;
            $destAbs = Storage::disk('public')->path($destRel);

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save($destAbs);

            DB::table('documenti_generati')->where('id', $this->documentoId)->update([
                'percorso_file' => $destRel,
                'nome_file' => $filename,
                'stato' => 'ready',
                'generato_il' => now(),
                'updated_at' => now(),
            ]);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();
        } catch (Throwable $e) {
            Log::error('SchedeRipartoCosti: exception', [
                'documentoId' => $this->documentoId,
                'idAssociazione' => $this->idAssociazione,
                'anno' => $this->anno,
                'msg' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    /** Prima cella "slot totale" dopo l'etichetta (es. PREV/CONSU) e prima delle coppie convenzioni. */
    private function pickTotalSlotCol(Worksheet $sheet, int $row, int $labelCol, int $firstPairCol): int {
        for ($c = $labelCol + 1; $c < $firstPairCol; $c++) {
            $v = (string)$sheet->getCellByColumnAndRow($c, $row)->getValue();
            if ($v === '' || $v === '-' || $v === '0') return $c;
        }
        // fallback: mettiamolo subito prima della prima coppia ORE/%
        return max($labelCol + 1, $firstPairCol - 2);
    }

    /** Colonne SINISTRE delle coppie (ORE/%), rilevate dalla riga header ("ORE DI SERVIZIO") */
    private function getPairLeftCols(Worksheet $sheet, int $headerRowPers): array {
        $cols = [];
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($c = 1; $c <= $maxCol; $c++) {
            $t = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRowPers)->getValue()));
            if ($t === 'ORE DI SERVIZIO') {
                $cols[] = $c;                // colonna sinistra della coppia (la destra è +1 -> "%")
            }
        }
        return $cols;
    }

    public function failed(Throwable $e): void {
        Log::error('GeneraSchedeRipartoCostiXlsJob failed', [
            'documentoId' => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno' => $this->anno,
            'error' => $e->getMessage(),
        ]);
        DB::table('documenti_generati')->where('id', $this->documentoId)
            ->update(['stato' => 'error', 'updated_at' => now()]);
    }
}
