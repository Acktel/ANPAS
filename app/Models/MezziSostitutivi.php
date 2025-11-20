<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;

class MezziSostitutivi
{
    protected const TABLE = 'mezzi_sostitutivi';

    /**
     * Restituisce il costo fascia oraria salvato (se presente).
     */
    public static function getByConvenzioneAnno(int $idConvenzione, int $anno): ?object
    {
        return DB::table(self::TABLE)
            ->where('idConvenzione', $idConvenzione)
            ->where('idAnno', $anno)
            ->first();
    }

    /**
     * Upsert del costo fascia oraria.
     */
    public static function upsertCosto(int $idConvenzione, int $anno, float $costo): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['idConvenzione' => $idConvenzione, 'idAnno' => $anno],
            [
                'costo_fascia_oraria' => $costo,
                'updated_at'          => now(),
            ]
        );
    }

    /**
     * Restituisce:
     *  - costo_fascia_oraria (manuale)
     *  - costo_mezzi_sostitutivi (calcolato dal SERVICE)
     *  - totale_netto = max(0, fascia - sostitutivi)
     */
    public static function getStato(int $idConvenzione, int $anno): object
    {
        // 1️⃣ Costo fascia oraria manuale
        $rec = self::getByConvenzioneAnno($idConvenzione, $anno);
        $costoFascia = $rec ? (float)$rec->costo_fascia_oraria : 0.0;

        // 2️⃣ Associazione della convenzione
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

        // 3️⃣ Costo ufficiale mezzi sostitutivi ripartito dal sistema
        $costByConv = RipartizioneCostiService::costoNettoMezziSostitutiviByConvenzione(
            (int)$conv->idAssociazione,
            $anno
        );

        $costoSost = (float)($costByConv[$idConvenzione] ?? 0.0);

        // 4️⃣ Differenza netta
        return (object)[
            'costo_fascia_oraria'     => $costoFascia,
            'costo_mezzi_sostitutivi' => $costoSost,
            'totale_netto'            => max(0.0, $costoFascia - $costoSost),
        ];
    }
}
