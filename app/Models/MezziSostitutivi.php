<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

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
     * ⛔ VERSIONE UFFICIALE PER LA TUA LOGICA:
     * Calcolo COSTO REALE mezzi sostitutivi (NO SERVICE).
     *
     * - prende TUTTI i mezzi della convenzione
     * - esclude il titolare
     * - somma solo le voci ammesse
     */
    public static function calcolaCostoSostitutivi(int $idConvenzione, int $anno): float
    {
        // Mezzi della convenzione escluso titolare
        $mezzi = DB::select("
            SELECT ak.idAutomezzo
            FROM automezzi_km AS ak
            JOIN automezzi AS a ON a.idAutomezzo = ak.idAutomezzo
            WHERE ak.idConvenzione = :idConv
              AND ak.is_titolare = 0
        ", ['idConv' => $idConvenzione]);

        if (empty($mezzi)) return 0.0;

        $ids = implode(',', array_map(fn($m) => (int)$m->idAutomezzo, $mezzi));
        if ($ids === '') return 0.0;

        // Somma costi reali
        $row = DB::selectOne("
            SELECT COALESCE(SUM(
                COALESCE(LeasingNoleggio, 0) +
                COALESCE(Assicurazione, 0) +
                COALESCE(ManutenzioneOrdinaria, 0) +
                GREATEST(COALESCE(ManutenzioneStraordinaria, 0) - COALESCE(RimborsiAssicurazione, 0), 0) +
                COALESCE(PuliziaDisinfezione, 0) +
                COALESCE(InteressiPassivi, 0) +
                COALESCE(ManutenzioneSanitaria, 0) +
                COALESCE(LeasingSanitaria, 0) +
                COALESCE(AmmortamentoMezzi, 0) +
                COALESCE(AmmortamentoSanitaria, 0) +
                COALESCE(AltriCostiMezzi, 0)
            ), 0) AS tot
            FROM costi_automezzi
            WHERE idAnno = :anno
              AND idAutomezzo IN ($ids)
        ", ['anno' => $anno]);

        return (float)($row->tot ?? 0.0);
    }

    /**
     * Ritorna lo stato visualizzabile in pagina.
     * - costo fascia (manuale)
     * - costo reale mezzi sostitutivi (calcolo vero)
     * - totale netto
     */
    public static function getStato(int $idConvenzione, int $anno): object
    {
        // costo manuale
        $rec = self::getByConvenzioneAnno($idConvenzione, $anno);
        $costoFascia = $rec ? (float)$rec->costo_fascia_oraria : 0.0;

        // costo reale
        $costoSost = self::calcolaCostoSostitutivi($idConvenzione, $anno);

        return (object)[
            'costo_fascia_oraria'     => $costoFascia,
            'costo_mezzi_sostitutivi' => $costoSost,
            'totale_netto'            => max(0.0, $costoFascia - $costoSost),
        ];
    }
}
