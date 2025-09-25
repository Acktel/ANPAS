<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\Dipendente;

class DipendenteController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    public function index(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');
        $selectedAssoc = null;
        $associazioni = [];

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            if ($request->has('idAssociazione')) {
                session(['associazione_selezionata' => $request->get('idAssociazione')]);
            }

            $selectedAssoc = session('associazione_selezionata') ?? ($associazioni->first()->idAssociazione ?? null);
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }

        $titolo = ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && !$isImpersonating)
            ? 'Tutti i Dipendenti'
            : 'Dipendenti della mia Associazione';

        return view('dipendenti.index', compact('titolo', 'anno', 'associazioni'));
    }

    public function create() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $associazioni = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? DB::table('associazioni')
            ->whereNull('deleted_at')
            ->whereNot("idAssociazione", 1)
            ->get()
            : DB::table('associazioni')->where('idAssociazione', $user->IdAssociazione)
            ->whereNull('deleted_at')
            ->whereNot("idAssociazione", 1)
            ->get();

        $anni = DB::table('anni')->orderByDesc('anno')->get();
        $qualifiche = DB::table('qualifiche')->get();
        $contratti = DB::table('contratti_applicati')->get();
        // $livelli = DB::table('livello_mansione')->get(); DA ELIMINARE SE CORRETTAMENTE SUPERFLUA

        return view('dipendenti.create', compact(
            'associazioni',
            'anni',
            'qualifiche',
            'contratti',
            // 'livelli' DA ELIMINARE SE CORRETTAMENTE SUPERFLUO
        ));
    }

    public function store(Request $request) {

        $validated = $request->validate([
            'IdAssociazione' => 'required|exists:associazioni,IdAssociazione',
            'idAnno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'DipendenteNome' => 'required|string|max:100',
            'DipendenteCognome' => 'required|string|max:100',
            'Qualifica' => 'required|array',
            'ContrattoApplicato' => 'required|string|max:100',
            'note' => 'nullable|string|max:1000',
            'LivelloMansione' => 'required|string',

        ]);

        return Dipendente::storeDipendente($validated);
    }

    public function edit(int $idDipendente) {
        $dipendente = Dipendente::getOne($idDipendente);
        abort_if(!$dipendente, 404);

        // prendo tutti i livelli disponibili
        $livelli = Dipendente::getLivelliMansione();

        $livelliAttuali = Dipendente::getLivelliMansioneByDipendente($idDipendente);
        // $livelloMansione = $livelliAttuali[0] ?? ''; // prendi il primo se esiste

        // ricavo i NOMI dei livelli attuali (filtrando dalla collection dei livelli)
        $livelliNomiAttuali = $livelli
            ->whereIn('id', $livelliAttuali)
            ->pluck('nome')
            ->toArray();

        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);
        $anni = Dipendente::getAnni();
        $qualifiche = Dipendente::getQualifiche();
        $qualificheAttuali = Dipendente::getQualificheByDipendente($idDipendente);
        $contratti = Dipendente::getContrattiApplicati();
        $livelli = Dipendente::getLivelliMansione();
        return view('dipendenti.edit', compact(
            'dipendente',
            'associazioni',
            'anni',
            'qualifiche',
            'qualificheAttuali',
            'contratti',
            'livelli',
            'livelliAttuali',
            'livelliNomiAttuali'
        ));
    }

    public function update(Request $request, int $idDipendente) {
        $validated = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'DipendenteNome' => 'required|string|max:100',
            'DipendenteCognome' => 'required|string|max:100',
            'Qualifica' => 'required|array',
            'ContrattoApplicato' => 'required|string|max:100',
            'note' => 'nullable|string|max:1000',
            'LivelloMansione' => 'required|string',
        ]);

        return Dipendente::updateDipendente($idDipendente, $validated);
    }

    public function show(int $idDipendente) {
        $dipendente = Dipendente::getOne($idDipendente);
        abort_if(!$dipendente, 404);

        $qualifiche = Dipendente::getNomiQualifiche($idDipendente);

        // Richiamiamo il metodo esistente e ne estraiamo il contenuto JSON
        $livelliJson = $this->getLivelloMansione($idDipendente);
        $livelliMansione = $livelliJson->getData()->livello;

        return view('dipendenti.show', compact('dipendente', 'qualifiche', 'livelliMansione'));
    }

    public function getData(Request $request): JsonResponse {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isElevated = !$this->isImpersonating() && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        $idAssociazione = session('associazione_selezionata') ?? $user->IdAssociazione;

        // Dataset base
        $dataset = $isElevated
            ? Dipendente::getByAssociazione($idAssociazione, $anno)
            : Dipendente::getByAssociazione($user->IdAssociazione, $anno);

        // Mappa dipendente → array qualifiche
        $qualificheMap = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->select('dipendenti_qualifiche.idDipendente', 'qualifiche.nome')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($items) => $items->pluck('nome')->toArray());

        // Aggiunge il campo virtuale "Qualifica" per ogni dipendente
        $dataset->transform(function ($d) use ($qualificheMap) {
            $d->Qualifica = isset($qualificheMap[$d->idDipendente])
                ? implode(', ', $qualificheMap[$d->idDipendente])
                : '';
            return $d;
        });

        // Filtro opzionale
        $dataset = match ($request->tipo) {
            'autisti' => $dataset->filter(
                fn($d) =>
                collect($qualificheMap[$d->idDipendente] ?? [])->contains(fn($q) => str_contains($q, 'AUTISTA'))
            ),
            'altro' => $dataset->reject(
                fn($d) =>
                collect($qualificheMap[$d->idDipendente] ?? [])->contains(fn($q) => str_contains($q, 'AUTISTA'))
            ),
            default => $dataset,
        };

        return response()->json(['data' => $dataset->values()]);
    }

    public function checkDuplicazioneDisponibile(): JsonResponse {
        $annoCorrente = session('anno_riferimento', now()->year);
        $annoPrecedente = $annoCorrente - 1;
        $user = Auth::user();

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = session('associazione_selezionata');
        } else {
            $idAssociazione = $user->IdAssociazione;
        }
        $vuotoCorrente = Dipendente::getByAssociazione($idAssociazione, $annoCorrente)->isEmpty();
        $pienoPrecedente = Dipendente::getByAssociazione($idAssociazione, $annoPrecedente)->isNotEmpty();


        return response()->json([
            'mostraMessaggio' => $vuotoCorrente && $pienoPrecedente,
            'annoCorrente' => $annoCorrente,
            'annoPrecedente' => $annoPrecedente
        ]);
    }

    public function duplicaAnnoPrecedente(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        try {
            if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
                $idAssoc = session('associazione_selezionata');
            } else {
                $idAssoc = $user->IdAssociazione;
            }

            $dipendente = Dipendente::getByAssociazione($idAssoc, $annoPrec);

            if ($dipendente->isEmpty()) {
                return response()->json(['message' => 'Nessuna dipendente da duplicare'], 404);
            }
            foreach ($dipendente as $d) {

                Dipendente::storeDipendente([
                    'idAssociazione' => $idAssoc,
                    'idAnno' => $anno,
                    'DipendenteNome' => $d->DipendenteNome,
                    'Qualifica' => $d->idQualifica,
                    'DipendenteCognome' => [$d->DipendenteCognome],
                    'ContrattoApplicato' => $d->ContrattoApplicato,
                    'note'      => null
                ]);
            }

            return response()->json(['message' => 'Convenzioni duplicate.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Errore durante la duplicazione.', 'error' => $e->getMessage()], 500);
        }
    }

    private function isImpersonating(): bool {
        return session()->has('impersonate');
    }

    public function amministrativi() {
        $anno = session('anno_riferimento', now()->year);
        $titolo = 'Personale Amministrativo';
        $associazioni = Auth::user()->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate')
            ? DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get()
            : collect();
        return view('dipendenti.index', compact('titolo', 'anno', 'associazioni'));
    }

    public function autisti() {
        $anno = session('anno_riferimento', now()->year);
        $titolo = 'Personale Autista';

        $associazioni = Auth::user()->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate')
            ? DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get()
            : collect();

        return view('dipendenti.index', compact('titolo', 'anno', 'associazioni'));
    }

    public function autistiData(Request $request): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $idQualificheAutisti = [1, 2, 3, 4, 5, 6]; // ID da aggiornare se necessario

        $idAssociazione = null;

        if (!$isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssociazione = session('associazione_selezionata');
        } else {
            $idAssociazione = $user->IdAssociazione;
        }

        abort_if(!$idAssociazione, 403, 'Associazione non determinata.');

        $dipendenti = Dipendente::getByAssociazione($idAssociazione, $anno);

        // Aggiunta nome delle qualifiche
        $qualificheMap = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->where('qualifiche.nome', 'LIKE', '%AUTISTA%')
            ->select('dipendenti_qualifiche.idDipendente', 'qualifiche.nome')
            ->get()
            ->groupBy('idDipendente');

        $filtered = $dipendenti->filter(fn($d) => isset($qualificheMap[$d->idDipendente]));

        $filtered->transform(function ($d) use ($qualificheMap) {
            $d->Qualifica = isset($qualificheMap[$d->idDipendente])
                ? implode(', ', $qualificheMap[$d->idDipendente]->pluck('nome')->toArray())
                : '';
            return $d;
        });

        return response()->json(['data' => $filtered->values()]);
    }

    public function getLivelloMansione(int $idDipendente): JsonResponse {
        $livelli = Dipendente::getLivelliMansioneByDipendente($idDipendente);
        $dati = DB::table('livello_mansione')
            ->whereIn('id', $livelli)
            ->select('id', 'nome')
            ->get();

        return response()->json(['livello' => $dati]);
    }

    public function destroy($id) {
        Dipendente::eliminaDipendente($id);

        return redirect()->route('dipendenti.index')
            ->with('success', 'Dipendente eliminato correttamente.');
    }

    public function byQualifica(int $id) {
        $anno = session('anno_riferimento', now()->year);
        $q = DB::table('qualifiche')->where('id', $id)->first();
        abort_if(!$q, 404, 'Qualifica non trovata');

        $titolo = 'Personale – ' . $q->nome;

        $associazioni = Auth::user()->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || session()->has('impersonate')
            ? DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get()
            : collect();

        // Passo anche l’ID per la view (serve al JS per l’ajax url)
        return view('dipendenti.index', [
            'titolo'       => $titolo,
            'anno'         => $anno,
            'associazioni' => $associazioni,
            'qualificaId'  => $id,
        ]);
    }

    public function byQualificaData(Request $request, int $id): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && !session()->has('impersonate')
            ? (session('associazione_selezionata') ?? null)
            : $user->IdAssociazione;

        if (!$idAssociazione) {
            return response()->json(['data' => []]);
        }

        $dataset = Dipendente::getByAssociazione($idAssociazione, $anno);

        // tieni solo chi ha quella qualifica
        $idsDip = DB::table('dipendenti_qualifiche')
            ->where('idQualifica', $id)
            ->pluck('idDipendente')
            ->toArray();

        $filtered = $dataset->filter(fn($d) => in_array($d->idDipendente, $idsDip, true));

        return response()->json(['data' => $filtered->values()]);
    }
}
