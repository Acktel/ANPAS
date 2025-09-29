<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Dipendente;
use App\Models\CostiPersonale;
use App\Models\Convenzione;
use App\Models\RipartizionePersonale;
use App\Models\CostiMansioni;

class CostiPersonaleController extends Controller
{
    public function index(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $selectedAssoc = session('associazione_selezionata') ?? $request->query('idAssociazione');

        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

        $qualifiche = DB::table('qualifiche')
            ->select('id', 'nome')
            ->orderBy('ordinamento')
            ->get();

        return view('ripartizioni.costi_personale.index', compact('anno', 'selectedAssoc', 'associazioni', 'qualifiche'));
    }

    public function getData() {
    $anno = session('anno_riferimento', now()->year);
    $user = Auth::user();

    $idQualifica = request()->query('idQualifica');
    $idQualifica = is_numeric($idQualifica) ? (int)$idQualifica : null;

    $qualificaTesto = $idQualifica ? '' : strtolower(trim((string) request()->query('qualifica', '')));

    $selectedAssoc = session('associazione_selezionata');
    $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
        ? $selectedAssoc
        : $user->IdAssociazione;

    if (!$idAssociazione) {
        return response()->json(['data' => [], 'labels' => []]);
    }

    // Dipendenti dell'associazione/anno
    $dipendenti = Dipendente::getByAssociazione($idAssociazione, $anno);

    // Pivot: dipendente -> [idQualifica...]
    $qualifichePivot = DB::table('dipendenti_qualifiche')
        ->select('idDipendente', 'idQualifica')
        ->get()
        ->groupBy('idDipendente')
        ->map(fn($rows) => $rows->pluck('idQualifica')->map(fn($v) => (int)$v)->toArray());

    // Mappa id->nome
    $nomiQualifiche = DB::table('qualifiche')->pluck('nome', 'id');

    // Filtro testuale (legacy)
    if (!$idQualifica && $qualificaTesto !== '') {
        $matching = DB::table('qualifiche')
            ->whereRaw('LOWER(nome) LIKE ?', ['%' . $qualificaTesto . '%'])
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->values();

        if ($matching->count() === 1) {
            $idQualifica = (int)$matching->first();
        } else {
            $dipendenti = $dipendenti->filter(function ($d) use ($qualifichePivot, $nomiQualifiche, $qualificaTesto) {
                $ids = $qualifichePivot[$d->idDipendente] ?? [];
                $nomi = collect($ids)->map(fn($idQ) => strtolower($nomiQualifiche[$idQ] ?? ''));
                return $nomi->contains(fn($n) => str_contains($n, $qualificaTesto));
            });
        }
    }

    // Filtro per idQualifica
    if ($idQualifica) {
        $dipendenti = $dipendenti->filter(function ($d) use ($qualifichePivot, $idQualifica) {
            $ids = $qualifichePivot[$d->idDipendente] ?? [];
            return in_array($idQualifica, $ids, true);
        });
    }

    // Costi per anno
    $costi = CostiPersonale::getAllByAnno($anno)->keyBy('idDipendente');

    // Percentuali per qualifica selezionata (idDipendente => %)
    $percSelezionata = $idQualifica
        ? CostiMansioni::getPercentualiByQualifica($idQualifica, $anno)
        : [];

    // Ripartizioni ore per convenzioni
    $ripartizioni = RipartizionePersonale::getAll($anno, $user)->groupBy('idDipendente');

    // Convenzioni per colonne dinamiche
    $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
        ->sortBy('idConvenzione')
        ->values();

    $labels = $convenzioni
        ->pluck('Convenzione', 'idConvenzione')
        ->mapWithKeys(fn($nome, $id) => ["C$id" => $nome])
        ->toArray();

    $rows = [];
    $totali = ['Retribuzioni' => 0, 'OneriSocialiInps' => 0, 'OneriSocialiInail' => 0, 'TFR' => 0, 'Consulenze' => 0, 'Totale' => 0];
    $totPerConv = [];

    foreach ($dipendenti as $d) {
        $id = $d->idDipendente;
        $c = $costi->get($id) ?? (object)[];

        // --- base (schema nuovo o vecchio) ---
        $retribuzioni_base = (float)($c->Retribuzioni ?? 0);
        // Fallback schema vecchio: se manca INPS ma c'è OneriSociali unico, consideralo lato INPS
        $inps_base   = (float)($c->OneriSocialiInps ?? ($c->OneriSociali ?? 0));
        $inail_base  = (float)($c->OneriSocialiInail ?? 0);
        $tfr_base    = (float)($c->TFR ?? 0);
        $cons_base   = (float)($c->Consulenze ?? 0);

        // --- diretti (tutti opzionali) ---
        $retribuzioni_dir = (float)($c->costo_diretto_Retribuzioni ?? 0);
        // Fallback diretto: se non esiste *_Inps usa costo_diretto_OneriSociali (vecchio schema)
        $inps_dir   = (float)($c->costo_diretto_OneriSocialiInps ?? ($c->costo_diretto_OneriSociali ?? 0));
        $inail_dir  = (float)($c->costo_diretto_OneriSocialiInail ?? 0);
        $tfr_dir    = (float)($c->costo_diretto_TFR ?? 0);
        $cons_dir   = (float)($c->costo_diretto_Consulenze ?? 0);

        // --- somma base + diretto ---
        $retribuzioni     = $retribuzioni_base + $retribuzioni_dir;
        $OneriSocialiInps   = $inps_base + $inps_dir;
        $OneriSocialiInail  = $inail_base + $inail_dir;
        $tfr              = $tfr_base + $tfr_dir;
        $consulenze       = $cons_base + $cons_dir;

        // --- coefficiente mansione (se filtro specifico) ---
        $coeff = 1.0;
        if ($idQualifica) {
            $idsQ = $qualifichePivot[$id] ?? [];
            if (count($idsQ) > 1) {
                $pct = (float)($percSelezionata[$id] ?? 0);
                $coeff = max(0.0, $pct / 100.0);
            }
        }

        if ($idQualifica) {
            $retribuzioni     *= $coeff;
            $OneriSocialiInps *= $coeff;
            $OneriSocialiInail*= $coeff;
            $tfr              *= $coeff;
            $consulenze       *= $coeff;
        }

        $totale = $retribuzioni + $OneriSocialiInps + $OneriSocialiInail + $tfr + $consulenze;

        // nomi qualifica per riga
        $idsQ = $qualifichePivot[$id] ?? [];
        $nomiQ = collect($idsQ)->map(fn($iq) => $nomiQualifiche[$iq] ?? null)->filter()->values()->implode(', ');

        $r = [
            'idDipendente'    => $id,
            'Dipendente'      => trim("{$d->DipendenteCognome} {$d->DipendenteNome}"),
            'Qualifica'       => $nomiQ,
            'Contratto'       => $d->ContrattoApplicato,
            'Retribuzioni'    => round($retribuzioni, 2),
            'OneriSocialiInps'=> round($OneriSocialiInps, 2),
            'OneriSocialiInail'=> round($OneriSocialiInail, 2),
            'TFR'             => round($tfr, 2),
            'Consulenze'      => round($consulenze, 2),
            'Totale'          => round($totale, 2),
            'is_totale'       => false,
        ];

        // accumula totali
        foreach ($totali as $k => $_) {
            $totali[$k] += $r[$k];
        }

        // ripartizione per convenzione
        $rip = $ripartizioni->get($id) ?? collect();
        $oreTot = $rip->sum('OreServizio');

        foreach ($convenzioni as $conv) {
            $convKey = "C{$conv->idConvenzione}";
            $entry = $rip->firstWhere('idConvenzione', $conv->idConvenzione);
            $percent = ($oreTot > 0 && $entry) ? round($entry->OreServizio / $oreTot * 100, 2) : 0;
            $importo = round(($percent / 100) * $totale, 2);

            $r["{$convKey}_percent"] = $percent;
            $r["{$convKey}_importo"] = $importo;

            $totPerConv["{$convKey}_importo"] = ($totPerConv["{$convKey}_importo"] ?? 0) + $importo;
            $totPerConv["{$convKey}_percent"] = 0; // non si somma in % sul totale (placeholder)
        }

        $rows[] = $r;
    }

    // riga totale
    $rows[] = array_merge([
        'idDipendente' => null,
        'Dipendente'   => 'TOTALE',
        'Qualifica'    => '',
        'Contratto'    => '',
        'is_totale'    => true,
    ], array_map(fn($v) => round($v, 2), $totali), $totPerConv);

    return response()->json([
        'data'   => $rows,
        'labels' => $labels,
    ]);
}


    public function salva(Request $request) {
        $data = $request->validate([
            'idDipendente'               => 'required|integer',
            'Retribuzioni'               => 'required|numeric',
            'OneriSocialiInps'           => 'nullable|numeric',
            'OneriSocialiInail'          => 'nullable|numeric',
            'TFR'                        => 'required|numeric',
            'Consulenze'                 => 'required|numeric',

            'costo_diretto_Retribuzioni'      => 'nullable|numeric',
            'costo_diretto_OneriSocialiInps'  => 'nullable|numeric',
            'costo_diretto_OneriSocialiInail' => 'nullable|numeric',
            'costo_diretto_TFR'               => 'nullable|numeric',
            'costo_diretto_Consulenze'        => 'nullable|numeric',
        ]);

        $data['idAnno'] = session('anno_riferimento', now()->year);

        // normalizza INPS/INAIL anche se arriva lo schema vecchio
        $inps       = (float)($data['OneriSocialiInps']           ?? 0);
        $inail      = (float)($data['OneriSocialiInail']          ?? 0);
        $inps_dir   = (float)($data['costo_diretto_OneriSocialiInps']  ?? 0);
        $inail_dir  = (float)($data['costo_diretto_OneriSocialiInail'] ?? 0);

        $totRetribuzioni = (float)$data['Retribuzioni'] + (float)($data['costo_diretto_Retribuzioni'] ?? 0);
        $totInps         = $inps  + $inps_dir;
        $totInail        = $inail + $inail_dir;
        $totTfr          = (float)$data['TFR'] + (float)($data['costo_diretto_TFR'] ?? 0);
        $totConsulenze   = (float)$data['Consulenze'] + (float)($data['costo_diretto_Consulenze'] ?? 0);

        $data['Totale'] = $totRetribuzioni + $totInps + $totInail + $totTfr + $totConsulenze;

        CostiPersonale::updateOrInsert($data);

        return response()->json([
            'success' => true,
            'message' => 'Dati salvati correttamente.'
        ]);
    }

    public function edit($idDipendente) {
        $anno = session('anno_riferimento', now()->year);

        $record = CostiPersonale::getWithDipendente($idDipendente, $anno);

        $qualifiche = DB::table('dipendenti_qualifiche as dq')
            ->join('qualifiche as q', 'q.id', '=', 'dq.idQualifica')
            ->where('dq.idDipendente', $idDipendente)
            ->select('q.id', 'q.nome')
            ->orderBy('q.nome')->get();

        $percentuali = CostiMansioni::getPercentuali($idDipendente, $anno);

        return view('ripartizioni.costi_personale.edit', compact('record', 'anno', 'qualifiche', 'percentuali'));
    }

    public function update(Request $request, $idDipendente) {
        $data = $request->validate([
            'Retribuzioni'               => 'required|numeric',
            'OneriSocialiInps'           => 'nullable|numeric',
            'OneriSocialiInail'          => 'nullable|numeric',
            'TFR'                        => 'required|numeric',
            'Consulenze'                 => 'required|numeric',

            'costo_diretto_Retribuzioni'      => 'nullable|numeric',
            'costo_diretto_OneriSocialiInps'  => 'nullable|numeric',
            'costo_diretto_OneriSocialiInail' => 'nullable|numeric',
            'costo_diretto_TFR'               => 'nullable|numeric',
            'costo_diretto_Consulenze'        => 'nullable|numeric',

            'percentuali'    => 'array',
            'percentuali.*'  => 'nullable|numeric|min:0|max:100',
        ]);

        $data['idDipendente'] = $idDipendente;
        $data['idAnno'] = session('anno_riferimento', now()->year);

        $inps       = (float)($data['OneriSocialiInps']           ?? 0);
        $inail      = (float)($data['OneriSocialiInail']          ?? 0);
        $inps_dir   = (float)($data['costo_diretto_OneriSocialiInps']  ?? 0);
        $inail_dir  = (float)($data['costo_diretto_OneriSocialiInail'] ?? 0);

        $totRetribuzioni = (float)$data['Retribuzioni'] + (float)($data['costo_diretto_Retribuzioni'] ?? 0);
        $totInps         = $inps  + $inps_dir;
        $totInail        = $inail + $inail_dir;
        $totTfr          = (float)$data['TFR'] + (float)($data['costo_diretto_TFR'] ?? 0);
        $totConsulenze   = (float)$data['Consulenze'] + (float)($data['costo_diretto_Consulenze'] ?? 0);

        $data['Totale'] = $totRetribuzioni + $totInps + $totInail + $totTfr + $totConsulenze;

        CostiPersonale::updateOrInsert($data);

        $qualificheDip = DB::table('dipendenti_qualifiche')->where('idDipendente', $idDipendente)->count();
        if ($qualificheDip > 1) {
            $perc = (array)($request->input('percentuali', []));
            $sum = array_reduce($perc, fn($c, $v) => $c + (float)$v, 0.0);
            if (abs($sum - 100.0) > 0.01) {
                return back()
                    ->withErrors(['percentuali' => 'La somma delle percentuali deve essere esattamente 100%.'])
                    ->withInput();
            }
            CostiMansioni::savePercentuali($idDipendente, $data['idAnno'], $perc);
        }

        return redirect()->route('ripartizioni.personale.costi.index')->with('success', 'Dati aggiornati.');
    }

    public function show($idDipendente) {
        $anno = session('anno_riferimento', now()->year);
        $record = CostiPersonale::getByDipendente($idDipendente, $anno);

        if (!$record) {
            $record = CostiPersonale::createEmptyRecord($idDipendente, $anno);
        }

        return view('ripartizioni.costi_personale.show', compact('record', 'anno'));
    }
}
