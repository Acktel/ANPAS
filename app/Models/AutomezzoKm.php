<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AutomezzoKm {
    protected const TABLE = 'automezzi_km';

    /**
     * Inserisce una nuova riga nella tabella automezzi_km.
     */
    public static function add(int $idAutomezzo, int $idConvenzione, float $KMPercorsi): int {
        return DB::table(self::TABLE)->insertGetId([
            'idAutomezzo'   => $idAutomezzo,
            'idConvenzione' => $idConvenzione,
            'KMPercorsi'    => $KMPercorsi,
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ], 'idAutomezzoKM');
    }

    /**
     * Recupera tutte le righe per un automezzo e anno specifici.
     */
    public static function getByAutomezzo(int $idAutomezzo, int $anno): Collection {
        return DB::table(self::TABLE . ' as k')
            ->join('convenzioni as c', 'k.idConvenzione', '=', 'c.idConvenzione')
            ->where('k.idAutomezzo', $idAutomezzo)
            ->where('c.idAnno', $anno)
            ->select([
                'k.idAutomezzoKM',
                'c.Convenzione',
                'k.KMPercorsi',
                'k.created_at',
            ])
            ->orderByDesc('k.created_at')
            ->get();
    }

    /**
     * Recupera i km percorsi raggruppati per automezzo-convenzione.
     */
    public static function getGroupedByAutomezzoAndConvenzione(int $anno, ?User $user): Collection {
        $query = DB::table(self::TABLE . ' as k')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 'k.idAutomezzo')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
            ->where('a.idAnno', $anno)
            ->where('c.idAnno', $anno)
            ->orderBy('a.CodiceIdentificativo')
            ->select('k.*');

        if (! $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $query->where('a.idAssociazione', $user->IdAssociazione);
        }

        return $query->get()->groupBy(fn($r) => $r->idAutomezzo . '-' . $r->idConvenzione);
    }

    /**
     * Upsert: aggiorna o inserisce km per coppia automezzo-convenzione.
     */
    public static function upsert(int $idAutomezzo, int $idConvenzione, float $KMPercorsi): void {
        DB::table(self::TABLE)->updateOrInsert(
            [
                'idAutomezzo'   => $idAutomezzo,
                'idConvenzione' => $idConvenzione,
            ],
            [
                'KMPercorsi' => $KMPercorsi,
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Elimina tutti i km legati a un automezzo.
     */
    public static function deleteByAutomezzo(int $idAutomezzo): void {
        DB::table(self::TABLE)
            ->where('idAutomezzo', $idAutomezzo)
            ->delete();
    }

    /**
     * Elimina una singola riga di km.
     */
    public static function deleteSingle(int $idAutomezzoKM): void {
        DB::table(self::TABLE)
            ->where('idAutomezzoKM', $idAutomezzoKM)
            ->delete();
    }

    /**
     * Restituisce una mappa [idConvenzione => KMPercorsi] per un automezzo e anno.
     */
    public static function getKmPerConvenzione(int $idAutomezzo, int $anno): Collection {
        return DB::table(self::TABLE . ' as k')
            ->join('convenzioni as c', 'k.idConvenzione', '=', 'c.idConvenzione')
            ->where('k.idAutomezzo', $idAutomezzo)
            ->where('c.idAnno', $anno)
            ->select('k.idConvenzione', 'k.KMPercorsi')
            ->get()
            ->keyBy('idConvenzione');
    }
}
