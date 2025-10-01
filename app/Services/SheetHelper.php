<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SheetHelper {
    public function fillRegistroDati(Worksheet $sheet, $rows, $convenzioni) {
        // Header
        $sheet->setCellValue('A1', 'Descrizione');
        $sheet->setCellValue('B1', 'Preventivo');
        $sheet->setCellValue('C1', 'Consuntivo');

        // Eventuali colonne aggiuntive per convenzioni, se presenti (es. colonne D, E, F...)
        $colIndex = 4;
        foreach ($convenzioni as $conv) {
            $lettera = $conv->lettera_identificativa ?? $conv->Convenzione ?? 'N/A';
            $sheet->setCellValueByColumnAndRow($colIndex++, 1, $lettera);
        }

        // Dati
        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowIndex}", $row->descrizione ?? '');
            $sheet->setCellValue("B{$rowIndex}", $row->preventivo ?? 0);
            $sheet->setCellValue("C{$rowIndex}", $row->consuntivo ?? 0);

            // Se hai valori per ogni convenzione dentro ogni $row (es: $row->valori_convenzioni come array), allora:
            // foreach ($row->valori_convenzioni ?? [] as $k => $val) {
            //     $sheet->setCellValueByColumnAndRow(4 + $k, $rowIndex, $val);
            // }

            $rowIndex++;
        }
    }

    public function fillRegistroAutomezzi($sheet, $automezzi) {
        // Intestazione
        $headers = [
            'Associazione',
            'Anno',
            'Targa',
            'Codice Identificativo',
            'Anno Prima Immatricolazione',
            'Anno Acquisto',
            'Modello',
            'Tipo Veicolo',
            'Tipo Carburante',
            'Km Totali',
            'Km Riferimento',
            'Autorizzazione Sanitaria',
            'Collaudo',
            'Incluso nel Riparto',
        ];

        // Scrivi intestazioni
        $sheet->fromArray([$headers], NULL, 'A1');

        // Formatta intestazioni
        $style = $sheet->getStyle('A1:' . chr(64 + count($headers)) . '1');
        $style->getFont()->setBold(true);
        $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $style->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');

        // Scrivi righe dati
        $row = 2;
        foreach ($automezzi as $auto) {
            $sheet->setCellValue("A{$row}", $auto->Associazione ?? '')
                ->setCellValue("B{$row}", $auto->anno ?? $auto->idAnno)
                ->setCellValue("C{$row}", $auto->Automezzo)
                ->setCellValue("D{$row}", $auto->Targa)
                ->setCellValue("E{$row}", $auto->CodiceIdentificativo)
                ->setCellValue("F{$row}", $auto->AnnoPrimaImmatricolazione)
                ->setCellValue("G{$row}", $auto->AnnoAcquisto)
                ->setCellValue("H{$row}", $auto->Modello)
                ->setCellValue("I{$row}", $auto->TipoVeicolo)
                ->setCellValue("J{$row}", $auto->TipoCarburante)
                ->setCellValue("K{$row}", $auto->KmTotali)
                ->setCellValue("L{$row}", $auto->KmRiferimento)
                ->setCellValue("M{$row}", optional($auto->DataUltimaAutorizzazioneSanitaria)->format('Y-m-d'))
                ->setCellValue("N{$row}", optional($auto->DataUltimoCollaudo)->format('Y-m-d'))
                ->setCellValue("O{$row}", $auto->incluso_riparto ? 'SI' : 'NO');

            $row++;
        }

        // Autosize colonne
        foreach (range('A', chr(64 + count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }


    public function fillPersonaleAutisti($sheet, $autisti) {
        $headers = [
            'Associazione',
            'Anno',
            'Cognome',
            'Nome',
            'Qualifica',
            'Livello Mansione',
            'Contratto Applicato',
            'Creato da',
            'Creato il',
            'Modificato da',
            'Modificato il',
        ];

        // Intestazione
        $sheet->fromArray([$headers], null, 'A1');

        $style = $sheet->getStyle('A1:' . chr(64 + count($headers)) . '1');
        $style->getFont()->setBold(true);
        $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $style->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');

        $row = 2;
        foreach ($autisti as $dip) {
            $sheet->setCellValue("A{$row}", $dip->Associazione ?? '')
                ->setCellValue("B{$row}", $dip->idAnno ?? '')
                ->setCellValue("C{$row}", $dip->DipendenteCognome ?? '')
                ->setCellValue("D{$row}", $dip->DipendenteNome ?? '')
                ->setCellValue("E{$row}", $dip->Qualifica ?? '')
                ->setCellValue("F{$row}", $dip->LivelloMansione ?? '')
                ->setCellValue("G{$row}", $dip->ContrattoApplicato ?? '')
                ->setCellValue("H{$row}", $dip->created_by_name ?? '')
                ->setCellValue("I{$row}", optional($dip->created_at)->format('Y-m-d H:i') ?? '')
                ->setCellValue("J{$row}", $dip->updated_by_name ?? '')
                ->setCellValue("K{$row}", optional($dip->updated_at)->format('Y-m-d H:i') ?? '');
            $row++;
        }

        foreach (range('A', chr(64 + count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }


    public function fillPersonaleAltri($sheet, $dipendenti) {
        $headers = [
            'Associazione',
            'Anno',
            'Cognome',
            'Nome',
            'Qualifica',
            'Livello Mansione',
            'Contratto Applicato',
            'Creato da',
            'Creato il',
            'Modificato da',
            'Modificato il',
        ];

        // Scrivi intestazioni
        $sheet->fromArray([$headers], null, 'A1');

        // Stile intestazione
        $style = $sheet->getStyle('A1:' . chr(64 + count($headers)) . '1');
        $style->getFont()->setBold(true);
        $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $style->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F4CCCC');

        // Riga iniziale
        $row = 2;
        foreach ($dipendenti as $dip) {
            $sheet->setCellValue("A{$row}", $dip->Associazione ?? '')
                ->setCellValue("B{$row}", $dip->idAnno ?? '')
                ->setCellValue("C{$row}", $dip->DipendenteCognome ?? '')
                ->setCellValue("D{$row}", $dip->DipendenteNome ?? '')
                ->setCellValue("E{$row}", $dip->Qualifica ?? '')
                ->setCellValue("F{$row}", $dip->LivelloMansione ?? '')
                ->setCellValue("G{$row}", $dip->ContrattoApplicato ?? '')
                ->setCellValue("H{$row}", $dip->created_by_name ?? '')
                ->setCellValue("I{$row}", optional($dip->created_at)->format('Y-m-d H:i') ?? '')
                ->setCellValue("J{$row}", $dip->updated_by_name ?? '')
                ->setCellValue("K{$row}", optional($dip->updated_at)->format('Y-m-d H:i') ?? '');
            $row++;
        }

        // Autosize colonne
        foreach (range('A', chr(64 + count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
