<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Convenzione;
use App\Models\RipartizionePersonale;
use App\Models\Dipendente;
use App\Services\RipartizioneCostiService;

class RipartizionePersonaleController extends Controller {
    public function index(Request $request) {
        $anno = session('anno_riferimento', now()->year);

        // chiave dedicata a questa pagina
        $sessionKey = 'associazione_selezionata';

        // Se arrivo con un parametro, lo salvo
        if ($request->has('idAssociazione')) {
            session([$sessionKey => $request->query('idAssociazione')]);
        }

        // Leggo dalla sessione, con fallback al parametro in query (se non ho ancora salvato)
        $selectedAssoc = session($sessionKey, $request->query('idAssociazione'));

        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');
        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

        return view('ripartizioni.personale.index', compact('anno', 'selectedAssoc', 'associazioni'));
    }

    public function getData(Request $request) {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);
        $sessionKey = 'associazione_selezionata';

        if ($request->has('idAssociazione')) {
            session([$sessionKey => $request->query('idAssociazione')]);
        }
        $selectedAssoc = session($sessionKey, $request->query('idAssociazione'));

        $assocId = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? ($selectedAssoc ?: null)
            : $user->IdAssociazione;

        $dipendenti  = Dipendente::getAutistiEBarellieri($anno, $assocId);
        $convenzioni = Convenzione::getByAssociazioneAnno($assocId, $anno)
            ->sortBy('idConvenzione')->values();

        $raw = RipartizionePersonale::getAll($anno, $user, $assocId)->groupBy('idDipendente');

        $labels = $convenzioni
            ->pluck('Convenzione', 'idConvenzione')
            ->mapWithKeys(fn($name, $id) => ['c' . $id => $name])
            ->toArray();

        $rows    = [];
        $totOre  = 0;
        $totCols = array_fill_keys(array_keys($labels), 0);

        foreach ($dipendenti as $d) {
            $fullName = "$d->DipendenteNome $d->DipendenteCognome";
            $servizi  = $raw->get($d->idDipendente, collect());
            $oreTot   = $servizi->sum('OreServizio');
            $totOre  += $oreTot;

            $r = [
                'is_totale'    => 0,
                'idDipendente' => $d->idDipendente,
                'Associazione' => $d->Associazione,
                'FullName'     => $fullName,
                'OreTotali'    => $oreTot,
            ];

            foreach ($convenzioni as $c) {
                $k    = 'c' . $c->idConvenzione;
                $ore  = $servizi->firstWhere('idConvenzione', $c->idConvenzione)->OreServizio ?? 0;
                $perc = $oreTot > 0 ? round($ore / $oreTot * 100, 2) : 0;

                $r["{$k}_ore"]     = $ore;
                $r["{$k}_percent"] = $perc;
                $totCols[$k]      += $ore;
            }

            $rows[] = $r;
        }

        // riga totale per la tabella ore
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

        $distinta = RipartizioneCostiService::distintaImputazioneData((int)$assocId, (int)$anno);
        $costoPersonaleTotale = 0.0;
        $costoPersonalePerConv = []; // [idConvenzione => importo_indiretti+diretti] oppure solo 'indiretti'

        if (!empty($distinta['data'])) {
            foreach ($distinta['data'] as $riga) {
                if ((int)($riga['idVoceConfig'] ?? 0) === 6001) {
                    // totale già calcolato nella riga
                    $costoPersonaleTotale = (float)($riga['totale'] ?? 0);

                    // per convenzione i campi sono per NOME convenzione
                    foreach ($convenzioni as $conv) {
                        $nome = $conv->Convenzione;
                        $idC  = (int)$conv->idConvenzione;
                        // somma diretti+indiretti per comodo (o prendi solo gli 'indiretti' se preferisci)
                        $dir = (float)($riga[$nome]['diretti']   ?? 0);
                        $ind = (float)($riga[$nome]['indiretti'] ?? 0);
                        $costoPersonalePerConv[$idC] = round($dir + $ind, 2);
                    }
                    break;
                }
            }
        }

        return response()->json([
            'data'                      => $rows,
            'labels'                    => $labels,
            'totale_costo_personale'    => round($costoPersonaleTotale, 2),
            'costo_personale_per_conv'  => $costoPersonalePerConv,
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
        $selectedAssoc = session('associazione_selezionata', $request->query('idAssociazione'));

        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? ($selectedAssoc ?: null)
            : $user->IdAssociazione;

        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
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
