<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;
use App\Models\CostoDiretto;

class DistintaImputazioneCostiController extends Controller {
    public function index(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        if ($isElevato) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            // priorità: query -> sessione -> prima disponibile
            $selectedAssoc = $request->integer('idAssociazione')
                ?? session('associazione_selezionata')
                ?? optional($associazioni->first())->idAssociazione;
        } else {
            $associazioni  = collect();
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        return view('distinta_imputazione_costi.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }
    public function getData(Request $request) {
        $user            = Auth::user();
        $anno            = (int) session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        // Associazione selezionata (stessa logica usata altrove)
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            if ($request->filled('idAssociazione')) {
                session(['associazione_selezionata' => (int) $request->integer('idAssociazione')]);
            }

            $selectedAssoc = (int) (session('associazione_selezionata') ?? optional($associazioni->first())->idAssociazione);
        } else {
            $selectedAssoc = (int) $user->IdAssociazione;
        }
        if (empty($selectedAssoc)) {
            return response()->json(['data' => [], 'convenzioni' => []]);
        }

        // ⬇️ tutto il calcolo è nel Service
        $payload = RipartizioneCostiService::distintaImputazioneData($selectedAssoc, $anno);
       
        return response()->json($payload);
    }

    /* ====== resto metodi invariati (salvaCostoDiretto / store / edit / update / destroy) ====== */

    public function salvaCostoDiretto(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno'         => 'required|integer',
            'idConvenzione'  => 'required|integer',
            'voce'           => 'required|string',
            'costo'          => 'required|numeric',
        ]);

        CostoDiretto::updateOrCreate(
            [
                'idAssociazione' => (int)$request->idAssociazione,
                'idAnno'         => (int)$request->idAnno,
                'idConvenzione'  => (int)$request->idConvenzione,
                'voce'           => trim($request->voce),
            ],
            [
                'costo'                => (float)$request->costo,
                'bilancio_consuntivo'  => (float)($request->bilancio_consuntivo ?? 0),
            ]
        );
        
        return response()->json([
            'idAssociazione' => $request->idAssociazione,
            'success' => 'Costo diretto salvato con successo.'
        ]);
    }


    public function create(Request $request, int $sezione) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();

        // associazione selezionata come altrove
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?: (int) session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        abort_if(!$idAssociazione, 422, 'Associazione non selezionata');

        // nome associazione
        $associazione = DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione');

        // convenzioni dell’anno (per select)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get();

        // voci disponibili per la sezione (da riepilogo_voci_config)
        $vociDisponibili = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1)
            ->orderBy('ordinamento')->orderBy('id')
            ->get();

        // --- Bilancio "suggerito" per voce (accumulo per DESCRIZIONE, non per idVoceConfig)
        //    costi_diretti NON ha la colonna idVoceConfig nel tuo schema attuale
        $righe = DB::table('costi_diretti')
            ->select('voce', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->groupBy('voce')
            ->get();

        $norm = fn(string $s) => preg_replace('/\s+/u', ' ', trim(mb_strtoupper($s, 'UTF-8')));

        // mappa descrizione normalizzata -> totale bilancio
        $bilancioByDesc = [];
        foreach ($righe as $r) {
            $bilancioByDesc[$norm((string)$r->voce)] = (float) $r->tot;
        }

        // convertiamo in mappa idVoceConfig -> totale bilancio, usando la descrizione della voce config
        $bilancioPerVoce = [];
        foreach ($vociDisponibili as $v) {
            $bilancioPerVoce[$v->id] = (float) ($bilancioByDesc[$norm($v->descrizione)] ?? 0);
        }

        return view('distinta_imputazione_costi.create', [
            'sezione'         => $sezione,
            'associazione'    => $associazione,
            'idAssociazione'  => $idAssociazione,
            'convenzioni'     => $convenzioni,
            'vociDisponibili' => $vociDisponibili,
            'bilancioPerVoce' => $bilancioPerVoce,
        ]);
    }


    /** Salvataggio costo diretto / bilancio consuntivo per voce e convenzione */
    public function store(Request $request) {
        $validated = $request->validate([
            'idAssociazione'      => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'              => 'required|integer',
            'idConvenzione'       => 'required|integer|exists:convenzioni,idConvenzione',
            'idSezione'           => 'required|integer',
            'idVoceConfig'        => 'required|integer|exists:riepilogo_voci_config,id', // ⬅️ obbligatoria ora
            'costo'               => 'nullable|numeric|min:0',
            'bilancio_consuntivo' => 'nullable|numeric|min:0',
        ]);

        // opzionale: tieni sincronizzato anche il campo legacy 'voce' finché esiste
        $voceDescr = DB::table('riepilogo_voci_config')->where('id', $validated['idVoceConfig'])->value('descrizione') ?? '';

        DB::table('costi_diretti')->updateOrInsert(
            [
                'idAssociazione' => (int)$validated['idAssociazione'],
                'idAnno'         => (int)$validated['idAnno'],
                'idConvenzione'  => (int)$validated['idConvenzione'],
                'idSezione'      => (int)$validated['idSezione'],
                'idVoceConfig'   => (int)$validated['idVoceConfig'],
            ],
            [
                'voce'                => $voceDescr, // transitorio
                'costo'               => (float)($validated['costo'] ?? 0),
                'bilancio_consuntivo' => (float)($validated['bilancio_consuntivo'] ?? 0),
                'updated_at'          => now(),
                'created_at'          => now(),
            ]
        );

        return redirect()->route('distinta.imputazione.index', ['idAssociazione' => $validated['idAssociazione']])
            ->with('success', 'Costo diretto / bilancio salvato con successo.');
    }
}
