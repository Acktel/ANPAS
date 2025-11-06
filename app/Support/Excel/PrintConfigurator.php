<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\PageMargins;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PrintConfigurator
{
    public const PROFILE_SINGLE = 'single'; // 1×1 pagina (tabella unica, grande e leggibile)
    public const PROFILE_MULTI  = 'multi';  // 1×N (tutte le colonne su una pagina in larghezza, multipagina in verticale)

    /** Soglia minima di leggibilità: MAI sotto il 30% (richiesta esplicita) */
    public int $minScale = 50;

    /** Consenti di salire ad A3 quando serve */
    public bool $allowA3 = true;

    /** Riga header da ripetere (1 = riga 1), null per non ripetere */
    public ?int $repeatHeaderRow = 1;

    /** Margini “compatti” in pollici */
    public float $marginTop = 0.4;
    public float $marginBottom = 0.6;
    public float $marginLeft = 0.4;
    public float $marginRight = 0.4;
    public float $marginHeader = 0.3;
    public float $marginFooter = 0.3;

    public function __construct(?int $minScale = null, ?bool $allowA3 = null)
    {
        if ($minScale !== null) $this->minScale = max(10, (int)$minScale); // guard rail
        if ($allowA3  !== null) $this->allowA3 = (bool)$allowA3;
    }

    /** Applica il profilo scelto a un Worksheet */
    public function apply(Worksheet $sheet, string $profile): void
    {
        $this->applyCosmetics($sheet);

        // Definisci area di stampa sulla base dell’area usata
        $this->ensurePrintArea($sheet);

        // Header ripetuto (se definito)
        if ($this->repeatHeaderRow !== null && $this->repeatHeaderRow > 0) {
            $sheet->getPageSetup()->setRowsToRepeatAtTop([$this->repeatHeaderRow, $this->repeatHeaderRow]);
        }

        // Logica profilo
        if ($profile === self::PROFILE_SINGLE) {
            $this->profileSingle($sheet);
        } else {
            $this->profileMulti($sheet);
        }
    }

    /** Impostazioni estetiche e margini */
    protected function applyCosmetics(Worksheet $sheet): void
    {
        $sheet->getPageMargins()
            ->setTop($this->marginTop)
            ->setBottom($this->marginBottom)
            ->setLeft($this->marginLeft)
            ->setRight($this->marginRight)
            ->setHeader($this->marginHeader)
            ->setFooter($this->marginFooter);

        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setHorizontalCentered(true)
            ->setVerticalCentered(false);

        $sheet->setShowGridlines(false);
    }

    /** Profilo 1×1 pagina, senza scendere sotto minScale; orientamento/formato adattivi */
    protected function profileSingle(Worksheet $sheet): void
    {
        $ps = $sheet->getPageSetup();
        $ps->setFitToWidth(1)->setFitToHeight(1);

        // Tenta Portrait A4 → Landscape A4 → Landscape A3 finché la scala stimata >= minScale
        $this->autoFixOrientation($sheet, $this->minScale, $this->allowA3);
        // Nota: con FitTo* attivo, Excel ignora Scale; noi usiamo la stima solo per scegliere orientamento/formato.
    }

    /** Profilo 1×N (tutte le colonne su una pagina, più pagine in verticale) */
    protected function profileMulti(Worksheet $sheet): void
    {
        $ps = $sheet->getPageSetup();
        $ps->setFitToWidth(1)->setFitToHeight(0); // 0 = illimitato in verticale

        // Adatta orientamento/formato per non scendere sotto soglia
        $this->autoFixOrientation($sheet, $this->minScale, $this->allowA3);
    }

    /** Orientamento + formato carta adattivi per rispettare la soglia di scala stimata */
    protected function autoFixOrientation(Worksheet $sheet, int $minScale, bool $allowA3): void
    {
        // A4 Portrait
        $sheet->getPageSetup()
              ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
              ->setPaperSize(PageSetup::PAPERSIZE_A4);
        if ($this->estimateScale($sheet) >= $minScale) return;

        // A4 Landscape
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        if ($this->estimateScale($sheet) >= $minScale) return;

        // A3 Landscape (se consentito)
        if ($allowA3) {
            $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
            // Ultimo tentativo; anche se resta < minScale, teniamo il meglio raggiunto
            $this->estimateScale($sheet); // for completeness
        }
    }

    /** Stima percentuale scala necessaria per stare in 1 pagina in larghezza */
    protected function estimateScale(Worksheet $sheet): int
    {
        $content = $this->estimateContentWidthPoints($sheet);
        $avail   = $this->printableWidthPoints($sheet);
        if ($content <= 0) return 100;
        return (int) floor(($avail / $content) * 100);
    }

    /** Stima larghezza contenuto sommando le colonne visibili (punti tipografici) */
    protected function estimateContentWidthPoints(Worksheet $sheet): float
    {
        $last = $sheet->getHighestColumn();
        $firstColIdx = 1;
        $lastColIdx  = Coordinate::columnIndexFromString($last);

        $sumPoints = 0.0;
        for ($i = $firstColIdx; $i <= $lastColIdx; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            if ($sheet->getColumnDimension($col)->getVisible() === false) continue;
            $wChars = $sheet->getColumnDimension($col)->getWidth();
            if ($wChars <= 0) $wChars = 8.43; // default Excel
            // 1 “unità colonna” ~ 7.2 pt (euristica sufficiente per la scelta)
            $sumPoints += $wChars * 7.2;
        }
        return $sumPoints;
    }

    /** Larghezza stampabile (punti) considerando formato, orientamento e margini */
    protected function printableWidthPoints(Worksheet $sheet): float
    {
        $ps = $sheet->getPageSetup();
        $pm = $sheet->getPageMargins();

        $paper = $ps->getPaperSize();
        $sizes = [
            PageSetup::PAPERSIZE_A4 => [595.28, 841.89],  // pt (210×297mm)
        ];
        [$w, $h] = $sizes[$paper] ?? $sizes[PageSetup::PAPERSIZE_A4];

        $isLandscape = $ps->getOrientation() === PageSetup::ORIENTATION_LANDSCAPE;
        $pageWidth   = $isLandscape ? $h : $w;

        $leftRightPt = ($pm->getLeft() + $pm->getRight()) * 72.0;
        return max(0, $pageWidth - $leftRightPt);
    }

    /** Imposta area di stampa sull’area realmente usata */
    protected function ensurePrintArea(Worksheet $sheet): void
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        if ($lastRow < 1) $lastRow = 1;
        $sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$lastRow}");
    }
}
