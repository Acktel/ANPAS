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
    public function index(Request $request)
    {
        $anno = session('anno_riferimento', now()->year);
        $sessionKey = 'associazione_selezionata';

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        // Se arriva un parametro dalla query, salvo in sessione
        if ($request->has('idAssociazione')) {
            session([$sessionKey => $request->get('idAssociazione')]);
        }

        // Associazione selezionata: prima sessione, poi fallback a user
        $selectedAssoc = session($sessionKey, Auth::user()->IdAssociazione ?? null);

        return view('ripartizioni.servizio_civile.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $sessionKey = 'associazione_selezionata';

        // Se arriva un parametro dalla query, salvo in sessione
        if ($request->has('idAssociazione')) {
            session([$sessionKey => $request->query('idAssociazione')]);
        }

        // Recupero da sessione (o fallback a user)
        $selectedAssoc = session($sessionKey, $user->IdAssociazione);

        // tutte le convenzioni attive per l'anno
        $convenzioni = Convenzione::getByAssociazione($selectedAssoc, $anno)
            ->sortBy('idConvenzione')->values();

        // dati aggregati filtrati per l'associazione selezionata
        $raw = RipartizioneServizioCivile::getAggregato($anno, $user, $selectedAssoc)
            ->keyBy('idConvenzione');

        $totOre = $raw->sum('OreServizio');

        $nomeAssociazione = DB::table('associazioni')
            ->where('idAssociazione', $selectedAssoc)
            ->value('Associazione');

        $row = [
            'Associazione' => $nomeAssociazione ?? '',
            'FullName'     => 'Totale servizio civile',
            'OreTotali'    => $totOre,
            'is_totale'    => -1,
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

        $record = RipartizioneServizioCivile::getAggregato($anno, $user, $selectedAssoc)
            ->keyBy('idConvenzione');

        return view('ripartizioni.servizio_civile.edit', compact('convenzioni', 'record', 'anno'));
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
            ->with('success', 'Rimborsi servizio civile aggiornati.');
    }
}
