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
    private const IDS_VOLONTARI_RICAVI = [6007, 6008, 6010, 6011, 6012, 6013, 6014];

    /* ========================= MATERIALE SANITARIO / AUTOMEZZI / RADIO ========================= */
    public static function getMaterialiSanitariConsumo(int $idAssociazione, int $idAnno, int $idAutomezzo): float {
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $idAnno);
        if ($totaleBilancio <= 0) return 0.0;

        // ripartizione per mezzo
        $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $idAnno);
        $righe = $dati['righe'] ?? [];
        $convs = collect($dati['convenzioni'] ?? []);

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
            foreach ($convInclIds as $idC) $somma += (float)($valori[$idC] ?? 0);

            $totServiziNetti += $somma;
            if ((int)($r['idAutomezzo'] ?? 0) === $idAutomezzo) $serviziMezzo = $somma;
        }

        if ($totServiziNetti <= 0 || $serviziMezzo <= 0) return 0.0;
        return round(($serviziMezzo / $totServiziNetti) * $totaleBilancio, 2);
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

        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        if (empty($convenzioni)) return [];

        $tabella = [];

        $costi = DB::table('costi_automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->where('idAnno', $anno)
            ->first();

        $kmPerConv = DB::table('automezzi_km')
            ->where('idAutomezzo', $idAutomezzo)
            ->pluck('KMPercorsi', 'idConvenzione')
            ->toArray();
        $totaleKM = array_sum(array_map('floatval', $kmPerConv));

        $serviziPerConv = DB::table('automezzi_servizi')
            ->where('idAutomezzo', $idAutomezzo)
            ->pluck('NumeroServizi', 'idConvenzione')
            ->toArray();
        $totaleServizi = array_sum(array_map('floatval', $serviziPerConv));

        foreach ($vociKm as $voceLabel => $colDB) {
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
                if ($voceLabel === 'AMMORTAMENTO ATTREZZATURA SANITARIA') {
                    $numeratore   = (float)($serviziPerConv[$idConv] ?? 0.0);
                    $denominatore = (float)$totaleServizi;
                } else {
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

            $delta = round($riga['totale'] - $somma, 2);
            if (abs($delta) >= 0.01 && $lastNome !== null) {
                $riga[$lastNome] += $delta;
            }

            $tabella[] = $riga;
        }

        // Materiali sanitari (SERVIZI)
        $valMSC = self::getMaterialiSanitariConsumo($idAssociazione, $anno, $idAutomezzo);
        $riga = ['voce' => 'MATERIALI SANITARI DI CONSUMO', 'totale' => round($valMSC, 2)];
        $somma = 0.0;
        $lastNome = null;
        foreach ($convenzioni as $idConv => $nomeConv) {
            $n = (float)($serviziPerConv[$idConv] ?? 0);
            $imp = ($totaleServizi > 0) ? round(($n / $totaleServizi) * $valMSC, 2) : 0.0;
            $riga[$nomeConv] = $imp;
            $somma += $imp;
            $lastNome = $nomeConv;
        }
        $delta = round($riga['totale'] - $somma, 2);
        if (abs($delta) >= 0.01 && $lastNome !== null) $riga[$lastNome] += $delta;
        $tabella[] = $riga;

        // Ossigeno (SERVIZI)
        $valOss = self::getOssigenoConsumo($idAssociazione, $anno, $idAutomezzo);
        $riga = ['voce' => 'OSSIGENO', 'totale' => round($valOss, 2)];
        $somma = 0.0;
        $lastNome = null;
        foreach ($convenzioni as $idConv => $nomeConv) {
            $n = (float)($serviziPerConv[$idConv] ?? 0);
            $imp = ($totaleServizi > 0) ? round(($n / $totaleServizi) * $valOss, 2) : 0.0;
            $riga[$nomeConv] = $imp;
            $somma += $imp;
            $lastNome = $nomeConv;
        }
        $delta = round($riga['totale'] - $somma, 2);
        if (abs($delta) >= 0.01 && $lastNome !== null) $riga[$lastNome] += $delta;
        $tabella[] = $riga;

        // Radio (riparto % KM dell’automezzo, quota “per automezzo”)
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

            $automezziAll = DB::table('automezzi')
                ->where('idAssociazione', $idAssociazione)
                ->where('idAnno', $anno)
                ->where('incluso_riparto', 1)
                ->count();
            $numAutomezzi = max($automezziAll, 1);

            foreach ($vociRadio as $voceLabel => $campoDB) {
                $importoBase = (float)($costiRadio->$campoDB ?? 0);
                $importoPerAutomezzo = $importoBase / $numAutomezzi;

                $riga   = ['voce' => $voceLabel, 'totale' => round($importoPerAutomezzo, 2)];
                $somma  = 0.0;
                $lastNome = null;

                foreach ($convenzioni as $idConv => $nomeConv) {
                    $km = (float)($kmPerConv[$idConv] ?? 0.0);
                    $quota = ($totaleKM > 0) ? ($km / $totaleKM) : 0.0;
                    $importo = round($importoPerAutomezzo * $quota, 2);
                    $riga[$nomeConv] = $importo;
                    $somma += $importo;
                    $lastNome = $nomeConv;
                }

                $delta = round(round($importoPerAutomezzo, 2) - $somma, 2);
                if (abs($delta) >= 0.01 && $lastNome !== null) $riga[$lastNome] += $delta;

                $tabella[] = $riga;
            }
        }

        return $tabella;
    }

    public static function calcolaTabellaTotale(int $idAssociazione, int $anno): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1) // fix
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

    /** Importi A&B per convenzione (e totale) */
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
     * Diretti per voce/convenzione + bilancio per voce (con fallback legacy e SOMMA bilanci manuali voce+sezione).
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

        $dirByVoceByConv = [];
        $ammByVoceByConv = [];
        $netByVoceByConv = [];
        $dirTotByVoce    = [];
        $ammTotByVoce    = [];
        $netTotByVoce    = [];
        $bilByVoce       = [];

        foreach ($cdId as $r) {
            $v   = (int) $r->idVoceConfig;
            $c   = (int) $r->idConvenzione;
            $dir = (float) $r->sum_costo;
            $amm = (float) $r->sum_amm;

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
            $mapDescToId[self::norm($vc->descrizione)] = (int) $vc->id;
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
            if (!$desc || !isset($mapDescToId[$desc])) continue;
            $v = $mapDescToId[$desc];
            $c = (int) $r->idConvenzione;

            $dirByVoceByConv[$v][$c] = ($dirByVoceByConv[$v][$c] ?? 0) + (float) $r->sum_costo;
            $dirTotByVoce[$v]        = ($dirTotByVoce[$v] ?? 0) + (float) $r->sum_costo;
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
            $sezioneByVoceId[(int) $vc->id] = (int) $vc->idTipologiaRiepilogo;
        }

        // helper whitelist
        $voceAmmessa = static function (int $idVoce, ?int $sezioneRiga): bool {
            $sezVoce = (int) $sezioneRiga;
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
            $voceIdByDesc[self::norm($vc->descrizione)] = (int) $vc->id;
        }

        foreach ($bilanciManuali as $r) {
            $descN = self::norm($r->voce ?? '');
            $idVoce = $voceIdByDesc[$descN] ?? null;
            if (!$idVoce) continue;

            // coerenza sezione (se diverso, skip)
            $sezVoce = $sezioneByVoceId[$idVoce] ?? null;
            if ($sezVoce !== null && (int)$r->idSezione !== (int)$sezVoce) continue;

            if (!$voceAmmessa($idVoce, (int)$r->idSezione)) continue;

            $bilByVoce[$idVoce] = ($bilByVoce[$idVoce] ?? 0) + (float)$r->tot;
        }
        /* ================================================================================================================ */

        // Bilancio per voce (priorità: bilByVoce -> legacy -> diretti)
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

        return [
            $dirByVoceByConv,
            $dirTotByVoce,
            $bilancioByVoce,
            $ammByVoceByConv,
            $ammTotByVoce,
            $netByVoceByConv,
            $netTotByVoce
        ];
    }

    /* ========================= DISTINTA IMPUTAZIONE COSTI ========================= */
    public static function distintaImputazioneData(int $idAssociazione, int $anno): array {
        $convenzioni = self::convenzioni($idAssociazione, $anno);
        if (empty($convenzioni)) return ['data' => [], 'convenzioni' => []];

        $convIds  = array_keys($convenzioni);
        $convNomi = array_values($convenzioni);

        $quoteRicavi         = self::quoteRicaviByConvenzione($idAssociazione, $anno, $convIds);
        $persPerQualByConv   = self::importiPersonalePerQualificaByConvenzione($idAssociazione, $anno, $convIds);
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

        $percServCivile = self::percentualiServizioCivileByConvenzione($idAssociazione, $anno, $convIds);
        $VOCE_SCIV_ID   = 6009;

        $IDS_ADMIN_RICAVI       = [8001, 8002, 8003, 8004, 8005, 8006, 8007];
        $IDS_QUOTE_AMMORTAMENTO = [9002, 9003, 9006, 9007, 9008, 9009];
        $BENI_STRUMENTALI_ID  = 10001;
        $IDS_BENI_STRUMENTALI = [11001, 11002];

        $vociConfig = DB::table('riepilogo_voci_config as vc')
            ->select('vc.id', 'vc.descrizione', 'vc.idTipologiaRiepilogo', 'vc.ordinamento')
            ->whereBetween('vc.idTipologiaRiepilogo', [2, 11])
            ->where('vc.attivo', 1)
            ->orderBy('vc.idTipologiaRiepilogo')
            ->orderBy('vc.ordinamento')
            ->orderBy('vc.id')
            ->get();

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

        [
            $dirByVoceByConv,
            $dirTotByVoce,
            $bilancioByVoce,
            $ammByVoceByConv,
            $ammTotByVoce,
            $netByVoceByConv,
            $netTotByVoce
        ] = self::aggregatiDirettiEBilancio($idAssociazione, $anno, $vociConfig, $ripByNormDesc);

        $tipToSez   = [2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11];

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

            // BASE INDIRETTI
            $baseIndiretti = max(0.0, $bilancio - $dirTotNetto);

            $riga = [
                'idVoceConfig' => $idV,
                'voce'         => $vc->descrizione,
                'sezione_id'   => $sez,
                'bilancio'     => $bilancio,
                'diretta'      => $dirTotNetto,
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

                // Personale 6001..6006 già calcolato con funzione dedicata
                // NB: gli ID effettivi delle voci personale dipendono dal tuo config: qui usiamo le mappe calcolate
                if (isset($persPerQualByConv[$idV])) {
                    $ind = (float) ($persPerQualByConv[$idV][$idC] ?? 0.0);
                } elseif ($idV === 6009) { // Servizio Civile
                    $ind = round($baseIndiretti * (float) ($percServCivile[$idC] ?? 0.0), 2);
                } elseif (in_array($idV, self::IDS_VOLONTARI_RICAVI, true)) {
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif (
                    $sez === 5 ||
                    in_array($idV, [8001, 8002, 8003, 8004, 8005, 8006, 8007], true) ||
                    in_array($idV, [9002, 9003, 9006, 9007, 9008, 9009], true) ||
                    $idV === 10001 ||
                    in_array($idV, [11001, 11002], true)
                ) {
                    // % ricavi (gestione struttura, amministrativi, ammortamenti, beni strumentali)
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } else {
                    // legacy per-conv o pro-rata sui diretti NETTI
                    if (is_array($ripRow)) {
                        $ind = (float) ($ripRow[$nomeC] ?? 0);
                    } else {
                        $quota = $dirTotNetto > 0 ? ($net / $dirTotNetto) : 0.0;
                        $ind   = round($baseIndiretti * $quota, 2);
                    }
                }

                $riga[$nomeC] = [
                    'diretti'      => $dirLordo,
                    'ammortamento' => $amm,
                    'indiretti'    => $ind,
                ];

                $sommaInd += $ind;
            }

            // riallineo centesimi: per personale aggancio ai totali; altrove spingo il delta sulla riga
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
                        $sez === 5 || $idV === 6009
                        || in_array($idV, [8001, 8002, 8003, 8004, 8005, 8006, 8007], true)
                        || in_array($idV, [9002, 9003, 9006, 9007, 9008, 9009], true)
                        || $idV === 10001 || in_array($idV, [11001, 11002], true)
                        // ⬇️ NUOVO: volontari % ricavi
                        || in_array($idV, self::IDS_VOLONTARI_RICAVI, true)
                    ) {
                        $delta = round($baseIndiretti - $sommaInd, 2);
                    } elseif (is_array($ripRow)) {
                        $target = 0.0;
                        foreach ($convenzioni as $idC2 => $nomeC2) $target += (float) ($ripRow[$nomeC2] ?? 0);
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

    /** Somma per voce+conv dei (diretti - ammortamento) + indiretti calcolati */
    public static function consuntiviPerVoceByConvenzione(int $idAssociazione, int $anno): array {
        $conv = self::convenzioni($idAssociazione, $anno);
        $dist = self::distintaImputazioneData($idAssociazione, $anno);
        $righe = $dist['data'] ?? [];

        $out = [];
        foreach ($righe as $riga) {
            $idVoce = (int)($riga['idVoceConfig'] ?? 0);
            if ($idVoce <= 0) continue;

            foreach ($conv as $idConv => $nomeConv) {
                $dir    = (float)($riga[$nomeConv]['diretti']      ?? 0.0);
                $sconto = (float)($riga[$nomeConv]['ammortamento'] ?? 0.0);
                $ind    = (float)($riga[$nomeConv]['indiretti']    ?? 0.0);
                $out[$idVoce][$idConv] = round($dir - $sconto + $ind, 2);
            }
        }
        return $out;
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

    public static function importiPersonalePerQualificaByConvenzione(
        int $idAssociazione,
        int $anno,
        array $convIds
    ): array {
        if (empty($convIds)) return [];

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

        if ($dip->isEmpty()) return [];

        $dipIds = $dip->pluck('idDipendente')->map(fn($v) => (int)$v)->all();

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

        $qualByDip = DB::table('dipendenti_qualifiche')
            ->whereIn('idDipendente', $dipIds)
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($rows) => $rows->pluck('idQualifica')->map(fn($v) => (int)$v)->all());

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

        $out = [];
        foreach ([6001, 6002, 6003, 6004, 6005, 6006] as $v) {
            foreach ($convIds as $idC) $out[$v][$idC] = 0;
        }

        $totByVoceCents = array_fill_keys([6002, 6003, 6004, 6005, 6006], 0);

        foreach ($dipIds as $idDip) {
            $c = $costi[$idDip] ?? null;
            if (!$c) continue;

            $totDipEuro = (float)$c->Retr + (float)$c->Inps + (float)$c->Inail + (float)$c->Tfr + (float)$c->Cons;
            if ($totDipEuro <= 0) continue;

            $totDipCents = (int) round($totDipEuro * 100, 0, PHP_ROUND_HALF_UP);

            $qDip = $qualByDip[$idDip] ?? [];
            if (empty($qDip)) continue;

            $perc = $percByDip[$idDip] ?? [];
            if (empty($perc)) {
                if (count($qDip) === 1) {
                    $perc[$qDip[0]] = 100.0;
                } else {
                    $u = 100.0 / count($qDip);
                    foreach ($qDip as $q) $perc[$q] = $u;
                }
            }

            $provVoce = [];
            $remVoce  = [];
            $sumVoce  = 0;

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

            foreach ($provVoce as $vId => $centi) {
                if ($vId === 6001) {
                    $oreTot = (float)($oreTotByDip[$idDip] ?? 0);
                    if ($oreTot <= 0) continue;

                    $provConv = [];
                    $remConv  = [];
                    $sum      = 0;

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
                    $totByVoceCents[$vId] += $centi;
                }
            }
        }

        // pesi = % servizi per convenzione (fallback uniforme)
        $quote = self::percentualiServiziByConvenzione($idAssociazione, $anno, $convIds);
        $sumW = 0.0;
        foreach ($convIds as $id) $sumW += (float)($quote[$id] ?? 0.0);
        $weights = ($sumW > 0) ? $quote : array_fill_keys($convIds, 1.0);
        if ($sumW <= 0) $sumW = (float) count($convIds);

        foreach ([6002, 6003, 6004, 6005, 6006] as $vId) {
            $tot = (int) ($totByVoceCents[$vId] ?? 0);
            if ($tot <= 0) continue;

            $prov = [];
            $rem  = [];
            $sum  = 0;

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

        foreach ($out as $vId => $byConv) {
            foreach ($byConv as $idC => $cent) {
                $out[$vId][$idC] = round(((int)$cent) / 100, 2);
            }
        }

        return $out;
    }

    public static function consuntivoPersonalePerConvenzione(int $idAssociazione, int $anno, array $convIds): array {
        if (empty($convIds)) return [];

        // Importi per voce+conv dalla distinta (diretti - amm + indiretti)
        $byVoce = self::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        $voceIds = range(6001, 6014);
        $out = array_fill_keys($convIds, 0.0);

        foreach ($voceIds as $vId) {
            $map = $byVoce[$vId] ?? [];
            foreach ($convIds as $cid) {
                $out[$cid] += (float)($map[$cid] ?? 0.0);
            }
        }

        // arrotondo a 2 decimali
        foreach ($convIds as $cid) $out[$cid] = round($out[$cid], 2);
        return $out;
    }

    /**
     * PREVENTIVO personale per convenzione:
     * - legge i totali di bilancio_preventivo per voce 6001..6014 (inclusi “bilanci manuali” whitelisted)
     * - ripartisce per convenzione usando le stesse pesature del consuntivo:
     *   6001: % ore; 6002..6006: % servizi; 6009: % SC; 6007/6008/6010..6014: % ricavi.
     */
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
            return ['preventivo' => [], 'consuntivo' => []];
        }

        $convIds = array_keys($conv);

        // =========================
        // PREVENTIVO: riepilogo_dati
        // =========================
        // Prendo il riepilogo dell'associazione/anno e sommo i preventivi
        // per le sole voci 6001..6014, raggruppando per convenzione.
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
                $cid = (int) $r->idConvenzione;
                $prevByConv[$cid] = round((float) $r->prev, 2);
            }
        }

        // =========================
        // CONSUNTIVO: Distinta Imputazione (regole speciali già dentro)
        // =========================
        $byVoce = self::consuntiviPerVoceByConvenzione($idAssociazione, $anno);

        $consByConv = array_fill_keys($convIds, 0.0);
        foreach (range(6001, 6014) as $voceId) {
            if (empty($byVoce[$voceId])) continue;
            foreach ($convIds as $cid) {
                $consByConv[$cid] += (float) ($byVoce[$voceId][$cid] ?? 0.0);
            }
        }

        // Arrotondo e sistemo i centesimi
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
    private static function normalizzaPesi(?array $valori, array $convIds, string $fallback, array $serv, array $ric): array {
        $p = array_fill_keys($convIds, 0.0);
        $sum = 0.0;
        foreach ($convIds as $id) {
            $p[$id] = (float)($valori[$id] ?? 0.0);
            $sum += $p[$id];
        }
        if ($sum > 0) return $p;

        // fallback
        $src = $fallback === 'servizi' ? $serv : ($fallback === 'ore' ? $serv : $ric);
        foreach ($convIds as $id) $p[$id] = (float)($src[$id] ?? 0.0);
        return $p;
    }
}
