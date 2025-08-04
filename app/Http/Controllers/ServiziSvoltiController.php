<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Automezzo;
use App\Models\Convenzione;
use App\Models\AutomezzoServiziSvolti;
use Illuminate\Support\Collection;
use App\Models\User;

class ServiziSvoltiController extends Controller {
    /**
     * Mostra la vista principale
     */
    public function index() {
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');
        $anno = session('anno_riferimento', now()->year);
        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;

        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }

        $associazioni = \App\Models\Dipendente::getAssociazioni($user, $isImpersonating);
        $selectedAssoc = $idAssociazione;
        return view('servizi_svolti.index', compact('anno', 'selectedAssoc', 'associazioni'));
    }

    /**
     * Restituisce i dati per DataTables, pivotati per convenzione.
     */
    public function getData(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $idAssociazione = $request->query('idAssociazione') ?? $user->IdAssociazione;

        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }

        $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)->sortBy('idConvenzione')->values();
        // Raggruppa i servizi esistenti
        $serviziRaw = AutomezzoServiziSvolti::getGroupedByAutomezzoAndConvenzione($anno, $idAssociazione);

        $rows = [];
        // inizializza riga TOTALE
        $totale = array_merge([
            'idAutomezzo' => null,
            'Automezzo' => 'TOTALE',
            'Targa' => '',
            'CodiceIdentificativo' => '',
            'Totale' => 0,
            'is_totale' => -1,
        ], collect($convenzioni)->flatMap(fn($c) => [
            'c' . $c->idConvenzione . '_n' => 0,
            'c' . $c->idConvenzione . '_percent' => 0,
        ])->all());

        foreach ($automezzi as $a) {
            // calcola Totale servizi per automezzo
            $riga = [
                'idAutomezzo' => $a->idAutomezzo,
                'Automezzo' => $a->Automezzo,
                'Targa' => $a->Targa,
                'CodiceIdentificativo' => $a->CodiceIdentificativo ?? '',
                'Totale' => 0,
                'is_totale' => 0,
            ];

            // numero per convenzione
            foreach ($convenzioni as $c) {
                $key = 'c' . $c->idConvenzione;
                $record = $serviziRaw->get("{$a->idAutomezzo}-{$c->idConvenzione}")?->first();
                $n = $record->NumeroServizi ?? 0;
                $riga["{$key}_n"] = $n;
                $riga['Totale'] += $n;
                $totale["{$key}_n"] += $n;
            }

            // percentuali per convenzione
            foreach ($convenzioni as $c) {
                $key = 'c' . $c->idConvenzione;
                $riga["{$key}_percent"] = $riga['Totale'] > 0
                    ? round(($riga["{$key}_n"] / $riga['Totale']) * 100, 2)
                    : 0;
            }

            // accumula totali complessivi
            $totale['Totale'] += $riga['Totale'];
            $rows[] = $riga;
        }

        // calcola percentuali sulla riga totale, correggendo eventuale arrotondamento
        $percentSum = 0;
        $last = count($convenzioni) - 1;
        foreach ($convenzioni->values() as $i => $c) {
            $key = 'c' . $c->idConvenzione;
            if ($i < $last) {
                $p = $totale['Totale'] > 0
                    ? round(($totale["{$key}_n"] / $totale['Totale']) * 100, 2)
                    : 0;
                $totale["{$key}_percent"] = $p;
                $percentSum += $p;
            } else {
                // chiudi a 100%
                $totale["{$key}_percent"] = max(0, round(100 - $percentSum, 2));
            }
        }

        $rows[] = $totale;

        // prepara labels per DataTables
        $labels = [];
        foreach ($convenzioni as $c) {
            $labels['c' . $c->idConvenzione] = $c->Convenzione;
        }

        return response()->json([
            'data' => $rows,
            'labels' => $labels,
        ]);
    }

    /**
     * Mostra form di creazione.
     */
    public function create() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;

        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione;
        }

        $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
            ->sortBy('idConvenzione')
            ->values();

        $associazioni = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? \App\Models\Dipendente::getAssociazioni($user, session()->has('impersonate'))
            : collect();

        return view('servizi_svolti.create', compact(
            'automezzi',
            'convenzioni',
            'associazioni',
            'idAssociazione' // opzionale
        ))->with('selectedAssoc', $idAssociazione);
    }


    /**
     * Salva i servizi inseriti.
     */
    public function store(Request $request) {
        $data = $request->validate([
            'servizi' => 'required|array',
            '*.servizi.*' => 'nullable|integer|min:0',
        ]);

        foreach ($data['servizi'] as $idConv => $n) {
            AutomezzoServiziSvolti::upsert(
                (int)$request->input('idAutomezzo'),
                (int)$idConv,
                (int)$n
            );
        }

        return redirect()
            ->route('servizi-svolti.index')
            ->with('success', 'Servizi salvati correttamente.');
    }

    /**
     * Mostra i dettagli di un automezzo.
     */
    public function show(int $id) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $automezzo = Automezzo::getById($id, $anno);
        $convenzioni = Convenzione::getByAnno($anno, $user)->sortBy('idConvenzione')->values();
        $raw    = AutomezzoServiziSvolti::getGroupedByAutomezzoAndConvenzione($anno, $user);

        // estrai semplici key => numero
        $valori = collect();
        foreach ($raw as $key => $rows) {
            [$idAuto, $idConv] = explode('-', $key);
            if ((int)$idAuto === $id) {
                $valori->put((int)$idConv, $rows->first()->NumeroServizi);
            }
        }

        return view('servizi_svolti.show', compact('automezzo', 'convenzioni', 'valori'));
    }

    /**
     * Mostra form di modifica.
     */
    public function edit(int $id) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        // Determina l'associazione in base ai privilegi
        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;
        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $isImpersonating = session()->has('impersonate');
            $idAssociazione = $user->IdAssociazione;
            $associazioni = \App\Models\Dipendente::getAssociazioni($user, $isImpersonating);
        }else {
            $associazioni = collect(); // lista vuota se non servono
        }

        // Ottieni automezzo e convenzioni
        $automezzo = Automezzo::getById($id, $anno);
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
            ->sortBy('idConvenzione')
            ->values();

        // Dati servizi esistenti raggruppati
        $raw = AutomezzoServiziSvolti::getGroupedByAutomezzoAndConvenzione($anno, $idAssociazione);

        $servizi = collect();
        foreach ($raw as $k => $rows) {
            [$idAuto, $idConv] = explode('-', $k);
            if ((int)$idAuto === $id) {
                $servizi->put((int)$idConv, $rows->first());
            }
        }

        return view('servizi_svolti.edit', [
            'automezzo'        => $automezzo,
            'convenzioni'      => $convenzioni,
            'serviziEsistenti' => $servizi,
            'associazioni'     => $associazioni,
            'selectedAssoc'    => $idAssociazione,
        ]);
    }


    /**
     * Applica l’aggiornamento.
     */
    public function update(Request $request, int $id) {
        $data = $request->validate([
            'servizi' => 'required|array',
            '*.servizi.*' => 'nullable|integer|min:0',
        ]);

        AutomezzoServiziSvolti::deleteByAutomezzo($id);

        foreach ($data['servizi'] as $idConv => $n) {
            AutomezzoServiziSvolti::upsert($id, (int)$idConv, (int)$n);
        }

        return redirect()
            ->route('servizi-svolti.index')
            ->with('success', 'Servizi aggiornati con successo.');
    }

    /**
     * Elimina tutti i servizi di un automezzo.
     */
    public function destroy(int $id) {
        AutomezzoServiziSvolti::deleteByAutomezzo($id);
        return redirect()
            ->route('servizi-svolti.index')
            ->with('success', 'Record eliminato.');
    }
}
