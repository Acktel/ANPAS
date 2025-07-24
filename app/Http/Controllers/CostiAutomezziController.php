<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CostiAutomezzi;
use App\Models\Automezzo;
use App\Models\Associazione;

class CostiAutomezziController extends Controller {
    public function index() {
        $isElevatedUser = auth()->user()->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
        $isImpersonating = session()->has('impersonate');
        $showAssociazione = $isElevatedUser && !$isImpersonating;

        $anno = session('anno_riferimento', now()->year);
        return view('ripartizioni.costi_automezzi.index', compact('anno', 'showAssociazione'));
    }

    public function getData() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $automezzi = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? Automezzo::getAll($anno)
            : Automezzo::getByAssociazione($user->IdAssociazione, $anno);

        $showAssociazione = !$isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $costi = CostiAutomezzi::getAllByAnno($anno)->keyBy('idAutomezzo');

        $rows = [];

        // Inizializzazione riga totale
        $totali = [
            'idAutomezzo' => null,
            'Targa' => '',
            'CodiceIdentificativo' => 'TOTALE',
            'LeasingNoleggio' => 0,
            'Assicurazione' => 0,
            'ManutenzioneOrdinaria' => 0,
            'ManutenzioneStraordinaria' => 0,
            'RimborsiAssicurazione' => 0,
            'PuliziaDisinfezione' => 0,
            'Carburanti' => 0,
            'Additivi' => 0,
            'RimborsiUTF' => 0,
            'InteressiPassivi' => 0,
            'AltriCostiMezzi' => 0,
            'ManutenzioneSanitaria' => 0,
            'LeasingSanitaria' => 0,
            'AmmortamentoMezzi' => 0,
            'AmmortamentoSanitaria' => 0,
            'is_totale' => -1,
        ];

        if ($showAssociazione) {
            $totali['Associazione'] = '';
        }

        foreach ($automezzi as $a) {
            $c = $costi->get($a->idAutomezzo);

            $row = [
                'idAutomezzo' => $a->idAutomezzo,
                'Targa' => $a->Targa,
                'CodiceIdentificativo' => $a->CodiceIdentificativo,
                'LeasingNoleggio' => $c->LeasingNoleggio ?? 0,
                'Assicurazione' => $c->Assicurazione ?? 0,
                'ManutenzioneOrdinaria' => $c->ManutenzioneOrdinaria ?? 0,
                'ManutenzioneStraordinaria' => $c->ManutenzioneStraordinaria ?? 0,
                'RimborsiAssicurazione' => $c->RimborsiAssicurazione ?? 0,
                'PuliziaDisinfezione' => $c->PuliziaDisinfezione ?? 0,
                'Carburanti' => $c->Carburanti ?? 0,
                'Additivi' => $c->Additivi ?? 0,
                'RimborsiUTF' => $c->RimborsiUTF ?? 0,
                'InteressiPassivi' => $c->InteressiPassivi ?? 0,
                'AltriCostiMezzi' => $c->AltriCostiMezzi ?? 0,
                'ManutenzioneSanitaria' => $c->ManutenzioneSanitaria ?? 0,
                'LeasingSanitaria' => $c->LeasingSanitaria ?? 0,
                'AmmortamentoMezzi' => $c->AmmortamentoMezzi ?? 0,
                'AmmortamentoSanitaria' => $c->AmmortamentoSanitaria ?? 0,
                'is_totale' => 0,
            ];

            if ($showAssociazione) {
                $row['Associazione'] = $a->Associazione ?? '-';
            }

            // Aggiunta riga all'elenco
            $rows[] = $row;

            // Somma nei totali
            foreach ($row as $key => $val) {
                if (in_array($key, array_keys($totali)) && is_numeric($val)) {
                    $totali[$key] += $val;
                }
            }
        }

        // Inserisce i totali in cima
        array_unshift($rows, $totali);

        return response()->json(['data' => $rows]);
    }

    public function edit($idAutomezzo) {
        $anno = session('anno_riferimento', now()->year);
        $record = CostiAutomezzi::getOrEmpty($idAutomezzo, $anno);

        return view('ripartizioni.costi_automezzi.edit', compact('record', 'anno'));
    }


    public function update(Request $request, $idAutomezzo) {
        $data = $request->validate([
            'LeasingNoleggio' => 'required|numeric',
            'Assicurazione' => 'required|numeric',
            'ManutenzioneOrdinaria' => 'required|numeric',
            'ManutenzioneStraordinaria' => 'required|numeric',
            'RimborsiAssicurazione' => 'required|numeric',
            'PuliziaDisinfezione' => 'required|numeric',
            'Carburanti' => 'required|numeric',
            'Additivi' => 'required|numeric',
            'RimborsiUTF' => 'required|numeric',
            'InteressiPassivi' => 'required|numeric',
            'AltriCostiMezzi' => 'required|numeric',
            'ManutenzioneSanitaria' => 'required|numeric',
            'LeasingSanitaria' => 'required|numeric',
            'AmmortamentoMezzi' => 'required|numeric',
            'AmmortamentoSanitaria' => 'required|numeric',
        ]);

        $data['idAutomezzo'] = $idAutomezzo;
        $data['idAnno'] = session('anno_riferimento', now()->year);

        CostiAutomezzi::updateOrInsert($data);

        return redirect()->route('ripartizioni.costi_automezzi.index')->with('success', 'Dati aggiornati.');
    }
}
