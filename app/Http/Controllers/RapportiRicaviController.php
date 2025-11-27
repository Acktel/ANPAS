<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;
use App\Models\RapportoRicavo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RapportiRicaviController extends Controller {

    /* ============================================================
     * INDEX
     * ============================================================ */
    public function index(Request $request) {

        $anno  = (int) session('anno_riferimento', now()->year);
        $user  = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor']) 
                     || session()->has('impersonate');

        if ($isElevato) {

            $associazioni = DB::table('associazioni')
                ->select('idAssociazione','Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            if ($request->filled('idAssociazione')) {
                session(['associazione_selezionata' => (int)$request->integer('idAssociazione')]);
            }

            $selectedAssoc = (int) (session('associazione_selezionata')
                ?? optional($associazioni->first())->idAssociazione);

        } else {

            $associazioni  = collect();
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        return view('rapporti_ricavi.index', compact('anno','associazioni','selectedAssoc'));
    }

    /* ============================================================
     * DATATABLE (getData)
     *  → UNA RIGA per associazione selezionata
     * ============================================================ */
    public function getData(Request $request) {

        $user = session()->has('impersonate')
            ? User::find(session('impersonate'))
            : auth()->user();

        $anno = (int) session('anno_riferimento', now()->year);

        $isElevato = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])
                     || session()->has('impersonate');

        $idAssociazione = $isElevato
            ? (int) ($request->input('idAssociazione') ?: session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['labels'=>[], 'data'=>[]]);
        }

        // Convenzioni di quell’associazione / anno (ordinarle bene)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione','Convenzione')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get();

        // Labels header dinamico
        $labels = [];
        foreach ($convenzioni as $c) {
            $labels['c'.$c->idConvenzione] = $c->Convenzione;
        }

        // 📌 RICAVI tramite MODEL (logica unica)
        $ricavi = RapportoRicavo::getRicaviPerAssociazione($idAssociazione, $anno);
        $totale = RapportoRicavo::getTotaleRicavi($idAssociazione, $anno);

        $nomeAssociazione =
            optional($ricavi->first())->Associazione
            ?: DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione');

        $riga = [
            'idAssociazione'  => $idAssociazione,
            'Associazione'    => $nomeAssociazione,
            'TotaleEsercizio' => $totale,
        ];

        // Genero colonne dinamiche convenzioni
        foreach ($convenzioni as $conv) {
            $key = 'c'.$conv->idConvenzione;

            $val = (float) optional($ricavi->firstWhere('idConvenzione', $conv->idConvenzione))->Rimborso ?? 0;

            $riga["{$key}_rimborso"] = $val;
            $riga["{$key}_percent"]  = $totale > 0 ? round($val / $totale * 100, 2) : 0.0;
        }

        return response()->json([
            'labels' => $labels,
            'data'   => [$riga],
        ]);
    }

    /* ============================================================
     * CREATE
     * ============================================================ */
    public function create() {

        $user = Auth::user();

        if ($user->hasAnyRole(['SuperAdmin','Admin'])) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione','Associazione')
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

        return view('rapporti_ricavi.create', compact('associazioni','convenzioni','anno'));
    }

    /* ============================================================
     * STORE
     * ============================================================ */
    public function store(Request $request) {

        $user = Auth::user();

        $validated = $request->validate([
            'idAssociazione' => 'sometimes|exists:associazioni,idAssociazione',
            'ricavi'         => 'array',
            'ricavi.*'       => 'nullable|numeric|min:0',
            'note'           => 'array',
            'note.*'         => 'nullable|string',
        ]);

        $idAss = (int) ($validated['idAssociazione'] ?? $user->IdAssociazione);
        $anno  = (int) session('anno_riferimento', now()->year);

        $ricavi = $validated['ricavi'] ?? [];
        $note   = $validated['note']  ?? [];

        $ids = array_unique(array_merge(array_keys($ricavi), array_keys($note)));

        foreach ($ids as $idConv) {

            if (!is_numeric($idConv)) continue;

            $rimborso = $ricavi[$idConv] ?? null;
            $nota     = $note[$idConv]   ?? null;

            if ($rimborso === null && ($nota === null || trim($nota)==='')) continue;

            RapportoRicavo::upsert(
                (int)$idConv,
                (int)$idAss,
                $anno,
                (float) ($rimborso ?? 0.0),
                $nota
            );
        }

        return redirect()->route('rapporti-ricavi.index')
            ->with('success','Ricavi (e note) salvati correttamente.');
    }

    /* ============================================================
     * EDIT
     * ============================================================ */
    public function edit(int $idAssociazione) {

        $user = session()->has('impersonate')
            ? User::find(session('impersonate'))
            : auth()->user();

        if (
            !$user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])
            && (int)$user->IdAssociazione !== (int)$idAssociazione
        ) {
            abort(403);
        }

        $anno = (int) session('anno_riferimento', now()->year);

        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione','Convenzione')
            ->where('idAnno',$anno)
            ->where('idAssociazione',$idAssociazione)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get();

        // 📌 ricavi tramite MODEL (logica unica!)
        $valori = RapportoRicavo::getRicaviPerAssociazione($idAssociazione, $anno)
            ->keyBy(fn($r)=> (int)$r->idConvenzione);

        $totaleEsercizio = RapportoRicavo::getTotaleRicavi($idAssociazione, $anno);

        $associazione = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])
            ? DB::table('associazioni')->where('idAssociazione',$idAssociazione)->value('Associazione')
            : $user->associazione->Associazione;

        return view('rapporti_ricavi.edit', compact(
            'convenzioni',
            'valori',
            'idAssociazione',
            'associazione',
            'anno',
            'totaleEsercizio'
        ));
    }

    /* ============================================================
     * UPDATE
     * ============================================================ */
    public function update(Request $request, int $idAssociazione) {

        $anno = (int) session('anno_riferimento', now()->year);

        $validated = $request->validate([
            'ricavi'   => 'array',
            'ricavi.*' => 'nullable|numeric|min:0',
            'note'     => 'array',
            'note.*'   => 'nullable|string',
        ]);

        $ricavi = $validated['ricavi'] ?? [];
        $note   = $validated['note']  ?? [];

        RapportoRicavo::deleteByAssociazione($idAssociazione, $anno);

        $ids = array_unique(array_merge(array_keys($ricavi), array_keys($note)));

        foreach ($ids as $idConv) {

            if (!is_numeric($idConv)) continue;

            $rimborso = $ricavi[$idConv] ?? null;
            $nota     = $note[$idConv]   ?? null;

            if ($rimborso === null && ($nota === null || trim($nota)==='')) continue;

            RapportoRicavo::upsert(
                (int)$idConv,
                (int)$idAssociazione,
                $anno,
                (float) ($rimborso ?? 0.0),
                $nota
            );
        }

        return redirect()->route('rapporti-ricavi.index')
            ->with('success','Ricavi (e note) aggiornati.');
    }

    public function show(int $idAssociazione) {
        return $this->edit($idAssociazione);
    }

    public function destroy(int $idAssociazione) {

        $anno = (int) session('anno_riferimento', now()->year);

        RapportoRicavo::deleteByAssociazione($idAssociazione, $anno);

        return redirect()->route('rapporti-ricavi.index')
            ->with('success','Dati eliminati.');
    }
}
