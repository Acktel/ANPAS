<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Convenzione;
use App\Models\RipartizionePersonale;
use App\Models\Dipendente;

class RipartizionePersonaleController extends Controller {
    public function index(Request $request) {
        $anno = session('anno_riferimento', now()->year);

        // Corretto: leggi prima dalla sessione, poi da query string (fallback)
        $selectedAssoc = session('associazione_selezionata') ?? $request->query('idAssociazione');
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');
        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

        return view('ripartizioni.personale.index', compact('anno', 'selectedAssoc', 'associazioni'));
    }
    
    public function getData(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $selectedAssoc = $request->query('idAssociazione');

        $assocId = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? ($selectedAssoc ?: null)
            : $user->IdAssociazione;

        $dipendenti = Dipendente::getAutistiEBarellieri($anno, $assocId);

        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')
            ->values();

        $raw = RipartizionePersonale::getAll($anno, $user, $assocId)
            ->groupBy('idDipendente');

        $labels = $convenzioni
            ->pluck('Convenzione', 'idConvenzione')
            ->mapWithKeys(fn($name, $id) => ['c' . $id => $name])
            ->toArray();

        $rows    = [];
        $totOre  = 0;
        $totCols = array_fill_keys(array_keys($labels), 0);

        foreach ($dipendenti as $d) {
            $fullName = "$d->DipendenteNome $d->DipendenteCognome";
            $servizi = $raw->get($d->idDipendente, collect());
            $oreTot  = $servizi->sum('OreServizio');
            $totOre += $oreTot;

            $r = [
                'is_totale'    => 0,
                'idDipendente' => $d->idDipendente,
                'Associazione' => $d->Associazione,
                'FullName'     => $fullName,
                'OreTotali'    => $oreTot,
            ];

            foreach ($convenzioni as $c) {
                $k   = 'c' . $c->idConvenzione;
                $ore = $servizi->firstWhere('idConvenzione', $c->idConvenzione)->OreServizio ?? 0;
                $perc = $oreTot > 0 ? round($ore / $oreTot * 100, 2) : 0;

                $r["{$k}_ore"]     = $ore;
                $r["{$k}_percent"] = $perc;
                $totCols[$k]      += $ore;
            }

            $rows[] = $r;
        }

        $totalRow = [
            'is_totale'    => -1,
            'idDipendente' => null,
            'Associazione' => 'TOTALE',
            'FullName'     => '',
            'OreTotali'    => $totOre,
        ];
        foreach (array_keys($labels) as $k) {
            $ore  = $totCols[$k];
            $perc = $totOre > 0 ? round($ore / $totOre * 100, 2) : 0;
            $totalRow["{$k}_ore"]     = $ore;
            $totalRow["{$k}_percent"] = $perc;
        }
        $rows[] = $totalRow;

        return response()->json([
            'data'   => $rows,
            'labels' => $labels,
        ]);
    }

    public function create() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')
            ->values();

        return view('ripartizioni.personale.create', compact('convenzioni', 'anno'));
    }

    public function store(Request $request) {
        $anno = session('anno_riferimento', now()->year);

        foreach ($request->input('ore', []) as $idDip => $convs) {
            foreach ($convs as $idConv => $ore) {
                RipartizionePersonale::upsert(
                    (int)$idDip,
                    (int)$idConv,
                    (float)$ore
                );
            }
        }

        return redirect()
            ->route('ripartizioni.personale.index', ['idAssociazione' => $request->input('idAssociazione')])
            ->with('success', 'Ore salvate correttamente.');
    }

    public function edit(int $idDipendente, Request $request) {
        $user  = Auth::user();
        $anno  = session('anno_riferimento', now()->year);
        $selectedAssoc = $request->query('idAssociazione');

        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? ($selectedAssoc ?: null)
            : $user->IdAssociazione;

        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')
            ->values();

        $record = RipartizionePersonale::getByDipendente($idDipendente, $anno)
            ->keyBy('idConvenzione');

        return view('ripartizioni.personale.edit', compact(
            'idDipendente',
            'convenzioni',
            'record',
            'anno',
            'idAssociazione'
        ));
    }


    public function update(Request $request, int $idDipendente) {
        $anno = session('anno_riferimento', now()->year);
        RipartizionePersonale::deleteByDipendente($idDipendente);

        foreach ($request->input('ore', [])[$idDipendente] as $idConv => $ore) {
            RipartizionePersonale::upsert(
                $idDipendente,
                (int)$idConv,
                (float)$ore
            );
        }

        return redirect()
            ->route('ripartizioni.personale.index', ['idAssociazione' => $request->input('idAssociazione')])
            ->with('success', 'Aggiornamento avvenuto con successo.');
    }

    public function destroy(int $idDipendente) {
        RipartizionePersonale::deleteByDipendente($idDipendente);

        return back()->with('success', 'Dati eliminati.');
    }

    public function show(int $idDipendente) {
        $user  = Auth::user();
        $anno  = session('anno_riferimento', now()->year);

        $convenzioni = Convenzione::getByAnno($anno, $user)
            ->sortBy('idConvenzione')
            ->values();

        $record = RipartizionePersonale::getByDipendente($idDipendente, $anno)
            ->keyBy('idConvenzione');

        $totOre = $record->sum('OreServizio');

        return view('ripartizioni.personale.show', compact(
            'idDipendente',
            'anno',
            'convenzioni',
            'record',
            'totOre'
        ));
    }
}
