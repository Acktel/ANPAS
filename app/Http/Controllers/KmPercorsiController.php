<?php

namespace App\Http\Controllers;

use App\Models\Automezzo;
use App\Models\Convenzione;
use App\Models\AutomezzoKm;
use App\Models\Dipendente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KmPercorsiController extends Controller
{
    /**
     * Vista principale (tabella pivot per convenzione).
     */
    public function index(Request $request)
    {
        $anno            = (int) session('anno_riferimento', now()->year);
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $selectedAssoc = session('associazione_selezionata', $user->IdAssociazione);
        $associazioni  = Dipendente::getAssociazioni($user, $isImpersonating);

        // Se vuoi mostrare anche le convenzioni nella vista: filtra correttamente per associazione
        $convenzioni = Convenzione::getByAssociazioneAnno((int) $selectedAssoc, $anno);

        return view('km_percorsi.index', compact('anno', 'convenzioni', 'associazioni', 'selectedAssoc'));
    }

    /**
     * JSON per DataTables: righe per automezzo con colonne dinamiche per convenzione.
     */
    public function getData(Request $request)
    {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        // Associazione da query/sessione; se l'utente non è elevato, forziamo la sua
        $idAssociazione = $request->query('idAssociazione')
            ?? session('associazione_selezionata')
            ?? $user->IdAssociazione;

        if (! $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }
        $idAssociazione = (int) $idAssociazione;

        // Dati base
        $automezzi    = Automezzo::getByAssociazione($idAssociazione, $anno);
        $convenzioni  = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)->sortBy('idConvenzione')->values();
        $kmGroupedMap = AutomezzoKm::getGroupedByAutomezzoAndConvenzione($anno, $user, $idAssociazione)
            ->filter(function ($group, $key) use ($automezzi) {
                [$idAutomezzo,] = explode('-', $key);
                return $automezzi->pluck('idAutomezzo')->contains((int) $idAutomezzo);
            });

        // Etichette colonne dinamiche
        $labels = [];
        foreach ($convenzioni as $c) {
            $labels['c' . $c->idConvenzione] = $c->Convenzione;
        }

        // Righe
        $rows   = [];
        $totali = [
            'idAutomezzo'          => null,
            'Targa'                => 'TOTALE',
            'CodiceIdentificativo' => '',
            'Totale'               => 0,
            'is_totale'            => -1,
        ];
        foreach ($convenzioni as $c) {
            $k = 'c' . $c->idConvenzione;
            $totali["{$k}_km"]      = 0;
            $totali["{$k}_percent"] = 0;
        }

        foreach ($automezzi as $a) {
            // somma totale km (interi) per riga
            $totKm = collect($kmGroupedMap)
                ->filter(fn ($v, $k) => str_starts_with($k, $a->idAutomezzo . '-'))
                ->flatMap(fn ($group) => $group)
                ->sum('KMPercorsi');

            $riga = [
                'idAutomezzo'          => $a->idAutomezzo,
                'Targa'                => $a->Targa,
                'CodiceIdentificativo' => $a->CodiceIdentificativo ?? '',
                'Totale'               => (int) $totKm,
                'is_totale'            => 0,
            ];
            $totali['Totale'] += (int) $totKm;

            foreach ($convenzioni as $c) {
                $kLookup = $a->idAutomezzo . '-' . $c->idConvenzione;
                $k       = 'c' . $c->idConvenzione;

                $kmPercorsi = 0;
                if ($kmGroupedMap->has($kLookup)) {
                    $first     = $kmGroupedMap->get($kLookup)->first();
                    $kmPercorsi = (int) ($first->KMPercorsi ?? 0);
                }

                $percent = $totKm > 0 ? round(($kmPercorsi / $totKm) * 100, 2) : 0;

                $riga["{$k}_km"]      = $kmPercorsi;
                $riga["{$k}_percent"] = $percent;

                $totali["{$k}_km"] += $kmPercorsi;
            }

            $rows[] = $riga;
        }

        // Percentuali totali per colonna (accortezza per sommare a 100)
        $percentSum = 0;
        $lastIndex  = count($convenzioni) - 1;
        foreach ($convenzioni as $i => $c) {
            $k   = 'c' . $c->idConvenzione;
            $val = (int) $totali["{$k}_km"];

            if ($i < $lastIndex) {
                $p = $totali['Totale'] > 0 ? round(($val / $totali['Totale']) * 100, 2) : 0;
                $totali["{$k}_percent"] = $p;
                $percentSum += $p;
            } else {
                $totali["{$k}_percent"] = max(0, round(100 - $percentSum, 2));
            }
        }

        $rows[] = $totali;

        return response()->json([
            'data'   => $rows,
            'labels' => $labels,
        ]);
    }

    /**
     * Form di editing per un singolo automezzo (km per convenzione).
     */
    public function edit(int $id)
    {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;
        if (! $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }
        $idAssociazione = (int) $idAssociazione;

        $automezzo    = Automezzo::getById($id, $anno);
        abort_if(! $automezzo, 404);

        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
            ->sortBy('idConvenzione')->values();
        $kmEsistenti = AutomezzoKm::getKmPerConvenzione($automezzo->idAutomezzo, $anno); // keyBy in model

        return view('km_percorsi.edit', compact('automezzo', 'convenzioni', 'kmEsistenti'));
    }

    /**
     * Form di creazione (selezione automezzo, inserimento km).
     */
    public function create()
    {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;
        if (! $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }
        $idAssociazione = (int) $idAssociazione;

        $automezzi    = Automezzo::getLightForAnno($anno, $user->hasAnyRole(['SuperAdmin', 'Admin']) ? null : $idAssociazione);
        $convenzioni  = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)->sortBy('idConvenzione')->values();

        return view('km_percorsi.create', compact('automezzi', 'convenzioni', 'idAssociazione'));
    }

    /**
     * Salva km per convenzione (creazione).
     */
    public function store(Request $request)
    {
        $request->validate([
            'idAutomezzo' => 'required|integer|exists:automezzi,idAutomezzo',
            'km'          => 'required|array',
        ]);

        $idAutomezzo = (int) $request->input('idAutomezzo');
        $kmArray     = $request->input('km');
        $anno        = (int) session('anno_riferimento', now()->year);

        DB::transaction(function () use ($idAutomezzo, $kmArray, $anno) {
            foreach ($kmArray as $idConvenzione => $km) {
                if (is_numeric($km) && (int)$km > 0) {
                    AutomezzoKm::upsert($idAutomezzo, (int) $idConvenzione, $km); // normalizza a intero nel model
                }
            }
            Automezzo::refreshKmTotaliFor($idAutomezzo, $anno);
        });

        return redirect()->route('km-percorsi.index')->with('success', 'KM percorsi salvati con successo.');
    }

    /**
     * Dettaglio lettura.
     */
    public function show(int $id)
    {
        $anno        = (int) session('anno_riferimento', now()->year);
        $automezzo   = Automezzo::getById($id, $anno);
        abort_if(! $automezzo, 404);

        // Se vuoi filtrare le convenzioni per la stessa associazione dell'automezzo:
        $convenzioni = Convenzione::getByAssociazioneAnno((int)$automezzo->idAssociazione, $anno)
            ->sortBy('idConvenzione')->values();

        $kmEsistenti = AutomezzoKm::getByAutomezzo($id, $anno)->keyBy('idConvenzione');

        return view('km_percorsi.show', compact('automezzo', 'convenzioni', 'kmEsistenti'));
    }

    /**
     * Aggiorna km per convenzione (edit).
     */
    public function update(Request $request, int $idAutomezzo)
    {
        $request->validate([
            'km' => 'required|array',
        ]);

        $kmArray = $request->input('km');
        $anno    = (int) session('anno_riferimento', now()->year);

        DB::transaction(function () use ($idAutomezzo, $kmArray, $anno) {
            // pulizia e re-inserimento (se preferisci merge, rimuovi questa riga)
            AutomezzoKm::deleteByAutomezzo($idAutomezzo);

            foreach ($kmArray as $idConvenzione => $km) {
                if (is_numeric($km) && (int)$km > 0) {
                    AutomezzoKm::upsert($idAutomezzo, (int) $idConvenzione, $km);
                }
            }
            Automezzo::refreshKmTotaliFor($idAutomezzo, $anno);
        });

        return redirect()->route('km-percorsi.index')->with('success', 'KM percorsi aggiornati con successo.');
    }

    /**
     * Elimina i km di un automezzo e aggiorna i totali.
     */
    public function destroy(int $idAutomezzo)
    {
        $anno = (int) session('anno_riferimento', now()->year);

        DB::transaction(function () use ($idAutomezzo, $anno) {
            AutomezzoKm::deleteByAutomezzo($idAutomezzo);
            Automezzo::refreshKmTotaliFor($idAutomezzo, $anno);
        });

        return redirect()->route('km-percorsi.index')->with('success', 'KM percorsi eliminati.');
    }
}
