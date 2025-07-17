<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Riepilogo;

class RiepilogoController extends Controller
{
    public function create()
    {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        return view('riepiloghi.create', compact('associazioni', 'anni'));
    }

    public function store(Request $request)
    {
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
                    Riepilogo::addDato($idRiepilogo, $descr, (float) $prev, (float) $cons, $idTipologia, $validated['idAnno']);
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

    public function index()
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $riepiloghi = Riepilogo::getAllForAdmin($anno);
        } else {
            $idAssociazione = $user->IdAssociazione ?? null;

            if (!$idAssociazione) {
                abort(403, "Associazione non trovata per l'utente.");
            }

            $riepiloghi = Riepilogo::getByAssociazione($idAssociazione, $anno);
        }

        return view('riepiloghi.index', compact('riepiloghi'));
    }

    public function show(Riepilogo $riepilogo)
    {
        $dati = Riepilogo::getDati($riepilogo->idRiepilogo);
        return view('riepiloghi.show', compact('riepilogo', 'dati'));
    }

    public function edit(Riepilogo $riepilogo)
    {
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

    public function update(Request $request, Riepilogo $riepilogo)
    {
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
                    Riepilogo::addDato($riepilogo->idRiepilogo, $descr, (float) $prev, (float) $cons,   $idTipologia ,  $validated['idAnno']);
                }
            }
        });

        return redirect()->route('riepiloghi.index')
            ->with('success', 'Riepilogo aggiornato correttamente.');
    }

    public function destroy(Riepilogo $riepilogo)
    {
        DB::transaction(function () use ($riepilogo) {
            Riepilogo::deleteDati($riepilogo->idRiepilogo);
            Riepilogo::deleteRiepilogo($riepilogo->idRiepilogo);
        });

        return redirect()->route('riepiloghi.index')
            ->with('success', 'Riepilogo eliminato correttamente.');
    }

    public function getData()
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $q = DB::table('riepiloghi as r')
            ->join('associazioni as s', 'r.idAssociazione', '=', 's.IdAssociazione')
            ->join('anni as a', 'r.idAnno', '=', 'a.idAnno')
            ->join('riepilogo_dati as d', 'r.idRiepilogo', '=', 'd.idRiepilogo')
            ->where('r.idAnno', $anno)
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

        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = $user->IdAssociazione ?? null;
          
            if (!$idAssociazione) {
                return response()->json(['data' => []]); // niente datidd
            }
            $q->where('r.idAssociazione', $idAssociazione);
        }
        return response()->json(['data' => $q->get()]);
    }
}
