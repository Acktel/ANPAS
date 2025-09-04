<?php

namespace App\Models;

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
            return collect(); // senza associazione non possiamo determinare il riepilogo
        }

        // Riepilogo pivot per Associazione+Anno
        $riepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if (!$riepilogo) {
            return collect();
        }

        // Voci configurate per la tipologia (fisse)
        $voci = DB::table('riepilogo_voci_config as vc')
            ->where('vc.idTipologiaRiepilogo', $idTipologia)
            ->where('vc.attivo', 1)
            ->orderBy('vc.ordinamento')
            ->orderBy('vc.id')
            ->get(['vc.id', 'vc.descrizione']);

        // Valori preventivo/consuntivo
        if ($idConvenzione === null || $idConvenzione === 'TOT') {
            $valori = DB::table('riepilogo_dati as rd')
                ->select(
                    'rd.idVoceConfig',
                    DB::raw('SUM(rd.preventivo) as preventivo'),
                    DB::raw('SUM(rd.consuntivo) as consuntivo')
                )
                ->where('rd.idRiepilogo', $riepilogo->idRiepilogo)
                ->groupBy('rd.idVoceConfig')
                ->get()
                ->keyBy('idVoceConfig');
        } else {
            $valori = DB::table('riepilogo_dati as rd')
                ->select('rd.idVoceConfig', 'rd.preventivo', 'rd.consuntivo')
                ->where('rd.idRiepilogo', $riepilogo->idRiepilogo)
                ->where('rd.idConvenzione', (int) $idConvenzione)
                ->get()
                ->keyBy('idVoceConfig');
        }

        // Compose rows (aggiungo il codice N:NN)
        $i = 0;
        return $voci->map(function ($voce) use (&$i, $valori, $idTipologia) {
            $i++;
            $v = $valori[$voce->id] ?? null;

            $preventivo = $v ? (float) $v->preventivo : 0.0;
            $consuntivo = $v ? (float) $v->consuntivo : 0.0;

            $scostamentoNum = $preventivo != 0.0
                ? round((($consuntivo - $preventivo) / $preventivo) * 100, 2)
                : 0.0;

            return (object) [
                'idVoceConfig' => (int) $voce->id,
                'codice'       => sprintf('%d:%02d', $idTipologia, $i), // es. 2:01
                'descrizione'  => $voce->descrizione,
                'preventivo'   => $preventivo,
                'consuntivo'   => $consuntivo,
                'scostamento'  => number_format($scostamentoNum, 2) . '%',
                // niente 'actions' qui: evitiamo route con id mancante
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
            ->selectRaw('vc.idTipologiaRiepilogo as tipologia, SUM(rd.preventivo) as preventivo, SUM(rd.consuntivo) as consuntivo')
            ->groupBy('vc.idTipologiaRiepilogo')
            ->orderBy('vc.idTipologiaRiepilogo');

        if ($idConvenzione !== null && $idConvenzione !== 'TOT') {
            $query->where('rd.idConvenzione', (int)$idConvenzione);
        }


        return $query->get();
    }
}
