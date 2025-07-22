<?php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExcelGeneratorService
{
    public function generaDocumento($registro, $convenz, $autoz, $autisti, $altri): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(12);

        // Sheet 1
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('RIEPILOGO GENERALE');
        (new SheetHelper())->fillRegistroDati($sheet1, $registro, $convenz);

        // Sheet 2
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('REGISTRO AUTOMEZZI');
        (new SheetHelper())->fillRegistroAutomezzi($sheet2, $autoz);

        // Sheet 3
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('PERSONALE DIPENDENTE AUTISTI');
        (new SheetHelper())->fillPersonaleAutisti($sheet3, $autisti);

        // Sheet 4
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('ALTRO PERSONALE DIPENDENTE');
        (new SheetHelper())->fillPersonaleAltri($sheet4, $altri);

        return $spreadsheet;
    }

    
}
