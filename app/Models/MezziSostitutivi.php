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

    /** Upsert del costo fascia oraria (SOLO SQL) */
    public static function upsertCosto(int $idConvenzione, int $anno, float $costo): void {
        DB::table(self::TABLE)->updateOrInsert(
            ['idConvenzione' => $idConvenzione, 'idAnno' => $anno],
            ['costo_fascia_oraria' => $costo, 'updated_at' => now()]
        );
    }

    /**
     * Calcola il COSTO NETTO "mezzi sostitutivi" di una convenzione per l'anno:
     * somma i costi dei MEZZI collegati alla convenzione (ESCLUSO il titolare),
     * usando i nomi colonna reali della tabella `costi_automezzi`.
     *
     * NOTE: la straordinaria è al netto dei rimborsi assicurativi.
     *       NON includo Carburanti/Additivi/RimborsiUTF.
     */
    public static function calcolaCostoSostitutivi(int $idConvenzione, int $anno): float {
        // 1) prendo gli automezzi della convenzione ESCLUSO il titolare
        $mezzi = DB::select("
            SELECT ak.idAutomezzo
            FROM automezzi_km AS ak
            JOIN automezzi AS a ON a.idAutomezzo = ak.idAutomezzo
            WHERE ak.idConvenzione = :idConvenzione
              AND ak.is_titolare = 0
        ", ['idConvenzione' => $idConvenzione]);

        if (empty($mezzi)) return 0.0;

        $ids = implode(',', array_map(fn($m) => (int)$m->idAutomezzo, $mezzi));
        if ($ids === '') return 0.0;

        // 2) somma coi nomi CAMPO corretti (vedi screenshot della tabella)
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

    public static function getStato(int $idConvenzione, int $anno): object
    {
        // 1️⃣ costo fascia oraria (record tabella mezzi_sostitutivi)
        $rec = self::getByConvenzioneAnno($idConvenzione, $anno);
        $costoFascia = $rec ? (float)$rec->costo_fascia_oraria : 0.0;
        $netByConv = RipartizioneCostiService::costoNettoMezziSostitutiviByConvenzione(
            (int) $conv->idAssociazione,
            $anno
        );
        $costoSost = (float)($netByConv[$idConvenzione] ?? 0.0);

        // 3️⃣ totale netto (differenza tra costo fascia oraria e costo mezzi)
        $totaleNetto = max(0, $costoFascia - $costoSost);

        return (object)[
            'costo_fascia_oraria'     => $costoFascia,
            'costo_mezzi_sostitutivi' => $costoSost,
            'totale_netto'            => $totaleNetto,
        ];
    }

}
