<?php
// app/Models/Associazione.php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;

class Associazione implements AuthenticatableContract
{
    use Authenticatable;

    protected const TABLE = 'associazioni';

    public static function findByEmail(string $email): ?\stdClass
    {
        return DB::table(self::TABLE)
            ->where('email', $email)
            ->first();
    }

    public static function create(array $data): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'Associazione' => $data['Associazione'],
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
            'provincia'    => $data['provincia'],
            'città'        => $data['città'],
            'created_at'   => now(),
            'updated_at'   => now(),
        ], 'idAssociazione');
    }
}
