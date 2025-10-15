<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\CostoMaterialeSanitario;
use App\Models\CostoOssigeno;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\RipartizioneOssigeno;
use App\Models\Convenzione;
use App\Models\Automezzo;
use App\Models\RipartizioneServizioCivile;
use App\Models\AutomezzoKmRiferimento;


class RipartizioneCostiService {
    /* ========================= MATERIALE SANITARIO / AUTOMEZZI / RADIO ========================= */
    public static function getMaterialiSanitariConsumo(int $idAssociazione, int $idAnno, int $idAutomezzo): float {
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $idAnno);
        if ($totaleBilancio <= 0) return 0.0;

        $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $idAnno);

        // stesso “-1” usato nella pagina di imputazione (se presente la convenzione tecnica)
        $applyMinusOne = method_exists(Convenzione::class, 'checkMaterialeSanitario')
            ? (Convenzione::checkMaterialeSanitario($idAssociazione, $idAnno) === true)
            : false;

        $totaleInclusiAdj    = 0.0;
        $serviziAutomezzoAdj = 0.0;

        foreach ($dati['righe'] as $riga) {
            if (!empty($riga['is_totale'])) continue;
            if (empty($riga['incluso_riparto'])) continue;

            $n = (float)($riga['totale'] ?? 0);
            if ($applyMinusOne) $n = max(0.0, $n - 1.0);

            $totaleInclusiAdj += $n;
            if ((int)($riga['idAutomezzo'] ?? 0) === $idAutomezzo) {
                $serviziAutomezzoAdj = $n;
            }
        }

        if ($totaleInclusiAdj <= 0 || $serviziAutomezzoAdj <= 0) return 0.0;
        return round(($serviziAutomezzoAdj / $totaleInclusiAdj) * $totaleBilancio, 2);
    }


    public static function calcolaRipartizione(int $idAssociazione, int $anno, float $totaleBilancio, ?int $idAutomezzo = null): array {
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

    public static function calcoloRipartizioneOssigeno(int $idAssociazione, int $anno, float $totaleBilancio, ?int $idAutomezzo = null): array {
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

    public static function calcoloRipartizioneCostiRadio(int $idAssociazione, int $anno, ?int $idAutomezzo = null): array {
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

        if (!$costiRadio) return [];

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
                    $ripartizione[$voce] = $totaleServizi > 0 ? round(($riga->NumeroServizi / $totaleServizi) * $importoTotale, 2) : 0;
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

    public static function calcolaRipartizioneTabellaFinale(int $idAssociazione, int $anno, int $idAutomezzo): array {
    // Voci ripartite per KM (Materiali e Ossigeno gestite a parte)
    $vociKm = [
        'LEASING/NOLEGGIO A LUNGO TERMINE'                          => 'LeasingNoleggio',
        'ASSICURAZIONI'                                             => 'Assicurazione',
        'MANUTENZIONE ORDINARIA'                                    => 'ManutenzioneOrdinaria',
        'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI' => 'ManutenzioneStraordinaria',
        'RIMBORSI ASSICURAZIONE'                                    => 'RimborsiAssicurazione',
        'PULIZIA E DISINFEZIONE'                                    => 'PuliziaDisinfezione',
        'CARBURANTI AL NETTO RIMBORSI UTIF'                         => 'Carburanti',
        'ADDITIVI'                                                  => 'Additivi',
        'INTERESSI PASS. F.TO, LEASING, NOL.'                       => 'InteressiPassivi',
        'MANUTENZIONE ATTREZZATURA SANITARIA'                       => 'ManutenzioneSanitaria',
        'LEASING ATTREZZATURA SANITARIA'                            => 'LeasingSanitaria',
        'AMMORTAMENTO AUTOMEZZI'                                    => 'AmmortamentoMezzi',
        'AMMORTAMENTO ATTREZZATURA SANITARIA'                       => 'AmmortamentoSanitaria',
        'ALTRI COSTI MEZZI'                                         => 'AltriCostiMezzi',
    ];

    // Convenzioni (ordine stabile)
    $convenzioni = DB::table('convenzioni')
        ->where('idAssociazione', $idAssociazione)
        ->where('idAnno', $anno)
        ->orderBy('ordinamento')
        ->orderBy('idConvenzione')
        ->pluck('Convenzione', 'idConvenzione')
        ->toArray();

    if (empty($convenzioni)) {
        return [];
    }

    $tabella = [];

    // ---------- PRE-CALCOLI PER L'AUTOMEZZO ----------
    $costi = DB::table('costi_automezzi')
        ->where('idAutomezzo', $idAutomezzo)
        ->where('idAnno', $anno)
        ->first();

    // KM per convenzione
    $kmPerConv = DB::table('automezzi_km')
        ->where('idAutomezzo', $idAutomezzo)
        ->pluck('KMPercorsi', 'idConvenzione')
        ->toArray();
    $totaleKM = array_sum(array_map('floatval', $kmPerConv));

    // SERVIZI per convenzione
    $serviziPerConv = DB::table('automezzi_servizi')
        ->where('idAutomezzo', $idAutomezzo)
        ->pluck('NumeroServizi', 'idConvenzione')
        ->toArray();
    $totaleServizi = array_sum(array_map('floatval', $serviziPerConv));

    // ---------- SEZIONE MEZZI (KM salvo eccezioni) ----------
    foreach ($vociKm as $voceLabel => $colDB) {
        // Calcolo valore della voce (con "netti" dove richiesto)
        if ($costi) {
            switch ($voceLabel) {
                case 'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI':
                    $valore = ((float)($costi->ManutenzioneStraordinaria ?? 0))
                            - ((float)($costi->RimborsiAssicurazione ?? 0));
                    break;

                case 'CARBURANTI AL NETTO RIMBORSI UTIF':
                    $valore = ((float)($costi->Carburanti ?? 0))
                            - ((float)($costi->RimborsiUTF ?? 0));
                    break;

                default:
                    $valore = (float)($costi->$colDB ?? 0);
            }
        } else {
            $valore = 0.0;
        }

        $valore = round(max(0.0, $valore), 2);

        $riga = ['voce' => $voceLabel, 'totale' => $valore];
        $somma = 0.0;
        $lastNome = null;

        foreach ($convenzioni as $idConv => $nomeConv) {
            // 👇 eccezione: AMMORTAMENTO ATTREZZATURA SANITARIA a % SERVIZI (mezzo)
            if ($voceLabel === 'AMMORTAMENTO ATTREZZATURA SANITARIA') {
                $numeratore   = (float)($serviziPerConv[$idConv] ?? 0.0);
                $denominatore = (float)$totaleServizi;
            } else {
                // default: a % KM (mezzo)
                $numeratore   = (float)($kmPerConv[$idConv] ?? 0.0);
                $denominatore = (float)$totaleKM;
            }

            $importo = ($denominatore > 0)
                ? round(($numeratore / $denominatore) * $valore, 2)
                : 0.0;

            $riga[$nomeConv] = $importo;
            $somma += $importo;
            $lastNome = $nomeConv;
        }

        // riallineo centesimi
        $delta = round($riga['totale'] - $somma, 2);
        if (abs($delta) >= 0.01 && $lastNome !== null) {
            $riga[$lastNome] += $delta;
        }

        $tabella[] = $riga;
    }

    // ---------- MATERIALI SANITARI DI CONSUMO (SERVIZI) ----------
    $valoreMSC = self::getMaterialiSanitariConsumo($idAssociazione, $anno, $idAutomezzo);

    $riga = ['voce' => 'MATERIALI SANITARI DI CONSUMO', 'totale' => round($valoreMSC, 2)];
    $somma = 0.0;
    $lastNome = null;

    foreach ($convenzioni as $idConv => $nomeConv) {
        $n = (float)($serviziPerConv[$idConv] ?? 0);
        $importo = ($totaleServizi > 0) ? round(($n / $totaleServizi) * $valoreMSC, 2) : 0.0;
        $riga[$nomeConv] = $importo;
        $somma += $importo;
        $lastNome = $nomeConv;
    }
    $delta = round($riga['totale'] - $somma, 2);
    if (abs($delta) >= 0.01 && $lastNome !== null) {
        $riga[$lastNome] += $delta;
    }
    $tabella[] = $riga;

    // ---------- OSSIGENO (SERVIZI) ----------
    $valoreOss = self::getOssigenoConsumo($idAssociazione, $anno, $idAutomezzo);

    $riga = ['voce' => 'OSSIGENO', 'totale' => round($valoreOss, 2)];
    $somma = 0.0;
    $lastNome = null;

    foreach ($convenzioni as $idConv => $nomeConv) {
        $n = (float)($serviziPerConv[$idConv] ?? 0);
        $importo = ($totaleServizi > 0) ? round(($n / $totaleServizi) * $valoreOss, 2) : 0.0;
        $riga[$nomeConv] = $importo;
        $somma += $importo;
        $lastNome = $nomeConv;
    }
    $delta = round($riga['totale'] - $somma, 2);
    if (abs($delta) >= 0.01 && $lastNome !== null) {
        $riga[$lastNome] += $delta;
    }
    $tabella[] = $riga;

    // ---------- SEZIONE RADIO (ripartizione per % KM dell’automezzo) ----------
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

        $automezzi = Automezzo::getByAssociazione($idAssociazione, $anno);
        $numAutomezzi = max(count($automezzi), 1);

        foreach ($vociRadio as $voceLabel => $campoDB) {
            $importoBase = (float)($costiRadio->$campoDB ?? 0);
            // Importo “per automezzo”, poi ripartito tra le sue convenzioni
            $importoPerAutomezzo = ($numAutomezzi > 0) ? ($importoBase / $numAutomezzi) : 0.0;

            $riga   = ['voce' => $voceLabel, 'totale' => round($importoPerAutomezzo, 2)];
            $somma  = 0.0;
            $lastNome = null;

            foreach ($convenzioni as $idConv => $nomeConv) {
                $km = (float)($kmPerConv[$idConv] ?? 0.0);
                $quota = ($totaleKM > 0) ? ($km / $totaleKM) : 0.0; // % km dell’automezzo
                $importo = round($importoPerAutomezzo * $quota, 2);
                $riga[$nomeConv] = $importo;
                $somma += $importo;
                $lastNome = $nomeConv;
            }

            // riallineo centesimi
            $delta = round(round($importoPerAutomezzo, 2) - $somma, 2);
            if (abs($delta) >= 0.01 && $lastNome !== null) {
                $riga[$lastNome] += $delta;
            }

            $tabella[] = $riga;
        }
    }

    return $tabella;
}


    public static function calcolaTabellaTotale(int $idAssociazione, int $anno): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', operator: 1)
            ->pluck('idAutomezzo');

        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        $tot = [];

        foreach ($automezzi as $idAutomezzo) {
            $tabella = self::calcolaRipartizioneTabellaFinale($idAssociazione, $anno, $idAutomezzo);

            foreach ($tabella as $riga) {
                $voce = $riga['voce'];
                if (!isset($tot[$voce])) {
                    $tot[$voce] = ['voce' => $voce, 'totale' => 0];
                    foreach ($convenzioni as $conv) $tot[$voce][$conv] = 0;
                }
                $tot[$voce]['totale'] += (float) ($riga['totale'] ?? 0);
                foreach ($convenzioni as $conv) $tot[$voce][$conv] += (float) ($riga[$conv] ?? 0);
            }
        }

        return array_values($tot);
    }

    public static function getCostiDiretti(int $idAssociazione, int $anno): array {
        return DB::table('costi_diretti')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('costo', 'idConvenzione')
            ->toArray();
    }

    public static function estraiVociDaRiepilogoDati(int $idAssociazione, int $anno, array $tipologie): array {
        $idRiepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->value('idRiepilogo');

        if (!$idRiepilogo) return [];

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
            $out[(int) $voce->idTipologiaRiepilogo][] = trim(mb_strtoupper($voce->descrizione, 'UTF-8'));
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
    public static function quoteRicaviByConvenzione(int $idAssociazione, int $anno, array $convIds): array {
        $rows = DB::table('rapporti_ricavi')
            ->select('idConvenzione', 'Rimborso')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereIn('idConvenzione', $convIds)
            ->get();

        $tot = (float) $rows->sum('Rimborso');
        $out = array_fill_keys($convIds, 0.0);
        foreach ($convIds as $id) {
            $val      = (float) ($rows->firstWhere('idConvenzione', $id)->Rimborso ?? 0);
            $out[$id] = $tot > 0 ? ($val / $tot) : 0.0;
        }
        return $out;
    }

    /**
     * Importi A&B per convenzione (0 = se assenti), e totale.
     */
    public static function importiAutistiBarellieriByConvenzione(int $idAssociazione, int $anno, array $convIds): array {
        $subOre = DB::table('dipendenti_servizi')
            ->select('idDipendente', DB::raw('SUM(OreServizio) AS ore_tot'))
            ->groupBy('idDipendente');

        $rows = DB::table('dipendenti as d')
            ->join('dipendenti_qualifiche as dq', 'dq.idDipendente', '=', 'd.idDipendente')
            ->join('qualifiche as q', 'q.id', '=', 'dq.idQualifica')
            ->leftJoin('costi_personale as cp', function ($j) use ($anno) {
                $j->on('cp.idDipendente', '=', 'd.idDipendente')->where('cp.idAnno', '=', $anno);
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
            $val = round((float) $r->importo, 2);
            $out[(int) $r->idConvenzione] = $val;
            $tot += $val;
        }
        return [$out, $tot];
    }

    /** Normalizzatore */
    private static function norm(?string $s): string {
        $s = (string) $s;
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($s)), 'UTF-8');
    }

    /**
     * Diretti per voce/convenzione + bilancio per voce (con fallback legacy).
     */
    public static function aggregatiDirettiEBilancio(int $idAssociazione, int $anno, Collection|array $vociConfig, Collection|array $ripByNormDesc): array {
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

        $dirByVoceByConv = [];   // lordo
        $ammByVoceByConv = [];   // ammortamento
        $netByVoceByConv = [];   // netto = diretti - ammortamento
        $dirTotByVoce    = [];   // lordo totale voce
        $ammTotByVoce    = [];   // amm totale voce
        $netTotByVoce    = [];   // netto totale voce
        $bilByVoce       = [];

        foreach ($cdId as $r) {
            $v = (int) $r->idVoceConfig;
            $c = (int) $r->idConvenzione;
            $dir = (float) $r->sum_costo;
            $amm = (float) $r->sum_amm;

            $dirByVoceByConv[$v][$c] = ($dirByVoceByConv[$v][$c] ?? 0) + $dir;
            $ammByVoceByConv[$v][$c] = ($ammByVoceByConv[$v][$c] ?? 0) + $amm;
            $netByVoceByConv[$v][$c] = ($netByVoceByConv[$v][$c] ?? 0) + ($dir - $amm);

            $dirTotByVoce[$v] = ($dirTotByVoce[$v] ?? 0) + $dir;
            $ammTotByVoce[$v] = ($ammTotByVoce[$v] ?? 0) + $amm;
            $netTotByVoce[$v] = ($netTotByVoce[$v] ?? 0) + ($dir - $amm);

            $bilByVoce[$v] = ($bilByVoce[$v] ?? 0) + (float) $r->sum_bilancio;
        }

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

        $mapDescToId = [];
        foreach ($vociConfig as $vc) $mapDescToId[self::norm($vc->descrizione)] = (int) $vc->id;

        foreach ($cdNo as $r) {
            $desc = self::norm($r->voce ?? '');
            if (!$desc || !isset($mapDescToId[$desc])) continue;
            $v = $mapDescToId[$desc];
            $c = (int) $r->idConvenzione;
            $dirByVoceByConv[$v][$c] = ($dirByVoceByConv[$v][$c] ?? 0) + (float) $r->sum_costo;
            $dirTotByVoce[$v]        = ($dirTotByVoce[$v] ?? 0) + (float) $r->sum_costo;
            $bilByVoce[$v]           = ($bilByVoce[$v] ?? 0) + (float) $r->sum_bilancio;
        }

        $bilancioByVoce = [];
        foreach ($vociConfig as $vc) {
            $v = (int) $vc->id;
            $descNorm = self::norm($vc->descrizione);
            if (!empty($bilByVoce[$v])) {
                $bilancioByVoce[$v] = (float) $bilByVoce[$v];
            } elseif (isset($ripByNormDesc[$descNorm])) {
                $bilancioByVoce[$v] = (float) ($ripByNormDesc[$descNorm]['totale'] ?? 0);
            } else {
                $bilancioByVoce[$v] = (float) ($dirTotByVoce[$v] ?? 0);
            }
        }

        return [$dirByVoceByConv, $dirTotByVoce, $bilancioByVoce, $ammByVoceByConv, $ammTotByVoce, $netByVoceByConv, $netTotByVoce];
    }

    /* ========================= DISTINTA IMPUTAZIONE COSTI ========================= */
    public static function distintaImputazioneData(int $idAssociazione, int $anno): array {
        $convenzioni = self::convenzioni($idAssociazione, $anno);
        if (empty($convenzioni)) return ['data' => [], 'convenzioni' => []];

        $convIds  = array_keys($convenzioni);
        $convNomi = array_values($convenzioni);

        $quoteRicavi   = self::quoteRicaviByConvenzione($idAssociazione, $anno, $convIds);
        $persPerQualByConv = self::importiPersonalePerQualificaByConvenzione($idAssociazione, $anno, $convIds);
        // estraggo gli array per comodità (se mancanti, array vuoti)
        $per6001 = $persPerQualByConv[6001] ?? [];
        $per6002 = $persPerQualByConv[6002] ?? [];
        $per6003 = $persPerQualByConv[6003] ?? [];
        $per6004 = $persPerQualByConv[6004] ?? [];
        $per6005 = $persPerQualByConv[6005] ?? [];
        $per6006 = $persPerQualByConv[6006] ?? [];

        $tot6001 = array_sum($per6001);
        $tot6002 = array_sum($per6002);
        $tot6003 = array_sum($per6003);
        $tot6004 = array_sum($per6004);
        $tot6005 = array_sum($per6005);
        $tot6006 = array_sum($per6006);

        // % Servizio Civile (6009)
        $percServCivile = self::percentualiServizioCivileByConvenzione($idAssociazione, $anno, $convIds);
        $VOCE_SCIV_ID   = 6009;

        // % ricavi
        $IDS_ADMIN_RICAVI       = [8001, 8002, 8003, 8004, 8005, 8006, 8007];
        $IDS_QUOTE_AMMORTAMENTO = [9002, 9003, 9006, 9007, 9008, 9009];

        // BENI STRUMENTALI
        $BENI_STRUMENTALI_ID  = 10001;
        $IDS_BENI_STRUMENTALI = [11001, 11002];

        // Voci attive
        $vociConfig = DB::table('riepilogo_voci_config as vc')
            ->select('vc.id', 'vc.descrizione', 'vc.idTipologiaRiepilogo', 'vc.ordinamento')
            ->whereBetween('vc.idTipologiaRiepilogo', [2, 11])
            ->where('vc.attivo', 1)
            ->orderBy('vc.idTipologiaRiepilogo')
            ->orderBy('vc.ordinamento')
            ->orderBy('vc.id')
            ->get();

        // Legacy per fallback
        $legacy = self::calcolaTabellaTotale($idAssociazione, $anno);
        $ripByNormDesc = [];
        foreach ((array) $legacy as $r) {
            if (!isset($r['voce'])) continue;
            $ripByNormDesc[self::norm($r['voce'])] = $r;
        }
        $alias = [
            'leasing/ noleggio automezzi'         => 'LEASING/NOLEGGIO A LUNGO TERMINE',
            'assicurazione automezzi'             => 'ASSICURAZIONI',
            'manutenzione ordinaria'              => 'MANUTENZIONE ORDINARIA',
            'manutenzione straordinaria'          => 'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI',
            'pulizia e disinfezione automezzi'    => 'PULIZIA E DISINFEZIONE',
            'carburanti'                          => 'CARBURANTI AL NETTO RIMBORSI UTIF',
            'additivi'                            => 'ADDITIVI',
            'interessi pass. f.to, leasing, nol.' => 'INTERESSI PASS. F.TO, LEASING, NOL.',
            'altri costi mezzi'                   => 'ALTRI COSTI MEZZI',
            'manutenzione attrezzatura sanitaria' => 'MANUTENZIONE ATTREZZATURA SANITARIA',
            'leasing attrezzatura sanitaria'      => 'LEASING ATTREZZATURA SANITARIA',
            'manutenzione apparati radio'         => 'MANUTENZIONE APPARATI RADIO',
            'montaggio/smontaggio radio 118'      => 'MONTAGGIO/SMONTAGGIO RADIO 118',
            'canoni locazione ponte radio'        => 'LOCAZIONE PONTE RADIO',
            'materiale sanitario di consumo'      => 'MATERIALE SANITARIO DI CONSUMO',
            'ossigeno'                            => 'OSSIGENO',
            'automezzi'                           => 'AMMORTAMENTO AUTOMEZZI',
            'impianti radio'                      => 'AMMORTAMENTO IMPIANTI RADIO',
            'attrezzature ambulanze'              => 'AMMORTAMENTO ATTREZZATURA SANITARIA',
        ];
        foreach ($alias as $cfg => $leg) {
            $cfgN = self::norm($cfg);
            $legN = self::norm($leg);
            if (isset($ripByNormDesc[$legN])) $ripByNormDesc[$cfgN] = $ripByNormDesc[$legN];
        }

        // === Diretti/ammortamento/netti/bilancio ===
        [
            $dirByVoceByConv,  // lordo per conv
            $dirTotByVoce,     // lordo tot voce
            $bilancioByVoce,
            $ammByVoceByConv,  // amm per conv
            $ammTotByVoce,     // amm tot voce
            $netByVoceByConv,  // netto per conv
            $netTotByVoce      // netto tot voce
        ] = self::aggregatiDirettiEBilancio($idAssociazione, $anno, $vociConfig, $ripByNormDesc);

        $tipToSez   = [2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11];
        $VOCE_AB_ID = 6001;

        $righe = [];

        foreach ($vociConfig as $vc) {
            $idV   = (int) $vc->id;
            $sez   = (int) ($tipToSez[$vc->idTipologiaRiepilogo] ?? 0);
            $descN = self::norm($vc->descrizione);

            $bilancio    = (float) ($bilancioByVoce[$idV] ?? 0);
            $dirTotLordo = (float) ($dirTotByVoce[$idV] ?? 0);
            $ammTot      = (float) ($ammTotByVoce[$idV] ?? 0);
            $dirTotNetto = (float) ($netTotByVoce[$idV] ?? ($dirTotLordo - $ammTot));
            $ripRow      = $ripByNormDesc[$descN] ?? null;

            // BASE INDIRETTI = BILANCIO − DIRETTI_NETTI
            $baseIndiretti = max(0.0, $bilancio - $dirTotNetto);

            $riga = [
                'idVoceConfig' => $idV,
                'voce'         => $vc->descrizione,
                'sezione_id'   => $sez,
                'bilancio'     => $bilancio,
                'diretta'      => $dirTotNetto, // ← aggregata NETTA
                'totale'       => 0.0,
            ];

            $sommaInd = 0.0;
            $ultimoId = null;

            foreach ($convenzioni as $idC => $nomeC) {
                $ultimoId   = $idC;
                $dirLordo   = (float) ($dirByVoceByConv[$idV][$idC] ?? 0);
                $amm        = (float) ($ammByVoceByConv[$idV][$idC] ?? 0);
                $net        = (float) ($netByVoceByConv[$idV][$idC] ?? ($dirLordo - $amm));
                $ind        = 0.0;

                if ($idV === 6001) {           // autisti & barellieri
                    $ind = (float) ($per6001[$idC] ?? 0.0);
                } elseif ($idV === 6002) {     // coordinatori tecnici
                    $ind = (float) ($per6002[$idC] ?? 0.0);
                } elseif ($idV === 6003) {     // addetto pulizia
                    $ind = (float) ($per6003[$idC] ?? 0.0);
                } elseif ($idV === 6004) {     // addetto logistica
                    $ind = (float) ($per6004[$idC] ?? 0.0);
                } elseif ($idV === 6005) {     // personale amministrativo
                    $ind = (float) ($per6005[$idC] ?? 0.0);
                } elseif ($idV === 6006) {     // coordinatori amministrativi
                    $ind = (float) ($per6006[$idC] ?? 0.0);
                } elseif ($idV === $VOCE_SCIV_ID) {
                    // 6009: % Servizio Civile
                    $ind = round($baseIndiretti * (float) ($percServCivile[$idC] ?? 0.0), 2);
                } elseif (in_array($idV, $IDS_ADMIN_RICAVI, true)) {
                    // 8001..: % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif (in_array($idV, $IDS_QUOTE_AMMORTAMENTO, true)) {
                    // 9002..: % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif ($idV === $BENI_STRUMENTALI_ID) {
                    // beni > 516 → % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif ($sez === 5) {
                    // gestione struttura → % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif (in_array($idV, $IDS_BENI_STRUMENTALI, true)) {
                    // altri costi (11001,11002) → % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } else {
                    // legacy/per-conv; altrimenti pro-rata sui diretti NETTI
                    if (is_array($ripRow)) {
                        $ind = (float) ($ripRow[$nomeC] ?? 0);
                    } else {
                        $quota = $dirTotNetto > 0 ? ($net / $dirTotNetto) : 0.0;
                        $ind   = round($baseIndiretti * $quota, 2);
                    }
                }

                $riga[$nomeC] = [
                    'diretti'      => $dirLordo, // mostro lordo
                    'ammortamento' => $amm,      // nuova colonna
                    'indiretti'    => $ind,
                    // 'netto'      => $net,     // opzionale
                ];

                $sommaInd += $ind;
            }

            // riallineo centesimi sugli indiretti
            $delta = 0.0;
            switch ($idV) {
                case 6001:
                    $delta = round($tot6001 - $sommaInd, 2);
                    break;
                case 6002:
                    $delta = round($tot6002 - $sommaInd, 2);
                    break;
                case 6003:
                    $delta = round($tot6003 - $sommaInd, 2);
                    break;
                case 6004:
                    $delta = round($tot6004 - $sommaInd, 2);
                    break;
                case 6005:
                    $delta = round($tot6005 - $sommaInd, 2);
                    break;
                case 6006:
                    $delta = round($tot6006 - $sommaInd, 2);
                    break;

                default:
                    if (
                        $idV === $VOCE_SCIV_ID || $sez === 5 ||
                        in_array($idV, $IDS_ADMIN_RICAVI, true) ||
                        in_array($idV, $IDS_QUOTE_AMMORTAMENTO, true) ||
                        $idV === $BENI_STRUMENTALI_ID ||
                        in_array($idV, $IDS_BENI_STRUMENTALI, true)
                    ) {
                        $delta = round($baseIndiretti - $sommaInd, 2);
                    } elseif (is_array($ripRow)) {
                        // righe legacy/per-conv → allinea alla somma attesa della riga legacy
                        $target = 0.0;
                        foreach ($convenzioni as $idC2 => $nomeC2) {
                            $target += (float) ($ripRow[$nomeC2] ?? 0);
                        }
                        $delta = round($target - $sommaInd, 2);
                    }
            }
            if (abs($delta) >= 0.01 && $ultimoId !== null) {
                $nomeUltima = $convenzioni[$ultimoId];
                $riga[$nomeUltima]['indiretti'] += $delta;
                $sommaInd += $delta;
            }
            $riga['totale'] = $sommaInd;
            $righe[] = $riga;
        }

        return ['data' => $righe, 'convenzioni' => $convNomi];
    }

    /* ========================= SUPPORTO % SERVIZI ========================= */

    /** % (0..1) dei Servizi Svolti per convenzione sull’intera ass./anno */
    public static function percentualiServiziByConvenzione(int $idAssociazione, int $anno, array $convIds): array {
        if (empty($convIds)) return [];

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
            $counts[(int) $r->idConvenzione] = (float) $r->n;
            $tot += (float) $r->n;
        }

        $perc = array_fill_keys($convIds, 0.0);
        if ($tot > 0) foreach ($convIds as $id) $perc[$id] = $counts[$id] / $tot;
        return $perc;
    }

    /** % (0..1) del Servizio Civile per convenzione sull’intera ass./anno */
    public static function percentualiServizioCivileByConvenzione(int $idAssociazione, int $anno, array $convIds): array {
        if (empty($convIds)) return [];

        $rows = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->where('ds.idDipendente', RipartizioneServizioCivile::ID_SERVIZIO_CIVILE)
            ->whereIn('ds.idConvenzione', $convIds)
            ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as ore'))
            ->groupBy('ds.idConvenzione')
            ->get();

        $tot  = 0.0;
        $ore  = array_fill_keys($convIds, 0.0);
        foreach ($rows as $r) {
            $ore[(int) $r->idConvenzione] = (float) $r->ore;
            $tot += (float) $r->ore;
        }

        $perc = array_fill_keys($convIds, 0.0);
        if ($tot > 0) foreach ($convIds as $id) $perc[$id] = $ore[$id] / $tot;
        return $perc;
    }

    public static function consuntiviPerVoceByConvenzione(int $idAssociazione, int $anno): array {
        // [idConv => Nome]
        $conv = self::convenzioni($idAssociazione, $anno);

        // Usa la distinta già calcolata
        $dist  = self::distintaImputazioneData($idAssociazione, $anno);
        $righe = $dist['data'] ?? [];

        $out = []; // [idVoceConfig][idConv] => importo_totale (diretti + indiretti)
        foreach ($righe as $riga) {
            $idVoce = (int)($riga['idVoceConfig'] ?? 0);
            if ($idVoce <= 0) continue;

            foreach ($conv as $idConv => $nomeConv) {
              
                // In distinta le colonne per conv sono per nome:
                // $riga[$nomeConv] = ['diretti' => x, 'ammortamento' => y, 'indiretti' => z]
                $dir = (float)($riga[$nomeConv]['diretti']     ?? 0.0);
                $ind = (float)($riga[$nomeConv]['indiretti']   ?? 0.0);
                $sconto = (float)($riga[$nomeConv]['ammortamento']   ?? 0.0);
                // Se desideri includere anche l'ammortamento diretto, aggiungi anche questa riga:
                // $amm = (float)($riga[$nomeConv]['ammortamento'] ?? 0.0);
                // $out[$idVoce][$idConv] = round($dir + $amm + $ind, 2);

                $out[$idVoce][$idConv] = round($dir - $sconto + $ind, 2); // ← diretti + indiretti
            }
        }
        
        return $out;
    }

    public static function importiPersonalePerQualificaByConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        if (empty($convIds)) return [];

        // Qualifica -> voce (6001..6006)
        $voceByQualifica = [
            1 => 6001, // Autista soccorritore
            6 => 6002, // Coordinatore tecnico
            3 => 6003, // Addetto pulizia
            2 => 6004, // Addetto logistica
            7 => 6005, // Impiegato amministrativo
            5 => 6006, // Coordinatore amministrativo
        ];

        // Dipendenti dell’associazione/anno
        $dip = DB::table('dipendenti')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->get(['idDipendente']);

        if ($dip->isEmpty()) return [];

        $dipIds = $dip->pluck('idDipendente')->map(fn($v) => (int)$v)->all();

        // Costi personale (lordo + diretto)
        $costi = DB::table('costi_personale')
            ->selectRaw("
            idDipendente,
            COALESCE(Retribuzioni,0)+COALESCE(costo_diretto_Retribuzioni,0)         AS Retr,
            COALESCE(OneriSocialiInps,0)+COALESCE(costo_diretto_OneriSocialiInps,0) AS Inps,
            COALESCE(OneriSocialiInail,0)+COALESCE(costo_diretto_OneriSocialiInail,0) AS Inail,
            COALESCE(TFR,0)+COALESCE(costo_diretto_TFR,0)                           AS Tfr,
            COALESCE(Consulenze,0)+COALESCE(costo_diretto_Consulenze,0)             AS Cons
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

        // Percentuali mansioni per dipendente (idQualifica => %)
        $percByDip = DB::table('costi_personale_mansioni')
            ->whereIn('idDipendente', $dipIds)
            ->where('idAnno', $anno)
            ->get()
            ->groupBy('idDipendente')
            ->map(function ($rows) {
                $m = [];
                foreach ($rows as $r) $m[(int)$r->idQualifica] = (float)$r->percentuale;
                return $m;
            });

        // Ore per riparto SOLO 6001 (A&B)
        $oreByDipConv = DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->whereIn('ds.idDipendente', $dipIds)
            ->whereIn('ds.idConvenzione', $convIds)
            ->where('c.idAnno', $anno)
            ->get(['ds.idDipendente', 'ds.idConvenzione', 'ds.OreServizio']);

        $oreTotByDip = [];
        foreach ($oreByDipConv as $r) {
            $d = (int)$r->idDipendente;
            $oreTotByDip[$d] = ($oreTotByDip[$d] ?? 0) + (float)$r->OreServizio;
        }

        // Output iniziale in centesimi
        $out = [];
        foreach ([6001, 6002, 6003, 6004, 6005, 6006] as $v) {
            foreach ($convIds as $idC) $out[$v][$idC] = 0;
        }

        // Totali (in cent) da ripartire per 6002..6006
        $totByVoceCents = array_fill_keys([6002, 6003, 6004, 6005, 6006], 0);

        foreach ($dipIds as $idDip) {
            $c = $costi[$idDip] ?? null;
            if (!$c) continue;

            $totDipEuro = (float)$c->Retr + (float)$c->Inps + (float)$c->Inail + (float)$c->Tfr + (float)$c->Cons;
            if ($totDipEuro <= 0) continue;

            $totDipCents = (int) round($totDipEuro * 100, 0, PHP_ROUND_HALF_UP);

            $qDip = $qualByDip[$idDip] ?? [];
            if (empty($qDip)) continue;

            // Percentuali: se assenti → 100% se 1 qualifica, altrimenti uniforme
            $perc = $percByDip[$idDip] ?? [];
            if (empty($perc)) {
                if (count($qDip) === 1) {
                    $perc[$qDip[0]] = 100.0;
                } else {
                    $u = 100.0 / count($qDip);
                    foreach ($qDip as $q) $perc[$q] = $u;
                }
            }

            // Centesimi per voce (6001..6006) – rounding per-dipendente
            $provVoce = [];
            $remVoce = [];
            $sumVoce = 0;
            foreach ($perc as $idQ => $pct) {
                $voceId = $voceByQualifica[$idQ] ?? null;
                if (!$voceId) continue;
                $quota = $totDipCents * ((float)$pct / 100.0);
                $f = (int) floor($quota);
                $provVoce[$voceId] = ($provVoce[$voceId] ?? 0) + $f;
                $remVoce[$voceId]  = ($remVoce[$voceId]  ?? 0) + ($quota - $f);
                $sumVoce += $f;
            }
            $dRem = $totDipCents - $sumVoce;
            if ($dRem > 0 && !empty($remVoce)) {
                arsort($remVoce);
                foreach (array_keys($remVoce) as $vId) {
                    if ($dRem <= 0) break;
                    $provVoce[$vId] += 1;
                    $dRem--;
                }
            }

            // Distribuzione:
            foreach ($provVoce as $vId => $centi) {
                if ($vId === 6001) {
                    // 6001 → riparto per convenzione in base alle ore del dipendente
                    $oreTot = (float)($oreTotByDip[$idDip] ?? 0);
                    if ($oreTot <= 0) continue; // senza ore non ripartiamo A&B

                    $provConv = [];
                    $remConv = [];
                    $sum = 0;
                    foreach ($oreByDipConv->where('idDipendente', $idDip) as $r) {
                        $share = $centi * ((float)$r->OreServizio / $oreTot);
                        $f = (int) floor($share);
                        $idC = (int)$r->idConvenzione;
                        $provConv[$idC] = ($provConv[$idC] ?? 0) + $f;
                        $remConv[$idC]  = ($remConv[$idC]  ?? 0) + ($share - $f);
                        $sum += $f;
                    }
                    $diff = $centi - $sum;
                    if ($diff > 0 && !empty($remConv)) {
                        arsort($remConv);
                        foreach (array_keys($remConv) as $idC) {
                            if ($diff <= 0) break;
                            $provConv[$idC] += 1;
                            $diff--;
                        }
                    }
                    foreach ($provConv as $idC => $cents) $out[6001][$idC] += $cents;
                } else {
                    // 6002..6006 → accumulo totale per qualifica (poi % ricavi)
                    $totByVoceCents[$vId] += $centi;
                }
            }
        }

        // Riparto 6002..6006 per % ricavi (fallback: quote uguali)
        $quote = self::percentualiServiziByConvenzione($idAssociazione, $anno, $convIds);
        $sumW = 0.0;
        foreach ($convIds as $id) $sumW += (float)($quote[$id] ?? 0.0);
        if ($sumW <= 0) {
            $weights = array_fill_keys($convIds, 1.0);
            $sumW = (float) count($convIds);
        }
        $weights = $sumW > 0 ? $quote : array_fill_keys($convIds, 1.0);
        if ($sumW <= 0) $sumW = (float) count($convIds);

        foreach ([6002, 6003, 6004, 6005, 6006] as $vId) {
            $tot = (int) ($totByVoceCents[$vId] ?? 0);
            if ($tot <= 0) continue;

            $prov = [];
            $rem = [];
            $sum = 0;
            foreach ($convIds as $idC) {
                $share = $tot * ((float)$weights[$idC] / $sumW);
                $f = (int) floor($share);
                $prov[$idC] = $f;
                $rem[$idC]  = $share - $f;
                $sum += $f;
            }
            $diff = $tot - $sum;
            if ($diff > 0) {
                arsort($rem);
                foreach (array_keys($rem) as $idC) {
                    if ($diff <= 0) break;
                    $prov[$idC] += 1;
                    $diff--;
                }
            }
            foreach ($convIds as $idC) $out[$vId][$idC] += $prov[$idC];
        }

        // Converto in euro
        foreach ($out as $vId => $byConv) {
            foreach ($byConv as $idC => $cent) {
                $out[$vId][$idC] = round(((int)$cent) / 100, 2);
            }
        }

        return $out; // es: [6001=>[conv=>€], 6002=>[...], ..., 6006=>[...]]
    }

    public static function getOssigenoConsumo(int $idAssociazione, int $idAnno, int $idAutomezzo): float {
        $totaleBilancio = CostoOssigeno::getTotale($idAssociazione, $idAnno);
        if ($totaleBilancio <= 0) return 0.0;

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

        if ($totaleInclusi <= 0 || $serviziAutomezzo <= 0) return 0.0;
        return round(($serviziAutomezzo / $totaleInclusi) * $totaleBilancio, 2);
    }
}
