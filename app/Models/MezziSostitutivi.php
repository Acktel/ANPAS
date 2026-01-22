<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;
use Illuminate\Support\Facades\Log;

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

    public static function calcolaCostoSostitutivi($idConvenzione, $anno) {
        $righe = DB::table('automezzi_km as ak')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
            ->select('ak.idAutomezzo', 'ak.KMPercorsi', 'ak.is_titolare')
            ->where('ak.idConvenzione', $idConvenzione)
            ->where('c.idAnno', $anno)
            ->where('ak.KMPercorsi', '>', 0)
            ->get();

        if ($righe->isEmpty()) {
            return 0.0;
        }
        Log::info('SOST:  Righe: '.$righe);

        $titolareRow = $righe->firstWhere('is_titolare', 1);
        $mezzoTitolare = $titolareRow ? (int)$titolareRow->idAutomezzo : null;

        $mezziSostitutivi = $righe
            ->filter(function ($r) use ($mezzoTitolare) {
                return (int)$r->idAutomezzo !== (int)$mezzoTitolare;
            })
            ->pluck('idAutomezzo')
            ->unique()
            ->toArray();

        if (empty($mezziSostitutivi)) {
            return 0.0;
        }

        $tot = 0.0;
        foreach ($mezziSostitutivi as $idMezzo) {
            $tot += self::quotaRipartitaSostitutivo((int)$idMezzo, $idConvenzione, $anno);
            Log::info('SOST: mezzo '.$idMezzo.' conv '.$idConvenzione.' anno '.$anno.' quota='.$tot);

        }

        return round($tot, 2);
    }


    private static function quotaRipartitaSostitutivo($idMezzo, $idConvenzione, $anno) {
        $c = DB::table('costi_automezzi')
            ->where('idAutomezzo', $idMezzo)
            ->where('idAnno', $anno)
            ->first();

        if (!$c) return 0.0;

        $costoAmmesso =
            ((float)$c->LeasingNoleggio)
            + ((float)$c->Assicurazione)
            + ((float)$c->ManutenzioneOrdinaria)
            + (((float)$c->ManutenzioneStraordinaria) - ((float)$c->RimborsiAssicurazione))
            + ((float)$c->PuliziaDisinfezione)
            + ((float)$c->InteressiPassivi)
            + ((float)$c->ManutenzioneSanitaria)
            + ((float)$c->LeasingSanitaria)
            + ((float)$c->AmmortamentoMezzi)
            + ((float)$c->AmmortamentoSanitaria)
            + ((float)$c->AltriCostiMezzi);

        // km totali mezzo (solo anno)
        $kmTot = (float) DB::table('automezzi_km as ak')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
            ->where('ak.idAutomezzo', $idMezzo)
            ->where('c.idAnno', $anno)
            ->sum('ak.KMPercorsi');

        if ($kmTot <= 0) return 0.0;

        // km del mezzo sulla convenzione (solo anno)
        $kmConv = (float) DB::table('automezzi_km as ak')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
            ->where('ak.idAutomezzo', $idMezzo)
            ->where('ak.idConvenzione', $idConvenzione)
            ->where('c.idAnno', $anno)
            ->sum('ak.KMPercorsi');

        if ($kmConv <= 0) return 0.0;
        Log::info('[MEZZI SOST] dettaglio calcolo', [
            'idAutomezzo'   => $idMezzo,
            'idConvenzione'=> $idConvenzione,
            'anno'          => $anno,
            'costo_ammesso' => round($costoAmmesso, 2),
            'km_conv'       => round($kmConv, 2),
            'km_tot_mezzo'  => round($kmTot, 2),
            'rapporto'      => ($kmTot > 0 ? round($kmConv / $kmTot, 6) : 0),
            'quota_euro'    => round($costoAmmesso * ($kmConv / $kmTot), 2),
        ]);

        return round($costoAmmesso * ($kmConv / $kmTot), 2);
    }
}
