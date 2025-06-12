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
        $idAssociazione = Auth::user()->IdAssociazione;

        return view('riepilogo_costi.index', data: compact('anno', 'idAssociazione'));
    }

    public function getSezione($idTipologia) {
        $anno = session('anno_riferimento', now()->year);
        $idAssociazione = Auth::user()->IdAssociazione;

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

        $idAssociazione = Auth::user()->IdAssociazione;
        $anno = session('anno_riferimento', now()->year);

        $riepilogoId = RiepilogoCosti::getOrCreateRiepilogo($idAssociazione, $anno);

        Log::info('Salvataggio voce riepilogo', $request->all());

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
        $voce = \DB::table('riepilogo_dati')->where('id', $id)->first();

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

        $record = \DB::table('riepilogo_dati')->where('id', $id)->first();

        if (! $record) {
            abort(404, 'Voce non trovata');
        }

        \DB::table('riepilogo_dati')
            ->where('id', $id)
            ->update([
                'descrizione' => $request->input('descrizione'),
                'preventivo'  => $request->input('preventivo'),
                'consuntivo'  => $request->input('consuntivo'),
                'updated_at'  => now(),
            ]);

        return redirect()->route('riepilogo.costi')->with('success', 'Voce aggiornata con successo');
    }

    public function destroy($id) {
        $voce = \DB::table('riepilogo_dati')->where('id', $id)->first();

        if (! $voce) {
            abort(404, 'Voce non trovata');
        }

        \DB::table('riepilogo_dati')->where('id', $id)->delete();

        return redirect()->route('riepilogo.costi')->with('success', 'Voce eliminata con successo');
    }


    public function importFromPreviousYear($idTipologia) {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $idAssociazione = Auth::user()->IdAssociazione;

        // Recupera idRiepilogo per l’anno corrente (lo crea se non c’è)
        $idRiepilogo = RiepilogoCosti::getOrCreateRiepilogo($idAssociazione, $anno);

        // Voci dall’anno precedente
        $vociPrec = DB::table('riepilogo_dati as rd')
            ->join('riepiloghi as r', 'rd.idRiepilogo', '=', 'r.idRiepilogo')
            ->where('r.idAnno', $annoPrec)
            ->where('r.idAssociazione', $idAssociazione)
            ->where('rd.idTipologiaRiepilogo', $idTipologia)
            ->select('rd.descrizione', 'rd.idTipologiaRiepilogo')
            ->get();

        if ($vociPrec->isEmpty()) {
            return back()->with('warning', 'Nessuna voce trovata per l\'anno precedente.');
        }

        // Verifica che l’anno corrente sia vuoto
        $vociCorr = DB::table('riepilogo_dati as rd')
            ->join('riepiloghi as r', 'rd.idRiepilogo', '=', 'r.idRiepilogo')
            ->where('r.idAnno', $anno)
            ->where('r.idAssociazione', $idAssociazione)
            ->where('rd.idTipologiaRiepilogo', $idTipologia)
            ->exists();

        if ($vociCorr) {
            return back()->with('error', 'Dati già presenti per l\'anno corrente.');
        }

        // Inserisci voci
        foreach ($vociPrec as $voce) {
            DB::table('riepilogo_dati')->insert([
                'idRiepilogo' => $idRiepilogo,
                'idAnno' => $anno,
                'idTipologiaRiepilogo' => $voce->idTipologiaRiepilogo,
                'descrizione' => $voce->descrizione,
                'preventivo' => 0,
                'consuntivo' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Voci importate da anno precedente con successo.');
    }

    public function checkDuplicazione(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $idAssoc = Auth::user()->IdAssociazione;

        $correnteVuoto = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssoc)
            ->where('idAnno', $anno)
            ->doesntExist();

        $precedentePieno = DB::table('riepilogo_dati as rd')
            ->join('riepiloghi as r', 'rd.idRiepilogo', '=', 'r.idRiepilogo')
            ->where('r.idAssociazione', $idAssoc)
            ->where('r.idAnno', $annoPrec)
            ->exists();
        
        return response()->json([
            'mostraMessaggio' => $correnteVuoto && $precedentePieno,
            'annoCorrente' => $anno,
            'annoPrecedente' => $annoPrec,
        ]);
    }

    public function duplicaDaAnnoPrecedente(Request $request): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $idAssoc = Auth::user()->IdAssociazione;

        $vociPrec = DB::table('riepilogo_dati as rd')
            ->join('riepiloghi as r', 'rd.idRiepilogo', '=', 'r.idRiepilogo')
            ->where('r.idAssociazione', $idAssoc)
            ->where('r.idAnno', $annoPrec)
            ->select('rd.descrizione', 'rd.idTipologiaRiepilogo')
            ->get();

        if ($vociPrec->isEmpty()) {
            return response()->json(['message' => 'Nessuna voce da importare.'], 404);
        }

        $idRiepilogo = RiepilogoCosti::getOrCreateRiepilogo($idAssoc, $anno);

        DB::beginTransaction();
        try {
            foreach ($vociPrec as $voce) {
                DB::table('riepilogo_dati')->insert([
                    'idRiepilogo' => $idRiepilogo,
                    'idAnno' => $anno,
                    'idTipologiaRiepilogo' => $voce->idTipologiaRiepilogo,
                    'descrizione' => $voce->descrizione,
                    'preventivo' => 0,
                    'consuntivo' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Voci duplicate con successo']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Errore duplicazione dati.'], 500);
        }
    }
}
