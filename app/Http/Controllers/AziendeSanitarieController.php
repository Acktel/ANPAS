<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

use App\Models\AziendaSanitaria;
use App\Models\LottoAziendaSanitaria;
use App\Models\Cities;
use App\Models\Convenzione;

class AziendeSanitarieController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    /** Utility: idAnno dal session('anno_riferimento'). */
    private function idAnnoCorrente(): int {
        $anno = (int) session('anno_riferimento', now()->year);
        return AziendaSanitaria::resolveIdAnno($anno);
    }

    public function index(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // =====================================================================================
        // RUOLI ELEVATI → VEDONO TUTTO, SENZA SELECT, SENZA FILTRI
        // =====================================================================================
        if ($isElevato) {

            $associazioni = collect();
            $selectedAssoc = null;

            $convenzioni = collect();
            $selectedConv = 0;

            $aziende = AziendaSanitaria::getAllSenzaFiltri($anno);

            $useAjax = false;
            $showDuplica = false; // gli elevati non usano duplicazione da convenzione
            $canDelete = $user->can('manage-all-associations');

            return view('aziende_sanitarie.index', compact(
                'anno',
                'isElevato',
                'associazioni',
                'selectedAssoc',
                'convenzioni',
                'selectedConv',
                'aziende',
                'useAjax',
                'showDuplica',
                'canDelete'
            ));
        }

        // =====================================================================================
        // UTENTI NORMALI → FILTRI PER ASSOCIAZIONE E CONVENZIONE
        // =====================================================================================
        $associazioni = collect();
        $selectedAssoc = (int) $user->IdAssociazione;

        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAssociazione', $selectedAssoc)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->get();

        if ($convenzioni->isEmpty()) {
            $selectedConv = null;
            $aziende = [];
        } else {
            $selectedConv = session('convenzione_selezionata') ?? (int) $convenzioni->first()->idConvenzione;
            session(['convenzione_selezionata' => $selectedConv]);

            $aziende = AziendaSanitaria::getAllWithConvenzioni($selectedConv);
        }

        $useAjax = true;

        // =====================================================================================
        // MOSTRA messaggio DUPLICA solo quando:
        // - non ci sono aziende nell’anno corrente
        // - esistono aziende nell’anno precedente
        // =====================================================================================
        $showDuplica = empty($aziende) && AziendaSanitaria::existsForAnno($anno - 1);
        $canDelete = $user->can('manage-own-associations');

        return view('aziende_sanitarie.index', compact(
            'anno',
            'isElevato',
            'associazioni',
            'selectedAssoc',
            'convenzioni',
            'selectedConv',
            'aziende',
            'useAjax',
            'showDuplica',
            'canDelete'
        ));
    }

    public function getData(Request $request): JsonResponse {

        // GET ha priorità
        $idConvenzione = $request->input('idConvenzione');

        if ($idConvenzione !== null && $idConvenzione !== '') {
            $idConvenzione = (int) $idConvenzione;
            session(['convenzione_selezionata' => $idConvenzione]);
        } else {
            // fallback: session
            $idConvenzione = session('convenzione_selezionata');
        }

        // ======================================================
        // CARICA AZIENDE FILTRATE
        // ======================================================
        if ($idConvenzione == null) {
            $data = [];
        } else {
            $data = AziendaSanitaria::getAllWithConvenzioni(
                $idConvenzione ?: null
            );
        }

        return response()->json(['data' => $data]);
    }


    public function create() {
        $anni = DB::table('anni')->orderBy('anno', 'desc')->get();

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAnno', $this->idAnnoCorrente())
            ->orderBy('Convenzione')
            ->get();

        $lotti  = collect();
        $cities = Cities::getAll();
        $caps   = Cities::getAllWithCap(); // cap, denominazione_ita, sigla_provincia, ...

        $useAjax = true; // serve per JS lato frontend (select dinamiche ecc)

        return view('aziende_sanitarie.create', compact(
            'anni',
            'associazioni',
            'convenzioni',
            'lotti',
            'cities',
            'caps',
            'useAjax'
        ));
    }


    public function store(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // -------------------------------
        // VALIDAZIONE BASE
        // -------------------------------
        $rules = [
            'Nome'            => 'required|string|max:150',
            'indirizzo_via'    => 'nullable|string|max:255',
            'indirizzo_civico' => 'nullable|string|max:20',
            'provincia'       => 'nullable|string|exists:ter_cities,sigla_provincia',
            'citta'           => 'nullable|string|max:100',
            'cap'             => ['nullable', 'string', 'size:5'],
            'mail'            => 'nullable|email|max:150',
            'note'            => 'nullable|string',

            'lotti_presenti'  => 'required|in:0,1',
            'lotti'           => 'nullable|array',
            'lotti.*.nomeLotto'   => 'nullable|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',
        ];

        if ($request->filled('provincia') && $request->filled('citta')) {
            $rules['cap'][] = Rule::exists('ter_cities_cap', 'cap')
                ->where(fn($q) => $q->where('sigla_provincia', $request->provincia)
                    ->where('denominazione_ita', $request->citta));
        }

        if ($isElevato) {
            $rules['conv_assoc'] = 'nullable|array';
            $rules['conv_assoc.*'] = 'nullable|array';
            $rules['conv_assoc.*.*'] = 'integer|exists:associazioni,idAssociazione';
        }

        $validated = $request->validate($rules);

        // -------------------------------
        // TRANSAZIONE
        // -------------------------------
        return DB::transaction(function () use ($validated, $anno, $isElevato, $user) {

            // 1) CREA AZIENDA
            $idAzienda = AziendaSanitaria::createSanitaria(
                $validated + ['anno_riferimento' => $anno]
            );

            // 2) CREA LOTTI (semplici)
            $lottiInput = $validated['lotti'] ?? [];
            $createdLotti = [];  // idx → nome lotto

            if ($validated['lotti_presenti'] === '1') {
                foreach ($lottiInput as $idx => $lotto) {

                    $nome = trim($lotto['nomeLotto'] ?? '');
                    if ($nome === '') continue;

                    DB::table('aziende_sanitarie_lotti')->insert([
                        'idAziendaSanitaria' => $idAzienda,
                        'nomeLotto'          => $nome,
                        'descrizione'        => $lotto['descrizione'] ?? null,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    $createdLotti[$idx] = $nome;  // INDICE CORRETTO
                }
            }

            // 3) CONVENZIONI
            $nomeAzienda = $validated['Nome'];
            $idAnno      = AziendaSanitaria::resolveIdAnno($anno);
            $createdConvIds = [];

            // UTENTE NON ELEVATO ==========================
            if (!$isElevato) {

                $idAss = $user->IdAssociazione;

                if ($validated['lotti_presenti'] === '0' || empty($createdLotti)) {
                    // SOLO 1 convenzione
                    $convName = $nomeAzienda;

                    $idConv = DB::table('convenzioni')->updateOrInsert(
                        [
                            'Convenzione' => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno' => $idAnno
                        ],
                        ['updated_at' => now()]
                    );

                    $createdConvIds[] = DB::table('convenzioni')
                        ->where('Convenzione', $convName)
                        ->where('idAssociazione', $idAss)
                        ->where('idAnno', $idAnno)
                        ->value('idConvenzione');
                } else {
                    // 1 PER LOTTO
                    foreach ($createdLotti as $idx => $lottoName) {

                        $convName = $nomeAzienda . " - " . $lottoName;

                        DB::table('convenzioni')->updateOrInsert(
                            [
                                'Convenzione' => $convName,
                                'idAssociazione' => $idAss,
                                'idAnno' => $idAnno
                            ],
                            ['updated_at' => now()]
                        );

                        $createdConvIds[] = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');
                    }
                }

                AziendaSanitaria::syncConvenzioni($idAzienda, $createdConvIds);
                return redirect()->route('aziende-sanitarie.index')
                    ->with('success', 'Azienda creata correttamente.');
            }

            // UTENTE ELEVATO ================================
            $convAssoc = $validated['conv_assoc'] ?? [];

            if ($validated['lotti_presenti'] === '0' || empty($createdLotti)) {

                $assList = $convAssoc[0] ?? [];

                foreach ($assList as $idAss) {
                    DB::table('convenzioni')->updateOrInsert(
                        [
                            'Convenzione' => $nomeAzienda,
                            'idAssociazione' => $idAss,
                            'idAnno' => $idAnno
                        ],
                        ['updated_at' => now()]
                    );

                    $createdConvIds[] = DB::table('convenzioni')
                        ->where('Convenzione', $nomeAzienda)
                        ->where('idAssociazione', $idAss)
                        ->where('idAnno', $idAnno)
                        ->value('idConvenzione');
                }
            } else {
                // 1 CONVENZIONE PER INDICE LOTTO REAL
                foreach ($createdLotti as $idx => $lottoName) {

                    $assList = $convAssoc[$idx] ?? [];
                    $convName = $nomeAzienda . " - " . $lottoName;

                    foreach ($assList as $idAss) {

                        DB::table('convenzioni')->updateOrInsert(
                            [
                                'Convenzione' => $convName,
                                'idAssociazione' => $idAss,
                                'idAnno' => $idAnno
                            ],
                            ['updated_at' => now()]
                        );

                        $createdConvIds[] = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');
                    }
                }
            }

            AziendaSanitaria::syncConvenzioni($idAzienda, $createdConvIds);

            return redirect()->route('aziende-sanitarie.index')
                ->with('success', 'Azienda creata correttamente.');
        });
    }

    public function edit(int $id) {
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $azienda = DB::table('aziende_sanitarie')
            ->where('idAziendaSanitaria', $id)
            ->first();

        abort_if(!$azienda, 404);

        // === 1) Identifica azienda "ALTRO" =====================================
        $isAltro = strtoupper(trim($azienda->Nome)) === 'ALTRO';

        // === 2) Chi può eliminare? (solo Admin/SuperAdmin/Supervisor) ==========
        $canDelete = $user->can('manage-all-associations');

        // === 3) Chi può modificare? ============================================
        // - Tutti se azienda = ALTRO
        // - Altrimenti solo Admin/SuperAdmin/Supervisor
        $canEdit = $isAltro || $user->can('manage-all-associations');

        // === 4) Se NON può modificare → redirect a SHOW readonly ================
        if (!$canEdit) {
            return redirect()->route('aziende-sanitarie.show', $id);
        }

        // ========================================================================
        //  LOGICA PERMESSI ESISTENTE (la mantengo identica)
        // ========================================================================
        $idAnno = $this->idAnnoCorrente();

        if (!$isElevato) {
            $convUser = DB::table('convenzioni')
                ->where('idAssociazione', $user->IdAssociazione)
                ->where('idAnno', $idAnno)
                ->pluck('idConvenzione')
                ->toArray();

            $convAzienda = DB::table('azienda_sanitaria_convenzione')
                ->where('idAziendaSanitaria', $id)
                ->pluck('idConvenzione')
                ->toArray();

            $match = array_intersect($convUser, $convAzienda);

            if (empty($match)) {
                abort(403, 'Non hai accesso a questa azienda sanitaria.');
            }

            // NIENTE select convenzioni per utenti non elevati
            $convenzioni = collect();
            $convenzioniSelezionate = $match;
        } else {
            $convenzioni = DB::table('convenzioni')
                ->select('idConvenzione', 'Convenzione')
                ->where('idAnno', $idAnno)
                ->orderBy('Convenzione')
                ->get();

            $convenzioniSelezionate = DB::table('azienda_sanitaria_convenzione')
                ->where('idAziendaSanitaria', $id)
                ->pluck('idConvenzione')
                ->toArray();
        }

        // Lotti
        $lotti = LottoAziendaSanitaria::getByAzienda($id);

        // Associazioni
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        // Preselezioni tab convenzioni
        $convAssocByLotto = [];
        if ($isElevato) {
            foreach ($lotti as $lotto) {
                $convName = $azienda->Nome . ' - ' . $lotto->nomeLotto;
                $assocIds = DB::table('convenzioni')
                    ->where('Convenzione', $convName)
                    ->where('idAnno', $idAnno)
                    ->pluck('idAssociazione')
                    ->toArray();

                $convAssocByLotto[$lotto->id] = $assocIds;
            }
        }

        $cities = Cities::getAll();
        $caps   = Cities::getAllWithCap();

        // === 5) Passo anche $canEdit e $canDelete alla VIEW =====================
        return view('aziende_sanitarie.edit', compact(
            'azienda',
            'convenzioni',
            'convenzioniSelezionate',
            'lotti',
            'associazioni',
            'convAssocByLotto',
            'cities',
            'caps',
            'isElevato',
            'canEdit',
            'canDelete',
            'isAltro'
        ));
    }


    public function update(Request $request, int $id) {
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        $anno = (int) session('anno_riferimento', now()->year);
        $idAnno = AziendaSanitaria::resolveIdAnno($anno);

        // -----------------
        // VALIDAZIONE
        // -----------------
        $rules = [
            'Nome'            => 'required|string|max:150',
            'indirizzo_via'    => 'nullable|string|max:255',
            'indirizzo_civico' => 'nullable|string|max:20',
            'provincia'       => 'nullable|string|exists:ter_cities,sigla_provincia',
            'citta'           => 'nullable|string|max:100',
            'cap'             => ['nullable', 'string', 'size:5'],
            'mail'            => 'nullable|email|max:150',
            'note'            => 'nullable|string',

            'lotti_presenti'  => 'nullable|in:0,1',

            'lotti'               => 'nullable|array',
            'lotti.*.id'          => 'nullable|integer',
            'lotti.*.nomeLotto'   => 'nullable|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',
            'lotti.*._delete'     => 'nullable|boolean',

            'convenzioni'        => 'nullable|array',
            'convenzioni.*'      => 'integer|exists:convenzioni,idConvenzione',
        ];

        if ($isElevato) {
            $rules['conv_assoc'] = 'nullable|array';
            $rules['conv_assoc.*'] = 'nullable|array';
            $rules['conv_assoc.*.*'] = 'integer|exists:associazioni,idAssociazione';
        }

        if ($request->filled('provincia') && $request->filled('citta')) {
            $rules['cap'][] = Rule::exists('ter_cities_cap', 'cap')
                ->where(fn($q) => $q->where('sigla_provincia', $request->provincia)
                    ->where('denominazione_ita', $request->citta));
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $id, $user, $isElevato, $idAnno) {

            // 1) Aggiorna anagrafica
            AziendaSanitaria::updateSanitaria($id, $validated);

            // 2) Update Lotti
            $lottiInput = $validated['lotti'] ?? [];
            LottoAziendaSanitaria::syncForAzienda($id, $lottiInput);

            // RESYNC Lotti dopo sync
            $lottiDb = LottoAziendaSanitaria::getByAzienda($id)
                ->keyBy('id'); // id lotto → lotto

            // Mappa idx form → nome lotto
            $idxToLottoName = [];
            foreach ($lottiInput as $idx => $r) {
                if (empty($r['_delete']) && trim($r['nomeLotto'] ?? '') !== '') {
                    $idxToLottoName[$idx] = trim($r['nomeLotto']);
                }
            }

            $nomeAzienda = $validated['Nome'];

            // =============================
            // UTENTE NON ELEVATO
            // =============================
            if (!$isElevato) {

                $idAss = $user->IdAssociazione;

                // Elimina convenzioni non della sua associazione
                DB::table('azienda_sanitaria_convenzione AS asc')
                    ->join('convenzioni AS c', 'c.idConvenzione', '=', 'asc.idConvenzione')
                    ->where('asc.idAziendaSanitaria', $id)
                    ->where('c.idAnno', $idAnno)
                    ->where('c.idAssociazione', '!=', $idAss)
                    ->delete();

                $newConvIds = [];

                // Nessun lotto → unica convenzione
                if ($validated['lotti_presenti'] == '0' || empty($idxToLottoName)) {

                    $convName = $nomeAzienda;

                    $idConv = DB::table('convenzioni')
                        ->where('Convenzione', $convName)
                        ->where('idAssociazione', $idAss)
                        ->where('idAnno', $idAnno)
                        ->value('idConvenzione');

                    if (!$idConv) {
                        $idConv = DB::table('convenzioni')->insertGetId([
                            'Convenzione' => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno' => $idAnno,
                            'ordinamento' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], 'idConvenzione');
                    }

                    $newConvIds[] = $idConv;
                } else {
                    // 1 convenzione per lotto
                    foreach ($idxToLottoName as $idx => $lottoName) {

                        $convName = "$nomeAzienda - $lottoName";

                        $idConv = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');

                        if (!$idConv) {
                            $idConv = DB::table('convenzioni')->insertGetId([
                                'Convenzione' => $convName,
                                'idAssociazione' => $idAss,
                                'idAnno' => $idAnno,
                                'ordinamento' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ], 'idConvenzione');
                        }

                        $newConvIds[] = $idConv;
                    }
                }

                AziendaSanitaria::syncConvenzioni($id, $newConvIds);

                return redirect()->route('aziende-sanitarie.index')
                    ->with('success', 'Azienda aggiornata.');
            }

            // =============================
            // UTENTE ELEVATO
            // =============================
            $convAssoc = $validated['conv_assoc'] ?? [];
            $created = [];

            $noLotti = ($validated['lotti_presenti'] == '0' || empty($idxToLottoName));

            // 1) Elimina convenzioni disassociate
            $toDelete = [];

            if ($noLotti) {

                $existing = DB::table('convenzioni')
                    ->where('Convenzione', $nomeAzienda)
                    ->where('idAnno', $idAnno)
                    ->get();

                $selected = array_map('intval', $convAssoc[0] ?? []);

                foreach ($existing as $e) {
                    if (!in_array($e->idAssociazione, $selected, true)) {
                        $toDelete[] = $e->idConvenzione;
                    }
                }
            } else {

                foreach ($idxToLottoName as $idx => $lottoName) {

                    $convName = "$nomeAzienda - $lottoName";

                    $existing = DB::table('convenzioni')
                        ->where('Convenzione', $convName)
                        ->where('idAnno', $idAnno)
                        ->get();

                    $selected = array_map('intval', $convAssoc[$idx] ?? []);

                    foreach ($existing as $e) {
                        if (!in_array($e->idAssociazione, $selected, true)) {
                            $toDelete[] = $e->idConvenzione;
                        }
                    }
                }
            }

            $toDelete = array_unique($toDelete);

            if ($toDelete) {
                DB::table('azienda_sanitaria_convenzione')
                    ->whereIn('idConvenzione', $toDelete)
                    ->delete();

                DB::table('convenzioni')
                    ->whereIn('idConvenzione', $toDelete)
                    ->delete();
            }

            // 2) CREA convenzioni nuove
            if ($noLotti) {

                $selected = array_map('intval', $convAssoc[0] ?? []);

                foreach ($selected as $idAss) {
                    $existingId = DB::table('convenzioni')
                        ->where('Convenzione', $nomeAzienda)
                        ->where('idAssociazione', $idAss)
                        ->where('idAnno', $idAnno)
                        ->value('idConvenzione');

                    $cid = $existingId ?: DB::table('convenzioni')->insertGetId([
                        'Convenzione' => $nomeAzienda,
                        'idAssociazione' => $idAss,
                        'idAnno' => $idAnno,
                        'ordinamento' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], 'idConvenzione');

                    $created[] = $cid;
                }
            } else {

                foreach ($idxToLottoName as $idx => $lottoName) {
                    $convName = "$nomeAzienda - $lottoName";
                    $selected = array_map('intval', $convAssoc[$idx] ?? []);

                    foreach ($selected as $idAss) {

                        $existingId = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');

                        $cid = $existingId ?: DB::table('convenzioni')->insertGetId([
                            'Convenzione' => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno' => $idAnno,
                            'ordinamento' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], 'idConvenzione');

                        $created[] = $cid;
                    }
                }
            }

            $created = array_unique($created);

            // 3) Mantieni convenzioni non gestite dal wizard
            $managedNames = $noLotti
                ? [$nomeAzienda]
                : array_map(fn($n) => "$nomeAzienda - $n", $idxToLottoName);

            $other = DB::table('azienda_sanitaria_convenzione AS asc')
                ->join('convenzioni AS c', 'c.idConvenzione', '=', 'asc.idConvenzione')
                ->where('asc.idAziendaSanitaria', $id)
                ->whereNotIn('c.Convenzione', $managedNames)
                ->pluck('c.idConvenzione')
                ->map(fn($v) => (int)$v)
                ->toArray();

            $extra = array_map('intval', $validated['convenzioni'] ?? []);

            $final = array_unique(array_merge($other, $created, $extra));

            AziendaSanitaria::syncConvenzioni($id, $final);

            return redirect()->route('aziende-sanitarie.index')
                ->with('success', 'Azienda aggiornata correttamente.');
        });
    }

    public function destroy(int $id) {
        AziendaSanitaria::deleteSanitaria($id);
        return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda eliminata.');
    }

    /** === DUPLICAZIONE PER ANNO === */
    public function checkDuplicazioneDisponibile(): JsonResponse {
        $anno     = (int) session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;

        $correnteVuoto   = !AziendaSanitaria::existsForAnno($anno);
        $precedentePieno = AziendaSanitaria::existsForAnno($annoPrec);

        return response()->json([
            'mostraMessaggio' => $correnteVuoto && $precedentePieno,
            'annoCorrente'    => $anno,
            'annoPrecedente'  => $annoPrec,
        ]);
    }

    /** Duplica aziende, lotti, convenzioni e pivot dall’anno precedente all’anno corrente. */
    public function duplicaAnnoPrecedente(): JsonResponse {
        $anno     = (int) session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;

        $idAnnoCurr = $this->idAnnoCorrente();
        $idAnnoPrev = AziendaSanitaria::resolveIdAnno($annoPrec);

        try {
            return DB::transaction(function () use ($idAnnoPrev, $idAnnoCurr) {
                $aziendePrev = DB::table('aziende_sanitarie')
                    ->where('idAnno', $idAnnoPrev)
                    ->orderBy('Nome')
                    ->get();

                if ($aziendePrev->isEmpty()) {
                    return response()->json(['message' => 'Nessuna azienda da duplicare'], 404);
                }

                $newCount = 0;

                foreach ($aziendePrev as $az) {
                    $newId = DB::table('aziende_sanitarie')->insertGetId([
                        'idAnno'     => $idAnnoCurr,
                        'Nome'       => $az->Nome,
                        'Indirizzo'  => $az->Indirizzo,
                        'provincia'  => $az->provincia,
                        'citta'      => $az->citta,
                        'cap'        => $az->cap ?? null,
                        'mail'       => $az->mail,
                        'note'       => $az->note,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], 'idAziendaSanitaria');

                    $newCount++;

                    // lotti
                    $lotti = DB::table('aziende_sanitarie_lotti')
                        ->where('idAziendaSanitaria', $az->idAziendaSanitaria)
                        ->get();

                    foreach ($lotti as $lotto) {
                        DB::table('aziende_sanitarie_lotti')->insert([
                            'idAziendaSanitaria' => $newId,
                            'nomeLotto'          => $lotto->nomeLotto,
                            'descrizione'        => $lotto->descrizione,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                    }

                    // convenzioni + pivot
                    $pivotPrev = DB::table('azienda_sanitaria_convenzione')
                        ->where('idAziendaSanitaria', $az->idAziendaSanitaria)
                        ->pluck('idConvenzione')
                        ->toArray();

                    if (!empty($pivotPrev)) {
                        $convsPrev = DB::table('convenzioni')
                            ->whereIn('idConvenzione', $pivotPrev)
                            ->get(['idConvenzione', 'Convenzione', 'idAssociazione', 'idAnno']);

                        foreach ($convsPrev as $cp) {
                            $newConvId = DB::table('convenzioni')
                                ->where('Convenzione', $cp->Convenzione)
                                ->where('idAssociazione', $cp->idAssociazione)
                                ->where('idAnno', $idAnnoCurr)
                                ->value('idConvenzione');

                            if (!$newConvId) {
                                $newConvId = DB::table('convenzioni')->insertGetId([
                                    'Convenzione'    => $cp->Convenzione,
                                    'idAssociazione' => $cp->idAssociazione,
                                    'idAnno'         => $idAnnoCurr,
                                    'ordinamento'    => 0,
                                    'created_at'     => now(),
                                    'updated_at'     => now(),
                                ], 'idConvenzione');
                            }

                            DB::table('azienda_sanitaria_convenzione')->insert([
                                'idAziendaSanitaria' => $newId,
                                'idConvenzione'      => $newConvId,
                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ]);
                        }
                    }
                }

                return response()->json([
                    'message'      => 'Duplicazione completata.',
                    'nuoveAziende' => $newCount,
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Errore durante la duplicazione.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id) {
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // === Recupero azienda ===
        $azienda = DB::table('aziende_sanitarie')
            ->where('idAziendaSanitaria', $id)
            ->first();

        abort_if(!$azienda, 404);

        $idAnno = $this->idAnnoCorrente();

        // === LOTTI ===
        $lotti = LottoAziendaSanitaria::getByAzienda($id);

        // === CONVENZIONI (solo elevati) ===
        $convenzioni = [];
        if ($isElevato) {
            $convenzioni = DB::table('convenzioni AS c')
                ->join('azienda_sanitaria_convenzione AS asc', 'asc.idConvenzione', '=', 'c.idConvenzione')
                ->leftJoin('associazioni AS a', 'a.idAssociazione', '=', 'c.idAssociazione')
                ->where('asc.idAziendaSanitaria', $id)
                ->where('c.idAnno', $idAnno)
                ->select('c.Convenzione', 'a.Associazione')
                ->orderBy('c.Convenzione')
                ->get();
        }

        return view('aziende_sanitarie.show', [
            'azienda'     => $azienda,
            'lotti'       => $lotti,
            'convenzioni' => $convenzioni,
            'isElevato'   => $isElevato,
        ]);
    }
}
