<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Dipendente;
use App\Models\CostiPersonale;
use App\Models\Convenzione;
use Illuminate\Support\Facades\DB;
use App\Models\RipartizionePersonale;

class CostiPersonaleController extends Controller {
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        return view('ripartizioni.costi_personale.index', compact('anno'));
    }

    public function getData() {
    $anno = session('anno_riferimento', now()->year);
    $user = Auth::user();

    $dipendenti = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
        ? Dipendente::getAll($anno)
        : Dipendente::getByAssociazione($user->IdAssociazione, $anno);

    $filtrati = $dipendenti->filter(function ($d) {
        $q = strtolower($d->Qualifica ?? '');
        $liv = strtolower($d->LivelloMansione ?? '');
        return str_contains($q, 'autista') || str_contains($q, 'barelliere') || str_contains($liv, 'c4');
    });

    $costi = DB::table('costi_personale')
        ->where('idAnno', $anno)
        ->get()
        ->keyBy('idDipendente');

    $raw = RipartizionePersonale::getAll($anno, $user)->groupBy('idDipendente');

    $associazioneId = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
        ? null
        : $user->IdAssociazione;

    $convenzioni = Convenzione::getByAssociazioneAnno($associazioneId, $anno)
        ->sortBy('idConvenzione')
        ->values();

    $labels = $convenzioni
        ->pluck('Convenzione', 'idConvenzione')
        ->mapWithKeys(fn($nome, $id) => ["C$id" => $nome])
        ->toArray();

    $rows = [];
    $totali = [
        'Retribuzioni' => 0,
        'OneriSociali' => 0,
        'TFR' => 0,
        'Consulenze' => 0,
        'Totale' => 0,
    ];
    $totPerConv = []; // es: ['C1' => 1234.00, 'C2' => 567.00, ...]

    foreach ($filtrati as $d) {
        $id = $d->idDipendente;
        $c = $costi[$id] ?? null;

        $retribuzioni = (float)($c->Retribuzioni ?? 0);
        $oneriSociali = (float)($c->OneriSociali ?? 0);
        $tfr = (float)($c->TFR ?? 0);
        $consulenze = (float)($c->Consulenze ?? 0);
        $totale = $retribuzioni + $oneriSociali + $tfr + $consulenze;

        // Inizializza riga
        $r = [
            'idDipendente' => $id,
            'Dipendente'   => trim("{$d->DipendenteCognome} {$d->DipendenteNome}"),
            'Qualifica'    => $d->Qualifica,
            'Contratto'    => $d->ContrattoApplicato,
            'Retribuzioni' => $retribuzioni,
            'OneriSociali' => $oneriSociali,
            'TFR'          => $tfr,
            'Consulenze'   => $consulenze,
            'Totale'       => $totale,
            'is_totale'    => false,
        ];

        foreach ($totali as $k => $v) {
            $totali[$k] += $r[$k];
        }

        $ripartizioni = $raw->get($id, collect());
        $oreTot = $ripartizioni->sum('OreServizio');

        foreach ($convenzioni as $conv) {
            $convKey = "C{$conv->idConvenzione}";
            $entry = $ripartizioni->firstWhere('idConvenzione', $conv->idConvenzione);
            $percent = ($oreTot > 0 && $entry) ? round($entry->OreServizio / $oreTot * 100, 2) : 0;
            $importo = round(($percent / 100) * $totale, 2);

            $r["{$convKey}_percent"] = $percent;
            $r["{$convKey}_importo"] = $importo;

            $totPerConv["{$convKey}_importo"] = ($totPerConv["{$convKey}_importo"] ?? 0) + $importo;
            $totPerConv["{$convKey}_percent"] = 0; // non usato nei totali
        }

        $rows[] = $r;
    }

    // Riga totale
    $rows[] = array_merge([
        'idDipendente' => null,
        'Dipendente' => 'TOTALE',
        'Qualifica' => '',
        'Contratto' => '',
        'is_totale' => true,
    ], $totali, $totPerConv);

    return response()->json([
        'data' => $rows,
        'labels' => $labels,
    ]);
}


    public function salva(Request $request) {
        $data = $request->validate([
            'idDipendente'   => 'required|integer',
            'Retribuzioni'   => 'required|numeric',
            'OneriSociali'   => 'required|numeric',
            'TFR'            => 'required|numeric',
            'Consulenze'     => 'required|numeric',
            'Totale'         => 'required|numeric',
        ]);

        $data['idAnno'] = session('anno_riferimento', now()->year);

        $record = CostiPersonale::updateOrCreate(
            ['idDipendente' => $data['idDipendente'], 'idAnno' => $data['idAnno']],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Dati salvati correttamente.',
            'record'  => $record
        ]);
    }

    public function edit($idDipendente) {
        $anno = session('anno_riferimento', now()->year);
        $record = CostiPersonale::getByDipendente($idDipendente, $anno);
        return view('ripartizioni.costi_personale.edit', compact('record', 'anno'));
    }

    public function update(Request $request, $id) {
        $validated = $request->validate([
            'Retribuzioni' => 'required|numeric',
            'OneriSociali' => 'required|numeric',
            'TFR' => 'required|numeric',
            'Consulenze' => 'required|numeric',
            'Totale' => 'required|numeric',
        ]);

        $costo = CostiPersonale::findOrFail($id);
        $costo->update($validated);

        return redirect()->route('ripartizioni.personale.costi.index')
            ->with('success', 'Costo aggiornato correttamente.');
    }
}
