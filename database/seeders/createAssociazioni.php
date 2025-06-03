<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class createAssociazioni extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('associazioni')->updateOrInsert(
            ['email' => 'default@associazione.test'],
            [
                'Associazione' => 'Associazione GOD',
                'email'        => 'massimopisano10@gmail.com',
                'password'     => Hash::make('god'),  // se usi password
                'provincia'    => 'AL',
                'citta'        => 'Casale Monferrato',
                'active'       => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );
    }
}
