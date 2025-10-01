<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\DocumentoGenerato;
use App\Jobs\Excel\GeneraRegistriPrimaPaginaXlsJob;
use App\Jobs\Excel\GeneraSchedeRipartoCostiXlsJob;

class DocumentiExcelController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    // === helper selezione associazione (come nel tuo DocumentiController) ===
    private function selectedAssocForUser(Request $request): array {
        $user = Auth::user();
        $associazioni = collect();
        $selectedAssoc = null;

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('IdAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('IdAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            $selectedAssoc = $request->get('idAssociazione')
                ?? session('associazione_selezionata')
                ?? ($associazioni->first()->IdAssociazione ?? null);
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }

        if ($request->has('idAssociazione')) {
            session(['associazione_selezionata' => $request->idAssociazione]);
        }
        $selectedAssoc = session('associazione_selezionata') ?? $selectedAssoc;

        return [$associazioni, $selectedAssoc];
    }

    /**
     * GET /documenti/registro-xls
     * Pagina “Esportazioni EXCEL” (stessa UX della pagina PDF).
     */
    public function registroXls(Request $request) {
        [$associazioni, $selectedAssoc] = $this->selectedAssocForUser($request);
        $anni = (int) session('anno_riferimento', now()->year);

        // elenca SOLO i documenti Excel dell’utente corrente (come fai coi PDF)
        $tipiExcel = [
            'excel_registri_p1',
            'excel_schede_riparto_costi',
        ];

        $documenti = DocumentoGenerato::query()
            ->where('idUtente', Auth::id())
            ->whereIn('tipo_documento', $tipiExcel)
            ->when($selectedAssoc, fn($q) => $q->where('idAssociazione', $selectedAssoc))
            ->where('tipo_documento', '!=', 'child')
            ->orderByDesc('generato_il')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('documenti.registro_xls', compact('associazioni', 'anni', 'documenti', 'selectedAssoc'));
    }

    /**
     * POST /documenti/registri/pagina1/xls
     * Avvio job: REGISTRI – Prima pagina (XLS).
     * Ritorna { id } per il polling.
     */
    public function startRegistriP1Xls(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => Auth::id(),
            'idAssociazione' => (int) $data['idAssociazione'],
            'idAnno'         => (int) $data['idAnno'],
            'tipo_documento' => 'excel_registri_p1',
            'stato'          => 'queued',
        ]);

        // dispatch su connection 'database' e coda 'excel' (teniamo pdf/excel separati)
        GeneraRegistriPrimaPaginaXlsJob::dispatch(
            documentoId: $doc->id,
            idAssociazione: (int) $data['idAssociazione'],
            anno: (int) $data['idAnno'],
            utenteId: (int) Auth::id()
        )->onConnection('database')->onQueue('excel');

        return response()->json(['id' => $doc->id]);
    }

    /**
     * POST /documenti/schede-riparto-costi/xls
     * Avvio job: SCHEDE DI RIPARTO DEI COSTI (XLS).
     * Ritorna { id } per il polling.
     */
    public function startSchedeRipartoCostiXls(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int) $data['idAssociazione'],
            'idAnno'         => (int) $data['idAnno'],
            'tipo_documento' => 'excel_schede_riparto_costi',
            'stato'          => 'queued',
        ]);

        GeneraSchedeRipartoCostiXlsJob::dispatch(
            documentoId: $doc->id,
            idAssociazione: (int) $data['idAssociazione'],
            anno: (int) $data['idAnno'],
            utenteId: (int) auth()->id()
        )->onConnection('database')->onQueue('excel');

        return response()->json(['id' => $doc->id]);
    }
}
