<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Convenzione;
use App\Models\RipartizioneVolontario;
use Illuminate\Support\Facades\DB;

class RipartizioneVolontarioController extends Controller
{
    public function index()
    {
        $anno = session('anno_riferimento', now()->year);
        return view('ripartizioni.volontari.index', compact('anno'));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        // tutte le convenzioni attive
        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')->values();

        // aggregato solo per qualificati idQualifica=15
        $raw = RipartizioneVolontario::getAggregato($anno, $user)
            ->keyBy('idConvenzione');

        $totOre = $raw->sum('OreServizio');

        $nomeAssociazione = DB::table('associazioni')
            ->where('idAssociazione', $user->idAssociazione)
            ->value('Associazione');


        $row = [
            'Associazione' => $nomeAssociazione ?? '',
            'FullName'     => 'Totale volontari',
            'OreTotali'    => $totOre,
        ];

        $labels = [];
        foreach ($convenzioni as $c) {
            $k = 'c'.$c->idConvenzione;
            $ore = $raw[$c->idConvenzione]->OreServizio ?? 0;
            $perc = $totOre>0 ? round($ore/$totOre*100,2) : 0;
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

        $record = RipartizioneVolontario::getAggregato($anno, $user)
            ->keyBy('idConvenzione');

        return view('ripartizioni.volontari.edit', compact('convenzioni','record','anno'));
    }

    public function update(Request $request)
    {
        foreach ($request->input('ore', []) as $idConv=>$ore) {
            RipartizioneVolontario::upsert(
                null,                // idDipendente null = totale volontari
                (int)$idConv,
                (float)$ore
            );
        }

        return redirect()
            ->route('ripartizioni.volontari.index')
            ->with('success','Rimborsi volontari aggiornati.');
    }
}
