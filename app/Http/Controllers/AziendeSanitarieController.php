<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use App\Models\AziendaSanitaria;
use App\Models\LottoAziendaSanitaria;
use App\Models\Cities;

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
        $anno = (int) session('anno_riferimento', now()->year);
        $idAnno = $this->idAnnoCorrente();

        // lista convenzioni per anno selezionato
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAnno', $idAnno)
            ->orderBy('Convenzione')
            ->get();

        // valore selezionato (request -> session -> null)
        if ($request->has('idConvenzione')) {
            session(['convenzione_selezionata' => $request->idConvenzione]);
        }
        $selectedConvenzione = session('convenzione_selezionata') ?? null;

        // carico le aziende per l'anno corrente
        $aziende = AziendaSanitaria::getAllWithConvenzioni($selectedConvenzione);

        return view('aziende_sanitarie.index', compact(
            'anno',
            'convenzioni',
            'selectedConvenzione',
            'aziende'
        ));
    }

    public function getData(Request $request): JsonResponse {
        $idConvenzione = $request->input('idConvenzione') ?? session('convenzione_selezionata');
        $data = AziendaSanitaria::getAllWithConvenzioni($idConvenzione);
        return response()->json(['data' => $data]);
    }

    public function create() {
        $anni = DB::table('anni')->orderBy('anno', 'desc')->get();

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        // convenzioni per anno corrente (in create di solito sono vuote)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAnno', $this->idAnnoCorrente())
            ->orderBy('Convenzione')
            ->get();

        $lotti = collect();
        $cities = Cities::getAll();

        return view('aziende_sanitarie.create', compact(
            'anni',
            'associazioni',
            'convenzioni',
            'lotti',
            'cities'
        ));
    }

    public function store(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);

        $validated = $request->validate([
            'Nome'           => 'required|string|max:150',
            'Indirizzo'      => 'nullable|string|max:255',
            'provincia'      => 'nullable|string|exists:ter_cities,sigla_provincia',
            'citta'          => 'nullable|string|max:100',
            'mail'           => 'nullable|email|max:150',
            'note'           => 'nullable|string',

            'lotti_presenti' => 'nullable|in:0,1',

            'lotti'               => 'nullable|array',
            'lotti.*.nomeLotto'   => 'nullable|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',

            'conv_assoc'     => 'nullable|array',
            'conv_assoc.*'   => 'nullable|array',
            'conv_assoc.*.*' => 'integer|exists:associazioni,idAssociazione',

            'convenzioni'    => 'nullable|array',
            'convenzioni.*'  => 'integer|exists:convenzioni,idConvenzione',
        ]);

        return DB::transaction(function () use ($validated, $anno) {
            // Helper: nome convenzione in base al lotto
            $buildConvName = static function (string $nomeAzienda, ?string $lottoName): string {
                $ln = trim((string)$lottoName);
                return ($ln === '' || strcasecmp($ln, 'LOTTI NON PRESENTI') === 0)
                    ? $nomeAzienda
                    : ($nomeAzienda . ' - ' . $ln);
            };

            // 1) Crea Azienda (forziamo anno corrente dentro ai dati)
            $idAzienda = AziendaSanitaria::createSanitaria($validated + ['anno_riferimento' => $anno]);

            // 2) Crea Lotti (se presenti)
            foreach ($validated['lotti'] ?? [] as $lotto) {
                if (trim((string)($lotto['nomeLotto'] ?? '')) === '' && ($validated['lotti_presenti'] ?? '1') === '1') {
                    continue;
                }
                DB::table('aziende_sanitarie_lotti')->insert([
                    'idAziendaSanitaria' => $idAzienda,
                    'nomeLotto'          => $lotto['nomeLotto'] ?? null,
                    'descrizione'        => $lotto['descrizione'] ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            // 3) Crea/riusa Convenzioni
            $idAnno      = $this->idAnnoCorrente();
            $convAssoc   = $validated['conv_assoc'] ?? [];
            $nomeAzienda = $validated['Nome'];
            $createdConvIds = [];

            // Verifica se ci sono lotti “utili”
            $hasRealLotti = collect($validated['lotti'] ?? [])
                ->contains(fn($r) => trim((string)($r['nomeLotto'] ?? '')) !== '');

            if (($validated['lotti_presenti'] ?? '1') === '0' && !$hasRealLotti) {
                // Modalità NO: conv = solo nome azienda, associazioni da conv_assoc[0]
                $assIds = array_unique(array_map('intval', $convAssoc[0] ?? []));
                if (!empty($assIds)) {
                    $convName = $buildConvName($nomeAzienda, null); // solo azienda
                    foreach ($assIds as $idAss) {
                        $existingId = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');

                        $idConv = $existingId ? (int)$existingId : DB::table('convenzioni')->insertGetId([
                            'Convenzione'    => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno'         => $idAnno,
                            'ordinamento'    => 0,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ], 'idConvenzione');

                        $createdConvIds[] = $idConv;
                    }
                }
            } else {
                // Modalità SÌ (o lotti presenti): una convenzione per lotto selezionato
                foreach (($validated['lotti'] ?? []) as $idx => $lotto) {
                    $lottoName = trim((string)($lotto['nomeLotto'] ?? ''));
                    if ($lottoName === '') continue;

                    $assIds = array_unique(array_map('intval', $convAssoc[$idx] ?? []));
                    if (empty($assIds)) continue;

                    $convName = $buildConvName($nomeAzienda, $lottoName);

                    foreach ($assIds as $idAss) {
                        $existingId = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');

                        $idConv = $existingId ? (int)$existingId : DB::table('convenzioni')->insertGetId([
                            'Convenzione'    => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno'         => $idAnno,
                            'ordinamento'    => 0,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ], 'idConvenzione');

                        $createdConvIds[] = $idConv;
                    }
                }
            }

            // 4) Aggancia all’azienda tutte le convenzioni nuove + eventuali selezionate extra
            $mergeExisting = array_map('intval', $validated['convenzioni'] ?? []);
            $allConv = array_values(array_unique(array_merge($createdConvIds, $mergeExisting)));
            if (!empty($allConv)) {
                AziendaSanitaria::syncConvenzioni($idAzienda, $allConv);
            }

            return redirect()->route('aziende-sanitarie.index')
                ->with('success', 'Azienda, lotti e convenzioni creati.');
        });
    }


    public function edit(int $id) {
        $azienda = DB::table('aziende_sanitarie')->where('idAziendaSanitaria', $id)->first();
        abort_if(!$azienda, 404);

        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAnno', $this->idAnnoCorrente())
            ->orderBy('Convenzione')
            ->get();

        $convenzioniSelezionate = DB::table('azienda_sanitaria_convenzione')
            ->where('idAziendaSanitaria', $id)
            ->pluck('idConvenzione')
            ->toArray();

        $lotti = LottoAziendaSanitaria::getByAzienda($id);

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        // Preselezioni per tab 3, per l'anno corrente
        $anno = (int) session('anno_riferimento', now()->year);
        $convAssocByLotto = [];
        foreach ($lotti as $lotto) {
            $convName = $azienda->Nome . ' - ' . $lotto->nomeLotto;
            $assocIds = DB::table('convenzioni')
                ->where('Convenzione', $convName)
                ->where('idAnno', $this->idAnnoCorrente())
                ->pluck('idAssociazione')
                ->toArray();
            $convAssocByLotto[$lotto->id] = $assocIds;
        }
        $cities = Cities::getAll();

        return view('aziende_sanitarie.edit', compact(
            'azienda',
            'convenzioni',
            'convenzioniSelezionate',
            'lotti',
            'associazioni',
            'convAssocByLotto',
            'cities'
        ));
    }

    public function update(Request $request, int $id) {
        $anno = (int) session('anno_riferimento', now()->year);

        $validated = $request->validate([
            'Nome'           => 'required|string|max:150',
            'Indirizzo'      => 'nullable|string|max:255',
            'provincia'      => 'nullable|string|exists:ter_cities,sigla_provincia',
            'citta'          => 'nullable|string|max:100',
            'mail'           => 'nullable|email|max:150',
            'note'           => 'nullable|string',

            'lotti_presenti' => 'nullable|in:0,1',

            'lotti'               => 'nullable|array',
            'lotti.*.id'          => 'nullable|integer',
            'lotti.*.nomeLotto'   => 'nullable|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',
            'lotti.*._delete'     => 'nullable|boolean',

            'conv_assoc'     => 'nullable|array',
            'conv_assoc.*'   => 'nullable|array',
            'conv_assoc.*.*' => 'integer|exists:associazioni,idAssociazione',

            'convenzioni'    => 'nullable|array',
            'convenzioni.*'  => 'integer|exists:convenzioni,idConvenzione',
        ]);

        return DB::transaction(function () use ($validated, $id, $anno) {
            // Helper
            $buildConvName = static function (string $nomeAzienda, ?string $lottoName): string {
                $ln = trim((string)$lottoName);
                return ($ln === '' || strcasecmp($ln, 'LOTTI NON PRESENTI') === 0)
                    ? $nomeAzienda
                    : ($nomeAzienda . ' - ' . $ln);
            };

            AziendaSanitaria::updateSanitaria($id, $validated);
            $nomeAzienda = $validated['Nome'];
            $idAnno = $this->idAnnoCorrente();

            // Duplicati lotti (solo su quelli non _delete e con nome valorizzato)
            $names = [];
            foreach (($validated['lotti'] ?? []) as $row) {
                if (!empty($row['_delete'])) continue;
                $name = trim((string)($row['nomeLotto'] ?? ''));
                if ($name === '') continue;
                $k = mb_strtolower($name);
                if (isset($names[$k])) {
                    abort(422, "Nome lotto duplicato: '{$name}'");
                }
                $names[$k] = true;
            }

            // Sincronizza lotti
            LottoAziendaSanitaria::syncForAzienda($id, $validated['lotti'] ?? []);

            // ===== A) Elimina convenzioni deselezionate (per anno corrente) =====
            $convAssoc = $validated['conv_assoc'] ?? [];
            $toDeleteConvIds = [];

            $hasRealLotti = collect($validated['lotti'] ?? [])
                ->contains(fn($r) => empty($r['_delete']) && trim((string)($r['nomeLotto'] ?? '')) !== '');

            if (($validated['lotti_presenti'] ?? '1') === '0' && !$hasRealLotti) {
                // Modalità NO: gestiamo la convenzione "<Azienda>" unica
                $existing = DB::table('convenzioni')
                    ->where('Convenzione', $nomeAzienda)
                    ->where('idAnno', $idAnno)
                    ->get(['idConvenzione', 'idAssociazione']);

                $selectedAss = array_unique(array_map('intval', $convAssoc[0] ?? []));
                $existingByAss = $existing->groupBy('idAssociazione');

                foreach ($existingByAss as $assId => $rows) {
                    if (!in_array((int)$assId, $selectedAss, true)) {
                        foreach ($rows as $r) $toDeleteConvIds[] = (int)$r->idConvenzione;
                    }
                }
            } else {
                // Modalità SÌ: una convenzione per ogni lotto gestito
                foreach (($validated['lotti'] ?? []) as $idx => $row) {
                    $lottoName = trim((string)($row['nomeLotto'] ?? ''));
                    if ($lottoName === '') continue;

                    $convName = $buildConvName($nomeAzienda, $lottoName);

                    $existing = DB::table('convenzioni')
                        ->where('Convenzione', $convName)
                        ->where('idAnno', $idAnno)
                        ->get(['idConvenzione', 'idAssociazione']);

                    if ($existing->isEmpty()) continue;

                    $selectedAss = !empty($row['_delete'])
                        ? []
                        : array_unique(array_map('intval', $convAssoc[$idx] ?? []));

                    $existingByAss = $existing->groupBy('idAssociazione');
                    foreach ($existingByAss as $assId => $rows) {
                        if (!in_array((int)$assId, $selectedAss, true)) {
                            foreach ($rows as $r) $toDeleteConvIds[] = (int)$r->idConvenzione;
                        }
                    }
                }
            }

            $toDeleteConvIds = array_values(array_unique($toDeleteConvIds));
            if (!empty($toDeleteConvIds)) {
                DB::table('azienda_sanitaria_convenzione')
                    ->whereIn('idConvenzione', $toDeleteConvIds)
                    ->delete();

                DB::table('convenzioni')
                    ->whereIn('idConvenzione', $toDeleteConvIds)
                    ->delete();
            }

            // ===== B) Crea/riusa convenzioni selezionate =====
            $createdOrEnsuredConvIds = [];

            if (($validated['lotti_presenti'] ?? '1') === '0' && !$hasRealLotti) {
                // NO: unica convenzione "<Azienda>"
                $assIds = array_unique(array_map('intval', $convAssoc[0] ?? []));
                if (!empty($assIds)) {
                    $convName = $buildConvName($nomeAzienda, null);
                    foreach ($assIds as $idAss) {
                        $existingId = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');

                        $idConv = $existingId ? (int)$existingId : DB::table('convenzioni')->insertGetId([
                            'Convenzione'    => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno'         => $idAnno,
                            'ordinamento'    => 0,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ], 'idConvenzione');

                        $createdOrEnsuredConvIds[] = $idConv;
                    }
                }
            } else {
                // SÌ: convenzioni per lotti
                foreach (($validated['lotti'] ?? []) as $idx => $row) {
                    if (!empty($row['_delete'])) continue;
                    $lottoName = trim((string)($row['nomeLotto'] ?? ''));
                    if ($lottoName === '') continue;

                    $assIds = array_unique(array_map('intval', $convAssoc[$idx] ?? []));
                    if (empty($assIds)) continue;

                    $convName = $buildConvName($nomeAzienda, $lottoName);

                    foreach ($assIds as $idAss) {
                        $existingId = DB::table('convenzioni')
                            ->where('Convenzione', $convName)
                            ->where('idAssociazione', $idAss)
                            ->where('idAnno', $idAnno)
                            ->value('idConvenzione');

                        $idConv = $existingId ? (int)$existingId : DB::table('convenzioni')->insertGetId([
                            'Convenzione'    => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno'         => $idAnno,
                            'ordinamento'    => 0,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ], 'idConvenzione');

                        $createdOrEnsuredConvIds[] = $idConv;
                    }
                }
            }

            $createdOrEnsuredConvIds = array_values(array_unique($createdOrEnsuredConvIds));

            // ===== C) Pivot finale: preserva convenzioni non gestite dal wizard, aggiungi nuove, + extra selezionate
            $managedNames = [];
            if (($validated['lotti_presenti'] ?? '1') === '0' && !$hasRealLotti) {
                $managedNames = [$nomeAzienda]; // solo "<Azienda>"
            } else {
                $managedNames = collect($validated['lotti'] ?? [])
                    ->filter(fn($r) => empty($r['_delete']) && isset($r['nomeLotto']))
                    ->map(fn($r) => $buildConvName($nomeAzienda, $r['nomeLotto']))
                    ->values()
                    ->all();
            }

            $otherPivot = DB::table('azienda_sanitaria_convenzione as asc')
                ->join('convenzioni as c', 'c.idConvenzione', '=', 'asc.idConvenzione')
                ->where('asc.idAziendaSanitaria', $id)
                ->when(!empty($managedNames), fn($q) => $q->whereNotIn('c.Convenzione', $managedNames))
                ->pluck('c.idConvenzione')
                ->map(fn($v) => (int)$v)
                ->toArray();

            $extraFromSelect = array_map('intval', $validated['convenzioni'] ?? []);

            $finalPivot = array_values(array_unique(array_merge(
                $otherPivot,
                $createdOrEnsuredConvIds,
                $extraFromSelect
            )));

            AziendaSanitaria::syncConvenzioni($id, $finalPivot);

            return redirect()->route('aziende-sanitarie.index')
                ->with('success', 'Azienda aggiornata. Convenzioni deselezionate eliminate, collegamenti sincronizzati.');
        });
    }


    public function destroy(int $id) {
        AziendaSanitaria::deleteSanitaria($id);
        return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda eliminata.');
    }

    /** === DUPLICAZIONE PER ANNO ===
     * Mostra se la duplicazione è disponibile (vuoto nell'anno corrente e pieno nell'anno precedente).
     */
    public function checkDuplicazioneDisponibile(): JsonResponse {
        $anno = (int) session('anno_riferimento', now()->year);
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
        $anno = (int) session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;

        $idAnnoCurr = $this->idAnnoCorrente();
        $idAnnoPrev = AziendaSanitaria::resolveIdAnno($annoPrec);

        try {
            return DB::transaction(function () use ($idAnnoPrev, $idAnnoCurr) {
                // 1) prendi aziende del prev anno
                $aziendePrev = DB::table('aziende_sanitarie')
                    ->where('idAnno', $idAnnoPrev)
                    ->orderBy('Nome')
                    ->get();

                if ($aziendePrev->isEmpty()) {
                    return response()->json(['message' => 'Nessuna azienda da duplicare'], 404);
                }

                $mapAzienda = []; // oldId => newId
                $newCount = 0;

                foreach ($aziendePrev as $az) {
                    // crea nuova azienda per anno corrente
                    $newId = DB::table('aziende_sanitarie')->insertGetId([
                        'idAnno'     => $idAnnoCurr,
                        'Nome'       => $az->Nome,
                        'Indirizzo'  => $az->Indirizzo,
                        'provincia'  => $az->provincia,
                        'citta'      => $az->citta,
                        'mail'       => $az->mail,
                        'note'       => $az->note,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], 'idAziendaSanitaria');

                    $mapAzienda[(int)$az->idAziendaSanitaria] = (int)$newId;
                    $newCount++;

                    // 2) duplica lotti
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

                    // 3) duplica convenzioni collegate (ricreandole per l'anno corrente se mancano) + pivot
                    $pivotPrev = DB::table('azienda_sanitaria_convenzione')
                        ->where('idAziendaSanitaria', $az->idAziendaSanitaria)
                        ->pluck('idConvenzione')
                        ->toArray();

                    if (!empty($pivotPrev)) {
                        $convsPrev = DB::table('convenzioni')
                            ->whereIn('idConvenzione', $pivotPrev)
                            ->get(['idConvenzione', 'Convenzione', 'idAssociazione', 'idAnno']);

                        foreach ($convsPrev as $cp) {
                            // cerco/creo la convenzione equivalente per l'anno corrente
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

                            // crea pivot verso la nuova azienda
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
                    'message'          => 'Duplicazione completata.',
                    'nuoveAziende'     => $newCount,
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Errore durante la duplicazione.', 'error' => $e->getMessage()], 500);
        }
    }
}
