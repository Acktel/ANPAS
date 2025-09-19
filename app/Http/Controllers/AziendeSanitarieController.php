<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use App\Models\AziendaSanitaria;
use App\Models\LottoAziendaSanitaria;

class AziendeSanitarieController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    public function index(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        // lista convenzioni (usata per la select)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        // valore selezionato (request -> session -> null)
        if ($request->has('idConvenzione')) {
            session(['convenzione_selezionata' => $request->idConvenzione]);
        }
        $selectedConvenzione = session('convenzione_selezionata') ?? null;

        // carico le aziende sanitarie (il filtro verrà applicato da getData / model)
        $aziende = AziendaSanitaria::getAllWithConvenzioni(); // carico tutto per la view iniziale

        return view('aziende_sanitarie.index', compact(
            'anno',
            'convenzioni',
            'selectedConvenzione',
            'aziende'
        ));
    }

    public function getData(Request $request): JsonResponse {
        // legge filtro da request (inviato dalla DataTable via ajax)
        $idConvenzione = $request->input('idConvenzione') ?? session('convenzione_selezionata');

        $data = AziendaSanitaria::getAllWithConvenzioni($idConvenzione);

        return response()->json(['data' => $data]);
    }

    public function create() {
        $user = Auth::user();
        $anni = DB::table('anni')->orderBy('anno', 'desc')->get();

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        $aziendeSanitarie = DB::table('aziende_sanitarie')
            ->select('idAziendaSanitaria', 'Nome')
            ->orderBy('Nome')
            ->get();

        // carico le convenzioni
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        // per uniformità con edit, passo lotti vuoti
        $lotti = collect();

        return view('aziende_sanitarie.create', compact(
            'anni',
            'associazioni',
            'convenzioni',
            'aziendeSanitarie',
            'lotti'
        ));
    }

    public function store(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);

        $validated = $request->validate([
            'Nome'       => 'required|string|max:150',
            'Indirizzo'  => 'nullable|string|max:255',
            'mail'       => 'nullable|email|max:150',
            'note'       => 'nullable|string',

            // lotti
            'lotti' => 'array',
            'lotti.*.nomeLotto'   => 'required|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',

            // associazioni per convenzione (per indice lotto)
            'conv_assoc' => 'array',
            'conv_assoc.*' => 'array',
            'conv_assoc.*.*' => 'integer|exists:associazioni,idAssociazione',

            // opzionale: convenzioni esistenti da agganciare
            'convenzioni'   => 'array',
            'convenzioni.*' => 'integer|exists:convenzioni,idConvenzione',
        ]);

        return DB::transaction(function () use ($validated, $anno) {
            // 1) Crea Azienda
            $idAzienda = AziendaSanitaria::createSanitaria($validated);

            // 2) Crea Lotti
            $lotti = $validated['lotti'] ?? [];
            $createdConvIds = [];

            foreach ($lotti as $lotto) {
                // se hai aggiunto la colonna descrizione, salva anche quella
                if (Schema::hasColumn('aziende_sanitarie_lotti', 'descrizione')) {
                    DB::table('aziende_sanitarie_lotti')->insert([
                        'idAziendaSanitaria' => $idAzienda,
                        'nomeLotto'          => $lotto['nomeLotto'],
                        'descrizione'        => $lotto['descrizione'] ?? null,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                } else {
                    DB::table('aziende_sanitarie_lotti')->insert([
                        'idAziendaSanitaria' => $idAzienda,
                        'nomeLotto'          => $lotto['nomeLotto'],
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }

            // 3) Crea Convenzioni “Azienda – Lotto” duplicandole per associazione
            $convAssoc = $validated['conv_assoc'] ?? [];
            $nomeAzienda = $validated['Nome'];

            foreach (($validated['lotti'] ?? []) as $idx => $lotto) {
                $assIds = array_unique(array_map('intval', $convAssoc[$idx] ?? []));
                if (empty($assIds)) continue;

                foreach ($assIds as $idAss) {
                    $convName = $nomeAzienda . ' - ' . $lotto['nomeLotto'];

                    // evita duplicati: se esiste stessa convenzione per stessa associazione e anno, riusa
                    $existingId = DB::table('convenzioni')
                        ->where('Convenzione', $convName)
                        ->where('idAssociazione', $idAss)
                        ->where('idAnno', $anno)
                        ->value('idConvenzione');

                    if ($existingId) {
                        $idConv = (int)$existingId;
                    } else {
                        // ordinamento minimo semplice: 0 (o calcola max+1 per quell’associazione/anno)
                        $idConv = DB::table('convenzioni')->insertGetId([
                            'Convenzione'   => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno'        => $anno,
                            'ordinamento'   => 0,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ], 'idConvenzione');
                    }

                    $createdConvIds[] = $idConv;
                }
            }

            // 4) Aggancia all’azienda tutte le convenzioni nuove (e anche eventuali selezionate esistenti)
            $mergeExisting = array_map('intval', $validated['convenzioni'] ?? []);
            $allConv = array_values(array_unique(array_merge($createdConvIds, $mergeExisting)));

            if (!empty($allConv)) {
                AziendaSanitaria::syncConvenzioni($idAzienda, $allConv);
            }

            return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda, lotti e convenzioni creati.');
        });
    }

    public function edit(int $id) {
        $azienda = DB::table('aziende_sanitarie')
            ->where('idAziendaSanitaria', $id)
            ->first();
        abort_if(!$azienda, 404);

        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        $convenzioniSelezionate = DB::table('azienda_sanitaria_convenzione')
            ->where('idAziendaSanitaria', $id)
            ->pluck('idConvenzione')
            ->toArray();

        // Lotti dell’azienda
        $lotti = LottoAziendaSanitaria::getByAzienda($id);

        // Associazioni per il multiselect del tab 3
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        // Pre-selezioni per il tab 3: per ogni lotto, quali associazioni hanno già la convenzione "<Azienda> - <Lotto>"
        $anno = (int) session('anno_riferimento', now()->year);
        $convAssocByLotto = [];
        foreach ($lotti as $lotto) {
            $convName = $azienda->Nome . ' - ' . $lotto->nomeLotto;
            $assocIds = DB::table('convenzioni')
                ->where('Convenzione', $convName)
                ->where('idAnno', $anno)
                ->pluck('idAssociazione')
                ->toArray();
            $convAssocByLotto[$lotto->id] = $assocIds;
        }

        return view('aziende_sanitarie.edit', compact(
            'azienda',
            'convenzioni',
            'convenzioniSelezionate',
            'lotti',
            'associazioni',
            'convAssocByLotto'
        ));
    }


    public function update(Request $request, int $id) {
        $anno = (int) session('anno_riferimento', now()->year);

        $validated = $request->validate([
            'Nome'           => 'required|string|max:150',
            'Indirizzo'      => 'nullable|string|max:255',
            'mail'           => 'nullable|email|max:150',
            'note'           => 'nullable|string',

            // lotti (wizard)
            'lotti'               => 'nullable|array',
            'lotti.*.id'          => 'nullable|integer',
            'lotti.*.nomeLotto'   => 'nullable|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',
            'lotti.*._delete'     => 'nullable|boolean',

            // conv_assoc: idx lotto -> [idAssociazione...]
            'conv_assoc'     => 'nullable|array',
            'conv_assoc.*'   => 'nullable|array',
            'conv_assoc.*.*' => 'integer|exists:associazioni,idAssociazione',

            // (se mantieni un multiselect “altre convenzioni”)
            'convenzioni'    => 'nullable|array',
            'convenzioni.*'  => 'integer|exists:convenzioni,idConvenzione',
        ]);

        return DB::transaction(function () use ($validated, $id, $anno) {
            // 1) Aggiorna anagrafica
            AziendaSanitaria::updateSanitaria($id, $validated);
            $nomeAzienda = $validated['Nome'];

            // 2) Duplicati lotti (case-insensitive)
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

            // 3) Sincronizza lotti (crea/aggiorna/marca delete)
            LottoAziendaSanitaria::syncForAzienda($id, $validated['lotti'] ?? []);

            // ===== A) ELIMINA convenzioni per associazioni DESELEZIONATE =====
            $convAssoc = $validated['conv_assoc'] ?? [];
            $toDeleteConvIds = [];

            foreach (($validated['lotti'] ?? []) as $idx => $row) {
                $lottoName = trim((string)($row['nomeLotto'] ?? ''));
                if ($lottoName === '') continue;

                $convName = $nomeAzienda . ' - ' . $lottoName;

                // Tutte le convenzioni esistenti per questo "Azienda - Lotto" e anno
                $existing = DB::table('convenzioni')
                    ->where('Convenzione', $convName)
                    ->where('idAnno', $anno)
                    ->get(['idConvenzione', 'idAssociazione']);

                if ($existing->isEmpty()) {
                    // Nulla da cancellare
                    continue;
                }

                // Associazioni selezionate nel tab (se lotto NON cancellato)
                $selectedAss = !empty($row['_delete'])
                    ? [] // se il lotto è cancellato, rimuovo TUTTE le convenzioni di quel nome
                    : array_unique(array_map('intval', $convAssoc[$idx] ?? []));

                $existingByAss = $existing->groupBy('idAssociazione');

                // Deselezionate = esistenti - selezionate (oppure tutte se lotto delete)
                foreach ($existingByAss as $assId => $rows) {
                    if (!in_array((int)$assId, $selectedAss, true)) {
                        foreach ($rows as $r) {
                            $toDeleteConvIds[] = (int)$r->idConvenzione;
                        }
                    }
                }
            }

            $toDeleteConvIds = array_values(array_unique($toDeleteConvIds));

            if (!empty($toDeleteConvIds)) {
                // 1) stacca dal pivot (per tutte le aziende, per sicurezza)
                DB::table('azienda_sanitaria_convenzione')
                    ->whereIn('idConvenzione', $toDeleteConvIds)
                    ->delete();

                // 2) elimina le convenzioni
                DB::table('convenzioni')
                    ->whereIn('idConvenzione', $toDeleteConvIds)
                    ->delete();
            }

            // ===== B) CREA/RIUSA convenzioni per associazioni SELEZIONATE =====
            $createdOrEnsuredConvIds = [];

            foreach (($validated['lotti'] ?? []) as $idx => $row) {
                if (!empty($row['_delete'])) continue;

                $lottoName = trim((string)($row['nomeLotto'] ?? ''));
                if ($lottoName === '') continue;

                $assIds = array_unique(array_map('intval', $convAssoc[$idx] ?? []));
                if (empty($assIds)) continue;

                $convName = $nomeAzienda . ' - ' . $lottoName;

                foreach ($assIds as $idAss) {
                    $existingId = DB::table('convenzioni')
                        ->where('Convenzione', $convName)
                        ->where('idAssociazione', $idAss)
                        ->where('idAnno', $anno)
                        ->value('idConvenzione');

                    if ($existingId) {
                        $idConv = (int)$existingId;
                    } else {
                        $idConv = DB::table('convenzioni')->insertGetId([
                            'Convenzione'    => $convName,
                            'idAssociazione' => $idAss,
                            'idAnno'         => $anno,
                            'ordinamento'    => 0,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ], 'idConvenzione');
                    }

                    $createdOrEnsuredConvIds[] = $idConv;
                }
            }

            $createdOrEnsuredConvIds = array_values(array_unique($createdOrEnsuredConvIds));

            // ===== C) Costruisci l’elenco finale per il pivot (preservo NON-wizard) =====
            // Nomi gestiti dal wizard (per escluderli dal "preserva")
            $managedNames = collect($validated['lotti'] ?? [])
                ->filter(fn($r) => empty($r['_delete']) && !empty($r['nomeLotto']))
                ->map(fn($r) => $nomeAzienda . ' - ' . $r['nomeLotto'])
                ->values()
                ->all();

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

            // ===== D) Sync pivot (sgancia il resto)
            AziendaSanitaria::syncConvenzioni($id, $finalPivot);

            return redirect()->route('aziende-sanitarie.index')
                ->with('success', 'Azienda aggiornata. Convenzioni deselezionate eliminate, collegamenti sincronizzati.');
        });
    }


    public function destroy(int $id) {
        AziendaSanitaria::deleteSanitaria($id);
        return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda eliminata.');
    }
}
