<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\CostoMaterialeSanitario;
use App\Models\Automezzo;
use Illuminate\Http\JsonResponse;


class CostoMaterialeSanitarioController extends Controller {
    /**
     * Mostra la vista con la tabella di imputazione costi del materiale sanitario.
     */
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getFiltratiByUtente($anno);

        // Estraggo l'ID dell'associazione (assumo tutti gli automezzi siano della stessa associazione)
        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        $numeroServizi  = RipartizioneMaterialeSanitario::getTotaleServizi($automezzi, $anno);
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $anno);
        $dati           = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);

        return view('imputazioni.materiale_sanitario.index', [
            'anno'           => $anno,
            'numeroServizi'  => $numeroServizi,
            'totaleBilancio' => $totaleBilancio,
            'righe'          => $dati['righe'],
            'totale_inclusi' => $dati['totale_inclusi'] ?? 0,
        ]);
    }

    /**
     * Aggiorna il valore totale a bilancio del materiale sanitario.
     */
    public function updateTotale(Request $request) {
        $request->validate([
            'TotaleBilancio' => 'required|numeric|min:0'
        ]);

        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getFiltratiByUtente($anno);

        // Estraggo l'ID dell'associazione (assumo tutti gli automezzi siano della stessa associazione)
        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        CostoMaterialeSanitario::upsertTotale($idAssociazione, $anno, $request->TotaleBilancio);

        return back()->with('success', 'Totale a bilancio aggiornato.');
    }

    public function getData(): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getFiltratiByUtente($anno);
        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $anno);

        $righe = [];

        foreach ($dati['righe'] as $riga) {
            // Riga totale
            if (isset($riga['is_totale']) && $riga['is_totale']) {
                $righe[] = [
                    'Targa'       => 'TOTALE',
                    'n_servizi'   => $riga['totale'],
                    'percentuale' => 100,
                    'importo'     => $totaleBilancio,
                    'is_totale'   => -1
                ];
                continue;
            }

            $incluso = $riga['incluso_riparto'];

            if ($incluso) {
                $percentuale = ($dati['totale_inclusi'] > 0)
                    ? round(($riga['totale'] / $dati['totale_inclusi']) * 100, 2)
                    : 0;

                $importo = ($dati['totale_inclusi'] > 0)
                    ? round(($riga['totale'] / $dati['totale_inclusi']) * $totaleBilancio, 2)
                    : 0;
            } else {
                $percentuale = 0;
                $importo = 0;
            }

            $righe[] = [
                'Targa'       => $riga['Targa'],
                'n_servizi'   => $riga['totale'],
                'percentuale' => $percentuale,
                'importo'     => $importo,
                'is_totale'   => 0
            ];
        }

        return response()->json(['data' => $righe]);
    }


    public function editTotale(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getFiltratiByUtente($anno);

        // Prendi l'associazione dell'utente o dei mezzi
        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        $totale = CostoMaterialeSanitario::getTotale($idAssociazione, $anno);

        return view('imputazioni.materiale_sanitario.edit_totale', [
            'totale' => $totale,
            'anno'   => $anno
        ]);
    }
}
