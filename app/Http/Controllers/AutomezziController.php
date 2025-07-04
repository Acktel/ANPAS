<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Automezzo;
use App\Models\AutomezzoKmRiferimento;
use App\Models\VehicleType;
use App\Models\FuelType;
use Illuminate\Http\JsonResponse;

class AutomezziController extends Controller {
    public function index() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $automezzi = Automezzo::getAll($anno);
        } else {
            $idAssoc = $user->IdAssociazione;
            abort_if(!$idAssoc, 403, "Associazione non trovata per l'utente.");
            $automezzi = Automezzo::getByAssociazione($idAssoc, $anno);
        }

        return view('automezzi.index', compact('automezzi', 'anno'));
    }

    public function create() {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')->select('idAnno', 'anno')->orderBy('anno', 'desc')->get();

        $vehicleTypes = DB::table('vehicle_types')->select('id', 'nome')->orderBy('nome')->get();
        $fuelTypes = DB::table('fuel_types')->select('id', 'nome')->orderBy('nome')->get();

        if (!in_array(Auth::user()->role_id, [1, 2, 3])) {
            $associazioni = $associazioni->where('idAssociazione', Auth::user()->idAssociazione);
        }

        return view('automezzi.create', compact('associazioni', 'anni', 'vehicleTypes', 'fuelTypes'));
    }

    public function store(Request $request) {
        $rules = [
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'Automezzo' => 'required|string|max:255',
            'Targa' => 'required|string|max:50',
            'CodiceIdentificativo' => 'required|string|max:100',
            'AnnoPrimaImmatricolazione' => 'required|integer|min:1900|max:' . date('Y'),
            'AnnoAcquisto' => 'nullable|integer|min:1900|max:' . date('Y'),
            'Modello' => 'required|string|max:255',
            'idTipoVeicolo' => 'required|exists:vehicle_types,id',
            'KmRiferimento' => 'required|numeric|min:0',
            'KmTotali' => 'required|numeric|min:0',
            'idTipoCarburante' => 'required|exists:fuel_types,id',
            'DataUltimaAutorizzazioneSanitaria' => 'nullable|date',
            'DataUltimoCollaudo' => 'nullable|date',
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {
            $newId = Automezzo::createAutomezzo($validated);

            AutomezzoKmRiferimento::create([
                'idAutomezzo' => $newId,
                'idAnno' => $validated['idAnno'],
                'KmRiferimento' => $validated['KmRiferimento'],
            ]);

            DB::commit();
            return redirect()->route('automezzi.index')->with('success', 'Automezzo creato correttamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Errore interno durante la creazione.']);
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
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')->select('idAnno', 'anno')->orderBy('anno', 'desc')->get();
        $vehicleTypes = DB::table('vehicle_types')->select('id', 'nome')->orderBy('nome')->get();
        $fuelTypes = DB::table('fuel_types')->select('id', 'nome')->orderBy('nome')->get();

        return view('automezzi.edit', compact('automezzo', 'associazioni', 'anni', 'vehicleTypes', 'fuelTypes'));
    }

    public function update(Request $request, int $idAutomezzo) {
        $rules = [
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'Automezzo' => 'required|string|max:255',
            'Targa' => 'required|string|max:50',
            'CodiceIdentificativo' => 'required|string|max:100',
            'AnnoPrimaImmatricolazione' => 'required|integer|min:1900|max:' . date('Y'),
            'AnnoAcquisto' => 'nullable|integer|min:1900|max:' . date('Y'),
            'Modello' => 'required|string|max:255',
            'idTipoVeicolo' => 'required|exists:vehicle_types,id',
            'KmRiferimento' => 'required|numeric|min:0',
            'KmTotali' => 'required|numeric|min:0',
            'idTipoCarburante' => 'required|exists:fuel_types,id',
            'DataUltimaAutorizzazioneSanitaria' => 'nullable|date',
            'DataUltimoCollaudo' => 'nullable|date',
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {
            Automezzo::updateAutomezzo($idAutomezzo, $validated);

            AutomezzoKmRiferimento::updateOrCreate(
                ['idAutomezzo' => $idAutomezzo, 'idAnno' => $validated['idAnno']],
                ['KmRiferimento' => $validated['KmRiferimento']]
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
            Automezzo::deleteAutomezzo($idAutomezzo);
            DB::commit();
            return redirect()->route('automezzi.index')->with('success', 'Automezzo eliminato.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Errore durante l’eliminazione.']);
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

        try {
            if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
                $automezzi = Automezzo::getAll($annoPrec);
            } else {
                $idAssoc = $user->IdAssociazione;
                $automezzi = Automezzo::getByAssociazione($idAssoc, $annoPrec);
            }

            if ($automezzi->isEmpty()) {
                return response()->json(['message' => 'Nessun automezzo da duplicare'], 404);
            }

            foreach ($automezzi as $auto) {
                $newId = DB::table('automezzi')->insertGetId([
                    'idAssociazione' => $auto->idAssociazione,
                    'idAnno' => $anno,
                    'Automezzo' => $auto->Automezzo,
                    'Targa' => $auto->Targa,
                    'CodiceIdentificativo' => $auto->CodiceIdentificativo,
                    'AnnoPrimaImmatricolazione' => $auto->AnnoPrimaImmatricolazione,
                    'AnnoAcquisto' => $auto->AnnoAcquisto,
                    'Modello' => $auto->Modello,
                    'idTipoVeicolo' => $auto->idTipoVeicolo ?? null,
                    'KmTotali' => $auto->KmTotali,
                    'idTipoCarburante' => $auto->idTipoCarburante ?? null,
                    'DataUltimaAutorizzazioneSanitaria' => $auto->DataUltimaAutorizzazioneSanitaria,
                    'DataUltimoCollaudo' => $auto->DataUltimoCollaudo,
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
            return response()->json(['message' => 'Errore durante duplicazione'], 500);
        }
    }

    public function datatable() {
        $anno = session('anno_riferimento', now()->year);

        $user = Auth::user();
        $data = Automezzo::getForDataTable($anno, $user);

        return response()->json(['data' => $data]);
    }
}