<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Riepilogo;

class RiepilogoController extends Controller
{
    /**
     * Voci calcolate (non editabili manualmente):
     * - 1002: Ore volontari (consuntivo calcolato)
     * - 1007: Ore autisti/barellieri (consuntivo calcolato)
     * - 1023: KM totali per associazione (in TOTALE) / per convenzione (in convenzione)
     * - 1024: KM per la singola convenzione (vuoto in TOTALE)
     * - 1025: Numero servizi per associazione (in TOTALE)
     * - 1026: Numero servizi per singola convenzione (vuoto in TOTALE)
     *
     * Nota: i nomi costanti lato Model devono esistere. Se i tuoi nomi differiscono,
     * aggiorna questo array di conseguenza.
     */
    private const VOCI_CALCOLATE = [
        Riepilogo::VOCE_ID_ORE_VOLONTARI,
        Riepilogo::VOCE_ID_ORE_AUTISTI,
        Riepilogo::VOCE_KM_ASSOC,                 // 1023
        Riepilogo::VOCE_KM_CONVENZIONE_ONLY,      // 1024
        Riepilogo::VOCE_SERVIZI_ASSOC,            // 1025
        Riepilogo::VOCE_SERVIZI_CONVENZIONE_ONLY, // 1026
    ];

    /* =======================
       INDEX + DATATABLE
       ======================= */

    public function index(Request $request)
    {
        $user      = Auth::user();
        $anno      = (int) session('anno_riferimento', now()->year);
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // Associazioni visibili
        $associazioni = collect();
        if ($isElevato) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();
        }

        // priorità: sessione -> query -> default
        $selectedAssoc = $isElevato
            ? (session('associazione_selezionata')
                ?? $request->integer('idAssociazione')
                ?? optional($associazioni->first())->idAssociazione)
            : (int) $user->IdAssociazione;

        // Convenzioni per la select iniziale
        $convenzioni  = collect();
        $selectedConv = 'TOT';

        if ($selectedAssoc) {
            $convenzioni = Riepilogo::getConvenzioniForAssAnno((int) $selectedAssoc, $anno);

            $reqConv = $request->input('idConvenzione');
            if ($reqConv !== null && $reqConv !== 'TOT' && $reqConv !== '') {
                $selectedConv = (int) $reqConv;
            }
        }

        return view('riepiloghi.index', compact(
            'associazioni',
            'selectedAssoc',
            'convenzioni',
            'selectedConv',
            'anno',
            'isElevato'
        ));
    }

    /** JSON per DataTables */
    public function getData(Request $request)
    {
        $user      = Auth::user();
        $anno      = (int) session('anno_riferimento', now()->year);
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $idAssociazione = $isElevato
            ? ($request->integer('idAssociazione') ?? session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['data' => []]);
        }

        $idConvenzione = $request->input('idConvenzione'); // 'TOT' | null | int

        $rows = Riepilogo::getForDataTable($anno, (int) $idAssociazione, $idConvenzione);

        return response()->json(['data' => $rows]);
    }

    /* =======================
       SALVATAGGI PUNTUALI
       ======================= */

    /** Salva PREVENTIVO per (voce config + singola convenzione) */
    public function savePreventivo(Request $request)
    {
        $user      = Auth::user();
        $anno      = (int) session('anno_riferimento', now()->year);
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // 'voce_id' come alias di 'idVoceConfig'
        if ($request->filled('voce_id') && !$request->filled('idVoceConfig')) {
            $request->merge(['idVoceConfig' => $request->input('voce_id')]);
        }

        $data = $request->validate([
            'idAssociazione' => $isElevato ? 'nullable|integer|exists:associazioni,idAssociazione' : 'nullable',
            'idConvenzione'  => 'required',
            'idVoceConfig'   => 'required|integer|exists:riepilogo_voci_config,id',
            'preventivo'     => 'required|numeric|min:0',
        ]);

        $idAssociazione = $isElevato
            ? (int) ($data['idAssociazione'] ?? session('associazione_selezionata'))
            : (int) $user->IdAssociazione;

        // blocco salvataggi su TOT
        if ($data['idConvenzione'] === 'TOT' || $data['idConvenzione'] === null || $data['idConvenzione'] === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Seleziona una convenzione specifica (non TOT) per inserire il preventivo.',
            ], 422);
        }

        // blocca voci calcolate
        if (in_array((int)$data['idVoceConfig'], self::VOCI_CALCOLATE, true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Questa voce è calcolata automaticamente e non è modificabile.',
            ], 422);
        }

        $idConvenzione = (int) $data['idConvenzione'];

        // Creo/recupero il riepilogo pivot
        $idRiepilogo = Riepilogo::createRiepilogo($idAssociazione, $anno);

        // Upsert del valore
        Riepilogo::upsertValore(
            $idRiepilogo,
            (int) $data['idVoceConfig'],
            $idConvenzione,
            (float) $data['preventivo'],
            0.0
        );

        return response()->json(['ok' => true]);
    }

    /* =======================
       AJAX CONVENZIONI
       ======================= */

    /** AJAX: convenzioni per associazione + anno */
    public function convenzioniByAssociazione(int $idAssociazione, Request $request)
    {
        $anno = $request->integer('anno') ?: (int) session('anno_riferimento', now()->year);

        return response()->json(
            Riepilogo::getConvenzioniForAssAnno($idAssociazione, $anno)
        );
    }

    /* =======================
       EDIT “TESTATA” (COMPAT)
       ======================= */

    public function edit(int $riepilogoId)
    {
        $row = Riepilogo::getSingle($riepilogoId);
        if (!$row) abort(404, 'Riepilogo non trovato');

        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        if (!$isElevato && (int) $row->idAssociazione !== (int) $user->IdAssociazione) {
            abort(403, 'Accesso negato');
        }

        session(['associazione_selezionata' => (int) $row->idAssociazione]);

        return view('riepiloghi.edit', ['riepilogo' => $row]);
    }

    /* =======================
       OPERAZIONI SU SINGOLA RIGA
       ======================= */

    /** EDIT con ID riga */
    public function editRiga(int $id)
    {
        $riga = Riepilogo::getRigaDettaglio($id);
        if (!$riga) abort(404, 'Voce non trovata');

        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int) $riga->idAssociazione !== (int) $user->IdAssociazione) {
            abort(403, 'Accesso negato');
        }

        return view('riepiloghi.edit_riga', compact('riga'));
    }

    /**
     * EDIT “by keys”:
     * - se la riga NON esiste, la crea (preventivo/consuntivo = 0)
     * - poi reindirizza a editRiga($id)
     */
    public function ensureAndEditRiga(int $idRiepilogo, int $idVoceConfig, int $idConvenzione)
    {
        $idRiga = Riepilogo::ensureRiga($idRiepilogo, $idVoceConfig, $idConvenzione);
        return redirect()->route('riepiloghi.riga.edit', $idRiga);
    }

    /** UPDATE by ID */
    public function updateRiga(Request $request, int $id)
    {
        $data = $request->validate([
            'preventivo' => 'required|numeric|min:0',
            // 'consuntivo' => 'nullable|numeric|min:0',
        ]);

        $riga = Riepilogo::getRigaDettaglio($id);
        if (!$riga) abort(404, 'Voce non trovata');

        // blocca update su voci calcolate
        if (in_array((int)$riga->idVoceConfig, self::VOCI_CALCOLATE, true)) {
            return back()->with('error', 'Questa voce è calcolata automaticamente e non è modificabile.');
        }

        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int) $riga->idAssociazione !== (int) $user->IdAssociazione) {
            abort(403, 'Accesso negato');
        }

        Riepilogo::updateRigaValori($id, [
            'preventivo' => (float) $data['preventivo'],
            // 'consuntivo' => (float) ($data['consuntivo'] ?? $riga->consuntivo),
        ]);

        return redirect()->route('riepiloghi.index')->with('success', 'Voce aggiornata correttamente.');
    }

    /** DELETE by ID */
    public function destroyRiga(int $id)
    {
        $riga = Riepilogo::getRigaDettaglio($id);
        if (!$riga) abort(404, 'Voce non trovata');

        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int) $riga->idAssociazione !== (int) $user->IdAssociazione) {
            abort(403, 'Accesso negato');
        }

        Riepilogo::deleteRiga($id);

        return redirect()->route('riepiloghi.index')->with('success', 'Riga eliminata correttamente.');
    }

    /** ensure (by keys) + redirect a edit */
    public function ensureAndRedirectToEdit(Request $request)
    {
        $data = $request->validate([
            'idRiepilogo'   => 'required|integer|exists:riepiloghi,idRiepilogo',
            'voce_id'       => 'required|integer|exists:riepilogo_voci_config,id',
            'idConvenzione' => 'required|integer', // niente TOT qui
        ]);

        $riepilogo = Riepilogo::getSingle((int)$data['idRiepilogo']);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int)$riepilogo->idAssociazione !== (int)$user->IdAssociazione) {
            abort(403, 'Accesso negato');
        }

        $rigaId = Riepilogo::ensureRiga(
            (int)$data['idRiepilogo'],
            (int)$data['voce_id'],
            (int)$data['idConvenzione']
        );

        return redirect()->route('riepiloghi.riga.edit', $rigaId);
    }

    /* =======================
       SHOW DETTAGLIO
       ======================= */

    public function show(Request $request, int $riepilogoId)
    {
        $riepilogo = Riepilogo::getSingle($riepilogoId);
        if (!$riepilogo) {
            abort(404, 'Riepilogo non trovato');
        }

        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int)$riepilogo->idAssociazione !== (int)$user->IdAssociazione) {
            abort(403, 'Accesso negato');
        }

        $idConvenzione = $request->input('idConvenzione', 'TOT');

        $righe = Riepilogo::getForDataTable(
            (int)$riepilogo->idAnno,
            (int)$riepilogo->idAssociazione,
            $idConvenzione
        );

        $convenzioni = Riepilogo::getConvenzioniForAssAnno(
            (int)$riepilogo->idAssociazione,
            (int)$riepilogo->idAnno
        );

        return view('riepiloghi.show', [
            'riepilogo'     => $riepilogo,
            'righe'         => $righe,
            'idConvenzione' => $idConvenzione,
            'convenzioni'   => $convenzioni,
        ]);
    }

    /* =======================
       EDIT/APPLY VOCE TOTALE
       ======================= */

    public function editVoceTotale(int $riepilogo, int $voce)
    {
        $riepilogoRow = Riepilogo::getSingle($riepilogo);
        if (!$riepilogoRow) abort(404, 'Riepilogo non trovato');

        $voceRow = DB::table('riepilogo_voci_config')->where('id', $voce)->first();
        if (!$voceRow) abort(404, 'Voce non trovata');

        // suggerito = MIN(preventivo) della voce per quel riepilogo (master value)
        $suggerito = (float) DB::table('riepilogo_dati')
            ->where('idRiepilogo', $riepilogoRow->idRiepilogo)
            ->where('idVoceConfig', $voce)
            ->min('preventivo') ?? 0.0;

        return view('riepiloghi.edit_voce_totale', [
            'riepilogo'           => $riepilogoRow,
            'voceId'              => (int) $voceRow->id,
            'voceDescrizione'     => $voceRow->descrizione,
            'preventivoSuggerito' => $suggerito,
        ]);
    }

    public function applyVoceTotale(Request $request)
    {
        $data = $request->validate([
            'idRiepilogo' => 'required|integer|exists:riepiloghi,idRiepilogo',
            'idVoce'      => 'required|integer|exists:riepilogo_voci_config,id',
            'preventivo'  => 'required|numeric|min:0',
            // 'consuntivo'  => 'nullable|numeric|min:0',
        ]);

        $riepilogo = Riepilogo::getSingle((int) $data['idRiepilogo']);
        if (!$riepilogo) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Riepilogo non trovato'], 404)
                : abort(404, 'Riepilogo non trovato');
        }

        // Autorizzazione
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int)$riepilogo->idAssociazione !== (int)$user->IdAssociazione) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Accesso negato'], 403)
                : abort(403, 'Accesso negato');
        }

        $idRiepilogo = (int) $riepilogo->idRiepilogo;
        $idVoce      = (int) $data['idVoce'];
        $val         = (float) $data['preventivo'];
        $cons        = $val; // se vuoi consuntivo=preventivo

        // Applica a tutte le convenzioni dell’associazione/anno
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $riepilogo->idAssociazione)
            ->where('idAnno', $riepilogo->idAnno)
            ->pluck('idConvenzione');

        foreach ($convenzioni as $idConvenzione) {
            Riepilogo::upsertValore($idRiepilogo, $idVoce, (int)$idConvenzione, $val, $cons);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return redirect()->route('riepiloghi.index')->with('success', 'Valore applicato a tutte le convenzioni.');
    }

    /* =======================
       DELETE BY KEYS
       ======================= */

    public function destroyRigaByKeys(Request $request)
    {
        $data = $request->validate([
            'idRiepilogo'   => 'required|integer|exists:riepiloghi,idRiepilogo',
            'voce_id'       => 'required|integer|exists:riepilogo_voci_config,id',
            'idConvenzione' => 'required|integer',
        ]);

        $riepilogo = Riepilogo::getSingle((int)$data['idRiepilogo']);
        $user      = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        if (!$isElevato && (int)$riepilogo->idAssociazione !== (int)$user->IdAssociazione) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Accesso negato'], 403)
                : abort(403, 'Accesso negato');
        }

        $rigaId = Riepilogo::getRigaIdByKeys(
            (int)$data['idRiepilogo'],
            (int)$data['voce_id'],
            (int)$data['idConvenzione']
        );

        if (!$rigaId) {
            return $request->expectsJson()
                ? response()->json(['ok' => true, 'message' => 'Nessuna riga trovata (già inesistente).'])
                : redirect()->route('riepiloghi.index')->with('success', 'Riga già inesistente.');
        }

        Riepilogo::deleteRiga($rigaId);

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('riepiloghi.index')->with('success', 'Riga eliminata correttamente.');
    }
}
