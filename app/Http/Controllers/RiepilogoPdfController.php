<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DocumentoGenerato;
use App\Jobs\RiepiloghiDatiECostiPdfJob;

class RiepilogoPdfController extends Controller
{
    public function generate(Request $request)
    {
        $data = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,idAssociazione',
            'idAnno'         => 'required|integer|min:2000|max:' . (date('Y') + 5),
            // opzionale se vuoi cambiare tipologia per la seconda tabella
            'tipologia_costi'=> 'nullable|integer|min:1'
        ]);

        $doc = DocumentoGenerato::create([
            'idUtente'       => Auth::id(),
            'idAssociazione' => (int)$data['idAssociazione'],
            'idAnno'         => (int)$data['idAnno'],
            'tipo_documento' => 'riepiloghi_dati_e_costi_pdf',
            'stato'          => 'queued',
        ]);

        RiepiloghiDatiECostiPdfJob::dispatch(
            documentoId: $doc->id,
            idAssociazione: (int)$data['idAssociazione'],
            anno: (int)$data['idAnno'],
            tipologiaCosti: (int)($data['tipologia_costi'] ?? 2),
        )->onQueue('pdf');

        return response()->json(['id' => $doc->id]);
    }
}
