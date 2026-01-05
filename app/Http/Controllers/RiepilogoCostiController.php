<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\RiepilogoCosti;
use App\Models\Riepilogo;
use App\Services\RipartizioneCostiService;

class RiepilogoCostiController extends Controller {
    /**
     * Pagina principale con selettori Associazione/Convenzione.
     */
    public function index(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // Associazione selezionata
        if ($isElevato) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            $selectedAssoc = session('associazione_selezionata')
                ?? $request->integer('idAssociazione')
                ?? optional($associazioni->first())->idAssociazione;
        } else {
            $associazioni  = collect(); // non mostriamo la select per utenti non elevati
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        // Convenzioni per l’associazione scelta
        $convenzioni  = collect();
        $selectedConv = 'TOT';

        if ($selectedAssoc) {
            $convenzioni = DB::table('convenzioni')
                ->select('idConvenzione', 'Convenzione')
                ->where('idAssociazione', $selectedAssoc)
                ->where('idAnno', $anno)
                ->orderBy('ordinamento')
                ->orderBy('idConvenzione')
                ->get();

            $reqConv = $request->input('idConvenzione');
            if ($reqConv !== null && $reqConv !== '' && $reqConv !== 'TOT') {
                $selectedConv = (int) $reqConv;
            }
        }

        return view('riepilogo_costi.index', compact(
            'anno',
            'isElevato',
            'associazioni',
            'selectedAssoc',
            'convenzioni',
            'selectedConv'
        ));
    }

    /**
     * Dati della SINGOLA sezione (tipologia 2..11).
     * GET: idAssociazione, idConvenzione ('TOT'|numero)
     * Ritorna: [{ idVoceConfig, descrizione, ordinamento?, preventivo, consuntivo, scostamento }]
     */
    public function getSezione(Request $request, int $idTipologia) {
        $anno      = (int) session('anno_riferimento', now()->year);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?: (int) session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['data' => []]);
        }

        $idConvenzione = $request->input('idConvenzione'); // 'TOT' | int | null

        // 1) Proviamo la logica attuale del Model
        $rows = RiepilogoCosti::getByTipologia(
            $idTipologia,
            $anno,
            $idAssociazione,
            $idConvenzione
        );

        // 2) Se NON TOT e tutti i preventivi risultano a 0 -> fallback ai preventivi di TOTALE
        if ($idConvenzione !== 'TOT' && $this->allPreventiviZero($rows)) {
            $rows = $this->buildSezioneWithPrevFromTotale(
                $idTipologia,
                $anno,
                $idAssociazione,
                (int) $idConvenzione
            );
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * Costruisce le righe della sezione usando:
     * - PREVENTIVI presi da 'TOT'
     * - CONSUNTIVI presi dalla convenzione specifica
     * - scostamento calcolato su questi due
     */
    private function buildSezioneWithPrevFromTotale(
        int $idTipologia,
        int $anno,
        int $idAssociazione,
        int $idConvenzione
    ): array {
        // Voci della tipologia
        $voci = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione', 'ordinamento')
            ->where('idTipologiaRiepilogo', $idTipologia)
            ->where('attivo', 1)
            ->orderBy('ordinamento')
            ->orderBy('id')
            ->get();

        // idRiepilogo pivot (crea se manca)
        $riep = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        $idRiepilogo = $riep
            ? (int)$riep->idRiepilogo
            : DB::table('riepiloghi')->insertGetId([
                'idAssociazione' => $idAssociazione,
                'idAnno'         => $anno,
                'created_at'     => now(),
                'updated_at'     => now(),
            ], 'idRiepilogo');

        // PREVENTIVI da TOTALE
        $prevTot = DB::table('riepilogo_dati')
            ->select('idVoceConfig', DB::raw('COALESCE(SUM(preventivo),0) AS preventivo'))
            ->where('idRiepilogo', $idRiepilogo)
            ->where('idConvenzione', 'TOT')
            ->whereIn('idVoceConfig', $voci->pluck('id'))
            ->groupBy('idVoceConfig')
            ->get()
            ->keyBy('idVoceConfig');

        // CONSUNTIVI per la convenzione specifica (via servizio)
        $mapCons = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        // Compose righe
        $out = [];
        foreach ($voci as $v) {
            $idVoce = (int)$v->id;

            $prev = (float)($prevTot[$idVoce]->preventivo ?? 0);
            $cons = (float)($mapCons[$idVoce][$idConvenzione] ?? 0);

            $scost = $prev != 0.0 ? (($cons - $prev) / $prev) * 100 : 0.0;

            $out[] = [
                'idVoceConfig' => $idVoce,
                'descrizione'  => $v->descrizione,
                'preventivo'   => round($prev, 2),
                'consuntivo'   => round($cons, 2),
                'scostamento'  => number_format($scost, 2, ',', '.') . '%',
            ];
        }

        return $out;
    }

    /**
     * true se non ci sono righe o tutti i preventivi sono 0
     */
    private function allPreventiviZero($rows): bool {
        if (empty($rows)) return true;
        foreach ($rows as $r) {
            if ((float)($r->preventivo ?? $r['preventivo'] ?? 0) > 0) return false;
        }
        return true;
    }

    /**
     * Salva/aggiorna il PREVENTIVO per una voce (tipologie 2..11).
     * Richiede convenzione specifica (non TOT).
     * Usata se salvi da AJAX inline (se lo manterrai), non da edit.blade.
     */
    public function savePreventivo(Request $request) {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // accetto anche alias 'voce_id'
        if ($request->filled('voce_id') && !$request->filled('idVoceConfig')) {
            $request->merge(['idVoceConfig' => $request->input('voce_id')]);
        }

        $data = $request->validate([
            'idAssociazione' => $isElevato ? 'required|integer|exists:associazioni,idAssociazione' : 'nullable',
            'idConvenzione'  => 'required',
            'idVoceConfig'   => 'required|integer|exists:riepilogo_voci_config,id',
            'preventivo'     => 'required|numeric|min:0',
        ]);
        $idAssociazione = $isElevato ? (int) $data['idAssociazione'] : (int) $user->IdAssociazione;

        // blocco su TOT
        if ($data['idConvenzione'] === 'TOT' || $data['idConvenzione'] === null || $data['idConvenzione'] === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Seleziona una convenzione specifica (non TOT) per inserire il preventivo.',
            ], 422);
        }
        $idConvenzione = (int) $data['idConvenzione'];

        // riepilogo pivot (crea se manca)
        $idRiepilogo = Riepilogo::createRiepilogo($idAssociazione, $anno);

        // upsert del valore (consuntivo lasciato 0: lo calcoleremo altrove)
        DB::table('riepilogo_dati')->updateOrInsert(
            [
                'idRiepilogo'   => $idRiepilogo,
                'idVoceConfig'  => (int) $data['idVoceConfig'],
                'idConvenzione' => $idConvenzione,
            ],
            [
                'preventivo' => (float) $data['preventivo'],
                'consuntivo' => 0.0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Crea (se manca) la riga per (associazione, anno, convenzione, voce) e
     * reindirizza alla edit classica per ID riga.
     * Linkata dal bottone "Modifica" nella tabella (index).
     */
    public function ensureAndEditByKeys(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?: (int) session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        $data = $request->validate([
            'idConvenzione'  => 'required|integer',
            'idVoceConfig'   => 'required|integer|exists:riepilogo_voci_config,id',
        ]);

        if (!$idAssociazione) {
            return back()->with('error', 'Associazione non selezionata.');
        }

        $idConvenzione = (int) $data['idConvenzione'];
        $idVoceConfig  = (int) $data['idVoceConfig'];

        // crea/recupera riepilogo pivot
        $idRiepilogo = Riepilogo::createRiepilogo($idAssociazione, $anno);

        // crea (se manca) la riga e ottieni l'ID
        $rigaId = Riepilogo::ensureRiga(
            $idRiepilogo,
            $idVoceConfig,
            $idConvenzione
        );

        // vai alla classica edit view della singola riga
        return redirect()->route('riepilogo.costi.edit', $rigaId);
    }

    /**
     * Edit "classico" per ID riga (riepilogo_dati.id).
     * Mostra preventivo (editabile) e consuntivo (readonly).
     */
    public function edit(int $id) {
        $riga = Riepilogo::getRigaDettaglio($id);

        if (!$riga) abort(404, 'Voce non trovata');

        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int)$riga->idAssociazione !== (int)$user->IdAssociazione) {
            abort(403, 'Accesso negato');
        }

        // 🔹 Consuntivo calcolato dalle ripartizioni (distinta)
        $mapCons = RipartizioneCostiService::consuntiviPerVoceByConvenzione((int)$riga->idAssociazione, (int)$riga->idAnno);

        // se l’edit è sempre su convenzione specifica (come da tuo flow ensureAndEdit), prendi quel valore:
        $consCalcolato = (float)($mapCons[(int)$riga->idVoceConfig][(int)$riga->idConvenzione] ?? 0.0);

        // se vuoi gestire anche eventuale “TOT” (non editabile), puoi sommare tutte le convenzioni:
        // $consCalcolato = $riga->idConvenzione === 'TOT'
        //     ? array_sum($mapCons[(int)$riga->idVoceConfig] ?? [])
        //     : (float)($mapCons[(int)$riga->idVoceConfig][(int)$riga->idConvenzione] ?? 0.0);

        return view('riepilogo_costi.edit', [
            'id'               => $riga->id,
            'anno'             => (int) $riga->idAnno,
            'idAssociazione'   => (int) $riga->idAssociazione,
            'nomeAssociazione' => DB::table('associazioni')->where('idAssociazione', $riga->idAssociazione)->value('Associazione'),
            'idConvenzione'    => (int) $riga->idConvenzione,
            'nomeConvenzione'  => $riga->convenzione_descrizione,
            'voceId'           => (int) $riga->idVoceConfig,
            'voceDescrizione'  => $riga->voce_descrizione,
            'preventivo'       => (float) $riga->preventivo,     // ← dal DB (editabile)
            'consuntivo'       => $consCalcolato,                // ← CALCOLATO (sola lettura)
        ]);
    }

    /**
     * Update by ID riga (salva SOLO preventivo).
     */
    public function update(Request $request, int $voceId) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $data = $request->validate([
            'idAssociazione' => $isElevato ? 'required|integer|exists:associazioni,idAssociazione' : 'nullable',
            'idConvenzione'  => 'required|integer',
            'preventivo'     => 'required|numeric|min:0',
            // consuntivo in sola lettura lato UI; lo accettiamo solo se vuoi passarlo
            'consuntivo'     => 'nullable|numeric|min:0',
        ]);

        $idAssociazione = $isElevato ? (int)$data['idAssociazione'] : (int)$user->IdAssociazione;
        $idConvenzione  = (int)$data['idConvenzione'];

        // crea/recupera il riepilogo pivot
        $riepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        $idRiepilogo = $riepilogo
            ? (int)$riepilogo->idRiepilogo
            : DB::table('riepiloghi')->insertGetId([
                'idAssociazione' => $idAssociazione,
                'idAnno'         => $anno,
                'created_at'     => now(),
                'updated_at'     => now(),
            ], 'idRiepilogo');

        // upsert valore (consuntivo opzionale, di default 0 o quello passato in read-only)
        DB::table('riepilogo_dati')->updateOrInsert(
            [
                'idRiepilogo'   => $idRiepilogo,
                'idVoceConfig'  => $voceId,
                'idConvenzione' => $idConvenzione,
            ],
            [
                'preventivo' => (float)$data['preventivo'],
                'consuntivo' => isset($data['consuntivo']) ? (float)$data['consuntivo'] : 0.0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Se è una richiesta AJAX, rispondi JSON; altrimenti redirect alla index
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('riepilogo.costi', [
                'idAssociazione' => $idAssociazione,
                'idConvenzione'  => $idConvenzione,
            ])
            ->with('success', 'Voce aggiornata correttamente.');
    }

    // GET: form doppio campo
    public function editTelefonia(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $data = $request->validate([
            'idAssociazione' => $isElevato ? 'required|integer' : 'nullable',
            'idConvenzione'  => 'required|integer',
            'idFissa'        => 'required|integer',
            'idMobile'       => 'required|integer',
        ]);

        $idAssociazione = $isElevato ? (int)$data['idAssociazione'] : (int)$user->IdAssociazione;
        $idConvenzione  = (int)$data['idConvenzione'];
        $idFissa        = (int)$data['idFissa'];
        $idMobile       = (int)$data['idMobile'];

        // pivot riepilogo
        $riepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if (!$riepilogo) {
            // crea se manca
            $riepilogoId = DB::table('riepiloghi')->insertGetId([
                'idAssociazione' => $idAssociazione,
                'idAnno' => $anno,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $riepilogoId = (int)$riepilogo->idRiepilogo;
        }

        // prendo preventivi esistenti
        $rows = DB::table('riepilogo_dati')
            ->where('idRiepilogo', $riepilogoId)
            ->where('idConvenzione', $idConvenzione)
            ->whereIn('idVoceConfig', [$idFissa, $idMobile])
            ->get()->keyBy('idVoceConfig');

        $prevFissa  = (float)($rows[$idFissa]->preventivo  ?? 0);
        $prevMobile = (float)($rows[$idMobile]->preventivo ?? 0);

        // consuntivi calcolati (read-only)
        $mapCons = \App\Services\RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);
        $consFissa  = (float)($mapCons[$idFissa][$idConvenzione]  ?? 0);
        $consMobile = (float)($mapCons[$idMobile][$idConvenzione] ?? 0);

        $nomeAssociazione = DB::table('associazioni')->where('idAssociazione', $idAssociazione)->value('Associazione');
        $nomeConvenzione  = DB::table('convenzioni')->where('idConvenzione', $idConvenzione)->value('Convenzione');

        return view('riepilogo_costi.edit_telefonia', compact(
            'anno',
            'idAssociazione',
            'nomeAssociazione',
            'idConvenzione',
            'nomeConvenzione',
            'idFissa',
            'idMobile',
            'prevFissa',
            'prevMobile',
            'consFissa',
            'consMobile'
        ));
    }

    // POST: salva i due preventivi
    public function updateTelefonia(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $data = $request->validate([
            'idAssociazione' => $isElevato ? 'required|integer' : 'nullable',
            'idConvenzione'  => 'required|integer',
            'idFissa'        => 'required|integer',
            'idMobile'       => 'required|integer',
            'preventivo_fissa'  => 'required|numeric|min:0',
            'preventivo_mobile' => 'required|numeric|min:0',
        ]);

        $idAssociazione = $isElevato ? (int)$data['idAssociazione'] : (int)$user->IdAssociazione;
        $idConvenzione  = (int)$data['idConvenzione'];
        $idFissa        = (int)$data['idFissa'];
        $idMobile       = (int)$data['idMobile'];

        // pivot (crea se manca)
        $riepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)->first();

        $idRiepilogo = $riepilogo
            ? (int)$riepilogo->idRiepilogo
            : DB::table('riepiloghi')->insertGetId([
                'idAssociazione' => $idAssociazione,
                'idAnno' => $anno,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        // upsert singole righe
        foreach (
            [
                [$idFissa,  (float)$data['preventivo_fissa']],
                [$idMobile, (float)$data['preventivo_mobile']],
            ] as [$idVoce, $prev]
        ) {
            DB::table('riepilogo_dati')->updateOrInsert(
                [
                    'idRiepilogo'  => $idRiepilogo,
                    'idVoceConfig' => $idVoce,
                    'idConvenzione' => $idConvenzione,
                ],
                [
                    'preventivo' => $prev,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return redirect()
            ->route('riepilogo.costi', ['idAssociazione' => $idAssociazione, 'idConvenzione' => $idConvenzione])
            ->with('success', 'Utenze telefoniche aggiornate.');
    }

    // GET: form tabellare per TUTTE le voci di una sezione (tipologia)
    public function editPreventiviSezione(Request $request, int $sezione) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate');

        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?: (int) session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        abort_if(!$idAssociazione, 422, 'Associazione non selezionata');

        // Convenzioni per combo
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get();

        // Convenzione selezionata (solo numerica; escludo 'TOT' e stringhe)
        $rawConv       = $request->input('idConvenzione', session('convenzione_selezionata'));
        $idConvenzione = (is_numeric($rawConv) && (int) $rawConv > 0) ? (int) $rawConv : null;

        // Voci attive della sezione
        $voci = DB::table('riepilogo_voci_config')
            ->select('id', 'descrizione')
            ->where('idTipologiaRiepilogo', $sezione)
            ->where('attivo', 1)
            ->orderBy('ordinamento')->orderBy('id')
            ->get();

        // Pivot riepilogo (crea se manca)
        $idRiepilogo = RiepilogoCosti::getOrCreateRiepilogo($idAssociazione, $anno);

        // Preventivi esistenti per la convenzione selezionata: [idVoceConfig => preventivo]
        $preventivi = [];
        if ($idConvenzione) {
            $preventivi = DB::table('riepilogo_dati')
                ->where('idRiepilogo', $idRiepilogo)
                ->where('idConvenzione', $idConvenzione)
                ->pluck('preventivo', 'idVoceConfig')
                ->map(fn($v) => (float) $v)
                ->toArray();
        }

        // (solo display) consuntivi indiretti per la convenzione selezionata
        $indirettiByVoce = [];
        if ($idConvenzione) {
            $mapCons = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);
            foreach ($voci as $v) {
                $idV = (int) $v->id;
                $indirettiByVoce[$idV] = (float) ($mapCons[$idV][$idConvenzione] ?? 0.0);
            }
        }

        // Etichetta sezione
        $sezioniMap = [
            2 => 'Automezzi',
            3 => 'Attrezzatura Sanitaria',
            4 => 'Telecomunicazioni',
            5 => 'Costi gestione struttura',
            6 => 'Costo del personale',
            7 => 'Materiale sanitario di consumo',
            8 => 'Costi amministrativi',
            9 => 'Quote di ammortamento',
            10 => 'Beni Strumentali < 516,00 €',
        ];

        return view('riepilogo_costi.edit_preventivi_sezione', [
            'sezione'         => $sezione,
            'sezioneLabel'    => $sezioniMap[$sezione] ?? "Sezione $sezione",
            'anno'            => $anno,
            'idAssociazione'  => $idAssociazione,
            'convenzioni'     => $convenzioni,
            'idConvenzione'   => $idConvenzione,     // int|null (mai 'TOT')
            'voci'            => $voci,
            'preventivi'      => $preventivi,        // [idVoce => float]
            'indirettiByVoce' => $indirettiByVoce,   // [idVoce => float] (readonly)
        ]);
    }


    // POST: salvataggio bulk dei preventivi per la sezione
    public function updatePreventiviSezione(Request $request, int $sezione) {
        // NOTA: permettiamo la virgola, quindi NIENTE rule "numeric" sui campi righe.*.preventivo
        $data = $request->validate([
            'idAssociazione'            => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'                    => 'required|integer',
            'idConvenzione'             => 'required|integer|exists:convenzioni,idConvenzione',
            'righe'                     => 'required|array',
            'righe.*.preventivo'        => 'nullable',   // ← niente numeric per accettare "1,23"
        ]);

        $idAssociazione = (int) $data['idAssociazione'];
        $anno           = (int) $data['idAnno'];
        $idConvenzione  = (int) $data['idConvenzione'];
        $righeInput     = $data['righe'];               // array: [idVoce => ['preventivo' => '1,23']]

        // pivot riepilogo
        $idRiepilogo = RiepilogoCosti::getOrCreateRiepilogo($idAssociazione, $anno);

        DB::beginTransaction();
        try {
            foreach ($righeInput as $idVoce => $row) {
                $idVoce      = (int) $idVoce;
                $rawPrev     = $row['preventivo'] ?? null;
                $preventivo  = $this->toDecimalOrZero($rawPrev);  // normalizza "1.234,56" -> 1234.56

                DB::table('riepilogo_dati')->updateOrInsert(
                    [
                        'idRiepilogo'   => $idRiepilogo,
                        'idVoceConfig'  => $idVoce,
                        'idConvenzione' => $idConvenzione,
                    ],
                    [
                        'preventivo' => $preventivo,
                        // il consuntivo NON si tocca qui (è calcolato altrove)
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors('Errore nel salvataggio: ' . $e->getMessage())->withInput();
        }

        return redirect()
            ->route('riepilogo.costi', [
                'idAssociazione' => $idAssociazione,
                'idConvenzione'  => $idConvenzione
            ])
            ->with('success', 'Preventivi della sezione aggiornati correttamente.');
    }


    /**
     * Normalizza un input numerico (supporta "1.234,56" o "1234,56" o "1234.56").
     * Ritorna SEMPRE un float >= 0. Se non numerico/empty -> 0.00
     */
    private function toDecimalOrZero($v): float {
        if ($v === null) return 0.0;
        $s = trim((string) $v);
        if ($s === '') return 0.0;

        // Rimuovi spazi, NBSP e punti come separatori migliaia
        $s = preg_replace('/[.\s\x{00A0}]/u', '', $s);
        // Sostituisci la virgola con il punto come separatore decimale
        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? max(0.0, (float) $s) : 0.0;
    }

    public function editFormazione(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $data = $request->validate([
            'idAssociazione' => $isElevato ? 'required|integer' : 'nullable',
            'idConvenzione'  => 'required|integer',
            'idA'            => 'required|integer', // 6010
            'idB'            => 'required|integer', // 6011
        ]);

        $idAss = $isElevato ? (int)$data['idAssociazione'] : (int)$user->IdAssociazione;
        $idConv = (int)$data['idConvenzione'];
        $idA   = (int)$data['idA'];
        $idB   = (int)$data['idB'];

        // pivot riepilogo
        $riepilogoId = RiepilogoCosti::getOrCreateRiepilogo($idAss, $anno);

        // preventivi esistenti
        $rows = DB::table('riepilogo_dati')
            ->where('idRiepilogo', $riepilogoId)
            ->where('idConvenzione', $idConv)
            ->whereIn('idVoceConfig', [$idA, $idB])
            ->pluck('preventivo', 'idVoceConfig');

        $prevA = (float)($rows[$idA] ?? 0);
        $prevB = (float)($rows[$idB] ?? 0);

        // consuntivi calcolati (read-only)
        $mapCons = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAss, $anno);
        $consA = (float)($mapCons[$idA][$idConv] ?? 0);
        $consB = (float)($mapCons[$idB][$idConv] ?? 0);

        $nomeAss = DB::table('associazioni')->where('idAssociazione', $idAss)->value('Associazione');
        $nomeConv = DB::table('convenzioni')->where('idConvenzione', $idConv)->value('Convenzione');

        return view('riepilogo_costi.edit_formazione_merge', [
            'anno' => $anno,
            'idAssociazione' => $idAss,
            'nomeAssociazione' => $nomeAss,
            'idConvenzione' => $idConv,
            'nomeConvenzione' => $nomeConv,
            'idA' => $idA,
            'idB' => $idB,
            'prevA' => $prevA,
            'prevB' => $prevB,
            'consA' => $consA,
            'consB' => $consB,
            'labelMerge' => 'formazione allegati A + DAE + RDAE',
        ]);
    }

    public function updateFormazione(Request $request) {
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $data = $request->validate([
            'idAssociazione' => $isElevato ? 'required|integer' : 'nullable',
            'idConvenzione'  => 'required|integer',
            'idA'            => 'required|integer',
            'idB'            => 'required|integer',
            'preventivo_a'   => 'required', // accetto virgola
            'preventivo_b'   => 'required',
        ]);

        $idAss = $isElevato ? (int)$data['idAssociazione'] : (int)$user->IdAssociazione;
        $idConv = (int)$data['idConvenzione'];
        $idA   = (int)$data['idA'];
        $idB   = (int)$data['idB'];

        $toDecimal = function ($v): float {
            if ($v === null) return 0.0;
            $s = trim((string)$v);
            if ($s === '') return 0.0;
            $s = preg_replace('/[.\s\x{00A0}]/u', '', $s);
            $s = str_replace(',', '.', $s);
            return is_numeric($s) ? max(0.0, (float)$s) : 0.0;
        };

        $prevA = $toDecimal($data['preventivo_a']);
        $prevB = $toDecimal($data['preventivo_b']);

        $riepilogoId = RiepilogoCosti::getOrCreateRiepilogo($idAss, $anno);

        DB::table('riepilogo_dati')->updateOrInsert(
            ['idRiepilogo' => $riepilogoId, 'idVoceConfig' => $idA, 'idConvenzione' => $idConv],
            ['preventivo' => $prevA, 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('riepilogo_dati')->updateOrInsert(
            ['idRiepilogo' => $riepilogoId, 'idVoceConfig' => $idB, 'idConvenzione' => $idConv],
            ['preventivo' => $prevB, 'updated_at' => now(), 'created_at' => now()]
        );

        return redirect()
            ->route('riepilogo.costi', ['idAssociazione' => $idAss, 'idConvenzione' => $idConv])
            ->with('success', 'Formazione (A + DAE + RDAE) aggiornata.');
    }

    public function getSummary(Request $request) {
        $anno      = (int) session('anno_riferimento', now()->year);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?: (int) session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['ok' => false, 'message' => 'Associazione mancante']);
        }

        $idConvenzione = $request->input('idConvenzione'); // 'TOT' | int | null
        if ($idConvenzione === null || $idConvenzione === '') $idConvenzione = 'TOT';

        $idsTipologie = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

        // PREVENTIVI: dal DB (riepilogo_dati) aggregati per tipologia
        $idRiepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->value('idRiepilogo');

        $prevByTipologia = array_fill_keys($idsTipologie, 0.0);

        if ($idRiepilogo) {
            $q = DB::table('riepilogo_dati as rd')
                ->join('riepilogo_voci_config as vc', 'vc.id', '=', 'rd.idVoceConfig')
                ->where('rd.idRiepilogo', $idRiepilogo)
                ->whereIn('vc.idTipologiaRiepilogo', $idsTipologie);

            if ($idConvenzione === 'TOT') {
                // TOT = somma su tutte le convenzioni (come già fai nel model) :contentReference[oaicite:2]{index=2}
                $q->select('vc.idTipologiaRiepilogo', DB::raw('COALESCE(SUM(rd.preventivo),0) AS tot'))
                    ->groupBy('vc.idTipologiaRiepilogo');
            } else {
                $q->where('rd.idConvenzione', (int)$idConvenzione)
                    ->select('vc.idTipologiaRiepilogo', DB::raw('COALESCE(SUM(rd.preventivo),0) AS tot'))
                    ->groupBy('vc.idTipologiaRiepilogo');
            }

            foreach ($q->get() as $r) {
                $prevByTipologia[(int)$r->idTipologiaRiepilogo] = (float)$r->tot;
            }
        }

        // CONSUNTIVI: via servizio (pesante -> metti cache lato service, consigliato)
        // Questo è lo stesso “motore” che usi in getByTipologia() 
        $mapCons = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        // mappa: tipologia -> lista idVoceConfig di quella tipologia (attive)
        $vociByTip = DB::table('riepilogo_voci_config')
            ->whereIn('idTipologiaRiepilogo', $idsTipologie)
            ->where('attivo', 1)
            ->select('id', 'idTipologiaRiepilogo')
            ->get()
            ->groupBy('idTipologiaRiepilogo');

        // convenzioni (servono per sommare TOT correttamente, come fai nel model) 
        $convIds = array_keys(RipartizioneCostiService::convenzioni($idAssociazione, $anno));

        $consByTipologia = array_fill_keys($idsTipologie, 0.0);

        foreach ($idsTipologie as $tip) {
            $rows = $vociByTip[$tip] ?? collect();
            $sum = 0.0;

            foreach ($rows as $v) {
                $idVoce = (int)$v->id;
                if ($idConvenzione === 'TOT') {
                    $row = $mapCons[$idVoce] ?? [];
                    foreach ($convIds as $cid) {
                        $sum += (float)($row[$cid] ?? 0.0);
                    }
                } else {
                    $sum += (float)($mapCons[$idVoce][(int)$idConvenzione] ?? 0.0);
                }
            }

            $consByTipologia[$tip] = round($sum, 2);
        }

        // output
        $out = [
            'ok' => true,
            'anno' => $anno,
            'idAssociazione' => (int)$idAssociazione,
            'idConvenzione' => $idConvenzione,
            'sezioni' => [],
            'totale' => ['preventivo' => 0.0, 'consuntivo' => 0.0, 'scostamento' => 0.0],
        ];

        $totPrev = 0.0;
        $totCons = 0.0;

        foreach ($idsTipologie as $tip) {
            $prev = round((float)($prevByTipologia[$tip] ?? 0.0), 2);
            $cons = round((float)($consByTipologia[$tip] ?? 0.0), 2);
            $scos = $prev != 0.0 ? round((($cons - $prev) / $prev) * 100, 2) : 0.0;

            $out['sezioni'][(string)$tip] = [
                'preventivo' => $prev,
                'consuntivo' => $cons,
                'scostamento' => $scos,
            ];

            $totPrev += $prev;
            $totCons += $cons;
        }

        $out['totale']['preventivo'] = round($totPrev, 2);
        $out['totale']['consuntivo'] = round($totCons, 2);
        $out['totale']['scostamento'] = $totPrev != 0.0 ? round((($totCons - $totPrev) / $totPrev) * 100, 2) : 0.0;

        return response()->json($out);
    }
}
