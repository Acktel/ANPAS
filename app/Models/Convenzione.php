<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Convenzione {
    protected const TABLE = 'convenzioni';

    /**
     * Recupera tutte le convenzioni per anno, con join associazione (solo ruoli alti).
     */
    public static function getAll(?int $anno = null): Collection {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        $sql = "
            SELECT
                c.idConvenzione,
                s.Associazione,
                c.idAnno,
                c.Convenzione,
                c.lettera_identificativa,
                c.created_at
            FROM " . self::TABLE . " AS c
            JOIN associazioni AS s ON c.idAssociazione = s.idAssociazione
            WHERE c.idAnno = :anno
            ORDER BY s.Associazione, c.Convenzione
        ";

        return collect(DB::select($sql, ['anno' => $anno]));
    }

    /**
     * Recupera convenzioni per anno e (opzionalmente) filtro utente.
     */
    public static function getByAnno(int $anno, ?\App\Models\User $user = null): Collection {
        $sql = "
            SELECT
                c.idConvenzione,
                c.idAssociazione,
                c.idAnno,
                c.Convenzione,
                c.lettera_identificativa,
                c.created_at
            FROM " . self::TABLE . " AS c
            WHERE c.idAnno = :anno
        ";

        $params = ['anno' => $anno];
        if ($user && !$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $sql .= " AND c.idAssociazione = :idAssociazione";
            $params['idAssociazione'] = $user->IdAssociazione;
        }

        $sql .= " ORDER BY c.Convenzione";
        return collect(DB::select($sql, $params));
    }

    /**
     * Convenzioni per specifica associazione (e opzionalmente per anno).
     */
    public static function getByAssociazione(int $idAssociazione, ?int $idAnno = null): Collection {
        $idAnno = $idAnno ?? session('anno_riferimento', now()->year);

        $sql = "
            SELECT
                c.idConvenzione,
                c.idAssociazione,
                c.idAnno,
                c.Convenzione,
                c.lettera_identificativa,
                c.created_at
            FROM " . self::TABLE . " AS c
            WHERE c.idAssociazione = :idAssociazione
              AND c.idAnno = :idAnno
            ORDER BY c.Convenzione
        ";

        return collect(DB::select($sql, [
            'idAssociazione' => $idAssociazione,
            'idAnno' => $idAnno,
        ]));
    }

    /**
     * Singola convenzione per ID.
     */
    public static function getById(int $id): ?object {
        return DB::selectOne("SELECT * FROM " . self::TABLE . " WHERE idConvenzione = :id LIMIT 1", ['id' => $id]);
    }

    /**
     * Crea una nuova convenzione.
     */
    public static function createConvenzione(array $data): int {
        $now = now()->toDateTimeString();

        $maxOrd = DB::table(self::TABLE)
            ->where('idAssociazione', $data['idAssociazione'])
            ->where('idAnno', $data['idAnno'])
            ->max('ordinamento');

        $ordinamento = is_null($maxOrd) ? 0 : $maxOrd + 1;

        DB::insert("INSERT INTO " . self::TABLE . "
            (idAssociazione, idAnno, Convenzione, lettera_identificativa, ordinamento, created_at, updated_at)
            VALUES
            (:idAssociazione, :idAnno, :Convenzione, :lettera_identificativa, :ordinamento, :created_at, :updated_at)", [
            'idAssociazione'         => $data['idAssociazione'],
            'idAnno'                 => $data['idAnno'],
            'Convenzione'            => $data['Convenzione'],
            'lettera_identificativa' => $data['lettera_identificativa'],
            'ordinamento'            => $ordinamento,
            'created_at'             => $now,
            'updated_at'             => $now,
        ]);

        return DB::getPdo()->lastInsertId();
    }

    /**
     * Aggiorna convenzione.
     */
    public static function updateConvenzione(int $id, array $data): void {
        $now = Carbon::now()->toDateTimeString();

        DB::update("UPDATE " . self::TABLE . "
            SET
                idAssociazione         = :idAssociazione,
                idAnno                 = :idAnno,
                Convenzione            = :Convenzione,
                lettera_identificativa = :lettera_identificativa,
                updated_at             = :updated_at
            WHERE idConvenzione = :id", [
            'idAssociazione'         => $data['idAssociazione'],
            'idAnno'                 => $data['idAnno'],
            'Convenzione'            => $data['Convenzione'],
            'lettera_identificativa' => $data['lettera_identificativa'],
            'updated_at'             => $now,
            'id'                     => $id,
        ]);
    }

    /**
     * Cancella convenzione.
     */
    public static function deleteConvenzione(int $id): void {
        DB::delete("DELETE FROM " . self::TABLE . " WHERE idConvenzione = ?", [$id]);
    }

public static function getWithAssociazione($idAssociazione, $anno): \Illuminate\Support\Collection {
    $sql = "
        SELECT 
            c.idConvenzione,
            c.idAssociazione,
            c.idAnno,
            c.Convenzione,
            c.lettera_identificativa,
            c.ordinamento,
            c.created_at,
            c.updated_at,
            a.Associazione,
            GROUP_CONCAT(asl.Nome ORDER BY asl.Nome SEPARATOR ', ') AS AziendeSanitarie
        FROM convenzioni AS c
        JOIN associazioni AS a ON a.idAssociazione = c.idAssociazione
        LEFT JOIN azienda_sanitaria_convenzione AS asco ON c.idConvenzione = asco.idConvenzione
        LEFT JOIN aziende_sanitarie AS asl ON asco.idAziendaSanitaria = asl.idAziendaSanitaria
        WHERE c.idAssociazione = :idAssociazione
          AND c.idAnno = :idAnno
        GROUP BY 
            c.idConvenzione,
            c.idAssociazione,
            c.idAnno,
            c.Convenzione,
            c.lettera_identificativa,
            c.ordinamento,
            c.created_at,
            c.updated_at,
            a.Associazione
        ORDER BY c.ordinamento, c.idConvenzione
    ";

    return collect(DB::select($sql, [
        'idAssociazione' => $idAssociazione,
        'idAnno' => $anno,
    ]));
}



    public static function getByAssociazioneAnno(?int $idAssociazione, int $idAnno): Collection {
        $query = DB::table(self::TABLE)->where('idAnno', $idAnno);

        if (!is_null($idAssociazione)) {
            $query->where('idAssociazione', $idAssociazione);
        }

        return $query->orderBy('Convenzione')->get();
    }
}
