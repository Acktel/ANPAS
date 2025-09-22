<?php

namespace App\Models;

use App\Helpers\SDB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use stdClass;

class Nations extends Model
{
    public static function getAll(bool $onlyActive = false): array
    {
        $whereOnlyActive = "";
        if ($onlyActive) $whereOnlyActive = "WHERE active = 1";
        return DB::select("SELECT * FROM ter_nations {$whereOnlyActive} ORDER BY denominazione_nazione");
    }

    public static function getByName(string $name, bool $onlyActive = false): ?stdClass
    {
        $whereOnlyActive = "";
        if ($onlyActive) $whereOnlyActive = "AND active = 1";
        return DB::select("SELECT * FROM ter_nations WHERE denominazione_nazione = ? {$whereOnlyActive} LIMIT 1", [$name])[0] ?? null;
    }

    public static function getByNationality(string $nationality, bool $onlyActive = false): ?stdClass
    {
        $whereOnlyActive = "";
        if ($onlyActive) $whereOnlyActive = "AND active = 1";
        return DB::select("SELECT * FROM ter_nations WHERE denominazione_cittadinanza = ? {$whereOnlyActive} LIMIT 1", [$nationality])[0] ?? null;
    }

    public static function getByCode(string $code, bool $onlyActive = false): ?stdClass
    {
        $whereOnlyActive = "";
        if ($onlyActive) $whereOnlyActive = "AND active = 1";
        return DB::select("SELECT * FROM ter_nations WHERE codice_belfiore = ? {$whereOnlyActive} LIMIT 1", [$code])[0] ?? null;
    }

    public static function softDelete(int $id)
    {
        return DB::statement("UPDATE ter_nations SET deleted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function definitiveDelete(int $id)
    {
        return DB::statement("DELETE FROM ter_nations WHERE deleted_at IS NULL AND id = ?", [$id]);
    }

    public static function insert(array $insertData)
    {
        return (new SDB)->insert('ter_nations', $insertData);
    }

    public static function modify($id, array $insertData)
    {
        return (new SDB)->modify('ter_nations', $id, $insertData);
    }
}
