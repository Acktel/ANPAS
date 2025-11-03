<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\RipartizioneCostiAutomezziSanitari;
use App\Models\Automezzo;
use App\Services\RipartizioneCostiService;

class RipartizioneCostiAutomezziSanitariController extends Controller {
    public function index() {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isElevato = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);

        if ($isElevato) {
            $associazioni = DB::table('associazioni')
                ->select('idAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->where('idAssociazione', '!=', 1)
                ->orderBy('Associazione')
                ->get();

            $selectedAssoc = session('associazione_selezionata')
                ?? optional($associazioni->first())->idAssociazione;

            // Precarico gli automezzi per l’associazione selezionata (se c’è)
            $automezziAssoc = collect();
            if (!empty($selectedAssoc)) {
                $automezziAssoc = DB::table('automezzi')
                    ->select('idAutomezzo', 'Targa','CodiceIdentificativo')
                    ->where('idAssociazione', $selectedAssoc)
                    ->where('idAnno', $anno)
                    ->orderBy('Targa')
                    ->get();
            }

            $selectedAutomezzo = session('automezzo_selezionato', 'TOT');
        } else {
            // Utenti non elevati: niente select associazione, automezzi filtrati per utente
            $associazioni = collect();
            $selectedAssoc = (int) $user->IdAssociazione;
            $automezziAssoc = Automezzo::getFiltratiByUtente($anno)
                ->map(fn($a) => (object) ['idAutomezzo' => $a->idAutomezzo, 'Targa' => $a->Targa, 'CodiceIdentificativo' => $a->CodiceIdentificativo]);
            $selectedAutomezzo = session('automezzo_selezionato', 'TOT');
        }
        return view('ripartizioni.costi_automezzi_sanitari.index', [
            'anno'              => $anno,
            'associazioni'      => $associazioni,
            'isElevato'         => $isElevato,
            'selectedAssoc'     => $selectedAssoc,
            'automezziAssoc'    => $automezziAssoc,
            'selectedAutomezzo' => $selectedAutomezzo,
        ]);
    }

    public function getData(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $idAutomezzo = $request->input('idAutomezzo'); // filtro dinamico
        $dati = RipartizioneCostiAutomezziSanitari::calcola($idAutomezzo, $anno);
        return response()->json(['data' => $dati]);
    }

    public function getTabellaFinale(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = $user->IdAssociazione;
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && $request->filled('idAssociazione')) {
            $idAssociazione = (int) $request->input('idAssociazione');
        }

        $idAutomezzo = $request->input('idAutomezzo', 'TOT');

        // ✅ Memorizzo in sessione le ultime scelte
        session([
            'associazione_selezionata' => $idAssociazione,
            'automezzo_selezionato'    => $idAutomezzo,
        ]);

        // Convenzioni ordinate
        $convMap = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray(); // [id=>nome]

        // Tabella dati (TOT o mezzo)
        if ($idAutomezzo === 'TOT') {
            $tabella = RipartizioneCostiService::calcolaTabellaTotale($idAssociazione, $anno);
        } else {
            $tabella = RipartizioneCostiService::calcolaRipartizioneTabellaFinale(
                $idAssociazione,
                $anno,
                (int) $idAutomezzo
            );
        }

        // Colonne per la tabella principale
        $colonne = array_merge(['voce', 'totale'], array_values($convMap)); // uso i Nomi

        $sumRow = ['voce' => 'TOTALI', 'totale' => 0.0];
        // inizializza a 0 tutte le convenzioni (per nome, perché le colonne sono per nome)
        foreach (array_values($convMap) as $nomeConv) {
            $sumRow[$nomeConv] = 0.0;
        }

        // somma per colonna
        foreach ($tabella as $r) {
            $sumRow['totale'] += (float)($r['totale'] ?? 0.0);
            foreach (array_values($convMap) as $nomeConv) {
                $sumRow[$nomeConv] += (float)($r[$nomeConv] ?? 0.0);
            }
        }

        // arrotonda
        $sumRow['totale'] = round($sumRow['totale'], 2);
        foreach (array_values($convMap) as $nomeConv) {
            $sumRow[$nomeConv] = round($sumRow[$nomeConv], 2);
        }

        // append in coda alla tabella
        $tabella[] = $sumRow;

        // === Metadati per UI: regimi & voci interessate ===
        $regimi = RipartizioneCostiService::convenzioniPerRegime($idAssociazione, $anno); // ['rotazione'=>[id=>nome], 'sostitutivi'=>[id=>nome]]

        // convenzioni dove questo automezzo HA km > 0
        $convKmOkIds = [];
        if ($idAutomezzo !== 'TOT') {
            $kmRows = DB::table('automezzi_km')
                ->select('idConvenzione', DB::raw('SUM(KMPercorsi) AS km'))
                ->where('idAutomezzo', (int)$idAutomezzo)
                ->groupBy('idConvenzione')
                ->get();
            foreach ($kmRows as $r) {
                if ((float)$r->km > 0) $convKmOkIds[] = (int)$r->idConvenzione;
            }
        }

        // colonne attive per rotazione/sostitutivi (in NOME convenzione) *filtrate sull’automezzo*
        $rotCols = [];
        $sostCols = [];
        if ($idAutomezzo !== 'TOT') {
            foreach ($regimi['rotazione'] as $idC => $nome) {
                if (in_array((int)$idC, $convKmOkIds, true)) $rotCols[] = $nome;
            }
            foreach ($regimi['sostitutivi'] as $idC => $nome) {
                if (in_array((int)$idC, $convKmOkIds, true)) $sostCols[] = $nome;
            }
        }

        // voci interessate (per evidenziare righe della tabella)
        $vociRot = RipartizioneCostiService::vociRotazioneUI();
        $vociSost = RipartizioneCostiService::vociSostitutiviUI();

        // Bottoni visibili solo se mezzo specifico e ha almeno una colonna attiva nel relativo regime
        $showRotBtn  = ($idAutomezzo !== 'TOT') && !empty($rotCols);
        $showSostBtn = ($idAutomezzo !== 'TOT') && !empty($sostCols);

        return response()->json([
            'data'    => $tabella,
            'colonne' => $colonne,

            // 👇 metadati per JS/UI
            'meta' => [
                'rotazione' => [
                    'colonne' => $rotCols,    // [nomi convenzione]
                    'voci'    => $vociRot,    // [descrizioni]
                    'showBtn' => $showRotBtn,
                ],
                'sostitutivi' => [
                    'colonne' => $sostCols,
                    'voci'    => $vociSost,
                    'showBtn' => $showSostBtn,
                ],
                'routeDettaglio' => [
                    'rotazione'    => route('ripartizioni.costi_automezzi_sanitari.dettaglio.rotazione'),
                    'sostitutivi'  => route('ripartizioni.costi_automezzi_sanitari.dettaglio.sostitutivi'),
                ],
            ],
        ]);
    }

    public function dettaglioRotazione(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $idAss = (int)($request->input('idAssociazione') ?? Auth::user()->IdAssociazione);
        $idAut = (int)$request->input('idAutomezzo');

        abort_if(!$idAut, 404);

        $convMap = RipartizioneCostiService::convenzioni($idAss, $anno); // [id=>nome]
        $regimi  = RipartizioneCostiService::convenzioniPerRegime($idAss, $anno)['rotazione']; // [id=>nome]

        // Filtra solo le colonne (nomi) in regime ROTAZIONE dove il mezzo ha km>0
        $kmRows = DB::table('automezzi_km')
            ->select('idConvenzione', DB::raw('SUM(KMPercorsi) AS km'))
            ->where('idAutomezzo', $idAut)
            ->groupBy('idConvenzione')->get();
        $convOk = [];
        foreach ($kmRows as $r) if ((float)$r->km > 0 && isset($regimi[(int)$r->idConvenzione])) {
            $convOk[] = $convMap[(int)$r->idConvenzione]; // nomi
        }

        $rows = RipartizioneCostiService::calcolaRipartizioneTabellaFinale($idAss, $anno, $idAut);
        $vociTarget = RipartizioneCostiService::vociRotazioneUI();

        // filtro righe per voce + prendo solo colonne interessate
        $filtered = [];
        foreach ($rows as $r) {
            if (!in_array((string)$r['voce'], $vociTarget, true)) continue;
            $row = ['voce' => $r['voce'], 'totale' => $r['totale'] ?? 0];
            foreach ($convOk as $nomeC) $row[$nomeC] = $r[$nomeC] ?? 0;
            $filtered[] = $row;
        }

        return view('ripartizioni.costi_automezzi_sanitari.dettaglio', [
            'titolo'     => 'Dettaglio — Rotazione mezzi',
            'anno'       => $anno,
            'idAss'      => $idAss,
            'idAutomezzo' => $idAut,
            'colonne'    => array_merge(['voce', 'totale'], $convOk),
            'righe'      => $filtered,
        ]);
    }

    public function dettaglioSostitutivi(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $idAss = (int)($request->input('idAssociazione') ?? Auth::user()->IdAssociazione);
        $idAut = (int)$request->input('idAutomezzo');

        abort_if(!$idAut, 404);

        $convMap = RipartizioneCostiService::convenzioni($idAss, $anno);
        $regimi  = RipartizioneCostiService::convenzioniPerRegime($idAss, $anno)['sostitutivi'];

        $kmRows = DB::table('automezzi_km')
            ->select('idConvenzione', DB::raw('SUM(KMPercorsi) AS km'))
            ->where('idAutomezzo', $idAut)
            ->groupBy('idConvenzione')->get();
        $convOk = [];
        foreach ($kmRows as $r) if ((float)$r->km > 0 && isset($regimi[(int)$r->idConvenzione])) {
            $convOk[] = $convMap[(int)$r->idConvenzione];
        }

        $rows = RipartizioneCostiService::calcolaRipartizioneTabellaFinale($idAss, $anno, $idAut);
        $vociTarget = RipartizioneCostiService::vociSostitutiviUI();

        $filtered = [];
        foreach ($rows as $r) {
            if (!in_array((string)$r['voce'], $vociTarget, true)) continue;
            $row = ['voce' => $r['voce'], 'totale' => $r['totale'] ?? 0];
            foreach ($convOk as $nomeC) $row[$nomeC] = $r[$nomeC] ?? 0;
            $filtered[] = $row;
        }

        return view('ripartizioni.costi_automezzi_sanitari.dettaglio', [
            'titolo'     => 'Dettaglio — Mezzi sostitutivi',
            'anno'       => $anno,
            'idAss'      => $idAss,
            'idAutomezzo' => $idAut,
            'colonne'    => array_merge(['voce', 'totale'], $convOk),
            'righe'      => $filtered,
        ]);
    }
}
