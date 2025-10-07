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

// 👇 aggiunte per ottimizzare memoria
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

use App\Models\Riepilogo;
use App\Models\Automezzo;
use App\Models\Dipendente;
use Carbon\Carbon;

class GeneraRegistriPrimaPaginaXlsJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $tries = 1;
    public $timeout = 600;

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

    private function replacePlaceholdersInSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $map, int $maxRows = 200, int $maxCols = 40): void {
        static $src = ['{{nome_associazione}}','{{ nome_associazione }}','{{anno_riferimento}}','{{ anno_riferimento }}'];
        $dst = [$map['nome_associazione'], $map['nome_associazione'], (string)$map['anno_riferimento'], (string)$map['anno_riferimento']];

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
            // Stato: processing
            DB::table('documenti_generati')
                ->where('id', $this->documentoId)
                ->update(['stato' => 'processing', 'updated_at' => now()]);

            // ==== HARDENING MEMORIA ====
            // 1) Cache celle su disco
            $cacheDir = storage_path('app/phpss-cache');
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
            Settings::setCacheStorageMethod(CachedObjectStorageFactory::cache_to_discISAM, ['dir' => $cacheDir]);

            // 2) Binder "stringa" per evitare inferenze di tipo
            Cell::setValueBinder(new StringValueBinder());

            // (opzionale) alza il limite solo per questo processo CLI
            if (function_exists('ini_get') && function_exists('ini_set')) {
                $cur = ini_get('memory_limit');
                if ($cur !== '-1') @ini_set('memory_limit', '512M');
            }

            // Template
            $baseDir = 'documenti/template_excel';
            $candidates = ["{$baseDir}/REGISTRI.xlsx", "{$baseDir}/REGISTRI.xls"];
            $templateRel = null;
            foreach ($candidates as $cand) {
                if (Storage::disk('public')->exists($cand)) { $templateRel = $cand; break; }
            }
            if (!$templateRel) {
                throw new \RuntimeException("Template REGISTRI.xlsx/xls non trovato in {$baseDir}");
            }
            $templateAbs = Storage::disk('public')->path($templateRel);

            // Reader (mantieni stili del template, ma niente lettura superflua)
            try {
                $inputType   = IOFactory::identify($templateAbs);
                $reader      = IOFactory::createReader($inputType);
                $reader->setReadDataOnly(false);
                $spreadsheet = $reader->load($templateAbs);
            } catch (Throwable $e) {
                throw new \RuntimeException("Errore apertura template Excel: " . $e->getMessage(), 0, $e);
            }

            // Token segnaposto
            $associazione = (string) (DB::table('associazioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->value('Associazione') ?? '');

            $tokens = [
                'nome_associazione' => $associazione,
                'anno_riferimento'  => $this->anno,
            ];

            // Sostituzione segnaposto su tutti i fogli
            foreach ($spreadsheet->getAllSheets() as $ws) {
                $this->replacePlaceholdersInSheet($ws, $tokens, 200, 40);
            }

            /* ==============================
             * FOGLIO 1 – RIEPILOGO
             * ============================== */
            $sheet1 = $spreadsheet->getSheet(0);

            // Indicizzazione descrizioni colonna A (righe 8..200)
            $labelToRow = [];
            for ($row = 8; $row <= 200; $row++) {
                $v = $sheet1->getCell("A{$row}")->getValue();
                if ($v !== null && $v !== '') {
                    $desc = trim((string)$v);
                    if ($desc !== '') $labelToRow[$desc] = $row;
                }
            }

            $rows = \App\Models\Riepilogo::getForDataTable($this->anno, $this->idAssociazione, 'TOT');
            $num = static fn($v) => ($v === null || $v === '' || $v === '-') ? 0.0 : (float)$v;

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

            // Elenco convenzioni da riga 46
            $convenzioni = DB::table('convenzioni')
                ->where('idAssociazione', $this->idAssociazione)
                ->where('idAnno', $this->anno)
                ->orderBy('Convenzione')
                ->pluck('Convenzione')
                ->toArray();

            $letters = [];
            $maxCols = max(200, count($convenzioni));
            for ($i = 0; $i < $maxCols; $i++) {
                $letters[] = Coordinate::stringFromColumnIndex($i + 1);
            }
            $startRow = 46;
            foreach ($convenzioni as $i => $conv) {
                $row     = $startRow + $i;
                $lettera = $letters[$i] ?? '';
                $sheet1->setCellValueExplicit("A{$row}", (string)$conv, DataType::TYPE_STRING);
                $sheet1->setCellValueExplicit("B{$row}", (string)$lettera, DataType::TYPE_STRING);
            }

            /* ==============================
             * FOGLIO 2 – REGISTRO AUTOMEZZI
             * ============================== */
            $sheet2 = $spreadsheet->getSheetByName('REGISTRO AUTOMEZZI') ?? $spreadsheet->getSheet(1);
            if ($sheet2) {
                $headerRow = 5;
                for ($r = 1; $r <= 40; $r++) {
                    $found = false;
                    for ($c = 1; $c <= 20; $c++) {
                        $v = $sheet2->getCellByColumnAndRow($c, $r)->getValue();
                        if (is_string($v) && mb_strtoupper(trim($v)) === 'TARGA') { $headerRow = $r; $found = true; break; }
                    }
                    if ($found) break;
                }
                $dataRow = $headerRow + 1;

                $autos = \App\Models\Automezzo::getByAssociazione($this->idAssociazione, $this->anno);

                $i = 0;
                foreach ($autos as $a) {
                    $row = $dataRow + $i; $i++;

                    $sheet2->setCellValueExplicit("A{$row}", 'AUTO ' . $i, DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("B{$row}", (string)$a->Targa, DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("C{$row}", (string)$a->CodiceIdentificativo, DataType::TYPE_STRING);

                    $sheet2->setCellValue("D{$row}", $a->AnnoPrimaImmatricolazione ?: null);
                    $sheet2->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0');

                    $sheet2->setCellValue("E{$row}", $a->AnnoAcquisto ?: null);
                    $sheet2->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0');

                    $sheet2->setCellValueExplicit("F{$row}", (string)$a->Modello, DataType::TYPE_STRING);
                    $sheet2->setCellValueExplicit("G{$row}", (string)$a->TipoVeicolo, DataType::TYPE_STRING);

                    $sheet2->setCellValue("H{$row}", (float)($a->KmTotali ?? 0));
                    $sheet2->getStyle("H{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                    $sheet2->setCellValue("I{$row}", (float)($a->KmRiferimento ?? 0));
                    $sheet2->getStyle("I{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                    $sheet2->setCellValueExplicit("J{$row}", (string)$a->TipoCarburante, DataType::TYPE_STRING);

                    if (!empty($a->DataUltimaAutorizzazioneSanitaria)) {
                        $kDate = ExcelDate::PHPToExcel(Carbon::parse($a->DataUltimaAutorizzazioneSanitaria));
                        $sheet2->setCellValue("K{$row}", $kDate);
                        $sheet2->getStyle("K{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } else {
                        $sheet2->setCellValueExplicit("K{$row}", '', DataType::TYPE_STRING);
                    }
                    if (!empty($a->DataUltimoCollaudo)) {
                        $lDate = ExcelDate::PHPToExcel(Carbon::parse($a->DataUltimoCollaudo));
                        $sheet2->setCellValue("L{$row}", $lDate);
                        $sheet2->getStyle("L{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } else {
                        $sheet2->setCellValueExplicit("L{$row}", '', DataType::TYPE_STRING);
                    }
                }

                // pulizia (limitata) fino a +200
                for ($r = $dataRow + $i; $r <= $dataRow + 200; $r++) {
                    for ($c = 'A'; $c <= 'L'; $c++) {
                        $sheet2->setCellValueExplicit($c.$r, '', DataType::TYPE_STRING);
                    }
                }
            }

            /* ==============================
             * FOGLIO 3 – PERSONALE DIPENDENTE AUTISTI
             * ============================== */
            $sheet3 = $spreadsheet->getSheetByName('PERSONALE DIPENDENTE AUTISTI') ?? $spreadsheet->getSheet(2);
            if ($sheet3) {
                $all = \App\Models\Dipendente::getByAssociazione($this->idAssociazione, $this->anno)
                    ->sortBy(fn($d) => [mb_strtoupper($d->DipendenteCognome), mb_strtoupper($d->DipendenteNome)])
                    ->values();

                $ids  = $all->pluck('idDipendente')->map(fn($v) => (int)$v)->all();
                $qmap = $this->buildQualificheMap($ids);
                $autisti = $all->filter(fn($d) => in_array(1, $qmap[$d->idDipendente] ?? [], true))->values();

                $headerRow = 2;
                for ($r = 1; $r <= 15; $r++) {
                    $hasC = false; $hasN = false;
                    for ($c = 1; $c <= 10; $c++) {
                        $v = $sheet3->getCellByColumnAndRow($c, $r)->getValue();
                        if (!is_string($v)) continue;
                        $t = mb_strtoupper(trim($v));
                        if ($t === 'COGNOME') $hasC = true;
                        if ($t === 'NOME')    $hasN = true;
                    }
                    if ($hasC && $hasN) { $headerRow = $r; break; }
                }
                $cols = ['COGNOME'=>2,'NOME'=>3,'CONTRATTO'=>4,'LIVELLO'=>5];
                $start = $headerRow + 1;

                $i = 0;
                foreach ($autisti as $d) {
                    $row = $start + $i; $i++;
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['COGNOME'],  $row, $i.' '.mb_strtoupper($d->DipendenteCognome), DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['NOME'],     $row, mb_strtoupper($d->DipendenteNome),          DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['CONTRATTO'],$row, (string)($d->ContrattoApplicato ?? ''),     DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['LIVELLO'],  $row, (string)($d->LivelloMansione ?? ''),        DataType::TYPE_STRING);
                }

                for ($r = $start + $i; $r <= $start + 100; $r++) {
                    foreach ($cols as $ci) {
                        $sheet3->setCellValueExplicitByColumnAndRow($ci, $r, '', DataType::TYPE_STRING);
                    }
                }
            }

            /* ==============================
             * FOGLIO 4 – ALTRO PERSONALE DIPENDENTE
             * ============================== */
            $sheet4 = $spreadsheet->getSheetByName('ALTRO PERSONALE DIPENDENTE') ?? $spreadsheet->getSheet(3);
            if ($sheet4) {
                $sectionsByQualId = [
                    'PERSONALE DIPENDENTE AMMINISTRATIVO'                        => [7],
                    'PERSONALE DIPENDENTE COORDINATORI TECNICI'                 => [6],
                    'PERSONALE DIPENDENTE ADDETTI PULIZIA E SANIFICAZIONE SEDE' => [3],
                    'PERSONALE DIPENDENTE ADDETTI ALLA LOGISTICA'               => [2],
                    'PERSONALE DIPENDENTE COORDINATORI AMMINISTRATIVI'          => [5],
                ];

                $all  = \App\Models\Dipendente::getByAssociazione($this->idAssociazione, $this->anno)->values();
                $ids  = $all->pluck('idDipendente')->map(fn($v) => (int)$v)->all();
                $qmap = $this->buildQualificheMap($ids);

                $bucket = [];
                foreach ($all as $d) {
                    $qids = $qmap[$d->idDipendente] ?? [];
                    foreach ($sectionsByQualId as $title => $idsOk) {
                        if (count(array_intersect($qids, $idsOk)) > 0) {
                            $bucket[$title] ??= [];
                            $bucket[$title][$d->idDipendente] = $d;
                        }
                    }
                }

                $findTitleRow = function ($sheet, string $title, int $maxRows = 600, int $maxCols = 12): ?int {
                    $needle = mb_strtoupper(preg_replace('/\s+/', ' ', trim($title)));
                    for ($r = 1; $r <= $maxRows; $r++) {
                        for ($c = 1; $c <= $maxCols; $c++) {
                            $raw = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                            if (!is_string($raw) || $raw === '') continue;
                            $txt = mb_strtoupper(preg_replace('/\s+/', ' ', trim($raw)));
                            if ($txt === $needle) return $r;
                        }
                    }
                    return null;
                };
                $findHeaderRow = function ($sheet, int $startRow, int $scan = 4): int {
                    for ($r = $startRow; $r <= $startRow + $scan; $r++) {
                        $hasC = false; $hasN = false;
                        for ($c = 1; $c <= 12; $c++) {
                            $v = $sheet->getCellByColumnAndRow($c, $r)->getValue();
                            if (!is_string($v)) continue;
                            $t = mb_strtoupper(trim($v));
                            if ($t === 'COGNOME') $hasC = true;
                            if ($t === 'NOME')    $hasN = true;
                        }
                        if ($hasC && $hasN) return $r;
                    }
                    return $startRow + 1;
                };
                $findHeaderCols = function ($sheet, int $headerRow): array {
                    $cols = ['COGNOME'=>null,'NOME'=>null,'CONTRATTO'=>null,'LIVELLO'=>null];
                    for ($c = 1; $c <= 20; $c++) {
                        $v = $sheet->getCellByColumnAndRow($c, $headerRow)->getValue();
                        if (!is_string($v)) continue;
                        $t = mb_strtoupper(trim($v));
                        if ($t === 'COGNOME') $cols['COGNOME'] = $c;
                        if ($t === 'NOME') $cols['NOME'] = $c;
                        if (str_contains($t, 'CONTRATTO')) $cols['CONTRATTO'] = $c;
                        if (str_contains($t, 'LIVELLO'))   $cols['LIVELLO']   = $c;
                    }
                    $cols['COGNOME']  ??= 2;
                    $cols['NOME']     ??= 3;
                    $cols['CONTRATTO']??= 4;
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
                        $row = $start + $i; $i++;
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['COGNOME'],  $row, $i.' '.mb_strtoupper($d->DipendenteCognome), DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['NOME'],     $row, mb_strtoupper($d->DipendenteNome),          DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['CONTRATTO'],$row, (string)($d->ContrattoApplicato ?? ''),     DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['LIVELLO'],  $row, (string)($d->LivelloMansione ?? ''),        DataType::TYPE_STRING);
                    }

                    for ($r = $start + $i; $r <= $start + 20; $r++) {
                        foreach ($cols as $ci) {
                            $sheet4->setCellValueExplicitByColumnAndRow($ci, $r, '', DataType::TYPE_STRING);
                        }
                    }
                }
            }

            // ===== Salvataggio con caching su disco e senza pre-calcolo formule
            $filename = sprintf('registri_p1_%d_%d_%s.xlsx', $this->idAssociazione, $this->anno, now()->format('Ymd_His'));
            $destRel = 'documenti/' . $filename;
            $destAbs = Storage::disk('public')->path($destRel);

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            if (method_exists($writer, 'setUseDiskCaching')) {
                $writer->setUseDiskCaching(true, $cacheDir);
            }
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

            // Libera subito
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();

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
