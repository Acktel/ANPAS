<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Riepilogo extends Model {
    protected $table = 'riepiloghi';
    protected $primaryKey = 'idRiepilogo';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    /* =======================
       ID FISSI DAL SEEDER
       ======================= */

    /** 1002: totale ore effettuate dai volontari per la convenzione (calcolata) */
    public const VOCE_ID_ORE_VOLONTARI = 1002;
    public const VOCE_ID_ORE_SERVIZI_CIVILE = 1003;

    public const ID_DIPENDENTE_VOLONTARI = 999999;
    public const ID_SERVIZIO_CIVILE = 999998;

    /** 1007: n. ore svolte dai dipendenti autisti/barellieri per la convenzione (calcolata SOLO per autisti) */
    public const VOCE_ID_ORE_AUTISTI   = 1007;

    /** 1023/1024: KM percorsi (calcolati) */
    public const VOCE_KM_ASSOC                 = 1023; // totale km percorsi nell'anno dall'associazione
    public const VOCE_KM_CONVENZIONE_ONLY      = 1024; // totale km percorsi nell'anno per la convenzione (vuoto in TOTALE)

    /** 1025/1026: SERVIZI svolti (calcolati) */
    public const VOCE_SERVIZI_ASSOC            = 1025; // totale servizi svolti nell'anno dall'associazione
    public const VOCE_SERVIZI_CONVENZIONE_ONLY = 1026; // totale servizi svolti nell'anno per la convenzione (vuoto in TOTALE)

    /** ID qualifica: Autista/Barellieri */
    protected const QUALIFICA_AUTISTA = 1;

    /**
     * Voci “TOTALE editabile”: in vista TOTALE mostriamo il master value (MIN),
     * mentre le altre voci mostriamo la somma (SUM). Riconosciute per ID fissi.
     */
    protected const VOCI_TOT_EDITABILI_IDS = [
        1001, // n. volontari totali iscritti all'associazione come da registro
        1004, // n. dipendenti dell'associazione come da libro unico al 31/12
        1011, // numero dipendenti coordinatori tecnici in servizio per l'associazione
        1014, // numero dipendenti addetti alla logistica in servizio per l'associazione
        1020, // numero dipendenti coordinatori amministrativi in servizio per l'associazione
        1028, // mq. locali sede associazione
        1029, // mq. locali sede dedicati alla postazione
        1030, // mq. locali ricovero mezzi e magazzini
        1031, // mq. locali ricovero mezzi e magazzini dedicati alla postazione
    ];

    public static function isVoceIdEditabileSuTot(int $idVoce): bool {
        return in_array($idVoce, self::VOCI_TOT_EDITABILI_IDS, true);
    }

    /* ================
       AGGREGATI: KM
       ================ */

    /** Somma KMPercorsi per una singola convenzione (anno filtrato) */
    protected static function sumKmForConvenzione(int $idConvenzione, int $anno): float {
        return (float) DB::table('automezzi_km as k')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
            ->where('k.idConvenzione', $idConvenzione)
            ->where('c.idAnno', $anno)
            ->sum('k.KMPercorsi');
    }

    /** Somma KMPercorsi per tutte le convenzioni di un’associazione in un anno */
    protected static function sumKmForAssociazione(int $idAssociazione, int $anno): float {
        return (float) DB::table('automezzi_km as k')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->sum('k.KMPercorsi');
    }

    /* ===================
       AGGREGATI: SERVIZI
       =================== */

    /** Somma NumeroServizi per associazione+anno (tutte le convenzioni) */
    private static function sumServiziForAssociazione(int $idAssociazione, int $anno): float {
        return (float) DB::table('automezzi_servizi as s')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 's.idAutomezzo')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 's.idConvenzione')
            ->where('a.idAssociazione', $idAssociazione)
            ->where('a.idAnno', $anno)
            ->where('c.idAnno', $anno)
            ->sum('s.NumeroServizi');
    }

    /** Somma NumeroServizi per una singola convenzione (filtrata per anno) */
    private static function sumServiziForConvenzione(int $idConvenzione, int $anno): float {
        return (float) DB::table('automezzi_servizi as s')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 's.idConvenzione')
            ->where('c.idConvenzione', $idConvenzione)
            ->where('c.idAnno', $anno)
            ->sum('s.NumeroServizi');
    }

    /* ========================
       AGGREGATI: DIPENDENTI
       ======================== */

    /** Somma OreServizio (tutti) per Associazione+Anno – per eventuale uso con 1002 in TOTALE */
    public static function sumOreServizioPerAssAnno(int $idAssociazione, int $anno): float {
        return (float) DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->where('ds.idDipendente', Riepilogo::ID_DIPENDENTE_VOLONTARI) // ← id fittizio aggregato
            ->sum('ds.OreServizio');
    }

        /** Somma UnitàServizio civile (tutti) per Associazione+Anno – per eventuale uso con 1003 in TOTALE */
    public static function sumUnitaServizioPerAssAnno(int $idAssociazione, int $anno): float {
        return (float) DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->where('ds.idDipendente', Riepilogo::ID_SERVIZIO_CIVILE) // ← id fittizio aggregato
            ->sum('ds.OreServizio');
    }

    /** Somma OreServizio (tutti) per singola convenzione – per 1002 in convenzione */
    public static function sumOreServizioPerConvenzione(int $idConvenzione, int $anno): float {
        return (float) DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idConvenzione', $idConvenzione)
            ->where('c.idAnno', $anno)
            ->sum('ds.OreServizio');
    }

    /** Somma OreServizio SOLO Autisti/Barellieri per convenzione (1007) */
    public static function sumOreAutistiPerConvenzione(int $idConvenzione, int $anno): float {
        return (float) DB::table('dipendenti_servizi as ds')
            ->join('dipendenti as d', 'd.idDipendente', '=', 'ds.idDipendente')
            ->where('ds.idConvenzione', $idConvenzione)
            ->where('d.idAnno', $anno)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('dipendenti_qualifiche as dq')
                    ->whereColumn('dq.idDipendente', 'ds.idDipendente')
                    ->where('dq.idQualifica', self::QUALIFICA_AUTISTA);
            })
            ->sum('ds.OreServizio');
    }

    /** Somma OreServizio SOLO Autisti/Barellieri per Associazione+Anno (1007 in TOTALE) */
    public static function sumOreAutistiPerAssAnno(int $idAssociazione, int $anno): float {
        return (float) DB::table('dipendenti_servizi as ds')
            ->join('dipendenti as d', 'd.idDipendente', '=', 'ds.idDipendente')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $anno)
            ->where('d.idAnno', $anno)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('dipendenti_qualifiche as dq')
                    ->whereColumn('dq.idDipendente', 'ds.idDipendente')
                    ->where('dq.idQualifica', self::QUALIFICA_AUTISTA);
            })
            ->sum('ds.OreServizio');
    }

    /**Somma  n. volontari servizio civile naz.le in servizio per convenzione*/



    /* =======================
       CREAZIONE / UPSERT BASE
       ======================= */

    /** Crea (o restituisce) il riepilogo per Associazione+Anno */
    public static function createRiepilogo(int $idAssociazione, int $idAnno): int {
        $esiste = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $idAnno)
            ->value('idRiepilogo');

        if ($esiste) {
            return (int) $esiste;
        }

        return DB::table('riepiloghi')->insertGetId([
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $idAnno,
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ], 'idRiepilogo');
    }

    /**
     * Insert/Update di una riga voce+convenzione (no TOT).
     * Evita di toccare created_at sugli update.
     */
    public static function upsertValore(
        int $idRiepilogo,
        int $idVoceConfig,
        ?int $idConvenzione,
        float $preventivo = 0,
        float $consuntivo = 0
    ): void {
        $keys = [
            'idRiepilogo'   => $idRiepilogo,
            'idVoceConfig'  => $idVoceConfig,
            'idConvenzione' => $idConvenzione,
        ];

        $updated = DB::table('riepilogo_dati')
            ->where($keys)
            ->update([
                'preventivo' => $preventivo,
                'consuntivo' => $consuntivo,
                'updated_at' => Carbon::now(),
            ]);

        if ($updated === 0) {
            DB::table('riepilogo_dati')->insert(array_merge($keys, [
                'preventivo' => $preventivo,
                'consuntivo' => $consuntivo,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }
    }

    /* =======================
       LETTURE PER LA UI
       ======================= */

    /**
     * Righe per DataTable (TIPOLOGIA = 1)
     * - Vista TOTALE: per voci in VOCI_TOT_EDITABILI_IDS mostra MIN (master value), altrimenti SUM.
     *   Voci calcolate speciali (1002, 1007, 1023, 1024, 1025, 1026) vanno in consuntivo.
     * - Vista convenzione: valori puntuali; voci calcolate riempiono sempre consuntivo.
     *
     * NOTA: abbiamo rimosso 'valore_id' dall’output (si lavora solo con voce_id).
     */
    public static function getForDataTable(int $anno, ?int $assocId, $idConvenzione = null): array {
        if (!$assocId) return [];

        $riepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $assocId)
            ->where('idAnno', $anno)
            ->first();

        if (!$riepilogo) return [];

        // Voci attive tipologia 1
        $voci = DB::table('riepilogo_voci_config')
            ->where('idTipologiaRiepilogo', 1)
            ->where('attivo', 1)
            ->orderBy('ordinamento')
            ->get(['id', 'descrizione']);

        $rows = [];

        /* =========================
       VISTA TOTALE (idConvenzione = TOT/null)
       ========================= */
        if ($idConvenzione === null || $idConvenzione === 'TOT') {

            // Statistiche "classiche" dalle righe memorizzate
            $stats = DB::table('riepilogo_dati')
                ->select(
                    'idVoceConfig',
                    DB::raw('MIN(preventivo) as min_prev'),
                    DB::raw('SUM(preventivo) as sum_prev'),
                    DB::raw('MIN(consuntivo) as min_cons'),
                    DB::raw('SUM(consuntivo) as sum_cons')
                )
                ->where('idRiepilogo', $riepilogo->idRiepilogo)
                ->groupBy('idVoceConfig')
                ->get()
                ->keyBy('idVoceConfig');

            foreach ($voci as $voce) {
                $voceId = (int) $voce->id;

                // ---- VOCI CALCOLATE (TOTALE) ----
                if ($voceId === 1001) { // n. volontari totali iscritti all'associazione come da registro
                    $num = DB::table('dipendenti')
                        ->where('idAssociazione', $riepilogo->idAssociazione)
                        ->where('idAnno', $riepilogo->idAnno)                    
                        ->count();
                    
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => null,
                        'consuntivo'    => $num,
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => true,
                        'non_editabile' => false,
                    ];
                    continue;
                }
                if ($voceId === self::VOCE_ID_ORE_VOLONTARI) { // Ore Servizio Volontari (ass+anno)
                    $ore = self::sumOreServizioPerAssAnno((int)$riepilogo->idAssociazione, (int)$riepilogo->idAnno);
                    $preventivo = self::getPreventivoVolontari($riepilogo->idRiepilogo);
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => $preventivo,
                        'consuntivo'    => $ore,
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => true,
                        'non_editabile' => false,
                    ];
                    continue;
                }
                if ($voceId === self::VOCE_ID_ORE_SERVIZI_CIVILE) {//n. volontari servizio civile naz.le in servizio per la convenzione
                    //SOMMA UNITA'
                    $unitaTotali = self::sumUnitaServizioPerAssAnno((int)$riepilogo->idAssociazione, (int)$riepilogo->idAnno);              
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => 0.0,
                        'consuntivo'    => $unitaTotali,
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => true,
                        'non_editabile' => false,
                    ];
                    continue;
                } 
                if ($voceId === 1007) { // Ore Autisti/Barellieri (ass+anno)
                    $ore = self::sumOreAutistiPerAssAnno((int)$riepilogo->idAssociazione, (int)$riepilogo->idAnno);
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => 0.0,
                        'consuntivo'    => $ore,
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => false,
                        'non_editabile' => true,
                    ];
                    continue;
                }
                if ($voceId === 1023) { // KM dall'associazione (somma su tutte le convenzioni)
                    $km = self::sumKmForAssociazione((int)$riepilogo->idAssociazione, (int)$riepilogo->idAnno);
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => 0.0,
                        'consuntivo'    => $km,
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => false,
                        'non_editabile' => true,
                    ];
                    continue;
                }
                if ($voceId === 1024) { // KM per convenzione (in TOT: vuoto)
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => 0.0,
                        'consuntivo'    => '-', // lasciala vuota in TOT
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => false,
                        'non_editabile' => true,
                    ];
                    continue;
                }
                if ($voceId === 1025) { // Servizi dall'associazione
                    $num = self::sumServiziForAssociazione((int)$riepilogo->idAssociazione, (int)$riepilogo->idAnno);
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => 0.0,
                        'consuntivo'    => $num,
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => false,
                        'non_editabile' => true,
                    ];
                    continue;
                }
                if ($voceId === 1026) { // Servizi per convenzione (in TOT: vuoto)
                    $rows[] = [
                        'anno'          => $anno,
                        'descrizione'   => $voce->descrizione,
                        'idRiepilogo'   => $riepilogo->idRiepilogo,
                        'preventivo'    => 0.0,
                        'consuntivo'    => '-', // lasciala vuota in TOT
                        'valore_id'     => null,
                        'voce_id'       => $voceId,
                        'tot_editabile' => false,
                        'non_editabile' => true,
                    ];
                    continue;
                }

                // ---- Voci "classiche" ----
                $st  = $stats[$voceId] ?? null;
                $isT = self::isVoceIdEditabileSuTot($voceId);

                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => $isT ? ($st ? (float)$st->min_prev : 0.0) : ($st ? (float)$st->sum_prev : 0.0),
                    'consuntivo'    => $isT ? ($st ? (float)$st->min_cons : 0.0) : ($st ? (float)$st->sum_cons : 0.0),
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => $isT,
                ];
            }

            return $rows;
        }

        /* =========================
       VISTA per CONVENZIONE
       ========================= */
        $valori = DB::table('riepilogo_dati')
            ->select('id as valore_id', 'idVoceConfig', 'preventivo', 'consuntivo')
            ->where('idRiepilogo', $riepilogo->idRiepilogo)
            ->where('idConvenzione', (int)$idConvenzione)
            ->get()
            ->keyBy('idVoceConfig');

        foreach ($voci as $voce) {
            $voceId = (int) $voce->id;
            if ($voceId === self::VOCE_ID_ORE_VOLONTARI) { // Ore Servizio Volontari per la convenzione
                $ore = self::sumOreServizioPerConvenzione((int)$idConvenzione, (int)$riepilogo->idAnno);
                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => 0.0,
                    'consuntivo'    => $ore,
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => false,
                    'non_editabile' => true,
                ];
                continue;
            }
            if ($voceId === self::VOCE_ID_ORE_SERVIZI_CIVILE) {//n. volontari servizio civile naz.le in servizio per la convenzione
                //SOMMA UNITA'
                $unitaTotali = self::sumUnitaServizioPerConvenzione((int)$riepilogo->idAssociazione, (int)$idConvenzione, (int)$riepilogo->idAnno);              
                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => 0.0,
                    'consuntivo'    => $unitaTotali,
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => true,
                    'non_editabile' => false,
                ];
                continue;
            } 
            // ---- VOCI CALCOLATE (per-convenzione) ----
            if ($voceId === 1007) { // Ore Autisti/Barellieri per la convenzione
                $ore = self::sumOreAutistiPerConvenzione((int)$idConvenzione, (int)$riepilogo->idAnno);
                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => 0.0,
                    'consuntivo'    => $ore,
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => false,
                    'non_editabile' => true,
                ];
                continue;
            }
            if ($voceId === 1023) { // KM dell'associazione (totale, non varia con la convenzione)
                $km = self::sumKmForAssociazione((int)$riepilogo->idAssociazione, (int)$riepilogo->idAnno);
                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => 0.0,
                    'consuntivo'    => $km,
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => false,
                    'non_editabile' => true,
                ];
                continue;
            }
            if ($voceId === 1024) { // KM per la convenzione selezionata
                $km = self::sumKmForConvenzione((int)$idConvenzione, (int)$riepilogo->idAnno);
                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => 0.0,
                    'consuntivo'    => $km,
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => false,
                    'non_editabile' => true,
                ];
                continue;
            }
            if ($voceId === 1025) { // Servizi dell'associazione (totale, non varia con la convenzione)
                $num = self::sumServiziForAssociazione((int)$riepilogo->idAssociazione, (int)$riepilogo->idAnno);
                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => 0.0,
                    'consuntivo'    => $num,
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => false,
                    'non_editabile' => true,
                ];
                continue;
            }
            if ($voceId === 1026) { // Servizi per la convenzione selezionata
                $num = self::sumServiziForConvenzione((int)$idConvenzione, (int)$riepilogo->idAnno);
                $rows[] = [
                    'anno'          => $anno,
                    'descrizione'   => $voce->descrizione,
                    'idRiepilogo'   => $riepilogo->idRiepilogo,
                    'preventivo'    => 0.0,
                    'consuntivo'    => $num,
                    'valore_id'     => null,
                    'voce_id'       => $voceId,
                    'tot_editabile' => false,
                    'non_editabile' => true,
                ];
                continue;
            }

            // ---- Voci "classiche" salvate ----
            $v = $valori[$voceId] ?? null;

            $rows[] = [
                'anno'          => $anno,
                'descrizione'   => $voce->descrizione,
                'idRiepilogo'   => $riepilogo->idRiepilogo,
                'preventivo'    => $v ? (float)$v->preventivo : 0.0,
                'consuntivo'    => $v ? (float)$v->consuntivo : 0.0,
                'valore_id'     => $v->valore_id ?? null,
                'voce_id'       => $voceId,
                'tot_editabile' => self::isVoceIdEditabileSuTot($voceId),
            ];
        }

        return $rows;
    }


    /** Convenzioni per associazione+anno (formato id/text per select) */
    public static function getConvenzioniForAssAnno(int $idAssociazione, int $anno): Collection {
        $rows = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->orderBy('ordinamento')
            ->orderBy('idConvenzione')
            ->get();

        return $rows->map(fn($r) => (object) [
            'id'   => $r->idConvenzione,
            'text' => $r->Convenzione,
        ]);
    }

    /** Dettaglio riga (con join) per schermata di edit riga */
    public static function getRigaDettaglio(int $id): ?object {

        return DB::table('riepilogo_dati as d')
            ->join('riepiloghi as r', 'r.idRiepilogo', '=', 'd.idRiepilogo')
            ->leftJoin('riepilogo_voci_config as vc', 'vc.id', '=', 'd.idVoceConfig')
            ->leftJoin('convenzioni as c', 'c.idConvenzione', '=', 'd.idConvenzione')
            ->where('d.id', $id)
            ->select([
                'd.id',
                'd.idRiepilogo',
                'd.idVoceConfig',
                'd.idConvenzione',
                'd.preventivo',
                'd.consuntivo',
                'r.idAssociazione',
                'r.idAnno',
                'vc.descrizione as voce_descrizione',
                'c.Convenzione as convenzione_descrizione',
            ])
            ->first();
    }

    /** Aggiorna i valori della riga (es. preventivo/consuntivo) */
    public static function updateRigaValori(int $id, array $fields): bool {
        $allowed = array_intersect_key($fields, array_flip(['preventivo', 'consuntivo']));
        if (empty($allowed)) {
            return false;
        }

        $allowed['updated_at'] = Carbon::now();

        return (bool) DB::table('riepilogo_dati')
            ->where('id', $id)
            ->update($allowed);
    }

    /** Elimina una riga di riepilogo_dati */
    public static function deleteRiga(int $id): bool {
        return (bool) DB::table('riepilogo_dati')->where('id', $id)->delete();
    }

    /* =======================
       HELPERS “CLASSICI”
       ======================= */

    public static function getByAssociazione(int $idAssociazione, ?int $anno = null): Collection {
        $anno = $anno ?? (int) session('anno_riferimento', now()->year);

        return DB::table('riepiloghi as r')
            ->where('r.idAssociazione', $idAssociazione)
            ->where('r.idAnno', $anno)
            ->select([
                'r.idRiepilogo',
                'r.idAnno as anno',
                'r.created_at',
            ])
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    public static function getSingle(int $idRiepilogo): ?object {
        return DB::table('riepiloghi')
            ->where('idRiepilogo', $idRiepilogo)
            ->first();
    }

    public static function updateRiepilogo(int $idRiepilogo, int $idAssociazione, int $idAnno): void {
        DB::table('riepiloghi')
            ->where('idRiepilogo', $idRiepilogo)
            ->update([
                'idAssociazione' => $idAssociazione,
                'idAnno'         => $idAnno,
                'updated_at'     => Carbon::now(),
            ]);
    }

    public static function deleteRiepilogo(int $idRiepilogo): void {
        DB::table('riepiloghi')->where('idRiepilogo', $idRiepilogo)->delete();
    }

    /** ID riga dato (riepilogo, voce, convenzione) oppure null */
    public static function getRigaIdByKeys(int $idRiepilogo, int $idVoceConfig, int $idConvenzione): ?int {
        $id = DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->where('idVoceConfig', $idVoceConfig)
            ->where('idConvenzione', $idConvenzione)
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Crea la riga se manca (preventivo/consuntivo 0) e ritorna SEMPRE l'id.
     */
    public static function ensureRiga(int $idRiepilogo, int $idVoceConfig, int $idConvenzione): int {

        $id = self::getRigaIdByKeys($idRiepilogo, $idVoceConfig, $idConvenzione);
        if (!$id) {
            $id = DB::table('riepilogo_dati')->insertGetId([
                'idRiepilogo'   => $idRiepilogo,
                'idVoceConfig'  => $idVoceConfig,
                'idConvenzione' => $idConvenzione,
                'preventivo'    => 0,
                'consuntivo'    => 0,
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ]);
        }

        return (int) $id;
    }

    public static function getVoceDescrizione(int $idVoce): ?string {
        return DB::table('riepilogo_voci_config')->where('id', $idVoce)->value('descrizione');
    }

    public static function sumPreventivoVoce(int $idRiepilogo, int $idVoce): float {
        return (float) DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->where('idVoceConfig', $idVoce)
            ->sum('preventivo');
    }

    /**
     * Replica un valore voce su tutte le convenzioni del riepilogo.
     * Ritorna quante convenzioni sono state toccate.
     */
    public static function applyVoceToAllConvenzioni(
        int $idRiepilogo,
        int $idVoceConfig,
        float $preventivo,
        float $consuntivo = 0.0
    ): int {
        $r = DB::table('riepiloghi')->where('idRiepilogo', $idRiepilogo)->first();
        if (!$r) {
            return 0;
        }

        $convs = DB::table('convenzioni')
            ->where('idAssociazione', $r->idAssociazione)
            ->where('idAnno', $r->idAnno)
            ->pluck('idConvenzione');

        $n = 0;
        foreach ($convs as $idConvenzione) {
            self::upsertValore($idRiepilogo, $idVoceConfig, (int) $idConvenzione, $preventivo, $consuntivo);
            $n++;
        }
        return $n;
    }

    private static function getPreventivoVolontari(int $idRiepilogo): float {
        return (float) DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->where('idVoceConfig', self::VOCE_ID_ORE_VOLONTARI) // n. volontari totali iscritti all'associazione come da registro
            ->value('preventivo') ?? 0.0;           
    }

    private static function sumUnitaServizioPerConvenzione($idAssociazione, $idConvenzione, $idAnno){
        return (float) DB::table('dipendenti_servizi as ds')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ds.idConvenzione')
            ->where('c.idAssociazione', $idAssociazione)
            ->where('c.idAnno', $idAnno)
            ->where('c.idConvenzione', $idConvenzione)
            ->where('ds.idDipendente', Riepilogo::ID_SERVIZIO_CIVILE) // ← id fittizio aggregato
            ->value('ds.OreServizio');
    }
}
