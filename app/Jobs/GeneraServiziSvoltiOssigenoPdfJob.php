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
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;
use App\Models\DocumentoGenerato;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class GeneraServiziSvoltiOssigenoPdfJob implements ShouldQueue
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
            (new WithoutOverlapping("pdf-servizi-ossigeno-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var \App\Models\DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        // intestazione
        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)->first();

        // convenzioni anno/associazione
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione',$this->idAssociazione)
            ->where('idAnno',$this->anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get(['idConvenzione','Convenzione']);

        // automezzi + flag incluso_riparto
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione',$this->idAssociazione)
            ->where('idAnno',$this->anno)
            ->orderBy('CodiceIdentificativo')
            ->get(['idAutomezzo','Targa','CodiceIdentificativo','Automezzo','incluso_riparto']);

        // numero servizi per (automezzo, convenzione)
        $servizi = DB::table('automezzi_servizi as s')
            ->join('convenzioni as c','c.idConvenzione','=','s.idConvenzione')
            ->join('automezzi as a','a.idAutomezzo','=','s.idAutomezzo')
            ->where('a.idAssociazione',$this->idAssociazione)
            ->where('a.idAnno',$this->anno)
            ->where('c.idAnno',$this->anno)
            ->select('s.idAutomezzo','s.idConvenzione', DB::raw('SUM(s.NumeroServizi) as NumeroServizi'))
            ->groupBy('s.idAutomezzo','s.idConvenzione')
            ->get()
            ->groupBy(fn($r)=>$r->idAutomezzo.'-'.$r->idConvenzione);

        // righe + totali
        $rows = [];
        $totali = [
            'is_totale'            => -1,
            'Targa'                => '',
            'CodiceIdentificativo' => '',
            'Automezzo'            => 'TOTALE',
            'RipartoOssigeno'      => '', // colonna "sì/no"
            'Totale'               => 0,
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
                $n = $servizi->has($key) ? (int) ($servizi->get($key)->first()->NumeroServizi ?? 0) : 0;
                $tot += $n;
            }

            $r = [
                'is_totale'            => 0,
                'idAutomezzo'          => $a->idAutomezzo,
                'Targa'                => (string)($a->Targa ?? ''),
                'CodiceIdentificativo' => (string)($a->CodiceIdentificativo ?? ''),
                'Automezzo'            => (string)($a->Automezzo ?? ''),
                'RipartoOssigeno'      => (bool)$a->incluso_riparto ? 'sì' : 'no',
                'Totale'               => $tot,
            ];

            foreach ($convenzioni as $c) {
                $k   = 'c'.$c->idConvenzione;
                $key = $a->idAutomezzo.'-'.$c->idConvenzione;
                $n   = $servizi->has($key) ? (int) ($servizi->get($key)->first()->NumeroServizi ?? 0) : 0;

                $r[$k.'_n']       = $n;
                $r[$k.'_percent'] = $tot>0 ? round($n/$tot*100,2) : 0;
                $totali[$k.'_n'] += $n;
            }

            $totali['Totale'] += $tot;
            $rows[] = $r;
        }

        // percentuali totali per convenzione
        $acc = 0; $last = count($convenzioni)-1;
        foreach ($convenzioni as $i=>$c) {
            $k = 'c'.$c->idConvenzione;
            if ($i < $last) {
                $p = $totali['Totale']>0 ? round($totali[$k.'_n']/$totali['Totale']*100,2) : 0;
                $totali[$k.'_percent'] = $p; $acc += $p;
            } else {
                $totali[$k.'_percent'] = max(0, round(100 - $acc, 2));
            }
        }
        $rows[] = $totali;

        // render
        $pdf = Pdf::loadView('template.servizi_svolti_ossigeno', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'rows'         => $rows,
        ])->setPaper('a4','landscape');

        $filename = "servizi_svolti_ossigeno_{$this->idAssociazione}_{$this->anno}_".now()->timestamp.".pdf";
        $path = "documenti/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        $doc->update([
            'nome_file'     => $filename,
            'percorso_file' => $path,
            'generato_il'   => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraServiziSvoltiOssigenoPdfJob failed: '.$e->getMessage(), [
            'documentoId'=>$this->documentoId,'assoc'=>$this->idAssociazione,'anno'=>$this->anno
        ]);
    }
}
