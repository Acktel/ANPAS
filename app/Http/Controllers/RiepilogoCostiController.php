<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\RiepilogoCosti;
use Illuminate\Http\JsonResponse;

class RiepilogoCostiController extends Controller {

    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $idAssociazione = Auth::user()->IdAssociazione ?? null;

        return view('riepilogo_costi.index', compact('anno', 'idAssociazione'));
    }

    public function getSezione($idTipologia) {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $idAssociazione = $user->hasRole('SuperAdmin') ? null : $user->IdAssociazione;

        $data = RiepilogoCosti::getByTipologia($idTipologia, $anno, $idAssociazione);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request) {
        $request->validate([
            'idTipologiaRiepilogo' => 'required|integer',
            'descrizione'          => 'required|string|max:500',
            'preventivo'           => 'required|numeric',
            'consuntivo'           => 'required|numeric',
        ]);

        $user = Auth::user();
        $idAssociazione = $user->hasRole('SuperAdmin') ? 1 : $user->IdAssociazione; // usa 1 come default se superadmin
        $anno = session('anno_riferimento', now()->year);

        $riepilogoId = RiepilogoCosti::getOrCreateRiepilogo($idAssociazione, $anno);

        Log::info('Salvataggio voce riepilogo', $request->toArray());

        RiepilogoCosti::createVoce([
            'idRiepilogo'           => $riepilogoId,
            'idAnno'                => $anno,
            'idTipologiaRiepilogo' => $request->idTipologiaRiepilogo,
            'descrizione'          => $request->descrizione,
            'preventivo'           => $request->preventivo,
            'consuntivo'           => $request->consuntivo,
        ]);

        return redirect()->route('riepilogo.costi')->with('success', 'Voce inserita correttamente');
    }

    private function getTitoloSezione($idTipologia) {
        $sezioni = [
            2 => 'Automezzi',
            3 => 'Attrezzatura Sanitaria',
            4 => 'Telecomunicazioni',
            5 => 'Costi gestione struttura',
            6 => 'Costo del personale',
            7 => 'Materiale sanitario di consumo',
            8 => 'Costi amministrativi',
            9 => 'Quote di ammortamento',
            10 => 'Beni Strumentali inferiori a 516,00 euro',
            11 => 'Altri costi',
        ];
        return $sezioni[$idTipologia] ?? 'Voce';
    }

    public function create($idTipologia) {
        $anno = session('anno_riferimento', now()->year);
        $sezione = $this->getTitoloSezione($idTipologia);

        return view('riepilogo_costi.create', compact('idTipologia', 'anno', 'sezione'));
    }

    public function edit($id) {
        $voce = DB::table('riepilogo_dati')->where('id', $id)->first();

        if (! $voce) {
            abort(404, 'Voce non trovata');
        }

        $sezione = $this->getTitoloSezione($voce->idTipologiaRiepilogo);
        $anno = session('anno_riferimento', now()->year);

        return view('riepilogo_costi.edit', compact('voce', 'anno', 'sezione'));
    }

    public function update(Request $request, $id) {
        $request->validate([
            'descrizione' => 'required|string|max:500',
            'preventivo'  => 'required|numeric',
            'consuntivo'  => 'required|numeric',
        ]);

        $record = DB::table('riepilogo_dati')->where('id', $id)->first();

        if (! $record) {
            abort(404, 'Voce non trovata');
        }

        DB::table('riepilogo_dati')
            ->where('id', $id)
            ->update([
                'descrizione' => $request->descrizione,
                'preventivo'  => $request->preventivo,
                'consuntivo'  => $request->consuntivo,
                'updated_at'  => now(),
            ]);

        return redirect()->route('riepilogo.costi')->with('success', 'Voce aggiornata con successo');
    }

    public function destroy($id) {
        $voce = DB::table('riepilogo_dati')->where('id', $id)->first();

        if (! $voce) {
            abort(404, 'Voce non trovata');
        }

        DB::table('riepilogo_dati')->where('id', $id)->delete();

        return redirect()->route('riepilogo.costi')->with('success', 'Voce eliminata con successo');
    }
}
