<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;
use Illuminate\Http\JsonResponse;

class ConvenzioniController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    public function index(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $associazioni = [];
        $selectedAssoc = null;

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            if ($request->has('idAssociazione')) {
                session(['associazione_selezionata' => $request->get('idAssociazione')]);
            }

            $selectedAssoc = $request->get('idAssociazione') ?? ($associazioni->first()->idAssociazione ?? null);
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }

        $convenzioni = Convenzione::getWithAssociazione($selectedAssoc, $anno);

        return view('convenzioni.index', compact(
            'convenzioni',
            'anno',
            'associazioni',
            'selectedAssoc'
        ));
    }

public function create()
{
    $anni = DB::table('anni')->orderBy('anno', 'desc')->get();

    $associazioni = DB::table('associazioni')
        ->select('idAssociazione', 'Associazione')
        ->whereNull('deleted_at')
        ->orderBy('Associazione')
        ->get();

    $aziendeSanitarie = DB::table('aziende_sanitarie')
        ->select('idAziendaSanitaria', 'Nome')
        ->orderBy('Nome')
        ->get();

    return view('convenzioni.create', compact('anni', 'associazioni', 'aziendeSanitarie'));
}

    public function store(Request $request) {
        $validated = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|exists:anni,idAnno',
            'Convenzione' => 'required|string|max:255',
            'lettera_identificativa' => 'required|string|max:5',
            'aziende_sanitarie' => 'nullable|array',
            'aziende_sanitarie.*' => 'exists:aziende_sanitarie,idAziendaSanitaria',
        ]);

        $idConv = DB::table('convenzioni')->insertGetId([
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
            'Convenzione' => $validated['Convenzione'],
            'lettera_identificativa' => $validated['lettera_identificativa'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!empty($validated['aziende_sanitarie'])) {
            $this->syncAziendeSanitarie($idConv, $validated['aziende_sanitarie']);
        }

        return redirect()->route('convenzioni.index', [
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
        ])->with('success', 'Convenzione creata con successo.');
    }

    public function edit(int $id) {
        $conv = Convenzione::getById($id);
        abort_if(! $conv, 404);

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->whereNot('idAssociazione', 1)
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        // 🔽 Qui recuperi tutte le aziende sanitarie disponibili
        $aziendeSanitarie = DB::table('aziende_sanitarie')
            ->select('idAziendaSanitaria', 'Nome')
            ->orderBy('Nome')
            ->get();

        // 🔽 Qui quelle associate alla convenzione corrente
        $aziendeSelezionate = DB::table('azienda_sanitaria_convenzione')
            ->where('idConvenzione', $id)
            ->pluck('idAziendaSanitaria')
            ->toArray();

        return view('convenzioni.edit', compact(
            'conv',
            'associazioni',
            'anni',
            'aziendeSanitarie',
            'aziendeSelezionate'
        ));
    }


    public function update(Request $request, int $id) {
        $validated = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|exists:anni,idAnno',
            'Convenzione' => 'required|string|max:255',
            'lettera_identificativa' => 'required|string|max:5',
            'aziende_sanitarie' => 'nullable|array',
            'aziende_sanitarie.*' => 'exists:aziende_sanitarie,idAziendaSanitaria',
        ]);

        DB::table('convenzioni')->where('idConvenzione', $id)->update([
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
            'Convenzione' => $validated['Convenzione'],
            'lettera_identificativa' => $validated['lettera_identificativa'],
            'updated_at' => now(),
        ]);

        $this->syncAziendeSanitarie($id, $validated['aziende_sanitarie'] ?? []);

        return redirect()->route('convenzioni.index', [
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
        ])->with('success', 'Convenzione aggiornata.');
    }

    public function destroy(int $id) {
        abort_if(!Convenzione::getById($id), 404);

        Convenzione::deleteConvenzione($id);

        return redirect()->route('convenzioni.index')->with('success', 'Convenzione eliminata.');
    }

    public function checkDuplicazioneDisponibile(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $correnteVuoto = DB::table('convenzioni')->where('idAnno', $anno)->doesntExist();
            $precedentePieno = DB::table('convenzioni')->where('idAnno', $annoPrec)->exists();
        } else {
            $idAssoc = $user->IdAssociazione;
            $correnteVuoto = Convenzione::getByAssociazioneAnno($idAssoc, $anno)->isEmpty();
            $precedentePieno = Convenzione::getByAssociazioneAnno($idAssoc, $annoPrec)->isNotEmpty();
        }

        return response()->json([
            'mostraMessaggio' => $correnteVuoto && $precedentePieno,
            'annoCorrente'    => $anno,
            'annoPrecedente'  => $annoPrec,
        ]);
    }

    public function duplicaAnnoPrecedente(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        try {
            if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
                $convenzioni = DB::table('convenzioni')->where('idAnno', $annoPrec)->get();
            } else {
                $idAssoc = $user->IdAssociazione;
                $convenzioni = Convenzione::getByAssociazioneAnno($idAssoc, $annoPrec);
            }

            if ($convenzioni->isEmpty()) {
                return response()->json(['message' => 'Nessuna convenzione da duplicare'], 404);
            }

            foreach ($convenzioni as $c) {
                Convenzione::createConvenzione([
                    'idAssociazione'         => $c->idAssociazione,
                    'idAnno'                 => $anno,
                    'Convenzione'            => $c->Convenzione,
                    'lettera_identificativa' => $c->lettera_identificativa,
                ]);
            }

            return response()->json(['message' => 'Convenzioni duplicate.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Errore durante la duplicazione.', 'error' => $e->getMessage()], 500);
        }
    }

    public function riordina(Request $request): JsonResponse {
        $ids = $request->input('order');

        if (!is_array($ids)) {
            return response()->json(['message' => 'Formato dati non valido'], 422);
        }

        foreach ($ids as $index => $id) {
            DB::table('convenzioni')
                ->where('idConvenzione', $id)
                ->update(['ordinamento' => $index]);
        }

        return response()->json(['message' => 'Ordinamento aggiornato']);
    }

    private function syncAziendeSanitarie(int $idConvenzione, array $idAziende): void {
        DB::table('azienda_sanitaria_convenzione')
            ->where('idConvenzione', $idConvenzione)
            ->delete();

        if (!empty($idAziende)) {
            $now = now();
            $insertData = array_map(fn($idAz) => [
                'idAziendaSanitaria' => $idAz,
                'idConvenzione' => $idConvenzione,
                'created_at' => $now,
                'updated_at' => $now,
            ], $idAziende);

            DB::table('azienda_sanitaria_convenzione')->insert($insertData);
        }
    }
}
