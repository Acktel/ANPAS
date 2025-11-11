<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


class PrintConfigurator {
    /** Profili (storici, puoi ignorarli se usi solo il forceLandscape) */
    public const PROFILE_SINGLE = 'single';
    public const PROFILE_MULTI  = 'multi';

    /** Soglia minima scala */
    public int $minScale = 50;
    public ?int $repeatHeaderRow = 1;

    /** Margini compatti */
    public float $marginTop = 0.4;
    public float $marginBottom = 0.6;
    public float $marginLeft = 0.4;
    public float $marginRight = 0.4;
    public float $marginHeader = 0.3;
    public float $marginFooter = 0.3;

    public bool $allowA3 = false;

    public function __construct(?int $minScale = null, ?bool $allowA3 = null) {
        if ($minScale !== null)  $this->minScale = max(10, (int)$minScale);
        if ($allowA3  !== null)  $this->allowA3  = (bool)$allowA3;
    }

    /* ============================================================
     *  IMPOSTAZIONI DI STAMPA
     * ============================================================ */
    public static function forceLandscapeCenteredMinScale(Worksheet $ws, int $minScale = 50, bool $setPrintArea = true): void {
        $ps = $ws->getPageSetup();

        // A4 orizzontale
        $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        // Scala (disattiva FitTo)
        $ps->setFitToWidth(0);
        $ps->setFitToHeight(0);
        $scale = (int) ($ps->getScale() ?: 100);
        if ($scale < $minScale) $scale = $minScale;
        if ($scale > 400)       $scale = 400;
        $ps->setScale($scale);

        // Centratura
        $ps->setHorizontalCentered(true);
        $ps->setVerticalCentered(true);
        $ps->setFitToPage(false);

        // Margini compatti
        $ws->getPageMargins()
            ->setTop(0.4)->setBottom(0.6)
            ->setLeft(0.4)->setRight(0.4)
            ->setHeader(0.3)->setFooter(0.3);

        // Area di stampa reale
        if ($setPrintArea) {
            $lastColIdx = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
            $lastRow    = $ws->getHighestDataRow();
            if ($lastColIdx > 0 && $lastRow > 0) {
                $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);
                $ps->setPrintArea("A1:{$lastCol}{$lastRow}");
            }
        }

        // Griglia visibile in anteprima disattivata
        $ws->setShowGridlines(false);
    }

    public static function applyToWorkbook(Spreadsheet $wb, int $minScale = 50, bool $setPrintArea = true): void {
        foreach ($wb->getAllSheets() as $ws) {
            self::forceLandscapeCenteredMinScale($ws, $minScale, $setPrintArea);
        }
    }

    /* ============================================================
     *  NORMALIZZAZIONE RIGHE E CELLE
     * ============================================================ */

    /** Resetta altezze, allinea in alto e compattamento generale */
    public static function normalizeRowHeights(
        Worksheet $ws,
        float $defaultRowHeight = 14.5,
        string $vertical = Alignment::VERTICAL_TOP
    ): void {
        // Default row height “slim”
        $ws->getDefaultRowDimension()->setRowHeight($defaultRowHeight);

        $lastRow = $ws->getHighestRow();
        for ($r = 1; $r <= $lastRow; $r++) {
            $dim = $ws->getRowDimension($r);
            if ($dim->getRowHeight() !== -1) {
                $dim->setRowHeight(-1); // auto
            }
        }

        // Allineamento verticale top su tutto
        $lastCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        if ($lastCol >= 1 && $lastRow >= 1) {
            $range = 'A1:' . Coordinate::stringFromColumnIndex($lastCol) . $lastRow;
            $style = $ws->getStyle($range)->getAlignment();
            $style->setVertical($vertical);
            $style->setWrapText(false);
            $style->setIndent(0);
            $style->setShrinkToFit(true);
        }
    }

    /** Compatta ulteriormente (senza cambiare font) */
    public static function compactAllCells(
        Worksheet $ws,
        float $defaultRowHeightPt = 14.0,
        ?float $fontSizeOverridePt = null
    ): void {
        $ws->getDefaultRowDimension()->setRowHeight($defaultRowHeightPt);

        $lastRow = $ws->getHighestRow();
        $lastCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        if ($lastCol < 1 || $lastRow < 1) return;

        $range = 'A1:' . Coordinate::stringFromColumnIndex($lastCol) . $lastRow;

        for ($r = 1; $r <= $lastRow; $r++) {
            $dim = $ws->getRowDimension($r);
            $dim->setRowHeight(-1);
        }

        $align = $ws->getStyle($range)->getAlignment();
        $align->setVertical(Alignment::VERTICAL_TOP);
        $align->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $align->setIndent(0);
        $align->setWrapText(false);
        $align->setShrinkToFit(true);

        if ($fontSizeOverridePt !== null) {
            $ws->getStyle($range)->getFont()->setSize($fontSizeOverridePt);
        }
    }

    public static function compactBodyOnly(
        Worksheet $ws,
        int $bodyStartRow = 2,
        float $defaultRowHeightPt = 14.0,
        ?float $fontSizeOverridePt = null,
        bool $autoSizeColumns = false,   // <-- DEFAULT: false (prima era true)
        int $firstCol = 1
    ): void {
        $ws->getDefaultRowDimension()->setRowHeight($defaultRowHeightPt);

        $lastRow = $ws->getHighestRow();
        $lastCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        if ($lastCol < $firstCol || $lastRow < $bodyStartRow) return;

        for ($r = $bodyStartRow; $r <= $lastRow; $r++) {
            $dim = $ws->getRowDimension($r);
            // se il template o il codice hanno già messo un’altezza >0, NON toccarla
            if ($dim->getRowHeight() === -1) {
                $dim->setRowHeight(-1); // solo righe "auto"
            }
        }
        $range = Coordinate::stringFromColumnIndex($firstCol) . $bodyStartRow . ':' .
            Coordinate::stringFromColumnIndex($lastCol)  . $lastRow;

        $align = $ws->getStyle($range)->getAlignment();
        $align->setVertical(Alignment::VERTICAL_TOP);
        $align->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $align->setIndent(0);
        $align->setWrapText(false);
        $align->setShrinkToFit(true);

        if ($fontSizeOverridePt !== null) {
            $ws->getStyle($range)->getFont()->setSize($fontSizeOverridePt);
        }

        // se proprio richiesto, abilita autosize (ma noi NON lo useremo quando “tagliamo”)
        if ($autoSizeColumns) {
            for ($c = $firstCol; $c <= $lastCol; $c++) {
                $dim = $ws->getColumnDimensionByColumn($c);
                if ($dim->getVisible()) $dim->setAutoSize(true);
            }
        }
    }



    /** Variante: escludi righe specifiche (es. 1..3) e compatta tutto il resto */
    public static function compactExceptRows(
        Worksheet $ws,
        array $excludeRows = [1],
        float $defaultRowHeightPt = 14.0,
        ?float $fontSizeOverridePt = null
    ): void {
        $ws->getDefaultRowDimension()->setRowHeight($defaultRowHeightPt);

        $lastRow = $ws->getHighestRow();
        $lastCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        if ($lastCol < 1 || $lastRow < 1) return;

        // set auto solo per le righe non escluse
        for ($r = 1; $r <= $lastRow; $r++) {
            if (in_array($r, $excludeRows, true)) continue;
            $ws->getRowDimension($r)->setRowHeight(-1);
        }

        // Applica stile solo alle righe non escluse
        // (costruiamo unione di range per blocchi continui)
        $ranges = [];
        $start = null;
        for ($r = 1; $r <= $lastRow; $r++) {
            $excluded = in_array($r, $excludeRows, true);
            if (!$excluded && $start === null) $start = $r;
            if (($excluded || $r === $lastRow) && $start !== null) {
                $end = $excluded ? $r - 1 : $r;
                $ranges[] = 'A' . $start . ':' .
                    Coordinate::stringFromColumnIndex($lastCol) . $end;
                $start = null;
            }
        }
        foreach ($ranges as $range) {
            $align = $ws->getStyle($range)->getAlignment();
            $align->setVertical(Alignment::VERTICAL_TOP);
            $align->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $align->setIndent(0);
            $align->setWrapText(false);
            $align->setShrinkToFit(true);
            if ($fontSizeOverridePt !== null) {
                $ws->getStyle($range)->getFont()->setSize($fontSizeOverridePt);
            }
        }
    }

    /**
     * Stringe orizzontalmente le colonne del **corpo** (da bodyStartRow in giù),
     * calcolando la larghezza sul testo **formattato** (es. 1.234,56; 12,50%).
     * - padding: spazio extra oltre al contenuto
     * - min/max: clamp delle larghezze per non avere colonne ridicole o infinite
     * - excludeCols: indici colonna (1=A, 2=B, ...) da NON toccare
     */
    public static function fitBodyColumns(
        Worksheet $ws,
        int $bodyStartRow = 2,
        ?int $firstCol = null,
        ?int $lastCol = null,
        float $padding = 1.6,
        float $minWidth = 7.0,
        float $maxWidth = 40.0,
        array $excludeCols = []
    ): void {
        $lastRow = $ws->getHighestRow();
        if ($lastRow < $bodyStartRow) return;

        $firstCol = $firstCol ?? 1;
        $lastCol  = $lastCol  ?? Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        if ($lastCol < $firstCol) return;

        $charToWidth = 1.0;

        for ($col = $firstCol; $col <= $lastCol; $col++) {
            if (in_array($col, $excludeCols, true)) continue;

            $maxChars = 0;

            // 🔹 Considera anche le prime righe d’intestazione (tipicamente 5–8)
            $headerRows = range(1, min(8, $lastRow));
            foreach ($headerRows as $r) {
                $cell = $ws->getCellByColumnAndRow($col, $r);
                if ($cell instanceof Cell) {
                    $txt = trim((string)$cell->getFormattedValue());
                    if ($txt !== '') {
                        $len = StringHelper::countCharacters($txt);
                        if ($len > $maxChars) $maxChars = $len;
                    }
                }
            }

            // 🔹 Scansiona anche il corpo dati per trovare testi lunghi
            for ($row = $bodyStartRow; $row <= $lastRow; $row++) {
                $cell = $ws->getCellByColumnAndRow($col, $row);
                if (!$cell instanceof Cell) continue;

                $text = (string) $cell->getFormattedValue();
                if ($text === '') continue;

                // Gestione multilinea → prendi la più lunga
                if (strpos($text, "\n") !== false) {
                    $text = str_replace("\r", '', $text);
                    $parts = explode("\n", $text);
                    $text = array_reduce(
                        $parts,
                        fn($a, $b) => (StringHelper::countCharacters($b) > StringHelper::countCharacters($a)) ? $b : $a,
                        ''
                    );
                }

                $len = StringHelper::countCharacters($text);
                if ($len > $maxChars) $maxChars = $len;
            }

            // 🔹 Calcolo larghezza effettiva
            $width = ($maxChars * $charToWidth) + $padding;
            if ($width < $minWidth) $width = $minWidth;
            if ($width > $maxWidth) $width = $maxWidth;

            // 🔹 Se colonna con header lungo (es. convenzione), allarghiamo ancora un po’
            $headerText = trim((string) $ws->getCellByColumnAndRow($col, 6)->getValue());
            if ($headerText !== '' && StringHelper::countCharacters($headerText) > 20) {
                $width = min($width + 6, $maxWidth + 4); // titoli lunghi leggibili
            }

            // Applica larghezza deterministica
            $ws->getColumnDimensionByColumn($col)->setAutoSize(false);
            $ws->getColumnDimensionByColumn($col)->setWidth($width);
        }
    }



    /**
     * Shortcut: applica fitBodyColumns a **tutti i fogli** del workbook.
     */
    public static function fitBodyColumnsForWorkbook(
        Spreadsheet $wb,
        int $bodyStartRow = 2,
        float $padding = 1.6,
        float $minWidth = 7.0,
        float $maxWidth = 26.0,
        array $excludeCols = [1] // esclude la colonna A (etichette) di default
    ): void {
        foreach ($wb->getAllSheets() as $ws) {
            self::fitBodyColumns(
                $ws,
                $bodyStartRow,
                null,
                null,
                $padding,
                $minWidth,
                $maxWidth,
                $excludeCols
            );
        }
    }

    public static function configureScrolling(Worksheet $ws, ?string $freezeCell = null): void {
        // Se specifichi la cella, blocca da lì in giù; altrimenti rimuove il blocco
        if ($freezeCell) {
            $ws->freezePane($freezeCell);
        } else {
            $ws->freezePane(null); // sblocca scroll verticale/orizzontale
        }

        $ws->setShowGridlines(false);
    }

    /**
     * Impone un font minimo leggibile su tutto il foglio (header, voci, corpo).
     * - Default: 10 pt
     * - Non tocca i fogli vuoti o i numeri (solo testo).
     */
    public static function enforceMinimumFontSize(
        Worksheet $ws,
        float $minFontSizePt = 10.0,
        bool $includeNumbers = false
    ): void {
        $lastRow = $ws->getHighestDataRow();
        $lastCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        if ($lastRow < 1 || $lastCol < 1) return;

        // Range totale
        $range = 'A1:' . Coordinate::stringFromColumnIndex($lastCol) . $lastRow;
        $style = $ws->getStyle($range);
        $font  = $style->getFont();

        // Se il font esistente è più piccolo → porta al minimo
        if ($font->getSize() < $minFontSizePt) {
            $font->setSize($minFontSizePt);
        }

        // Forza anche nero per leggibilità (evita font tema grigio)
        $font->getColor()->setARGB('FF000000');

        // Eventuale logica opzionale: salta numeri puri
        if (!$includeNumbers) {
            for ($r = 1; $r <= $lastRow; $r++) {
                for ($c = 1; $c <= $lastCol; $c++) {
                    $cell = $ws->getCellByColumnAndRow($c, $r);
                    if (!$cell) continue;
                    $v = trim((string)$cell->getValue());
                    if ($v === '' || is_numeric(str_replace(',', '.', $v))) continue;
                    $ws->getStyleByColumnAndRow($c, $r)->getFont()->setSize(max($minFontSizePt, 10));
                }
            }
        }
    }

    public static function enforceMinimumFontSizeForWorkbook(
        Spreadsheet $wb,
        float $minFontSizePt = 10.0
    ): void {
        foreach ($wb->getAllSheets() as $ws) {
            self::enforceMinimumFontSize($ws, $minFontSizePt);
        }
    }

    public static function enforceHeaderAndFirstColReadable(
    Worksheet $ws,
    int $minFontPt = 10,
    int $firstColIdx = 1,
    int $headerTopRow = 1,
    int $headerBottomRow = 8,
    float $firstColMinWidth = 12.0
): void {
    $lastRow = $ws->getHighestDataRow();
    $lastCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
    if ($lastRow < 1 || $lastCol < 1) return;

    // --- Header: prime N righe ---
    $headerTopRow    = max(1, $headerTopRow);
    $headerBottomRow = max($headerTopRow, min($lastRow, $headerBottomRow));
    $hdrRange = 'A' . $headerTopRow . ':' .
       Coordinate::stringFromColumnIndex($lastCol) . $headerBottomRow;

    $hdrStyle = $ws->getStyle($hdrRange);
    $hdrStyle->getAlignment()->setShrinkToFit(false)->setWrapText(false);
    // forza minimo leggibile ma non toccare il grassetto ecc.
    if ($hdrStyle->getFont()->getSize() < $minFontPt) {
        $hdrStyle->getFont()->setSize($minFontPt);
    }

    // --- Prima colonna su tutto il foglio ---
    $colL = Coordinate::stringFromColumnIndex($firstColIdx);
    $colRange = $colL . '1:' . $colL . $lastRow;
    $colStyle = $ws->getStyle($colRange)->getAlignment();
    $colStyle->setShrinkToFit(false)->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_LEFT);
    // font minimo anche qui
    $ws->getStyle($colRange)->getFont()->setSize($minFontPt);

    // larghezza minima per evitare #### o squeeze eccessivo
    $dim = $ws->getColumnDimension($colL);
    $dim->setAutoSize(false);
    if (($dim->getWidth() ?? 0) < $firstColMinWidth) {
        $dim->setWidth($firstColMinWidth);
    }
}

}
