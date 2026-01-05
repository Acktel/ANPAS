<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Services\RipartizioneCostiService;
use App\Models\CostoDiretto;
use App\Models\Riepilogo;
use Illuminate\Support\Facades\Cache;

class DistintaImputazioneCostiController extends Controller {
    // Sezioni dove si può editare l’Importo Totale da Bilancio Consuntivo
    private const SEZIONI_BILANCIO_EDITABILE = [5, 6, 8, 9, 10, 11];

    /** Whitelist voci editabili per sezione (ALL = tutte le voci attive della sezione) */
    private const VOCI_BILANCIO_EDIT_PER_SEZIONE = [
        5  => 'ALL',
        6  => [6007, 6008, 6009, 6010, 6011, 6012, 6013, 6014],
        8  => 'ALL',
        9  => [9002, 9003, 9006, 9007, 9008, 9009],
        10 => 'ALL',
        11 => 'ALL',
    ];

    /* ========================= INDEX ========================= */
    public function index(Request $request) {
        $anno      = (int) session('anno_riferimento', now()->year);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        $associazioni = $isElevato
            ? DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->where('idAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get()
            : collect();

        $selectedAssoc = $this->resolveAssociazione($request);

        return view('distinta_imputazione_costi.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }

    /* ========================= AJAX DATA (accordion) ========================= */
    public function getData(Request $request) {
        $anno          = (int) session('anno_riferimento', now()->year);
        $selectedAssoc = $this->resolveAssociazione($request);

        if (empty($selectedAssoc)) {
            return response()->json(['data' => [], 'convenzioni' => []]);
        }

        $payload = $this->distintaPayloadCached($selectedAssoc, $anno);
        return response()->json($payload);
    }

    /* ========================= SALVATAGGIO VELOCE (legacy singola voce) ========================= */
    public function salvaCostoDiretto(Request $request) {
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
            ]
        );

        return response()->json([
            'idAssociazione' => $validated['idAssociazione'],
            'success'        => 'Costo diretto salvato con successo.',
        ]);
    }

    /* ========================= CREATE (form aggiunta) ========================= */
    public function create(Request $request, int $sezione) {
        $anno           = (int) session('anno_riferimento', now()->year);
        $idAssociazione = $this->resolveAssociazione($request);
        abort_if(!$idAssociazione, 422, 'Associazione non selezionata');

        $associazione = DB::table('associazioni')
            ->where('idAssociazione', $idAssociazione)
            ->value('Associazione');

        $convenzioni = Riepilogo::getConvenzioniForAssAnno($idAssociazione, $anno)
            ->map(fn($r) => (object) ['idConvenzione' => $r->id, 'Convenzione' => $r->text]);

        $vociDisponibili = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1)
            ->orderBy('ordinamento')->orderBy('id')
            ->get();

        // --- Bilancio per VOCE: SOLO “globale per voce + sezione” (idVoceConfig NULL, idConvenzione NULL)
        $norm = fn(string $s) => preg_replace('/\s+/u', ' ', trim(mb_strtoupper($s, 'UTF-8')));

        $bilancioGlobalByDesc = DB::table('costi_diretti')
            ->select('voce', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('idSezione', $sezione)
            ->whereNull('idVoceConfig')
            ->whereNull('idConvenzione')
            ->groupBy('voce')
            ->get()
            ->mapWithKeys(fn($r) => [$norm((string) $r->voce) => (float) $r->tot]);

        $bilancioPerVoce = [];
        foreach ($vociDisponibili as $v) {
            $k = $norm($v->descrizione);
            $bilancioPerVoce[$v->id] = (float) ($bilancioGlobalByDesc[$k] ?? 0);
        }

        // --- Prefill diretti/sconti per voce+convenzione (schema nuovo + fallback legacy)
        $rowsNew = DB::table('costi_diretti')
            ->select('idVoceConfig', 'idConvenzione', 'costo', 'ammortamento')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('idSezione', $sezione)
            ->whereNotNull('idVoceConfig')
            ->get();

        $rowsLegacy = DB::table('costi_diretti')
            ->select('idVoceConfig', 'idConvenzione', 'costo', 'ammortamento')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNull('idSezione')
            ->whereNotNull('idVoceConfig')
            ->get();

        $esistenti = [];
        foreach ($rowsNew as $r) {
            $esistenti[(int) $r->idVoceConfig][(int) $r->idConvenzione] = [
                'costo'        => (float) ($r->costo ?? 0),
                'ammortamento' => (float) ($r->ammortamento ?? 0),
            ];
        }
        foreach ($rowsLegacy as $r) {
            if (!isset($esistenti[(int) $r->idVoceConfig][(int) $r->idConvenzione])) {
                $esistenti[(int) $r->idVoceConfig][(int) $r->idConvenzione] = [
                    'costo'        => (float) ($r->costo ?? 0),
                    'ammortamento' => (float) ($r->ammortamento ?? 0),
                ];
            }
        }

        // --- Indiretti calcolati per (voce, convenzione) in sola-lettura
        $indirettiByVoceByConv = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        return view('distinta_imputazione_costi.create', [
            'sezione'               => $sezione,
            'associazione'          => $associazione,
            'anno'                  => $anno,
            'idAssociazione'        => $idAssociazione,
            'convenzioni'           => $convenzioni,
            'vociDisponibili'       => $vociDisponibili,
            'bilancioPerVoce'       => $bilancioPerVoce,
            'esistenti'             => $esistenti,
            'indirettiByVoceByConv' => $indirettiByVoceByConv,
        ]);
    }

    /* ========================= STORE (solo costo/ammortamento) ========================= */
    public function store(Request $request) {
        $validated = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer',
            'idConvenzione'  => 'required|integer|exists:convenzioni,idConvenzione',
            'idSezione'      => 'required|integer',
            'idVoceConfig'   => 'required|integer|exists:riepilogo_voci_config,id',
            'costo'          => 'nullable|numeric|min:0',
            'ammortamento'   => 'nullable|numeric|min:0',
        ]);

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
                'voce'         => $voceDescr,
                'costo'        => (float) ($validated['costo'] ?? 0),
                'ammortamento' => (float) ($validated['ammortamento'] ?? 0),
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        return redirect()
            ->route('distinta.imputazione.index', ['idAssociazione' => $validated['idAssociazione']])
            ->with('success', 'Costo diretto salvato con successo.');
    }

    /* ========================= API: personale per convenzione ========================= */
    public function personalePerConvenzione(Request $request) {
        $anno          = (int) session('anno_riferimento', now()->year);
        $selectedAssoc = $this->resolveAssociazione($request);
        abort_if(!$selectedAssoc, 422, 'Associazione non selezionata');

        $conv    = RipartizioneCostiService::convenzioni($selectedAssoc, $anno);
        $convIds = array_keys($conv);

        [$importiPerConv, $totale] = RipartizioneCostiService::importiAutistiBarellieriByConvenzione(
            $selectedAssoc,
            $anno,
            $convIds
        );

        return response()->json([
            'convenzioni' => $conv,
            'per_conv'    => $importiPerConv,
            'totale'      => $totale,
        ]);
    }

    /* ========================= EDIT SEZIONE (solo diretti/ammortamenti) ========================= */
    public function editSezione(Request $request, int $sezione) {
        $anno           = (int) session('anno_riferimento', now()->year);
        $idAssociazione = $this->resolveAssociazione($request);
        abort_if(!$idAssociazione, 422, 'Associazione non selezionata');

        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get(['idConvenzione', 'Convenzione']);

        $voci = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('attivo', 1)
            ->where('idTipologiaRiepilogo', $sezione)
            ->orderBy('ordinamento')->orderBy('id')
            ->get();

        $diretti = DB::table('costi_diretti')
            ->select('idVoceConfig', 'idConvenzione', 'costo', 'ammortamento')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNotNull('idVoceConfig')
            ->get()
            ->groupBy('idVoceConfig');

        return view('distinta_imputazione_costi.edit_sezione', [
            'sezione'        => $sezione,
            'anno'           => $anno,
            'idAssociazione' => $idAssociazione,
            'convenzioni'    => $convenzioni,
            'voci'           => $voci,
            'diretti'        => $diretti,
        ]);
    }

    public function updateSezione(Request $request, int $sezione) {
        $validated = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer',
            'righe'          => 'required|array', // righe[voceId][conv][idConv][costo|ammortamento]
        ]);

        $idAssociazione = (int) $validated['idAssociazione'];
        $anno           = (int) $validated['idAnno'];
        $righe          = $validated['righe'];

        $descrById = DB::table('riepilogo_voci_config')
            ->whereIn('id', array_keys($righe))
            ->pluck('descrizione', 'id');

        foreach ($righe as $idVoce => $row) {
            $idVoce = (int) $idVoce;
            $descr  = (string) ($descrById[$idVoce] ?? '');

            // SOLO diretti/ammortamento per convenzione
            foreach (($row['conv'] ?? []) as $idConv => $vals) {
                $costo = isset($vals['costo']) ? (float) $vals['costo'] : 0.0;
                $amm   = isset($vals['ammortamento']) ? (float) $vals['ammortamento'] : 0.0;

                DB::table('costi_diretti')->updateOrInsert(
                    [
                        'idAssociazione' => $idAssociazione,
                        'idAnno'         => $anno,
                        'idVoceConfig'   => $idVoce,
                        'idConvenzione'  => (int) $idConv,
                    ],
                    [
                        'voce'           => $descr,
                        'costo'          => $costo,
                        'ammortamento'   => $amm,
                        'updated_at'     => now(),
                        'created_at'     => now(),
                    ]
                );
            }
        }

        return redirect()
            ->route('distinta.imputazione.index', ['idAssociazione' => $idAssociazione])
            ->with('success', 'Sezione aggiornata correttamente.');
    }

    /* ========================= EDIT “Importo Totale da Bilancio Consuntivo” ========================= */
    public function editBilancioSezione(Request $request, int $sezione) {
        abort_unless(in_array($sezione, self::SEZIONI_BILANCIO_EDITABILE, true), 404);

        $anno      = (int) session('anno_riferimento', now()->year);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        $idAssociazione = $isElevato
            ? (int) ($request->integer('idAssociazione') ?? session('associazione_selezionata') ?? $user->IdAssociazione)
            : (int) $user->IdAssociazione;

        abort_if(!$idAssociazione, 422, 'Associazione non selezionata');

        $whitelist = self::VOCI_BILANCIO_EDIT_PER_SEZIONE[$sezione] ?? null;

        $q = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1);

        if (is_array($whitelist)) {
            $q->whereIn('id', $whitelist);
        }

        $voci = $q->orderBy('ordinamento')->orderBy('id')->get();

        // Leggo solo il bilancio “globale per voce + sezione”
        $righeBil = DB::table('costi_diretti')
            ->select('voce', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('idSezione', $sezione)
            ->whereNull('idVoceConfig')
            ->whereNull('idConvenzione')
            ->groupBy('voce')
            ->get()
            ->keyBy(function ($r) {
                return mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $r->voce)), 'UTF-8');
            });

        $norm = fn(string $s) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($s)), 'UTF-8');

        $righe = [];
        foreach ($voci as $v) {
            $key = $norm($v->descrizione);
            $righe[] = [
                'idVoceConfig' => (int) $v->id,
                'descrizione'  => $v->descrizione,
                'bilancio'     => (float) ($righeBil[$key]->tot ?? 0),
            ];
        }

        $nomiSez = [
            5  => 'Costi gestione struttura',
            8  => 'Costi amministrativi',
            10 => 'Beni strumentali < 516 €'
        ];

        return view('distinta_imputazione_costi.edit_bilancio', [
            'sezione'        => $sezione,
            'sezioneLabel'   => $nomiSez[$sezione] ?? "Sezione $sezione",
            'anno'           => $anno,
            'idAssociazione' => $idAssociazione,
            'righe'          => $righe,
        ]);
    }

    public function updateBilancioSezione(Request $request, int $sezione) {
        abort_unless(in_array($sezione, self::SEZIONI_BILANCIO_EDITABILE, true), 404);

        $data = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer',
            'bilancio'       => 'required|array',        // [idVoceConfig => valore]
            'bilancio.*'     => 'nullable|numeric|min:0',
        ]);

        $idAssociazione = (int) $data['idAssociazione'];
        $anno           = (int) $data['idAnno'];
        $valori         = $data['bilancio'] ?? [];

        $whitelist = self::VOCI_BILANCIO_EDIT_PER_SEZIONE[$sezione] ?? null;

        $q = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1);

        if (is_array($whitelist)) {
            $q->whereIn('id', $whitelist);
        }

        $voci = $q->get()->keyBy('id');

        if (is_array($whitelist)) {
            $valori = array_intersect_key($valori, array_flip($whitelist));
        }

        DB::beginTransaction();
        try {
            foreach ($valori as $idVoce => $val) {
                $idVoce = (int) $idVoce;
                if (!isset($voci[$idVoce])) {
                    continue; // voce non ammessa
                }

                $descr = (string) $voci[$idVoce]->descrizione;
                $val   = (float) ($val ?? 0);

                DB::table('costi_diretti')->updateOrInsert(
                    [
                        'idAssociazione' => $idAssociazione,
                        'idAnno'         => $anno,
                        'idSezione'      => $sezione,
                        'idVoceConfig'   => null,    // GLOBALE per VOCE+SEZIONE
                        'idConvenzione'  => null,
                        'voce'           => $descr,
                    ],
                    [
                        'costo'               => 0,
                        'ammortamento'        => 0,
                        'bilancio_consuntivo' => $val,
                        'updated_at'          => now(),
                        'created_at'          => now(),
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Errore nel salvataggio: ' . $e->getMessage())->withInput();
        }

        return redirect()
            ->route('distinta.imputazione.index', ['idAssociazione' => $idAssociazione])
            ->with('success', 'Importi da bilancio aggiornati correttamente.');
    }

    /* ========================= Helper: risolve l’associazione corrente ========================= */
    private function resolveAssociazione(Request $request): ?int {
        $user            = Auth::user();
        $associazioni    = collect();

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('IdAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('IdAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            $selectedAssoc = $request->get('idAssociazione')
                ?? ($associazioni->first()->IdAssociazione ?? null);
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }

        if ($request->has('idAssociazione')) {
            session(['associazione_selezionata' => $request->idAssociazione]);
        }

        $selectedAssoc = session('associazione_selezionata') ?? $selectedAssoc ?? $user->IdAssociazione;

        return $selectedAssoc ? (int) $selectedAssoc : null;
    }

    /* ========================= STORE BULK (diretti/ammortamenti) ========================= */
    public function storeBulk(Request $request) {
        $data = $request->validate([
            'idAssociazione'        => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'                => 'required|integer',
            'idSezione'             => 'required|integer',
            'idConvenzione'         => 'required|integer|exists:convenzioni,idConvenzione',
            'righe'                 => 'required|array', // righe[idVoceConfig][costo|ammortamento]
            'righe.*.costo'         => 'nullable|numeric|min:0',
            'righe.*.ammortamento'  => 'nullable|numeric|min:0',
        ]);

        $idAssociazione = (int) $data['idAssociazione'];
        $anno           = (int) $data['idAnno'];
        $sezione        = (int) $data['idSezione'];
        $idConvenzione  = (int) $data['idConvenzione'];
        $righe          = $data['righe'];

        $descrById = DB::table('riepilogo_voci_config')
            ->whereIn('id', array_keys($righe))
            ->pluck('descrizione', 'id');

        DB::beginTransaction();
        try {
            foreach ($righe as $idVoce => $vals) {
                $idVoce = (int) $idVoce;
                $descr  = (string) ($descrById[$idVoce] ?? '');

                $costo = isset($vals['costo']) ? (float) $vals['costo'] : 0.0;
                $amm   = isset($vals['ammortamento']) ? (float) $vals['ammortamento'] : 0.0;

                DB::table('costi_diretti')->updateOrInsert(
                    [
                        'idAssociazione' => $idAssociazione,
                        'idAnno'         => $anno,
                        'idSezione'      => $sezione,
                        'idConvenzione'  => $idConvenzione,
                        'idVoceConfig'   => $idVoce,
                    ],
                    [
                        'voce'           => $descr,
                        'costo'          => $costo,
                        'ammortamento'   => $amm,
                        'updated_at'     => now(),
                        'created_at'     => now(),
                    ]
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Errore nel salvataggio: ' . $e->getMessage())->withInput();
        }

        return redirect()
            ->route('distinta.imputazione.index', ['idAssociazione' => $idAssociazione])
            ->with('success', 'Costi diretti aggiornati correttamente.');
    }

    private function distintaPayloadCached(int $idAssociazione, int $anno): array {
        $key = "distinta_imputazione_payload:{$idAssociazione}:{$anno}";

        // 10 minuti: regola tu. Se hai dati che cambiano spesso, metti 60-120s.
        return Cache::remember($key, 600, function () use ($idAssociazione, $anno) {
            return RipartizioneCostiService::distintaImputazioneData($idAssociazione, $anno);
        });
    }

    public function summary(Request $request)
{
    $anno          = (int) session('anno_riferimento', now()->year);
    $selectedAssoc = $this->resolveAssociazione($request);

    if (empty($selectedAssoc)) {
        return response()->json(['ok' => false, 'sezioni' => [], 'totale' => [], 'convenzioni' => []]);
    }

    $payload = $this->distintaPayloadCached($selectedAssoc, $anno);

    $rows = $payload['data'] ?? [];
    $convenzioni = $payload['convenzioni'] ?? [];

    $totBySez = [];
    $grand = ['bilancio' => 0.0, 'diretta' => 0.0, 'totale' => 0.0];

    foreach ($rows as $r) {
        $sez = (int) ($r['sezione_id'] ?? $r['sezione'] ?? $r['idSezione'] ?? 0);
        if ($sez === 0) continue;

        if (!isset($totBySez[$sez])) {
            $totBySez[$sez] = ['bilancio' => 0.0, 'diretta' => 0.0, 'totale' => 0.0];
        }

        $b = (float) ($r['bilancio'] ?? 0);
        $d = (float) ($r['diretta'] ?? 0);
        $t = (float) ($r['totale']  ?? 0);

        $totBySez[$sez]['bilancio'] += $b;
        $totBySez[$sez]['diretta']  += $d;
        $totBySez[$sez]['totale']   += $t;

        $grand['bilancio'] += $b;
        $grand['diretta']  += $d;
        $grand['totale']   += $t;
    }

    // arrotondo a 2 decimali
    $round2 = fn($x) => round((float)$x, 2);
    foreach ($totBySez as $k => $v) {
        $totBySez[$k] = [
            'bilancio' => $round2($v['bilancio']),
            'diretta'  => $round2($v['diretta']),
            'totale'   => $round2($v['totale']),
        ];
    }
    $grand = [
        'bilancio' => $round2($grand['bilancio']),
        'diretta'  => $round2($grand['diretta']),
        'totale'   => $round2($grand['totale']),
    ];

    return response()->json([
        'ok'         => true,
        'convenzioni'=> $convenzioni,
        'sezioni'    => $totBySez,
        'totale'     => $grand,
    ]);
}

public function getDataSezione(Request $request, int $sezione)
{
    $anno          = (int) session('anno_riferimento', now()->year);
    $selectedAssoc = $this->resolveAssociazione($request);

    if (empty($selectedAssoc)) {
        return response()->json(['ok' => false, 'data' => [], 'convenzioni' => []]);
    }

    $payload = $this->distintaPayloadCached($selectedAssoc, $anno);

    $rows = $payload['data'] ?? [];
    $convenzioni = $payload['convenzioni'] ?? [];

    $filtered = array_values(array_filter($rows, function ($r) use ($sezione) {
        $s = (int) ($r['sezione_id'] ?? $r['sezione'] ?? $r['idSezione'] ?? 0);
        return $s === (int)$sezione;
    }));

    return response()->json([
        'ok'          => true,
        'sezione'     => $sezione,
        'convenzioni' => $convenzioni,
        'data'        => $filtered,
    ]);
}


}
