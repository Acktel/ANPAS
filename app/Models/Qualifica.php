<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Qualifica
{
    protected const TABLE = 'qualifiche';

    public static function getAll()
    {
        return DB::table(self::TABLE)->orderBy('nome')->get();
    }

    public static function getById(int $id)
    {
        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    public static function createQualifica(array $data)
    {
        return DB::table(self::TABLE)->insert([
            'nome' => $data['nome'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function deleteById(int $id)
    {
        return DB::table(self::TABLE)->where('id', $id)->delete();
    }
}
