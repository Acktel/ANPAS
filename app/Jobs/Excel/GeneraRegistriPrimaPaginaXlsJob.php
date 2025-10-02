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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

use App\Models\Riepilogo;
use App\Models\Automezzo;
use App\Models\Dipendente;
use Carbon\Carbon;

class GeneraRegistriPrimaPaginaXlsJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
    public $tries = 1;      // un tentativo basta: se fallisce, è un bug
    public $timeout = 600;  // gli XLS possono richiedere più di 120s

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('excel');
    }

    public function middleware(): array {
        $key = "xls-registri-p1-{$this->idAssociazione}-{$this->anno}";
        return [
            (new WithoutOverlapping($key))->expireAfter(300),
        ];
    }

    /** Sostituisce i segnaposto testuali nelle celle di un worksheet */
    private function replacePlaceholdersInSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $map, int $maxRows = 200, int $maxCols = 40): void {
        $src = [
            '{{nome_associazione}}',
            '{{ nome_associazione }}',
            '{{anno_riferimento}}',
            '{{ anno_riferimento }}',
        ];
        $dst = [
            $map['nome_associazione'],
            $map['nome_associazione'],
            (string)$map['anno_riferimento'],
            (string)$map['anno_riferimento'],
        ];

        for ($r = 1; $r <= $maxRows; $r++) {
            for ($c = 1; $c <= $maxCols; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $val  = $cell->getValue();
                if (!is_string($val) || $val === '') continue;
                $new = str_replace($src, $dst, $val);
                if ($new !== $val) {
                    $cell->setValueExplicit($new, DataType::TYPE_STRING);
                }
            }
        }
    }

    public function handle(): void {
        try {
            // stato: processing
            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update(['stato' => 'processing', 'updated_at' => now()]);

            // template (xlsx preferito, fallback xls)
            $baseDir = 'documenti/template_excel';
            $candidates = [
                "{$baseDir}/REGISTRI.xlsx",
                "{$baseDir}/REGISTRI.xls",
            ];
            $templateRel = null;
            foreach ($candidates as $cand) {
                if (Storage::disk('public')->exists($cand)) {
                    $templateRel = $cand;
                    break;
                }
            }
            if (!$templateRel) {
                throw new \RuntimeException("Template REGISTRI.xlsx/xls non trovato in {$baseDir}");
            }
            $templateAbs = Storage::disk('public')->path($templateRel);

            // carica
            try {
                $inputType   = IOFactory::identify($templateAbs);
                $reader      = IOFactory::createReader($inputType);
                $reader->setReadDataOnly(false);
                $spreadsheet = $reader->load($templateAbs);
            } catch (Throwable $e) {
                throw new \RuntimeException("Errore apertura template Excel: " . $e->getMessage(), 0, $e);
            }

            // token segnaposto
            $associazione = (string) DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->value('Associazione') ?? '';

            $tokens = [
                'nome_associazione' => $associazione,
                'anno_riferimento'  => $this->anno,
            ];

            // sostituzione segnaposto su tutti i fogli
            foreach ($spreadsheet->getAllSheets() as $ws) {
                $this->replacePlaceholdersInSheet($ws, $tokens, 200, 40);
            }

            /* ==============================
         * FOGLIO 1 – RIEPILOGO 
         * ============================== */
            $sheet1 = $spreadsheet->getSheet(0);

            // mappo le descrizioni della colonna A (righe 8..200)
            $labelToRow = [];
            for ($row = 8; $row <= 200; $row++) {
                $desc = trim((string) $sheet1->getCell("A{$row}")->getValue());
                if ($desc !== '') $labelToRow[$desc] = $row;
            }

            $rows = Riepilogo::getForDataTable($this->anno, $this->idAssociazione, 'TOT');
            $num = fn($v) => ($v === null || $v === '' || $v === '-') ? 0.0 : (float) $v;

            foreach ($rows as $r) {
                $desc = (string) ($r['descrizione'] ?? '');
                if (!isset($labelToRow[$desc])) continue;

                $row = $labelToRow[$desc];
                $sheet1->setCellValue("B{$row}", $num($r['preventivo'] ?? 0));
                $sheet1->setCellValue("C{$row}", $num($r['consuntivo'] ?? 0));
                $sheet1->getStyle("B{$row}:C{$row}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            }

            // Seconda tabella (elenco convenzioni) da riga 46
            $convenzioni = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('Convenzione')
                ->pluck('Convenzione')
                ->toArray();

            $letters = [];
            for ($i = 0; $i < max(200, count($convenzioni)); $i++) {
                $letters[] = Coordinate::stringFromColumnIndex($i + 1);
            }
            $startRow = 46;
            foreach ($convenzioni as $i => $conv) {
                $row     = $startRow + $i;
                $lettera = $letters[$i] ?? '';
                $sheet1->setCellValueExplicit("A{$row}", $conv, DataType::TYPE_STRING);
                $sheet1->setCellValueExplicit("B{$row}", $lettera, DataType::TYPE_STRING);
            }

            /* ==============================
         * FOGLIO 2 – REGISTRO AUTOMEZZI
         * ============================== */
            // prendo per nome, altrimenti indice 1
            $sheet2 = $spreadsheet->getSheetByName('REGISTRO AUTOMEZZI') ?? $spreadsheet->getSheet(1);

            if ($sheet2) {
                // Trova la riga di intestazione cercando “TARGA”; se non trovata uso 5
                $headerRow = 5;
                for ($r = 1; $r <= 40; $r++) {
                    for ($c = 1; $c <= 20; $c++) {
                        $v = trim((string)$sheet2->getCellByColumnAndRow($c, $r)->getValue());
                        if (mb_strtoupper($v) === 'TARGA') {
                            $headerRow = $r;
                            break 2;
                        }
                    }
                }
                $dataRow = $headerRow + 1;

                // Dati automezzi per associazione/anno (già joinati con tipi e km)
                $autos = Automezzo::getByAssociazione($this->idAssociazione, $this->anno);

                $i = 0;
                foreach ($autos as $a) {
                    $row = $dataRow + $i;
                    $i++;

                    // A: progressivo “AUTO n”
                    $sheet2->setCellValueExplicit("A{$row}", 'AUTO ' . $i, DataType::TYPE_STRING);

                    // B..L colonne come screenshot
                    $sheet2->setCellValueExplicit("B{$row}", (string)$a->Targa, DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("C{$row}", (string)$a->CodiceIdentificativo, DataType::TYPE_STRING);

                    $sheet2->setCellValue("D{$row}", $a->AnnoPrimaImmatricolazione ?: null);
                    $sheet2->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0');

                    $sheet2->setCellValue("E{$row}", $a->AnnoAcquisto ?: null);
                    $sheet2->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0');

                    $sheet2->setCellValueExplicit("F{$row}", (string)$a->Modello, DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("G{$row}", (string)$a->TipoVeicolo, DataType::TYPE_STRING);

                    // H: Km esercizio (anno corrente) => KmTotali
                    $sheet2->setCellValue("H{$row}", (float)($a->KmTotali ?? 0));
                    $sheet2->getStyle("H{$row}")->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                    // I: Totale km al 31/12 anno precedente => KmRiferimento
                    $sheet2->setCellValue("I{$row}", (float)($a->KmRiferimento ?? 0));
                    $sheet2->getStyle("I{$row}")->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                    // J: Carburante
                    $sheet2->setCellValueExplicit("J{$row}", (string)$a->TipoCarburante, DataType::TYPE_STRING);

                    // K: Data ultima autorizzazione sanitaria
                    if (!empty($a->DataUltimaAutorizzazioneSanitaria)) {
                        $kDate = ExcelDate::PHPToExcel(Carbon::parse($a->DataUltimaAutorizzazioneSanitaria));
                        $sheet2->setCellValue("K{$row}", $kDate);
                        $sheet2->getStyle("K{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } else {
                        $sheet2->setCellValueExplicit("K{$row}", '', DataType::TYPE_STRING);
                    }

                    // L: Data ultimo revisione/collaudo
                    if (!empty($a->DataUltimoCollaudo)) {
                        $lDate = ExcelDate::PHPToExcel(Carbon::parse($a->DataUltimoCollaudo));
                        $sheet2->setCellValue("L{$row}", $lDate);
                        $sheet2->getStyle("L{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } else {
                        $sheet2->setCellValueExplicit("L{$row}", '', DataType::TYPE_STRING);
                    }
                }

                // (opzionale) pulizia righe residue fino a +200
                for ($r = $dataRow + $i; $r <= $dataRow + 200; $r++) {
                    $sheet2->setCellValueExplicit("A{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("B{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("C{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("D{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("E{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("F{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("G{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("H{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("I{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("J{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("K{$r}", '', DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("L{$r}", '', DataType::TYPE_STRING);
                }
            }

            /* ==============================
        * FOGLIO 3 – PERSONALE DIPENDENTE AUTISTI
        * ============================== */
            $sheet3 = $spreadsheet->getSheetByName('PERSONALE DIPENDENTE AUTISTI') ?? $spreadsheet->getSheet(2);

            if ($sheet3) {
                // prendo tutti i dip dell’associazione/anno
                $all = Dipendente::getByAssociazione($this->idAssociazione, $this->anno)
                    ->sortBy(fn($d) => [mb_strtoupper($d->DipendenteCognome), mb_strtoupper($d->DipendenteNome)])
                    ->values();

                $ids   = $all->pluck('idDipendente')->map(fn($v) => (int)$v)->all();
                $qmap  = $this->buildQualificheMap($ids);

                // tieni solo chi ha idQualifica = 1 (AUTISTA SOCCORRITORE)
                $autisti = $all->filter(fn($d) => in_array(1, $qmap[$d->idDipendente] ?? [], true))->values();

                // trova header (cerca “COGNOME” / “NOME”)
                $headerRow = 2;
                for ($r = 1; $r <= 15; $r++) {
                    $hasC = false;
                    $hasN = false;
                    for ($c = 1; $c <= 10; $c++) {
                        $v = mb_strtoupper(trim((string)$sheet3->getCellByColumnAndRow($c, $r)->getValue()));
                        if ($v === 'COGNOME') $hasC = true;
                        if ($v === 'NOME')    $hasN = true;
                    }
                    if ($hasC && $hasN) {
                        $headerRow = $r;
                        break;
                    }
                }
                $cols = ['COGNOME' => 2, 'NOME' => 3, 'CONTRATTO' => 4, 'LIVELLO' => 5]; // B,C,D,E
                $start = $headerRow + 1;

                $i = 0;
                foreach ($autisti as $d) {
                    $row = $start + $i;
                    $i++;
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['COGNOME'], $row, $i . ' ' . mb_strtoupper($d->DipendenteCognome), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['NOME'],   $row, mb_strtoupper($d->DipendenteNome), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['CONTRATTO'], $row, (string)($d->ContrattoApplicato ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['LIVELLO'], $row, (string)($d->LivelloMansione ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }

                // pulizia righe in eccesso (fino a +100)
                for ($r = $start + $i; $r <= $start + 100; $r++) {
                    foreach ($cols as $ci) {
                        $sheet3->setCellValueExplicitByColumnAndRow($ci, $r, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                }
            }

            /* ==============================
        * FOGLIO 4 – ALTRO PERSONALE DIPENDENTE
        * ============================== */
            $sheet4 = $spreadsheet->getSheetByName('ALTRO PERSONALE DIPENDENTE') ?? $spreadsheet->getSheet(3);

            if ($sheet4) {
                // titolo sezione => idQualifica
                $sectionsByQualId = [
                    'PERSONALE DIPENDENTE AMMINISTRATIVO'                        => [7], // IMPIEGATO AMMINISTRATIVO
                    'PERSONALE DIPENDENTE COORDINATORI TECNICI'                 => [6], // COORDINATORE TECNICO
                    'PERSONALE DIPENDENTE ADDETTI PULIZIA E SANIFICAZIONE SEDE' => [3], // ADDETTO PULIZIA
                    'PERSONALE DIPENDENTE ADDETTI ALLA LOGISTICA'               => [2], // ADDETTO LOGISTICA
                    'PERSONALE DIPENDENTE COORDINATORI AMMINISTRATIVI'          => [5], // COORD. AMMINISTRATIVO
                ];

                $all  = \App\Models\Dipendente::getByAssociazione($this->idAssociazione, $this->anno)->values();
                $ids  = $all->pluck('idDipendente')->map(fn($v) => (int)$v)->all();
                $qmap = $this->buildQualificheMap($ids);

                $others = $all;
                // bucket per sezione (lo stesso dipendente può apparire in più sezioni)
                $bucket = [];
                foreach ($others as $d) {
                    $qids = $qmap[$d->idDipendente] ?? [];
                    foreach ($sectionsByQualId as $title => $idsOk) {
                        if (count(array_intersect($qids, $idsOk)) > 0) {
                            $bucket[$title] ??= [];
                            $bucket[$title][$d->idDipendente] = $d; // no doppie righe nella stessa sezione
                        }
                    }
                }

                // helpers per posizionarsi nelle box del template
                $findTitleRow = function (\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $title, int $maxRows = 600, int $maxCols = 12): ?int {
                    $needle = mb_strtoupper(preg_replace('/\s+/', ' ', trim($title)));
                    for ($r = 1; $r <= $maxRows; $r++) {
                        for ($c = 1; $c <= $maxCols; $c++) {
                            $raw = (string) $sheet->getCellByColumnAndRow($c, $r)->getValue();
                            if ($raw === '') continue;
                            $txt = mb_strtoupper(preg_replace('/\s+/', ' ', trim($raw)));
                            if ($txt === $needle) return $r;
                        }
                    }
                    return null;
                };
                $findHeaderRow = function (\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $startRow, int $scan = 4): int {
                    for ($r = $startRow; $r <= $startRow + $scan; $r++) {
                        $hasC = false;
                        $hasN = false;
                        for ($c = 1; $c <= 12; $c++) {
                            $v = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                            if ($v === 'COGNOME') $hasC = true;
                            if ($v === 'NOME')    $hasN = true;
                        }
                        if ($hasC && $hasN) return $r;
                    }
                    return $startRow + 1;
                };
                $findHeaderCols = function (\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow): array {
                    $cols = ['COGNOME' => null, 'NOME' => null, 'CONTRATTO' => null, 'LIVELLO' => null];
                    for ($c = 1; $c <= 20; $c++) {
                        $v = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
                        if ($v === 'COGNOME') $cols['COGNOME'] = $c;
                        if ($v === 'NOME')    $cols['NOME'] = $c;
                        if (str_contains($v, 'CONTRATTO')) $cols['CONTRATTO'] = $c;
                        if (str_contains($v, 'LIVELLO'))   $cols['LIVELLO']   = $c;
                    }
                    // fallback B,C,D,E
                    $cols['COGNOME']  ??= 2;
                    $cols['NOME']     ??= 3;
                    $cols['CONTRATTO'] ??= 4;
                    $cols['LIVELLO']  ??= 5;
                    return $cols;
                };

                foreach ($sectionsByQualId as $title => $_) {
                    $people = collect(array_values($bucket[$title] ?? []))
                        ->sortBy(fn($d) => [mb_strtoupper($d->DipendenteCognome), mb_strtoupper($d->DipendenteNome)])
                        ->values();

                    $titleRow  = $findTitleRow($sheet4, $title);
                    if (!$titleRow) continue;
                    $headerRow = $findHeaderRow($sheet4, $titleRow + 1, 4);
                    $cols      = $findHeaderCols($sheet4, $headerRow);
                    $start     = $headerRow + 1;

                    $i = 0;
                    foreach ($people as $d) {
                        $row = $start + $i;
                        $i++;
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['COGNOME'],  $row, $i . ' ' . mb_strtoupper($d->DipendenteCognome), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['NOME'],     $row, mb_strtoupper($d->DipendenteNome), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['CONTRATTO'], $row, (string)($d->ContrattoApplicato ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['LIVELLO'],  $row, (string)($d->LivelloMansione ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }

                    // pulizia fino a +20 righe
                    for ($r = $start + $i; $r <= $start + 20; $r++) {
                        foreach ($cols as $ci) {
                            $sheet4->setCellValueExplicitByColumnAndRow($ci, $r, '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        }
                    }
                }
            }

            /* ==============================
         * SALVATAGGIO
         * ============================== */
            $filename = sprintf(
                'registri_p1_%d_%d_%s.xlsx',
                $this->idAssociazione,
                $this->anno,
                now()->format('Ymd_His')
            );

            $destRel = 'documenti/' . $filename;
            $destAbs = Storage::disk('public')->path($destRel);

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($destAbs);

            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update([
                    'percorso_file' => $destRel,
                    'nome_file'     => $filename,
                    'stato'         => 'ready',
                    'generato_il'   => now(),
                    'updated_at'    => now(),
                ]);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (Throwable $e) {
            Log::error('REGISTRI P1 XLS: errore in handle', [
                'docId' => $this->documentoId,
                'assoc' => $this->idAssociazione,
                'anno'  => $this->anno,
                'msg'   => $e->getMessage(),
            ]);
            $this->fail($e);
            return;
        }
    }

    public function failed(Throwable $e): void {
        Log::error('GeneraRegistriPrimaPaginaXlsJob failed', [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
            'error'          => $e->getMessage(),
        ]);

        DB::table('documenti_generati')
            ->where('id', $this->documentoId)
            ->update(['stato' => 'error', 'updated_at' => now()]);
    }

    /** Ritorna [ idDipendente => [idQualifica, ...] ] limitato a un set di id */
    private function buildQualificheMap(array $dipIds): array {
        if (empty($dipIds)) return [];

        return DB::table('dipendenti_qualifiche')
            ->whereIn('idDipendente', $dipIds)
            ->select('idDipendente', 'idQualifica')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($rows) => $rows->pluck('idQualifica')->map(fn($x) => (int)$x)->unique()->values()->all())
            ->toArray();
    }
}
