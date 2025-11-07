<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PrintConfigurator
{
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

    public function __construct(?int $minScale = null)
    {
        if ($minScale !== null) {
            $this->minScale = max(10, (int) $minScale);
        }
    }

    /* ============================================================
     *  IMPOSTAZIONI DI STAMPA
     * ============================================================ */
    public static function forceLandscapeCenteredMinScale(Worksheet $ws, int $minScale = 50, bool $setPrintArea = true): void
    {
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

    public static function applyToWorkbook(Spreadsheet $wb, int $minScale = 50, bool $setPrintArea = true): void
    {
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
}
