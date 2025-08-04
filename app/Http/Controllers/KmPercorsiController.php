<?php

namespace App\Http\Controllers;

use App\Models\Automezzo;
use App\Models\Convenzione;
use App\Models\AutomezzoKm;
use App\Models\Dipendente;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KmPercorsiController extends Controller {
    /**
     * Mostra la vista principale con intestazioni dinamiche.
     */
    public function index(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $selectedAssoc = session('associazione_selezionata', $user->IdAssociazione);
        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

        $convenzioni = Convenzione::getByAnno($anno, $user, $selectedAssoc);

        return view('km_percorsi.index', compact('anno', 'convenzioni', 'associazioni', 'selectedAssoc'));
    }

    /**
     * Restituisce i dati per DataTables, pivotati per convenzione.
     */
    public function getData(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        // Recupera l'associazione da query o sessione
        $idAssociazione = $request->query('idAssociazione')
            ?? session('associazione_selezionata')
            ?? $user->IdAssociazione;

        // Se l'utente NON ha ruoli alti, forziamo la sua associazione
        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }

        // Dati filtrati per associazione
        $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);
        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->where('idAssociazione', $idAssociazione)
            ->sortBy('idConvenzione')
            ->values();
        $kmData = AutomezzoKm::getGroupedByAutomezzoAndConvenzione($anno, $user)
            ->filter(function ($group, $key) use ($automezzi) {
                // filtro i dati per i soli automezzi validi (in caso di dati sporchi)
                [$idAutomezzo,] = explode('-', $key);
                return $automezzi->pluck('idAutomezzo')->contains((int) $idAutomezzo);
            });

        $labels = [];
        foreach ($convenzioni as $c) {
            $labels['c' . $c->idConvenzione] = $c->Convenzione;
        }

        $rows = [];
        $totali = [
            'idAutomezzo' => null,
            'Automezzo' => 'TOTALE',
            'Targa' => '',
            'CodiceIdentificativo' => '',
            'Totale' => 0,
            'is_totale' => -1
        ];
        foreach ($convenzioni as $c) {
            $key = 'c' . $c->idConvenzione;
            $totali["{$key}_km"] = 0;
            $totali["{$key}_percent"] = 0;
        }

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
                'is_totale' => 0
            ];
            $totali['Totale'] += $totKm;

            foreach ($convenzioni as $c) {
                $key = 'c' . $c->idConvenzione;
                $lookup = $a->idAutomezzo . '-' . $c->idConvenzione;

                $kmPercorsi = 0;
                if ($kmData->has($lookup)) {
                    $val = $kmData->get($lookup)->first();
                    $kmPercorsi = $val->KMPercorsi ?? 0;
                }

                $percentuale = $totKm > 0 ? round(($kmPercorsi / $totKm) * 100, 2) : 0;

                $riga["{$key}_km"] = $kmPercorsi;
                $riga["{$key}_percent"] = $percentuale;

                $totali["{$key}_km"] += $kmPercorsi;
            }

            $rows[] = $riga;
        }

        // Calcola percentuali totali
        $percentSum = 0;
        $lastIndex = count($convenzioni) - 1;
        foreach ($convenzioni as $i => $c) {
            $key = 'c' . $c->idConvenzione;
            $val = $totali["{$key}_km"];

            if ($i < $lastIndex) {
                $percent = $totali['Totale'] > 0 ? round(($val / $totali['Totale']) * 100, 2) : 0;
                $totali["{$key}_percent"] = $percent;
                $percentSum += $percent;
            } else {
                $totali["{$key}_percent"] = max(0, round(100 - $percentSum, 2));
            }
        }

        $rows[] = $totali;

        return response()->json([
            'data' => $rows,
            'labels' => $labels
        ]);
    }


    public function edit(int $id) {
        $user = Auth::user();

        $anno = session('anno_riferimento', now()->year);
        $automezzo = Automezzo::getById($id, $anno);

        $convenzioni = Convenzione::getByAnno($anno, $user)->sortBy('idConvenzione')->values();
        $kmEsistenti = AutomezzoKm::getKmPerConvenzione($automezzo->idAutomezzo, $anno);

        return view('km_percorsi.edit', compact('automezzo', 'convenzioni', 'kmEsistenti'));
    }

    public function create() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        // Associazione da sessione o fallback su quella dell’utente
        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;
        
        // Se non hai i privilegi, forzi comunque l’associazione utente
        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }

        // Automezzi filtrati per anno e associazione (null = tutte se admin)
        $automezzi = Automezzo::getLightForAnno($anno, $user->hasAnyRole(['SuperAdmin', 'Admin']) ? null : $idAssociazione);

        // Convenzioni correttamente filtrate per associazione + anno
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
            ->sortBy('idConvenzione')
            ->values();

        return view('km_percorsi.create', compact('automezzi', 'convenzioni', 'idAssociazione'));
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
