<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Automezzo;
use App\Models\AutomezzoKm;
use App\Models\AutomezzoKmRiferimento;
use Illuminate\Support\Facades\Log;
use App\Models\VehicleType;
use App\Models\FuelType;
use Illuminate\Http\JsonResponse;

class AutomezziController extends Controller {
    /**
     * GET /automezzi
     * Mostra lista automezzi con filtro per associazione (solo per ruoli elevati)
     */
    public function index(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');

        $associazioni = collect();
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('IdAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('IdAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();
            $selectedAssoc = $request->get('idAssociazione')
                ?? ($associazioni->first()->IdAssociazione ?? null);
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }

        if ($request->has('idAssociazione')) {
            session(['associazione_selezionata' => $request->idAssociazione]);
        }
        $selectedAssoc = session('associazione_selezionata') ?? $user->IdAssociazione;

        return view('automezzi.index', compact(
            'anno',
            'associazioni',
            'selectedAssoc',
            'isImpersonating'
        ));
    }

    public function create() {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->whereNot("idAssociazione", 1)
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')->select('idAnno', 'anno')->orderBy('anno', 'desc')->get();

        $vehicleTypes = DB::table('vehicle_types')->select('id', 'nome')->orderBy('nome')->get();
        $fuelTypes = DB::table('fuel_types')->select('id', 'nome')->orderBy('nome')->get();

        if (!in_array(Auth::user()->role_id, [1, 2, 3])) {
            $associazioni = $associazioni->where('idAssociazione', Auth::user()->idAssociazione);
        }

        // Recupera dalla sessione o fallback al primo elemento
        $selectedAssociazione = session('selectedAssociazione') ?? ($associazioni->first()->idAssociazione ?? null);
        $annoCorr = session('annoCorrente') ?? ($anni->first()->idAnno ?? null);

        return view('automezzi.create', compact('associazioni', 'anni', 'vehicleTypes', 'fuelTypes', 'selectedAssociazione', 'annoCorr'));
    }

    public function store(Request $request) {
        $rules = [
        'idAssociazione' => 'required|exists:associazioni,idAssociazione',
        'idAnno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
        'Targa' => 'required|string|max:50',
        'CodiceIdentificativo' => 'required|string|max:100',

        // opzionali
        'AnnoPrimaImmatricolazione' => 'nullable|integer|min:1900|max:' . date('Y'),
        'AnnoAcquisto' => 'nullable|integer|min:1900|max:' . date('Y'),
        'Modello' => 'nullable|string|max:255',
        'idTipoVeicolo' => 'nullable|exists:vehicle_types,id',
        'KmRiferimento' => 'nullable|integer|min:0',
        'KmTotali' => 'nullable|integer|min:0',
        'idTipoCarburante' => 'nullable|exists:fuel_types,id',
        'DataUltimaAutorizzazioneSanitaria' => 'nullable|date',
        'DataUltimoCollaudo' => 'nullable|date',
        'incluso_riparto' => 'boolean',
        'note' => 'nullable|string',
        'informazioniAggiuntive' => 'nullable|string',
        ];

        $validated = $request->validate($rules);
        DB::beginTransaction();

        try {
            $newId = Automezzo::createAutomezzo($validated);

            if (!is_null($validated['KmRiferimento'] ?? null)) {
                AutomezzoKmRiferimento::insertKmRiferimento([
                    'idAutomezzo'    => $newId,
                    'idAnno'         => $validated['idAnno'],
                    'KmRiferimento'  => (int)$validated['KmRiferimento'],
                ]);
            }

            DB::commit();
            return redirect()->route('automezzi.index')->with('success', 'Automezzo creato correttamente.');
        } catch (\Exception $e) {
            // log completo con stack trace
            Log::error('Errore durante creazione automezzo', [
                'exception' => $e,
                'request' => $request->all()
            ]);
            
            DB::rollBack();
            // opzionale: rilancia per vedere errore completo sul browser in debug
            if (config('app.debug')) {
                throw $e;
            }

            // messaggio custom per l'utente
            return back()->with('error', 'Errore interno durante la creazione.');
        }
    }

    public function show(int $idAutomezzo) {
        $anno = session('anno_riferimento', now()->year);
        $automezzo = Automezzo::getById($idAutomezzo, $anno);
        abort_if(!$automezzo, 404);

        return view('automezzi.show', compact('automezzo'));
    }

    public function edit(int $idAutomezzo) {
        $anno = session('anno_riferimento', now()->year);
        $automezzo = Automezzo::getById($idAutomezzo, $anno);
        abort_if(!$automezzo, 404);

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->whereNot("idAssociazione", 1)
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')->select('idAnno', 'anno')->orderBy('anno', 'desc')->get();
        $vehicleTypes = DB::table('vehicle_types')->select('id', 'nome')->orderBy('nome')->get();
        $fuelTypes = DB::table('fuel_types')->select('id', 'nome')->orderBy('nome')->get();

        // Recupera dalla sessione o fallback ai valori correnti dell'automezzo
        $selectedAssociazione = session('selectedAssociazione') ?? $automezzo->idAssociazione;
        $annoCorr = session('annoCorrente') ?? $automezzo->idAnno;

        return view('automezzi.edit', compact('automezzo', 'associazioni', 'anni', 'vehicleTypes', 'fuelTypes', 'selectedAssociazione', 'annoCorr'));
    }

    public function update(Request $request, int $idAutomezzo) {
        $rules = [
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'Targa' => 'required|string|max:50',
            'CodiceIdentificativo' => 'required|string|max:100',
            'AnnoPrimaImmatricolazione' => 'integer|min:1900|max:' . date('Y'),
            'AnnoAcquisto' => 'nullable|integer|min:1900|max:' . date('Y'),
            'Modello' => 'string|max:255',
            'idTipoVeicolo' => 'exists:vehicle_types,id',
            'KmRiferimento' => 'integer|min:0',
            'KmTotali' => 'nullable|integer|min:0',
            'idTipoCarburante' => 'exists:fuel_types,id',
            'DataUltimaAutorizzazioneSanitaria' => 'nullable|date',
            'DataUltimoCollaudo' => 'nullable|date',
            'incluso_riparto' => 'boolean',
            'note' => 'nullable|string',
            'informazioniAggiuntive' => 'nullable|string'
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {
            Automezzo::updateAutomezzo($idAutomezzo, $validated);

            AutomezzoKmRiferimento::updateOrCreate(
                ['idAutomezzo' => (int)$idAutomezzo, 'idAnno' => $validated['idAnno']],
                ['KmRiferimento' => (int)$validated['KmRiferimento']]
            );

            DB::commit();
            return redirect()->route('automezzi.index')->with('success', 'Automezzo aggiornato.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Errore interno durante l’aggiornamento.']);
        }
    }

    public function destroy(int $idAutomezzo) {
        $automezzo = Automezzo::getById($idAutomezzo, session('anno_riferimento', now()->year));
        abort_if(!$automezzo, 404);

        DB::beginTransaction();
        try {
            Automezzo::deleteAutomezzo($idAutomezzo); // opzionale passare $anno
            DB::commit();
            return redirect()->route('automezzi.index')->with('success', 'Automezzo eliminato.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AutomezziController@destroy: delete failed', [
                'idAutomezzo' => $idAutomezzo,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Eliminazione fallita: '.$e->getMessage()]);
        }
    }

    public function checkDuplicazioneDisponibile(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $correnteVuoto = Automezzo::getAll($anno)->isEmpty();
            $precedentePieno = Automezzo::getAll($annoPrec)->isNotEmpty();
        } else {
            $idAssoc = $user->IdAssociazione;
            $correnteVuoto = Automezzo::getByAssociazione($idAssoc, $anno)->isEmpty();
            $precedentePieno = Automezzo::getByAssociazione($idAssoc, $annoPrec)->isNotEmpty();
        }

        return response()->json([
            'mostraMessaggio' => $correnteVuoto && $precedentePieno,
            'annoCorrente' => $anno,
            'annoPrecedente' => $annoPrec,
        ]);
    }

    public function duplicaAnnoPrecedente(Request $request): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        DB::beginTransaction();
        $associazioni = collect();
        try {
            if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
                $associazioni = DB::table('associazioni')
                    ->select('IdAssociazione', 'Associazione')
                    ->whereNull('deleted_at')
                    ->where('IdAssociazione', '!=', 1)
                    ->orderBy('Associazione')
                    ->get();
                $selectedAssoc = session('associazione_selezionata') ?? $request->get('idAssociazione')
                    ?? ($associazioni->first()->IdAssociazione ?? null);
            } else {
                $selectedAssoc = $user->IdAssociazione;
            }

            $automezzi = Automezzo::getByAssociazione($selectedAssoc, $annoPrec);


            if ($automezzi->isEmpty()) {
                return response()->json(['message' => 'Nessun automezzo da duplicare'], 404);
            }

            foreach ($automezzi as $auto) {

                $newId = DB::table('automezzi')->insertGetId([
                    'idAssociazione' => $selectedAssoc,
                    'idAnno' => $anno,
                    'Targa' => $auto->Targa,
                    'CodiceIdentificativo' => $auto->CodiceIdentificativo,
                    'AnnoPrimaImmatricolazione' => $auto->AnnoPrimaImmatricolazione,
                    'AnnoAcquisto' => $auto->AnnoAcquisto,
                    'Modello' => $auto->Modello,
                    'idTipoVeicolo' => $auto->idTipoVeicolo ?? 1,
                    'KmTotali' => $auto->KmTotali,
                    'idTipoCarburante' => $auto->idTipoCarburante ?? 1,
                    'DataUltimaAutorizzazioneSanitaria' => $auto->DataUltimaAutorizzazioneSanitaria,
                    'DataUltimoCollaudo' => $auto->DataUltimoCollaudo,
                    'note' => $auto->note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $kmPrec = DB::table('automezzi_km_riferimento')
                    ->where('idAutomezzo', $auto->idAutomezzo)
                    ->where('idAnno', $annoPrec)
                    ->value('KmRiferimento');

                if (!is_null($kmPrec)) {
                    AutomezzoKmRiferimento::create([
                        'idAutomezzo' => $newId,
                        'idAnno' => $anno,
                        'KmRiferimento' => $kmPrec,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Automezzi duplicati con successo.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Errore durante duplicazione automezzi', [
                'exception' => $e
            ]);

            return response()->json(['message' => 'Errore durante duplicazione'], 500);
        }
    }


    /**
     * GET /automezzi/datatable
     * Restituisce JSON per DataTables, filtrato lato server
     */
    public function datatable(Request $request): JsonResponse {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        // Determina l'associazione su cui filtrare
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $assocId = $request->get('idAssociazione');
        } else {
            $assocId = $user->IdAssociazione;
        }

        // *** Qui passi SOLO $anno e $assocId ***
        $data = Automezzo::getForDataTable($anno, $assocId);

        return response()->json(['data' => $data]);
    }

    public function getByAssociazione($idAssociazione): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno)
            ->map(function ($a) {
                return [
                    'id' => $a->idAutomezzo,
                    'text' => $a->Targa . ' - ' . $a->CodiceIdentificativo
                ];
            });

        return response()->json($automezzi);
    }

    public function setAssociazioneSelezionata(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,IdAssociazione',
        ]);

        session(['associazione_selezionata' => $request->idAssociazione]);

        return redirect()->route('automezzi.index'); // oppure redirect()->route('automezzi.index') se vuoi forzare reload
    }
}
