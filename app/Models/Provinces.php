<?php

namespace App\Models;

use App\Helpers\SDB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use stdClass;

class Provinces extends Model
{
    public static function getAll(): array
    {
        return DB::select("SELECT id, label FROM ter_provinces WHERE deleted_at IS NULL");
    }

    public static function softDelete(int $id)
    {
        return DB::statement("UPDATE ter_provinces SET deleted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function definitiveDelete(int $id)
    {
        return DB::statement("DELETE FROM ter_provinces WHERE deleted_at IS NULL AND id = ?", [$id]);
    }

    public static function insert(array $insertData)
    {
        return (new SDB)->insert('ter_provinces', $insertData);
    }

    public static function modify($id, array $insertData)
    {
        return (new SDB)->modify('ter_provinces', $id, $insertData);
    }

    public static function getById(int $id): ?stdClass
    {
        return DB::select("SELECT id, label FROM ter_provinces WHERE id = ? LIMIT 1", [$id])[0];
    }
}
