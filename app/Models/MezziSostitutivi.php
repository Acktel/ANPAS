<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;

class MezziSostitutivi {
    protected const TABLE = 'mezzi_sostitutivi';

    /**
     * Lettura costo fascia oraria salvato.
     */
    public static function getByConvenzioneAnno(int $idConvenzione, int $anno): ?object {
        return DB::table(self::TABLE)
            ->where('idConvenzione', $idConvenzione)
            ->where('idAnno', $anno)
            ->first();
    }

    /**
     * Upsert costo fascia oraria.
     */
    public static function upsertCosto(int $idConvenzione, int $anno, float $costo): void {
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
    public static function getStato(int $idConvenzione, int $anno): object {
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

    public static function calcolaCostoSostitutivi(int $idConvenzione, int $anno): float {
        // 1️⃣ Recupero TUTTE le righe di km di quella convenzione
        $righe = DB::table('automezzi_km')
            ->select('idAutomezzo', 'KMPercorsi', 'is_titolare')
            ->where('idConvenzione', $idConvenzione)
            ->where('KMPercorsi', '>', 0)
            ->get();

        if ($righe->isEmpty()) {
            return 0.0;
        }

        // 2️⃣ Titolare certo al 100%
        $mezzoTitolare = $righe->firstWhere('is_titolare', 1)->idAutomezzo ?? null;

        // 3️⃣ Mezzi sostitutivi = tutti quelli con km > 0 tranne il titolare
        $mezziSostitutivi = $righe
            ->filter(fn($r) => $r->idAutomezzo != $mezzoTitolare)
            ->pluck('idAutomezzo')
            ->unique()
            ->toArray();

        if (empty($mezziSostitutivi)) {
            return 0.0;
        }

        // 4️⃣ Somma costi reali ammessi
        $tot = 0.0;
        foreach ($mezziSostitutivi as $idMezzo) {
            $tot += self::quotaRipartitaSostitutivo($idMezzo, $idConvenzione, $anno);
        }

        return round($tot, 2);
    }

    private static function quotaRipartitaSostitutivo(int $idMezzo, int $idConvenzione, int $anno): float
{
    // costi annuali ammessi
    $c = DB::table('costi_automezzi')
        ->where('idAutomezzo', $idMezzo)
        ->where('idAnno', $anno)
        ->first();

    if (!$c) return 0.0;

    $costoAmmesso =
          ($c->LeasingNoleggio)
        + ($c->Assicurazione)
        + ($c->ManutenzioneOrdinaria)
        + ($c->ManutenzioneStraordinaria - $c->RimborsiAssicurazione)
        + ($c->PuliziaDisinfezione)
        + ($c->InteressiPassivi)
        + ($c->ManutenzioneSanitaria)
        + ($c->LeasingSanitaria)
        + ($c->AmmortamentoMezzi)
        + ($c->AmmortamentoSanitaria)
        + ($c->AltriCostiMezzi);

    // km totali mezzo
    $kmTot = DB::table('automezzi_km')
        ->where('idAutomezzo', $idMezzo)
        ->sum('KMPercorsi');

    if ($kmTot <= 0) return 0.0;

    // km del mezzo sulla convenzione
    $kmConv = DB::table('automezzi_km')
        ->where('idAutomezzo', $idMezzo)
        ->where('idConvenzione', $idConvenzione)
        ->sum('KMPercorsi');

    if ($kmConv <= 0) return 0.0;

    // quota ripartita
    return round($costoAmmesso * ($kmConv / $kmTot), 2);
}

}
