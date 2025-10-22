<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\CostoMaterialeSanitario;
use App\Models\Automezzo;
use App\Models\Convenzione;
use App\Models\Dipendente;
use App\Models\AutomezzoKm; 
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

        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        CostoMaterialeSanitario::upsertTotale($idAssociazione, $anno, $request->TotaleBilancio);

        return redirect()
            ->route('imputazioni.materiale_sanitario.index')
            ->with('success', 'Aggiornamento completato.');
    }

    /**
     * Dati per DataTable con il nuovo algoritmo:
     * servizi_adjusted = servizi - (kmPercorsi se materiale_fornito_asl = 1 per quella convenzione).
     */
    public function getData(Request $request): JsonResponse {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = $request->query('idAssociazione')
            ?? session('associazione_selezionata')
            ?? $user->IdAssociazione;

        // Automezzi & ripartizione base (contiene per ogni riga: valori[conv] = n_servizi)
        $automezzi      = Automezzo::getByAssociazione($idAssociazione, $anno);
        $dati           = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $anno);
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $anno);

        // Convenzioni dell’associazione con flag materiale
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);
        $convFlagYes = $convenzioni
            ->filter(fn($c) => (int)($c->materiale_fornito_asl ?? 0) === 1)
            ->pluck('idConvenzione')
            ->all();
        $convFlagYes = array_map('intval', $convFlagYes);
        $convFlagYesSet = array_flip($convFlagYes); // set O(1)

        // KM percorsi per (automezzo, convenzione) nell’anno (filtrati sugli automezzi dell’associazione)
        $kmData = AutomezzoKm::getGroupedByAutomezzoAndConvenzione($anno, $user, $idAssociazione)
            ->filter(function ($group, $key) use ($automezzi) {
                [$idAutomezzo,] = explode('-', $key);
                return $automezzi->pluck('idAutomezzo')->contains((int) $idAutomezzo);
            });
        $righeOut = [];

        // 1) somma adjusted totale per gli inclusi
        $totaleInclusiAdj = 0;

        foreach ($dati['righe'] as $idAuto => $r) {
            if (!empty($r['is_totale'])) {
                continue;
            }

            $incluso = !empty($r['incluso_riparto']);
            $valori  = $r['valori'] ?? []; // [idConvenzione => NumeroServizi]
            $adjustedSum = 0;

            if ($incluso) {
                foreach ($valori as $idConv => $numServizi) {
                    $numServizi = (int) $numServizi;
                    // se la convenzione ha materiale fornito da ASL, sottraggo i KM percorsi
                    //In realtà è un refuso di alessandra! stiamo sottraendo i servizi non i km

                    if (isset($convFlagYesSet[(int)$idConv])) {
                        $lookup = $idAuto . '-' . $idConv;
                        $kmPercorsi = 0;
                     
                        if ($kmData->has($lookup)) {
                            $kmPercorsi = (float) $kmData->get($lookup)->sum('KMPercorsi');
                        }
                      
                        $numServizi = max(0, $numServizi - $kmPercorsi); // clamp a 0
                    }

                    $adjustedSum += $numServizi;
                }

                $totaleInclusiAdj += $adjustedSum;
            }

            $righeOut[] = [
                'Targa'       => $r['Targa'] ?? '',
                'n_servizi'   => $adjustedSum,
                'incluso'     => $incluso,
                'is_totale'   => 0,
            ];
        }

        // 2) calcolo percentuali / importi + riga totale
        $righeFinal = [];
        foreach ($righeOut as $row) {
            $incluso = $row['incluso'];
            $nServ   = (int) $row['n_servizi'];

            if ($incluso && $totaleInclusiAdj > 0) {
                $percentuale = round(($nServ / $totaleInclusiAdj) * 100, 2);
                $importo     = round(($nServ / $totaleInclusiAdj) * $totaleBilancio, 2);
            } else {
                $percentuale = 0;
                $importo     = 0;
            }

            $righeFinal[] = [
                'Targa'       => $row['Targa'],
                'n_servizi'   => $nServ,
                'percentuale' => $percentuale,
                'importo'     => $importo,
                'is_totale'   => 0,
            ];
        }

        // riga totale
        $righeFinal[] = [
            'Targa'       => 'TOTALE',
            'n_servizi'   => $totaleInclusiAdj,
            'percentuale' => $totaleInclusiAdj > 0 ? 100 : 0,
            'importo'     => $totaleBilancio,
            'is_totale'   => -1,
        ];

        return response()->json(['data' => $righeFinal]);
    }

    public function editTotale(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $automezzi = Automezzo::getFiltratiByUtente($anno);

        $idAssociazione = $automezzi->first()->idAssociazione ?? null;
        $totale = CostoMaterialeSanitario::getTotale($idAssociazione, $anno);

        return view('imputazioni.materiale_sanitario.edit_totale', [
            'totale' => $totale,
            'anno'   => $anno
        ]);
    }
}
