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
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DocumentoGenerato;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class GeneraRipVolontariScnPdfJob implements ShouldQueue
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
            (new WithoutOverlapping("pdf-rip-vol-scn-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var \App\Models\DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        // intestazioni
        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)->first();

        // convenzioni anno + associazione (ordine stabile)
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get(['idConvenzione','Convenzione']);

        // === aggregati VOLONTARI (idDipendente = 999999) ===
        $aggVol = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c','c.idConvenzione','=','ds.idConvenzione')
            ->where('ds.idDipendente', 999999)
            ->where('c.idAssociazione', $this->idAssociazione)
            ->where('c.idAnno', $this->anno)
            ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as OreServizio'))
            ->groupBy('ds.idConvenzione')
            ->pluck('OreServizio', 'idConvenzione');

        $totVol = (float) array_sum($aggVol->all());

        $rowVol = [
            'label'     => "ORE TOTALI DI SERVIZIO VOLONTARIO",
            'OreTotali' => $totVol,
        ];
        foreach ($convenzioni as $c) {
            $k   = 'c'.$c->idConvenzione;
            $ore = (float) ($aggVol[$c->idConvenzione] ?? 0);
            $rowVol[$k.'_ore']     = $ore;
            $rowVol[$k.'_percent'] = $totVol > 0 ? round($ore / $totVol * 100, 2) : 0.0;
        }

        // === aggregati SERVIZIO CIVILE (idDipendente = 999998) ===
        $aggScn = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c','c.idConvenzione','=','ds.idConvenzione')
            ->where('ds.idDipendente', 999998)
            ->where('c.idAssociazione', $this->idAssociazione)
            ->where('c.idAnno', $this->anno)
            ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as OreServizio'))
            ->groupBy('ds.idConvenzione')
            ->pluck('OreServizio', 'idConvenzione');

        $totScn = (float) array_sum($aggScn->all());

        $rowScn = [
            'label'     => "UNITÀ TOTALI DI SERVIZIO CIVILE NAZIONALE",
            'OreTotali' => $totScn,
        ];
        foreach ($convenzioni as $c) {
            $k   = 'c'.$c->idConvenzione;
            $ore = (float) ($aggScn[$c->idConvenzione] ?? 0);
            $rowScn[$k.'_ore']     = $ore;
            $rowScn[$k.'_percent'] = $totScn > 0 ? round($ore / $totScn * 100, 2) : 0.0;
        }

        // render
        $pdf = Pdf::loadView('template.rip_volontari_scn', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'volontari'    => $rowVol,
            'scn'          => $rowScn,
        ])->setPaper('a4','landscape');

        $filename = "ripartizione_volontari_scn_{$this->idAssociazione}_{$this->anno}_".now()->timestamp.".pdf";
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
        Log::error('GeneraRipVolontariScnPdfJob failed: '.$e->getMessage(), [
            'documentoId'=>$this->documentoId,
            'assoc'=>$this->idAssociazione,
            'anno'=>$this->anno,
        ]);
    }
}
