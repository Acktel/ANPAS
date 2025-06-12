<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class Convenzione
{
    protected const TABLE = 'convenzioni';

    /**
     * Recupera tutte le convenzioni per anno (solo SuperAdmin/Admin/Supervisor).
     */
    public static function getAll(?int $anno = null)
    {
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
     * Recupera convenzioni per una associazione (filtro anno opzionale).
     */
    public static function getByAssociazione(int $idAssociazione, ?int $idAnno = null)
    {
        $idAnno = $idAnno ?? session('anno_riferimento', now()->year);

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
     * Recupera convenzioni per associazione e anno specifici.
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
              AND c.idAnno = :idAnno
            ORDER BY c.Convenzione
        ";

        return collect(DB::select($sql, [
            'idAssociazione' => $idAssociazione,
            'idAnno' => $idAnno,
        ]));
    }

    /**
     * Recupera una convenzione singola per ID.
     */
    public static function getById(int $idConvenzione)
    {
        return DB::selectOne(
            "SELECT * FROM " . self::TABLE . " WHERE idConvenzione = :id LIMIT 1",
            ['id' => $idConvenzione]
        );
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
        (:idAssociazione, :idAnno, :Convenzione, :lettera_identificativa, :created_at, :updated_at)
", [
    'idAssociazione'         => $data['idAssociazione'],
    'idAnno'                 => $data['idAnno'],
    'Convenzione'            => $data['Convenzione'],
    'lettera_identificativa' => $data['lettera_identificativa'],
    'created_at'             => $now,
    'updated_at'             => $now,
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
     * Elimina una convenzione per ID.
     */
    public static function deleteConvenzione(int $id): void
    {
        DB::delete("DELETE FROM " . self::TABLE . " WHERE idConvenzione = ?", [$id]);
    }
}
