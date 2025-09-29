<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Services\RipartizioneCostiService;
use App\Models\CostoDiretto;
use App\Models\Riepilogo;

class DistintaImputazioneCostiController extends Controller
{
    /* =========================
       INDEX
       ========================= */
    public function index(Request $request)
    {
        $anno      = (int) session('anno_riferimento', now()->year);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        if ($isElevato) {
            // elenco associazioni per select (attive, esclusa la 1)
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            // priorità: query -> session -> prima disponibile
            $selectedAssoc = $request->integer('idAssociazione')
                ?? session('associazione_selezionata')
                ?? optional($associazioni->first())->idAssociazione;
        } else {
            $associazioni  = collect();
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        return view('distinta_imputazione_costi.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }

    /* =========================
       AJAX DATA (accordion)
       ========================= */
    public function getData(Request $request)
    {
        $user            = Auth::user();
        $anno            = (int) session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating) {
            // memorizza eventuale associazione scelta
            if ($request->filled('idAssociazione')) {
                session(['associazione_selezionata' => (int) $request->integer('idAssociazione')]);
            }

            // prendi la selezionata o prima disponibile
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            $selectedAssoc = (int) (session('associazione_selezionata') ?? optional($associazioni->first())->idAssociazione);
        } else {
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        if (empty($selectedAssoc)) {
            return response()->json(['data' => [], 'convenzioni' => []]);
        }

        // Tutto il calcolo è nel Service
        $payload = RipartizioneCostiService::distintaImputazioneData($selectedAssoc, $anno);

        return response()->json($payload);
    }

    /* =========================
       SALVATAGGIO VELOCE (AJAX singola voce legacy)
       — niente sconto, niente bilancio scritto
       ========================= */
    public function salvaCostoDiretto(Request $request)
    {
        $validated = $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno'         => 'required|integer',
            'idConvenzione'  => 'required|integer',
            'voce'           => 'required|string',
            'costo'          => 'required|numeric|min:0',
        ]);

        CostoDiretto::updateOrCreate(
            [
                'idAssociazione' => (int) $validated['idAssociazione'],
                'idAnno'         => (int) $validated['idAnno'],
                'idConvenzione'  => (int) $validated['idConvenzione'],
                'voce'           => trim($validated['voce']),
            ],
            [
                'costo' => (float) $validated['costo'],
                // NIENTE bilancio_consuntivo qui
                // NIENTE sconto qui
            ]
        );

        return response()->json([
            'idAssociazione' => $validated['idAssociazione'],
            'success'        => 'Costo diretto salvato con successo.',
        ]);
    }

    /* =========================
       CREATE (form aggiunta)
       ========================= */
    public function create(Request $request, int $sezione)
    {
        $anno      = (int) session('anno_riferimento', now()->year);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // Associazione selezionata come altrove
        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?: (int) session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        abort_if(!$idAssociazione, 422, 'Associazione non selezionata');

        // Nome associazione
        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $idAssociazione)
            ->value('Associazione');

        // Convenzioni per anno/associazione (usa helper del Riepilogo)
        // Ritorna oggetti { id, text } => li mappiamo per la view
        $convenzioni = Riepilogo::getConvenzioniForAssAnno($idAssociazione, $anno)
            ->map(fn($r) => (object) ['idConvenzione' => $r->id, 'Convenzione' => $r->text]);

        // Voci disponibili per la sezione
        $vociDisponibili = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1)
            ->orderBy('ordinamento')
            ->orderBy('id')
            ->get();

        /**
         * BILANCIO “SUGGERITO” PER VOCE
         * Nota: il bilancio NON viene scritto in store/salva.
         * Qui lo mostriamo soltanto (se proviene da storici/import/altre fonti).
         */
        $righe = DB::table('costi_diretti')
            ->select('voce', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->groupBy('voce')
            ->get();

        $norm = fn(string $s) => preg_replace('/\s+/u', ' ', trim(mb_strtoupper($s, 'UTF-8')));
        $bilancioByDesc = [];
        foreach ($righe as $r) {
            $bilancioByDesc[$norm((string) $r->voce)] = (float) $r->tot;
        }

        // mappa idVoceConfig -> totale bilancio (display-only)
        $bilancioPerVoce = [];
        foreach ($vociDisponibili as $v) {
            $bilancioPerVoce[$v->id] = (float) ($bilancioByDesc[$norm($v->descrizione)] ?? 0);
        }

        // Pre-riempimento: esistenti per voce -> convenzione -> {costo, ammortamento}
        $esistenti = DB::table('costi_diretti')
            ->select('idVoceConfig', 'idConvenzione', 'costo', 'ammortamento')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNotNull('idVoceConfig')
            ->get()
            ->groupBy('idVoceConfig')
            ->map(function ($rows) {
                $m = [];
                foreach ($rows as $r) {
                    $m[(int) $r->idConvenzione] = [
                        'costo'        => (float) ($r->costo ?? 0),
                        'ammortamento' => (float) ($r->ammortamento ?? 0),
                    ];
                }
                return $m;
            })
            ->toArray();

        return view('distinta_imputazione_costi.create', [
            'sezione'         => $sezione,
            'associazione'    => $associazione,
            'idAssociazione'  => $idAssociazione,
            'convenzioni'     => $convenzioni,
            'vociDisponibili' => $vociDisponibili,
            'bilancioPerVoce' => $bilancioPerVoce,  // solo display
            'esistenti'       => $esistenti,        // prefill costo/ammortamento
        ]);
    }

    /* =========================
       STORE (salvataggio form)
       — salva SOLO costo/ammortamento. Niente bilancio.
       ========================= */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer',
            'idConvenzione'  => 'required|integer|exists:convenzioni,idConvenzione',
            'idSezione'      => 'required|integer',
            'idVoceConfig'   => 'required|integer|exists:riepilogo_voci_config,id',
            'costo'          => 'nullable|numeric|min:0',
            'ammortamento'   => 'nullable|numeric|min:0',
            // nessun bilancio_consuntivo qui
        ]);

        // Descrizione voce per compat legacy
        $voceDescr = Riepilogo::getVoceDescrizione((int) $validated['idVoceConfig']) ?? '';

        DB::table('costi_diretti')->updateOrInsert(
            [
                'idAssociazione' => (int) $validated['idAssociazione'],
                'idAnno'         => (int) $validated['idAnno'],
                'idConvenzione'  => (int) $validated['idConvenzione'],
                'idSezione'      => (int) $validated['idSezione'],
                'idVoceConfig'   => (int) $validated['idVoceConfig'],
            ],
            [
                'voce'         => $voceDescr, // compat legacy
                'costo'        => (float) ($validated['costo'] ?? 0),
                'ammortamento' => (float) ($validated['ammortamento'] ?? 0),
                // niente bilancio_consuntivo qui
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        return redirect()
            ->route('distinta.imputazione.index', ['idAssociazione' => $validated['idAssociazione']])
            ->with('success', 'Costo diretto salvato con successo.');
    }

    /* =========================
       API: personale per convenzione
       ========================= */
    public function personalePerConvenzione(Request $request)
    {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        // stessa logica selezione associazione
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate')) {
            $selectedAssoc = (int) ($request->integer('idAssociazione') ?: session('associazione_selezionata'));
        } else {
            $selectedAssoc = (int) $user->IdAssociazione;
        }
        abort_if(!$selectedAssoc, 422, 'Associazione non selezionata');

        // Convenzioni (id => nome) nell’ordine stabile
        $conv    = RipartizioneCostiService::convenzioni($selectedAssoc, $anno);
        $convIds = array_keys($conv);

        // 6001 = Autisti & Barellieri (importi assoluti per convenzione)
        [$importiPerConv, $totale] = RipartizioneCostiService::importiAutistiBarellieriByConvenzione(
            $selectedAssoc,
            $anno,
            $convIds
        );

        return response()->json([
            'convenzioni' => $conv,           // [idConv => Nome]
            'per_conv'    => $importiPerConv, // [idConv => importo]
            'totale'      => $totale,         // somma complessiva
        ]);
    }
}
