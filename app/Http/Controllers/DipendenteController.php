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

    public function index() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        $dipendenti = (!$isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            ? Dipendente::getAll($anno)
            : Dipendente::getByAssociazione($user->IdAssociazione, $anno);

        $titolo = (!$isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            ? 'Tutti i Dipendenti'
            : 'Dipendenti della mia Associazione';

        return view('dipendenti.index', compact('dipendenti', 'titolo', 'anno'));
    }

    public function create()
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $associazioni = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? DB::table('associazioni')->get()
            : DB::table('associazioni')->where('idAssociazione', $user->IdAssociazione)->get();

        $anni = DB::table('anni')->orderByDesc('anno')->get();
        $qualifiche = DB::table('qualifiche')->get();
        $contratti = DB::table('contratti_applicati')->get();
        $livelli = DB::table('livello_mansione')->get();

        return view('dipendenti.create', compact(
            'associazioni',
            'anni',
            'qualifiche',
            'contratti',
            'livelli'
        ));
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'IdAssociazione'     => 'required|exists:associazioni,IdAssociazione',
            'idAnno'             => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'DipendenteNome'     => 'required|string|max:100',
            'DipendenteCognome'  => 'required|string|max:100',
            'Qualifica'          => 'required|array',
            'ContrattoApplicato' => 'required|string|max:100',
            'LivelloMansione' => 'required|array',
            'LivelloMansione.*' => 'exists:livello_mansione,id',

        ]);

        return Dipendente::storeDipendente($validated);
    }

    public function edit(int $idDipendente) {
        $dipendente = Dipendente::getOne($idDipendente);
        abort_if(!$dipendente, 404);

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
            'livelli'
        ));
    }

    public function update(Request $request, int $idDipendente) {
        $validated = $request->validate([
            'idAssociazione'     => 'required|exists:associazioni,idAssociazione',
            'idAnno'             => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'DipendenteNome'     => 'required|string|max:100',
            'DipendenteCognome'  => 'required|string|max:100',
            'Qualifica'          => 'required|array',
            'ContrattoApplicato' => 'required|string|max:100',
            'LivelloMansione' => 'required|array',
            'LivelloMansione.*' => 'exists:livello_mansione,id',
        ]);

        return Dipendente::updateDipendente($idDipendente, $validated);
    }

    public function show(int $idDipendente) {
        $dipendente = Dipendente::getOne($idDipendente);
        abort_if(!$dipendente, 404);

        $qualifiche = Dipendente::getNomiQualifiche($idDipendente);
        return view('dipendenti.show', compact('dipendente', 'qualifiche'));
    }

    public function getData(Request $request): JsonResponse {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isElevated = !$this->isImpersonating() && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        // Dataset base
        $dataset = $isElevated
            ? Dipendente::getAll($anno)
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

        $idAssociazione = (!$this->isImpersonating() && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            ? null
            : $user->IdAssociazione;

        $vuotoCorrente = Dipendente::getByAssociazione($idAssociazione, $annoCorrente)->isEmpty();
        $pienoPrecedente = Dipendente::getByAssociazione($idAssociazione, $annoPrecedente)->isNotEmpty();

        return response()->json([
            'mostraMessaggio'  => $vuotoCorrente && $pienoPrecedente,
            'annoCorrente'     => $annoCorrente,
            'annoPrecedente'   => $annoPrecedente
        ]);
    }

    private function isImpersonating(): bool {
        return session()->has('impersonate');
    }

    public function amministrativiData(Request $request): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $dataset = Dipendente::getAmministrativi($anno);

        // opzionale: se vuoi ancora aggiungere nomi delle qualifiche
        $qualificheMap = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->select('dipendenti_qualifiche.idDipendente', 'qualifiche.nome')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($items) => $items->pluck('nome')->toArray());

        $dataset->transform(function ($d) use ($qualificheMap) {
            $d->Qualifica = isset($qualificheMap[$d->idDipendente])
                ? implode(', ', $qualificheMap[$d->idDipendente])
                : '';
            return $d;
        });

        return response()->json(['data' => $dataset->values()]);
    }

    public function amministrativi() {
        $anno = session('anno_riferimento', now()->year);
        $titolo = 'Personale Amministrativo';
        return view('dipendenti.index', compact('titolo', 'anno'));
    }

    public function autisti() {
        $anno = session('anno_riferimento', now()->year);
        $titolo = 'Personale Autista';
        return view('dipendenti.index', compact('titolo', 'anno'));
    }

    public function autistiData(Request $request): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $idQualificheAutisti = [1, 2, 3, 4, 5, 6];

        $dipendenti = Dipendente::getAll($anno);

        $qualificheMap = DB::table('dipendenti_qualifiche')
            ->whereIn('idQualifica', $idQualificheAutisti)
            ->select('idDipendente', 'idQualifica')
            ->get()
            ->groupBy('idDipendente');

        $filtered = $dipendenti->filter(fn($d) => isset($qualificheMap[$d->idDipendente]));

        // facoltativo: aggiungi anche il nome delle qualifiche
        $nomiQualificheMap = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->select('dipendenti_qualifiche.idDipendente', 'qualifiche.nome')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($items) => $items->pluck('nome')->toArray());

        $filtered->transform(function ($d) use ($nomiQualificheMap) {
            $d->Qualifica = isset($nomiQualificheMap[$d->idDipendente])
                ? implode(', ', $nomiQualificheMap[$d->idDipendente])
                : '';
            return $d;
        });

        return response()->json(['data' => $filtered->values()]);
    }
}
