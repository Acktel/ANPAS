<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;

class MezziSostitutivi {
    protected const TABLE = 'mezzi_sostitutivi';

    /** Legge (se esiste) il costo fascia oraria per convenzione/anno */
    public static function getByConvenzioneAnno(int $idConvenzione, int $anno): ?object {
        return DB::table(self::TABLE)
            ->where('idConvenzione', $idConvenzione)
            ->where('idAnno', $anno)
            ->first();
    }

    /** Upsert del costo fascia oraria */
    public static function upsertCosto(int $idConvenzione, int $anno, float $costo): void {
        DB::table(self::TABLE)->updateOrInsert(
            ['idConvenzione' => $idConvenzione, 'idAnno' => $anno],
            ['costo_fascia_oraria' => $costo, 'updated_at' => now()]
        );
    }

    /**
     * Stato mezzi sostitutivi usando SOLO la logica ufficiale
     * di RipartizioneCostiService (costo netto ripartito).
     */
    public static function getStato(int $idConvenzione, int $anno): object
    {
        // 1️⃣ costo fascia oraria salvato manualmente
        $rec = self::getByConvenzioneAnno($idConvenzione, $anno);
        $costoFascia = $rec ? (float)$rec->costo_fascia_oraria : 0.0;

        // 2️⃣ servono i dati della convenzione
        $conv = DB::table('convenzioni')
            ->select('idAssociazione')
            ->where('idConvenzione', $idConvenzione)
            ->first();

        if (!$conv) {
            return (object)[
                'costo_fascia_oraria'     => $costoFascia,
                'costo_mezzi_sostitutivi' => 0.0,
                'totale_netto'            => $costoFascia,
            ];
        }

        // 3️⃣ costo netto calcolato dalla ripartizione ufficiale ANPAS
        $netByConv = RipartizioneCostiService::costoNettoMezziSostitutiviByConvenzione(
            (int)$conv->idAssociazione,
            $anno
        );

        $costoSost = (float)($netByConv[$idConvenzione] ?? 0.0);

        // 4️⃣ differenza come da regola
        $totaleNetto = max(0, $costoFascia - $costoSost);

        return (object)[
            'costo_fascia_oraria'     => $costoFascia,
            'costo_mezzi_sostitutivi' => $costoSost,
            'totale_netto'            => $totaleNetto,
        ];
    }
}
