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

        // dd($associazioni, $convenzioni, $selectedAssoc, $selectedConv);


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
        $anno = (int) session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?: (int) session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['data' => []]);
        }

        $idConvenzione = $request->input('idConvenzione'); // 'TOT' | int

        $rows = RiepilogoCosti::getByTipologia(
            $idTipologia,
            $anno,
            $idAssociazione,
            $idConvenzione
        );

        // dd($rows);

        return response()->json(['data' => $rows]);
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
}
