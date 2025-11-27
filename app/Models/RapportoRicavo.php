<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RapportoRicavo {
    protected const TABLE = 'rapporti_ricavi';

    public static function getByAssociazione(int $anno, int $idAssociazione): Collection {
        return DB::table(self::TABLE)
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->get();
    }

    public static function mapByAssociazione(int $anno, int $idAssociazione): array {
        return DB::table(self::TABLE)
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->pluck('Rimborso', 'idConvenzione')
            ->toArray();
    }

    public static function getWithConvenzioni(int $anno, int $idAssociazione): Collection {
        return DB::table(self::TABLE . ' as rr')
            ->join('convenzioni as c', 'rr.idConvenzione', '=', 'c.idConvenzione')
            ->select(['rr.idConvenzione', 'c.Convenzione', 'rr.Rimborso', 'rr.note'])
            ->where('rr.idAnno', $anno)
            ->where('rr.idAssociazione', $idAssociazione)
            ->orderBy('c.ordinamento')
            ->orderBy('rr.idConvenzione')
            ->get();
    }

    /** Inserisce/aggiorna rimborso + nota per (anno, associazione, convenzione) */
    public static function upsert(int $idConvenzione, int $idAssociazione, int $anno, float $rimborso, ?string $note = null): void {
        DB::table(self::TABLE)->updateOrInsert(
            [
                'idConvenzione'  => $idConvenzione,
                'idAssociazione' => $idAssociazione,
                'idAnno'         => $anno,
            ],
            [
                'Rimborso'   => $rimborso,
                'note'       => ($note === '') ? null : $note,
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

    public static function getAllByAnno(int $anno, ?int $idAssociazione = null): Collection {
        $q = DB::table(self::TABLE . ' as rr')
            ->join('convenzioni as c', 'rr.idConvenzione', '=', 'c.idConvenzione')
            ->join('associazioni as a', 'rr.idAssociazione', '=', 'a.idAssociazione')
            ->select(['rr.idAssociazione', 'a.Associazione', 'rr.idConvenzione', 'rr.Rimborso', 'rr.note'])
            ->where('rr.idAnno', $anno);

        if ($idAssociazione) $q->where('rr.idAssociazione', $idAssociazione);

        return collect($q->get());
    }

    public static function getRicaviPerAssociazione(int $idAssociazione, int $anno) {
        return DB::table('rapporti_ricavi as rr')
            ->join('convenzioni as c', 'rr.idConvenzione', '=', 'c.idConvenzione')
            ->select(
                'rr.idConvenzione',
                'c.Convenzione',
                'rr.Rimborso',
                'rr.note'
            )
            ->where('rr.idAssociazione', $idAssociazione)
            ->where('rr.idAnno', $anno)
            ->orderBy('c.ordinamento')
            ->orderBy('rr.idConvenzione')
            ->get();
    }

    public static function getTotaleRicavi(int $idAssociazione, int $anno): float {
        return (float) DB::table('rapporti_ricavi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->sum('Rimborso');
    }
}
