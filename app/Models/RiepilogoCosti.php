<?php

namespace App\Models;

use App\Services\RipartizioneCostiService;

use Illuminate\Support\Facades\DB;

class RiepilogoCosti {
    /**
     * Voci e valori per tipologia (usa configurazioni),
     * con filtri su anno, associazione e (opzionale) convenzione.
     *
     * - Se $idConvenzione === 'TOT' o null -> SUM su tutte le convenzioni (per voce).
     * - Se $idConvenzione è numerico -> valori per quella convenzione.
     */
    public static function getByTipologia(int $idTipologia, int $anno, ?int $idAssociazione = null, $idConvenzione = null) {
        if (is_null($idAssociazione)) {
            return collect();
        }

        // Riepilogo pivot
        $riepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        // Voci configurate della sezione
        $voci = DB::table('riepilogo_voci_config as vc')
            ->where('vc.idTipologiaRiepilogo', $idTipologia)
            ->where('vc.attivo', 1)
            ->orderBy('vc.ordinamento')
            ->orderBy('vc.id')
            ->get(['vc.id', 'vc.descrizione']);

            if (!$riepilogo){
                $preventivi =0;    
                $mapCons =0;    
                $sumConsVoce =0;
                $i =0;
                return $voci->map(function ($voce) use (&$i, $preventivi, $idConvenzione, $mapCons, $sumConsVoce) {
                    $i++;
                    $idVoce     = (int) $voce->id;
                    $prev       =  0.0;


                    // Consuntivo calcolato
                    $cons = (float) 0.0;


                    $scostPerc = $prev != 0.0 ? round((($cons - $prev) / $prev) * 100, 2) : 0.0;


                    return (object) [
                        'idVoceConfig' => $idVoce,
                        'codice'       => sprintf('%d:%02d', $idConvenzione === 'TOT' || $idConvenzione === null ? (int)request()->route('idTipologia') : (int)request()->route('idTipologia'), $i),
                        'descrizione'  => $voce->descrizione,
                        'preventivo'   => $prev,
                        'consuntivo'   => $cons,         // ← calcolato dalle ripartizioni
                        'scostamento'  => number_format($scostPerc, 2) . '%',
                    ];
                });
        }

        // PREVENTIVO: resta preso da riepilogo_dati (per conv o TOT)
        if ($idConvenzione === null || $idConvenzione === 'TOT') {
            $preventivi = DB::table('riepilogo_dati as rd')
                ->select('rd.idVoceConfig', DB::raw('SUM(rd.preventivo) as preventivo'))
                ->where('rd.idRiepilogo', $riepilogo->idRiepilogo)
                ->groupBy('rd.idVoceConfig')
                ->get()
                ->keyBy('idVoceConfig');
        } else {
            $preventivi = DB::table('riepilogo_dati as rd')
                ->select('rd.idVoceConfig', 'rd.preventivo')
                ->where('rd.idRiepilogo', $riepilogo->idRiepilogo)
                ->where('rd.idConvenzione', (int) $idConvenzione)
                ->get()
                ->keyBy('idVoceConfig');
        }

        // CONSUNTIVO: calcolato dalla distinta (indiretti ripartiti)
        $mapCons = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);
        $convIds = array_keys(RipartizioneCostiService::convenzioni($idAssociazione, $anno));
        $sumConsVoce = function (int $idVoce) use ($mapCons, $convIds) {
            $row = $mapCons[$idVoce] ?? [];
            $sum = 0.0;
            foreach ($convIds as $c) $sum += (float) ($row[$c] ?? 0.0);
            return $sum;
        };

        // Compose righe
        $i = 0;
        return $voci->map(function ($voce) use (&$i, $preventivi, $idConvenzione, $mapCons, $sumConsVoce) {
            $i++;
            $idVoce     = (int) $voce->id;
            $prev       = (float) ($preventivi[$idVoce]->preventivo ?? 0.0);

            // Consuntivo calcolato
            if ($idConvenzione === null || $idConvenzione === 'TOT') {
                $cons = $sumConsVoce($idVoce);
            } else {
                $cons = (float) ($mapCons[$idVoce][(int)$idConvenzione] ?? 0.0);
            }

            $scostPerc = $prev != 0.0 ? round((($cons - $prev) / $prev) * 100, 2) : 0.0;

            return (object) [
                'idVoceConfig' => $idVoce,
                'codice'       => sprintf('%d:%02d', $idConvenzione === 'TOT' || $idConvenzione === null ? (int)request()->route('idTipologia') : (int)request()->route('idTipologia'), $i),
                'descrizione'  => $voce->descrizione,
                'preventivo'   => $prev,
                'consuntivo'   => $cons,         // ← calcolato dalle ripartizioni
                'scostamento'  => number_format($scostPerc, 2) . '%',
            ];
        });
    }


    /** Ritorna/crea il riepilogo pivot */
    public static function getOrCreateRiepilogo(int $idAssociazione, int $anno): int {
        $record = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if ($record) {
            return (int)$record->idRiepilogo;
        }

        return DB::table('riepiloghi')->insertGetId([
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $anno,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /** Inserisce/aggiorna un valore (NUOVO schema) */
    public static function upsertValore(array $data): bool {
        // attesi: idRiepilogo, idVoceConfig, (idConvenzione|null), preventivo, consuntivo
        return DB::table('riepilogo_dati')->updateOrInsert(
            [
                'idRiepilogo'   => $data['idRiepilogo'],
                'idVoceConfig'  => $data['idVoceConfig'],
                'idConvenzione' => $data['idConvenzione'] ?? null,
            ],
            [
                'preventivo' => $data['preventivo'] ?? 0,
                'consuntivo' => $data['consuntivo'] ?? 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /** Totali per tipologia (somma delle voci di quella tipologia) */
    public static function getTotaliPerTipologia(int $anno, ?int $idAssociazione = null, $idConvenzione = null) {
        if (!$idAssociazione) return collect();

        $riepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if (!$riepilogo) return collect();

        $query = DB::table('riepilogo_dati as rd')
            ->join('riepilogo_voci_config as vc', 'rd.idVoceConfig', '=', 'vc.id')
            ->where('rd.idRiepilogo', $riepilogo->idRiepilogo)
            ->whereNotNull('vc.idTipologiaRiepilogo')
            ->selectRaw('vc.idTipologiaRiepilogo as tipologia, SUM(rd.preventivo) as preventivo, SUM(rd.consuntivo) as consuntivo, vc.descrizione as descrizione')
            ->groupBy('vc.idTipologiaRiepilogo', 'vc.descrizione')
            ->orderBy('vc.idTipologiaRiepilogo');

        if ($idConvenzione !== null && $idConvenzione !== 'TOT') {
            $query->where('rd.idConvenzione', (int)$idConvenzione);
        }


        return $query->get();
    }
}
