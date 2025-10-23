<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\User;

class AutomezzoKm {
    protected const TABLE = 'automezzi_km';

    /** Normalizza qualsiasi input a intero non negativo (o 0). */
    private static function toIntKm($v): int {
        if ($v === null || $v === '') return 0;
        // rimuove spazi/virgole, converte a float e poi arrotonda all'intero
        $n = (int) round((float) str_replace([',', ' '], ['.', ''], (string) $v));
        return max(0, $n);
    }

    /**
     * Inserisce una nuova riga nella tabella automezzi_km.
     */
    public static function add(int $idAutomezzo, int $idConvenzione, $KMPercorsi): int {
        return DB::table(self::TABLE)->insertGetId([
            'idAutomezzo'   => $idAutomezzo,
            'idConvenzione' => $idConvenzione,
            'KMPercorsi'    => self::toIntKm($KMPercorsi),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ], 'idAutomezzoKM');
    }

    /**
     * Recupera tutte le righe per un automezzo e anno specifici (KMPercorsi come intero).
     */
    public static function getByAutomezzo(int $idAutomezzo, int $anno): Collection {
        return DB::table(self::TABLE . ' as k')
            ->join('convenzioni as c', 'k.idConvenzione', '=', 'c.idConvenzione')
            ->where('k.idAutomezzo', $idAutomezzo)
            ->where('c.idAnno', $anno)
            ->select([
                'k.idAutomezzoKM',
                'c.Convenzione',
                DB::raw('CAST(k.KMPercorsi AS SIGNED) as KMPercorsi'),
                'k.created_at',
            ])
            ->orderByDesc('k.created_at')
            ->get();
    }

    /**
     * Km raggruppati per (automezzo-convenzione). Restituisce map "idAutomezzo-idConvenzione" => collection.
     */
    public static function getGroupedByAutomezzoAndConvenzione(int $anno, ?User $user, $idAssociazione): Collection {
        $query = DB::table(self::TABLE . ' as k')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 'k.idAutomezzo')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
            ->where('a.idAnno', $anno)
            ->where('c.idAnno', $anno)
            ->where('a.idAssociazione', $idAssociazione)
            ->orderBy('a.CodiceIdentificativo')
            ->select([
                'k.idAutomezzoKM',
                'k.idAutomezzo',
                'k.idConvenzione',
                DB::raw('CAST(k.KMPercorsi AS SIGNED) as KMPercorsi'),
                'k.is_titolare',          
                'k.created_at',
                'k.updated_at',
            ]);

        if ($user && !$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $query->where('a.idAssociazione', $user->IdAssociazione);
        }

        return $query->get()->groupBy(fn($r) => $r->idAutomezzo . '-' . $r->idConvenzione);
    }


    /**
     * Upsert: aggiorna o inserisce km per coppia automezzo-convenzione.
     */
    public static function upsert(int $idAutomezzo, int $idConvenzione, $KMPercorsi): void {
        DB::table(self::TABLE)->updateOrInsert(
            [
                'idAutomezzo'   => $idAutomezzo,
                'idConvenzione' => $idConvenzione,
            ],
            [
                'KMPercorsi' => self::toIntKm($KMPercorsi),
                'created_at' => Carbon::now(),
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

    // ... (header classe invariato)

    public static function getKmPerConvenzione(int $idAutomezzo, int $anno): Collection {
        return DB::table(self::TABLE . ' as k')
            ->join('convenzioni as c',  'k.idConvenzione', '=', 'c.idConvenzione')
            ->where('k.idAutomezzo',   $idAutomezzo)
            ->where('c.idAnno', $anno)
            ->select(
                'k.idConvenzione',
                DB::raw('CAST(k.KMPercorsi AS SIGNED) as KMPercorsi'),
                'k.is_titolare'
            )
            ->get()
            ->keyBy('idConvenzione');
    }

    /** Nomina titolare in modo atomico (unico per convenzione). */
    public static function setTitolare(int $idConvenzione, int $idAutomezzo): void {
        DB::update(
            'UPDATE automezzi_km
         SET is_titolare = (idAutomezzo = ?), updated_at = NOW()
         WHERE idConvenzione = ?',
            [$idAutomezzo, $idConvenzione]
        );
    }

    /** Togli titolarità a questo mezzo su una convenzione. */
    public static function unsetTitolare(int $idConvenzione, int $idAutomezzo): void {
        DB::update(
            'UPDATE automezzi_km
         SET is_titolare = 0, updated_at = NOW()
         WHERE idConvenzione = ? AND idAutomezzo = ?',
            [$idConvenzione, $idAutomezzo]
        );
    }


    /**
     * Ritorna 0/1 se un automezzo è titolare per una convenzione.
     */
    public static function getIsTitolare(int $idAutomezzo, int $idConvenzione): int {
        $row = DB::selectOne(
            'SELECT is_titolare FROM automezzi_km WHERE idAutomezzo = ? AND idConvenzione = ? LIMIT 1',
            [$idAutomezzo, $idConvenzione]
        );
        return $row ? (int) $row->is_titolare : 0;
    }

    /**
     * Ritorna i dati del mezzo titolare per una convenzione (se esiste).
     */
    public static function getTitolareByConvenzione(int $idConvenzione): ?object {
        $sql = "
            SELECT a.idAutomezzo, a.Targa, a.CodiceIdentificativo, k.KMPercorsi
            FROM automezzi_km k
            JOIN automezzi a ON a.idAutomezzo = k.idAutomezzo
            WHERE k.idConvenzione = :conv AND k.is_titolare = 1
            LIMIT 1
        ";
        return DB::selectOne($sql, ['conv' => $idConvenzione]);
    }
}
