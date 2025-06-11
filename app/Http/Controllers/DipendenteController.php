<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Dipendente;
use Illuminate\Http\JsonResponse;

class DipendenteController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    public function index() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $dipendenti = Dipendente::getAll($anno);
            $titolo     = 'Tutti i Dipendenti';
        } else {
            $idAssoc = $user->IdAssociazione;
            if (! $idAssoc) {
                abort(403, "Associazione non trovata per l'utente.");
            }
            $dipendenti = Dipendente::getByAssociazione($idAssoc, $anno);
            $titolo     = 'Dipendenti della mia Associazione';
        }

        return view('dipendenti.index', compact('dipendenti', 'titolo', 'anno'));
    }

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

    public function show(int $idDipendente) {
        $dipendente = Dipendente::getById($idDipendente);
        if (! $dipendente) {
            abort(404);
        }

        return view('dipendenti.show', compact('dipendente'));
    }

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

    public function autisti() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $dipendenti = Dipendente::getAutisti($anno);
        } else {
            $idAssoc = $user->IdAssociazione;
            if (! $idAssoc) {
                abort(403, "Associazione non trovata per l'utente.");
            }
            $tutti = Dipendente::getByAssociazione($idAssoc, $anno);
            $dipendenti = $tutti->filter(fn($d) => str_contains($d->Qualifica, 'AUTISTA'));
        }

        $titolo = 'Personale Dipendente Autisti';
        return view('dipendenti.index', compact('dipendenti', 'titolo', 'anno'));
    }

    public function altro() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $dipendenti = Dipendente::getAltri($anno);
        } else {
            $idAssoc = $user->IdAssociazione;
            if (! $idAssoc) {
                abort(403, "Associazione non trovata per l'utente.");
            }
            $tutti = Dipendente::getByAssociazione($idAssoc, $anno);
            $dipendenti = $tutti->reject(fn($d) => str_contains($d->Qualifica, 'AUTISTA'));
        }

        $titolo = 'Altro Personale Dipendente';
        return view('dipendenti.index', compact('dipendenti', 'titolo', 'anno'));
    }

    public function getData(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        // Determina quale dataset restituire
        if ($request->tipo === 'altro') {
            // Solo “altro” (escludi chi ha AUTISTA in Qualifica)
            $dataset = !$isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
                ? Dipendente::getAltri($anno)
                : collect(Dipendente::getByAssociazione($user->IdAssociazione, $anno))
                ->reject(fn($d) => str_contains($d->Qualifica, 'AUTISTA'));
        } elseif ($request->tipo === 'autisti') {
            // Solo AUTISTI
            $dataset = !$isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
                ? Dipendente::getAutisti($anno)
                : collect(Dipendente::getByAssociazione($user->IdAssociazione, $anno))
                ->filter(fn($d) => str_contains($d->Qualifica, 'AUTISTA'));
        } else {
            // Default: tutti o filtrati per associazione
            if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
                $dataset = Dipendente::getAll($anno);
            } else {
                $idAssoc = $user->IdAssociazione
                    ?: abort(403, "Associazione non trovata per l'utente.");
                $dataset = Dipendente::getByAssociazione($idAssoc, $anno);
            }
        }

        return response()->json(['data' => $dataset]);
    }

    public function duplicaAnnoPrecedente(Request $request) {
        $annoCorrente = session('anno_riferimento', now()->year);
        $annoPrecedente = $annoCorrente - 1;
        $idAssociazione = Auth::user()->IdAssociazione;

        // Seleziona tutti i dipendenti dell’anno precedente
        $vecchi = DB::table('dipendenti')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $annoPrecedente)
            ->get();

        if ($vecchi->isEmpty()) {
            return response()->json(['message' => 'Nessun dipendente da copiare.'], 404);
        }

        // Duplica per l’anno corrente
        foreach ($vecchi as $d) {
            DB::table('dipendenti')->insert([
                'idAssociazione'     => $d->idAssociazione,
                'idAnno'             => $annoCorrente,
                'DipendenteNome'     => $d->DipendenteNome,
                'DipendenteCognome'  => $d->DipendenteCognome,
                'Qualifica'          => $d->Qualifica,
                'ContrattoApplicato' => $d->ContrattoApplicato,
                'LivelloMansione'    => $d->LivelloMansione,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        return response()->json(['message' => 'Dipendenti duplicati con successo.']);
    }

    public function checkDuplicazioneDisponibile(): JsonResponse {
        $annoCorrente = session('anno_riferimento', now()->year);
        $annoPrecedente = $annoCorrente - 1;
        $user = Auth::user();
        $idAssoc = $user->IdAssociazione;

        $vuotoCorrente = Dipendente::getByAssociazione($idAssoc, $annoCorrente)->isEmpty();
        $pienoPrecedente = Dipendente::getByAssociazione($idAssoc, $annoPrecedente)->isNotEmpty();

        return response()->json([
            'mostraMessaggio' => $vuotoCorrente && $pienoPrecedente,
            'annoCorrente' => $annoCorrente,
            'annoPrecedente' => $annoPrecedente
        ]);
    }

    public function altroData(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $data = auth()->user()->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? Dipendente::getAltri($anno)
            : collect(Dipendente::getByAssociazione(auth()->user()->IdAssociazione, $anno))
            ->reject(fn($d) => str_contains($d->Qualifica, 'AUTISTA'));
        return response()->json(['data' => $data]);
    }
}
