<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Riepilogo;
use App\Models\Convenzione;

class RiepilogoController extends Controller {
    /**
     * Mostra il form di inserimento.
     */
    public function create() {
        // Se l'utente è Admin/SuperAdmin/Supervisor, mostriamo TUTTE le associazioni:
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        // Per un “utente di associazione” potresti voler filtrare:
        // $myId = Auth::user()->idAssociazione;
        // $associazioni = DB::table('associazioni')
        //     ->where('idAssociazione', $myId)
        //     ->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        return view('riepiloghi.create', compact('associazioni', 'anni'));
    }

    /**
     * Riceve il POST del form, valida e salva usando i Model.
     */
    public function store(Request $request) {
        $rules = [
            'idAssociazione'      => 'required|exists:associazioni,idAssociazione',
            'idAnno'              => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'riep_descrizione'    => 'nullable|array',
            'riep_descrizione.*'  => 'nullable|string|max:500',
            'riep_preventivo'     => 'nullable|array',
            'riep_preventivo.*'   => 'nullable|numeric|min:0',
            'riep_consuntivo'     => 'nullable|array',
            'riep_consuntivo.*'   => 'nullable|numeric|min:0',
            'tab_descrizione'     => 'nullable|array',
            'tab_descrizione.*'   => 'nullable|string|max:500',
            'tab_lettera'         => 'nullable|array',
            'tab_lettera.*'       => 'nullable|string|max:5',
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            // 1) Creo il riepilogo principale
            $idRiepilogo = Riepilogo::createRiepilogo(
                $validated['idAssociazione'],
                $validated['idAnno']
            );

            // 2) Inserisco i dati caratteristici (se presenti)
            if (! empty($validated['riep_descrizione']) && is_array($validated['riep_descrizione'])) {
                foreach ($validated['riep_descrizione'] as $i => $descr) {
                    if (trim($descr) === '') {
                        continue;
                    }
                    $prev = $validated['riep_preventivo'][$i] ?? 0;
                    $cons = $validated['riep_consuntivo'][$i] ?? 0;
                    Riepilogo::addDato($idRiepilogo, $descr, (float)$prev, (float)$cons);
                }
            }

            DB::commit();
            return redirect()
                ->route('riepiloghi.index')
                ->with('success', 'Riepilogo e convenzioni salvate correttamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            // In fase di sviluppo puoi fare dd($e->getMessage()) per debug
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Errore interno: impossibile salvare i dati.']);
        }
    }

    /**
     * Mostra l'elenco dei riepiloghi.
     */
    public function index() {
        $user = Auth::user();

        // 1) Se utente Admin/SuperAdmin/Supervisor → tutti i riepiloghi
        if (in_array($user->role_id ?? 0, [1, 2, 3])) {
            $riepiloghi = Riepilogo::getAllForAdmin();  // array con campi [idRiepilogo, Associazione, anno, created_at]
            return view('riepiloghi.index', compact('riepiloghi'));
        }

        // 2) Altrimenti utente di associazione
        $myId = $user->idAssociazione;
        if (! $myId) {
            abort(403, "Associazione non trovata per l'utente.");
        }

        $riepiloghi = Riepilogo::getByAssociazione($myId);
        return view('riepiloghi.index', compact('riepiloghi'));
    }

    /**
     * Mostra il dettaglio di un singolo riepilogo.
     */
    public function show(Riepilogo $riepilogo) {
        $dati = Riepilogo::getDati($riepilogo->idRiepilogo);

        return view('riepiloghi.show', compact('riepilogo', 'dati'));
    }

    /**
     * Mostra il form per modificare un riepilogo esistente.
     */
    public function edit(Riepilogo $riepilogo) {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        $dati = Riepilogo::getDati($riepilogo->idRiepilogo);

        return view('riepiloghi.edit', compact('riepilogo', 'associazioni', 'anni', 'dati'));
    }

    /**
     * Riceve il PUT per aggiornare un riepilogo e i suoi dati.
     */
    public function update(Request $request, Riepilogo $riepilogo) {
        $rules = [
            'idAssociazione'      => 'required|exists:associazioni,idAssociazione',
            'idAnno'              => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'riep_descrizione'    => 'nullable|array',
            'riep_descrizione.*'  => 'nullable|string|max:500',
            'riep_preventivo'     => 'nullable|array',
            'riep_preventivo.*'   => 'nullable|numeric|min:0',
            'riep_consuntivo'     => 'nullable|array',
            'riep_consuntivo.*'   => 'nullable|numeric|min:0',
        ];

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $riepilogo) {
            Riepilogo::updateRiepilogo(
                $riepilogo->idRiepilogo,
                $validated['idAssociazione'],
                $validated['idAnno']
            );

            Riepilogo::deleteDati($riepilogo->idRiepilogo);
            if (! empty($validated['riep_descrizione'])) {
                foreach ($validated['riep_descrizione'] as $i => $descr) {
                    if (trim($descr) === '') continue;
                    $prev = $validated['riep_preventivo'][$i] ?? 0;
                    $cons = $validated['riep_consuntivo'][$i] ?? 0;
                    Riepilogo::addDato($riepilogo->idRiepilogo, $descr, (float)$prev, (float)$cons);
                }
            }
        });

        return redirect()
            ->route('riepiloghi.index')
            ->with('success', 'Riepilogo aggiornato correttamente.');
    }

    /**
     * Elimina un riepilogo (e tutte le righe collegate).
     */
    public function destroy(Riepilogo $riepilogo) {
        DB::transaction(function () use ($riepilogo) {
            Riepilogo::deleteDati($riepilogo->idRiepilogo);
            Riepilogo::deleteRiepilogo($riepilogo->idRiepilogo);
        });

        return redirect()
            ->route('riepiloghi.index')
            ->with('success', 'Riepilogo eliminato correttamente.');
    }

    /**
     * Restituisce JSON per DataTable: join fra riepiloghi e riepilogo_dati
     */
    public function getData() {
        $user = Auth::user();

        $q = DB::table('riepiloghi as r')
            ->join('associazioni as s',    'r.idAssociazione', '=', 's.idAssociazione')
            ->join('anni as a',            'r.idAnno',         '=', 'a.idAnno')
            ->join('riepilogo_dati as d',  'r.idRiepilogo',    '=', 'd.idRiepilogo')
            ->select([
                's.Associazione',
                'r.idAnno as anno',
                'd.descrizione',
                'r.idRiepilogo',
                'd.preventivo',
                'd.consuntivo',
                'r.idRiepilogo as actions_id',
            ])
            ->orderBy('s.Associazione')
            ->orderBy('r.idAnno', 'desc');

        // se non Admin/SuperAdmin/Supervisor, filtro per la sola associazione
        if (! $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $q->where('r.idAssociazione', $user->IdAssociazione);
        }

        return response()->json(['data' => $q->get()]);
    }
}
