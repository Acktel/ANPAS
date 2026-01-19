<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Automezzo;
use App\Models\AutomezzoKm;
use App\Models\Convenzione;
use App\Models\CostoMaterialeSanitario;
use App\Models\CostoOssigeno;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\RipartizioneOssigeno;
use App\Models\RipartizioneServizioCivile;
use App\Models\RipartizioneCostiAutomezziSanitari;

class RipartizioneCostiService {
    /* Whitelist bilancio manuale (voci editabili per sezione) */
    private const SEZIONI_BILANCIO_EDITABILE = [5, 6, 8, 9, 10, 11];

    private const VOCI_BILANCIO_EDIT_PER_SEZIONE = [
        5  => 'ALL',
        6  => [6007, 6008, 6009, 6010, 6011, 6012, 6013, 6014],
        8  => 'ALL',
        9  => [9002, 9003, 9006, 9007, 9008, 9009],
        10 => 'ALL',
        11 => 'ALL',
    ];

    private const IDS_VOLONTARI_RICAVI = [6007, 6008, 6009, 6014];
    private const VOCI_ROTAZIONE_MEZZI = [
        'LEASING/NOLEGGIO A LUNGO TERMINE',
        'ASSICURAZIONI',
        'MANUTENZIONE ORDINARIA',
        'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI',
        'PULIZIA E DISINFEZIONE',
        'INTERESSI PASS. F.TO, LEASING, NOL.',
        'AMMORTAMENTO AUTOMEZZI',
        'ALTRI COSTI MEZZI',
    ];

    public const VOCI_MEZZI_SOSTITUTIVI  = [
        'LEASING/NOLEGGIO A LUNGO TERMINE',
        'ASSICURAZIONI',
        'MANUTENZIONE ORDINARIA',
        'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI',
        'PULIZIA E DISINFEZIONE',
        'INTERESSI PASS. F.TO, LEASING, NOL.',
        'MANUTENZIONE ATTREZZATURA SANITARIA',
        'LEASING ATTREZZATURA SANITARIA',
        'AMMORTAMENTO AUTOMEZZI',
        'AMMORTAMENTO ATTREZZATURA SANITARIA',
        'ALTRI COSTI MEZZI',
    ];

    private static $IDS_PERSONALE_RETRIBUZIONI = array(6001, 6002, 6003, 6004, 6005, 6006);

    private static $MAP_VOCE_TO_QUALIFICA = array(
        6001 => 1, // AUTISTA SOCCORRITORE
        6002 => 6, // COORDINATORE TECNICO
        6003 => 3, // ADDETTO PULIZIA
        6004 => 2, // ADDETTO LOGISTICA
        6005 => 7, // IMPIEGATO AMMINISTRATIVO
        6006 => 5, // COORDINATORE AMMINISTRATIVO
    );

    /* ========================= MATERIALE SANITARIO / AUTOMEZZI / RADIO ========================= */

    public static function getMaterialiSanitariConsumo(int $idAssociazione, int $idAnno, int $idAutomezzo): float {
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $idAnno);
        if ($totaleBilancio <= 0) {
            return 0.0;
        }

        // ripartizione per mezzo
        $dati   = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $idAnno);
        $righe  = $dati['righe'] ?? [];
        $convs  = collect($dati['convenzioni'] ?? []);

        // ESCLUDO MSA / MSAB / ASA (match sul nome, case-insensitive)
        $exclude = static function ($nome): bool {
            $t = mb_strtoupper((string)$nome, 'UTF-8');
            return str_contains($t, 'MSA') || str_contains($t, 'MSAB') || str_contains($t, 'ASA');
        };

        $convInclIds = $convs
            ->reject(fn($c) => $exclude($c->Convenzione ?? ''))
            ->pluck('idConvenzione')
            ->map(fn($v) => (int)$v)
            ->all();

        // sommo SOLO le conv incluse e SOLO i mezzi inclusi_riparto
        $totServiziNetti = 0.0;
        $serviziMezzo    = 0.0;

        foreach ($righe as $r) {
            if (!empty($r['is_totale'])) continue;
            if (empty($r['incluso_riparto'])) continue;

            $valori = (array)($r['valori'] ?? []);
            $somma  = 0.0;
            foreach ($convInclIds as $idC) {
                $somma += (float)($valori[$idC] ?? 0);
            }

            $totServiziNetti += $somma;
            if ((int)($r['idAutomezzo'] ?? 0) === $idAutomezzo) {
                $serviziMezzo = $somma;
            }
        }

        if ($totServiziNetti <= 0 || $serviziMezzo <= 0) {
            return 0.0;
        }

        return round(($serviziMezzo / $totServiziNetti) * $totaleBilancio, 2);
    }

    public static function calcolaRipartizione(
        int $idAssociazione,
        int $anno,
        float $totaleBilancio,
        ?int $idAutomezzo = null
    ): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->when($idAutomezzo, fn($q) => $q->where('idAutomezzo', $idAutomezzo))
            ->get();

        $risultato = [];

        foreach ($automezzi as $mezzo) {
            $kmConvenzioni = DB::table('automezzi_km')
                ->where('idAutomezzo', $mezzo->idAutomezzo)
                ->get();

            $kmTotali = $kmConvenzioni->sum('KMPercorsi');

            foreach ($kmConvenzioni as $riga) {
                $percentuale = $kmTotali > 0 ? round(($riga->KMPercorsi / $kmTotali) * 100, 2) : 0;
                $importo     = $kmTotali > 0 ? round(($riga->KMPercorsi / $kmTotali) * $totaleBilancio, 2) : 0;

                $risultato[] = [
                    'idAutomezzo'   => $riga->idAutomezzo,
                    'idConvenzione' => $riga->idConvenzione,
                    'km'            => $riga->KMPercorsi,
                    'km_totali'     => $kmTotali,
                    'percentuale'   => $percentuale,
                    'importo'       => $importo,
                ];
            }
        }

        return $risultato;
    }

    public static function calcoloRipartizioneOssigeno(
        int $idAssociazione,
        int $anno,
        float $totaleBilancio,
        ?int $idAutomezzo = null
    ): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->when($idAutomezzo, fn($q) => $q->where('idAutomezzo', $idAutomezzo))
            ->get();

        $risultato = [];

        foreach ($automezzi as $mezzo) {
            $serviziConvenzioni = DB::table('automezzi_servizi')
                ->where('idAutomezzo', $mezzo->idAutomezzo)
                ->get();

            if ($serviziConvenzioni->isEmpty()) continue;

            $serviziTotali = $serviziConvenzioni->sum('NumeroServizi');

            foreach ($serviziConvenzioni as $riga) {
                $percentuale = $serviziTotali > 0 ? round(($riga->NumeroServizi / $serviziTotali) * 100, 2) : 0;
                $importo     = $serviziTotali > 0 ? round(($riga->NumeroServizi / $serviziTotali) * $totaleBilancio, 2) : 0;

                $risultato[] = [
                    'idAutomezzo'   => $riga->idAutomezzo,
                    'idConvenzione' => $riga->idConvenzione,
                    'NumeroServizi' => $riga->NumeroServizi,
                    'ServiziTotali' => $serviziTotali,
                    'percentuale'   => $percentuale,
                    'importo'       => $importo,
                ];
            }
        }

        return $risultato;
    }

    public static function calcoloRipartizioneCostiRadio(
        int $idAssociazione,
        int $anno,
        ?int $idAutomezzo = null
    ): array {
        $voci = [
            'ManutenzioneApparatiRadio',
            'MontaggioSmontaggioRadio118',
            'LocazionePonteRadio',
            'AmmortamentoImpiantiRadio',
        ];

        $costiRadio = DB::table('costi_radio')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if (!$costiRadio) {
            return [];
        }

        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->when($idAutomezzo, fn($q) => $q->where('idAutomezzo', $idAutomezzo))
            ->get();

        $risultato = [];

        foreach ($automezzi as $mezzo) {
            $servizi = DB::table('automezzi_servizi')
                ->where('idAutomezzo', $mezzo->idAutomezzo)
                ->get();

            $totaleServizi = $servizi->sum('NumeroServizi');

            foreach ($servizi as $riga) {
                $percentuale = $totaleServizi > 0 ? round(($riga->NumeroServizi / $totaleServizi) * 100, 2) : 0;

                $ripartizione = [];
                foreach ($voci as $voce) {
                    $importoTotale       = $costiRadio->$voce ?? 0;
                    $ripartizione[$voce] = $totaleServizi > 0
                        ? round(($riga->NumeroServizi / $totaleServizi) * $importoTotale, 2)
                        : 0;
                }

                $risultato[] = array_merge([
                    'idAutomezzo'   => $mezzo->idAutomezzo,
                    'idConvenzione' => $riga->idConvenzione,
                    'NumeroServizi' => $riga->NumeroServizi,
                    'TotaleServizi' => $totaleServizi,
                    'percentuale'   => $percentuale,
                ], $ripartizione);
            }
        }

        return $risultato;
    }

    public static function calcolaRipartizioneTabellaFinale(
        int $idAssociazione,
        int $anno,
        int $idAutomezzo
    ): array {
        // 1) Mappa voci → campo DB
        $vociKm = [
            'LEASING/NOLEGGIO A LUNGO TERMINE'                          => 'LeasingNoleggio',
            'ASSICURAZIONI'                                             => 'Assicurazione',
            'MANUTENZIONE ORDINARIA'                                    => 'ManutenzioneOrdinaria',
            'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI' => 'ManutenzioneStraordinaria',
            'RIMBORSI ASSICURAZIONE'                                    => 'RimborsiAssicurazione',
            'PULIZIA E DISINFEZIONE'                                    => 'PuliziaDisinfezione',
            'CARBURANTI AL NETTO RIMBORSI UTF'                          => 'Carburanti',
            'ADDITIVI'                                                  => 'Additivi',
            'INTERESSI PASS. F.TO, LEASING, NOL.'                       => 'InteressiPassivi',
            'MANUTENZIONE ATTREZZATURA SANITARIA'                       => 'ManutenzioneSanitaria',
            'LEASING ATTREZZATURA SANITARIA'                            => 'LeasingSanitaria',
            'AMMORTAMENTO AUTOMEZZI'                                    => 'AmmortamentoMezzi',
            'AMMORTAMENTO ATTREZZATURA SANITARIA'                       => 'AmmortamentoSanitaria',
            'ALTRI COSTI MEZZI'                                         => 'AltriCostiMezzi',
        ];

        $tabella = [];

        // Convenzioni ordinate
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        if (empty($convenzioni)) {
            return [];
        }

        // 2) Dati di base mezzo selezionato
        $costi = DB::table('costi_automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->where('idAnno', $anno)
            ->first();

        // KM del mezzo per convenzione
        $kmRecords = AutomezzoKm::getKmPerConvenzione($idAutomezzo, $anno);
        $kmPerConv = $kmRecords->pluck('KMPercorsi', 'idConvenzione')
            ->map(fn($v) => (float)$v)
            ->toArray();

        // KM totali per convenzione (tutti i mezzi)
        $kmTotConvMap = DB::table('automezzi_km')
            ->whereIn('idConvenzione', array_keys($convenzioni))
            ->select('idConvenzione', DB::raw('SUM(COALESCE(KMPercorsi,0)) AS tot'))
            ->groupBy('idConvenzione')
            ->pluck('tot', 'idConvenzione')
            ->map(fn($v) => (float)$v)
            ->toArray();

        // Servizi del mezzo per convenzione
        $serviziPerConv = DB::table('automezzi_servizi')
            ->where('idAutomezzo', $idAutomezzo)
            ->pluck('NumeroServizi', 'idConvenzione')
            ->map(fn($v) => (float)$v)
            ->toArray();

        $totaleServizi = array_sum($serviziPerConv);

        // 3) Voci a riparto (in base alle regole)
        foreach ($vociKm as $voceLabel => $colDB) {

            // 3.1) valore base voce (per MEZZO)
            if (!$costi) {
                $valore = 0.0;
            } else {
                switch ($voceLabel) {
                    case 'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI':
                        $valore = (float)($costi->ManutenzioneStraordinaria ?? 0)
                            - (float)($costi->RimborsiAssicurazione ?? 0);
                        break;

                    case 'CARBURANTI AL NETTO RIMBORSI UTF':
                        $valore = self::carburantiNetti($costi);
                        break;

                    default:
                        $valore = (float)($costi->$colDB ?? 0);
                }
            }

            $valore = round(max(0.0, $valore), 2);

            // 3.2) Quote per convenzione:
            // - ROTAZIONE (solo per convenzioni in regime rotazione e solo per le voci rotazione):
            //     quota = valore * (km mezzo su conv / km totali conv)
            // - resto: residuo splittato con Hamilton su pesi km (o servizi per amm. sanitaria)
            $quote        = [];   // [idConv => euro]
            $pesiFallback = [];   // per split residuo
            $sommaRot     = 0.0;

            $isVoceRot = in_array($voceLabel, self::VOCI_ROTAZIONE_MEZZI, true);

            foreach ($convenzioni as $idConv => $nomeConv) {
                $idConv = (int)$idConv;

                // Ammortamento attrezzatura sanitaria -> % servizi (sempre)
                if ($voceLabel === 'AMMORTAMENTO ATTREZZATURA SANITARIA') {
                    $pesiFallback[$idConv] = (float)($serviziPerConv[$idConv] ?? 0.0);
                    continue;
                }

                // Mezzi sostitutivi -> km mezzo per convenzione (solo se regime sostitutivi)
                if (
                    in_array($voceLabel, self::VOCI_MEZZI_SOSTITUTIVI, true)
                    && self::isRegimeMezziSostitutivi($idConv)
                ) {
                    $pesiFallback[$idConv] = (float)($kmPerConv[$idConv] ?? 0.0);
                    continue;
                }

                // Rotazione Excel -> SOLO se convenzione in regime rotazione e voce è rotazione
                if ($isVoceRot && self::isRegimeRotazione($idConv)) {
                    $den = (float)($kmTotConvMap[$idConv] ?? 0.0);   // km totali convenzione (tutti i mezzi)
                    $num = (float)($kmPerConv[$idConv] ?? 0.0);      // km di questo mezzo in quella convenzione

                    $share = ($den > 0.0) ? ($num / $den) : 0.0;

                    $q = round($valore * $share, 2);
                    $quote[$idConv] = $q;
                    $sommaRot += $q;

                    continue;
                }

                // Default -> pesi km mezzo per convenzione (Hamilton sul residuo)
                $pesiFallback[$idConv] = (float)($kmPerConv[$idConv] ?? 0.0);
            }

            // 3.3) Completa quote:
            if ($voceLabel === 'AMMORTAMENTO ATTREZZATURA SANITARIA') {
                // split su TUTTO il valore con pesi servizi
                $quote = self::splitByWeightsCents($valore, $pesiFallback);
            } else {
                // residuo = valore - somma quote rotazione (così somma riga torna al totale)
                $residuo = round($valore - $sommaRot, 2);
                if ($residuo < 0) $residuo = 0.0;

                if ($residuo != 0.0 && !empty($pesiFallback)) {
                    $q2 = self::splitByWeightsCents($residuo, $pesiFallback);
                    foreach ($q2 as $cid => $v) {
                        $quote[(int)$cid] = (float)$v;
                    }
                } else {
                    // se non ho fallback, garantisco almeno le chiavi
                    foreach ($pesiFallback as $cid => $_) {
                        if (!isset($quote[(int)$cid])) {
                            $quote[(int)$cid] = 0.0;
                        }
                    }
                }
            }

            // 3.4) costruisci riga
            $riga = [
                'voce'   => $voceLabel,
                'totale' => $valore,
            ];

            foreach ($convenzioni as $idConv => $nomeConv) {
                $idConv = (int)$idConv;
                $riga[$nomeConv] = $quote[$idConv] ?? 0.0;
            }

            $tabella[] = $riga;
        }

        // 4) Materiali sanitari di consumo → % servizi (valore per MEZZO)
        $valoreMSC = self::getMaterialiSanitariConsumo($idAssociazione, $anno, $idAutomezzo);
        $tabella[] = self::ripartisciPerServizi(
            $valoreMSC,
            'MATERIALI SANITARI DI CONSUMO',
            $serviziPerConv,
            $totaleServizi,
            $convenzioni
        );

        // 5) Ossigeno → % servizi (valore per MEZZO)
        $valoreOss = self::getOssigenoConsumo($idAssociazione, $anno, $idAutomezzo);
        $tabella[] = self::ripartisciPerServizi(
            $valoreOss,
            'OSSIGENO',
            $serviziPerConv,
            $totaleServizi,
            $convenzioni
        );

        // 6) Radio → prima per-automezzo, poi % km del mezzo nell’anno
        $costiRadio = DB::table('costi_radio')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if ($costiRadio) {
            $vociRadio = [
                'MANUTENZIONE APPARATI RADIO'    => 'ManutenzioneApparatiRadio',
                'MONTAGGIO/SMONTAGGIO RADIO 118' => 'MontaggioSmontaggioRadio118',
                'LOCAZIONE PONTE RADIO'          => 'LocazionePonteRadio',
                'AMMORTAMENTO IMPIANTI RADIO'    => 'AmmortamentoImpiantiRadio',
            ];

            $numAutomezzi = max(
                (int)DB::table('automezzi')
                    ->where('idAssociazione', $idAssociazione)
                    ->where('idAnno', $anno)
                    ->count(),
                1
            );

            foreach ($vociRadio as $voceLabel => $campoDB) {
                $importoBase         = (float)($costiRadio->$campoDB ?? 0);
                $importoPerAutomezzo = $importoBase / $numAutomezzi;

                // pesi: km del mezzo per convenzione
                $pesi = [];
                foreach (array_keys($convenzioni) as $idConv) {
                    $idConv = (int)$idConv;
                    $pesi[$idConv] = (float)($kmPerConv[$idConv] ?? 0.0);
                }

                $quote = self::splitByWeightsCents($importoPerAutomezzo, $pesi);

                $riga = [
                    'voce'   => $voceLabel,
                    'totale' => round($importoPerAutomezzo, 2),
                ];

                foreach ($convenzioni as $idConv => $nomeConv) {
                    $idConv = (int)$idConv;
                    $riga[$nomeConv] = $quote[$idConv] ?? 0.0;
                }

                $tabella[] = $riga;
            }
        }

        return $tabella;
    }

    public static function calcolaTabellaTotale(int $idAssociazione, int $anno): array {
        // usa TUTTI i mezzi (come vuoi tu)
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('idAutomezzo');

        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        $tot = [];

        foreach ($automezzi as $idAutomezzo) {
            $idAutomezzo = (int)$idAutomezzo;

            $tabella = self::calcolaRipartizioneTabellaFinale($idAssociazione, $anno, $idAutomezzo);

            foreach ($tabella as $riga) {
                $voce = $riga['voce'];

                if (!isset($tot[$voce])) {
                    $tot[$voce] = [
                        'voce'   => $voce,
                        'totale' => 0.0,
                    ];
                    foreach ($convenzioni as $convName) {
                        $tot[$voce][$convName] = 0.0;
                    }
                }

                // somma le celle per convenzione ESATTAMENTE come arrivano dal per-mezzo
                foreach ($convenzioni as $convName) {
                    $tot[$voce][$convName] += (float)($riga[$convName] ?? 0.0);
                }

                // somma totale riga (poi radio verrà sostituita)
                $tot[$voce]['totale'] += (float)($riga['totale'] ?? 0.0);
            }
        }

        // RADIO: una sola ripartizione a livello associazione
        $radioRows = self::ripartoRadioAssoc($idAssociazione, $anno, $convenzioni);
        foreach ($radioRows as $voce => $row) {
            $tot[$voce] = $row;
        }

        // arrotondo tutte le celle a 2 decimali
        foreach ($tot as &$r) {
            foreach ($r as $k => $v) {
                if ($k === 'voce') continue;
                $r[$k] = round((float)$v, 2, PHP_ROUND_HALF_UP);
            }
        }
        unset($r);

        return array_values($tot);
    }



    /**
     * Ripartizione RADIO a livello associazione in stile Excel:
     * - totale esatto da tabella costi_radio (nessuna perdita di centesimi)
     * - pesi = media delle quote km per convenzione sui mezzi (mezzi senza km ignorati)
     * - un unico splitByWeightsCents per voce.
     */
    private static function ripartoRadioAssoc(
        int $idAssociazione,
        int $anno,
        array $convenzioni
    ): array {
        $costi = DB::table('costi_radio')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if (!$costi) {
            return [];
        }

        $vociRadio = [
            'MANUTENZIONE APPARATI RADIO'    => 'ManutenzioneApparatiRadio',
            'MONTAGGIO/SMONTAGGIO RADIO 118' => 'MontaggioSmontaggioRadio118',
            'LOCAZIONE PONTE RADIO'          => 'LocazionePonteRadio',
            'AMMORTAMENTO IMPIANTI RADIO'    => 'AmmortamentoImpiantiRadio',
        ];

        // raccogli quote km per convenzione per OGNI mezzo
        $mezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('idAutomezzo');

        $sumQuote           = array_fill_keys(array_keys($convenzioni), 0.0);
        $nMezziConsiderati  = 0;

        foreach ($mezzi as $idAutomezzo) {
            $km = DB::table('automezzi_km')
                ->where('idAutomezzo', $idAutomezzo)
                ->whereIn('idConvenzione', array_keys($convenzioni))
                ->pluck('KMPercorsi', 'idConvenzione')
                ->map(fn($v) => (float)$v)
                ->toArray();

            $totKm = array_sum($km);
            if ($totKm <= 0) {
                continue; // ignora mezzi senza km
            }

            foreach ($km as $cid => $val) {
                $sumQuote[(int)$cid] += ($val / $totKm); // quota di questo mezzo su quella convenzione
            }
            $nMezziConsiderati++;
        }

        // media delle quote per convenzione (se nessun mezzo con km, fallback uniforme)
        $pesiMedi = [];
        if ($nMezziConsiderati > 0) {
            foreach ($sumQuote as $cid => $s) {
                $pesiMedi[$cid] = $s / $nMezziConsiderati;
            }
        } else {
            foreach ($sumQuote as $cid => $_) {
                $pesiMedi[$cid] = 1.0;
            }
        }

        $out = [];
        foreach ($vociRadio as $label => $campo) {
            $totale = round((float)($costi->$campo ?? 0.0), 2);
            $quote  = self::splitByWeightsCents($totale, $pesiMedi);

            $riga = [
                'voce'   => $label,
                'totale' => $totale,
            ];
            foreach ($convenzioni as $cid => $nome) {
                $riga[$nome] = $quote[$cid] ?? 0.0;
            }

            $out[$label] = $riga;
        }

        return $out;
    }

    public static function getCostiDiretti(int $idAssociazione, int $anno): array {
        return DB::table('costi_diretti')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('costo', 'idConvenzione')
            ->toArray();
    }

    public static function estraiVociDaRiepilogoDati(
        int $idAssociazione,
        int $anno,
        array $tipologie
    ): array {
        $idRiepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->value('idRiepilogo');

        if (!$idRiepilogo) {
            return [];
        }

        $voci = DB::table('riepilogo_dati as rd')
            ->join('riepilogo_voci_config as vc', 'vc.id', '=', 'rd.idVoceConfig')
            ->where('rd.idRiepilogo', $idRiepilogo)
            ->whereIn('vc.idTipologiaRiepilogo', $tipologie)
            ->select('vc.idTipologiaRiepilogo', 'vc.descrizione')
            ->distinct()
            ->orderBy('vc.idTipologiaRiepilogo')
            ->orderBy('vc.descrizione')
            ->get();

        $out = [];
        foreach ($voci as $voce) {
            $out[(int)$voce->idTipologiaRiepilogo][] = trim(mb_strtoupper($voce->descrizione, 'UTF-8'));
        }

        return $out;
    }

    /** Convenzioni (id => nome) ordinate stabilmente */
    public static function convenzioni(int $idAssociazione, int $anno): array {
        return DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();
    }

    /** Quote ricavi per convenzione (0..1) */
    public static function quoteRicaviByConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        $rows = DB::table('rapporti_ricavi')
            ->select('idConvenzione', 'Rimborso')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereIn('idConvenzione', $convIds)
            ->get();

        $tot = (float)$rows->sum('Rimborso');

        $out = array_fill_keys($convIds, 0.0);
        foreach ($convIds as $id) {
            $val      = (float)($rows->firstWhere('idConvenzione', $id)->Rimborso ?? 0);
            $out[$id] = $tot > 0 ? ($val / $tot) : 0.0;
        }

        return $out;
    }

    /** Importi A&B per convenzione (e totale) */
    public static function importiAutistiBarellieriByConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        $subOre = DB::table('dipendenti_servizi')
            ->select('idDipendente', DB::raw('SUM(OreServizio) AS ore_tot'))
            ->groupBy('idDipendente');

        $rows = DB::table('dipendenti as d')
            ->join('dipendenti_qualifiche as dq', 'dq.idDipendente', '=', 'd.idDipendente')
            ->join('qualifiche as q', 'q.id', '=', 'dq.idQualifica')
            ->leftJoin('costi_personale as cp', function ($j) use ($anno) {
                $j->on('cp.idDipendente', '=', 'd.idDipendente')
                    ->where('cp.idAnno', '=', $anno);
            })
            ->join('dipendenti_servizi as ds', 'ds.idDipendente', '=', 'd.idDipendente')
            ->leftJoinSub($subOre, 's', fn($j) => $j->on('s.idDipendente', '=', 'd.idDipendente'))
            ->where('d.idAssociazione', $idAssociazione)
            ->where('d.idAnno', $anno)
            ->where(function ($w) {
                $w->whereRaw('LOWER(q.nome) LIKE ?', ['%autist%'])
                    ->orWhereRaw('LOWER(q.nome) LIKE ?', ['%barell%']);
            })
            ->whereIn('ds.idConvenzione', $convIds)
            ->select([
                'ds.idConvenzione',
                DB::raw("
                    SUM(
                        (CASE WHEN s.ore_tot > 0 THEN ds.OreServizio / s.ore_tot ELSE 0 END)
                        *
                        (CASE
                            WHEN COALESCE(cp.Totale,0) > 0 THEN cp.Totale
                            ELSE COALESCE(cp.Retribuzioni,0)
                               + COALESCE(cp.OneriSocialiInps,0)
                               + COALESCE(cp.OneriSocialiInail,0)
                               + COALESCE(cp.TFR,0)
                               + COALESCE(cp.Consulenze,0)
                         END)
                    ) AS importo
                "),
            ])
            ->groupBy('ds.idConvenzione')
            ->get();

        $out = array_fill_keys($convIds, 0.0);
        $tot = 0.0;

        foreach ($rows as $r) {
            $val = round((float)$r->importo, 2);
            $out[(int)$r->idConvenzione] = $val;
            $tot += $val;
        }

        return [$out, $tot];
    }

    /** Normalizzatore */
    private static function norm(?string $s): string {
        $s = (string)$s;
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($s)), 'UTF-8');
    }

    /**
     * Diretti per voce/convenzione + bilancio per voce (con fallback legacy e
     * SOMMA bilanci manuali voce+sezione).
     */
    public static function aggregatiDirettiEBilancio(
        int $idAssociazione,
        int $anno,
        Collection|array $vociConfig,
        Collection|array $ripByNormDesc
    ): array {
        $cdId = DB::table('costi_diretti')
            ->select(
                'idVoceConfig',
                'idConvenzione',
                DB::raw('SUM(costo) as sum_costo'),
                DB::raw('SUM(ammortamento) as sum_amm'),
                DB::raw('SUM(bilancio_consuntivo) as sum_bilancio')
            )
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNotNull('idVoceConfig')
            ->groupBy('idVoceConfig', 'idConvenzione')
            ->get();

        $dirByVoceByConv = [];
        $ammByVoceByConv = [];
        $netByVoceByConv = [];
        $dirTotByVoce    = [];
        $ammTotByVoce    = [];
        $netTotByVoce    = [];
        $bilByVoce       = [];

        foreach ($cdId as $r) {
            $v   = (int)$r->idVoceConfig;
            $c   = (int)$r->idConvenzione;
            $dir = (float)$r->sum_costo;
            $amm = (float)$r->sum_amm;

            $dirByVoceByConv[$v][$c] = ($dirByVoceByConv[$v][$c] ?? 0) + $dir;
            $ammByVoceByConv[$v][$c] = ($ammByVoceByConv[$v][$c] ?? 0) + $amm;
            $netByVoceByConv[$v][$c] = ($netByVoceByConv[$v][$c] ?? 0) + ($dir - $amm);

            $dirTotByVoce[$v] = ($dirTotByVoce[$v] ?? 0) + $dir;
            $ammTotByVoce[$v] = ($ammTotByVoce[$v] ?? 0) + $amm;
            $netTotByVoce[$v] = ($netTotByVoce[$v] ?? 0) + ($dir - $amm);
        }

        // mapping descrizione -> idVoce
        $mapDescToId = [];
        foreach ($vociConfig as $vc) {
            $mapDescToId[self::norm($vc->descrizione)] = (int)$vc->id;
        }

        // Diretti/bilancio su righe senza idVoceConfig (mappate per descrizione)
        $cdNo = DB::table('costi_diretti')
            ->select(
                'voce',
                'idConvenzione',
                DB::raw('SUM(costo) as sum_costo'),
                DB::raw('SUM(bilancio_consuntivo) as sum_bilancio')
            )
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNull('idVoceConfig')
            ->groupBy('voce', 'idConvenzione')
            ->get();

        foreach ($cdNo as $r) {
            $desc = self::norm($r->voce ?? '');
            if (!$desc || !isset($mapDescToId[$desc])) {
                continue;
            }

            $v = $mapDescToId[$desc];
            $c = (int)$r->idConvenzione;

            $dirByVoceByConv[$v][$c] = ($dirByVoceByConv[$v][$c] ?? 0)
                + (float)$r->sum_costo;
            $dirTotByVoce[$v]        = ($dirTotByVoce[$v] ?? 0)
                + (float)$r->sum_costo;
        }

        /* ======= Sommo i BILANCI INSERITI A MANO per VOCE+SEZIONE (globale: idVoceConfig NULL, idConvenzione NULL) ======= */
        $bilanciManuali = DB::table('costi_diretti')
            ->select('voce', 'idSezione', DB::raw('SUM(bilancio_consuntivo) AS tot'))
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNull('idVoceConfig')
            ->whereNull('idConvenzione')
            ->groupBy('voce', 'idSezione')
            ->get();

        // sezione per idVoce
        $sezioneByVoceId = [];
        foreach ($vociConfig as $vc) {
            $sezioneByVoceId[(int)$vc->id] = (int)$vc->idTipologiaRiepilogo;
        }

        // helper whitelist
        $voceAmmessa = static function (int $idVoce, ?int $sezioneRiga): bool {
            $sezVoce = (int)$sezioneRiga;

            if (!in_array($sezVoce, self::SEZIONI_BILANCIO_EDITABILE, true)) {
                return false;
            }

            $wl = self::VOCI_BILANCIO_EDIT_PER_SEZIONE[$sezVoce] ?? null;
            if ($wl === 'ALL') {
                return true;
            }

            return is_array($wl) && in_array($idVoce, $wl, true);
        };

        // mappa descrizione -> idVoce
        $voceIdByDesc = [];
        foreach ($vociConfig as $vc) {
            $voceIdByDesc[self::norm($vc->descrizione)] = (int)$vc->id;
        }

        foreach ($bilanciManuali as $r) {
            $descN  = self::norm($r->voce ?? '');
            $idVoce = $voceIdByDesc[$descN] ?? null;
            if (!$idVoce) {
                continue;
            }

            // coerenza sezione (se diverso, skip)
            $sezVoce = $sezioneByVoceId[$idVoce] ?? null;
            if ($sezVoce !== null && (int)$r->idSezione !== (int)$sezVoce) {
                continue;
            }

            if (!$voceAmmessa($idVoce, (int)$r->idSezione)) {
                continue;
            }

            $bilByVoce[$idVoce] = ($bilByVoce[$idVoce] ?? 0)
                + (float)$r->tot;
        }
        /* ================================================================================================================ */

        // Bilancio per voce (priorità: bilByVoce -> legacy -> diretti)
        $bilancioByVoce = [];
        foreach ($vociConfig as $vc) {
            $v        = (int)$vc->id;
            $descNorm = self::norm($vc->descrizione);

            if (!empty($bilByVoce[$v])) {
                $bilancioByVoce[$v] = (float)$bilByVoce[$v];
            } elseif (isset($ripByNormDesc[$descNorm])) {
                $bilancioByVoce[$v] = (float)($ripByNormDesc[$descNorm]['totale'] ?? 0);
            } else {
                $bilancioByVoce[$v] = (float)($dirTotByVoce[$v] ?? 0);
            }
        }

        return [
            $dirByVoceByConv,
            $dirTotByVoce,
            $bilancioByVoce,
            $ammByVoceByConv,
            $ammTotByVoce,
            $netByVoceByConv,
            $netTotByVoce,
        ];
    }

    /* ========================= DISTINTA IMPUTAZIONE COSTI ========================= */

    public static function distintaImputazioneData(int $idAssociazione, int $anno): array {
        // ------------------------------------------------------------
        // 0) Convenzioni (id => nome)
        // ------------------------------------------------------------
        $convenzioni = self::convenzioni($idAssociazione, $anno);
        if (empty($convenzioni)) {
            return [
                'data'        => [],
                'convenzioni' => [],
            ];
        }

        $convIds  = array_keys($convenzioni);
        $convNomi = array_values($convenzioni);

        // ------------------------------------------------------------
        // 1) Pesi base
        // ------------------------------------------------------------
        $quoteRicavi       = self::quoteRicaviByConvenzione($idAssociazione, $anno, $convIds);
        $persPerQualByConv = self::importiPersonalePerQualificaByConvenzione($idAssociazione, $anno, $convIds);
        $percServCivile    = self::percentualiServizioCivileByConvenzione($idAssociazione, $anno, $convIds);

        // Id speciali/sezioni
        $VOCE_SCIV_ID           = 6013; // Servizio Civile Nazionale
        $IDS_ADMIN_RICAVI       = [8001, 8002, 8003, 8004, 8005, 8006, 8007];
        $IDS_QUOTE_AMMORTAMENTO = [9002, 9003, 9006, 9007, 9008, 9009];
        $BENI_STRUMENTALI_ID    = 10001;
        $IDS_BENI_STRUMENTALI   = [11001, 11002];

        // Volontari a ricavi: elenco consolidato
        $IDS_VOLONTARI_RICAVI = array_values(array_unique(array_merge(
            self::IDS_VOLONTARI_RICAVI ?? [],
            [6007, 6008, 6009, 6013, 6014] // pasti, avvicendamenti, assicurazioni, SCN (quota ANPAS), divise
        )));

        // Formazioni volontari a % servizi
        $IDS_VOLONTARI_FORMAZIONE_SERVIZI = [6010, 6011, 6012]; // A+DAE, RDAE, SARA

        // ------------------------------------------------------------
        // 2) Config voci e “legacy” (tabella riparto mezzi)
        // ------------------------------------------------------------
        $vociConfig = DB::table('riepilogo_voci_config as vc')
            ->select('vc.id', 'vc.descrizione', 'vc.idTipologiaRiepilogo', 'vc.ordinamento')
            ->whereBetween('vc.idTipologiaRiepilogo', [2, 11])
            ->where('vc.attivo', 1)
            ->orderBy('vc.idTipologiaRiepilogo')
            ->orderBy('vc.ordinamento')
            ->orderBy('vc.id')
            ->get();

        $legacy = self::calcolaTabellaTotale($idAssociazione, $anno);

        // Mappa normalizzata descrizione -> riga legacy
        $ripByNormDesc = [];
        foreach ((array)$legacy as $r) {
            if (!isset($r['voce'])) {
                continue;
            }
            $ripByNormDesc[self::norm($r['voce'])] = $r;
        }

        // Alias per matchare le descrizioni configurate con le righe legacy
        $alias = [
            'leasing/ noleggio automezzi'         => 'LEASING/NOLEGGIO A LUNGO TERMINE',
            'assicurazione automezzi'             => 'ASSICURAZIONI',
            'manutenzione ordinaria'              => 'MANUTENZIONE ORDINARIA',
            'manutenzione straordinaria'          => 'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI',
            'pulizia e disinfezione automezzi'    => 'PULIZIA E DISINFEZIONE',
            'carburanti'                          => 'CARBURANTI AL NETTO RIMBORSI UTF',
            'additivi'                            => 'ADDITIVI',
            'interessi passivi da finanziamento e costi per finanziamento/leasing/noleggio a lungo termine' => 'INTERESSI PASS. F.TO, LEASING, NOL.',
            'altri costi mezzi'                   => 'ALTRI COSTI MEZZI',
            'manutenzione attrezzatura sanitaria' => 'MANUTENZIONE ATTREZZATURA SANITARIA',
            'leasing attrezzatura sanitaria'      => 'LEASING ATTREZZATURA SANITARIA',
            'manutenzione apparati radio'         => 'MANUTENZIONE APPARATI RADIO',
            'montaggio/smontaggio radio 118'      => 'MONTAGGIO/SMONTAGGIO RADIO 118',
            'canoni locazione ponte radio'        => 'LOCAZIONE PONTE RADIO',
            'materiale sanitario di consumo'      => 'MATERIALI SANITARI DI CONSUMO',
            'materiali sanitari di consumo'       => 'MATERIALI SANITARI DI CONSUMO',
            'ossigeno'                            => 'OSSIGENO',
            'automezzi'                           => 'AMMORTAMENTO AUTOMEZZI',
            'impianti radio'                      => 'AMMORTAMENTO IMPIANTI RADIO',
            'attrezzature ambulanze'              => 'AMMORTAMENTO ATTREZZATURA SANITARIA',
        ];

        foreach ($alias as $cfg => $leg) {
            $cfgN = self::norm($cfg);
            $legN = self::norm($leg);
            if (isset($ripByNormDesc[$legN])) {
                $ripByNormDesc[$cfgN] = $ripByNormDesc[$legN];
            }
        }

        // ------------------------------------------------------------
        // 3) Aggregati diretti/bilancio (nuovo schema + fallback legacy)
        // ------------------------------------------------------------
        [
            $dirByVoceByConv,  // [idVoce][idConv] => costo lordo
            $dirTotByVoce,     // [idVoce] => somma costi lordi
            $bilancioByVoce,   // [idVoce] => importo bilancio (globale voce, con priorità manuali/legacy)
            $ammByVoceByConv,  // [idVoce][idConv] => ammortamento/sconto
            $ammTotByVoce,     // [idVoce] => tot ammortamenti/sconti
            $netByVoceByConv,  // [idVoce][idConv] => (diretti - ammortamento)
            $netTotByVoce      // [idVoce] => somma netti
        ] = self::aggregatiDirettiEBilancio($idAssociazione, $anno, $vociConfig, $ripByNormDesc);

        // ===== OVERRIDE BILANCIO PERSONALE 6001..6006 dal foglio costi dipendenti =====
        $bilPers = self::bilancioPersonalePerVoci($idAssociazione, $anno);
        foreach (self::$IDS_PERSONALE_RETRIBUZIONI as $vId) {
            $vId = (int)$vId;
            if (isset($bilPers[$vId])) {
                $bilancioByVoce[$vId] = (float)$bilPers[$vId];
            }
        }

        // Mappa tipologia->sezione (uno a uno)
        $tipToSez = [
            2  => 2,
            3  => 3,
            4  => 4,
            5  => 5,
            6  => 6,
            7  => 7,
            8  => 8,
            9  => 9,
            10 => 10,
            11 => 11,
        ];

        // ------------------------------------------------------------
        // 4) Costruzione righe di output
        // ------------------------------------------------------------
        $righe = [];

        foreach ($vociConfig as $vc) {
            $idV   = (int)$vc->id;
            $sez   = (int)($tipToSez[$vc->idTipologiaRiepilogo] ?? 0);
            $descN = self::norm($vc->descrizione);

            $bilancio    = (float)($bilancioByVoce[$idV] ?? 0.0);
            $dirTotLordo = (float)($dirTotByVoce[$idV] ?? 0.0);
            $ammTot      = (float)($ammTotByVoce[$idV] ?? 0.0);
            $dirTotNetto = (float)($netTotByVoce[$idV] ?? ($dirTotLordo - $ammTot));

            // Indiretti da ripartire = (Bilancio voce) - (Diretti netti voce), non negativi
            $baseIndiretti = max(0.0, $bilancio - $dirTotNetto);

            // Eventuale riga legacy di riparto già calcolato (per alcune voci)
            $ripRow = $ripByNormDesc[$descN] ?? null;

            // Inizializzo mappa indiretti per convenzione
            $indPerConv = array_fill_keys($convIds, 0.0);

            // 4.a) Personale 6001..6006: importi già calcolati per convenzione → usali tali e quali
            // - bilancio già override sopra (bilancioByVoce)
            // - qui gli importi del foglio dipendenti sono il "target consuntivo" per convenzione
            // - gli indiretti devono essere SOLO la differenza rispetto ai diretti netti già imputati a mano
            if (isset($persPerQualByConv[$idV])) {
                foreach ($convIds as $cid) {

                    // importo calcolato dal foglio "DIST.RIPARTO COSTI DIPENDENTI" per questa voce+convenzione
                    $targetConv = (float)($persPerQualByConv[$idV][$cid] ?? 0.0);

                    // diretti netti (quelli inseriti a mano) per questa voce+convenzione
                    $dirL = (float)($dirByVoceByConv[$idV][$cid] ?? 0.0);
                    $amm  = (float)($ammByVoceByConv[$idV][$cid] ?? 0.0);
                    $netDirConv = $dirL - $amm;

                    // indiretti = differenza (non negativa)
                    $indPerConv[$cid] = max(0.0, $targetConv - $netDirConv);
                }
            } elseif ($idV === $VOCE_SCIV_ID) {
                $quote = self::splitByWeightsCents($baseIndiretti, $percServCivile);
                foreach ($convIds as $cid) {
                    $indPerConv[$cid] = $quote[$cid] ?? 0.0;
                }

                // 4.c) Formazioni volontari: 6010, 6011, 6012 → % servizi
            } elseif (in_array($idV, $IDS_VOLONTARI_FORMAZIONE_SERVIZI, true)) {
                $pesi  = self::percentualiServiziByConvenzione($idAssociazione, $anno, $convIds);
                $quote = self::splitByWeightsCents($baseIndiretti, $pesi);
                foreach ($convIds as $cid) {
                    $indPerConv[$cid] = $quote[$cid] ?? 0.0;
                }

                // 4.d) Voci a ricavi (volontari “economici”, sezione 5, amministrativi, quote amm., beni strumentali)
            } elseif (
                in_array($idV, $IDS_VOLONTARI_RICAVI, true)
                || $sez === 5
                || in_array($idV, $IDS_ADMIN_RICAVI, true)
                || in_array($idV, $IDS_QUOTE_AMMORTAMENTO, true)
                || $idV === $BENI_STRUMENTALI_ID
                || in_array($idV, $IDS_BENI_STRUMENTALI, true)
            ) {
                $pesi  = self::weightsRicaviConFallback($idAssociazione, $anno, $convIds, $quoteRicavi);
                $quote = self::splitByWeightsCents($baseIndiretti, $pesi);
                foreach ($convIds as $cid) {
                    $indPerConv[$cid] = $quote[$cid] ?? 0.0;
                }

                // 4.e) Legacy disponibile: usa direttamente le quote storiche
            } elseif ($ripRow) {
                foreach ($convIds as $cid) {
                    $nome           = $convenzioni[$cid];
                    $indPerConv[$cid] = (float)($ripRow[$nome] ?? 0.0);
                }

                // 4.f) Default: pro-rata sui diretti NETTI per convenzione
            } else {
                $pesi = [];
                foreach ($convIds as $cid) {
                    $net         = (float)($netByVoceByConv[$idV][$cid] ?? 0.0);
                    $pesi[$cid] = max($net, 0.0);
                }

                $quote = self::splitByWeightsCents($baseIndiretti, $pesi);
                foreach ($convIds as $cid) {
                    $indPerConv[$cid] = $quote[$cid] ?? 0.0;
                }
            }

            // 4.g) Costruzione riga finale
            $riga = [
                'idVoceConfig' => $idV,
                'voce'         => $vc->descrizione,
                'sezione_id'   => $sez,
                'bilancio'     => $bilancio,
                'diretta'      => $dirTotNetto,
                'totale'       => 0.0, // calcolato a fine riga
            ];

            foreach ($convIds as $cid) {
                $nome = $convenzioni[$cid];
                $dirL = (float)($dirByVoceByConv[$idV][$cid] ?? 0.0);
                $amm  = (float)($ammByVoceByConv[$idV][$cid] ?? 0.0);
                $ind  = (float)$indPerConv[$cid];

                $riga[$nome] = [
                    'diretti'      => $dirL,
                    'ammortamento' => $amm,
                    'indiretti'    => $ind,
                ];
            }

            // “Totale” = somma indiretti (Excel-like, arrotondo solo alla fine)
            $riga['totale'] = self::sumLateRound($indPerConv);

            $righe[] = $riga;
        }

        return [
            'data'        => $righe,
            'convenzioni' => $convNomi,
        ];
    }

    /* ========================= SUPPORTO % SERVIZI ========================= */

    public static function percentualiServiziByConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        if (empty($convIds)) {
            return [];
        }

        $rows = DB::table('automezzi_servizi as s')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 's.idAutomezzo')
            ->where('a.idAssociazione', $idAssociazione)
            ->where('a.idAnno', $anno)
            ->whereIn('s.idConvenzione', $convIds)
            ->select('s.idConvenzione', DB::raw('SUM(s.NumeroServizi) AS n'))
            ->groupBy('s.idConvenzione')
            ->get();

        $tot    = 0.0;
        $counts = array_fill_keys($convIds, 0.0);

        foreach ($rows as $r) {
            $counts[(int)$r->idConvenzione] = (float)$r->n;
            $tot                            += (float)$r->n;
        }

        $perc = array_fill_keys($convIds, 0.0);
        if ($tot > 0) {
            foreach ($convIds as $id) {
                $perc[$id] = $counts[$id] / $tot;
            }
        }

        return $perc;
    }

    public static function percentualiServizioCivileByConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        if (empty($convIds)) {
            return [];
        }

        $rows = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->where('ds.idDipendente', RipartizioneServizioCivile::ID_SERVIZIO_CIVILE)
            ->whereIn('ds.idConvenzione', $convIds)
            ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as ore'))
            ->groupBy('ds.idConvenzione')
            ->get();

        $tot = 0.0;
        $ore = array_fill_keys($convIds, 0.0);

        foreach ($rows as $r) {
            $ore[(int)$r->idConvenzione] = (float)$r->ore;
            $tot                         += (float)$r->ore;
        }

        $perc = array_fill_keys($convIds, 0.0);
        if ($tot > 0) {
            foreach ($convIds as $id) {
                $perc[$id] = $ore[$id] / $tot;
            }
        }

        return $perc;
    }

    /** Somma per voce+conv dei (diretti - ammortamento) + indiretti calcolati */
    public static function consuntiviPerVoceByConvenzione(
        int $idAssociazione,
        int $anno
    ): array {
        $conv = self::convenzioni($idAssociazione, $anno);
        $dist = self::distintaImputazioneData($idAssociazione, $anno);

        $righe = $dist['data'] ?? [];

        $out = [];
        foreach ($righe as $riga) {
            $idVoce = (int)($riga['idVoceConfig'] ?? 0);
            if ($idVoce <= 0) {
                continue;
            }

            foreach ($conv as $idConv => $nomeConv) {
                $dir    = (float)($riga[$nomeConv]['diretti']      ?? 0.0);
                $sconto = (float)($riga[$nomeConv]['ammortamento'] ?? 0.0);
                $ind    = (float)($riga[$nomeConv]['indiretti']    ?? 0.0);

                $out[$idVoce][$idConv] = ($dir - $sconto + $ind);
            }
        }

        return $out;
    }

    public static function getOssigenoConsumo(int $idAssociazione, int $idAnno, int $idAutomezzo): float {
        $totaleBilancio = CostoOssigeno::getTotale($idAssociazione, $idAnno);
        if ($totaleBilancio <= 0) {
            return 0.0;
        }

        $dati = RipartizioneOssigeno::getRipartizione($idAssociazione, $idAnno);

        $totaleInclusi    = (float)($dati['totale_inclusi'] ?? 0);
        $serviziAutomezzo = 0.0;

        foreach ($dati['righe'] as $riga) {
            if (!empty($riga['is_totale'])) continue;
            if (empty($riga['incluso_riparto'])) continue;

            if ((int)($riga['idAutomezzo'] ?? 0) === $idAutomezzo) {
                $serviziAutomezzo = (float)($riga['totale'] ?? 0);
                break;
            }
        }

        if ($totaleInclusi <= 0 || $serviziAutomezzo <= 0) {
            return 0.0;
        }

        return round(($serviziAutomezzo / $totaleInclusi) * $totaleBilancio, 2);
    }

    public static function importiPersonalePerQualificaByConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        if (empty($convIds)) {
            return [];
        }

        // Qualifica -> Voce
        $voceByQualifica = [
            1 => 6001, // Autista soccorritore
            6 => 6002, // Coordinatore tecnico
            3 => 6003, // Addetto pulizia
            2 => 6004, // Addetto logistica
            7 => 6005, // Impiegato amministrativo
            5 => 6006, // Coordinatore amministrativo
        ];

        $dip = DB::table('dipendenti')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->get(['idDipendente']);

        if ($dip->isEmpty()) {
            return [];
        }

        $dipIds = $dip->pluck('idDipendente')
            ->map(fn($v) => (int)$v)
            ->all();

        // Costi annui dipendente (base + diretti)
        $costi = DB::table('costi_personale')
            ->selectRaw("
            idDipendente,
            COALESCE(Retribuzioni,0)+COALESCE(costo_diretto_Retribuzioni,0)            AS Retr,
            COALESCE(OneriSocialiInps,0)+COALESCE(costo_diretto_OneriSocialiInps,0)    AS Inps,
            COALESCE(OneriSocialiInail,0)+COALESCE(costo_diretto_OneriSocialiInail,0)  AS Inail,
            COALESCE(TFR,0)+COALESCE(costo_diretto_TFR,0)                              AS Tfr,
            COALESCE(Consulenze,0)+COALESCE(costo_diretto_Consulenze,0)                AS Cons
        ")
            ->whereIn('idDipendente', $dipIds)
            ->where('idAnno', $anno)
            ->get()
            ->keyBy('idDipendente');

        // Qualifiche per dipendente
        $qualByDip = DB::table('dipendenti_qualifiche')
            ->whereIn('idDipendente', $dipIds)
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($rows) => $rows->pluck('idQualifica')->map(fn($v) => (int)$v)->all());

        // % mansioni per dipendente (se presenti)
        $percByDip = DB::table('costi_personale_mansioni')
            ->whereIn('idDipendente', $dipIds)
            ->where('idAnno', $anno)
            ->get()
            ->groupBy('idDipendente')
            ->map(function ($rows) {
                $m = [];
                foreach ($rows as $r) {
                    $m[(int)$r->idQualifica] = (float)$r->percentuale;
                }
                return $m;
            });

        // Ore per dipendente+convenzione
        $oreRows = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->whereIn('ds.idDipendente', $dipIds)
            ->whereIn('ds.idConvenzione', $convIds)
            ->where('c.idAnno', $anno)
            ->get(['ds.idDipendente', 'ds.idConvenzione', 'ds.OreServizio']);

        // oreTot per dipendente
        $oreTotByDip = [];
        foreach ($oreRows as $r) {
            $d = (int)$r->idDipendente;
            $oreTotByDip[$d] = ($oreTotByDip[$d] ?? 0.0) + (float)$r->OreServizio;
        }

        // ore per dipendente per convenzione (array più comodo)
        $oreByDipConv = []; // [idDip][idConv] => ore
        foreach ($oreRows as $r) {
            $d   = (int)$r->idDipendente;
            $cid = (int)$r->idConvenzione;
            $oreByDipConv[$d][$cid] = ($oreByDipConv[$d][$cid] ?? 0.0) + (float)$r->OreServizio;
        }

        // Output in centesimi
        $outCents = [];
        foreach ([6001, 6002, 6003, 6004, 6005, 6006] as $v) {
            foreach ($convIds as $cid) {
                $outCents[$v][(int)$cid] = 0;
            }
        }

        foreach ($dipIds as $idDip) {
            $c = $costi[$idDip] ?? null;
            if (!$c) continue;

            $totDipEuro = (float)$c->Retr
                + (float)$c->Inps
                + (float)$c->Inail
                + (float)$c->Tfr
                + (float)$c->Cons;

            if ($totDipEuro <= 0) continue;

            $totDipCents = (int)round($totDipEuro * 100, 0, PHP_ROUND_HALF_UP);

            $qDip = $qualByDip[$idDip] ?? [];
            if (empty($qDip)) continue;

            // % per qualifica: se assenti -> 100% se una sola, altrimenti uniforme
            $perc = $percByDip[$idDip] ?? [];
            if (empty($perc)) {
                if (count($qDip) === 1) {
                    $perc[(int)$qDip[0]] = 100.0;
                } else {
                    $u = 100.0 / count($qDip);
                    foreach ($qDip as $q) $perc[(int)$q] = $u;
                }
            }

            // 1) split dipendente -> voci (centesimi) con Hamilton
            $provVoce = [];
            $remVoce  = [];
            $sumVoce  = 0;

            foreach ($perc as $idQ => $pct) {
                $idQ   = (int)$idQ;
                $voceId = $voceByQualifica[$idQ] ?? null;
                if (!$voceId) continue;

                $quota = $totDipCents * ((float)$pct / 100.0);
                $f     = (int)floor($quota);

                $provVoce[$voceId] = ($provVoce[$voceId] ?? 0) + $f;
                $remVoce[$voceId]  = ($remVoce[$voceId] ?? 0.0) + ($quota - $f);
                $sumVoce          += $f;
            }

            $dRem = $totDipCents - $sumVoce;
            if ($dRem > 0 && !empty($remVoce)) {
                arsort($remVoce);
                foreach (array_keys($remVoce) as $vId) {
                    if ($dRem <= 0) break;
                    $provVoce[(int)$vId] += 1;
                    $dRem--;
                }
            }

            // 2) per OGNI voce 6001..6006 split su convenzioni per ORE (Hamilton)
            $oreTot = (float)($oreTotByDip[$idDip] ?? 0.0);

            foreach ($provVoce as $vId => $centiVoce) {
                $vId = (int)$vId;
                $centiVoce = (int)$centiVoce;
                if ($centiVoce <= 0) continue;

                // pesi = ore del dipendente per convenzione
                $weights = [];
                $sumW = 0.0;
                foreach ($convIds as $cid) {
                    $cid = (int)$cid;
                    $w = (float)($oreByDipConv[$idDip][$cid] ?? 0.0);
                    $weights[$cid] = $w;
                    $sumW += $w;
                }

                // fallback: se ore tutte 0 -> uniforme
                if ($sumW <= 0.0) {
                    foreach ($convIds as $cid) $weights[(int)$cid] = 1.0;
                    $sumW = (float)count($convIds);
                }

                // Hamilton in centesimi (stesso schema del tuo)
                $prov = [];
                $rem  = [];
                $sum  = 0;

                foreach ($weights as $cid => $w) {
                    $raw = $centiVoce * ((float)$w / $sumW);
                    $f   = (int)floor($raw);
                    $prov[(int)$cid] = $f;
                    $rem[(int)$cid]  = $raw - $f;
                    $sum += $f;
                }

                $diff = $centiVoce - $sum;
                if ($diff > 0) {
                    arsort($rem);
                    foreach (array_keys($rem) as $cid) {
                        if ($diff <= 0) break;
                        $prov[(int)$cid] += 1;
                        $diff--;
                    }
                }

                foreach ($prov as $cid => $cents) {
                    $outCents[$vId][(int)$cid] += (int)$cents;
                }
            }
        }

        // ritorno in euro
        $out = [];
        foreach ($outCents as $vId => $byConv) {
            foreach ($byConv as $cid => $cent) {
                $out[$vId][(int)$cid] = round(((int)$cent) / 100, 2, PHP_ROUND_HALF_UP);
            }
        }

        return $out;
    }


    public static function consuntivoPersonalePerConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        if (empty($convIds)) {
            return [];
        }

        // Importi per voce+conv dalla distinta (diretti - amm + indiretti)
        $byVoce = self::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        $voceIds = range(6001, 6014);
        $out     = array_fill_keys($convIds, 0.0);

        foreach ($voceIds as $vId) {
            $map = $byVoce[$vId] ?? [];
            foreach ($convIds as $cid) {
                $out[$cid] += (float)($map[$cid] ?? 0.0);
            }
        }

        // arrotondo a 2 decimali
        foreach ($convIds as $cid) {
            $out[$cid] = round($out[$cid], 2);
        }

        return $out;
    }

    /**
     * PREVENTIVO & CONSUNTIVO "Costo del personale" (voci 6001..6014) per convenzione.
     *
     * Ritorna:
     *  [
     *    'preventivo' => [ idConvenzione => float, ... ],
     *    'consuntivo' => [ idConvenzione => float, ... ],
     *  ]
     */
    public static function personalePrevConsPerConvenzione(
        int $idAssociazione,
        int $anno
    ): array {
        // 1) Convenzioni ordinate come sempre
        $conv = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        if (empty($conv)) {
            return [
                'preventivo' => [],
                'consuntivo' => [],
            ];
        }

        $convIds = array_keys($conv);

        // =========================
        // PREVENTIVO: riepilogo_dati
        // =========================
        $idRiepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->value('idRiepilogo');

        $prevByConv = array_fill_keys($convIds, 0.0);

        if ($idRiepilogo) {
            $prevRows = DB::table('riepilogo_dati')
                ->where('idRiepilogo', $idRiepilogo)
                ->whereBetween('idVoceConfig', [6001, 6014])
                ->whereIn('idConvenzione', $convIds)
                ->select('idConvenzione', DB::raw('SUM(preventivo) AS prev'))
                ->groupBy('idConvenzione')
                ->get();

            foreach ($prevRows as $r) {
                $cid             = (int)$r->idConvenzione;
                $prevByConv[$cid] = round((float)$r->prev, 2);
            }
        }

        // =========================
        // CONSUNTIVO: Distinta Imputazione
        // =========================
        $byVoce = self::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        $consByConv = array_fill_keys($convIds, 0.0);
        foreach (range(6001, 6014) as $voceId) {
            if (empty($byVoce[$voceId])) {
                continue;
            }
            foreach ($convIds as $cid) {
                $consByConv[$cid] += (float)($byVoce[$voceId][$cid] ?? 0.0);
            }
        }

        foreach ($convIds as $cid) {
            $prevByConv[$cid] = round($prevByConv[$cid], 2);
            $consByConv[$cid] = round($consByConv[$cid], 2);
        }

        return [
            'preventivo' => $prevByConv,
            'consuntivo' => $consByConv,
        ];
    }

    /** Normalizza pesi con fallback (servizi/ore/ricavi) */
    private static function normalizzaPesi(
        ?array $valori,
        array $convIds,
        string $fallback,
        array $serv,
        array $ric
    ): array {
        $p   = array_fill_keys($convIds, 0.0);
        $sum = 0.0;

        foreach ($convIds as $id) {
            $p[$id] = (float)($valori[$id] ?? 0.0);
            $sum    += $p[$id];
        }

        if ($sum > 0) {
            return $p;
        }

        // fallback
        $src = $fallback === 'servizi'
            ? $serv
            : ($fallback === 'ore' ? $serv : $ric);

        foreach ($convIds as $id) {
            $p[$id] = (float)($src[$id] ?? 0.0);
        }

        return $p;
    }

    private static function ripartisciPerServizi(
        float $valore,
        string $voce,
        array $serviziPerConv,
        float $totaleServizi,
        array $convenzioni
    ): array {
        // pesi: NumeroServizi per convenzione
        $pesi = [];
        foreach ($convenzioni as $idConv => $_) {
            $pesi[$idConv] = (float)($serviziPerConv[$idConv] ?? 0.0);
        }

        $quote = self::splitByWeightsCents($valore, $pesi);

        $riga = [
            'voce'   => $voce,
            'totale' => round($valore, 2),
        ];

        foreach ($convenzioni as $idConv => $nomeConv) {
            $riga[$nomeConv] = $quote[$idConv] ?? 0.0;
        }

        return $riga;
    }

    /**
     * true se per la convenzione vale il regime “mezzi sostitutivi”
     *  - flag abilita_rot_sost = 1
     *  - esiste un mezzo con is_titolare = 1
     *  - % TRADIZIONALE del titolare (km mezzo su conv / km totali mezzo) >= 98
     */
    public static function isRegimeMezziSostitutivi(int $idConv): bool {
        $conv = DB::table('convenzioni')
            ->where('idConvenzione', $idConv)
            ->where('abilita_rot_sost', 1)
            ->first();

        if (!$conv) {
            return false;
        }

        // prendo il titolare dichiarato
        $tit = DB::table('automezzi_km')
            ->select('idAutomezzo', DB::raw('COALESCE(KMPercorsi,0) AS kmConv'))
            ->where('idConvenzione', $idConv)
            ->where('is_titolare', 1)
            ->first();

        if (!$tit) {
            return false;
        }

        $totKmMezzo = (float)DB::table('automezzi_km')
            ->where('idAutomezzo', $tit->idAutomezzo)
            ->sum('KMPercorsi');

        $kmConv   = (float)($tit->kmConv ?? 0);
        $percTrad = $totKmMezzo > 0
            ? ($kmConv / $totKmMezzo) * 100.0
            : 0.0;

        return $percTrad >= 98.0;
    }

    /**
     * true se per la convenzione vale il regime “rotazione mezzi”
     *  - flag abilita_rot_sost = 1
     *  - esiste un mezzo con is_titolare = 1
     *  - % TRADIZIONALE del titolare < 98
     */
    public static function isRegimeRotazione(int $idConv): bool {
        $conv = DB::table('convenzioni')
            ->where('idConvenzione', $idConv)
            ->where('abilita_rot_sost', 1)
            ->first();

        if (!$conv) return false;

        $tit = DB::table('automezzi_km')
            ->select('idAutomezzo', DB::raw('COALESCE(KMPercorsi,0) AS kmConv'))
            ->where('idConvenzione', $idConv)
            ->where('is_titolare', 1)
            ->first();

        if (!$tit) return false;

        $kmConv = (float)($tit->kmConv ?? 0);

        // km totali convenzione (tutti i mezzi che hanno viaggiato)
        $totKmMezzo = (float)DB::table('automezzi_km')
            ->where('idAutomezzo', $tit->idAutomezzo)
            ->sum(DB::raw('COALESCE(KMPercorsi,0)'));

        $percTrad = ($totKmMezzo > 0) ? ($kmConv / $totKmMezzo) * 100.0 : 0.0;

        return ($percTrad < 98.0);
    }


    /** Voci interessate dalla ROTAZIONE (render lato UI) */
    public static function vociRotazioneUI(): array {
        return [
            'LEASING/NOLEGGIO A LUNGO TERMINE',
            'ASSICURAZIONI',
            'MANUTENZIONE ORDINARIA',
            'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI',
            'PULIZIA E DISINFEZIONE',
            'INTERESSI PASS. F.TO, LEASING, NOL.',
            'AMMORTAMENTO AUTOMEZZI',
            'ALTRI COSTI MEZZI',
        ];
    }

    /** Voci interessate dai MEZZI SOSTITUTIVI (render lato UI) */
    public static function vociSostitutiviUI(): array {
        return [
            'LEASING/NOLEGGIO A LUNGO TERMINE',
            'ASSICURAZIONI',
            'MANUTENZIONE ORDINARIA',
            'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI',
            'PULIZIA E DISINFEZIONE',
            'INTERESSI PASS. F.TO, LEASING, NOL.',
            'MANUTENZIONE ATTREZZATURA SANITARIA',
            'LEASING ATTREZZATURA SANITARIA',
            'AMMORTAMENTO AUTOMEZZI',
            'AMMORTAMENTO ATTREZZATURA SANITARIA',
            'ALTRI COSTI MEZZI',
        ];
    }

    /** Mappa convenzioni per regime (id=>nome), indipendente dal mezzo */
    public static function convenzioniPerRegime(int $idAssociazione, int $anno): array {
        $conv = self::convenzioni($idAssociazione, $anno); // [id=>nome]
        $rot  = [];
        $sost = [];

        foreach ($conv as $idC => $nome) {
            if (self::isRegimeRotazione($idC)) {
                $rot[$idC] = $nome;
            }
            if (self::isRegimeMezziSostitutivi($idC)) {
                $sost[$idC] = $nome;
            }
        }

        return [
            'rotazione'   => $rot,
            'sostitutivi' => $sost,
        ];
    }

    /** Somma senza arrotondare sugli addendi; arrotonda SOLO alla fine. */
    private static function sumLateRound(array $vals): float {
        $sum = 0.0;
        foreach ($vals as $v) {
            $sum += (float)$v;
        }
        return round($sum, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Ripartizione in **centesimi** con metodo "largest remainder" (Hamilton).
     * $totalEuro: importo totale in euro.
     * $weights:   [chiave => peso non normalizzato] (km, servizi, ricavi, ecc.)
     * Ritorna     [chiave => euro con 2 decimali], somma esatta = $totalEuro.
     */
    private static function splitByWeightsCents(float $totalEuro, array $weights): array {
        $keys = array_keys($weights);
        if ($totalEuro <= 0 || empty($keys)) {
            return array_fill_keys($keys, 0.0);
        }

        $sumW = 0.0;
        foreach ($weights as $w) {
            $sumW += (float)$w;
        }

        if ($sumW <= 0) {
            return array_fill_keys($keys, 0.0);
        }

        $totCents = (int)round($totalEuro * 100, 0, PHP_ROUND_HALF_UP);

        $floor    = [];
        $frac     = [];
        $sumFloor = 0;

        foreach ($weights as $k => $w) {
            $raw         = $totCents * ((float)$w / $sumW);
            $f           = (int)floor($raw);
            $floor[$k]   = $f;
            $frac[$k]    = $raw - $f;
            $sumFloor   += $f;
        }

        // Distribuisci i centesimi rimanenti ai più “meritevoli”
        $res = $totCents - $sumFloor;
        if ($res > 0) {
            arsort($frac);
            foreach (array_keys($frac) as $k) {
                if ($res <= 0) break;
                $floor[$k] += 1;
                $res--;
            }
        }

        // Torna in euro
        $out = [];
        foreach ($floor as $k => $cents) {
            $out[$k] = round($cents / 100, 2, PHP_ROUND_HALF_UP);
        }

        return $out;
    }

    // in RipartizioneCostiService (o helper condiviso)
    private static function carburantiNetti(object $costi = null): float {
        $carb = (float)($costi->Carburanti ?? 0);
        $utf  = (float)($costi->RimborsiUTF ?? 0);

        return round($carb - $utf, 2, PHP_ROUND_HALF_UP);
        // NIENTE max(0), così vedi anche i negativi se ci sono
    }

    private static function weightsRicaviConFallback(
        int $idAssociazione,
        int $anno,
        array $convIds,
        array $quoteRicavi
    ): array {
        // 1) prova coi ricavi
        $pesi   = array_fill_keys($convIds, 0.0);
        $sumRic = 0.0;

        foreach ($convIds as $id) {
            $w        = (float)($quoteRicavi[$id] ?? 0.0);
            $pesi[$id] = $w;
            $sumRic   += $w;
        }

        // se i ricavi sono tutti zero o “praticamente concentrati” (una sola convenzione >0),
        // fallback ai servizi
        $nonZero = array_sum(array_map(fn($v) => $v > 0 ? 1 : 0, $pesi));

        if ($sumRic <= 0.0 || $nonZero <= 1) {
            $serv    = self::percentualiServiziByConvenzione($idAssociazione, $anno, $convIds);
            $sumServ = 0.0;
            foreach ($convIds as $id) {
                $sumServ += (float)($serv[$id] ?? 0.0);
            }

            if ($sumServ > 0) {
                foreach ($convIds as $id) {
                    $pesi[$id] = (float)($serv[$id] ?? 0.0);
                }
            } else {
                // ultimo fallback: uniforme
                foreach ($convIds as $id) {
                    $pesi[$id] = 1.0;
                }
            }
        }

        return $pesi;
    }

    private static function kmPerMezzoEConvenzione(int $idAssociazione, int $anno): array {
        $rows = DB::table('automezzi_km AS ak')
            ->join('convenzioni AS c', 'c.idConvenzione', '=', 'ak.idConvenzione')
            ->select('ak.idAutomezzo', 'ak.idConvenzione', DB::raw('SUM(ak.KMPercorsi) AS km'))
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->groupBy('ak.idAutomezzo', 'ak.idConvenzione')
            ->get();

        $out = [
            'conv' => [],
        ];

        foreach ($rows as $r) {
            $idConv  = (int)$r->idConvenzione;
            $idMezzo = (int)$r->idAutomezzo;
            $km      = (float)$r->km;

            $out['conv'][$idConv][$idMezzo] = $km;
        }

        return $out;
    }

    private static function costiPerMezzoSoloSostitutivi(int $anno): array {
        $target = array_map(fn($s) => self::norm($s), self::VOCI_MEZZI_SOSTITUTIVI);

        // mappa voce → colonna DB (tutte le colonne reali del mezzo)
        $colonne = [
            'LEASING/NOLEGGIO A LUNGO TERMINE'                           => 'LeasingNoleggio',
            'ASSICURAZIONI'                                              => 'Assicurazione',
            'MANUTENZIONE ORDINARIA'                                     => 'ManutenzioneOrdinaria',
            'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI'  => null, // caso speciale
            'PULIZIA E DISINFEZIONE'                                     => 'PuliziaDisinfezione',
            'INTERESSI PASS. F.TO, LEASING, NOL.'                        => 'InteressiPassivi',
            'MANUTENZIONE ATTREZZATURA SANITARIA'                        => 'ManutenzioneSanitaria',
            'LEASING ATTREZZATURA SANITARIA'                             => 'LeasingSanitaria',
            'AMMORTAMENTO AUTOMEZZI'                                     => 'AmmortamentoMezzi',
            'AMMORTAMENTO ATTREZZATURA SANITARIA'                        => 'AmmortamentoSanitaria',
            'ALTRI COSTI MEZZI'                                          => 'AltriCostiMezzi',
        ];

        // costi base mezzi
        $rows = DB::table('costi_automezzi')
            ->join('automezzi', 'automezzi.idAutomezzo', '=', 'costi_automezzi.idAutomezzo')
            ->select('costi_automezzi.*', 'automezzi.idAssociazione')
            ->where('costi_automezzi.idAnno', $anno)
            ->get();

        // costi radio per associazione
        $radio = DB::table('costi_radio')
            ->where('idAnno', $anno)
            ->get()
            ->keyBy('idAssociazione');

        $out = [];

        foreach ($rows as $r) {
            $idMezzo = (int)$r->idAutomezzo;
            $idAss   = (int)$r->idAssociazione;

            $radioRow = $radio[$idAss] ?? null;

            $tot = 0.0;

            foreach (self::VOCI_MEZZI_SOSTITUTIVI as $voce) {
                $vNorm = self::norm($voce);
                if (!in_array($vNorm, $target, true)) {
                    continue;
                }

                $colDB = $colonne[$voce] ?? null;

                // voce speciale
                if ($voce === 'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI') {
                    $val = (float)$r->ManutenzioneStraordinaria
                        - (float)$r->RimborsiAssicurazione;
                } else {
                    $val = $colDB ? (float)($r->$colDB ?? 0) : 0.0;
                }

                // aggiungi eventuali voci radio attinenti
                if ($voce === 'MANUTENZIONE ATTREZZATURA SANITARIA') {
                    $val += (float)($radioRow->ManutenzioneApparatiRadio ?? 0);
                }
                if ($voce === 'LEASING ATTREZZATURA SANITARIA') {
                    $val += (float)($radioRow->MontaggioSmontaggioRadio118 ?? 0);
                }

                $tot += max(0.0, $val);
            }

            $out[$idMezzo] = round($tot, 2);
        }

        return $out;
    }

    public static function costoNettoMezziSostitutiviFromDistinta(int $idAssociazione, int $anno): array {
        $distinta = self::distintaImputazioneData($idAssociazione, $anno);
        $voci     = self::VOCI_MEZZI_SOSTITUTIVI;
        $data     = isset($distinta['data']) ? $distinta['data'] : array();
        $convNomi = isset($distinta['convenzioni']) ? $distinta['convenzioni'] : array();

        // normalizza elenco voci target in upper + spazi compressi
        $target = array();
        foreach ($voci as $v) {
            $t = strtoupper(trim(preg_replace('/\s+/', ' ', $v)));
            $target[$t] = true;
        }

        $totCentsByConv = array();

        foreach ($data as $riga) {
            $voceRaw = isset($riga['voce']) ? $riga['voce'] : '';
            $voce    = strtoupper(trim(preg_replace('/\s+/', ' ', $voceRaw)));

            if (!isset($target[$voce])) {
                continue;
            }

            foreach ($convNomi as $nomeConv) {
                if (!isset($riga[$nomeConv]) || !is_array($riga[$nomeConv])) {
                    continue;
                }

                $dir = isset($riga[$nomeConv]['diretti']) ? (float)$riga[$nomeConv]['diretti'] : 0.0;
                $amm = isset($riga[$nomeConv]['ammortamento']) ? (float)$riga[$nomeConv]['ammortamento'] : 0.0;
                $ind = isset($riga[$nomeConv]['indiretti']) ? (float)$riga[$nomeConv]['indiretti'] : 0.0;

                // niente round qui
                $net   = $dir - $amm + $ind;
                $cents = (int)round($net * 100, 0, PHP_ROUND_HALF_UP);

                if (!isset($totCentsByConv[$nomeConv])) {
                    $totCentsByConv[$nomeConv] = 0;
                }
                $totCentsByConv[$nomeConv] += $cents;
            }
        }

        $out = array();
        foreach ($totCentsByConv as $nomeConv => $cents) {
            $out[$nomeConv] = round($cents / 100, 2, PHP_ROUND_HALF_UP);
        }

        return $out;
    }

    private static function kmEPercentualiMezzoPerConvenzione($idAutomezzo, $anno, $convIds) {
        // km mezzo per convenzione (SOMMATI)
        $rows = DB::table('automezzi_km as k')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
            ->where('k.idAutomezzo', $idAutomezzo)
            ->where('c.idAnno', $anno)
            ->whereIn('k.idConvenzione', $convIds)
            ->select('k.idConvenzione', DB::raw('SUM(COALESCE(k.KMPercorsi,0)) AS km'))
            ->groupBy('k.idConvenzione')
            ->get();

        $kmPerConv = array();
        foreach ($convIds as $cid) {
            $kmPerConv[(int)$cid] = 0.0;
        }

        foreach ($rows as $r) {
            $cid = (int)$r->idConvenzione;
            $kmPerConv[$cid] = (float)$r->km;
        }

        $totKmMezzo = 0.0;
        foreach ($kmPerConv as $v) {
            $totKmMezzo += (float)$v;
        }

        $percPerConv = array();
        foreach ($kmPerConv as $cid => $km) {
            $percPerConv[$cid] = ($totKmMezzo > 0) ? ((float)$km / (float)$totKmMezzo) : 0.0; // 0..1
        }

        return array($kmPerConv, $totKmMezzo, $percPerConv);
    }

    /**
     * Bilancio (importo bil. consuntivo) per le voci 6001..6006 preso dai costi_personale.
     * Regola:
     * - dipendente con 1 qualifica: 100% del Totale
     * - dipendente con più qualifiche: usa % in costi_personale_mansioni, se mancano -> split uniforme
     * Ritorna: [6001=>float, 6002=>float, ...] in EURO con 2 decimali.
     */
    public static function bilancioPersonalePerVoci(int $idAssociazione, int $anno): array {
        $outCents = array();
        foreach (self::$IDS_PERSONALE_RETRIBUZIONI as $v) {
            $outCents[(int)$v] = 0;
        }

        // dipendenti dell'associazione/anno
        $dip = DB::table('dipendenti')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('idDipendente');

        if (!$dip || count($dip) === 0) {
            // ritorna zeri
            $out = array();
            foreach ($outCents as $v => $c) $out[$v] = 0.0;
            return $out;
        }

        $dipIds = array();
        foreach ($dip as $id) $dipIds[] = (int)$id;

        // costi_personale totali per dipendente (base+diretti)
        $costi = DB::table('costi_personale')
            ->selectRaw("
            idDipendente,
            (
              COALESCE(Retribuzioni,0)+COALESCE(costo_diretto_Retribuzioni,0)
            + COALESCE(OneriSocialiInps,0)+COALESCE(costo_diretto_OneriSocialiInps,0)
            + COALESCE(OneriSocialiInail,0)+COALESCE(costo_diretto_OneriSocialiInail,0)
            + COALESCE(TFR,0)+COALESCE(costo_diretto_TFR,0)
            + COALESCE(Consulenze,0)+COALESCE(costo_diretto_Consulenze,0)
            ) AS TotaleCalc
        ")
            ->whereIn('idDipendente', $dipIds)
            ->where('idAnno', $anno)
            ->get()
            ->keyBy('idDipendente');

        // qualifiche per dipendente
        $qualByDip = DB::table('dipendenti_qualifiche')
            ->whereIn('idDipendente', $dipIds)
            ->get()
            ->groupBy('idDipendente');

        // percentuali mansioni per dipendente (se esistono)
        $percRows = DB::table('costi_personale_mansioni')
            ->whereIn('idDipendente', $dipIds)
            ->where('idAnno', $anno)
            ->get()
            ->groupBy('idDipendente');

        // helper: percentuali per dipendente (idQualifica => pct)
        $percByDip = array();
        foreach ($percRows as $idDip => $rows) {
            $m = array();
            foreach ($rows as $r) {
                $m[(int)$r->idQualifica] = (float)$r->percentuale;
            }
            $percByDip[(int)$idDip] = $m;
        }

        // accumulo in centesimi per voce
        foreach ($dipIds as $idDip) {
            $c = isset($costi[$idDip]) ? $costi[$idDip] : null;
            if (!$c) continue;

            $totEuro = (float)$c->TotaleCalc;
            if ($totEuro <= 0) continue;

            $totCents = (int) round($totEuro * 100, 0, PHP_ROUND_HALF_UP);

            $qRows = isset($qualByDip[$idDip]) ? $qualByDip[$idDip] : null;
            if (!$qRows || count($qRows) === 0) continue;

            $qualIds = array();
            foreach ($qRows as $qr) $qualIds[] = (int)$qr->idQualifica;

            // costruisci mappa percentuali: se non ci sono, fallback uniforme
            $pctMap = isset($percByDip[$idDip]) ? $percByDip[$idDip] : array();

            if (empty($pctMap)) {
                if (count($qualIds) === 1) {
                    $pctMap[$qualIds[0]] = 100.0;
                } else {
                    $u = 100.0 / max(count($qualIds), 1);
                    foreach ($qualIds as $qid) $pctMap[$qid] = $u;
                }
            }

            // distribuzione centesimi sulle voci (Hamilton per evitare cent persi)
            $prov = array(); // [voceId => cents floor]
            $rem  = array(); // [voceId => remainder sum]
            $sum  = 0;

            foreach ($pctMap as $idQ => $pct) {
                $idQ = (int)$idQ;
                // prendiamo solo le qualifiche che mappano su 6001..6006
                $voceId = null;
                foreach (self::$MAP_VOCE_TO_QUALIFICA as $vId => $qId) {
                    if ((int)$qId === $idQ) {
                        $voceId = (int)$vId;
                        break;
                    }
                }
                if (!$voceId) continue;

                $quota = $totCents * ((float)$pct / 100.0);
                $f = (int) floor($quota);

                if (!isset($prov[$voceId])) $prov[$voceId] = 0;
                if (!isset($rem[$voceId]))  $rem[$voceId]  = 0.0;

                $prov[$voceId] += $f;
                $rem[$voceId]  += ($quota - $f);
                $sum += $f;
            }

            // residuo centesimi al “più meritevole”
            $diff = $totCents - $sum;
            if ($diff > 0 && !empty($rem)) {
                arsort($rem);
                foreach (array_keys($rem) as $vId) {
                    if ($diff <= 0) break;
                    $prov[(int)$vId] += 1;
                    $diff--;
                }
            }

            foreach ($prov as $vId => $cents) {
                if (!isset($outCents[(int)$vId])) continue;
                $outCents[(int)$vId] += (int)$cents;
            }
        }

        // ritorna in euro
        $out = array();
        foreach ($outCents as $vId => $cents) {
            $out[(int)$vId] = round(((int)$cents) / 100, 2, PHP_ROUND_HALF_UP);
        }
        return $out;
    }
}
