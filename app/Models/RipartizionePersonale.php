<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RipartizionePersonale
{
    protected const TABLE = 'dipendenti_servizi';

    public static function getAll(int $anno, $user, ?int $idAssociazioneFiltro = null)
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

        if ($idAssociazioneFiltro !== null) {
            $q->where('d.idAssociazione', $idAssociazioneFiltro);
        } elseif (!$user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])) {
            $q->where('d.idAssociazione', $user->IdAssociazione);
        }

        return collect($q->get());
    }

    public static function sumOre($collection)
    {
        return $collection->sum('OreServizio');
    }

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

    public static function upsert(int $idDip, int $idConv, float $ore)
    {
        DB::table(self::TABLE)
          ->updateOrInsert(
              ['idDipendente' => $idDip, 'idConvenzione' => $idConv],
              ['OreServizio'  => $ore, 'updated_at' => now()]
          );
    }

    public static function deleteByDipendente(int $idDip)
    {
        DB::table(self::TABLE)
          ->where('idDipendente', $idDip)
          ->delete();
    }
}
