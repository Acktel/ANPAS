<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\AziendaSanitaria;

class AziendeSanitarieController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

public function index(Request $request)
{
    $user = Auth::user();
    $anno = session('anno_riferimento', now()->year);

    // lista associazioni solo per ruoli elevati
    $associazioni = collect();
    $selectedAssoc = null;

    if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
        $associazioni = DB::table('associazioni')
            ->select('IdAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->where('IdAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        if ($request->has('idAssociazione')) {
            session(['associazione_selezionata' => $request->idAssociazione]);
        }

        $selectedAssoc = session('associazione_selezionata')
            ?? ($associazioni->first()->IdAssociazione ?? null);
    } else {
        $selectedAssoc = $user->IdAssociazione;
    }

    // carico anche le aziende sanitarie (quelle con convenzioni)
    $aziende = AziendaSanitaria::getAllWithConvenzioni();

    return view('aziende_sanitarie.index', compact(
        'anno',
        'associazioni',
        'selectedAssoc',
        'aziende'      // <-- aggiunto!
    ));
}


    public function getData(): JsonResponse {
        $data = AziendaSanitaria::getAllWithConvenzioni();
        return response()->json(['data' => $data]);
    }

    public function create() {
        $user = Auth::user();
        $anni = DB::table('anni')->orderBy('anno', 'desc')->get();

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        // Aggiungi questo:
        $aziendeSanitarie = DB::table('aziende_sanitarie')
            ->select('idAziendaSanitaria', 'Nome')
            ->orderBy('Nome')
            ->get();

        //carico le convenzioni
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        return view('aziende_sanitarie.create', compact(
            'anni',
            'associazioni',
                        'convenzioni',
            'aziendeSanitarie'
        ));
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'Nome' => 'required|string|max:150',
            'Indirizzo' => 'nullable|string|max:255',
            'mail' => 'nullable|email|max:150',
            'note' => 'nullable|string',
            'convenzioni' => 'nullable|array',
            'convenzioni.*' => 'exists:convenzioni,idConvenzione',
        ]);

        $id = AziendaSanitaria::createSanitaria($validated);

        if (!empty($validated['convenzioni'])) {
            AziendaSanitaria::syncConvenzioni($id, $validated['convenzioni']);
        }

        return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda creata.');
    }

    public function edit(int $id) {
        // Singola azienda sanitaria
        $azienda = DB::table('aziende_sanitarie')
            ->where('idAziendaSanitaria', $id)
            ->first();
        abort_if(!$azienda, 404);

        // Tutte le convenzioni disponibili
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        // Convenzioni collegate all’azienda
        $convenzioniSelezionate = DB::table('azienda_sanitaria_convenzione')
            ->where('idAziendaSanitaria', $id)
            ->pluck('idConvenzione')
            ->toArray();

        return view('aziende_sanitarie.edit', compact(
            'azienda',
            'convenzioni',
            'convenzioniSelezionate'
        ));
    }

    public function update(Request $request, int $id) {
        $validated = $request->validate([
            'Nome' => 'required|string|max:150',
            'Indirizzo' => 'nullable|string|max:255',
            'mail' => 'nullable|email|max:150',
            'note' => 'nullable|string',
            'convenzioni' => 'nullable|array',
            'convenzioni.*' => 'exists:convenzioni,idConvenzione',
        ]);

        AziendaSanitaria::updateSanitaria($id, $validated);

        if (array_key_exists('convenzioni', $validated)) {
            AziendaSanitaria::syncConvenzioni($id, $validated['convenzioni']);
        }

        return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda aggiornata.');
    }

    public function destroy(int $id) {
        AziendaSanitaria::deleteSanitaria($id);

        return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda eliminata.');
    }
}
