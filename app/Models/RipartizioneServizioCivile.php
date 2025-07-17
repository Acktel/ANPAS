<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RipartizioneServizioCivile
{
    // usiamo la stessa tabella usata per i dipendenti
    protected const TABLE = 'dipendenti_servizi';
    public const ID_SERVIZIO_CIVILE = 999998;

    /**
     * Restituisce, per ciascuna convenzione, la somma delle OreServizio
     * solo per i dipendenti che hanno idQualifica = 15 (volontari),
     * utilizzando la tabella pivot dipendenti_qualifiche.
     */
    public static function getAggregato(int $anno, $user)
    {
        $query = DB::table(self::TABLE . ' as ds')
            ->join('convenzioni as c', 'ds.idConvenzione', '=', 'c.idConvenzione')
            ->select('ds.idConvenzione', DB::raw('SUM(ds.OreServizio) as OreServizio'))
            ->where('ds.idDipendente', self::ID_SERVIZIO_CIVILE) // ← id fittizio aggregato
            ->where('c.idAnno', $anno)
            ->groupBy('ds.idConvenzione');

        if (! $user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])) {
            $query->where('c.idAssociazione', $user->IdAssociazione);
        }

        return $query->get();
    }


    /**
     * Inserisce o aggiorna la somma aggregata nella stessa tabella.
     * idDipendente = null perché è un totale per convenzione.
     */
    public static function upsert(?int $idDipendente, int $idConvenzione, float $ore)
    {
        $idDipendente = self::ID_SERVIZIO_CIVILE;
        DB::table(self::TABLE)
            ->updateOrInsert(
                ['idDipendente'   => $idDipendente,   // null
                 'idConvenzione'  => $idConvenzione],
                ['OreServizio'    => $ore,
                 'updated_at'     => now()]
            );
    }
}
