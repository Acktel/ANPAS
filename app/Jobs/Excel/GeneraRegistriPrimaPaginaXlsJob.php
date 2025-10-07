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
use PhpOffice\PhpSpreadsheet\Settings;
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
        return [(new WithoutOverlapping("xls-registri-p1-{$this->idAssociazione}-{$this->anno}"))->expireAfter(300)];
    }

    /* ---------- utility header a due righe (foglio 3) ---------- */
    private function findTwoLineHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $scanRows = 30): array {
        for ($r = 1; $r <= $scanRows; $r++) {
            $hasCog = false;
            $hasNome = false;
            for ($c = 1; $c <= 15; $c++) {
                $t1 = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                $t2 = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r + 1)->getValue()));
                if ($t1 === 'COGNOME') $hasCog = true;
                if ($t2 === 'NOME')    $hasNome = true;
            }
            if ($hasCog && $hasNome) return [$r, $r + 1];
        }
        return [2, 3];
    }
    private function resolveTwoLineHeaderCols(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $rowTop, int $rowBottom): array {
        $cols = ['B' => 2, 'C' => 3, 'D' => 4]; // fallback B,C, D
        for ($c = 1; $c <= 20; $c++) {
            $tTop = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $rowTop)->getValue()));
            $tBot = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $rowBottom)->getValue()));
            if ($tTop === 'COGNOME') $cols['B'] = $c;
            if ($tTop === 'QUALIFICA') $cols['C'] = $c;
            if ($tTop === 'CONTRATTO APPLICATO') $cols['D'] = $c;
            if ($tBot === 'NOME') $cols['B'] = $cols['B'] ?? $c;
            if ($tBot === 'LIVELLO E MANSIONE') $cols['D'] = $cols['D'] ?? $c;
        }
        return $cols;
    }

    /* ---------- utility header a una riga (foglio 4) ---------- */
    private function findTitleRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $title, int $maxRows = 600, int $maxCols = 14): ?int {
        $needle = mb_strtoupper(preg_replace('/\s+/', ' ', trim($title)));
        for ($r = 1; $r <= $maxRows; $r++) {
            for ($c = 1; $c <= $maxCols; $c++) {
                $raw = (string)$sheet->getCellByColumnAndRow($c, $r)->getValue();
                if ($raw === '') continue;
                $txt = mb_strtoupper(preg_replace('/\s+/', ' ', trim($raw)));
                if ($txt === $needle) return $r;
            }
        }
        return null;
    }
    private function findHeaderRowSingle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $startRow, int $scan = 6): int {
        for ($r = $startRow; $r <= $startRow + $scan; $r++) {
            $hasC = false;
            $hasN = false;
            for ($c = 1; $c <= 20; $c++) {
                $v = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $r)->getValue()));
                if ($v === 'COGNOME') $hasC = true;
                if ($v === 'NOME')    $hasN = true;
            }
            if ($hasC && $hasN) return $r;
        }
        return $startRow + 1;
    }
    private function findHeaderColsSingle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $headerRow): array {
        $cols = ['COGNOME' => 2, 'NOME' => 3, 'CONTRATTO' => 4, 'LIVELLO' => 5]; // fallback B..E
        for ($c = 1; $c <= 20; $c++) {
            $v = mb_strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, $headerRow)->getValue()));
            if ($v === 'COGNOME') $cols['COGNOME'] = $c;
            if ($v === 'NOME')    $cols['NOME']    = $c;
            if (str_contains($v, 'CONTRATTO')) $cols['CONTRATTO'] = $c;
            if (str_contains($v, 'LIVELLO'))   $cols['LIVELLO']   = $c;
        }
        return $cols;
    }

    /** segnaposto */
    private function replacePlaceholdersInSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $map, int $maxRows = 200, int $maxCols = 40): void {
        static $src = ['{{nome_associazione}}', '{{ nome_associazione }}', '{{anno_riferimento}}', '{{ anno_riferimento }}'];
        $dst = [
            (string)($map['nome_associazione'] ?? ''),
            (string)($map['nome_associazione'] ?? ''),
            (string)($map['anno_riferimento'] ?? ''),
            (string)($map['anno_riferimento'] ?? ''),
        ];
        for ($r = 1; $r <= $maxRows; $r++) {
            for ($c = 1; $c <= $maxCols; $c++) {
                $cell = $sheet->getCellByColumnAndRow($c, $r);
                $val  = $cell->getValue();
                if (!is_string($val) || $val === '') continue;
                $new = str_replace($src, $dst, $val);
                if ($new !== $val) $cell->setValueExplicit($new, DataType::TYPE_STRING);
            }
        }
    }

    /** [ idDipendente => [idQualifica,...] ] */
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

    public function handle(): void {
        try {
            DB::table('documenti_generati')->where('id', $this->documentoId)->update(['stato' => 'processing', 'updated_at' => now()]);

            // compat PhpSpreadsheet v2
            $tmp = storage_path('app/phpss-cache');
            if (!is_dir($tmp)) @mkdir($tmp, 0775, true);
            if (method_exists(Settings::class, 'setTempDir')) Settings::setTempDir($tmp);
            Cell::setValueBinder(new StringValueBinder());
            if (function_exists('ini_set')) @ini_set('memory_limit', '512M');

            // template
            $base = 'documenti/template_excel';
            $tpl = null;
            foreach (["$base/REGISTRI.xlsx", "$base/REGISTRI.xls"] as $cand) if (Storage::disk('public')->exists($cand)) {
                $tpl = $cand;
                break;
            }
            if (!$tpl) throw new \RuntimeException("Template REGISTRI.xlsx/xls non trovato in $base");
            $abs = Storage::disk('public')->path($tpl);

            $reader = IOFactory::createReader(IOFactory::identify($abs));
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($abs);

            // placeholders
            $asso = (string)(DB::table('associazioni')->where('idAssociazione', $this->idAssociazione)->value('Associazione') ?? '');
            foreach ($spreadsheet->getAllSheets() as $ws) $this->replacePlaceholdersInSheet($ws, ['nome_associazione' => $asso, 'anno_riferimento' => $this->anno], 200, 40);

            /* ====== FOGLIO 1 – RIEPILOGO ====== */
            $sheet1 = $spreadsheet->getSheet(0);
            $labelToRow = [];
            for ($row = 8; $row <= 200; $row++) {
                $v = $sheet1->getCell("A{$row}")->getValue();
                if ($v !== null && $v !== '') $labelToRow[trim((string)$v)] = $row;
            }
            $rows = Riepilogo::getForDataTable($this->anno, $this->idAssociazione, 'TOT');
            $num = static fn($v) => ($v === null || $v === '' || $v === '-') ? 0.0 : (float)$v;
            foreach ($rows as $r) {
                $d = (string)($r['descrizione'] ?? '');
                if (!isset($labelToRow[$d])) continue;
                $row = $labelToRow[$d];
                $sheet1->setCellValue("B{$row}", $num($r['preventivo'] ?? 0));
                $sheet1->setCellValue("C{$row}", $num($r['consuntivo'] ?? 0));
                $sheet1->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            }
            $convs = DB::table('convenzioni')->where('idAssociazione', $this->idAssociazione)->where('idAnno', $this->anno)->orderBy('Convenzione')->pluck('Convenzione')->toArray();
            $letters = [];
            for ($i = 0; $i < max(200, count($convs)); $i++) $letters[] = Coordinate::stringFromColumnIndex($i + 1);
            $start = 46;
            foreach ($convs as $i => $conv) {
                $row = $start + $i;
                $sheet1->setCellValueExplicit("A{$row}", (string)$conv, DataType::TYPE_STRING);
                $sheet1->setCellValueExplicit("B{$row}", (string)($letters[$i] ?? ''), DataType::TYPE_STRING);
            }

            /* ====== FOGLIO 2 – REGISTRO AUTOMEZZI ====== */
            $sheet2 = $spreadsheet->getSheetByName('REGISTRO AUTOMEZZI') ?? $spreadsheet->getSheet(1);
            if ($sheet2) {
                $headerRow = 5;
                for ($r = 1; $r <= 40; $r++) {
                    for ($c = 1; $c <= 20; $c++) {
                        $v = $sheet2->getCellByColumnAndRow($c, $r)->getValue();
                        if (is_string($v) && mb_strtoupper(trim($v)) === 'TARGA') {
                            $headerRow = $r;
                            break 2;
                        }
                    }
                }
                $dataRow = $headerRow + 1;
                $autos = Automezzo::getByAssociazione($this->idAssociazione, $this->anno);
                $i = 0;
                foreach ($autos as $a) {
                    $row = $dataRow + $i;
                    $i++;
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
                        $k = ExcelDate::PHPToExcel(Carbon::parse($a->DataUltimaAutorizzazioneSanitaria));
                        $sheet2->setCellValue("K{$row}", $k);
                        $sheet2->getStyle("K{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } else {
                        $sheet2->setCellValueExplicit("K{$row}", '', DataType::TYPE_STRING);
                    }
                    if (!empty($a->DataUltimoCollaudo)) {
                        $l = ExcelDate::PHPToExcel(Carbon::parse($a->DataUltimoCollaudo));
                        $sheet2->setCellValue("L{$row}", $l);
                        $sheet2->getStyle("L{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } else {
                        $sheet2->setCellValueExplicit("L{$row}", '', DataType::TYPE_STRING);
                    }
                }
                for ($r = $dataRow + $i; $r <= $dataRow + 200; $r++) {
                    for ($c = 'A'; $c <= 'L'; $c++) $sheet2->setCellValueExplicit($c . $r, '', DataType::TYPE_STRING);
                }
            }

            /* ====== FOGLIO 3 – PERSONALE DIPENDENTE AUTISTI (due righe, QUALIFICA solo riga alta) ====== */
            $sheet3 = $spreadsheet->getSheetByName('PERSONALE DIPENDENTE AUTISTI') ?? $spreadsheet->getSheet(2);
            if ($sheet3) {
                $all = Dipendente::getByAssociazione($this->idAssociazione, $this->anno)
                    ->sortBy(fn($d) => [mb_strtoupper($d->DipendenteCognome), mb_strtoupper($d->DipendenteNome)])->values();
                $ids  = $all->pluck('idDipendente')->map(fn($v) => (int)$v)->all();
                $qmap = $this->buildQualificheMap($ids);
                $autisti = $all->filter(fn($d) => in_array(1, $qmap[$d->idDipendente] ?? [], true))->values();

                $contrattiMap  = DB::table('contratti_applicati')->pluck('nome', 'id')->all();
                $qualificheMap = DB::table('qualifiche')->pluck('nome', 'id')->all();
                $qualAutista   = (string)($qualificheMap[1] ?? 'AUTISTA SOCCORRITORE');

                [$hdrTop, $hdrBot] = $this->findTwoLineHeader($sheet3, 20);
                $cols = $this->resolveTwoLineHeaderCols($sheet3, $hdrTop, $hdrBot);
                $start = $hdrTop + 2;

                $i = 0;
                foreach ($autisti as $d) {
                    $rTop = $start + ($i * 2);
                    $rBot = $rTop + 1;
                    $i++;

                    $sheet3->setCellValueExplicitByColumnAndRow($cols['B'], $rTop, $i . ' ' . mb_strtoupper((string)$d->DipendenteCognome), DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['B'], $rBot, mb_strtoupper((string)$d->DipendenteNome), DataType::TYPE_STRING);

                    // QUALIFICA solo riga alta
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['C'], $rTop, $qualAutista, DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['C'], $rBot, '', DataType::TYPE_STRING);

                    $contratto = $d->ContrattoApplicato;
                    if (is_numeric($contratto)) $contratto = $contrattiMap[(int)$contratto] ?? (string)$contratto;
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['D'], $rTop, (string)$contratto, DataType::TYPE_STRING);
                    $sheet3->setCellValueExplicitByColumnAndRow($cols['D'], $rBot, (string)($d->LivelloMansione ?? ''), DataType::TYPE_STRING);
                }
                for ($r = $start + ($i * 2); $r <= $start + 80; $r++) {
                    foreach ($cols as $cIdx) $sheet3->setCellValueExplicitByColumnAndRow($cIdx, $r, '', DataType::TYPE_STRING);
                }
            }

            /* ====== FOGLIO 4 – ALTRO PERSONALE DIPENDENTE (orizzontale, 1 riga/persona) ====== */
            $sheet4 = $spreadsheet->getSheetByName('ALTRO PERSONALE DIPENDENTE') ?? $spreadsheet->getSheet(3);
            if ($sheet4) {
                $sectionsByQualId = [
                    'PERSONALE DIPENDENTE AMMINISTRATIVO'                        => [7],
                    'PERSONALE DIPENDENTE COORDINATORI TECNICI'                 => [6],
                    'PERSONALE DIPENDENTE ADDETTI PULIZIA E SANIFICAZIONE SEDE' => [3],
                    'PERSONALE DIPENDENTE ADDETTI ALLA LOGISTICA'               => [2],
                    'PERSONALE DIPENDENTE COORDINATORI AMMINISTRATIVI'          => [5],
                ];

                $all  = Dipendente::getByAssociazione($this->idAssociazione, $this->anno)->values();
                $ids  = $all->pluck('idDipendente')->map(fn($v) => (int)$v)->all();
                $qmap = $this->buildQualificheMap($ids);
                $contrattiMap = DB::table('contratti_applicati')->pluck('nome', 'id')->all();

                // bucket per sezione
                $bucket = [];
                foreach ($all as $d) {
                    $qids = $qmap[$d->idDipendente] ?? [];
                    foreach ($sectionsByQualId as $title => $idsOk) {
                        if (count(array_intersect($qids, $idsOk)) > 0) {
                            $bucket[$title] ??= [];
                            $bucket[$title][] = $d;
                        }
                    }
                }

                foreach ($sectionsByQualId as $title => $idsOk) {
                    $people = collect($bucket[$title] ?? [])
                        ->sortBy(fn($d) => [mb_strtoupper($d->DipendenteCognome), mb_strtoupper($d->DipendenteNome)])
                        ->values();
                    if ($people->isEmpty()) continue;

                    $titleRow = $this->findTitleRow($sheet4, $title) ?? 1;
                    $headerRow = $this->findHeaderRowSingle($sheet4, $titleRow + 1, 6);
                    $cols = $this->findHeaderColsSingle($sheet4, $headerRow);
                    $start = $headerRow + 1;

                    $i = 0;
                    foreach ($people as $d) {
                        $row = $start + $i;
                        $i++;

                        $sheet4->setCellValueExplicitByColumnAndRow($cols['COGNOME'], $row, $i . ' ' . mb_strtoupper((string)$d->DipendenteCognome), DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['NOME'],    $row, mb_strtoupper((string)$d->DipendenteNome),          DataType::TYPE_STRING);

                        $contratto = $d->ContrattoApplicato;
                        if (is_numeric($contratto)) $contratto = $contrattiMap[(int)$contratto] ?? (string)$contratto;
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['CONTRATTO'], $row, (string)$contratto, DataType::TYPE_STRING);
                        $sheet4->setCellValueExplicitByColumnAndRow($cols['LIVELLO'],   $row, (string)($d->LivelloMansione ?? ''), DataType::TYPE_STRING);
                    }

                    // pulizia righe residue (20 righe dopo l’ultima)
                    for ($r = $start + $i; $r <= $start + 20; $r++) {
                        foreach ($cols as $cIdx) {
                            $sheet4->setCellValueExplicitByColumnAndRow($cIdx, $r, '', DataType::TYPE_STRING);
                        }
                    }
                }
            }

            // salvataggio
            $filename = sprintf('registri_p1_%d_%d_%s.xlsx', $this->idAssociazione, $this->anno, now()->format('Ymd_His'));
            $destRel = 'documenti/' . $filename;
            $destAbs = Storage::disk('public')->path($destRel);

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            if (method_exists($writer, 'setUseDiskCaching')) $writer->setUseDiskCaching(true, $tmp);
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
            Log::error('REGISTRI P1 XLS: errore in handle', ['docId' => $this->documentoId, 'assoc' => $this->idAssociazione, 'anno' => $this->anno, 'msg' => $e->getMessage()]);
            DB::table('documenti_generati')->where('id', $this->documentoId)->update(['stato' => 'error', 'updated_at' => now()]);
            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void {
        Log::error('GeneraRegistriPrimaPaginaXlsJob failed', [
            'documentoId' => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno' => $this->anno,
            'error' => $e->getMessage(),
        ]);
        DB::table('documenti_generati')->where('id', $this->documentoId)->update(['stato' => 'error', 'updated_at' => now()]);
    }
}
