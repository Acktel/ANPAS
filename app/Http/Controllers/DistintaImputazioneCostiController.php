<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;

class DistintaImputazioneCostiController extends Controller {
    public function index(Request $request) {
        $anno = session('anno');
        $user = auth()->user();

        $associazioni = [];
        $selectedAssoc = session('idAssociazione');

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && !session()->has('impersonated_by')) {
            $associazioni = DB::table('associazioni')->orderBy('Associazione')->get();
        }

        return view('distinta_imputazione_costi.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }

    public function getData(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $isImpersonating = session()->has('impersonate');
        $selectedAssoc = null;
        $associazioni = [];

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            if ($request->has('idAssociazione')) {
                session(['associazione_selezionata' => $request->get('idAssociazione')]);
            }

            $selectedAssoc = session('associazione_selezionata') ?? ($associazioni->first()->idAssociazione ?? null);
        } else {
            $selectedAssoc = $user->IdAssociazione;
        }

        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $selectedAssoc)
            ->where('idAnno', $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        $costiRipartiti = RipartizioneCostiService::calcolaTabellaTotale($selectedAssoc, $anno);

        $voceToSezione = [
            'LEASING/NOLEGGIO A LUNGO TERMINE' => 2,
            'ASSICURAZIONI' => 2,
            'MANUTENZIONE ORDINARIA' => 2,
            'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI' => 2,
            'RIMBORSI ASSICURAZIONE' => 2,
            'PULIZIA E DISINFEZIONE' => 2,
            'CARBURANTI AL NETTO RIMBORSI UTIF' => 2,
            'ADDITIVI' => 2,
            'INTERESSI PASS. F.TO, LEASING, NOL.' => 2,
            'ALTRI COSTI MEZZI' => 2,

            'MANUTENZIONE ATTREZZATURA SANITARIA' => 3,
            'LEASING ATTREZZATURA SANITARIA' => 3,

            'MANUTENZIONE APPARATI RADIO' => 4,
            'MONTAGGIO/SMONTAGGIO RADIO 118' => 4,
            'LOCAZIONE PONTE RADIO' => 4,
            'AMMORTAMENTO IMPIANTI RADIO' => 4,

            'AMMORTAMENTO AUTOMEZZI' => 9,
            'AMMORTAMENTO ATTREZZATURA SANITARIA' => 9,
        ];

        $righe = [];

        foreach ($costiRipartiti as $voce) {
            $nomeVoce = $voce['voce'];
            $sezione = $voceToSezione[$nomeVoce] ?? null;
            if (!$sezione) continue;

            $riga = [
                'voce' => $nomeVoce,
                'bilancio' => floatval($voce['totale'] ?? 0),
                'diretta' => 0,
                'totale' => 0,
                'sezione_id' => $sezione
            ];

            foreach ($convenzioni as $idConv => $nomeConv) {
                $val = floatval($voce[$nomeConv] ?? 0);
                $riga[$nomeConv] = [
                    'diretti' => 0,
                    'indiretti' => $val
                ];
                $riga['totale'] += $val;
            }

            $righe[] = $riga;
        }

        return response()->json([
            'data' => $righe,
            'convenzioni' => array_values($convenzioni)
        ]);
    }
}
