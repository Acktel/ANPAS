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
use Illuminate\Bus\Batchable;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DocumentoGenerato;

class GeneraServiziSvoltiPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ){
        $this->onQueue('pdf');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("pdf-servizi-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);
        Log::debug('SERVIZI SVOLTI: start', [
            'documentoId'    => $this->documentoId,
            'idAssociazione' => $this->idAssociazione,
            'anno'           => $this->anno,
            'doc'            => $doc
        ]);
        
        // intestazione
        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->first();

        // convenzioni dell’associazione/anno
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get(['idConvenzione','Convenzione']);

        // automezzi (usa alias per il campo Automezzo)
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('CodiceIdentificativo')
            ->select([
                'idAutomezzo',
                DB::raw('TRIM(Targa)                as Targa'),
                DB::raw('TRIM(CodiceIdentificativo) as CodiceIdentificativo'),
                DB::raw('Automezzo                  as NomeAutomezzo'),
            ])->get();

        // servizi per (automezzo, convenzione)
        $serviziGrouped = DB::table('automezzi_servizi as s')
            ->join('automezzi as a','a.idAutomezzo','=','s.idAutomezzo')
            ->join('convenzioni as c','c.idConvenzione','=','s.idConvenzione')
            ->where('a.idAssociazione',$this->idAssociazione)
            ->where('a.idAnno',$this->anno)
            ->where('c.idAnno',$this->anno)
            ->select('s.idAutomezzo','s.idConvenzione', DB::raw('SUM(s.NumeroServizi) as NumeroServizi'))
            ->groupBy('s.idAutomezzo','s.idConvenzione')
            ->get()
            ->groupBy(fn($r) => $r->idAutomezzo.'-'.$r->idConvenzione);

        // righe + totali
        $rows = [];
        $totali = [
            'idAutomezzo'          => null,
            'Targa'                => '',
            'CodiceIdentificativo' => '',
            'Automezzo'            => 'TOTALE',
            'Totale'               => 0,
            'is_totale'            => -1,
        ];
        foreach ($convenzioni as $c) {
            $k = 'c'.$c->idConvenzione;
            $totali[$k.'_n'] = 0;
            $totali[$k.'_percent'] = 0;
        }

        foreach ($automezzi as $a) {
            $tot = 0;
            foreach ($convenzioni as $c) {
                $key = $a->idAutomezzo.'-'.$c->idConvenzione;
                $n   = $serviziGrouped->has($key)
                    ? (int) ($serviziGrouped->get($key)->first()->NumeroServizi ?? 0)
                    : 0;
                $tot += $n;
            }

            $riga = [
                'idAutomezzo'          => (int)$a->idAutomezzo,
                'Targa'                => (string)($a->Targa ?? ''),
                'CodiceIdentificativo' => (string)($a->CodiceIdentificativo ?? ''),
                'Automezzo'            => (string)($a->NomeAutomezzo ?? ''),
                'Totale'               => $tot,
                'is_totale'            => 0,
            ];

            foreach ($convenzioni as $c) {
                $k   = 'c'.$c->idConvenzione;
                $key = $a->idAutomezzo.'-'.$c->idConvenzione;
                $n   = $serviziGrouped->has($key)
                    ? (int) $serviziGrouped->get($key)->first()->NumeroServizi
                    : 0;

                $riga[$k.'_n']      = $n;
                $riga[$k.'_percent'] = $tot > 0 ? round(($n / $tot) * 100, 2) : 0.0;

                $totali[$k.'_n'] += $n;
            }

            $totali['Totale'] += $riga['Totale'];
            $rows[] = $riga;
        }

        // percentuali riga totale (chiudi a 100)
        $acc = 0; $last = count($convenzioni) - 1;
        foreach ($convenzioni as $i => $c) {
            $k = 'c'.$c->idConvenzione;
            if ($i < $last) {
                $p = $totali['Totale'] > 0 ? round(($totali[$k.'_n'] / $totali['Totale']) * 100, 2) : 0.0;
                $totali[$k.'_percent'] = $p;
                $acc += $p;
            } else {
                $totali[$k.'_percent'] = max(0, round(100 - $acc, 2));
            }
        }
        $rows[] = $totali;

        // render + salva
        $pdf = Pdf::loadView('template.distinta_servizi_svolti', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'rows'         => $rows,
        ])->setPaper('a4', 'landscape');

        $filename = "servizi_svolti_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
        $path     = "documenti/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());
            Log::debug('SERVIZI SVOLTI: salvataggio PDF'. $path );

        $doc->update([
            'nome_file'     => $filename,
            'percorso_file' => $path,
            'generato_il'   => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraServiziSvoltiPdfJob failed: '.$e->getMessage(), [
            'documentoId'=>$this->documentoId, 'assoc'=>$this->idAssociazione, 'anno'=>$this->anno
        ]);
    }
}
