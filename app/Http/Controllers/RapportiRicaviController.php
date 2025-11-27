<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;
use App\Models\RapportoRicavo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RapportiRicaviController extends Controller {
    /* =========================================
     * INDEX: passa anno, associazioni e selectedAssoc
     * ========================================= */
    public function index(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        if ($isElevato) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            // priorità: query -> sessione -> prima disponibile
            if ($request->filled('idAssociazione')) {
                session(['associazione_selezionata' => (int)$request->integer('idAssociazione')]);
            }

            $selectedAssoc = (int) (session('associazione_selezionata')
                ?? optional($associazioni->first())->idAssociazione);
        } else {
            $associazioni  = collect(); // niente select per utenti non elevati
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        return view('rapporti_ricavi.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }

    /* =========================================
     * DATATABLE: riga per associazione selezionata
     * labels: { "c{idConv}": "Nome convenzione" }
     * data:   [ { Associazione, TotaleEsercizio, cX_rimborso, cX_percent, ... } ]
     * ========================================= */
    public function getData(Request $request) {
        $user = session()->has('impersonate')
            ? User::find(session('impersonate'))
            : auth()->user();

        $anno = (int) session('anno_riferimento', now()->year);
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        $idAssociazione = $isElevato
            ? (int) ($request->input('idAssociazione') ?: session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['data' => [], 'labels' => []]);
        }

        // Convenzioni di quell'associazione/anno (ordine stabile)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get();

        $labels = [];
        foreach ($convenzioni as $c) {
            $labels['c' . $c->idConvenzione] = $c->Convenzione;
        }

        // Ricavi salvati per associazione/anno
        $ricavi = DB::table('rapporti_ricavi as rr')
            ->join('associazioni as a', 'rr.idAssociazione', '=', 'a.idAssociazione')
            ->select('rr.idConvenzione', 'rr.Rimborso', 'a.Associazione')
            ->where('rr.idAnno', $anno)
            ->where('rr.idAssociazione', $idAssociazione)
            ->get();

        $totale = (float) $ricavi->sum('Rimborso');

        $riga = [
            'idAssociazione'  => $idAssociazione,
            'Associazione'    => $ricavi->first()->Associazione
                ?? DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione'),
            'TotaleEsercizio' => $totale,
        ];

        foreach ($convenzioni as $conv) {
            $key = 'c' . $conv->idConvenzione;
            $val = (float) optional($ricavi->firstWhere('idConvenzione', $conv->idConvenzione))->Rimborso ?? 0;
            $riga["{$key}_rimborso"] = $val;
            $riga["{$key}_percent"]  = $totale > 0 ? round($val / $totale * 100, 2) : 0.0;
        }

        return response()->json([
            'data'   => [$riga], // una riga per l’associazione selezionata
            'labels' => $labels,
        ]);
    }

    /* ====== resto metodi (CRUD) invariati, con minimi tocchi di coerenza ====== */

    public function create() {
        $user = Auth::user();

        if ($user->hasAnyRole(['SuperAdmin', 'Admin'])) {
            $associazioni = DB::table('associazioni')
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

        $anno = (int) session('anno_riferimento', now()->year);
        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')
            ->values();

        return view('rapporti_ricavi.create', compact('associazioni', 'convenzioni', 'anno'));
    }

    public function store(Request $request) {
        $user = Auth::user();

        $rules = [
            'idAssociazione' => 'sometimes|exists:associazioni,idAssociazione',
            'ricavi'         => 'array',
            'ricavi.*'       => 'nullable|numeric|min:0',
            'note'           => 'array',
            'note.*'         => 'nullable|string',
        ];
        $validated = $request->validate($rules);

        $idAss = (int) ($validated['idAssociazione'] ?? $user->IdAssociazione);
        $anno  = (int) session('anno_riferimento', now()->year);

        $ricavi = $validated['ricavi'] ?? [];
        $notes  = $validated['note']  ?? [];

        // unione degli idConvenzione presenti in ricavi e/o note
        $ids = array_unique(array_merge(array_keys($ricavi), array_keys($notes)));

        foreach ($ids as $idConv) {
            if (!is_numeric($idConv)) continue;

            $rimborso = $ricavi[$idConv] ?? null;
            $nota     = $notes[$idConv]  ?? null;

            // se non c’è né rimborso né nota, salta
            if ($rimborso === null && ($nota === null || trim($nota) === '')) {
                continue;
            }

            $rimborsoVal = ($rimborso === null) ? 0.0 : (float)$rimborso;

            RapportoRicavo::upsert((int)$idConv, $idAss, $anno, $rimborsoVal, $nota);
        }

        return redirect()->route('rapporti-ricavi.index')
            ->with('success', 'Ricavi (e note) salvati correttamente.');
    }


    public function edit(int $idAssociazione) {
        $user = session()->has('impersonate')
            ? User::find(session('impersonate'))
            : auth()->user();

        if (
            !$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) &&
            (int)$user->IdAssociazione !== (int)$idAssociazione
        ) {
            abort(403);
        }

        $anno = (int) session('anno_riferimento', now()->year);

        // CONVENZIONI SOLO di questa associazione e anno
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get();

        // RICAVI esistenti (con note), keyBy su intero
        $valori = DB::table('rapporti_ricavi')
            ->select('idConvenzione', 'Rimborso', 'note')
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->get()
            ->keyBy(fn($r) => (int)$r->idConvenzione);

        $associazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione')
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
        $anno = (int) session('anno_riferimento', now()->year);

        $rules = [
            'ricavi'   => 'array',
            'ricavi.*' => 'nullable|numeric|min:0',
            'note'     => 'array',
            'note.*'   => 'nullable|string',
        ];
        $validated = $request->validate($rules);

        $ricavi = $validated['ricavi'] ?? [];
        $notes  = $validated['note']  ?? [];

        // rigenero tutte le righe per l’associazione/anno
        RapportoRicavo::deleteByAssociazione($idAssociazione, $anno);

        $ids = array_unique(array_merge(array_keys($ricavi), array_keys($notes)));

        foreach ($ids as $idConv) {
            if (!is_numeric($idConv)) continue;

            $rimborso = $ricavi[$idConv] ?? null;
            $nota     = $notes[$idConv]  ?? null;

            if ($rimborso === null && ($nota === null || trim($nota) === '')) {
                continue; // nessun dato, non inserire riga
            }

            $rimborsoVal = ($rimborso === null) ? 0.0 : (float)$rimborso;

            RapportoRicavo::upsert((int)$idConv, (int)$idAssociazione, $anno, $rimborsoVal, $nota);
        }

        return redirect()->route('rapporti-ricavi.index')
            ->with('success', 'Ricavi (e note) aggiornati.');
    }


    public function show(int $idAssociazione) {
        return $this->edit($idAssociazione);
    }

    public function destroy(int $idAssociazione) {
        $anno = (int) session('anno_riferimento', now()->year);
        RapportoRicavo::deleteByAssociazione($idAssociazione, $anno);

        return redirect()->route('rapporti-ricavi.index')
            ->with('success', 'Dati eliminati.');
    }
}
