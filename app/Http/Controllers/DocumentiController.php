<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Jobs\GeneraDocumentoJob;
use App\Models\DocumentoGenerato;

class DocumentiController extends Controller {

    protected function getAssociazioni() {
        return DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();
    }

    protected function getAnni() {
        $anni = [];
        for ($y = 2000; $y <= date('Y') + 5; $y++) {
            $anni[] = (object)['idAnno' => $y, 'anno' => $y];
        }
        return collect($anni);
    }

    public function registroForm() {
        $associazioni = $this->getAssociazioni();
        $anni = $this->getAnni();

        $documenti = DocumentoGenerato::where('idUtente', Auth::id())
            ->where('tipo_documento', 'registro')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('documenti.registro', compact('associazioni', 'anni', 'documenti'));
    }

    public function registroGenerate(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|exists:associazioni,idAssociazione',
            'idAnno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);
         
        $idAssociazione = $request->input('idAssociazione');
        $idAnno = $request->input('idAnno');
        $utenteId = Auth::id();

        GeneraDocumentoJob::dispatch($idAssociazione, $idAnno, $utenteId);

        return back()->with('success', 'Il documento è stato messo in coda. Potrai scaricarlo non appena sarà pronto.');
    }

    public function download($id)
    {
        $documento = DocumentoGenerato::where('id', $id)->first();

        if (!$documento || !$documento->generato_il || !Storage::disk('public')->exists($documento->percorso_file)) {
            return redirect()->back()->with('error', 'Il file non è disponibile per il download.');
        }

        $filePath = storage_path("app/public/{$documento->percorso_file}");
        $filename = basename($documento->percorso_file);

        return response()->download($filePath, $filename);
    }
}
