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
use App\Models\Dipendente;
use App\Models\Convenzione;
use App\Models\RipartizionePersonale;

class GeneraRipartizionePersonalePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            (new WithoutOverlapping("pdf-rip-personale-{$this->idAssociazione}-{$this->anno}"))
                ->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var DocumentoGenerato $doc */
        $doc = DocumentoGenerato::findOrFail($this->documentoId);

        // Intestazione
        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->first();

        // Convenzioni di anno+associazione (ordine stabile)
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $this->idAssociazione)
            ->where('idAnno', $this->anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get(['idConvenzione','Convenzione']);

        // Dipendenti autisti/barellieri anno+associazione
        $dipendenti = Dipendente::getAutistiEBarellieri($this->anno, $this->idAssociazione);

        // Ore per dipendente-convenzione
        $raw = RipartizionePersonale::getAll($this->anno, auth()->user(), $this->idAssociazione)
            ->groupBy('idDipendente');

        // Costruzione righe
        $rows = [];
        $totOre = 0;
        $totCol = [];
        foreach ($convenzioni as $c) {
            $k = 'c'.$c->idConvenzione;
            $totCol[$k] = 0;
        }

        foreach ($dipendenti as $d) {
            $serv = $raw->get($d->idDipendente, collect());
            $oreTot = (float) $serv->sum('OreServizio');
            $totOre += $oreTot;

            $r = [
                'is_totale'    => 0,
                'idDipendente' => $d->idDipendente,
                'FullName'     => trim(($d->DipendenteCognome ?? '').' '.($d->DipendenteNome ?? '')),
                'OreTotali'    => $oreTot,
            ];

            foreach ($convenzioni as $c) {
                $k   = 'c'.$c->idConvenzione;
                $ore = (float) optional(
                    $serv->firstWhere('idConvenzione', $c->idConvenzione)
                )->OreServizio ?? 0;
                $r[$k.'_ore']     = $ore;
                $r[$k.'_percent'] = $oreTot > 0 ? round($ore / $oreTot * 100, 2) : 0.0;
                $totCol[$k]      += $ore;
            }

            $rows[] = $r;
        }

        // Riga totale
        $tot = [
            'is_totale'    => -1,
            'idDipendente' => null,
            'FullName'     => 'TOTALE',
            'OreTotali'    => $totOre,
        ];
        foreach ($convenzioni as $c) {
            $k = 'c'.$c->idConvenzione;
            $ore = (float) ($totCol[$k] ?? 0);
            $tot[$k.'_ore']     = $ore;
            $tot[$k.'_percent'] = $totOre > 0 ? round($ore / $totOre * 100, 2) : 0.0;
        }
        $rows[] = $tot;

        // Render PDF
        $pdf = Pdf::loadView('template.ripartizione_personale', [
            'anno'         => $this->anno,
            'associazione' => $associazione,
            'convenzioni'  => $convenzioni,
            'rows'         => $rows,
        ])->setPaper('a4', 'landscape');

        $filename = "ripartizione_personale_{$this->idAssociazione}_{$this->anno}_".now()->timestamp.".pdf";
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
        Log::error('GeneraRipartizionePersonalePdfJob failed: '.$e->getMessage(), [
            'documentoId'=>$this->documentoId,
            'assoc'=>$this->idAssociazione,
            'anno'=>$this->anno,
        ]);
    }
}
