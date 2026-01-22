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

    public static function calcolaCostoSostitutivi(int $idConvenzione, int $anno): float {
        // info convenzione
        $conv = DB::table('convenzioni')
            ->where('idConvenzione', $idConvenzione)
            ->where('idAnno', $anno)
            ->first();

        if (!$conv) {
            return 0.0;
        }

        $idAssociazione = (int)$conv->idAssociazione;
        $nomeConv       = (string)$conv->Convenzione;

        // km mezzi su quella convenzione nell'anno
        $righe = DB::table('automezzi_km as ak')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'ak.idConvenzione')
            ->select('ak.idAutomezzo', 'ak.is_titolare')
            ->where('ak.idConvenzione', $idConvenzione)
            ->where('c.idAnno', $anno)
            ->where('ak.KMPercorsi', '>', 0)
            ->get();

        if ($righe->isEmpty()) {
            return 0.0;
        }

        // titolare
        $titolareRow = $righe->firstWhere('is_titolare', 1);
        $idTitolare  = $titolareRow ? (int)$titolareRow->idAutomezzo : 0;

        // sostitutivi = tutti tranne titolare
        $mezziSostitutivi = $righe
            ->filter(function ($r) use ($idTitolare) {
                return (int)$r->idAutomezzo !== $idTitolare;
            })
            ->pluck('idAutomezzo')
            ->unique()
            ->toArray();

        if (empty($mezziSostitutivi)) {
            return 0.0;
        }

        $vociTarget = RipartizioneCostiService::VOCI_MEZZI_SOSTITUTIVI;

        // =========================
        // SOMMA "EXCEL-LIKE":
        // sommo in euro e arrotondo SOLO alla fine
        // =========================
        $totEuro = 0.0;

        foreach ($mezziSostitutivi as $idMezzo) {
            $idMezzo = (int)$idMezzo;

            $tab = RipartizioneCostiService::calcolaRipartizioneTabellaFinale(
                $idAssociazione,
                $anno,
                $idMezzo
            );

            $subEuro = 0.0;

            foreach ($tab as $r) {
                if (!isset($r['voce'])) continue;
                if (!in_array($r['voce'], $vociTarget, true)) continue;

                // prendi il valore così com'è (NO round qui)
                $valEuro = (float)($r[$nomeConv] ?? 0.0);

                Log::info('[MEZZI SOST] cella', [
                    'mezzo' => $idMezzo,
                    'voce'  => $r['voce'],
                    'conv'  => $nomeConv,
                    'val'   => $valEuro,
                ]);

                $subEuro += $valEuro;
            }

            Log::info('[MEZZI SOST] subtot mezzo', [
                'mezzo' => $idMezzo,
                'sub'   => $subEuro,
            ]);

            $totEuro += $subEuro;
        }

        Log::info('[MEZZI SOST] totale euro pre-round', [
            'conv' => $nomeConv,
            'tot'  => $totEuro,
        ]);

        return round($totEuro, 2, PHP_ROUND_HALF_UP);
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
            'idConvenzione' => $idConvenzione,
            'anno'          => $anno,
            'costo_ammesso' => round($costoAmmesso, 2),
            'km_conv'       => round($kmConv, 2),
            'km_tot_mezzo'  => round($kmTot, 2),
            'rapporto'      => ($kmTot > 0 ? round($kmConv / $kmTot, 6) : 0),
            'quota_euro'    => round($costoAmmesso * ($kmConv / $kmTot), 2),
        ]);

        return round($costoAmmesso * ($kmConv / $kmTot), 2);
    }

    private static function euroToCentsExcel($euro) {
        // forza 2 decimali come stringa
        $s = number_format((float)$euro, 2, '.', '');
        // "387.10" -> 38710
        return (int)str_replace('.', '', $s);
    }
}
