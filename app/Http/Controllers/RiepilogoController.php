<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Riepilogo;

class RiepilogoController extends Controller {
    public function create() {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->where('idAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        return view('riepiloghi.create', compact('associazioni', 'anni'));
    }

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
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            $idRiepilogo = Riepilogo::createRiepilogo(
                $validated['idAssociazione'],
                $validated['idAnno']
            );
            if (!empty($validated['riep_descrizione'])) {
                foreach ($validated['riep_descrizione'] as $i => $descr) {
                    if (trim($descr) === '') continue;

                    $prev = $validated['riep_preventivo'][$i] ?? 0;
                    $cons = $validated['riep_consuntivo'][$i] ?? 0;
                    $idTipologia = 1;
                    Riepilogo::addDato(
                        $idRiepilogo,
                        $descr,
                        (float) $prev,
                        (float) $cons,
                        $idTipologia,
                        $validated['idAnno']
                    );
                }
            }

            DB::commit();
            return redirect()->route('riepiloghi.index')
                ->with('success', 'Riepilogo e convenzioni salvate correttamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Errore interno: impossibile salvare i dati.']);
        }
    }

    public function index(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $associazioni = collect();
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();
            $selectedAssoc = $request->get('idAssociazione')
                ?? ($associazioni->first()->idAssociazione ?? null);
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }

        return view('riepiloghi.index', compact('associazioni', 'selectedAssoc', 'anno'));
    }

    public function show(Riepilogo $riepilogo) {
        $dati = Riepilogo::getDati($riepilogo->idRiepilogo);
        return view('riepiloghi.show', compact('riepilogo', 'dati'));
    }

    public function edit(Riepilogo $riepilogo) {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->where('idAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        $dati = Riepilogo::getDati($riepilogo->idRiepilogo);

        return view('riepiloghi.edit', compact('riepilogo', 'associazioni', 'anni', 'dati'));
    }

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

            if (!empty($validated['riep_descrizione'])) {
                foreach ($validated['riep_descrizione'] as $i => $descr) {
                    if (trim($descr) === '') continue;

                    $prev = $validated['riep_preventivo'][$i] ?? 0;
                    $cons = $validated['riep_consuntivo'][$i] ?? 0;
                    $idTipologia = 1;
                    Riepilogo::addDato(
                        $riepilogo->idRiepilogo,
                        $descr,
                        (float) $prev,
                        (float) $cons,
                        $idTipologia,
                        $validated['idAnno']
                    );
                }
            }
        });

        return redirect()->route('riepiloghi.index')
            ->with('success', 'Riepilogo aggiornato correttamente.');
    }

    public function destroy(Request $request, Riepilogo $riepilogo) {
        // se arriva un dato_id, cancella solo quella riga
        if ($request->filled('dato_id')) {
            $datoId = $request->input('dato_id');
            DB::table('riepilogo_dati')
                ->where('id', $datoId)
                ->delete();

            return back()->with('success', 'Voce eliminata correttamente.');
        }
        // altrimenti elimini l’intero riepilogo + tutti i suoi dati
        DB::transaction(function () use ($riepilogo) {
            // cancella prima i dati
            DB::table('riepilogo_dati')
                ->where('idRiepilogo', $riepilogo->idRiepilogo)
                ->delete();

            // poi il riepilogo padre
            DB::table('riepiloghi')
                ->where('idRiepilogo', $riepilogo->idRiepilogo)
                ->delete();
        });

        return redirect()
            ->route('riepiloghi.index')
            ->with('success', 'Riepilogo eliminato correttamente.');
    }

    public function getData(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $q = DB::table('riepiloghi as r')
            ->join('associazioni as s', 'r.idAssociazione', '=', 's.idAssociazione')
            ->join('anni as a', 'r.idAnno', '=', 'a.idAnno')
            ->join('riepilogo_dati as d', 'r.idRiepilogo', '=', 'd.idRiepilogo')
            ->where('r.idAnno', $anno)
            ->select([
                's.Associazione',
                'r.idAnno as anno',
                'd.descrizione',
                'r.idRiepilogo',
                'd.id as dato_id',
                'd.preventivo',
                'd.consuntivo',
                'r.idRiepilogo as actions_id',
            ])
            ->orderBy('s.Associazione')
            ->orderBy('r.idAnno', 'desc');

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $assocId = $request->get('idAssociazione');
            if ($assocId) {
                $q->where('r.idAssociazione', $assocId);
            }
        } else {
            $idAssociazione = $user->IdAssociazione;
            $q->where('r.idAssociazione', $idAssociazione);
        }

        return response()->json(['data' => $q->get()]);
    }
}
