<?php

namespace App\Http\Controllers;

use App\Models\Automezzo;
use App\Models\Convenzione;
use App\Models\AutomezzoKm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KmPercorsiController extends Controller {
    /**
     * Mostra la vista principale con intestazioni dinamiche.
     */
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $convenzioni = Convenzione::getByAnno($anno, $user);

        return view('km_percorsi.index', compact('anno', 'convenzioni'));
    }

    /**
     * Restituisce i dati per DataTables, pivotati per convenzione.
     */
public function getData()
{
    $user = Auth::user();
    
    $isImpersonating = session()->has('impersonate');
    // var_dump($isImpersonating);die();
    if ($isImpersonating) {
        $user = User::find(session('impersonate'));
    }
    $anno = session('anno_riferimento', now()->year);

    $automezzi = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
        ? Automezzo::getAll($anno)
        : Automezzo::getByAssociazione($user->idAssociazione, $anno);

    $convenzioni = Convenzione::getByAnno($anno, $user)->sortBy('idConvenzione')->values();
    $kmData = AutomezzoKm::getGroupedByAutomezzoAndConvenzione($anno, $user);

    $labels = [];
    foreach ($convenzioni as $c) {
        $labels['c' . $c->idConvenzione] = $c->Convenzione;
    }

    $rows = [];
    foreach ($automezzi as $a) {
        $totKm = collect($kmData)
            ->filter(fn($v, $k) => str_starts_with($k, $a->idAutomezzo . '-'))
            ->flatMap(fn($rows) => $rows)
            ->sum('KMPercorsi');

        $riga = [
            'idAutomezzo' => $a->idAutomezzo,
            'Automezzo' => $a->Automezzo,
            'Targa' => $a->Targa,
            'CodiceIdentificativo' => $a->CodiceIdentificativo ?? '',
            'Totale' => $totKm,
        ];

        foreach ($convenzioni as $c) {
            $key = 'c' . $c->idConvenzione;
            $lookup = $a->idAutomezzo . '-' . $c->idConvenzione;

            $kmPercorsi = 0;
            $percentuale = 0;

            if ($kmData->has($lookup)) {
                $val = $kmData->get($lookup)->first();
                $kmPercorsi = $val->KMPercorsi ?? 0;
                $percentuale = $totKm > 0 ? round(($kmPercorsi / $totKm) * 100, 2) : 0;
            }

            $riga["{$key}_km"] = $kmPercorsi;
            $riga["{$key}_percent"] = $percentuale;
        }

        $rows[] = $riga;
    }

    return response()->json([
        'data' => $rows,
        'labels' => $labels
    ]);
}


    public function edit(int $id) {
        $anno = session('anno_riferimento', now()->year);
        $automezzo = Automezzo::getById($id, $anno);
        $convenzioni = Convenzione::getByAnno($anno, Auth::user())->sortBy('idConvenzione')->values();
        $kmEsistenti = AutomezzoKm::getKmPerConvenzione($automezzo->idAutomezzo, $anno);

        return view('km_percorsi.edit', compact('automezzo', 'convenzioni', 'kmEsistenti'));
    }

    public function create() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $automezzi = Automezzo::getLightForAnno($anno, $user->isSuperAdmin() ? null : $user->idAssociazione);
        $convenzioni = Convenzione::getByAnno($anno, $user)->sortBy('idConvenzione')->values();

        return view('km_percorsi.create', compact('automezzi', 'convenzioni'));
    }


    public function store(Request $request) {
        $request->validate([
            'idAutomezzo' => 'required|integer|exists:automezzi,idAutomezzo',
            'km'          => 'required|array',
        ]);

        $idAutomezzo = $request->input('idAutomezzo');
        $kmArray = $request->input('km');
        $now = now();

        foreach ($kmArray as $idConvenzione => $km) {
            if (is_numeric($km) && $km > 0) {
                AutomezzoKm::upsert((int)$idAutomezzo, (int)$idConvenzione, (float)$km);
            }
        }

        return redirect()
            ->route('km-percorsi.index')
            ->with('success', 'KM percorsi salvati con successo.');
    }

    public function show(int $id) {
        $anno = session('anno_riferimento', now()->year);
        $automezzo = Automezzo::getById($id, $anno);
        $convenzioni = Convenzione::getByAnno($anno, Auth::user())->sortBy('idConvenzione')->values();
        $kmEsistenti = AutomezzoKm::getByAutomezzo($id, $anno)->keyBy('idConvenzione');

        return view('km_percorsi.show', compact('automezzo', 'convenzioni', 'kmEsistenti'));
    }

    public function update(Request $request, int $idAutomezzo) {
        $request->validate([
            'km' => 'required|array',
        ]);

        $kmArray = $request->input('km');
        $now = now();
        $anno = session('anno_riferimento', now()->year);

        // Rimuove i record esistenti per quell'automezzo e anno
        AutomezzoKm::deleteByAutomezzo($idAutomezzo, $anno);

        // Reinserisce quelli aggiornati
        foreach ($kmArray as $idConvenzione => $km) {
            if (is_numeric($km) && $km > 0) {
                AutomezzoKm::upsert((int)$idAutomezzo, (int)$idConvenzione, (float)$km);
            }
        }

        return redirect()
            ->route('km-percorsi.index')
            ->with('success', 'KM percorsi aggiornati con successo.');
    }
}
