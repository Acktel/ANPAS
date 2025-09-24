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

class CostiPersonaleController extends Controller {
    public function index(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $selectedAssoc = session('associazione_selezionata') ?? $request->query('idAssociazione');

        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

        //Ora le qualifiche arrivano dalla tabella (id + nome), non più dedotte dai dipendenti
        $qualifiche = DB::table('qualifiche')
            ->select('id', 'nome')
            ->orderBy('ordinamento')
            ->get();

        return view('ripartizioni.costi_personale.index', compact('anno', 'selectedAssoc', 'associazioni', 'qualifiche'));
    }

    public function getData() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        // Preferito: filtro per ID qualifica (numerico).
        $idQualifica = request()->query('idQualifica');
        $idQualifica = is_numeric($idQualifica) ? (int)$idQualifica : null;

        // Legacy: filtro testuale (se presente e non c'è id)
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
        $qualifichePivot = \DB::table('dipendenti_qualifiche')
            ->select('idDipendente', 'idQualifica')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($rows) => $rows->pluck('idQualifica')->map(fn($v) => (int)$v)->toArray());

        // Mappa id->nome
        $nomiQualifiche = \DB::table('qualifiche')->pluck('nome', 'id');

        // Se serve, risolvi il filtro testuale a un singolo id
        if (!$idQualifica && $qualificaTesto !== '') {
            $matching = \DB::table('qualifiche')
                ->whereRaw('LOWER(nome) LIKE ?', ['%' . $qualificaTesto . '%'])
                ->pluck('id')
                ->map(fn($v) => (int)$v)
                ->values();

            if ($matching->count() === 1) {
                $idQualifica = (int)$matching->first();
            } else {
                // fallback: filtro per testo senza ripartire (mostro intero)
                $dipendenti = $dipendenti->filter(function ($d) use ($qualifichePivot, $nomiQualifiche, $qualificaTesto) {
                    $ids = $qualifichePivot[$d->idDipendente] ?? [];
                    $nomi = collect($ids)->map(fn($idQ) => strtolower($nomiQualifiche[$idQ] ?? ''));
                    return $nomi->contains(fn($n) => str_contains($n, $qualificaTesto));
                });
            }
        }

        // Se ho idQualifica → filtro i dipendenti che ce l'hanno
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
        $totali = ['Retribuzioni' => 0, 'OneriSociali' => 0, 'TFR' => 0, 'Consulenze' => 0, 'Totale' => 0];
        $totPerConv = [];

        foreach ($dipendenti as $d) {
            $id = $d->idDipendente;
            $c = $costi->get($id);

            // base (importi pieni)
            $retribuzioni = (float)($c->Retribuzioni ?? 0);
            $oneriSociali = (float)($c->OneriSociali ?? 0);
            $tfr          = (float)($c->TFR ?? 0);
            $consulenze   = (float)($c->Consulenze ?? 0);

            // coefficiente di ripartizione se filtriamo per una qualifica specifica
            $coeff = 1.0;
            if ($idQualifica) {
                $idsQ = $qualifichePivot[$id] ?? [];
                if (count($idsQ) <= 1) {
                    // unica mansione → 100%
                    $coeff = 1.0;
                } else {
                    // multi-mansione → usa % salvata (se manca, 0%)
                    $pct = (float)($percSelezionata[$id] ?? 0);
                    $coeff = max(0.0, $pct / 100.0);
                }
            }

            // applico coefficiente solo se filtro per mansione
            if ($idQualifica) {
                $retribuzioni *= $coeff;
                $oneriSociali *= $coeff;
                $tfr          *= $coeff;
                $consulenze   *= $coeff;
            }

            $totale = $retribuzioni + $oneriSociali + $tfr + $consulenze;

            // nomi qualifica per riga
            $idsQ = $qualifichePivot[$id] ?? [];
            $nomiQ = collect($idsQ)->map(fn($iq) => $nomiQualifiche[$iq] ?? null)->filter()->values()->implode(', ');

            $r = [
                'idDipendente' => $id,
                'Dipendente'   => trim("{$d->DipendenteCognome} {$d->DipendenteNome}"),
                'Qualifica'    => $nomiQ,
                'Contratto'    => $d->ContrattoApplicato,
                'Retribuzioni' => round($retribuzioni, 2),
                'OneriSociali' => round($oneriSociali, 2),
                'TFR'          => round($tfr, 2),
                'Consulenze'   => round($consulenze, 2),
                'Totale'       => round($totale, 2),
                'is_totale'    => false,
            ];

            foreach ($totali as $k => $v) {
                $totali[$k] += $r[$k];
            }

            $rip = $ripartizioni->get($id, collect());
            $oreTot = $rip->sum('OreServizio');

            foreach ($convenzioni as $conv) {
                $convKey = "C{$conv->idConvenzione}";
                $entry = $rip->firstWhere('idConvenzione', $conv->idConvenzione);
                $percent = ($oreTot > 0 && $entry) ? round($entry->OreServizio / $oreTot * 100, 2) : 0;
                $importo = round(($percent / 100) * $totale, 2);

                $r["{$convKey}_percent"] = $percent;
                $r["{$convKey}_importo"] = $importo;

                $totPerConv["{$convKey}_importo"] = ($totPerConv["{$convKey}_importo"] ?? 0) + $importo;
                $totPerConv["{$convKey}_percent"] = 0;
            }

            $rows[] = $r;
        }

        $rows[] = array_merge([
            'idDipendente' => null,
            'Dipendente'   => 'TOTALE',
            'Qualifica'    => '',
            'Contratto'    => '',
            'is_totale'    => true,
        ], array_map(fn($v) => round($v, 2), $totali), $totPerConv);

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
        ]);

        $data['idAnno'] = session('anno_riferimento', now()->year);
        $data['Totale'] =
            (float)$data['Retribuzioni'] +
            (float)$data['OneriSociali'] +
            (float)$data['TFR'] +
            (float)$data['Consulenze'];

        CostiPersonale::updateOrInsert($data);

        return response()->json([
            'success' => true,
            'message' => 'Dati salvati correttamente.'
        ]);
    }

    // CostiPersonaleController@edit
    public function edit($idDipendente) {
        $anno = session('anno_riferimento', now()->year);

        $record = CostiPersonale::getWithDipendente($idDipendente, $anno);

        // qualifiche del dipendente (id + nome)
        $qualifiche = DB::table('dipendenti_qualifiche as dq')
            ->join('qualifiche as q', 'q.id', '=', 'dq.idQualifica')
            ->where('dq.idDipendente', $idDipendente)
            ->select('q.id', 'q.nome')
            ->orderBy('q.nome')->get();

        // percentuali salvate per anno (array: [idQualifica => percentuale])
        $percentuali = CostiMansioni::getPercentuali($idDipendente, $anno);

        return view('ripartizioni.costi_personale.edit', compact('record', 'anno', 'qualifiche', 'percentuali'));
    }



    public function update(Request $request, $idDipendente) {
        $data = $request->validate([
            'Retribuzioni'   => 'required|numeric',
            'OneriSociali'   => 'required|numeric',
            'TFR'            => 'required|numeric',
            'Consulenze'     => 'required|numeric',
            // array opzionale di percentuali per idQualifica
            'percentuali'    => 'array',
            'percentuali.*'  => 'nullable|numeric|min:0|max:100',
        ]);

        $data['idDipendente'] = $idDipendente;
        $data['idAnno'] = session('anno_riferimento', now()->year);
        $data['Totale'] =
            (float)$data['Retribuzioni'] +
            (float)$data['OneriSociali'] +
            (float)$data['TFR'] +
            (float)$data['Consulenze'];

        // Salvo i costi base
        CostiPersonale::updateOrInsert($data);

        // Se ci sono più di una qualifica, salvo la ripartizione percentuale
        $qualificheDip = \DB::table('dipendenti_qualifiche')->where('idDipendente', $idDipendente)->count();
        if ($qualificheDip > 1) {
            $perc = (array)($request->input('percentuali', []));
            // somma deve essere 100 (tolleranza 0.01)
            $sum = array_reduce($perc, fn($c, $v) => $c + (float)$v, 0.0);
            if (abs($sum - 100.0) > 0.01) {
                return back()
                    ->withErrors(['percentuali' => 'La somma delle percentuali deve essere esattamente 100%.'])
                    ->withInput();
            }
            CostiMansioni::savePercentuali($idDipendente, $data['idAnno'], $perc);
        } else {
            // se torna a una sola mansione, puoi opzionalmente pulire le percentuali
            // CostiMansioni::deleteAllFor($idDipendente, $data['idAnno']);
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
