<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class Convenzione
{
    protected const TABLE = 'convenzioni';

    /**
     * Recupera tutte le convenzioni (con join su associazioni per il nome).
     */
    public static function getAll()
    {
        $sql = "
            SELECT
                c.idConvenzione,
                s.Associazione,
                c.idAnno,
                c.Convenzione,
                c.lettera_identificativa,
                c.created_at
            FROM " . self::TABLE . " AS c
            JOIN associazioni AS s
              ON c.idAssociazione = s.idAssociazione
            ORDER BY s.Associazione, c.idAnno DESC, c.Convenzione
        ";
        return collect(DB::select($sql));
    }

    /**
     * Recupera tutte le convenzioni per una data associazione.
     */
    public static function getByAssociazione(int $idAssociazione)
    {
        $sql = "
            SELECT
                c.idConvenzione,
                s.Associazione,
                c.idAnno,
                c.Convenzione,
                c.lettera_identificativa,
                c.created_at
            FROM " . self::TABLE . " AS c
            JOIN associazioni AS s
              ON c.idAssociazione = s.idAssociazione
            WHERE c.idAssociazione = :idAssociazione
            ORDER BY c.idAnno DESC, c.Convenzione
        ";
        return collect(DB::select($sql, ['idAssociazione' => $idAssociazione]));
    }

    /**
     * Recupera tutte le convenzioni per una data associazione E anno.
     */
    public static function getByAssociazioneAnno(int $idAssociazione, int $idAnno)
    {
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
              AND c.idAnno         = :idAnno
            ORDER BY c.Convenzione
        ";
        return collect(DB::select($sql, [
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $idAnno,
        ]));
    }

    /**
     * Recupera una singola convenzione per ID.
     */
    public static function getById(int $idConvenzione)
    {
        $row = DB::selectOne(
            "SELECT * FROM " . self::TABLE . " WHERE idConvenzione = :id LIMIT 1",
            ['id' => $idConvenzione]
        );
        return $row;
    }

    /**
     * Crea una nuova convenzione.
     */
    public static function createConvenzione(array $data): int
    {
        $now = Carbon::now()->toDateTimeString();
        DB::insert("
            INSERT INTO " . self::TABLE . "
                (idAssociazione, idAnno, Convenzione, lettera_identificativa, created_at, updated_at)
            VALUES
                (:idAssociazione, :idAnno, :Convenzione, :lettera_identificativa, :now, :now)
        ", [
            'idAssociazione'         => $data['idAssociazione'],
            'idAnno'                 => $data['idAnno'],
            'Convenzione'            => $data['Convenzione'],
            'lettera_identificativa' => $data['lettera_identificativa'],
            'now'                    => $now,
        ]);
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Aggiorna una convenzione esistente.
     */
    public static function updateConvenzione(int $id, array $data): void
    {
        $now = Carbon::now()->toDateTimeString();
        DB::update("
            UPDATE " . self::TABLE . "
            SET
              idAssociazione         = :idAssociazione,
              idAnno                 = :idAnno,
              Convenzione            = :Convenzione,
              lettera_identificativa = :lettera_identificativa,
              updated_at             = :now
            WHERE idConvenzione = :id
        ", [
            'idAssociazione'         => $data['idAssociazione'],
            'idAnno'                 => $data['idAnno'],
            'Convenzione'            => $data['Convenzione'],
            'lettera_identificativa' => $data['lettera_identificativa'],
            'now'                    => $now,
            'id'                     => $id,
        ]);
    }

    /**
     * Elimina una convenzione.
     */
    public static function deleteConvenzione(int $id): void
    {
        DB::delete("DELETE FROM " . self::TABLE . " WHERE idConvenzione = ?", [$id]);
    }
}
