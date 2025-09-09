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
use App\Jobs\GeneraDocumentoUnicoPdfJob;
use App\Jobs\GeneraRipartizionePersonalePdfJob;
use App\Jobs\GeneraRipVolontariScnPdfJob;
use App\Jobs\GeneraServiziSvoltiOssigenoPdfJob;

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
        }

        if ($request->has('idAssociazione')) {
            session(['associazione_selezionata' => $request->idAssociazione]);
        }
        $selectedAssoc = session('associazione_selezionata') ?? $selectedAssoc;

        return [$associazioni, $selectedAssoc];
    }

    public function registroForm(Request $request) {
        [$associazioni, $selectedAssoc] = $this->selectedAssocForUser($request);
        $anni = session('anno_riferimento', now()->year);

        // elenco ultimi documenti (entrambi i tipi PDF)
        $documenti = DocumentoGenerato::where('idUtente', Auth::id())
            ->whereIn('tipo_documento', ['riepilogo_costi_pdf', 'registro_automezzi_pdf', 'km_percentuali_pdf', 'servizi_svolti_pdf', 'rapporti_ricavi_pdf'])
            ->orderByDesc('generato_il')
            ->orderByDesc('id')
            ->limit(20)
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
            'stato'          => 'queued', // se la colonna esiste, altrimenti rimuovi
        ]);

        GeneraDistintaKmPercorsiPdfJob::dispatch(
            $doc->id,
            (int)$request->idAssociazione,
            (int)$request->idAnno,
            auth()->id()
        );

        return back()->with('success', 'Richiesta in coda: il PDF della “Distinta km percorsi” sarà disponibile appena pronto.');
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

    // === polling stato (JSON) ===
    public function status($id) {
        $doc = DocumentoGenerato::findOrFail($id);
        $ready = $doc->generato_il && $doc->percorso_file && Storage::disk('public')->exists($doc->percorso_file);

        return response()->json([
            'id'           => $doc->id,
            'tipo'         => $doc->tipo_documento,
            'status'       => $ready ? 'ready' : 'queued',
            'generato_il'  => $doc->generato_il,
            'download_url' => $ready ? route('documenti.download', $doc->id) : null,
            'filename'     => $ready ? $doc->nome_file : null,
        ]);
    }

    public function download($id) {
        $documento = DocumentoGenerato::findOrFail($id);
        abort_unless($documento->generato_il && Storage::disk('public')->exists($documento->percorso_file), 404);
        return response()->download(storage_path("app/public/{$documento->percorso_file}"), basename($documento->percorso_file));
    }
}
