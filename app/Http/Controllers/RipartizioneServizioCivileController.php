<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Convenzione;
use App\Models\RipartizioneServizioCivile;
use Illuminate\Support\Facades\DB;

class RipartizioneServizioCivileController extends Controller
{
    public const ID_DIPENDENTE_VOLONTARI = 999998;
    public function index()
    {
        $anno = session('anno_riferimento', now()->year);
        
        return view('ripartizioni.servizio_civile.index', compact('anno'));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')->values();

        $raw = RipartizioneServizioCivile::getAggregato($anno, $user)
            ->keyBy('idConvenzione');

        $totOre = $raw->sum('OreServizio');

        $nomeAssociazione = DB::table('associazioni')
            ->where('idAssociazione', $user->IdAssociazione)
            ->value('Associazione');

        $row = [
            'Associazione' => $nomeAssociazione ?? '',
            'FullName'     => 'Totale servizio civile',
            'OreTotali'    => $totOre,
        ];

        $labels = [];
        foreach ($convenzioni as $c) {
            $k = 'c'.$c->idConvenzione;
            $ore = $raw[$c->idConvenzione]->OreServizio ?? 0;
            $perc = $totOre > 0 ? round($ore / $totOre * 100, 2) : 0;
            $row["{$k}_ore"]     = $ore;
            $row["{$k}_percent"] = $perc;
            $labels[$k] = $c->Convenzione;
        }

        return response()->json([
            'data'   => [$row],
            'labels' => $labels,
        ]);
    }

    public function edit()
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')->values();

        $record = RipartizioneServizioCivile::getAggregato($anno, $user)
            ->keyBy('idConvenzione');

        return view('ripartizioni.servizio_civile.edit', compact('convenzioni','record','anno'));
    }

    public function update(Request $request)
    {
        foreach ($request->input('ore', []) as $idConv => $ore) {
            RipartizioneServizioCivile::upsert(
                RipartizioneServizioCivile::ID_SERVIZIO_CIVILE,
                (int)$idConv,
                (float)$ore
            );
        }

        return redirect()
            ->route('ripartizioni.servizio_civile.index')
            ->with('success','Rimborsi servizio civile aggiornati.');
    }
}
