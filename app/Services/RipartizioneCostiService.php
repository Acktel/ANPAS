<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\CostoMaterialeSanitario;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\Automezzo;
use App\Models\RipartizioneServizioCivile;

class RipartizioneCostiService {
    /* ========================= MATERIALE SANITARIO / AUTOMEZZI / RADIO ========================= */

    public static function getMaterialiSanitariConsumo(int $idAssociazione, int $idAnno, int $idAutomezzo): float {
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $idAnno);

        $automezzi = Automezzo::getByAssociazione($idAssociazione, $idAnno);
        $dati      = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $idAnno);

        $totaleInclusi    = $dati['totale_inclusi'] ?? 0;
        $serviziAutomezzo = 0;

        foreach ($dati['righe'] as $riga) {
            if (($riga['idAutomezzo'] ?? null) == $idAutomezzo && !empty($riga['incluso_riparto'])) {
                $serviziAutomezzo = (float) ($riga['totale'] ?? 0);
                break;
            }
        }

        if ($totaleInclusi <= 0 || $serviziAutomezzo <= 0) return 0.0;
        return round(($serviziAutomezzo / $totaleInclusi) * $totaleBilancio, 2);
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
        $voci = [
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
            'MATERIALI SANITARI DI CONSUMO'                             => 'MaterialiSanitariConsumo',
            'OSSIGENO'                                                  => 'Ossigeno',
        ];

        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        $tabella = [];

        foreach ($voci as $voceLabel => $colDB) {
            $valore = DB::table('costi_automezzi')
                ->where('idAutomezzo', $idAutomezzo)
                ->where('idAnno', $anno)
                ->value($colDB) ?? 0;

            $ripartizione = DB::table('automezzi_km')
                ->where('idAutomezzo', $idAutomezzo)
                ->pluck('KMPercorsi', 'idConvenzione')
                ->toArray();

            $totaleKM = array_sum($ripartizione);

            $riga = ['voce' => $voceLabel, 'totale' => $valore];

            foreach ($convenzioni as $idConv => $nomeConv) {
                $km      = $ripartizione[$idConv] ?? 0;
                $importo = $totaleKM > 0 ? round(($km / $totaleKM) * $valore, 2) : 0;
                $riga[$nomeConv] = $importo;
            }

            $tabella[] = $riga;
        }

        // Costi radio
        $costiRadio = DB::table('costi_radio')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        $vociRadio = [
            'MANUTENZIONE APPARATI RADIO'    => 'ManutenzioneApparatiRadio',
            'MONTAGGIO/SMONTAGGIO RADIO 118' => 'MontaggioSmontaggioRadio118',
            'LOCAZIONE PONTE RADIO'          => 'LocazionePonteRadio',
            'AMMORTAMENTO IMPIANTI RADIO'    => 'AmmortamentoImpiantiRadio',
        ];

        $servizi = DB::table('automezzi_servizi')
            ->where('idAutomezzo', $idAutomezzo)
            ->pluck('NumeroServizi', 'idConvenzione')
            ->toArray();

        $totaleServizi = array_sum($servizi);

        foreach ($vociRadio as $voceLabel => $campoDB) {
            $valore = $costiRadio->$campoDB ?? 0;
            $riga   = ['voce' => $voceLabel, 'totale' => $valore];

            foreach ($convenzioni as $idConv => $nomeConv) {
                $serv    = $servizi[$idConv] ?? 0;
                $importo = $totaleServizi > 0 ? round(($serv / $totaleServizi) * $valore, 2) : 0;
                $riga[$nomeConv] = $importo;
            }

            $tabella[] = $riga;
        }

        return $tabella;
    }

    public static function calcolaTabellaTotale(int $idAssociazione, int $anno): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
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

        $voci = DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->whereIn('idTipologiaRiepilogo', $tipologie)
            ->select('idTipologiaRiepilogo', 'descrizione')
            ->distinct()
            ->orderBy('idTipologiaRiepilogo')
            ->orderBy('descrizione')
            ->get();

        $out = [];
        foreach ($voci as $voce) {
            $out[(int) $voce->idTipologiaRiepilogo][] = trim(strtoupper($voce->descrizione));
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
                               + COALESCE(cp.OneriSociali,0)
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
                DB::raw('SUM(bilancio_consuntivo) as sum_bilancio')
            )
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->whereNotNull('idVoceConfig')
            ->groupBy('idVoceConfig', 'idConvenzione')
            ->get();

        $dirByVoceByConv = [];
        $dirTotByVoce    = [];
        $bilByVoce       = [];

        foreach ($cdId as $r) {
            $v = (int) $r->idVoceConfig;
            $c = (int) $r->idConvenzione;
            $dirByVoceByConv[$v][$c] = ($dirByVoceByConv[$v][$c] ?? 0) + (float) $r->sum_costo;
            $dirTotByVoce[$v]        = ($dirTotByVoce[$v] ?? 0) + (float) $r->sum_costo;
            $bilByVoce[$v]           = ($bilByVoce[$v] ?? 0) + (float) $r->sum_bilancio;
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

        return [$dirByVoceByConv, $dirTotByVoce, $bilancioByVoce];
    }

    /* ========================= DISTINTA IMPUTAZIONE COSTI ========================= */

    public static function distintaImputazioneData(int $idAssociazione, int $anno): array {
        $convenzioni = self::convenzioni($idAssociazione, $anno);
        if (empty($convenzioni)) return ['data' => [], 'convenzioni' => []];

        $convIds  = array_keys($convenzioni);
        $convNomi = array_values($convenzioni);

        $quoteRicavi = self::quoteRicaviByConvenzione($idAssociazione, $anno, $convIds);

        // 6001: importi assoluti
        [$abPerConv, $abTotale] = self::importiAutistiBarellieriByConvenzione($idAssociazione, $anno, $convIds);

        // 6002..6006: % servizi svolti
        $percServizi = self::percentualiServiziByConvenzione($idAssociazione, $anno, $convIds);
        $IDS_SERVIZI = [6002, 6003, 6004, 6005, 6006];

        // 6009: % servizio civile
        $percServCivile = self::percentualiServizioCivileByConvenzione($idAssociazione, $anno, $convIds);
        $VOCE_SCIV_ID   = 6009;

        // 8001...8007:COSTI AMINISTRATIVI: spese postali → % ricavi
        $IDS_ADMIN_RICAVI = [8001, 8002, 8003, 8004, 8005, 8006, 8007];

        // 9002...:COSTI QUOTE AMMORTAMENTO: spese postali → % ricavi
        $IDS_QUOTE_AMMORTAMENTO = [9002, 9003, 9006, 9007, 9008, 9009];

        //10001: BENI STRUMENTALI>516:
        $BENI_STRUMENTALI_ID   = 10001;

        //11001,11002: ALTRI COSTI
        $IDS_BENI_STRUMENTALI   = [11001, 11002];

        // Voci config
        $vociConfig = DB::table('riepilogo_voci_config as vc')
            ->select('vc.id', 'vc.descrizione', 'vc.idTipologiaRiepilogo', 'vc.ordinamento')
            ->whereBetween('vc.idTipologiaRiepilogo', [2, 11])
            ->where('vc.attivo', 1)
            ->orderBy('vc.idTipologiaRiepilogo')
            ->orderBy('vc.ordinamento')
            ->orderBy('vc.id')
            ->get();

        // Legacy (automezzi/radio)
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

        // Diretti/bilancio
        [$dirByVoceByConv, $dirTotByVoce, $bilancioByVoce]
            = self::aggregatiDirettiEBilancio($idAssociazione, $anno, $vociConfig, $ripByNormDesc);

        $tipToSez   = [2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11];
        $VOCE_AB_ID = 6001;

        $righe = [];

        foreach ($vociConfig as $vc) {
            $idV   = (int) $vc->id;
            $sez   = (int) ($tipToSez[$vc->idTipologiaRiepilogo] ?? 0);
            $descN = self::norm($vc->descrizione);

            $bilancio = (float) ($bilancioByVoce[$idV] ?? 0);
            $dirTot   = (float) ($dirTotByVoce[$idV] ?? 0);
            $ripRow   = $ripByNormDesc[$descN] ?? null;

            $baseIndiretti = max(0.0, $bilancio - $dirTot); // “Totale costi ripartiti”

            $riga = [
                'idVoceConfig' => $idV,
                'voce'         => $vc->descrizione,
                'sezione_id'   => $sez,
                'bilancio'     => $bilancio,
                'diretta'      => 0.0,
                'totale'       => 0.0,
            ];

            $sommaInd = 0.0;
            $ultimoId = null;

            foreach ($convenzioni as $idC => $nomeC) {
                $ultimoId = $idC;
                $dir = (float) ($dirByVoceByConv[$idV][$idC] ?? 0);
                $ind = 0.0;

                if ($idV === $VOCE_AB_ID) {
                    $ind = (float) ($abPerConv[$idC] ?? 0.0);
                } elseif (in_array($idV, $IDS_SERVIZI, true)) {
                    // 6002..6006: % servizi svolti
                    $ind = round($baseIndiretti * (float) ($percServizi[$idC] ?? 0.0), 2);
                } elseif ($idV === $VOCE_SCIV_ID) {
                    // 6009: % Servizio Civile
                    $ind = round($baseIndiretti * (float) ($percServCivile[$idC] ?? 0.0), 2);
                } elseif (in_array($idV, $IDS_ADMIN_RICAVI, true)) {
                    // 8001..8002: % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif (in_array($idV, $IDS_QUOTE_AMMORTAMENTO, true)) {
                    // 9002..: % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif ($idV === $BENI_STRUMENTALI_ID) {
                    // 6009: % Servizio Civile
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif ($sez === 5) {
                    // gestione struttura: % ricavi
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } elseif ($idV === $IDS_BENI_STRUMENTALI) {
                    // 6009: % Servizio Civile
                    $ind = round($baseIndiretti * (float) ($quoteRicavi[$idC] ?? 0.0), 2);
                } else {
                    // legacy/per-conv altrimenti pro-rata diretti
                    if (is_array($ripRow)) {
                        $ind = (float) ($ripRow[$nomeC] ?? 0);
                    } else {
                        $quota = $dirTot > 0 ? ($dir / $dirTot) : 0.0;
                        $ind   = round($baseIndiretti * $quota, 2);
                    }
                }

                $riga[$nomeC]    = ['diretti' => $dir, 'indiretti' => $ind];
                $riga['diretta'] += $dir;
                $sommaInd        += $ind;
            }

            // riallineo centesimi
            $delta = 0.0;
            if ($idV === $VOCE_AB_ID) {
                $delta = round($abTotale - $sommaInd, 2);
            } elseif (in_array($idV, $IDS_SERVIZI, true) || $idV === $VOCE_SCIV_ID || $sez === 5) {
                $delta = round($baseIndiretti - $sommaInd, 2);
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
        $dist = self::distintaImputazioneData($idAssociazione, $anno);
        $righe = $dist['data'] ?? [];

        $out = []; // [idVoceConfig][idConv] => importo_indiretti
        foreach ($righe as $riga) {
            $idVoce = (int)($riga['idVoceConfig'] ?? 0);
            if ($idVoce <= 0) continue;

            foreach ($conv as $idConv => $nomeConv) {
                // In distinta le colonne per conv sono per nome: $riga[$nomeConv] = ['diretti','indiretti']
                $ind = (float)($riga[$nomeConv]['indiretti'] ?? 0.0);
                $out[$idVoce][$idConv] = $ind;
            }
        }
        return $out;
    }
}
