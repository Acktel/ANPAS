<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Documento;
use App\Models\Automezzo;
use App\Models\Dipendente;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DocumentiController extends Controller
{
    protected function getAssociazioni()
    {
        return DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();
    }

    protected function getAnni()
    {
        $anni = [];
        for ($y = 2000; $y <= date('Y') + 5; $y++) {
            $anni[] = (object)['idAnno' => $y, 'anno' => $y];
        }
        return collect($anni);
    }

    public function registroForm()
    {
        $associazioni = $this->getAssociazioni();
        $anni         = $this->getAnni();
        return view('documenti.registro', compact('associazioni', 'anni'));
    }

    public function exportAll(Request $request)
    {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $registro = Documento::getRegistroData($data['idAssociazione'], $data['idAnno']);
        $convenz  = Documento::getConvenzioniData($data['idAssociazione'], $data['idAnno']);
        $autoz    = Automezzo::getByAssociazione($data['idAssociazione'], $data['idAnno']);
        $autisti  = Dipendente::getAutisti($data['idAnno']);
        $altri    = Dipendente::getAltri($data['idAnno']);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()
                    ->setName('Arial')->setSize(12);

        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('RIEPILOGO GENERALE');
        $this->fillRegistroDati($sheet1, $registro, $convenz);

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('REGISTRO AUTOMEZZI');
        $this->fillRegistroAutomezzi($sheet2, $autoz);

        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('PERSONALE DIEPNDENTE AUTISTI');
        $this->fillPersonaleAutisti($sheet3, $autisti);

        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('ALTRO PERSONALE DIPENDENTE');
        $this->fillPersonaleAltri($sheet4, $altri);

        $writer = new Xls($spreadsheet);
        $file   = "Registro_{$data['idAssociazione']}_{$data['idAnno']}.xls";

        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            $file,
            ['Content-Type' => 'application/vnd.ms-excel']
        );
    }

    private function fillRegistroDati($sheet, $rows, $convenzioni)
    {
        $borderThin = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]];
        $azureFill  = ['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'00F2FF']]];
        $center     = ['alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]];
        $wrapText   = ['alignment'=>['wrapText'=>true]];
        $boldBlack  = ['font'=>['bold'=>true,'color'=>['argb'=>'FF000000']]];

        // Banner
        $sheet->mergeCells('A1:C1');
        $sheet->setCellValue('A1','RIEPILOGO DATI CARATTERISTICI');
        $sheet->getStyle('A1')->applyFromArray($azureFill + $boldBlack + $center);

        // Images row 2
        foreach (['A2'=>'images/Immagine1.png','C2'=>'images/logo.png'] as $coord=>$path) {
            $d = new Drawing();
            $d->setPath(public_path($path))
              ->setCoordinates($coord)
              ->setOffsetX(2)->setOffsetY(2)
              ->setHeight(50)
              ->setWorksheet($sheet);
        }
        $sheet->getRowDimension(2)->setRowHeight(50);

        // Table 1 header
        $h1 = 5;
        $sheet->fromArray(['DESCRIZIONE','PREVENTIVO','CONSUNTIVO'],null,"A{$h1}");
        $sheet->getStyle("A{$h1}:C{$h1}")
              ->applyFromArray($borderThin + $azureFill + $boldBlack + $center);

        // Table 1 data
        foreach ($rows as $i=>$r) {
            $rowNum = $h1+1+$i;
            $sheet->setCellValue("A{$rowNum}", $r->descrizione)
                  ->setCellValue("B{$rowNum}", $r->preventivo)
                  ->setCellValue("C{$rowNum}", $r->consuntivo);
        }
        $end1 = $h1 + count($rows);
        $sheet->getStyle("A".($h1+1).":C{$end1}")
              ->applyFromArray($borderThin + $wrapText);

        // Footer in table 1
        $f = $end1+1;
        $note = "N.B. Per l'indicazione del numero di personale ... = 0,5";
        $sheet->mergeCells("A{$f}:C{$f}");
        $sheet->setCellValue("A{$f}", $note);
        $sheet->getStyle("A{$f}")->applyFromArray($wrapText);

        // Banner Convenzioni
        $b2 = $f+2;
        $sheet->mergeCells("A{$b2}:B{$b2}");
        $sheet->setCellValue("A{$b2}", 'TABELLA IDENTIFICATIVA CONVENZIONI E SERVIZI CON LETTERE');
        $sheet->getStyle("A{$b2}")->applyFromArray($azureFill + $boldBlack + $center);

        // Images row b2+1
        foreach (['A'.($b2+1)=>'images/Immagine1.png','B'.($b2+1)=>'images/logo.png'] as $c=>$p) {
            $d=new Drawing();
            $d->setPath(public_path($p))
              ->setCoordinates($c)
              ->setOffsetX(2)->setOffsetY(2)
              ->setHeight(50)
              ->setWorksheet($sheet);
        }
        $sheet->getRowDimension($b2+1)->setRowHeight(50);

        // Table 2 header
        $h2 = $b2+3;
        $sheet->fromArray(['CONVENZIONE','LETTERA IDENTIFICATIVA'],null,"A{$h2}");
        $sheet->getStyle("A{$h2}:B{$h2}")
              ->applyFromArray($borderThin + $azureFill + $boldBlack + $center);

        // Table 2 data
        foreach ($convenzioni as $j=>$c) {
            $r = $h2+1+$j;
            $sheet->setCellValue("A{$r}", $c->Convenzione)
                  ->setCellValue("B{$r}", $c->lettera_identificativa);
        }
        $end2 = $h2 + count($convenzioni);
        $sheet->getStyle("A".($h2+1).":B{$end2}")
              ->applyFromArray($borderThin + $wrapText);

        // Columns
        $sheet->getColumnDimension('A')->setWidth(60);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(40);
    }

    private function fillRegistroAutomezzi($sheet, $automezzi)
    {
        $borderThin = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]];
        $azureFill  = ['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'00F2FF']]];
        $center     = ['alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]];
        $wrapText   = ['alignment'=>['wrapText'=>true]];
        $boldBlack  = ['font'=>['bold'=>true,'color'=>['argb'=>'FF000000']]];

        // Banner
        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1','REGISTRO AUTOMEZZI IN DOTAZIONE');
        $sheet->getStyle('A1')->applyFromArray($azureFill + $boldBlack + $center);

        // Images
        foreach (['A2'=>'images/Immagine1.png','K2'=>'images/logo.png'] as $c=>$p) {
            $d=new Drawing();
            $d->setPath(public_path($p))
              ->setCoordinates($c)
              ->setOffsetX(2)->setOffsetY(2)
              ->setHeight(50)
              ->setWorksheet($sheet);
        }
        $sheet->getRowDimension(2)->setRowHeight(50);

        // Header
        $h=5;
        $hdr=['TARGA','CODICE IDENTIFICATIVO','ANNO IMMAT.','ANNO ACQ.','MODELLO','TIPO VEIC.','KM RIF.','KM TOT.','CARBURANTE','AUT. SANIT.','COLLAUDO'];
        $sheet->fromArray($hdr,null,"A{$h}");
        $sheet->getStyle("A{$h}:K{$h}")
              ->applyFromArray($borderThin + $azureFill + $boldBlack + $center);

        // Data
        foreach ($automezzi as $i=>$m) {
            $r=$h+1+$i;
            $sheet->setCellValue("A{$r}",$m->Targa)
                  ->setCellValue("B{$r}",$m->CodiceIdentificativo)
                  ->setCellValue("C{$r}",$m->AnnoPrimaImmatricolazione)
                  ->setCellValue("D{$r}","")
                  ->setCellValue("E{$r}",$m->Modello)
                  ->setCellValue("F{$r}",$m->TipoVeicolo)
                  ->setCellValue("G{$r}",$m->KmRiferimento)
                  ->setCellValue("H{$r}",$m->KmTotali)
                  ->setCellValue("I{$r}",$m->TipoCarburante)
                  ->setCellValue("J{$r}",optional($m->DataUltimaAutorizzazioneSanitaria)->format('d/m/Y'))
                  ->setCellValue("K{$r}",optional($m->DataUltimoCollaudo)->format('d/m/Y'));
        }
        $end=$h+count($automezzi);
        $sheet->getStyle("A".($h+1).":K{$end}")
              ->applyFromArray($borderThin + $wrapText);

        foreach(range('A','K') as $col){
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function fillPersonaleAutisti($sheet, $autisti)
    {
        $outline    = ['borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]]];
        $azureFill  = ['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'00F2FF']]];
        $center     = ['alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]];
        $wrapText   = ['alignment'=>['wrapText'=>true]];
        $boldBlack  = ['font'=>['bold'=>true,'color'=>['argb'=>'FF000000']]];

        // Banner
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1','PERSONALE DIPENDENTE AUTISTI');
        $sheet->getStyle('A1')->applyFromArray($azureFill + $boldBlack + $center);

        // Images
        foreach(['A2'=>'images/Immagine1.png','E2'=>'images/logo.png'] as $c=>$p){
            $d=new Drawing();
            $d->setPath(public_path($p))
              ->setCoordinates($c)
              ->setOffsetX(2)->setOffsetY(2)
              ->setHeight(50)
              ->setWorksheet($sheet);
        }
        $sheet->getRowDimension(2)->setRowHeight(50);

        // Header two rows
        $h1=5; $h2=6;
        $sheet->setCellValue("B{$h1}",'COGNOME')
              ->setCellValue("C{$h1}",'QUALIFICA')
              ->setCellValue("D{$h1}",'CONTRATTO APPLICATO');
        $sheet->getStyle("B{$h1}:D{$h1}")
              ->applyFromArray($outline + $azureFill + $boldBlack + $center);

        $sheet->setCellValue("B{$h2}",'NOME')
              ->setCellValue("C{$h2}","")
              ->setCellValue("D{$h2}",'LIVELLO E MANSIONE');
        $sheet->getStyle("B{$h2}:D{$h2}")
              ->applyFromArray($outline + $azureFill + $boldBlack + $center);

        // Data rows
        $start=$h2+1;
        foreach($autisti as $i=>$d){
            $r1=$start+2*$i; $r2=$r1+1;
            [$q1,$q2]=array_pad(array_map('trim',explode(',',$d->Qualifica)),2,'');
            
            $sheet->setCellValue("B{$r1}",$d->DipendenteCognome)
                  ->setCellValue("C{$r1}",$q1)
                  ->setCellValue("D{$r1}",$d->ContrattoApplicato);
            $sheet->setCellValue("B{$r2}",$d->DipendenteNome)
                  ->setCellValue("C{$r2}",$q2)
                  ->setCellValue("D{$r2}",$d->LivelloMansione);

            $sheet->getStyle("B{$r1}:D{$r2}")
                  ->applyFromArray($outline + $wrapText + $center);
        }

        foreach(['A','B','C','D','E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

        private function fillPersonaleAltri($sheet, $dipendenti)
    {
        $borderThin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $azureFill  = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00F2FF']]];
        $center     = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
        $wrapText   = ['alignment' => ['wrapText' => true]];
        $boldBlack  = ['font' => ['bold' => true, 'color' => ['argb' => 'FF000000']]];

        // Raggruppa per qualifica
        $gruppi = $dipendenti->groupBy(fn($d) => $d->Qualifica);
        $row    = 1;

        foreach ($gruppi as $qualifica => $gruppo) {
            // Banner per il gruppo
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", strtoupper("Personale {$qualifica}"));
            $sheet->getStyle("A{$row}")->applyFromArray($azureFill + $boldBlack + $center);
            $row += 2;

            // Intestazione a riga singola
            $header = ['COGNOME', 'NOME', 'QUALIFICA', 'CONTRATTO APPLICATO', 'LIVELLO E MANSIONE'];
            $sheet->fromArray($header, null, "A{$row}");
            $sheet->getStyle("A{$row}:E{$row}")
                  ->applyFromArray($borderThin + $azureFill + $boldBlack + $center);
            $row++;

            // Dati: una sola riga per dipendente
            foreach ($gruppo as $dip) {
                $sheet->setCellValue("A{$row}", $dip->DipendenteCognome)
                      ->setCellValue("B{$row}", $dip->DipendenteNome)
                      ->setCellValue("C{$row}", $dip->Qualifica)
                      ->setCellValue("D{$row}", $dip->ContrattoApplicato)
                      ->setCellValue("E{$row}", $dip->LivelloMansione);

                $sheet->getStyle("A{$row}:E{$row}")
                      ->applyFromArray($borderThin + $wrapText + $center);
                $row++;
            }

            // Spazio prima del prossimo gruppo
            $row += 2;
        }

        // Regola larghezza colonne
        foreach (['A','B','C','D','E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
