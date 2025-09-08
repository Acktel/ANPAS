<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DocumentoGenerato;
use App\Models\Automezzo;

class GeneraDistintaKmPercorsiPdfJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        // usa la coda dedicata senza ridefinire la property $queue
        $this->onQueue('pdf');
    }

    public function middleware(): array {
        return [
            (new WithoutOverlapping("pdf-distinta-km-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)
                ->dontRelease(),
        ];
    }

    public function handle(): void {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->first();

        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get(['idConvenzione', 'Convenzione']);

        // ✅ PRENDI GLI AUTOMEZZI CON ALIAS CHIARI (niente Model qui)
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('CodiceIdentificativo')
            ->select([
                'idAutomezzo',
                DB::raw('TRIM(Targa)                   as Targa'),
                DB::raw('TRIM(CodiceIdentificativo)    as CodiceIdentificativo'),
                DB::raw('Automezzo                     as NomeAutomezzo'),
            ])
            ->get();

        // (facoltativo) un log per vedere il primo record
        Log::info('DistintaKM first automezzo', (array) ($automezzi->first() ?? []));

        $kmGrouped = DB::table('automezzi_km as k')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 'k.idAutomezzo')
            ->where('a.idAssociazione', $this->idAssociazione)
            ->where('a.idAnno', $this->anno)
            ->where('c.idAnno', $this->anno)
            ->select('k.idAutomezzo', 'k.idConvenzione', 'k.KMPercorsi')
            ->get()
            ->groupBy(fn($r) => $r->idAutomezzo . '-' . $r->idConvenzione);

        $rows = [];

        // riga TOTALE
        $totali = [
            'idAutomezzo'          => null,
            'Targa'                => '',
            'CodiceIdentificativo' => '',
            'Automezzo'            => 'TOTALE',
            'Totale'               => 0.0,
            'is_totale'            => -1,
        ];
        foreach ($convenzioni as $c) {
            $k = 'c' . $c->idConvenzione;
            $totali[$k . '_km']      = 0.0;
            $totali[$k . '_percent'] = 0.0;
        }

        foreach ($automezzi as $a) {
            // totale km per automezzo
            $totKm = 0.0;
            foreach ($convenzioni as $c) {
                $key = $a->idAutomezzo . '-' . $c->idConvenzione;
                $km  = $kmGrouped->has($key) ? (float) ($kmGrouped->get($key)->first()->KMPercorsi ?? 0) : 0.0;
                $totKm += $km;
            }

            // ✅ usa i campi ALIAS per popolare la riga
            $riga = [
                'idAutomezzo'          => (int) $a->idAutomezzo,
                'Targa'                => (string) ($a->Targa ?? ''),
                'CodiceIdentificativo' => (string) ($a->CodiceIdentificativo ?? ''),
                'Automezzo'            => (string) ($a->NomeAutomezzo ?? ''), // <- alias usato qui
                'Totale'               => $totKm,
                'is_totale'            => 0,
            ];

            foreach ($convenzioni as $c) {
                $k   = 'c' . $c->idConvenzione;
                $key = $a->idAutomezzo . '-' . $c->idConvenzione;

                $km = $kmGrouped->has($key) ? (float) $kmGrouped->get($key)->first()->KMPercorsi : 0.0;

                $riga[$k . '_km']      = $km;
                $riga[$k . '_percent'] = $totKm > 0 ? round(($km / $totKm) * 100, 2) : 0.0;

                $totali[$k . '_km'] += $km;
            }

            $totali['Totale'] += $totKm;
            $rows[] = $riga;
        }

        // chiusura percentuali a 100%
        $acc = 0.0;
        $last = count($convenzioni) - 1;
        foreach ($convenzioni as $i => $c) {
            $k = 'c' . $c->idConvenzione;
            if ($i < $last) {
                $p = $totali['Totale'] > 0 ? round(($totali[$k . '_km'] / $totali['Totale']) * 100, 2) : 0.0;
                $totali[$k . '_percent'] = $p;
                $acc += $p;
            } else {
                $totali[$k . '_percent'] = max(0, round(100 - $acc, 2));
            }
        }
        $rows[] = $totali;

        $pdf = Pdf::loadView('template.distinta_km_percorsi', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'rows'         => $rows,
        ])->setPaper('a4', 'landscape');

        $filename = "distinta_km_percorsi_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
        $path     = "documenti/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        $doc->update([
            'nome_file'     => $filename,
            'percorso_file' => $path,
            'generato_il'   => now(),
        ]);
    }

    public function failed(Throwable $e): void {
        Log::error('GeneraDistintaKmPercorsiPdfJob failed: ' . $e->getMessage(), [
            'documentoId' => $this->documentoId,
            'assoc'       => $this->idAssociazione,
            'anno'        => $this->anno,
        ]);
    }
}
