<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Convenzione;
use App\Models\RipartizioneVolontario;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RipartizioneVolontarioController extends Controller {
    public function index(Request $request) {
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

    public function getData(Request $request) {
        // utente effettivo (gestisce impersonate come in RapportiRicavi)
        $user = session()->has('impersonate')
            ? User::find(session('impersonate'))
            : auth()->user();

        $anno = (int) session('anno_riferimento', now()->year);
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        // persisto selezione se arriva in request
        if ($request->filled('idAssociazione')) {
            session(['associazione_selezionata' => (int) $request->input('idAssociazione')]);
        }

        $idAssociazione = $isElevato
            ? (int) (session('associazione_selezionata') ?: $request->input('idAssociazione'))
            : (int) $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['data' => [], 'labels' => []]);
        }

        // Convenzioni ordinate come in RapportiRicavi
        $convenzioni = \DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get();

        // Etichette per colonne dinamiche
        $labels = [];
        foreach ($convenzioni as $c) {
            $labels['c' . $c->idConvenzione] = $c->Convenzione;
        }

        // Ricavi salvati per associazione/anno
        $ricavi = \DB::table('rapporti_ricavi as rr')
            ->join('associazioni as a', 'rr.idAssociazione', '=', 'a.idAssociazione')
            ->select('rr.idConvenzione', 'rr.Rimborso', 'a.Associazione')
            ->where('rr.idAnno', $anno)
            ->where('rr.idAssociazione', $idAssociazione)
            ->get();

        $totale = (float) $ricavi->sum('Rimborso');

        // RIGA: mantengo le chiavi storiche della vista "volontari"
        $riga = [
            'Associazione' => $ricavi->first()->Associazione
                ?? \DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione'),
            'FullName'  => 'Totale volontari', // label legacy: puoi cambiarla in 'Totale ricavi' se preferisci
            'OreTotali' => $totale,            // qui usiamo il TOTALE RICAVI al posto delle ore totali
        ];

        foreach ($convenzioni as $conv) {
            $key = 'c' . $conv->idConvenzione;
            $val = (float) optional($ricavi->firstWhere('idConvenzione', $conv->idConvenzione))->Rimborso ?? 0;
            $riga["{$key}_ore"]     = $val;                                       // ← ricavo per convenzione
            $riga["{$key}_percent"] = $totale > 0 ? round($val / $totale * 100, 2) : 0.0;
        }

        return response()->json([
            'data'   => [$riga],
            'labels' => $labels,
        ]);
    }

    public function edit() {
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

    public function update(Request $request) {
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
