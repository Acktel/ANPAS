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

    public function index() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year); // ✅ Anno dinamico

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            // 🔁 Logica SuperAdmin: mostra tutte le convenzioni dell’anno corrente
            $convenzioni = DB::table('convenzioni as c')
                ->join('associazioni as a', 'a.idAssociazione', '=', 'c.idAssociazione')
                ->where('c.idAnno', $anno)
                ->select('c.*', 'a.Associazione')
                ->orderBy('c.ordinamento')
                ->get();
        } else {
            
            $convenzioni = Convenzione::getWithAssociazione($user->IdAssociazione, $anno);
        }

        return view('convenzioni.index', compact('convenzioni', 'anno'));
    }

    public function create() {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at') 
            ->whereNot("idAssociazione", 1) 
            ->orderBy('Associazione')->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')->get();

        return view('convenzioni.create', compact('associazioni', 'anni'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'idAssociazione'         => 'required|exists:associazioni,idAssociazione',
            'idAnno'                 => 'required|exists:anni,idAnno',
            'Convenzione'            => 'required|string|max:100',
            'lettera_identificativa' => 'required|string|max:5',
        ]);

        Convenzione::createConvenzione($data);

        return redirect()->route('convenzioni.index')
            ->with('success', 'Convenzione creata.');
    }

    public function edit(int $id) {
        $conv = Convenzione::getById($id);
        abort_if(! $conv, 404);

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at') 
            ->whereNot("idAssociazione", 1) 
            ->orderBy('Associazione')->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')->get();

        return view('convenzioni.edit', compact('conv', 'associazioni', 'anni'));
    }

    public function update(Request $request, int $id) {
        $data = $request->validate([
            'idAssociazione'         => 'required|exists:associazioni,idAssociazione',
            'idAnno'                 => 'required|exists:anni,idAnno',
            'Convenzione'            => 'required|string|max:100',
            'lettera_identificativa' => 'required|string|max:5',
        ]);

        Convenzione::updateConvenzione($id, $data);

        return redirect()->route('convenzioni.index')
            ->with('success', 'Convenzione aggiornata.');
    }

    public function destroy(int $id) {
        abort_if(!Convenzione::getById($id), 404);

        Convenzione::deleteConvenzione($id);

        return redirect()->route('convenzioni.index')
            ->with('success', 'Convenzione eliminata.');
    }

    public function checkDuplicazioneDisponibile(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $annoPrec = $anno - 1;
        $user = Auth::user();

        // 🔐 Se SuperAdmin, controlla su TUTTE le convenzioni
        if ($user->hasRole('SuperAdmin') || $user->hasRole('Admin') || $user->hasRole('Supervisor')) {
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
            // 🔐 Se SuperAdmin: duplica TUTTE le convenzioni per TUTTE le associazioni
            if ($user->hasRole('SuperAdmin') || $user->hasRole('Admin') || $user->hasRole('Supervisor')) {
                $convenzioni = DB::table('convenzioni')
                    ->where('idAnno', $annoPrec)
                    ->get();

                if ($convenzioni->isEmpty()) {
                    return response()->json(['message' => 'Nessuna convenzione da duplicare'], 404);
                }

                foreach ($convenzioni as $c) {
                    Convenzione::createConvenzione([
                        'idAssociazione' => $c->idAssociazione,
                        'idAnno' => $anno,
                        'Convenzione' => $c->Convenzione,
                        'lettera_identificativa' => $c->lettera_identificativa,
                    ]);
                }
            } else {
                $idAssoc = $user->IdAssociazione;

                $convenzioni = Convenzione::getByAssociazioneAnno($idAssoc, $annoPrec);

                if ($convenzioni->isEmpty()) {
                    return response()->json(['message' => 'Nessuna convenzione da duplicare'], 404);
                }

                foreach ($convenzioni as $c) {
                    Convenzione::createConvenzione([
                        'idAssociazione' => $idAssoc,
                        'idAnno' => $anno,
                        'Convenzione' => $c->Convenzione,
                        'lettera_identificativa' => $c->lettera_identificativa,
                    ]);
                }
            }

            return response()->json(['message' => 'Convenzioni duplicate.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Errore durante la duplicazione.', 'error' => $e->getMessage()], 500);
        }
    }

    public function riordina(Request $request): JsonResponse {
        $ids = $request->input('order'); // array ordinato di idConvenzione

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
}
