<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Automezzo;
use App\Models\Dipendente;
use Illuminate\Support\Collection;


class CostiRadioController extends Controller {
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');
        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);
        // Prendi l'associazione selezionata (se sei admin) oppure quella dell'utente
        $selectedAssoc = session('associazione_selezionata');
        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? $selectedAssoc
            : $user->IdAssociazione;

        $automezzi = Automezzo::getForRipartizione($anno, $idAssociazione);
        $numeroAutomezzi = count($automezzi);
        if($idAssociazione == null){
            $idAssociazione= $user->IdAssociazione;
            $selectedAssoc=  $idAssociazione;
            session(['associazione_selezionata' => $selectedAssoc]);
        } 
        return view('ripartizioni.costi_radio.index', compact('numeroAutomezzi', 'anno', 'idAssociazione', 'associazioni'));
    }

    private function getAutomezziFiltrati($anno): Collection {
        $user = Auth::user();
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            return Automezzo::getAll($anno);
        }

        $idAssoc = $user->IdAssociazione;
        abort_if(!$idAssoc, 403, "Associazione non trovata per l'utente.");
        return Automezzo::getByAssociazione($idAssoc, $anno);
    }

    public function getData() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        // Recupero ID Associazione
        $selectedAssoc = session('associazione_selezionata');
        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? session('associazione_selezionata')
            : $user->IdAssociazione;
        
        // Recupero automezzi
        $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);
        $numAutomezzi = max(count($automezzi), 1);

        // Recupero totali
        $totali = DB::table('costi_radio')
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->first();

    
        $rows = [];

        // Riga totale
        $rows[] = [
            'Targa' => 'TOTALE',
            'ManutenzioneApparatiRadio'     => round($totali->ManutenzioneApparatiRadio ?? 0, 2),
            'MontaggioSmontaggioRadio118'   => round($totali->MontaggioSmontaggioRadio118 ?? 0, 2),
            'LocazionePonteRadio'           => round($totali->LocazionePonteRadio ?? 0, 2),
            'AmmortamentoImpiantiRadio'     => round($totali->AmmortamentoImpiantiRadio ?? 0, 2),
            'is_totale'                     => -1,
        ];


        // Righe per ogni automezzo
        foreach ($automezzi as $a) {
            $rows[] = [
                'Targa' => $a->Targa,
                'ManutenzioneApparatiRadio'     => round(($totali->ManutenzioneApparatiRadio ?? 0) / $numAutomezzi, 2),
                'MontaggioSmontaggioRadio118'   => round(($totali->MontaggioSmontaggioRadio118 ?? 0) / $numAutomezzi, 2),
                'LocazionePonteRadio'           => round(($totali->LocazionePonteRadio ?? 0) / $numAutomezzi, 2),
                'AmmortamentoImpiantiRadio'     => round(($totali->AmmortamentoImpiantiRadio ?? 0) / $numAutomezzi, 2),
                'is_totale'                     => 0,
            ];
        }
        return response()->json(['data' => $rows]);
    }


    public function editTotale() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $automezzi = $this->getAutomezziFiltrati($anno);

        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? session('associazione_selezionata')
            : $user->IdAssociazione;
            
        abort_if(!$idAssociazione, 403, "Associazione non determinata.");

        $record = DB::table('costi_radio')
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->first();

        return view('ripartizioni.costi_radio.edit_totale', compact('record', 'anno'));
    }

    public function updateTotale(Request $request) {
        $data = $request->validate([
            'TotManutenzioneRadio' => 'required|numeric',
            'TotMontaggioRadio' => 'required|numeric',
            'TotLocazioneRadio' => 'required|numeric',
            'TotAmmortamentoRadio' => 'required|numeric'
        ]);

        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $automezzi = $this->getAutomezziFiltrati($anno);

        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? session('associazione_selezionata')
            : $user->IdAssociazione;

        //abort_if(!$idAssociazione, 403, "Associazione non determinata.");

        DB::table('costi_radio')->updateOrInsert(
            ['idAssociazione' => $idAssociazione, 'idAnno' => $anno],
            [
                'ManutenzioneApparatiRadio' => $data['TotManutenzioneRadio'],
                'MontaggioSmontaggioRadio118' => $data['TotMontaggioRadio'],
                'LocazionePonteRadio' => $data['TotLocazioneRadio'],
                'AmmortamentoImpiantiRadio' => $data['TotAmmortamentoRadio']
            ]
        );
        return redirect()->route('ripartizioni.costi_radio.index')->with('success', 'Totali aggiornati correttamente.');
    }
}
