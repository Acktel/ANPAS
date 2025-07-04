<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class ContrattoApplicato
{
    protected const TABLE = 'contratti_applicati';

    public static function getAll()
    {
        return DB::table(self::TABLE)->orderBy('nome')->get();
    }

    public static function getById(int $id)
    {
        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    public static function createContratto(array $data)
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
