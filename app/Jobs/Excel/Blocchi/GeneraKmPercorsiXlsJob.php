<?php

namespace App\Jobs\Excel\Blocchi;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Throwable;

class GeneraKmPercorsiXlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $backoff = 0;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno
    ) { $this->onQueue('excel'); }

    public function handle(): void
    {
        $tplRel = 'documenti/template_excel/KmPercorsi.xlsx'; // già presente
        if (!Storage::disk('public')->exists($tplRel)) {
            throw new \RuntimeException("Template mancante: {$tplRel}");
        }

        $tplAbs = Storage::disk('public')->path($tplRel);
        $outRel = "tmp/excel/{$this->documentoId}_km.xlsx";
        $outAbs = Storage::disk('public')->path($outRel);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $wb = $reader->load($tplAbs);
        $ws = $wb->getSheet(0);

        // --- segnaposto base
        $nomeAssoc = (string) DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->value('Associazione');

        $this->replaceAll($ws, [
            '{{nome_associazione}}' => $nomeAssoc,
            '{{ anno_riferimento }}' => $this->anno,
            '{{anno_riferimento}}' => $this->anno,
        ]);

        // --- dati base
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione','Convenzione','ordinamento')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get();

        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('idAutomezzo')
            ->get();

        $kmGrouped = DB::table('automezzi_km as k')
            ->join('convenzioni as c','c.idConvenzione','=','k.idConvenzione')
            ->where('c.idAnno',$this->anno)
            ->select('k.idAutomezzo','k.idConvenzione','k.KMPercorsi')
            ->get()
            ->groupBy(fn($r)=>$r->idAutomezzo.'-'.$r->idConvenzione);

        // --- coordinate dal template (robuste)
        // Se nel template hai Named Ranges, usa questi nomi. Fallback hardcoded se non ci sono.
        [$headerRow, $startRow, $pairStartCol] = $this->detectLayout($ws, [
            'HEADER_ROW' => null,   // es. riga intestazioni
            'DATA_START' => null,   // es. prima riga dati
            'PAIR_START' => null,   // es. prima colonna coppie (KM, %)
        ], fallback: [
            'HEADER_ROW' => 10,
            'DATA_START' => 11,
            'PAIR_START' => 5, // E
        ]);
        $colProgr = 1; // A
        $colTarga = 2; // B
        $colCodice= 3; // C
        $colTot   = 4; // D

        // intestazioni convenzioni nelle coppie
        for ($i=0; $i<$convenzioni->count(); $i++) {
            $kmCol = $pairStartCol + ($i*2);
            $pcCol = $kmCol + 1;
            $kmL = Coordinate::stringFromColumnIndex($kmCol);
            $pcL = Coordinate::stringFromColumnIndex($pcCol);
            // scrivo il titolo convenzione sopra (riga headerRow - 1) se vuoto
            $titleRow = max(1, $headerRow - 1);
            if (trim((string)$ws->getCellByColumnAndRow($kmCol, $titleRow)->getValue()) === '') {
                $ws->setCellValueByColumnAndRow($kmCol, $titleRow, (string)$convenzioni[$i]->Convenzione);
            }
            if (trim((string)$ws->getCellByColumnAndRow($kmCol, $headerRow)->getValue()) === '') {
                $ws->setCellValue("{$kmL}{$headerRow}", 'KM. PERCORSI');
            }
            if (trim((string)$ws->getCellByColumnAndRow($pcCol, $headerRow)->getValue()) === '') {
                $ws->setCellValue("{$pcL}{$headerRow}", '%');
            }
        }

        // --- scrittura righe
        $row = $startRow;
        $totKmAll = 0.0;
        $totByConv = [];
        foreach ($convenzioni as $cv) $totByConv['c'.$cv->idConvenzione]=0.0;

        $idx=0;
        foreach ($automezzi as $a) {
            $idx++;
            // somma km del veicolo
            $sum = 0.0;
            foreach ($convenzioni as $cv) {
                $key = $a->idAutomezzo.'-'.$cv->idConvenzione;
                $sum += (float)($kmGrouped->get($key)?->first()->KMPercorsi ?? 0);
            }
            $totKmAll += $sum;

            $ws->setCellValueByColumnAndRow($colProgr, $row, 'AUTO '.$idx);
            $ws->setCellValueExplicitByColumnAndRow($colTarga, $row, (string)$a->Targa, DataType::TYPE_STRING);
            $ws->setCellValueExplicitByColumnAndRow($colCodice,$row, (string)($a->CodiceIdentificativo ?? ''), DataType::TYPE_STRING);
            $ws->setCellValueByColumnAndRow($colTot, $row, $sum);
            $ws->getStyleByColumnAndRow($colTot, $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            for ($i=0; $i<$convenzioni->count(); $i++) {
                $cv = $convenzioni[$i];
                $kmCol = $pairStartCol + ($i*2);
                $pcCol = $kmCol + 1;

                $kmL = Coordinate::stringFromColumnIndex($kmCol);
                $pcL = Coordinate::stringFromColumnIndex($pcCol);

                $key = $a->idAutomezzo.'-'.$cv->idConvenzione;
                $val = (float)($kmGrouped->get($key)?->first()->KMPercorsi ?? 0);
                $p   = $sum>0 ? $val/$sum : 0.0;

                $ws->setCellValue("{$kmL}{$row}", $val);
                $ws->getStyle("{$kmL}{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                // se la cella percentuale NON ha formula, metto il valore
                $cur = (string)$ws->getCell("{$pcL}{$row}")->getValue();
                if ($cur === '' || $cur[0] !== '=') {
                    $ws->setCellValue("{$pcL}{$row}", $p);
                    $ws->getStyle("{$pcL}{$row}")->getNumberFormat()->setFormatCode('0.00%');
                }

                $totByConv['c'.$cv->idConvenzione] += $val;
            }

            $row++;
        }

        // riga TOTALE (se vuoi nel mini-template già stilata, qui solo i numeri)
        $ws->setCellValueByColumnAndRow($colProgr, $row, 'TOTALE');
        $ws->setCellValueByColumnAndRow($colTot,   $row, $totKmAll);
        $ws->getStyleByColumnAndRow($colTot, $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        $percentSum = 0.0;
        $last = max(0, $convenzioni->count()-1);
        for ($i=0; $i<$convenzioni->count(); $i++) {
            $cv = $convenzioni[$i];
            $kmCol = $pairStartCol + ($i*2);
            $pcCol = $kmCol + 1;

            $kmL = Coordinate::stringFromColumnIndex($kmCol);
            $pcL = Coordinate::stringFromColumnIndex($pcCol);

            $v = (float)$totByConv['c'.$cv->idConvenzione];
            $ws->setCellValue("{$kmL}{$row}", $v);
            $ws->getStyle("{$kmL}{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            $p = ($i < $last && $totKmAll>0) ? ($v/$totKmAll) : max(0.0, 1.0-$percentSum);
            if ($i < $last) $percentSum += $p;

            $cur = (string)$ws->getCell("{$pcL}{$row}")->getValue();
            if ($cur === '' || $cur[0] !== '=') {
                $ws->setCellValue("{$pcL}{$row}", $p);
                $ws->getStyle("{$pcL}{$row}")->getNumberFormat()->setFormatCode('0.00%');
            }
        }

        // salva tiny workbook
        $writer = IOFactory::createWriter($wb, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($outAbs);

        $wb->disconnectWorksheets();
    }

    private function replaceAll(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $map): void {
        $maxRow = $ws->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($ws->getHighestColumn());
        foreach ($map as $src => $dst) {
            for ($r=1; $r<=$maxRow; $r++) {
                for ($c=1; $c<=$maxCol; $c++) {
                    $cell = $ws->getCellByColumnAndRow($c,$r);
                    $val  = $cell->getValue();
                    if (is_string($val) && $val !== '' && str_contains($val, $src)) {
                        $cell->setValueExplicit(str_replace($src,(string)$dst,$val), DataType::TYPE_STRING);
                    }
                }
            }
        }
    }

    private function detectLayout($ws, array $names, array $fallback): array {
        $named = $ws->getParent()->getNamedRanges();
        $res = [];
        foreach (['HEADER_ROW','DATA_START','PAIR_START'] as $k) {
            $val = null;
            if (isset($names[$k]) && $names[$k]) $val = $names[$k];
            else {
                $nr = $ws->getParent()->getNamedRange($k);
                if ($nr) {
                    $cell = $nr->getCells()[0] ?? $nr->getRange();
                    // estrae riga/colonna dall’indirizzo
                    if (preg_match('/([A-Z]+)(\d+)/', is_string($cell)?$cell:(string)$cell, $m)) {
                        if ($k === 'PAIR_START') {
                            $val = Coordinate::columnIndexFromString($m[1]);
                        } elseif ($k === 'HEADER_ROW' || $k === 'DATA_START') {
                            $val = (int)$m[2];
                        }
                    }
                }
            }
            $res[$k] = $val ?? $fallback[$k];
        }
        return [$res['HEADER_ROW'], $res['DATA_START'], $res['PAIR_START']];
    }
}
