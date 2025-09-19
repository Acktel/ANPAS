<?php

namespace App\Models;

use App\Helpers\SDB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use stdClass;

class Cities extends Model
{
    public static function getAll(): array
    {
        return DB::select("SELECT * FROM ter_cities ORDER BY denominazione_ita ASC");
    }

    public static function getAllWithCap(): array
    {
        return DB::select("SELECT cap, denominazione_ita, sigla_provincia, denominazione_provincia, denominazione_regione, codice_istat, codice_belfiore FROM ter_cities_cap ORDER BY denominazione_ita ASC, cap ASC");
    }

    public static function getInformationsByCapAndIstatCode(int $istatCode, int $cap): ?\stdClass
    {
        $q = "  SELECT 
                    'Italia' AS nation,
                    'IT' AS nation_sigle,
                    c.denominazione_ita AS city,
                    c.denominazione_provincia AS province,
                    c.sigla_provincia AS province_sigle,
                    c.denominazione_regione AS region,
                    c.cap
                FROM ter_cities_cap c
                WHERE c.codice_istat = ? AND c.cap = ? 
                LIMIT 1
        ";

        return DB::select($q, [$istatCode, $cap])[0] ?? null;
    }


    public static function softDelete(int $id)
    {
        return DB::statement("UPDATE ter_cities SET deleted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function definitiveDelete(int $id)
    {
        return DB::statement("DELETE FROM ter_cities WHERE deleted_at IS NULL AND id = ?", [$id]);
    }

    public static function insert(array $insertData)
    {
        return (new SDB)->insert('ter_cities', $insertData);
    }

    public static function modify($id, array $insertData)
    {
        return (new SDB)->modify('ter_cities', $id, $insertData);
    }

    public static function getById(int $id): ?stdClass
    {
        return DB::select("SELECT id, label FROM ter_cities WHERE id = ? LIMIT 1", [$id])[0];
    }

    public static function getByCap(string $cap): ?stdClass
    {
        return DB::select("SELECT * FROM ter_cities_cap WHERE cap = ? LIMIT 1", [$cap])[0] ?? null;
    }

    public static function getByName(string $name): ?stdClass
    {
        return DB::select("SELECT * FROM ter_cities_cap WHERE denominazione_ita = ? LIMIT 1", [$name])[0] ?? null;
    }

    public static function getByIstatCode(string $code): ?stdClass
    {
        return DB::select("SELECT * FROM ter_cities WHERE codice_istat = ? LIMIT 1", [$code])[0] ?? null;
    }

    public static function getByBelfioreCodeAndDate(string $code, string $date = ""): ?stdClass
    {
        if (!empty($date)) {
            $q = "  SELECT * 
                    FROM ter_city_validity 
                    WHERE codice_belfiore = ? 
                        AND data_inizio_validita <= ?
                        AND (data_fine_validita IS NULL OR data_fine_validita >= ?)
                        AND stato_validita = ?
                    LIMIT 1
            ";
            return DB::select($q, [$code, $date, $date, 'Attivo'])[0] ?? null;
        }
        return DB::select("SELECT * FROM ter_cities WHERE codice_belfiore = ? LIMIT 1", [$code])[0] ?? null;
    }
}
