<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\User;

class AutomezzoServiziSvolti
{
    protected const TABLE = 'automezzi_servizi';

    /**
     * Inserisce un nuovo record.
     */
    public static function add(int $idAutomezzo, int $idConvenzione, int $numeroServizi): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'idAutomezzo'      => $idAutomezzo,
            'idConvenzione'    => $idConvenzione,
            'NumeroServizi'    => $numeroServizi,
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ], 'idAutomezzoServizi');
    }

    /**
     * Inserisce o aggiorna il numero servizi.
     */
    public static function upsert(int $idAutomezzo, int $idConvenzione, int $numeroServizi): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            [
                'idAutomezzo'   => $idAutomezzo,
                'idConvenzione' => $idConvenzione,
            ],
            [
                'NumeroServizi' => $numeroServizi,
                'updated_at'    => Carbon::now(),
            ]
        );
    }

    /**
     * Elimina tutti i record di servizi per un automezzo.
     */
    public static function deleteByAutomezzo(int $idAutomezzo): void
    {
        DB::table(self::TABLE)
            ->where('idAutomezzo', $idAutomezzo)
            ->delete();
    }

    /**
     * Recupera i servizi raggruppati per automezzo e convenzione.
     */
    public static function getGroupedByAutomezzoAndConvenzione(int $anno, ?User $user): Collection
    {
        $query = DB::table(self::TABLE . ' as s')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 's.idAutomezzo')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 's.idConvenzione')
            ->where('a.idAnno', $anno)
            ->where('c.idAnno', $anno)
            ->select('s.*');

        if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $query->where('a.idAssociazione', $user->idAssociazione);
        }

        return $query->get()->groupBy(fn($r) => $r->idAutomezzo . '-' . $r->idConvenzione);
    }
}
