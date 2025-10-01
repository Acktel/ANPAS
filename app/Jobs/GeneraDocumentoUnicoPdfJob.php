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
use App\Models\DocumentoGenerato;
use App\Models\Automezzo;
use App\Models\RapportoRicavo;
use Throwable;
use Illuminate\Support\Facades\Log;

class GeneraDocumentoUnicoPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $documentoId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('pdf');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("pdf-doc-unico-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        // intestazione
        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)->first();

        // convenzioni (ordine stabile)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione','Convenzione')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get();

        // ===== 1) RAPPORTI RICAVI =====
        $ricavi = RapportoRicavo::getWithConvenzioni($this->anno, $this->idAssociazione);
        $totRicavi = (float) $ricavi->sum('Rimborso');
        $ricaviRow = ['TotaleEsercizio' => $totRicavi];
        foreach ($convenzioni as $c) {
            $k = 'c'.$c->idConvenzione;
            $val = (float) optional($ricavi->firstWhere('idConvenzione',$c->idConvenzione))->Rimborso ?? 0.0;
            $ricaviRow["{$k}_rimborso"] = $val;
            $ricaviRow["{$k}_percent"]  = $totRicavi>0 ? round($val/$totRicavi*100,2) : 0.0;
        }

        // ===== 2) DISTINTA KM PERCORSI =====
        $automezzi = Automezzo::getByAssociazione($this->idAssociazione, $this->anno)
            ->sortBy('CodiceIdentificativo')->values();

        $kmGrouped = DB::table('automezzi_km as k')
            ->join('convenzioni as c','c.idConvenzione','=','k.idConvenzione')
            ->join('automezzi as a','a.idAutomezzo','=','k.idAutomezzo')
            ->where('a.idAssociazione',$this->idAssociazione)
            ->where('a.idAnno',$this->anno)
            ->where('c.idAnno',$this->anno)
            ->select('k.idAutomezzo','k.idConvenzione','k.KMPercorsi')
            ->get()->groupBy(fn($r)=>$r->idAutomezzo.'-'.$r->idConvenzione);

        $kmRows = [];
        $kmTot = [
           'Targa'=>'TOTALE', 'CodiceIdentificativo'=>'',
            'Totale'=>0.0, 'is_totale'=>-1,
        ];
        foreach ($convenzioni as $c){ $k='c'.$c->idConvenzione; $kmTot[$k.'_km']=0.0; $kmTot[$k.'_percent']=0.0; }

        foreach ($automezzi as $a) {
            $totKm = 0.0;
            foreach ($convenzioni as $c) {
                $key = $a->idAutomezzo.'-'.$c->idConvenzione;
                $km  = $kmGrouped->has($key) ? (float)$kmGrouped->get($key)->first()->KMPercorsi : 0.0;
                $totKm += $km;
            }
            $r = [
                'Targa' => (string)($a->Targa ?? ''),
                'CodiceIdentificativo' => (string)($a->CodiceIdentificativo ?? ''),
                'Totale' => $totKm, 'is_totale'=>0,
            ];
            foreach ($convenzioni as $c){
                $k='c'.$c->idConvenzione; $key=$a->idAutomezzo.'-'.$c->idConvenzione;
                $km = $kmGrouped->has($key)? (float)$kmGrouped->get($key)->first()->KMPercorsi : 0.0;
                $r[$k.'_km']=$km; $r[$k.'_percent']=$totKm>0? round($km/$totKm*100,2):0.0;
                $kmTot[$k.'_km'] += $km;
            }
            $kmTot['Totale'] += $totKm;
            $kmRows[] = $r;
        }
        // chiusura % per totale
        $acc=0.0; $last=count($convenzioni)-1;
        foreach ($convenzioni as $i=>$c){
            $k='c'.$c->idConvenzione;
            if ($i<$last){ $p = $kmTot['Totale']>0? round($kmTot[$k.'_km']/$kmTot['Totale']*100,2):0.0; $kmTot[$k.'_percent']=$p; $acc+=$p; }
            else { $kmTot[$k.'_percent']=max(0, round(100-$acc,2)); }
        }
        $kmRows[] = $kmTot;

        // ===== 3) SERVIZI SVOLTI =====
        $serviziGrouped = DB::table('automezzi_servizi as s')
            ->join('automezzi as a','s.idAutomezzo','=','a.idAutomezzo')
            ->where('a.idAssociazione',$this->idAssociazione)
            ->where('a.idAnno',$this->anno)
            ->select('s.idAutomezzo','s.idConvenzione', DB::raw('SUM(s.NumeroServizi) as NumeroServizi'))
            ->groupBy('s.idAutomezzo','s.idConvenzione')
            ->get()->groupBy(fn($r)=>$r->idAutomezzo.'-'.$r->idConvenzione);

        $servRows=[]; 
        $servTot=['Targa'=>'TOTALE','CodiceIdentificativo'=>'','Totale'=>0,'is_totale'=>-1];
        foreach ($convenzioni as $c){ $k='c'.$c->idConvenzione; $servTot[$k.'_n']=0; $servTot[$k.'_percent']=0.0; }

        foreach ($automezzi as $a){
            $r=['Targa'=>$a->Targa??'','CodiceIdentificativo'=>$a->CodiceIdentificativo??'','Totale'=>0,'is_totale'=>0];
            foreach ($convenzioni as $c){
                $k='c'.$c->idConvenzione; $key=$a->idAutomezzo.'-'.$c->idConvenzione;
                $n = $serviziGrouped->has($key)? (int)$serviziGrouped->get($key)->first()->NumeroServizi : 0;
                $r[$k.'_n']=$n; $r['Totale'] += $n; $servTot[$k.'_n'] += $n;
            }
            foreach ($convenzioni as $c){
                $k='c'.$c->idConvenzione; $r[$k.'_percent']= $r['Totale']>0? round($r[$k.'_n']/$r['Totale']*100,2):0.0;
            }
            $servTot['Totale'] += $r['Totale'];
            $servRows[]=$r;
        }
        $acc=0.0; $last=count($convenzioni)-1;
        foreach ($convenzioni as $i=>$c){
            $k='c'.$c->idConvenzione;
            if ($i<$last){ $p=$servTot['Totale']>0? round($servTot[$k.'_n']/$servTot['Totale']*100,2):0.0; $servTot[$k.'_percent']=$p; $acc+=$p; }
            else { $servTot[$k.'_percent']=max(0, round(100-$acc,2)); }
        }
        $servRows[]=$servTot;

        // ===== 4) REGISTRO AUTOMEZZI (tabella semplice come nel tuo template) =====
        $registro = $automezzi; // già pronto

        // ===== render unico PDF =====
        $pdf = Pdf::loadView('template.documento_unico', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,

            'ricaviRow'    => $ricaviRow,

            'kmRows'       => $kmRows,

            'servRows'     => $servRows,

            'registro'     => $registro,
        ])->setPaper('a4','landscape');

        $filename = "documento_unico_{$this->idAssociazione}_{$this->anno}_" . now()->timestamp . ".pdf";
        $path     = "documenti/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        $doc->update([
            'nome_file'     => $filename,
            'percorso_file' => $path,
            'generato_il'   => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneraDocumentoUnicoPdfJob failed: '.$e->getMessage(), [
            'documentoId'=>$this->documentoId,'assoc'=>$this->idAssociazione,'anno'=>$this->anno
        ]);
    }
}
