<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Dipendente;

class DipendenteController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    /**
     * Elenco dei dipendenti.
     */
    public function index() {
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $dipendenti = Dipendente::getAll();
            $titolo     = 'Tutti i Dipendenti';
        } else {
            $idAssoc    = $user->IdAssociazione;
            if (! $idAssoc) {
                abort(403, "Associazione non trovata per l'utente.");
            }
            $dipendenti = Dipendente::getByAssociazione($idAssoc);
            $titolo     = 'Dipendenti della mia Associazione';
        }

        return view('dipendenti.index', compact('dipendenti', 'titolo'));
    }

    /**
     * Mostra il form di creazione.
     */
    public function create() {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        $anni = [];
        for ($y = 2000; $y <= date('Y') + 5; $y++) {
            $anni[] = (object)['idAnno' => $y, 'anno' => $y];
        }

        return view('dipendenti.create', compact('associazioni', 'anni'));
    }

    /**
     * Memorizza un nuovo dipendente.
     */
    public function store(Request $request) {
        $validated = $request->validate([
            'idAssociazione'      => 'required|exists:associazioni,idAssociazione',
            'idAnno'              => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'DipendenteNome'      => 'required|string|max:100',
            'DipendenteCognome'   => 'required|string|max:100',
            'Qualifica'           => 'required|string|max:255',
            'ContrattoApplicato'  => 'required|string|max:100',
            'LivelloMansione'     => 'required|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            Dipendente::createDipendente($validated);
            DB::commit();

            return redirect()
                ->route('dipendenti.index')
                ->with('success', 'Dipendente creato correttamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('DipendenteController@store error: ' . $e->getMessage());
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Errore interno: impossibile salvare il dipendente.']);
        }
    }

    /**
     * Mostra un singolo dipendente.
     */
    public function show(int $idDipendente) {
        $dipendente = Dipendente::getById($idDipendente);
        if (! $dipendente) {
            abort(404);
        }

        return view('dipendenti.show', compact('dipendente'));
    }

    /**
     * Mostra il form di modifica.
     */
    public function edit(int $idDipendente) {
        $dipendente = Dipendente::getById($idDipendente);
        if (! $dipendente) {
            abort(404);
        }

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        $anni = [];
        for ($y = 2000; $y <= date('Y') + 5; $y++) {
            $anni[] = (object)['idAnno' => $y, 'anno' => $y];
        }

        return view('dipendenti.edit', compact('dipendente', 'associazioni', 'anni'));
    }

    /**
     * Aggiorna un dipendente esistente.
     */
    public function update(Request $request, int $idDipendente) {
        $validated = $request->validate([
            'idAssociazione'      => 'required|exists:associazioni,idAssociazione',
            'idAnno'              => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'DipendenteNome'      => 'required|string|max:100',
            'DipendenteCognome'   => 'required|string|max:100',
            'Qualifica'           => 'required|string|max:255',
            'ContrattoApplicato'  => 'required|string|max:100',
            'LivelloMansione'     => 'required|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            Dipendente::updateDipendente($idDipendente, $validated);
            DB::commit();

            return redirect()
                ->route('dipendenti.index')
                ->with('success', 'Dipendente aggiornato correttamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('DipendenteController@update error: ' . $e->getMessage());
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Errore interno: impossibile aggiornare il dipendente.']);
        }
    }

    /**
     * Elimina un dipendente.
     */
    public function destroy(int $idDipendente) {
        $esiste = Dipendente::getById($idDipendente);
        if (! $esiste) {
            abort(404);
        }

        DB::beginTransaction();
        try {
            Dipendente::deleteDipendente($idDipendente);
            DB::commit();

            return redirect()
                ->route('dipendenti.index')
                ->with('success', 'Dipendente eliminato correttamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('DipendenteController@destroy error: ' . $e->getMessage());
            return redirect()
                ->back()
                ->withErrors(['error' => 'Errore interno: impossibile eliminare il dipendente.']);
        }
    }

    /**
     * Mostra solo i dipendenti con Qualifica contenente “AUTISTA”.
     */
    public function autisti() {
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $dipendenti = Dipendente::getAutisti();
        } else {
            $idAssoc = $user->IdAssociazione;
            if (! $idAssoc) {
                abort(403, "Associazione non trovata per l'utente.");
            }
            $tutti      = Dipendente::getByAssociazione($idAssoc);
            $dipendenti = $tutti->filter(fn($d) => str_contains($d->Qualifica, 'AUTISTA'));
        }

        $titolo = 'Personale Dipendente Autisti';
        return view('dipendenti.index', compact('dipendenti', 'titolo'));
    }

    /**
     * Mostra tutti i dipendenti la cui Qualifica NON contiene “AUTISTA”.
     */
    public function altro() {
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $dipendenti = Dipendente::getAltri();
        } else {
            $idAssoc = $user->IdAssociazione;
            if (! $idAssoc) {
                abort(403, "Associazione non trovata per l'utente.");
            }
            $tutti      = Dipendente::getByAssociazione($idAssoc);
            $dipendenti = $tutti->reject(fn($d) => str_contains($d->Qualifica, 'AUTISTA'));
        }

        $titolo = 'Altro Personale Dipendente';
        return view('dipendenti.index', compact('dipendenti', 'titolo'));
    }

    /**
     * Restituisce i dati JSON per DataTable.
     */
    public function getData() {
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $data = Dipendente::getAll();
        } else {
            $idAssoc = $user->IdAssociazione;
            if (! $idAssoc) {
                abort(403, "Associazione non trovata per l'utente.");
            }
            $data = Dipendente::getByAssociazione($idAssoc);
        }

        return response()->json(['data' => $data]);
    }
}
