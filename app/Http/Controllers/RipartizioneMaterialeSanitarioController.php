<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\Automezzo;

class RipartizioneMaterialeSanitarioController extends Controller
{
    public function index()
    {
        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getFiltratiByUtente($anno); // logica centralizzata
        $idAssociazioni = $automezzi->pluck('idAssociazione')->unique();

        $idAssociazione = $idAssociazioni->count() === 1 ? $idAssociazioni->first() : null;

        $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);

        return view('ripartizioni.materiale_sanitario.index', [
            'anno' => $anno,
            'convenzioni' => $dati['convenzioni'],
            'righe' => $dati['righe'],
            'totale_inclusi' => $dati['totale_inclusi'],
        ]);
    }

    public function getData(Request $request): JsonResponse
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = (!$this->isImpersonating() && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            ? null
            : $user->IdAssociazione;

        $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);

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
