<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\CostoMaterialeSanitario;
use App\Models\Automezzo;
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

    public function getData(Request $request): JsonResponse {
    $anno = session('anno_riferimento', now()->year);
    $user = Auth::user();

    // Prendi idAssociazione dalla query string, oppure dalla sessione, oppure dal fallback utente
    $idAssociazione = $request->query('idAssociazione')
        ?? session('associazione_selezionata')
        ?? $user->IdAssociazione;

    // Ottieni gli automezzi filtrati per associazione
    $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);

    // Ricalcola i dati con la giusta associazione
    $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);
    $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $anno);

    $righe = [];

    foreach ($dati['righe'] as $riga) {
        if (isset($riga['is_totale']) && $riga['is_totale']) {

        $nServiziTotale = isset($riga['totale']) ? (int) $riga['totale'] : 0;

            // calcola percentuale in modo coerente con le altre righe
            $percentualeTotale = ($dati['totale_inclusi'] > 0)
                ? round(($nServiziTotale / $dati['totale_inclusi']) * 100, 2)
                : 0;


            $righe[] = [
                'Targa'       => 'TOTALE',
                'n_servizi'   => $riga['totale'],
                'percentuale' => $percentualeTotale,
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
