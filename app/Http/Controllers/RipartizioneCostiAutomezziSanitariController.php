<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneCostiAutomezziSanitari;
use App\Models\Automezzo;
use App\Services\RipartizioneCostiService;

class RipartizioneCostiAutomezziSanitariController extends Controller
{
    public function index()
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        if ($isElevato) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            $selectedAssoc = session('associazione_selezionata')
                ?? optional($associazioni->first())->idAssociazione;

            // Precarico gli automezzi per l’associazione selezionata (se c’è)
            $automezziAssoc = collect();
            if (!empty($selectedAssoc)) {
                $automezziAssoc = DB::table('automezzi')
                    ->select('idAutomezzo', 'Targa')
                    ->where('idAssociazione', $selectedAssoc)
                    ->where('idAnno', $anno)
                    ->orderBy('Targa')
                    ->get();
            }

            $selectedAutomezzo = session('automezzo_selezionato', 'TOT');
        } else {
            // Utenti non elevati: niente select associazione, automezzi filtrati per utente
            $associazioni = collect();
            $selectedAssoc = (int) $user->IdAssociazione;
            $automezziAssoc = Automezzo::getFiltratiByUtente($anno)
                ->map(fn ($a) => (object) ['idAutomezzo' => $a->idAutomezzo, 'Targa' => $a->Targa]);
            $selectedAutomezzo = session('automezzo_selezionato', 'TOT');
        }

        return view('ripartizioni.costi_automezzi_sanitari.index', [
            'anno'              => $anno,
            'associazioni'      => $associazioni,
            'isElevato'         => $isElevato,
            'selectedAssoc'     => $selectedAssoc,
            'automezziAssoc'    => $automezziAssoc,
            'selectedAutomezzo' => $selectedAutomezzo,
        ]);
    }

    public function getData(Request $request)
    {
        $anno = session('anno_riferimento', now()->year);
        $idAutomezzo = $request->input('idAutomezzo'); // filtro dinamico
        $dati = RipartizioneCostiAutomezziSanitari::calcola($idAutomezzo, $anno);
        return response()->json(['data' => $dati]);
    }

    public function getTabellaFinale(Request $request)
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = $user->IdAssociazione;
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && $request->filled('idAssociazione')) {
            $idAssociazione = (int) $request->input('idAssociazione');
        }

        $idAutomezzo = $request->input('idAutomezzo', 'TOT');

        // ✅ Memorizzo in sessione le ultime scelte
        session([
            'associazione_selezionata' => $idAssociazione,
            'automezzo_selezionato'    => $idAutomezzo,
        ]);

        // Recupera i nomi delle convenzioni in ordine
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->pluck('Convenzione')
            ->toArray();

        if ($idAutomezzo === 'TOT') {
            $tabella = RipartizioneCostiService::calcolaTabellaTotale($idAssociazione, $anno);
        } else {
            $tabella = RipartizioneCostiService::calcolaRipartizioneTabellaFinale(
                $idAssociazione,
                $anno,
                (int) $idAutomezzo
            );
        }

        $colonne = array_merge(['voce', 'totale'], $convenzioni);

        return response()->json([
            'data'    => $tabella,
            'colonne' => $colonne,
        ]);
    }
}
