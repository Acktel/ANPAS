<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RapportoRicavo
{
    protected const TABLE = 'rapporti_ricavi';

    /** Tutte le righe per anno+associazione (key = idConvenzione) */
    public static function getByAssociazione(int $anno, int $idAssociazione): Collection
    {
        return DB::table(self::TABLE)
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->get();
    }

    /** Mappa idConvenzione => Rimborso per anno+associazione */
    public static function mapByAssociazione(int $anno, int $idAssociazione): array
    {
        return DB::table(self::TABLE)
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->pluck('Rimborso', 'idConvenzione')
            ->toArray();
    }

    /** Ricavi (join convenzioni) per anno+associazione — utile per viste/report */
    public static function getWithConvenzioni(int $anno, int $idAssociazione): Collection
    {
        return DB::table(self::TABLE.' as rr')
            ->join('convenzioni as c', 'rr.idConvenzione', '=', 'c.idConvenzione')
            ->select([
                'rr.idConvenzione',
                'c.Convenzione',
                'rr.Rimborso',
            ])
            ->where('rr.idAnno', $anno)
            ->where('rr.idAssociazione', $idAssociazione)
            ->orderBy('c.ordinamento')
            ->orderBy('rr.idConvenzione')
            ->get();
    }

    /** Inserisce/aggiorna il rimborso per (anno, associazione, convenzione) */
    public static function upsert(int $idConvenzione, int $idAssociazione, int $anno, float $rimborso): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            [
                'idConvenzione'  => $idConvenzione,
                'idAssociazione' => $idAssociazione,
                'idAnno'         => $anno,
            ],
            [
                'Rimborso'   => $rimborso,
                'updated_at' => now(),
                // NB: lasciamo gestire created_at dal primo insert (se serve aggiungilo qui)
            ]
        );
    }

    /** Cancella tutte le righe per anno+associazione */
    public static function deleteByAssociazione(int $idAssociazione, int $anno): void
    {
        DB::table(self::TABLE)
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->delete();
    }

    /* --- Opzionale: compat di vecchi metodi (se ancora referenziati altrove) --- */

    /** (Legacy) Ricavi di TUTTE le associazioni per anno, con join ad associazioni e convenzioni */
    public static function getAllByAnno(int $anno, ?int $idAssociazione = null): Collection
    {
        $q = DB::table(self::TABLE.' as rr')
            ->join('convenzioni as c', 'rr.idConvenzione', '=', 'c.idConvenzione')
            ->join('associazioni as a', 'rr.idAssociazione', '=', 'a.idAssociazione')
            ->select([
                'rr.idAssociazione',
                'a.Associazione',
                'rr.idConvenzione',
                'rr.Rimborso',
            ])
            ->where('rr.idAnno', $anno);

        if ($idAssociazione) {
            $q->where('rr.idAssociazione', $idAssociazione);
        }

        return collect($q->get());
    }
}
