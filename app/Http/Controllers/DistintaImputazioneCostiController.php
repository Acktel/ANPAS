<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Services\RipartizioneCostiService;
use App\Models\CostoDiretto;
use App\Models\Riepilogo;

class DistintaImputazioneCostiController extends Controller {
    /** Sezioni dove si può editare l’Importo Totale da Bilancio Consuntivo (giallo) */
    /** Sezioni dove si può editare l’Importo Totale da Bilancio Consuntivo */
    private const SEZIONI_BILANCIO_EDITABILE = [5, 8, 10, 11];

    /** Whitelist voci editabili per sezione (ALL = tutte le voci attive della sezione) */
    private const VOCI_BILANCIO_EDIT_PER_SEZIONE = [
        5  => 'ALL',
        8  => 'ALL',
        10 => [6007,6008,6009,6010,6011,6012,6013,6014],
        11 => [9002,9003,9006,9007,9008,9009],
    ];
    /* =========================
       INDEX
       ========================= */
    public function index(Request $request) {
        $anno      = (int) session('anno_riferimento', now()->year);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        // elenco associazioni per select se elevato
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

    /* =========================
       AJAX DATA (accordion)
       ========================= */
    public function getData(Request $request) {
        $anno           = (int) session('anno_riferimento', now()->year);
        $selectedAssoc  = $this->resolveAssociazione($request);

        if (empty($selectedAssoc)) {
            return response()->json(['data' => [], 'convenzioni' => []]);
        }

        $payload = RipartizioneCostiService::distintaImputazioneData($selectedAssoc, $anno);
        return response()->json($payload);
    }

    /* =========================
       SALVATAGGIO VELOCE (AJAX singola voce legacy)
       — niente sconto, niente bilancio scritto
       ========================= */
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

    /* =========================
       CREATE (form aggiunta)
       ========================= */
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

        // --- Bilancio per VOCE (nuovo schema + fallback legacy), filtrato per sezione ---
        $righeBilDescNew = DB::table('costi_diretti')
            ->select('voce', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('idSezione', $sezione)
            ->whereNull('idVoceConfig')
            ->groupBy('voce')
            ->get();

        $bilancioByIdNew = DB::table('costi_diretti')
            ->select('idVoceConfig', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('idSezione', $sezione)
            ->whereNotNull('idVoceConfig')
            ->groupBy('idVoceConfig')
            ->pluck('tot', 'idVoceConfig');

        $righeBilDescLegacy = DB::table('costi_diretti')
            ->select('voce', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNull('idVoceConfig')
            ->groupBy('voce')
            ->get();

        $bilancioByIdLegacy = DB::table('costi_diretti')
            ->select('idVoceConfig', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNotNull('idVoceConfig')
            ->groupBy('idVoceConfig')
            ->pluck('tot', 'idVoceConfig');

        $norm = fn(string $s) => preg_replace('/\s+/u', ' ', trim(mb_strtoupper($s, 'UTF-8')));
        $byDescNew = [];
        foreach ($righeBilDescNew as $r) $byDescNew[$norm((string)$r->voce)] = (float)$r->tot;
        $byDescLegacy = [];
        foreach ($righeBilDescLegacy as $r) $byDescLegacy[$norm((string)$r->voce)] = (float)$r->tot;

        $bilancioPerVoce = [];
        foreach ($vociDisponibili as $v) {
            $k = $norm($v->descrizione);
            $val = $byDescNew[$k] ?? ($bilancioByIdNew[$v->id] ?? ($byDescLegacy[$k] ?? ($bilancioByIdLegacy[$v->id] ?? 0)));
            $bilancioPerVoce[$v->id] = (float)$val;
        }

        // --- Prefill diretti/sconti per voce+convenzione (nuovo + fallback legacy) ---
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
            $esistenti[(int)$r->idVoceConfig][(int)$r->idConvenzione] = [
                'costo'        => (float)($r->costo ?? 0),
                'ammortamento' => (float)($r->ammortamento ?? 0),
            ];
        }
        foreach ($rowsLegacy as $r) {
            if (!isset($esistenti[(int)$r->idVoceConfig][(int)$r->idConvenzione])) {
                $esistenti[(int)$r->idVoceConfig][(int)$r->idConvenzione] = [
                    'costo'        => (float)($r->costo ?? 0),
                    'ammortamento' => (float)($r->ammortamento ?? 0),
                ];
            }
        }

        // --- Indiretti calcolati per (voce, convenzione) in sola-lettura ---
        $indirettiByVoceByConv = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        return view('distinta_imputazione_costi.create', [
            'sezione'                => $sezione,
            'associazione'           => $associazione,
            'anno'                   => $anno,
            'idAssociazione'         => $idAssociazione,
            'convenzioni'            => $convenzioni,
            'vociDisponibili'        => $vociDisponibili,
            'bilancioPerVoce'        => $bilancioPerVoce,
            'esistenti'              => $esistenti,
            'indirettiByVoceByConv'  => $indirettiByVoceByConv, // << nuovo
        ]);
    }


    /* =========================
       STORE (solo costo/ammortamento)
       ========================= */
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

    /* =========================
       API: personale per convenzione
       ========================= */
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

    /* =========================
       EDIT SEZIONE (lista voci con diretti/sconti + bilancio per riga)
       ========================= */
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

        $bilanci = DB::table('costi_diretti')
            ->select('idVoceConfig', DB::raw('SUM(bilancio_consuntivo) as bil'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNotNull('idVoceConfig')
            ->groupBy('idVoceConfig')
            ->pluck('bil', 'idVoceConfig');

        return view('distinta_imputazione_costi.edit_sezione', [
            'sezione'        => $sezione,
            'anno'           => $anno,
            'idAssociazione' => $idAssociazione,
            'convenzioni'    => $convenzioni,
            'voci'           => $voci,
            'diretti'        => $diretti,
            'bilanci'        => $bilanci,
        ]);
    }

    public function updateSezione(Request $request, int $sezione) {
        $validated = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer',
            'righe'          => 'required|array', // righe[voceId][bilancio], righe[voceId][conv][idConv][costo|ammortamento]
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

            // 1) Bilancio consuntivo per voce (riga "globale": idConvenzione NULL)
            $bil = isset($row['bilancio']) ? (float) $row['bilancio'] : 0.0;

            DB::table('costi_diretti')->updateOrInsert(
                [
                    'idAssociazione' => $idAssociazione,
                    'idAnno'         => $anno,
                    'idVoceConfig'   => $idVoce,
                    'idConvenzione'  => null,
                ],
                [
                    'voce'                 => $descr,
                    'costo'                => 0,
                    'ammortamento'         => 0,
                    'bilancio_consuntivo'  => $bil,
                    'updated_at'           => now(),
                    'created_at'           => now(),
                ]
            );

            // 2) Diretti/Sconto per convenzione
            $perConv = $row['conv'] ?? [];
            foreach ($perConv as $idConv => $vals) {
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

    /* =========================
       Helper: risolve l’associazione corrente
       ========================= */
    private function resolveAssociazione(Request $request): ?int {
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        // priorità: query -> sessione -> utente (se non elevato) -> prima disponibile (se elevato)
        $id = $request->integer('idAssociazione')
            ?? session('associazione_selezionata')
            ?? (!$isElevato ? $user->IdAssociazione : null);

        if ($isElevato && empty($id)) {
            $id = DB::table('associazioni')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->value('idAssociazione');
        }

        if (!empty($id)) {
            session(['associazione_selezionata' => (int) $id]);
            return (int) $id;
        }

        return null;
    }

    public function storeBulk(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer',
            'idSezione'      => 'required|integer',
            'idConvenzione'  => 'required|integer|exists:convenzioni,idConvenzione',
            'righe'          => 'required|array',                 // righe[idVoceConfig][costo|ammortamento]
            'righe.*.costo'        => 'nullable|numeric|min:0',
            'righe.*.ammortamento' => 'nullable|numeric|min:0',
        ]);

        $idAssociazione = (int) $data['idAssociazione'];
        $anno           = (int) $data['idAnno'];
        $sezione        = (int) $data['idSezione'];
        $idConvenzione  = (int) $data['idConvenzione'];
        $righe          = $data['righe'];

        // mappa idVoce -> descrizione (per compatibilità legacy sul campo "voce")
        $descrById = DB::table('riepilogo_voci_config')
            ->whereIn('id', array_keys($righe))
            ->pluck('descrizione', 'id');

        DB::beginTransaction();
        try {
            foreach ($righe as $idVoce => $vals) {
                $idVoce = (int) $idVoce;
                $descr  = (string) ($descrById[$idVoce] ?? '');

                $costo = isset($vals['costo']) ? (float)$vals['costo'] : 0.0;
                $amm   = isset($vals['ammortamento']) ? (float)$vals['ammortamento'] : 0.0;

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

    private static function voceBilancioPerId(int $idVoce): bool {
        return in_array($idVoce, self::VOCI_BILANCIO_EDITABILE_IDS, true);
    }

    /* =========================
       EDIT “Importo Totale da Bilancio Consuntivo” 
       ========================= */
    public function editBilancioSezione(Request $request, int $sezione)
    {
        // Consenti 5,8,10,11 ma filtra 10/11 alle sole voci ammesse
        abort_unless(in_array($sezione, self::SEZIONI_BILANCIO_EDITABILE, true), 404);

        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        $idAssociazione = $isElevato
            ? ((int) ($request->integer('idAssociazione') ?? session('associazione_selezionata') ?? $user->IdAssociazione))
            : (int) $user->IdAssociazione;

        abort_if(!$idAssociazione, 422, 'Associazione non selezionata');

        $whitelist = self::VOCI_BILANCIO_EDIT_PER_SEZIONE[$sezione] ?? null;

        // Voci attive (eventualmente filtrate per ID)
        $q = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1);

        if (is_array($whitelist)) {
            $q->whereIn('id', $whitelist);
        }

        $voci = $q->orderBy('ordinamento')->orderBy('id')->get();

        // Bilanci già inseriti per QUESTA SEZIONE (schema "globale per voce": idVoceConfig NULL)
        $righeBil = DB::table('costi_diretti')
            ->select('voce', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('idSezione', $sezione)
            ->whereNull('idVoceConfig')
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

        $nomiSez = [5 => 'Costi gestione struttura', 8 => 'Costi amministrativi', 10 => 'Beni strumentali < 516 €', 11 => 'Altri costi'];

        return view('distinta_imputazione_costi.edit_bilancio', [
            'sezione'        => $sezione,
            'sezioneLabel'   => $nomiSez[$sezione] ?? "Sezione $sezione",
            'anno'           => $anno,
            'idAssociazione' => $idAssociazione,
            'righe'          => $righe,
        ]);
    }

    public function updateBilancioSezione(Request $request, int $sezione)
    {
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

        // Voci attive (eventualmente filtrate per ID)
        $q = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1);

        if (is_array($whitelist)) {
            $q->whereIn('id', $whitelist);
        }

        $voci = $q->get()->keyBy('id');

        // Se arrivano ID non ammessi, li scarto qui
        if (is_array($whitelist)) {
            $valori = array_intersect_key($valori, array_flip($whitelist));
        }

        DB::beginTransaction();
        try {
            foreach ($valori as $idVoce => $val) {
                $idVoce = (int) $idVoce;
                if (!isset($voci[$idVoce])) {
                    // voce non ammessa per questa sezione: skip
                    continue;
                }

                $descr = (string) $voci[$idVoce]->descrizione;
                $val   = (float) ($val ?? 0);

                DB::table('costi_diretti')->updateOrInsert(
                    [
                        'idAssociazione' => $idAssociazione,
                        'idAnno'         => $anno,
                        'idSezione'      => $sezione,
                        'idVoceConfig'   => null,     // globale per VOCE (per sezione)
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

}
