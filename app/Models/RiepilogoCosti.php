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

        // Pivot riepilogo
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

        $norm = function (?string $s): string {
            return mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string)$s)), 'UTF-8');
        };
        $mkCod = function (int $idx) use ($idTipologia): string {
            return sprintf('%d:%02d', $idTipologia, $idx);
        };

        // --- Se NON esiste il pivot: righe a zero (ma manteniamo la lista voci per il merge)
        if (!$riepilogo) {
            $rows = [];
            foreach ($voci as $i => $voce) {
                $rows[] = (object)[
                    'idVoceConfig' => (int)$voce->id,
                    'codice'       => $mkCod($i + 1),
                    'descrizione'  => $voce->descrizione,
                    'preventivo'   => 0.0,
                    'consuntivo'   => 0.0,
                    'scostamento'  => '0.00%',
                ];
            }
        } else {
            // PREVENTIVI
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
                    ->where('rd.idConvenzione', (int)$idConvenzione)
                    ->get()
                    ->keyBy('idVoceConfig');
            }

            // CONSUNTIVI calcolati
            $mapCons  = RipartizioneCostiService::consuntiviPerVoceByConvenzione($idAssociazione, $anno);
            $convIds  = array_keys(RipartizioneCostiService::convenzioni($idAssociazione, $anno));
            $sumConsVoce = function (int $idVoce) use ($mapCons, $convIds): float {
                $row = $mapCons[$idVoce] ?? [];
                $sum = 0.0;
                foreach ($convIds as $c) $sum += (float)($row[$c] ?? 0.0);
                return $sum;
            };

            $rows = [];
            $i = 0;
            foreach ($voci as $voce) {
                $i++;
                $idVoce = (int)$voce->id;
                $prev   = (float)($preventivi[$idVoce]->preventivo ?? 0.0);

                $cons = ($idConvenzione === null || $idConvenzione === 'TOT')
                    ? $sumConsVoce($idVoce)
                    : (float)($mapCons[$idVoce][(int)$idConvenzione] ?? 0.0);

                $scostPerc = $prev != 0.0 ? round((($cons - $prev) / $prev) * 100, 2) : 0.0;

                $rows[] = (object)[
                    'idVoceConfig' => $idVoce,
                    'codice'       => $mkCod($i),
                    'descrizione'  => $voce->descrizione,
                    'preventivo'   => $prev,
                    'consuntivo'   => $cons,
                    'scostamento'  => number_format($scostPerc, 2) . '%',
                ];
            }
        }

        // === Merge “costi telefonia fissa” + “costi telefonia mobile” => “UTENZE TELEFONICHE”
        $telTargets = ['COSTI TELEFONIA FISSA', 'COSTI TELEFONIA MOBILE'];

        // mappa descrizione->id per poter passare gli id al frontend (edit doppio)
        $descToId = [];
        foreach ($voci as $vc) {
            $descToId[$norm($vc->descrizione)] = (int)$vc->id;
        }
        $idFissa  = $descToId['COSTI TELEFONIA FISSA']  ?? null;
        $idMobile = $descToId['COSTI TELEFONIA MOBILE'] ?? null;

        $telPrev = 0.0;
        $telCons = 0.0;
        $firstPos = null;
        $filtered = [];

        foreach ($rows as $idx => $row) {
            $d = $norm($row->descrizione);
            if (in_array($d, $telTargets, true)) {
                if ($firstPos === null) $firstPos = count($filtered); // posizione di inserimento
                $telPrev += (float)$row->preventivo;
                $telCons += (float)$row->consuntivo;
                continue; // scarta le due righe originali
            }
            $filtered[] = $row;
        }

        if ($firstPos !== null) {
            $scostPercTel = $telPrev != 0.0 ? round((($telCons - $telPrev) / $telPrev) * 100, 2) : 0.0;

            $mergedRow = (object)[
                'idVoceConfig' => 'TEL_MERGE',               // marcatore speciale per il frontend
                'codice'       => '',                        // ricalcolato sotto
                'descrizione'  => 'UTENZE TELEFONICHE',
                'preventivo'   => round($telPrev, 2),
                'consuntivo'   => round($telCons, 2),
                'scostamento'  => number_format($scostPercTel, 2) . '%',
                // metadati per l’edit: gli id delle due voci originali (se esistono)
                'merged_of'    => array_values(array_filter([$idFissa, $idMobile])),
            ];

            array_splice($filtered, $firstPos, 0, [$mergedRow]);
        }

        // Rinumera i codici in base all’ordine finale
        foreach ($filtered as $i => $row) {
            $row->codice = $mkCod($i + 1);
        }

        return collect($filtered);
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
            ->selectRaw('
            vc.idTipologiaRiepilogo as tipologia,
            vc.descrizione as descrizione,
            SUM(rd.preventivo) as preventivo,
            SUM(rd.consuntivo) as consuntivo
        ')
            ->groupBy('vc.idTipologiaRiepilogo', 'vc.descrizione')
            ->orderBy('vc.idTipologiaRiepilogo');

        if ($idConvenzione !== null && $idConvenzione !== 'TOT') {
            $query->where('rd.idConvenzione', (int)$idConvenzione);
        }

        return $query->get();
    }
}
