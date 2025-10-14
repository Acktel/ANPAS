<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CostiAutomezzi;
use App\Models\Automezzo;
use App\Models\Associazione;
use App\Models\Dipendente;

class CostiAutomezziController extends Controller {
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);
        $selectedAssoc = session('associazione_selezionata') ?? $user->IdAssociazione;
        $showAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        return view('ripartizioni.costi_automezzi.index', compact('anno', 'showAssociazione', 'associazioni', 'selectedAssoc'));
    }

    public function getData() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        // 🔍 ID Associazione da sessione o da utente se non admin
        $selectedAssoc = session('associazione_selezionata');
        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? $selectedAssoc
            : $user->IdAssociazione;

        // 🧾 Prendi solo gli automezzi della associazione selezionata
        $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);

        $showAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        $costi = CostiAutomezzi::getAllByAnno($anno)->keyBy('idAutomezzo');

        $rows = [];

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

            $rows[] = $row;

            foreach ($row as $key => $val) {
                if (isset($totali[$key]) && is_numeric($val)) {
                    $totali[$key] += $val;
                }
            }
        }

        array_unshift($rows, $totali);

        return response()->json(['data' => $rows]);
    }


    public function edit($idAutomezzo) {
        $anno = session('anno_riferimento', now()->year);
        $record = CostiAutomezzi::getOrEmpty($idAutomezzo, $anno);
        $automezzo = Automezzo::getById($idAutomezzo, $anno);
        return view('ripartizioni.costi_automezzi.edit', compact('record', 'anno', 'automezzo'));
    }


    public function update(Request $request, $idAutomezzo) {
        $fields = [
            'LeasingNoleggio',
            'Assicurazione',
            'ManutenzioneOrdinaria',
            'ManutenzioneStraordinaria',
            'RimborsiAssicurazione',
            'PuliziaDisinfezione',
            'Carburanti',
            'Additivi',
            'RimborsiUTF',
            'InteressiPassivi',
            'AltriCostiMezzi',
            'ManutenzioneSanitaria',
            'LeasingSanitaria',
            'AmmortamentoMezzi',
            'AmmortamentoSanitaria',
        ];

        // 1) Valida ma consenti vuoto (che poi mettiamo a 0)
        $rules = array_fill_keys($fields, 'nullable|numeric');
        $data  = $request->validate($rules);

        // 2) Vuoto -> 0 (e preserva i decimali se presenti)
        foreach ($fields as $f) {
            $v = $data[$f] ?? null;
            if ($v === null || $v === '') {
                $data[$f] = 0;
            } else {
                // accetta anche "1.234,56"
                $s = (string)$v;
                if (preg_match('/,\d+$/', $s)) {
                    $s = str_replace('.', '', $s);
                    $s = str_replace(',', '.', $s);
                } else {
                    $s = str_replace(',', '', $s);
                }
                $data[$f] = round((float)$s, 2);
            }
        }

        // 3) Chiavi
        $data['idAutomezzo'] = (int)$idAutomezzo;
        $data['idAnno'] = (int) session('anno_riferimento', now()->year);

        CostiAutomezzi::updateOrInsert($data);

        return redirect()
            ->route('ripartizioni.costi_automezzi.index', ['idAssociazione' => $request->input('idAssociazione')])
            ->with('success', 'Dati aggiornati.');
    }
}
