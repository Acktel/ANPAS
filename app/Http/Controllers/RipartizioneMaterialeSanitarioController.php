<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\Automezzo;
use Illuminate\Support\Facades\DB;


class RipartizioneMaterialeSanitarioController extends Controller
{
// App\Http\Controllers\RipartizioneMaterialeSanitarioController.php

public function index(Request $request)
{
    $anno = session('anno_riferimento', now()->year);

    // associazione scelta (priorità: GET -> sessione pagina -> auto-detect -> utente)
    $selectedAssoc = $request->get('idAssociazione')
        ?? session('associazione_selezionata')
        ?? (function () use ($anno) {
            $automezzi = Automezzo::getFiltratiByUtente($anno);
            $ids = $automezzi->pluck('idAssociazione')->unique();
            return $ids->count() === 1 ? $ids->first() : (Auth::user()->IdAssociazione ?? null);
        })();

    // memorizzo la scelta per questa pagina (coerente con getData)
    session(['associazione_selezionata' => $selectedAssoc]);

    $dati = RipartizioneMaterialeSanitario::getRipartizione($selectedAssoc, $anno);

    $associazioni = DB::table('associazioni')
        ->select('idAssociazione', 'Associazione')
        ->orderBy('Associazione')
        ->get();

    return view('ripartizioni.materiale_sanitario.index', [
        'anno'           => $anno,
        'convenzioni'    => $dati['convenzioni'],
        'righe'          => $dati['righe'],
        'totale_inclusi' => $dati['totale_inclusi'],
        'associazioni'   => $associazioni,
        'selectedAssoc'  => $selectedAssoc,
    ]);
}

    public function getData(Request $request): JsonResponse
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $sessionKey = 'associazione_selezionata';
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            if ($request->has('idAssociazione')) {
                // salvo la selezione solo per QUESTA pagina
                session([$sessionKey => $request->get('idAssociazione')]);
                $selectedAssoc = $request->get('idAssociazione');
            } else {
                // recupero dalla sessione di questa pagina
                $selectedAssoc = session($sessionKey, $associazioni->first()->idAssociazione ?? null);
            }
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }
        $dati = RipartizioneMaterialeSanitario::getRipartizione($selectedAssoc, $anno);
        
        return response()->json($dati);
    }

    public function show()
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = (!$this->isImpersonating() && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            ? null
            : $user->IdAssociazione;

        $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);

        return view('ripartizioni.materiale_sanitario.show', [
            'anno' => $anno,
            'convenzioni' => $dati['convenzioni'],
            'righe' => $dati['righe'],
            'totale_inclusi' => $dati['totale_inclusi'],
        ]);
    }

    public function edit()
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = (!$this->isImpersonating() && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            ? null
            : $user->IdAssociazione;

        $automezzi = RipartizioneMaterialeSanitario::getAutomezziPerEdit($idAssociazione, $anno);

        return view('ripartizioni.materiale_sanitario.edit', compact('automezzi', 'anno'));
    }

    public function update(Request $request)
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = (!$this->isImpersonating() && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            ? null
            : $user->IdAssociazione;

        $idsInclusi = $request->input('inclusi', []);

        RipartizioneMaterialeSanitario::aggiornaInclusioni($idsInclusi, $idAssociazione, $anno);

        return redirect()
            ->route('ripartizioni.materiale_sanitario.index')
            ->with('success', 'Aggiornamento completato.');
    }

    public function aggiornaInclusione(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idAutomezzo' => 'required|integer|exists:automezzi,idAutomezzo',
            'incluso' => 'required|boolean',
        ]);

        RipartizioneMaterialeSanitario::aggiornaInclusione(
            $validated['idAutomezzo'],
            $validated['incluso']
        );

        return response()->json(['success' => true]);
    }

    /**
     * Rileva se l’utente sta impersonificando un altro account.
     */
    private function isImpersonating(): bool
    {
        return session()->has('impersonate');
    }
}
