<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ConvenzioniMaterialeSanitario extends Model
{
    protected const TABLE = 'convenzioni_materiale_sanitario';

        public static function getAll(): Collection
    {
        $sql = "
            SELECT
                cms.id,
                cms.sigla,
                cms.descrizione,
                cms.created_at,
                cms.updated_at
            FROM " . self::TABLE . " AS cms
            ORDER BY cms.sigla
        ";

        return collect(DB::select($sql));
    }

    /**
     * Recupera un singolo record per ID
     */
    public static function getById(int $id): ?object
    {
        $sql = "
            SELECT
                cms.id,
                cms.sigla,
                cms.descrizione,
                cms.created_at,
                cms.updated_at
            FROM " . self::TABLE . " AS cms
            WHERE cms.id = :id
            LIMIT 1
        ";

        $result = DB::select($sql, ['id' => $id]);

        return $result ? $result[0] : null;
    }

        public static function createConvMatSanitario(string $sigla, string $descrizione): int
    {
        $sql = "
            INSERT INTO " . self::TABLE . " (sigla, descrizione, created_at, updated_at)
            VALUES (:sigla, :descrizione, NOW(), NOW())
        ";

        DB::insert($sql, [
            'sigla'       => $sigla,
            'descrizione' => $descrizione,
        ]);

        // ritorna l'ID del record appena creato
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Aggiorna un record esistente
     */
    public static function updateConvMatSanitario(int $id, string $sigla, string $descrizione): bool
    {
        $sql = "
            UPDATE " . self::TABLE . "
            SET sigla = :sigla,
                descrizione = :descrizione,
                updated_at = NOW()
            WHERE id = :id
        ";

        return DB::update($sql, [
            'id'          => $id,
            'sigla'       => $sigla,
            'descrizione' => $descrizione,
        ]) > 0;
    }

    /**
     * Elimina un record
     */
    public static function deleteConvMatSanitario(int $id): bool
    {
        $sql = "
            DELETE FROM " . self::TABLE . "
            WHERE id = :id
        ";

        return DB::delete($sql, ['id' => $id]) > 0;
    }
}
