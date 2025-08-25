<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;
use App\Models\CostoDiretto;

class DistintaImputazioneCostiController extends Controller
{
    public function index(Request $request)
    {
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

    $costiRipartiti = collect(RipartizioneCostiService::calcolaTabellaTotale($selectedAssoc, $anno));
    $bilancioCalcolatoPerVoce = $costiRipartiti->keyBy(fn($r) => strtoupper(trim($r['voce'] ?? '')));

    $costiDiretti = CostoDiretto::where('idAssociazione', $selectedAssoc)
        ->where('idAnno', $anno)
        ->get();

    $idRiepilogo = DB::table('riepiloghi')
        ->where('idAssociazione', $selectedAssoc)
        ->where('idAnno', $anno)
        ->value('idRiepilogo');

    $vociRiepilogo = DB::table('riepilogo_dati')
        ->select('descrizione', 'idTipologiaRiepilogo')
        ->where('idRiepilogo', $idRiepilogo)
        ->distinct()
        ->get();

    $tipologiaToSezione = [5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11];

    $voceToSezioneLegacy = [
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
        'AMMORTAMENTO ATTREZZATURA SANITARIA' => 9
    ];

    $vociTotali = collect($vociRiepilogo)
        ->mapWithKeys(fn($v) => [strtoupper(trim($v->descrizione)) => $tipologiaToSezione[$v->idTipologiaRiepilogo] ?? null])
        ->merge($voceToSezioneLegacy)
        ->filter();

    $righe = [];

    foreach ($vociTotali as $voce => $sezione) {
        $voceUpper = strtoupper(trim($voce));
        $voceRipartita = $bilancioCalcolatoPerVoce->get($voceUpper);
        $direttaTotale = $costiDiretti->filter(fn($c) => strtoupper(trim($c->voce)) === $voceUpper)->sum('costo');

        if ($voceRipartita) {
            $bilancio = floatval($voceRipartita['totale']);
            $calcolata = true;
        } else {
            $consuntivo = $costiDiretti->first(fn($c) => strtoupper($c->voce) === $voceUpper);
            $bilancio = $consuntivo ? floatval($consuntivo->bilancio_consuntivo) : 0;
            $calcolata = false;
        }

        $riga = [
            'voce' => $voceUpper,
            'bilancio' => floatval($bilancio),
            'diretta' => 0,
            'totale' => 0,
            'sezione_id' => $sezione,
        ];

        $indirettiConvenzione = [];

        foreach ($convenzioni as $idConv => $nomeConv) {
            $diretto = $costiDiretti->first(fn($c) => $c->idConvenzione == $idConv && strtoupper($c->voce) === $voceUpper);
            $valoreDiretto = $diretto ? floatval($diretto->costo) : 0;
            $valoreIndiretto = 0;

            if (!$calcolata && $bilancio > 0 && $direttaTotale > 0) {
                $differenza = $bilancio - $direttaTotale;
                $percentuale = $valoreDiretto / $direttaTotale;
                $valoreIndiretto = $differenza > 0 ? round($differenza * $percentuale, 2) : 0;
            } elseif ($calcolata) {
                $valoreIndiretto = floatval($voceRipartita[$nomeConv] ?? 0);
            }

            $riga[$nomeConv] = [
                'diretti' => $valoreDiretto,
                'indiretti' => $valoreIndiretto
            ];

            $riga['diretta'] += $valoreDiretto;
            $indirettiConvenzione[] = $valoreIndiretto;
        }

        $riga['totale'] = $calcolata
            ? $riga['diretta'] + array_sum($indirettiConvenzione)
            : array_sum($indirettiConvenzione);

        $righe[] = $riga;
    }

    return response()->json([
        'data' => $righe,
        'convenzioni' => array_values($convenzioni)
    ]);
}


    public function salvaCostoDiretto(Request $request)
    {
        $request->validate([
            'idAssociazione' => 'required|integer',
            'idAnno' => 'required|integer',
            'idConvenzione' => 'required|integer',
            'costo' => 'required|numeric',
        ]);

        CostoDiretto::updateOrCreate(
            [
                'idAssociazione' => $request->idAssociazione,
                'idAnno' => $request->idAnno,
                'idConvenzione' => $request->idConvenzione,
                'voce' => $request->voce
            ],
            [
                'costo' => $request->costo,
                'bilancio_consuntivo' => $request->bilancio_consuntivo ?? 0
            ]
        );

        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,IdAssociazione',
            'idAnno' => 'required|integer|exists:anni,idAnno',
            'idConvenzione' => 'required|integer|exists:convenzioni,idConvenzione',
            'idSezione' => 'required|integer',
            'voce' => 'required|string|max:255',
            'costo' => 'required|numeric|min:0',
            'bilancio_consuntivo' => 'nullable|numeric|min:0',
        ]);

        DB::table('costi_diretti')->updateOrInsert(
            [
                'idAssociazione' => $validated['idAssociazione'],
                'idAnno' => $validated['idAnno'],
                'idConvenzione' => $validated['idConvenzione'],
                'idSezione' => $validated['idSezione'],
                'voce' => $validated['voce'],
            ],
            [
                'costo' => $validated['costo'],
                'bilancio_consuntivo' => $validated['bilancio_consuntivo'] ?? 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return redirect()->route('distinta.imputazione.index')
            ->with('success', 'Costo diretto aggiunto o aggiornato con successo.');
    }
}
