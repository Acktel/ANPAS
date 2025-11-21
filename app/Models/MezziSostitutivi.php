<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;

class MezziSostitutivi
{
    protected const TABLE = 'mezzi_sostitutivi';

    /**
     * Lettura costo fascia oraria salvato.
     */
    public static function getByConvenzioneAnno(int $idConvenzione, int $anno): ?object
    {
        return DB::table(self::TABLE)
            ->where('idConvenzione', $idConvenzione)
            ->where('idAnno', $anno)
            ->first();
    }

    /**
     * Upsert costo fascia oraria.
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
     * Ritorna lo stato visualizzabile in pagina.
     * - costo fascia (manuale)
     * - costo reale mezzi sostitutivi (calcolo vero)
     * - totale netto
     */
    public static function getStato(int $idConvenzione, int $anno): object
    {
        //inserito manualmente
        $rec = self::getByConvenzioneAnno($idConvenzione, $anno);
        $costoFascia = $rec ? (float)$rec->costo_fascia_oraria : 0.0;

        //costo reale mezzi sostitutivi (dal SERVICE)
        $costoSost = self::calcolaCostoSostitutivi($idConvenzione, $anno);

        return (object)[
            'costo_fascia_oraria'     => $costoFascia,
            'costo_mezzi_sostitutivi' => $costoSost,
            'totale_netto'            => max(0.0, $costoFascia - $costoSost),
        ];
    }

    public static function calcolaCostoSostitutivi(int $idConvenzione, int $anno): float
    {
        // recupera id associazione
        $idAss = DB::table('convenzioni')
            ->where('idConvenzione', $idConvenzione)
            ->value('idAssociazione');

        if (!$idAss) {
            return 0.0;
        }

        // ottiene array: [ idConv => eccedenza ]
        $eccedenze = RipartizioneCostiService::costoNettoMezziSostitutiviByConvenzione(
            (int)$idAss,
            $anno
        );

        return (float)($eccedenze[$idConvenzione] ?? 0.0);
    }

}
