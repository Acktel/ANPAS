<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RipartizionePersonale
{
    // punta alla tabella dove memorizzi le ore di servizio
    protected const TABLE = 'dipendenti_servizi';

    /**
     * Prendo tutti i record per l’anno specificato, unendo
     * dipendenti_servizi (ds) con dipendenti (d) per filtrare su d.idAnno
     */
    public static function getAll(int $anno, $user)
    {
        $q = DB::table(self::TABLE . ' as ds')
            ->join('dipendenti as d', 'ds.idDipendente', '=', 'd.idDipendente')
            ->join('associazioni as a', 'd.idAssociazione', '=', 'a.idAssociazione')
            ->select(
                'ds.idDipendente',
                'ds.idConvenzione',
                'ds.OreServizio',
                'a.Associazione',
                'd.idAssociazione'
            )
            ->where('d.idAnno', $anno);

        if (! $user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])) {
            $q->where('d.idAssociazione', $user->idAssociazione);
        }

        return collect($q->get());
    }


    /**
     * Somma le OreServizio in una collection
     */
    public static function sumOre($collection)
    {
        return $collection->sum('OreServizio');
    }

    /**
     * Prendo i record di un singolo dipendente per l’anno dato,
     * filtrando sempre su d.idAnno
     */
    public static function getByDipendente(int $idDip, int $anno)
    {
        return collect(
            DB::table(self::TABLE . ' as ds')
              ->join('dipendenti as d', 'ds.idDipendente', '=', 'd.idDipendente')
              ->select('ds.idConvenzione', 'ds.OreServizio')
              ->where('ds.idDipendente', $idDip)
              ->where('d.idAnno', $anno)
              ->get()
        );
    }

    /**
     * Inserisce o aggiorna le ore per (dipendente, convenzione).
     * Nella tabella non c’è più idAnno, quindi la chiave unica è solo
     * idDipendente + idConvenzione.
     */
    public static function upsert(int $idDip, int $idConv, float $ore)
    {
        DB::table(self::TABLE)
          ->updateOrInsert(
              ['idDipendente' => $idDip, 'idConvenzione' => $idConv],
              ['OreServizio'  => $ore, 'updated_at' => now()]
          );
    }

    /**
     * Elimina tutti i record di un dipendente (indipendentemente dall’anno –
     * l’anno già viene filtrato in getAll/getByDipendente).
     */
    public static function deleteByDipendente(int $idDip)
    {
        DB::table(self::TABLE)
          ->where('idDipendente', $idDip)
          ->delete();
    }

}
