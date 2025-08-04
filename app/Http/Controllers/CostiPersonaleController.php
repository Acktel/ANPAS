<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Dipendente;
use App\Models\CostiPersonale;
use App\Models\Convenzione;
use App\Models\RipartizionePersonale;

class CostiPersonaleController extends Controller {
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $selectedAssoc = session('associazione_selezionata') ?? $request->query('idAssociazione');

        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');
        $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);
        $qualifiche = $this->getQualificheDisponibili($anno, $user);

        return view('ripartizioni.costi_personale.index', compact('anno', 'selectedAssoc', 'associazioni', 'qualifiche'));
    }

    public function getData() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $qualificaInput = strtolower(request()->query('qualifica', ''));
        $selectedAssoc = session('associazione_selezionata');

        // Selezione Associazione
        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? $selectedAssoc
            : $user->IdAssociazione;

        // Blocca se non selezionata (per sicurezza)
        if (!$idAssociazione) {
            return response()->json(['data' => [], 'labels' => []]);
        }

        // Carica dipendenti filtrati per associazione
        $dipendenti = Dipendente::getByAssociazione($idAssociazione, $anno);

        // Filtra per qualifica
        $filtrati = $dipendenti->filter(function ($d) use ($qualificaInput) {
            $q = strtolower($d->Qualifica ?? '');
            $liv = strtolower($d->LivelloMansione ?? '');
            if ($qualificaInput === 'autisti e barellieri') {
                return str_contains($q, 'autista') || str_contains($q, 'barelliere') || str_contains($liv, 'c4');
            }
            return str_contains($q, $qualificaInput);
        });

        $costi = CostiPersonale::getAllByAnno($anno)->keyBy('idDipendente');
        $ripartizioni = RipartizionePersonale::getAll($anno, $user)->groupBy('idDipendente');
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

        foreach ($filtrati as $d) {
            $id = $d->idDipendente;
            $c = $costi->get($id);

            $retribuzioni = (float)($c->Retribuzioni ?? 0);
            $oneriSociali = (float)($c->OneriSociali ?? 0);
            $tfr = (float)($c->TFR ?? 0);
            $consulenze = (float)($c->Consulenze ?? 0);
            $totale = $retribuzioni + $oneriSociali + $tfr + $consulenze;

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
                $totPerConv["{$convKey}_percent"] = 0; // opzionale
            }

            $rows[] = $r;
        }

        $rows[] = array_merge([
            'idDipendente' => null,
            'Dipendente'   => 'TOTALE',
            'Qualifica'    => '',
            'Contratto'    => '',
            'is_totale'    => true,
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
        CostiPersonale::updateOrInsert($data);

        return response()->json([
            'success' => true,
            'message' => 'Dati salvati correttamente.'
        ]);
    }

    public function edit($idDipendente) {
        $anno = session('anno_riferimento', now()->year);
        $record = CostiPersonale::getWithDipendente($idDipendente, $anno);

        return view('ripartizioni.costi_personale.edit', compact('record', 'anno'));
    }

    public function update(Request $request, $idDipendente) {
        $data = $request->validate([
            'Retribuzioni'   => 'required|numeric',
            'OneriSociali'   => 'required|numeric',
            'TFR'            => 'required|numeric',
            'Consulenze'     => 'required|numeric',
            'Totale'         => 'required|numeric',
        ]);

        $data['idDipendente'] = $idDipendente;
        $data['idAnno'] = session('anno_riferimento', now()->year);
        CostiPersonale::updateOrInsert($data);

        return redirect()->route('ripartizioni.personale.costi.index')->with('success', 'Dati aggiornati.');
    }

    private function getQualificheDisponibili($anno, $user) {
        $dipendenti = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])
            ? Dipendente::getAll($anno)
            : Dipendente::getByAssociazione($user->IdAssociazione, $anno);

        $qualifiche = collect();

        foreach ($dipendenti as $d) {
            $q = strtolower($d->Qualifica ?? '');
            $liv = strtolower($d->LivelloMansione ?? '');

            if (str_contains($q, 'autista') || str_contains($q, 'barelliere') || str_contains($liv, 'c4')) {
                $qualifiche->push('Autisti e Barellieri');
            } else {
                $qualifiche->push(trim($d->Qualifica ?? 'Altro'));
            }
        }

        $qualificheUniche = $qualifiche->unique()->values();

        // Sposta "Autisti e Barellieri" in cima
        if ($qualificheUniche->contains('Autisti e Barellieri')) {
            $qualificheUniche = collect(['Autisti e Barellieri'])->merge(
                $qualificheUniche->reject(fn($q) => $q === 'Autisti e Barellieri')->sort()->values()
            );
        } else {
            $qualificheUniche = $qualificheUniche->sort()->values();
        }

        return $qualificheUniche;
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
