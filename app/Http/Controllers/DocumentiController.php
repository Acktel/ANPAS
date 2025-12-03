<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Jobs\GeneraRiepilogoCostiPdfJob;
use App\Jobs\GeneraRegistroAutomezziPdfJob;
use App\Jobs\GeneraDistintaKmPercorsiPdfJob;
use App\Jobs\GeneraServiziSvoltiPdfJob;
use App\Jobs\GeneraRapportiRicaviPdfJob;
use App\Jobs\GeneraRipartizionePersonalePdfJob;
use App\Jobs\GeneraRipVolontariScnPdfJob;
use App\Jobs\GeneraServiziSvoltiOssigenoPdfJob;
use App\Jobs\GeneraCostiPersonalePdfJob;
use App\Jobs\GeneraCostiAutomezziSanitariPdfJob;
use App\Jobs\GeneraCostiRadioPdfJob;
use App\Jobs\GeneraImputazioniMaterialeEOssigenoPdfJob;
use App\Jobs\GeneraRiepilogoRipCostiAutomezziPdfJob;
use App\Jobs\DistintaImputazioneCostiPdfJob;
use App\Jobs\GeneraDocumentoUnicoPdfJob;
use App\Jobs\RiepiloghiDatiECostiPdfJob;
use App\Jobs\BuildAllPdfsAndBundleJob;

use App\Models\Associazione;
use App\Models\DocumentoGenerato;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\RiepilogoCosti;

class DocumentiController extends Controller {

    // === helper selezione associazione (come altrove) ===
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
            $associazioni = Associazione::getById($selectedAssoc);
        }

        if ($request->has('idAssociazione')) {
            session(['associazione_selezionata' => $request->idAssociazione]);
        }
        $selectedAssoc = session('associazione_selezionata') ?? $selectedAssoc;

        return [$associazioni, $selectedAssoc];
    }

    public function registroForm(Request $request) {
        [$associazioni, $selectedAssoc] = $this->selectedAssocForUser($request);
        $anni = (int) session('anno_riferimento', now()->year);

        // elenco tipi supportati (tutti i PDF “finali”, esclusi i child)
        $tipi = [
            'riepilogo_costi_pdf',
            'registro_automezzi_pdf',
            'km_percentuali_pdf',
            'servizi_svolti_pdf',
            'rapporti_ricavi_pdf',
            'costi_radio_pdf',
            'imputazioni_materiale_ossigeno_pdf',
            'costi_automezzi_sanitari_pdf',
            'ripartizione_costi_automezzi_riepilogo_pdf',
            'documento_unico_pdf',
            'ripartizione_personale_pdf',
            'rip_volontari_scn_pdf',
            'costi_personale_pdf',
            'servizi_svolti_ossigeno_pdf',
            'distinta_imputazione_costi_pdf',
            'riepiloghi_dati_e_costi_pdf',
            'bundle_all_pdf',
            'bundle_all',
        ];

        $documenti = DocumentoGenerato::query()
            ->where('idUtente', Auth::id())
            ->whereIn('tipo_documento', $tipi)
            ->when($selectedAssoc, fn($q) => $q->where('idAssociazione', $selectedAssoc))
            ->where('tipo_documento', '!=', 'child') // ulteriore safety per non elencare i figli del bundle
            ->orderByDesc('generato_il')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('documenti.registro', compact('associazioni', 'anni', 'documenti', 'selectedAssoc'));
    }


    // === avvio job RIEPILOGO COSTI (JSON) ===
    public function riepilogoCostiPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => Auth::id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'riepilogo_costi_pdf',
        ]);

        GeneraRiepilogoCostiPdfJob::dispatch($doc->id, (int)$data['idAssociazione'], (int)$data['idAnno'], Auth::id());

        return response()->json(['id' => $doc->id]);
    }

    // === avvio job REGISTRO AUTOMEZZI (JSON) ===
    public function registroAutomezziPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => Auth::id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'registro_automezzi_pdf',
        ]);

        GeneraRegistroAutomezziPdfJob::dispatch($doc->id, (int)$data['idAssociazione'], (int)$data['idAnno'], Auth::id());

        return response()->json(['id' => $doc->id]);
    }

    public function distintaKmPercorsiPdf(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$request->idAssociazione,
            'idAnno'         => (int)$request->idAnno,
            'tipo_documento' => 'distinta_km_percorsi_pdf',
            'stato'          => 'queued',
        ]);

        GeneraDistintaKmPercorsiPdfJob::dispatch(
            $doc->id,
            (int)$request->idAssociazione,
            (int)$request->idAnno,
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }


    public function kmPercentualiPdf(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => Auth::id(),
            'idAssociazione' => (int) $request->idAssociazione,
            'idAnno'         => (int) $request->idAnno,
            'tipo_documento' => 'km_percentuali_pdf',
            'stato'          => 'queued',
        ]);

        GeneraDistintaKmPercorsiPdfJob::dispatch(
            $doc->id,
            (int)$request->idAssociazione,
            (int)$request->idAnno,
            Auth::id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function serviziSvoltiPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'servizi_svolti_pdf',
        ]);

        GeneraServiziSvoltiPdfJob::dispatch(
            $doc->id,
            (int)$data['idAssociazione'],
            (int)$data['idAnno'],
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function rapportiRicaviPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'rapporti_ricavi_pdf',
            'stato'          => 'queued',
        ]);

        GeneraRapportiRicaviPdfJob::dispatch(
            $doc->id,
            (int)$data['idAssociazione'],
            (int)$data['idAnno'],
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function ripartizionePersonalePdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'ripartizione_personale_pdf',
            'stato'          => 'queued',
        ]);

        GeneraRipartizionePersonalePdfJob::dispatch(
            $doc->id,
            (int)$data['idAssociazione'],
            (int)$data['idAnno'],
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function ripVolontariScnPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'rip_volontari_scn_pdf',
            'stato'          => 'queued',
        ]);

        GeneraRipVolontariScnPdfJob::dispatch(
            $doc->id,
            (int)$data['idAssociazione'],
            (int)$data['idAnno'],
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function serviziSvoltiOssigenoPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'servizi_svolti_ossigeno_pdf',
            'stato'          => 'queued',
        ]);

        GeneraServiziSvoltiOssigenoPdfJob::dispatch(
            $doc->id,
            (int)$data['idAssociazione'],
            (int)$data['idAnno'],
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function costiAutomezziSanitariPdf(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno'         => 'required|integer',
        ]);

        $userId = auth()->id();

        $doc = DocumentoGenerato::create([
            'idAssociazione' => (int)$request->idAssociazione,
            'idAnno'         => (int)$request->idAnno,
            'idUtente'       => $userId,
            'tipo_documento' => 'costi_automezzi_sanitari_pdf', // ✅ corretto
            'stato'          => 'queued',
        ]);

        dispatch(new GeneraCostiAutomezziSanitariPdfJob(
            $doc->id,
            (int) $request->idAssociazione,
            (int) $request->idAnno,
            (int) $userId
        ))->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function costiRadioPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'costi_radio_pdf',
            'stato'          => 'queued',
        ]);

        GeneraCostiRadioPdfJob::dispatch(
            $doc->id,
            (int)$data['idAssociazione'],
            (int)$data['idAnno'],
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }


    public function imputazioniMaterialeOssigenoPdf(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno'         => 'required|integer',
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$request->idAssociazione,
            'idAnno'         => (int)$request->idAnno,
            'tipo_documento' => 'imputazioni_materiale_ossigeno_pdf',
            'stato'          => 'queued',
        ]);

        GeneraImputazioniMaterialeEOssigenoPdfJob::dispatch(
            $doc->id,
            (int)$request->idAssociazione,
            (int)$request->idAnno,
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function ripartizioneCostiAutomezziRiepilogoPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'rip_costi_automezzi_unico_pdf', // etichetta libera
            'stato'          => 'queued',
        ]);

        GeneraRiepilogoRipCostiAutomezziPdfJob::dispatch(
            documentoId: $doc->id,
            idAssociazione: (int)$data['idAssociazione'],
            anno: (int)$data['idAnno'],
            utenteId: auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function documentoUnicoPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'documento_unico_pdf',
            'stato'          => 'queued',
        ]);

        GeneraDocumentoUnicoPdfJob::dispatch(
            $doc->id,
            (int)$data['idAssociazione'],
            (int)$data['idAnno'],
            auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function distintaImputazioneCostiPdf(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno'         => 'required|integer',
        ]);

        $user = Auth::user();

        $idAssociazione = (int) $request->idAssociazione;
        $idAnno         = (int) $request->idAnno;

        // 🔁 inserimento nella tabella corretta
        $docId = DB::table('documenti_generati')->insertGetId([
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $idAnno,
            'tipo_documento' => 'distinta_imputazione_costi_pdf',
            'percorso_file'  => null,
            'stato'          => 'queued',
            'created_at'     => now(),
            'updated_at'     => now(),
            'generato_il'    => null,
            'idUtente'       => $user?->id ?? null,
        ]);

        DistintaImputazioneCostiPdfJob::dispatch($docId)->onQueue('pdf');

        return response()->json(['id' => $docId]);
    }

    public function riepiloghiDatiECostiPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => auth()->id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'riepiloghi_dati_costi_pdf',
            'stato'          => 'queued',
        ]);

        RiepiloghiDatiECostiPdfJob::dispatch(
            documentoId: $doc->id,
            idAssociazione: (int)$data['idAssociazione'],
            anno: (int)$data['idAnno'],
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }


    // === polling stato (JSON) ===
    public function status($id) {
        $doc = DocumentoGenerato::find($id);
        if (!$doc) return response()->json(['status' => 'error'], 404);

        $ready = ($doc->stato === 'ready') && $doc->generato_il && $doc->percorso_file; // niente Storage::exists()
        return response()->json([
            'id'           => $doc->id,
            'tipo'         => $doc->tipo_documento,
            'status'       => $ready ? 'ready' : ($doc->stato ?? 'queued'),
            'generato_il'  => $doc->generato_il,
            'download_url' => $ready ? route('documenti.download', $doc->id) : null,
            'filename'     => $ready ? basename($doc->percorso_file) : null,
        ]);
    }

    public function download($id) {
        $doc = DocumentoGenerato::findOrFail($id);
        abort_unless($doc->generato_il && $doc->percorso_file && Storage::disk('public')->exists($doc->percorso_file), 404);
        return Storage::disk('public')->download($doc->percorso_file);
    }

    public function costiPersonalePdf(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno'         => 'required|integer',
        ]);

        $utente = Auth::id();

        $doc = DocumentoGenerato::create([
            'tipo_documento' => 'costi_personale_pdf',
            'idAssociazione' => (int)$request->idAssociazione,
            'idAnno'         => (int)$request->idAnno,
            'generato_il'    => null,
            'nome_file'      => null,
            'percorso_file'  => null,
            'idUtente'       => $utente, // ✅
            'stato'          => 'queued',
        ]);

        GeneraCostiPersonalePdfJob::dispatch(
            documentoId: $doc->id,
            idAssociazione: (int)$request->idAssociazione,
            anno: (int)$request->idAnno,
            utenteId: $utente,
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }

    public function bundleAllPdf(Request $request) {
        $data = $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno'         => 'required|integer',
        ]);

        $bundleId = DB::table('documenti_generati')->insertGetId([
            'idAssociazione' => (int) $data['idAssociazione'],
            'idAnno'         => (int) $data['idAnno'],
            'tipo_documento' => 'bundle_all', // etichetta del documento finale
            'stato'          => 'queued',
            'created_at'     => now(),
            'updated_at'     => now(),
            'idUtente'       => auth()->id(),
        ]);

        BuildAllPdfsAndBundleJob::dispatch(
            bundleId: $bundleId,
            idAssociazione: (int) $data['idAssociazione'],
            anno: (int) $data['idAnno'],
            utenteId: (int) auth()->id()
        )->onQueue('pdf');

        return response()->json(['id' => $bundleId]);
    }
}
