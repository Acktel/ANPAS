<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Models\Automezzo;
use App\Models\CostoOssigeno;
use App\Models\Dipendente;
use App\Models\RipartizioneOssigeno;

class CostoOssigenoController extends Controller {
    /**
     * Mostra la vista con la tabella di imputazione costi dell'ossigeno.
     */
  public function index(Request $request) {
    $anno = session('anno_riferimento', now()->year);
    $user = Auth::user();
    $isImpersonating = session()->has('impersonate');

    $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

    $idAssociazione = session('associazione_selezionata', $user->IdAssociazione);
    if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
        $idAssociazione = $user->IdAssociazione;
    }

    $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);

    $numeroServizi  = RipartizioneOssigeno::getTotaleServizi($automezzi, $anno);
    $totaleBilancio = CostoOssigeno::getTotale($idAssociazione, $anno);
    $dati           = RipartizioneOssigeno::getRipartizione($idAssociazione, $anno);

    return view('imputazioni.ossigeno.index', [
        'anno'           => $anno,
        'numeroServizi'  => $numeroServizi,
        'totaleBilancio' => $totaleBilancio,
        'righe'          => $dati['righe'],
        'totale_inclusi' => $dati['totale_inclusi'] ?? 0,
        'associazioni'   => $associazioni,
        'selectedAssoc'  => $idAssociazione,
    ]);
}


    /**
     * Aggiorna il valore totale a bilancio dell'ossigeno.
     */
    public function updateTotale(Request $request) {
        $request->validate([
            'TotaleBilancio' => 'required|numeric|min:0'
        ]);

        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getFiltratiByUtente($anno);
        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        CostoOssigeno::upsertTotale($idAssociazione, $anno, $request->TotaleBilancio);

        return redirect()
            ->route('imputazioni.ossigeno.index')
            ->with('success', 'Totale a bilancio aggiornato correttamente.');
    }

  public function getData(Request $request): JsonResponse {
    $anno = session('anno_riferimento', now()->year);
    $user = Auth::user();

    $idAssociazione = $request->query('idAssociazione')
        ?? session('associazione_selezionata')
        ?? $user->IdAssociazione;

    if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
        $idAssociazione = $user->IdAssociazione;
    }

    $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);
    $dati = RipartizioneOssigeno::getRipartizione($idAssociazione, $anno);
    $totaleBilancio = CostoOssigeno::getTotale($idAssociazione, $anno);

    $righe = [];

    foreach ($dati['righe'] as $riga) {
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
        $idAssociazione = $automezzi->first()->idAssociazione ?? null;
        $totale = CostoOssigeno::getTotale($idAssociazione, $anno);

        return view('imputazioni.ossigeno.edit_totale', [
            'totale' => $totale,
            'anno'   => $anno
        ]);
    }
}
