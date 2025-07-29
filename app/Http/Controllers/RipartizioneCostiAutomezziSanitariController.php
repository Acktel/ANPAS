<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneCostiAutomezziSanitari;
use App\Models\Automezzo;
use App\Services\RipartizioneCostiService;

class RipartizioneCostiAutomezziSanitariController extends Controller {
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $associazioni = $isElevato
            ? DB::table('associazioni')->select('IdAssociazione as id', 'Associazione as nome')->orderBy('Associazione')->get()
            : [];

        $automezzi = Automezzo::getFiltratiByUtente($anno);

        return view('ripartizioni.costi_automezzi_sanitari.index', compact('anno', 'associazioni', 'automezzi', 'isElevato'));
    }


    public function getData(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $idAutomezzo = $request->input('idAutomezzo'); // filtro dinamico        
        $dati = RipartizioneCostiAutomezziSanitari::calcola($idAutomezzo, $anno);
        return response()->json(['data' => $dati]);
    }

    public function getTabellaFinale(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = $user->IdAssociazione;
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && $request->filled('idAssociazione')) {
            $idAssociazione = $request->input('idAssociazione');
        }
        $tabella = RipartizioneCostiService::calcolaRipartizioneTabellaFinale($idAssociazione, $anno, $request->idAutomezzo);

        return response()->json(['data' => $tabella]);
    }
}
