<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CostiAutomezzi;
use App\Models\Automezzo;
use App\Models\Dipendente;

class CostiAutomezziController extends Controller
{
    /** Colonne monetarie da sommare/mostrare a 2 decimali */
    private const MONEY_KEYS = [
        'LeasingNoleggio','Assicurazione','ManutenzioneOrdinaria','ManutenzioneStraordinaria',
        'RimborsiAssicurazione','PuliziaDisinfezione','Carburanti','Additivi','RimborsiUTF',
        'InteressiPassivi','AltriCostiMezzi','ManutenzioneSanitaria','LeasingSanitaria',
        'AmmortamentoMezzi','AmmortamentoSanitaria',
    ];

    public function index()
    {
        $anno            = session('anno_riferimento', now()->year);
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $associazioni     = Dipendente::getAssociazioni($user, $isImpersonating);
        $selectedAssoc    = session('associazione_selezionata') ?? $user->IdAssociazione;
        $showAssociazione = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor']);

        return view('ripartizioni.costi_automezzi.index', compact('anno','showAssociazione','associazioni','selectedAssoc'));
    }

    public function getData()
    {
        $anno            = session('anno_riferimento', now()->year);
        $user            = Auth::user();
        $isImpersonating = session()->has('impersonate');

        // id associazione in base al ruolo
        $selectedAssoc   = session('associazione_selezionata');
        $idAssociazione  = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])
            ? $selectedAssoc
            : $user->IdAssociazione;

        // automezzi dell’associazione per l’anno
        $automezzi         = Automezzo::getByAssociazione($idAssociazione, $anno);
        $showAssociazione  = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor']);
        $costiByAutomezzo  = CostiAutomezzi::getAllByAnno($anno)->keyBy('idAutomezzo');

        $rows = [];

        // riga “totale” (valori non numerici + placeholder)
        $totali = [
            'idAutomezzo'            => null,
            'Targa'                  => '',
            'CodiceIdentificativo'   => 'TOTALE',
            'is_totale'              => -1,
        ];
        if ($showAssociazione) $totali['Associazione'] = '';

        // accumulatore grezzo per somme (niente arrotondamenti qui)
        $acc = array_fill_keys(self::MONEY_KEYS, 0.0);

        foreach ($automezzi as $a) {
            $c = $costiByAutomezzo->get($a->idAutomezzo);

            $row = [
                'idAutomezzo'               => $a->idAutomezzo,
                'Targa'                     => $a->Targa,
                'CodiceIdentificativo'      => $a->CodiceIdentificativo,
                'LeasingNoleggio'           => (float) ($c?->LeasingNoleggio           ?? 0),
                'Assicurazione'             => (float) ($c?->Assicurazione             ?? 0),
                'ManutenzioneOrdinaria'     => (float) ($c?->ManutenzioneOrdinaria     ?? 0),
                'ManutenzioneStraordinaria' => (float) ($c?->ManutenzioneStraordinaria ?? 0),
                'RimborsiAssicurazione'     => (float) ($c?->RimborsiAssicurazione     ?? 0),
                'PuliziaDisinfezione'       => (float) ($c?->PuliziaDisinfezione       ?? 0),
                'Carburanti'                => (float) ($c?->Carburanti                ?? 0),
                'Additivi'                  => (float) ($c?->Additivi                  ?? 0),
                'RimborsiUTF'               => (float) ($c?->RimborsiUTF               ?? 0),
                'InteressiPassivi'          => (float) ($c?->InteressiPassivi          ?? 0),
                'AltriCostiMezzi'           => (float) ($c?->AltriCostiMezzi           ?? 0),
                'ManutenzioneSanitaria'     => (float) ($c?->ManutenzioneSanitaria     ?? 0),
                'LeasingSanitaria'          => (float) ($c?->LeasingSanitaria          ?? 0),
                'AmmortamentoMezzi'         => (float) ($c?->AmmortamentoMezzi         ?? 0),
                'AmmortamentoSanitaria'     => (float) ($c?->AmmortamentoSanitaria     ?? 0),
                'is_totale'                 => 0,
            ];
            if ($showAssociazione) $row['Associazione'] = $a->Associazione ?? '-';

            // accumulo grezzo (nessun round prima)
            foreach (self::MONEY_KEYS as $k) {
                $acc[$k] += $row[$k];
            }

            $rows[] = $row;
        }

        // assegna i totali arrotondando SOLO ora (PHP_ROUND_HALF_UP)
        foreach (self::MONEY_KEYS as $k) {
            $totali[$k] = round($acc[$k], 2, PHP_ROUND_HALF_UP);
        }

        // piazza la riga totale in testa
        array_unshift($rows, $totali);

        return response()->json(['data' => $rows]);
    }

    public function edit($idAutomezzo)
    {
        $anno     = session('anno_riferimento', now()->year);
        $record   = CostiAutomezzi::getOrEmpty($idAutomezzo, $anno);
        $automezzo= Automezzo::getById($idAutomezzo, $anno);

        return view('ripartizioni.costi_automezzi.edit', compact('record','anno','automezzo'));
    }

    public function update(Request $request, $idAutomezzo)
    {
        $fields = self::MONEY_KEYS; // riuso l’elenco

        // 1) regole: monetari come stringhe liberamente formattate, Note testuale
        $rules = array_fill_keys($fields, 'nullable|string');
        $rules['Note'] = 'nullable|string|max:2000';
        $data = $request->validate($rules);

        // 2) normalizza input numerici (accetta "1.234,56" e "1,234.56")
        foreach ($fields as $f) {
            $s = trim((string)($data[$f] ?? ''));
            if ($s === '') { $data[$f] = 0; continue; }
            if (preg_match('/,\d{1,2}$/', $s)) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
            $data[$f] = round((float)$s, 2, PHP_ROUND_HALF_UP);
        }

        // 3) chiavi + note
        $data['idAutomezzo'] = (int) $idAutomezzo;
        $data['idAnno']      = (int) session('anno_riferimento', now()->year);
        $data['Note']        = $request->input('Note', null);

        CostiAutomezzi::updateOrInsert($data);

        return redirect()
            ->route('ripartizioni.costi_automezzi.index', ['idAssociazione' => $request->input('idAssociazione')])
            ->with('success', 'Dati aggiornati.');
    }
}
