<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneCostiAutomezziSanitari;
use App\Models\Automezzo;
use App\Services\RipartizioneCostiService;
use App\Models\CostoDiretto;

class RipartizioneCostiAutomezziSanitariController extends Controller {
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        if ($isElevato) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione' , 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            $selectedAssoc = session('associazione_selezionata')
                ?? optional($associazioni->first())->idAssociazione;
        } else {
            $associazioni  = collect(); // non mostriamo la select per utenti non elevati
            $selectedAssoc = (int) $user->IdAssociazione;
        }

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

        $idAutomezzo = $request->input('idAutomezzo');

        // Recupera i nomi delle convenzioni in ordine
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('Convenzione')
            ->toArray();

        $tabella = [];

        if ($idAutomezzo === 'TOT') {
            $tabella = RipartizioneCostiService::calcolaTabellaTotale($idAssociazione, $anno);
        } else {
            $tabella = RipartizioneCostiService::calcolaRipartizioneTabellaFinale(
                $idAssociazione,
                $anno,
                (int)$idAutomezzo
            );
        }

        $colonne = array_merge(['voce', 'totale'], $convenzioni);

        return response()->json([
            'data' => $tabella,
            'colonne' => $colonne,
        ]);
    }
}
