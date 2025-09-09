<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Convenzione;
use App\Models\RipartizioneVolontario;
use Illuminate\Support\Facades\DB;

class RipartizioneVolontarioController extends Controller
{
    public function index(Request $request)
    {
        $anno = session('anno_riferimento', now()->year);

        $sessionKey = 'associazione_selezionata';

        // Recupera tutte le associazioni
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        // Se l’utente ha scelto un’associazione, la salvo in sessione
        if ($request->has('idAssociazione')) {
            session([$sessionKey => $request->get('idAssociazione')]);
        }

        // Recupero l’associazione selezionata da sessione o da user
        $selectedAssoc = session($sessionKey, Auth::user()->IdAssociazione ?? null);

        return view('ripartizioni.volontari.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $sessionKey = 'associazione_selezionata';

        // Aggiorno sessione se arriva un parametro
        if ($request->has('idAssociazione')) {
            session([$sessionKey => $request->query('idAssociazione')]);
        }

        $selectedAssoc = session($sessionKey, $user->IdAssociazione);

        // tutte le convenzioni attive
        $convenzioni = Convenzione::getByAssociazione($selectedAssoc, $anno)
            ->sortBy('idConvenzione')->values();

        // dd($convenzioni);

        // aggregato solo per qualificati idQualifica=15
        $raw = RipartizioneVolontario::getAggregato($anno, $user, $selectedAssoc)
            ->keyBy('idConvenzione');

        $totOre = $raw->sum('OreServizio');

        $nomeAssociazione = DB::table('associazioni')
            ->where('idAssociazione', $selectedAssoc)
            ->value('Associazione');

        $row = [
            'Associazione' => $nomeAssociazione ?? '',
            'FullName'     => 'Totale volontari',
            'OreTotali'    => $totOre,
        ];

        $labels = [];
        if (sizeof($convenzioni) == 0) {
            return response()->json([
                'data'   => [],
                'labels' => [],
            ]);
        }

        foreach ($convenzioni as $c) {
            $k = 'c' . $c->idConvenzione;
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
        $sessionKey = 'associazione_selezionata';

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->whereNot("idAssociazione", 1)
            ->orderBy('Associazione')
            ->get();

        $selectedAssoc = session($sessionKey, Auth::user()->IdAssociazione ?? null);

        $convenzioni = Convenzione::getByAssociazione($selectedAssoc, $anno)
            ->sortBy('idConvenzione')->values();

        $record = RipartizioneVolontario::getAggregato($anno, $user, $selectedAssoc)
            ->keyBy('idConvenzione');

        return view('ripartizioni.volontari.edit', compact('convenzioni', 'record', 'anno'));
    }

    public function update(Request $request)
    {
        foreach ($request->input('ore', []) as $idConv => $ore) {
            RipartizioneVolontario::upsert(
                null,                // idDipendente null = totale volontari
                (int)$idConv,
                (float)$ore
            );
        }

        return redirect()
            ->route('ripartizioni.volontari.index')
            ->with('success', 'Rimborsi volontari aggiornati.');
    }
}
