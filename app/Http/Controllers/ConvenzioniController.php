<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;
use Illuminate\Http\JsonResponse;

class ConvenzioniController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $associazioni = collect();
        $selectedAssoc = null;

        // chiave unica per questa pagina (puoi anche usare il nome route con route()->getName())
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
        if ($request->has('idAssociazione')) {
            session(['associazione_selezionata' => $selectedAssoc]);
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

        $materiali = DB::table('materiale_sanitario')
            ->select('id', 'sigla', 'descrizione')
            ->orderBy('descrizione')
            ->get();

        // Recupera dalla sessione la selezione corrente
        $selectedAssoc = session('associazione_selezionata') ?? ($associazioni->first()->idAssociazione ?? null);
        $selectedAnno = session('selectedAnno') ?? ($anni->first()->idAnno ?? null);

        return view('convenzioni.create', compact('anni', 'associazioni', 'aziendeSanitarie', 'materiali', 'selectedAssoc', 'selectedAnno'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|exists:anni,idAnno',
            'Convenzione' => 'required|string|max:255',
            'note' => 'nullable|string',
            'aziende_sanitarie' => 'nullable|array',
            'aziende_sanitarie.*' => 'exists:aziende_sanitarie,idAziendaSanitaria',
            'materiali' => 'nullable|array',
            'materiali.*' => 'exists:materiale_sanitario,id',
        ]);

        $idConv = DB::table('convenzioni')->insertGetId([
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
            'Convenzione' => $validated['Convenzione'],
            'note' => $validated['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $materialiIds = $validated['materiali'] ?? [];

        if (!empty($validated['materiale_sanitario'])) {
            $idMat = DB::table('materiale_sanitario')->insertGetId([
                'sigla' => substr($validated['materiale_sanitario'], 0, 10),
                'descrizione' => $validated['materiale_sanitario'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $materialiIds[] = $idMat;
        }

        if (!empty($materialiIds)) {
            $this->syncMateriali($idConv, $materialiIds);
        }

        if (!empty($validated['aziende_sanitarie'])) {
            $this->syncAziendeSanitarie($idConv, $validated['aziende_sanitarie']);
        }

        return redirect()->route('convenzioni.index', [
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
        ])->with('success', 'Convenzione creata con successo.');
    }

    public function edit(int $id)
    {
        $conv = Convenzione::getById($id);
        abort_if(!$conv, 404);

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

        $materiali = DB::table('materiale_sanitario')
            ->select('id', 'sigla', 'descrizione')
            ->orderBy('descrizione')
            ->get();

        // 🔽 Materiali già selezionati per questa convenzione
        $materialiSelezionati = DB::table('convenzioni_materiale_sanitario')
            ->where('idConvenzione', $id)
            ->pluck('idMaterialeSanitario')
            ->toArray();

        // Recupera dalla sessione o fallback ai valori correnti della convenzione
        $selectedAssoc = session('associazione_selezionata') ?? $conv->idAssociazione;
        $selectedAnno = session('selectedAnno') ?? $conv->idAnno;

        return view('convenzioni.edit', compact(
            'conv',
            'associazioni',
            'anni',
            'aziendeSanitarie',
            'aziendeSelezionate',
            'materiali',
            'materialiSelezionati',
            'selectedAssoc',
            'selectedAnno'
        ));
    }


    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|exists:anni,idAnno',
            'Convenzione' => 'required|string|max:255',
            'note' => 'nullable|string',
            'aziende_sanitarie' => 'nullable|array',
            'aziende_sanitarie.*' => 'exists:aziende_sanitarie,idAziendaSanitaria',
            'materiali' => 'nullable|array',
            'materiali.*' => 'exists:materiale_sanitario,id',
        ]);

        DB::table('convenzioni')->where('idConvenzione', $id)->update([
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
            'Convenzione' => $validated['Convenzione'],
            'note' => $validated['note'] ?? null,
            'updated_at' => now(),
        ]);

        $this->syncMateriali($id, $validated['materiali'] ?? []);

        $this->syncAziendeSanitarie($id, $validated['aziende_sanitarie'] ?? []);

        return redirect()->route('convenzioni.index', [
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno' => $validated['idAnno'],
        ])->with('success', 'Convenzione aggiornata.');
    }

    private function syncMateriali(int $idConvenzione, array $idMateriali): void
    {
        DB::table('convenzioni_materiale_sanitario')
            ->where('idConvenzione', $idConvenzione)
            ->delete();

        if (!empty($idMateriali)) {
            $now = now();
            $insertData = array_map(fn($idMat) => [
                'idConvenzione' => $idConvenzione,
                'idMaterialeSanitario' => $idMat,
                'created_at' => $now,
                'updated_at' => $now,
            ], $idMateriali);

            DB::table('convenzioni_materiale_sanitario')->insert($insertData);
        }
    }

    public function destroy(int $id)
    {
        abort_if(!Convenzione::getById($id), 404);

        Convenzione::deleteConvenzione($id);

        return redirect()->route('convenzioni.index')->with('success', 'Convenzione eliminata.');
    }

    public function checkDuplicazioneDisponibile(): JsonResponse
    {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $idAssoc = session('associazione_selezionata');
        } else {
            $idAssoc = $user->IdAssociazione;
        }
        $correnteVuoto = Convenzione::getByAssociazioneAnno($idAssoc, $anno)->isEmpty();
        $precedentePieno = Convenzione::getByAssociazioneAnno($idAssoc, $annoPrec)->isNotEmpty();

        return response()->json([
            'mostraMessaggio' => $correnteVuoto && $precedentePieno,
            'annoCorrente' => $anno,
            'annoPrecedente' => $annoPrec,
        ]);
    }

    public function duplicaAnnoPrecedente(): JsonResponse
    {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        try {
            if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
                $idAssoc = session('associazione_selezionata');
            } else {
                $idAssoc = $user->IdAssociazione;
            }
            
            $convenzioni = Convenzione::getByAssociazioneAnno($idAssoc, $annoPrec);
            
           
            if ($convenzioni->isEmpty()) {
                return response()->json(['message' => 'Nessuna convenzione da duplicare'], 404);
            }

            foreach ($convenzioni as $c) {
                
                Convenzione::createConvenzione([
                    'idAssociazione' => $idAssoc,
                    'idAnno' => $anno,
                    'Convenzione' => $c->Convenzione,
                    'note'      => $c->note,
                    "ordinamento"=> $c->ordinamento,
                ]);
            }

            return response()->json(['message' => 'Convenzioni duplicate.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Errore durante la duplicazione.', 'error' => $e->getMessage()], 500);
        }
    }

    public function riordina(Request $request): JsonResponse
    {
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

    private function syncAziendeSanitarie(int $idConvenzione, array $idAziende): void
    {
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
