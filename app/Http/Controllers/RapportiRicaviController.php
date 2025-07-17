<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;
use App\Models\RapportoRicavo;
use App\Models\Associazione;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RapportiRicaviController extends Controller {
    public function index() {
        return view('rapporti_ricavi.index');
    }

    /** per DataTables */
   public function getData()
{
    $user = session()->has('impersonate')
        ? User::find(session('impersonate'))
        : auth()->user();

    $anno = session('anno_riferimento', now()->year);

    // Le convenzioni da mostrare
    $convenzioni = Convenzione::getByAnno($anno, $user)
        ->sortBy('idConvenzione')
        ->values();

    // Tutti i ricavi, raggruppati per associazione
    $ricaviRaw = RapportoRicavo::getAllByAnno($anno, $user)
        ->groupBy('idAssociazione');

    // Preparo le label per DataTables
    $labels = $convenzioni
        ->pluck('Convenzione','idConvenzione')
        ->mapWithKeys(fn($name,$id) => ['c'.$id => $name])
        ->toArray();

    $rows = [];
    foreach ($ricaviRaw as $idAss => $collezione) {
        $totAssoc = $collezione->sum('Rimborso');
        $riga = [
            'idAssociazione'   => $idAss,
            'Associazione'     => $collezione->first()->Associazione,
            'TotaleEsercizio'  => $totAssoc,
        ];

        // per ogni convenzione metto rimborso e percentuale
        foreach ($convenzioni as $conv) {
            $k = 'c'.$conv->idConvenzione;
            $val = $collezione
                ->firstWhere('idConvenzione',$conv->idConvenzione)
                ->Rimborso ?? 0;
            $riga["{$k}_rimborso"] = $val;
            $riga["{$k}_percent"]  = $totAssoc>0
                ? round($val/$totAssoc*100,2)
                : 0;
        }

        $rows[] = $riga;
    }

    return response()->json([
        'data'   => $rows,
        'labels' => $labels,
    ]);
}

    public function create() {
        $user = Auth::user();
        // se super/admin prendo tutte le associazioni
        if ($user->hasAnyRole(['SuperAdmin', 'Admin'])) {
            $associazioni = \DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();
        } else {
            $associazioni = collect([
                (object)[
                    'idAssociazione' => $user->IdAssociazione,
                    'Associazione'   => $user->associazione->Associazione,
                ]
            ]);
        }
        $anno = session('anno_riferimento', now()->year);
        $convenzioni = Convenzione::getByAnno($anno, $user)->sortBy('idConvenzione')->values();

        return view('rapporti_ricavi.create', compact('associazioni', 'convenzioni', 'anno'));
    }

    public function store(Request $request) {
        $user = Auth::user();
        $idAss = (int)$request->input('idAssociazione', $user->IdAssociazione);
        $anno  = session('anno_riferimento');

        foreach ($request->input('ricavi', []) as $idConv => $rimborso) {
            if (!is_numeric($idConv) || is_null($rimborso)) continue;
            RapportoRicavo::upsert(
                (int)$idConv,
                $idAss,
                $anno,
                (float)$rimborso
            );
        }
        return redirect()->route('rapporti-ricavi.index')
            ->with('success', 'Ricavi salvati correttamente.');
    }

    public function edit(int $idAssociazione) {
        $user = session()->has('impersonate')
            ? User::find(session('impersonate'))
            : auth()->user();

        if (
            !$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            && $user->IdAssociazione !== $idAssociazione
        ) {
            abort(403);
        }

        $anno = session('anno_riferimento', now()->year);
        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')
            ->values();

        // **qui** invertiamo i parametri
        $raw = RapportoRicavo::getByAssociazione($anno, $idAssociazione);
        $valori = $raw->keyBy('idConvenzione');

        $associazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? 'Tutte'
            : $user->associazione->Associazione;

        return view('rapporti_ricavi.edit', compact(
            'convenzioni',
            'valori',
            'idAssociazione',
            'associazione',
            'anno'
        ));
    }


    public function update(Request $request, int $idAssociazione) {
        $anno = session('anno_riferimento');
        RapportoRicavo::deleteByAssociazione($idAssociazione, $anno);

        foreach ($request->input('ricavi', []) as $idConv => $rimborso) {
            if (!is_numeric($idConv)) continue;
            RapportoRicavo::upsert(
                (int)$idConv,
                $idAssociazione,
                $anno,
                (float)$rimborso
            );
        }
        return redirect()->route('rapporti-ricavi.index')
            ->with('success', 'Ricavi aggiornati.');
    }

    public function show(int $idAssociazione) {
        // qui potresti semplicemente redirigere a edit, oppure:
        return $this->edit($idAssociazione);
    }

    public function destroy(int $idAssociazione) {
        $anno = session('anno_riferimento');
        RapportoRicavo::deleteByAssociazione($idAssociazione, $anno);
        return redirect()->route('rapporti-ricavi.index')
            ->with('success', 'Dati eliminati.');
    }
}
