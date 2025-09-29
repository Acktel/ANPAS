<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\CostoMaterialeSanitario;
use App\Models\Automezzo;
use App\Models\Convenzione;
use App\Models\Dipendente;
use Illuminate\Http\JsonResponse;


class CostoMaterialeSanitarioController extends Controller {
    /**
     * Mostra la vista con la tabella di imputazione costi del materiale sanitario.
     */
public function index(Request $request) {
    $anno = session('anno_riferimento', now()->year);
    $user = Auth::user();
    $isImpersonating = session()->has('impersonate');

    $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

    // Se cambia la select aggiorna la sessione
    if ($request->has('idAssociazione')) {
        session(['associazione_selezionata' => $request->get('idAssociazione')]);
    }

    $selectedAssoc = session('associazione_selezionata') ?? $user->IdAssociazione;

    $automezzi = Automezzo::getByAssociazione($selectedAssoc, $anno);

    $numeroServizi  = RipartizioneMaterialeSanitario::getTotaleServizi($automezzi, $anno);
    $totaleBilancio = CostoMaterialeSanitario::getTotale($selectedAssoc, $anno);
    $dati           = RipartizioneMaterialeSanitario::getRipartizione($selectedAssoc, $anno);

    return view('imputazioni.materiale_sanitario.index', [
        'anno'           => $anno,
        'numeroServizi'  => $numeroServizi,
        'totaleBilancio' => $totaleBilancio,
        'righe'          => $dati['righe'],
        'associazioni'   => $associazioni,
        'selectedAssoc'  => $selectedAssoc,
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

        return redirect()
            ->route('imputazioni.materiale_sanitario.index')
            ->with('success', 'Aggiornamento completato.');
    }

    public function getData(Request $request): JsonResponse
{
    $anno = session('anno_riferimento', now()->year);
    $user = Auth::user();

    $idAssociazione = $request->query('idAssociazione')
        ?? session('associazione_selezionata')
        ?? $user->IdAssociazione;

    $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);

    $dati           = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);
    $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $anno);
    $checkConv      = Convenzione::checkMaterialeSanitario($idAssociazione, $anno) === true;

    $righe = [];

    // 1) Calcolo il totale_inclusi "aggiustato"
    $totaleInclusiAdj = 0;
    foreach ($dati['righe'] as $r) {
        if (!empty($r['is_totale'])) continue;
        if (!empty($r['incluso_riparto'])) {
            $n = (int)($r['totale'] ?? 0);
            if ($checkConv) {
                // sempre -1, anche se era già 0
                $n = $n - 1;
            }
            $totaleInclusiAdj += $n;
        }
    }

    // 2) Costruisco righe output usando il totale_inclusi "aggiustato"
    foreach ($dati['righe'] as $riga) {
        if (!empty($riga['is_totale'])) {
            $righe[] = [
                'Targa'       => 'TOTALE',
                'n_servizi'   => $totaleInclusiAdj,
                'percentuale' => $totaleInclusiAdj > 0 ? 100 : 0,
                'importo'     => $totaleBilancio,
                'is_totale'   => -1,
            ];
            continue;
        }

        $incluso = !empty($riga['incluso_riparto']);
        $nServ   = (int)($riga['totale'] ?? 0);

        if ($checkConv) {
            // sempre -1 anche se era già 0
            $nServ = $nServ - 1;
        }

        if ($incluso && $totaleInclusiAdj > 0) {
            $percentuale = round(($nServ / $totaleInclusiAdj) * 100, 2);
            $importo     = round(($nServ / $totaleInclusiAdj) * $totaleBilancio, 2);
        } else {
            $percentuale = 0;
            $importo     = 0;
        }

        $righe[] = [
            'Targa'       => $riga['Targa'],
            'n_servizi'   => $nServ,
            'percentuale' => $percentuale,
            'importo'     => $importo,
            'is_totale'   => 0,
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
