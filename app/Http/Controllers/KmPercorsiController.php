<?php

namespace App\Http\Controllers;

use App\Models\Automezzo;
use App\Models\Convenzione;
use App\Models\AutomezzoKm;
use App\Models\Dipendente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class KmPercorsiController extends Controller {
    /**
     * Vista principale (pivot km per convenzione).
     */
    public function index(Request $request) {
        $anno            = (int) session('anno_riferimento', now()->year);
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $selectedAssoc = (int) session('associazione_selezionata', $user->IdAssociazione);
        $associazioni  = Dipendente::getAssociazioni($user, $isImpersonating);

        // Convenzioni dell’associazione corrente
        $convenzioni = Convenzione::getByAssociazioneAnno($selectedAssoc, $anno);

        // Parametro opzionale per deep-link (non indispensabile per la tabella)
        $selectedConvId = $request->query('idConvenzione');
        $selectedConvId = $selectedConvId ? (int) $selectedConvId : null;

        // Flag della convenzione selezionata (solo se passato)
        $abilitaRotSost = null;
        if ($selectedConvId) {
            $row = DB::selectOne(
                'SELECT abilita_rot_sost FROM convenzioni WHERE idConvenzione = ? LIMIT 1',
                [$selectedConvId]
            );
            $abilitaRotSost = $row ? (int) $row->abilita_rot_sost : null;
        }

        return view('km_percorsi.index', compact(
            'anno',
            'convenzioni',
            'associazioni',
            'selectedAssoc',
            'selectedConvId',
            'abilitaRotSost'
        ));
    }

    /**
     * JSON per DataTables: righe per automezzo con colonne dinamiche per convenzione.
     * Include, per ciascuna convenzione, anche il flag `${cXX}_is_titolare`.
     */
    public function getData(Request $request): JsonResponse {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        // Associazione da query/sessione; se utente non elevato, forza la sua
        $idAssociazione = $request->query('idAssociazione')
            ?? session('associazione_selezionata')
            ?? $user->IdAssociazione;

        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }
        $idAssociazione = (int) $idAssociazione;

        // Dati base
        $automezzi   = Automezzo::getByAssociazione($idAssociazione, $anno); // Collection
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
            ->sortBy('idConvenzione')->values();

        // Mappa km: chiave "idAutomezzo-idConvenzione" => collection(rows)
        // ATT: il model ora seleziona anche k.is_titolare
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

        // Costruzione righe
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
            $totali["{$k}_km"]        = 0;
            $totali["{$k}_percent"]   = 0;
            $totali["{$k}_is_titolare"] = null; // non serve sul totale, ma manteniamo la chiave
        }

        foreach ($automezzi as $a) {
            // Tot km (interi) per riga
            $totKm = collect($kmGroupedMap)
                ->filter(fn($v, $k) => str_starts_with($k, $a->idAutomezzo . '-'))
                ->flatMap(fn($group) => $group)
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
                $kLookup    = $a->idAutomezzo . '-' . $c->idConvenzione;
                $k          = 'c' . $c->idConvenzione;
                $kmPercorsi = 0;
                $isTit      = 0;

                if ($kmGroupedMap->has($kLookup)) {
                    $first      = $kmGroupedMap->get($kLookup)->first();
                    $kmPercorsi = (int) ($first->KMPercorsi ?? 0);
                    $isTit      = (int) ($first->is_titolare ?? 0);
                }

                $percent = $totKm > 0 ? round(($kmPercorsi / $totKm) * 100, 2) : 0;

                $riga["{$k}_km"]         = $kmPercorsi;
                $riga["{$k}_percent"]    = $percent;
                $riga["{$k}_is_titolare"] = $isTit;

                $totali["{$k}_km"] += $kmPercorsi;
            }

            $rows[] = $riga;
        }

        // Percentuali totali per colonna (somma 100)
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
            'meta'   => [
                // meta opzionali utili se arrivi da deep-link
                'selectedConvId' => $request->query('idConvenzione') ? (int)$request->query('idConvenzione') : null,
                'abilitaRotSost' => $request->query('idConvenzione')
                    ? (int) (DB::selectOne('SELECT abilita_rot_sost FROM convenzioni WHERE idConvenzione = ? LIMIT 1', [(int)$request->query('idConvenzione')])->abilita_rot_sost ?? 0)
                    : null,
            ],
        ]);
    }

    /**
     * Form di editing per un singolo automezzo (km per convenzione).
     * Qui metterai anche la radio/stellina per nominare il TITOLARE per ciascuna convenzione.
     */
    public function edit(int $id) {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;
        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }
        $idAssociazione = (int) $idAssociazione;

        $automezzo = Automezzo::getById($id, $anno);
        abort_if(!$automezzo, 404);

        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
            ->sortBy('idConvenzione')->values();
        $kmEsistenti = AutomezzoKm::getKmPerConvenzione($automezzo->idAutomezzo, $anno); // keyBy nel model (include is_titolare)

        return view('km_percorsi.edit', compact('automezzo', 'convenzioni', 'kmEsistenti'));
    }

    /**
     * Form di creazione (selezione automezzo, inserimento km).
     */
    public function create() {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;
        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }
        $idAssociazione = (int) $idAssociazione;

        $automezzi   = Automezzo::getLightForAnno($anno, $user->hasAnyRole(['SuperAdmin', 'Admin']) ? null : $idAssociazione);
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)->sortBy('idConvenzione')->values();

        return view('km_percorsi.create', compact('automezzi', 'convenzioni', 'idAssociazione'));
    }

    /**
     * Salva km per convenzione (creazione).
     */
    public function store(Request $request) {
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
                    AutomezzoKm::upsert($idAutomezzo, (int)$idConvenzione, $km);
                }
            }
            //Automezzo::refreshKmTotaliFor($idAutomezzo, $anno);
        });

        return redirect()->route('km-percorsi.index')->with('success', 'KM percorsi salvati con successo.');
    }

    /**
     * Dettaglio lettura.
     */
    public function show(int $id) {
        $anno      = (int) session('anno_riferimento', now()->year);
        $automezzo = Automezzo::getById($id, $anno);
        abort_if(!$automezzo, 404);

        $convenzioni = Convenzione::getByAssociazioneAnno((int)$automezzo->idAssociazione, $anno)
            ->sortBy('idConvenzione')->values();

        $kmEsistenti = AutomezzoKm::getByAutomezzo($id, $anno)->keyBy('idConvenzione');

        return view('km_percorsi.show', compact('automezzo', 'convenzioni', 'kmEsistenti'));
    }

    /**
     * Aggiorna km per convenzione (edit).
     */
    public function update(Request $request, int $idAutomezzo) {
        $request->validate([
            'km'         => 'required|array',
            'titolare'   => 'array', // opzionale
            'titolare.*' => 'integer',
        ]);

        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        // Recupero le convenzioni dell'associazione/anno per sapere quali sono abilitate al flag
        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;
        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }
        $idAssociazione = (int) $idAssociazione;

        $convsAbilitate = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('abilita_rot_sost', 1)
            ->pluck('idConvenzione')
            ->all();

        $kmArray       = $request->input('km', []);
        $titolariCheck = collect($request->input('titolare', []))
            ->map(fn($v) => (int)$v)
            ->filter()
            ->values()
            ->all(); // array di idConvenzione spuntati

        DB::transaction(function () use ($idAutomezzo, $kmArray, $anno, $convsAbilitate, $titolariCheck) {

            // 1) Pulizia totale delle righe dell’automezzo, poi re-inseriamo (scelta semplice e coerente)
            AutomezzoKm::deleteByAutomezzo($idAutomezzo);

            // 2) Re-inserimento KM (se >0), oppure 0 se convenzione è spuntata come titolare
            foreach ($kmArray as $idConvenzione => $km) {
                $idConvenzione = (int) $idConvenzione;
                $val           = is_numeric($km) ? (int)$km : 0;

                // Se è titolare (spuntato) ma km = 0, inseriamo comunque la riga a 0
                if ($val > 0 || in_array($idConvenzione, $titolariCheck, true)) {
                    AutomezzoKm::upsert($idAutomezzo, $idConvenzione, $val);
                }
            }

            // 3) Gestione titolarità (solo per convenzioni abilitate)
            foreach ($convsAbilitate as $idConvenzione) {
                if (in_array($idConvenzione, $titolariCheck, true)) {
                    // Imposta questo automezzo come unico titolare della convenzione
                    AutomezzoKm::setTitolare((int)$idConvenzione, $idAutomezzo);
                } else {
                    // Se prima era titolare e ora non lo è più, togli la titolarità
                    AutomezzoKm::unsetTitolare((int)$idConvenzione, $idAutomezzo);
                }
            }

            // 4) Refresh totali del mezzo
            //Automezzo::refreshKmTotaliFor($idAutomezzo, $anno);
        });

        return redirect()->route('km-percorsi.index')->with('success', 'KM percorsi aggiornati con successo.');
    }


    /**
     * Elimina i km di un automezzo e aggiorna i totali.
     */
    public function destroy(int $idAutomezzo) {
        $anno = (int) session('anno_riferimento', now()->year);

        DB::transaction(function () use ($idAutomezzo, $anno) {
            AutomezzoKm::deleteByAutomezzo($idAutomezzo);
            //Automezzo::refreshKmTotaliFor($idAutomezzo, $anno);
        });

        return redirect()->route('km-percorsi.index')->with('success', 'KM percorsi eliminati.');
    }

    /**
     * Nomina atomica del mezzo TITOLARE per una convenzione.
     * POST /km-percorsi/{idConvenzione}/titolare  body: { idAutomezzo: int }
     */
    public function setTitolare(Request $request, int $idConvenzione): JsonResponse {
        $request->validate([
            'idAutomezzo' => 'required|integer',
        ]);

        $idConvenzione = (int) $idConvenzione;
        $idAutomezzo   = (int) $request->input('idAutomezzo');

        // opzionale: verifica che la convenzione abbia il flag abilitato
        $row = DB::selectOne(
            'SELECT abilita_rot_sost FROM convenzioni WHERE idConvenzione = ? LIMIT 1',
            [$idConvenzione]
        );
        if (!$row || (int)$row->abilita_rot_sost !== 1) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Funzione disattivata per questa convenzione',
            ], 422);
        }

        DB::transaction(function () use ($idConvenzione, $idAutomezzo) {
            AutomezzoKm::setTitolare($idConvenzione, $idAutomezzo);
        });

        return response()->json(['ok' => true]);
    }
}
