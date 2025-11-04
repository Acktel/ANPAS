<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Models\Dipendente;
use App\Services\RipartizioneCostiService;

class HomeController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    public function index(Request $request): View {
        $anno             = (int) session('anno_riferimento', now()->year);
        $user             = Auth::user();
        $isImpersonating  = session()->has('impersonate');

        // Associazioni visibili all'utente
        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

        // Associazione selezionata (GET -> session; per non admin usa quella dell’utente)
        $selectedAssoc = $request->query('idAssociazione', session('idAssociazione'));
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            if ($request->has('idAssociazione')) {
                session(['idAssociazione' => $selectedAssoc]);
            }
        } else {
            $selectedAssoc = $user->IdAssociazione;
            session(['idAssociazione' => $selectedAssoc]);
        }
        $selectedAssoc = $selectedAssoc ? (int)$selectedAssoc : null;

        // Nome associazione per header
        $assocName = $selectedAssoc
            ? DB::table('associazioni')
            ->where('idAssociazione', $selectedAssoc)
            ->value('Associazione')
            : null;

        /* ===================== CONSUNTIVO per tipologia ===================== */
        $consPerTip = []; // [idTipologia => float]
        $convNames  = [];

        if ($selectedAssoc) {
            $dist   = RipartizioneCostiService::distintaImputazioneData($selectedAssoc, $anno);
            $righe  = $dist['data'] ?? [];
            $convNm = $dist['convenzioni'] ?? [];
            $convNames = is_array($convNm) ? $convNm : [];

            foreach ($righe as $r) {
                $tip = (int)($r['sezione_id'] ?? 0);
                if ($tip <= 0) continue;

                $consVoce = 0.0;
                foreach ($convNames as $nomeConv) {
                    $d = (float)($r[$nomeConv]['diretti']      ?? 0.0);
                    $a = (float)($r[$nomeConv]['ammortamento'] ?? 0.0);
                    $i = (float)($r[$nomeConv]['indiretti']    ?? 0.0);
                    $consVoce += ($d - $a + $i);
                }
                $consPerTip[$tip] = ($consPerTip[$tip] ?? 0.0) + $consVoce;
            }
        }

        /* ===================== PREVENTIVO per tipologia ===================== */
        $prevPerTip = [];
        if ($selectedAssoc) {
            $prevPerTip = DB::table('riepiloghi as r')
                ->join('riepilogo_dati as rd', 'rd.idRiepilogo', '=', 'r.idRiepilogo')
                ->join('riepilogo_voci_config as vc', 'vc.id', '=', 'rd.idVoceConfig')
                ->where('r.idAssociazione', $selectedAssoc)
                ->where('r.idAnno', $anno)
                ->select('vc.idTipologiaRiepilogo as tip', DB::raw('SUM(rd.preventivo) as prev'))
                ->groupBy('vc.idTipologiaRiepilogo')
                ->pluck('prev', 'tip')
                ->toArray();
        }

        /* ===================== Label ordinate (2..11) ===================== */
        // ⚠️ Tabella corretta: tipologia_riepilogo
        $tipologie = DB::table('tipologia_riepilogo')
            ->whereBetween('id', [2, 11])
            ->orderBy('id')
            ->pluck('descrizione', 'id')
            ->toArray();

        $labels       = [];
        $preventivi   = [];
        $consuntivi   = [];
        $scostamenti  = [];

        foreach ($tipologie as $tipId => $desc) {
            $prev = (float)($prevPerTip[$tipId] ?? 0.0);
            $cons = (float)($consPerTip[$tipId] ?? 0.0);

            // salta righe completamente vuote
            if ($prev == 0.0 && $cons == 0.0) continue;

            $labels[]      = $desc;
            $preventivi[]  = round($prev, 2);
            $consuntivi[]  = round($cons, 2);
            $scostamenti[] = ($prev != 0.0)
                ? round((($cons - $prev) / $prev) * 100, 2)
                : 0.0;
        }

        /* ===================== KPI ===================== */
        $totPrev      = array_sum($preventivi);
        $totCons      = array_sum($consuntivi);
        $deltaTot     = $totCons - $totPrev;
        $scostPercTot = ($totPrev != 0.0) ? round(($deltaTot / $totPrev) * 100, 2) : null;

        $numeroConvenzioni = $selectedAssoc
            ? DB::table('convenzioni')
            ->where('idAssociazione', $selectedAssoc)
            ->where('idAnno', $anno)
            ->count()
            : 0;

        $numeroAutomezzi = $selectedAssoc
            ? DB::table('automezzi')
            ->where('idAssociazione', $selectedAssoc)
            ->where('idAnno', $anno)
            ->count()
            : 0;

        $kmTotali = $selectedAssoc
            ? (int) DB::table('automezzi_km')
                ->join('automezzi', 'automezzi.idAutomezzo', '=', 'automezzi_km.idAutomezzo')
                ->where('automezzi.idAssociazione', $selectedAssoc)
                ->where('automezzi.idAnno', $anno)
                ->sum('KMPercorsi')
            : 0;

        /* ===================== TOP 5 SCOSTAMENTI ===================== */
        $rows = [];
        foreach ($labels as $i => $lab) {
            $prev  = $preventivi[$i] ?? 0.0;
            $cons  = $consuntivi[$i] ?? 0.0;
            $delta = round($cons - $prev, 2);
            $rows[] = ['label' => $lab, 'delta' => $delta, 'abs' => abs($delta)];
        }
        usort($rows, fn($a, $b) => $b['abs'] <=> $a['abs']);
        $rows = array_slice($rows, 0, 5);

        $topLabels = array_column($rows, 'label');
        $topDelta  = array_column($rows, 'delta'); // mantiene il segno

        return view('dashboard', compact(
            'anno',
            'associazioni',
            'selectedAssoc',
            'assocName',
            'labels',
            'preventivi',
            'consuntivi',
            'scostamenti',
            'totPrev',
            'totCons',
            'deltaTot',
            'scostPercTot',
            'numeroConvenzioni',
            'numeroAutomezzi',
            'kmTotali',
            'topLabels',
            'topDelta'
        ));
    }
}
