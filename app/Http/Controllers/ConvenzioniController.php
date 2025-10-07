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

    /* =========================
       INDEX
       ========================= */
    public function index(Request $request) {
        $user = Auth::user();
        $anno = (int) session('anno_riferimento', now()->year);

        $associazioni  = collect();
        $selectedAssoc = null;

        // chiave usata in tutta l’app per ricordare l’associazione corrente
        $sessionKey = 'associazione_selezionata';

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            if ($request->filled('idAssociazione')) {
                $selectedAssoc = (int) $request->get('idAssociazione');
                session([$sessionKey => $selectedAssoc]);
            } else {
                $selectedAssoc = (int) (session($sessionKey, $associazioni->first()->idAssociazione ?? 0));
            }
        } else {
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        // allinea anche la sessione “globale” se passo da query
        if ($request->filled('idAssociazione')) {
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

    /* =========================
       CREATE
       ========================= */
    public function create() {
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

        $selectedAssoc = session('associazione_selezionata') ?? ($associazioni->first()->idAssociazione ?? null);
        $selectedAnno  = session('selectedAnno') ?? ($anni->first()->idAnno ?? null);

        return view('convenzioni.create', compact(
            'anni',
            'associazioni',
            'aziendeSanitarie',
            'selectedAssoc',
            'selectedAnno'
        ));
    }

    /* =========================
       STORE
       ========================= */
    public function store(Request $request) {
        $validated = $request->validate([
            'idAssociazione'        => 'required|exists:associazioni,idAssociazione',
            'idAnno'                => 'required|exists:anni,idAnno',
            'Convenzione'           => 'required|string|max:255',
            'note'                  => 'nullable|string',
            'aziende_sanitarie'     => 'nullable|array',
            'aziende_sanitarie.*'   => 'exists:aziende_sanitarie,idAziendaSanitaria',
            'materiale_fornito_asl' => 'required|boolean', // <— nuovo flag
        ]);

        $idConv = DB::table('convenzioni')->insertGetId([
            'idAssociazione'        => (int) $validated['idAssociazione'],
            'idAnno'                => (int) $validated['idAnno'],
            'Convenzione'           => strtoupper(trim($validated['Convenzione'])),
            'note'                  => $validated['note'] ?? null,
            'materiale_fornito_asl' => (int) (bool) $validated['materiale_fornito_asl'],
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // aziende sanitarie collegate (se presenti)
        if (!empty($validated['aziende_sanitarie'])) {
            $this->syncAziendeSanitarie($idConv, $validated['aziende_sanitarie']);
        }

        return redirect()->route('convenzioni.index', [
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno'         => $validated['idAnno'],
        ])->with('success', 'Convenzione creata con successo.');
    }

    /* =========================
       EDIT
       ========================= */
    public function edit(int $id) {
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

        $aziendeSanitarie = DB::table('aziende_sanitarie')
            ->select('idAziendaSanitaria', 'Nome')
            ->orderBy('Nome')
            ->get();

        $aziendeSelezionate = DB::table('azienda_sanitaria_convenzione')
            ->where('idConvenzione', $id)
            ->pluck('idAziendaSanitaria')
            ->toArray();

        $selectedAssoc = session('associazione_selezionata') ?? $conv->idAssociazione;
        $selectedAnno  = session('selectedAnno') ?? $conv->idAnno;

        return view('convenzioni.edit', compact(
            'conv',
            'associazioni',
            'anni',
            'aziendeSanitarie',
            'aziendeSelezionate',
            'selectedAssoc',
            'selectedAnno'
        ));
    }

    /* =========================
       UPDATE
       ========================= */
    public function update(Request $request, int $id) {
        $validated = $request->validate([
            'idAssociazione'        => 'required|exists:associazioni,idAssociazione',
            'idAnno'                => 'required|exists:anni,idAnno',
            'Convenzione'           => 'required|string|max:255',
            'note'                  => 'nullable|string',
            'aziende_sanitarie'     => 'nullable|array',
            'aziende_sanitarie.*'   => 'exists:aziende_sanitarie,idAziendaSanitaria',
            'materiale_fornito_asl' => 'required|boolean', // <— nuovo flag
        ]);

        DB::table('convenzioni')
            ->where('idConvenzione', $id)
            ->update([
                'idAssociazione'        => (int) $validated['idAssociazione'],
                'idAnno'                => (int) $validated['idAnno'],
                'Convenzione'           => strtoupper(trim($validated['Convenzione'])),
                'note'                  => $validated['note'] ?? null,
                'materiale_fornito_asl' => (int) (bool) $validated['materiale_fornito_asl'],
                'updated_at'            => now(),
            ]);

        $this->syncAziendeSanitarie($id, $validated['aziende_sanitarie'] ?? []);

        return redirect()->route('convenzioni.index', [
            'idAssociazione' => $validated['idAssociazione'],
            'idAnno'         => $validated['idAnno'],
        ])->with('success', 'Convenzione aggiornata.');
    }

    /* =========================
       DESTROY
       ========================= */
    public function destroy(int $id) {
        abort_if(!Convenzione::getById($id), 404);

        Convenzione::deleteConvenzione($id);

        return redirect()->route('convenzioni.index')->with('success', 'Convenzione eliminata.');
    }

    /* =========================
       CHECK DUPLICAZIONE
       ========================= */
    public function checkDuplicazioneDisponibile(): JsonResponse {
        $anno     = (int) session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user     = Auth::user();

        $idAssoc = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? (int) session('associazione_selezionata')
            : (int) $user->IdAssociazione;

        $correnteVuoto   = Convenzione::getByAssociazioneAnno($idAssoc, $anno)->isEmpty();
        $precedentePieno = Convenzione::getByAssociazioneAnno($idAssoc, $annoPrec)->isNotEmpty();

        return response()->json([
            'mostraMessaggio' => $correnteVuoto && $precedentePieno,
            'annoCorrente'    => $anno,
            'annoPrecedente'  => $annoPrec,
        ]);
    }

    /* =========================
       DUPLICA ANNO PRECEDENTE
       ========================= */
    public function duplicaAnnoPrecedente(): JsonResponse {
        $anno     = (int) session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user     = Auth::user();

        try {
            $idAssoc = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
                ? (int) session('associazione_selezionata')
                : (int) $user->IdAssociazione;

            $convenzioni = Convenzione::getByAssociazioneAnno($idAssoc, $annoPrec);
            if ($convenzioni->isEmpty()) {
                return response()->json(['message' => 'Nessuna convenzione da duplicare'], 404);
            }

            foreach ($convenzioni as $c) {
                DB::table('convenzioni')->insert([
                    'idAssociazione'        => $idAssoc,
                    'idAnno'                => $anno,
                    'Convenzione'           => $c->Convenzione,
                    'note'                  => $c->note,
                    'ordinamento'           => $c->ordinamento,
                    'materiale_fornito_asl' => (int) ($c->materiale_fornito_asl ?? 0),
                    'lettera_identificativa' => $c->lettera_identificativa ?? null, // se presente in schema
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }

            return response()->json(['message' => 'Convenzioni duplicate.']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Errore durante la duplicazione.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /* =========================
       RIORDINA
       ========================= */
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

    /* =========================
       SUPPORTO: sync aziende sanitarie
       ========================= */
    private function syncAziendeSanitarie(int $idConvenzione, array $idAziende): void {
        DB::table('azienda_sanitaria_convenzione')
            ->where('idConvenzione', $idConvenzione)
            ->delete();

        if (!empty($idAziende)) {
            $now = now();
            $rows = array_map(fn($idAz) => [
                'idAziendaSanitaria' => (int) $idAz,
                'idConvenzione'      => $idConvenzione,
                'created_at'         => $now,
                'updated_at'         => $now,
            ], $idAziende);

            DB::table('azienda_sanitaria_convenzione')->insert($rows);
        }
    }  
}
