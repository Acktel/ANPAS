<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RapportoRicavo {
    protected const TABLE = 'rapporti_ricavi';

    public static function getGroupedByConvenzione(int $anno, $user): Collection {
        $query = DB::table(self::TABLE . ' as rr')
            ->join('convenzioni as c', 'rr.idConvenzione', '=', 'c.idConvenzione')
            ->select([
                'rr.idConvenzione',
                'c.Convenzione',
                'rr.Rimborso',
            ])
            ->where('rr.idAnno', $anno);

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            $query->where('rr.idAssociazione', $user->idAssociazione);
        }

        return $query->get();
    }

    public static function getByAssociazione(int $anno, int $idAssociazione): Collection {
        return DB::table(self::TABLE)
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->get();
    }

    public static function upsert(int $idConvenzione, int $idAssociazione, int $anno, float $rimborso): void {
        DB::table(self::TABLE)->updateOrInsert(
            [
                'idConvenzione' => $idConvenzione,
                'idAssociazione' => $idAssociazione,
                'idAnno' => $anno,
            ],
            [
                'Rimborso' => $rimborso,
                'updated_at' => now(),
            ]
        );
    }

    public static function deleteByAssociazione(int $idAssociazione, int $anno): void {
        DB::table(self::TABLE)
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->delete();
    }

    public static function getAllByAnno(int $anno, $user): Collection {
        $q = DB::table(self::TABLE . ' as rr')
            ->join('convenzioni as c', 'rr.idConvenzione', '=', 'c.idConvenzione')
            ->join('associazioni as a', 'rr.idAssociazione', '=', 'a.idAssociazione')
            ->select([
                'rr.idAssociazione',
                'a.Associazione',
                'rr.idConvenzione',
                'rr.Rimborso'
            ])
            ->where('rr.idAnno', $anno);
        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $q->where('rr.idAssociazione', $user->idAssociazione);
        }
        return collect($q->get());
    }
}
