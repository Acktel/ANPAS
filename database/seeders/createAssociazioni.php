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
        $associazioni = [
            [
                'email'        => 'massimopisano10@gmail.com',
                'Associazione' => 'Associazione GOD',
                'password'     => Hash::make('god'),
                'provincia'    => 'AL',
                'citta'        => 'Casale Monferrato',
                'active'       => 1,
                'created_at'   => '2025-06-04 14:02:54',
                'updated_at'   => '2025-06-04 14:02:54',
            ],
            [
                'email'        => 'AdminAnpas@associazione.it',
                'Associazione' => 'Default Association',
                'password'     => null,
                'provincia'    => 'RM',
                'citta'        => 'Roma',
                'active'       => 1,
                'created_at'   => '2025-06-04 14:02:54',
                'updated_at'   => '2025-06-04 14:02:54',
            ],
          /*  [
                'email'        => 'dfsgdsfg@dfg.it',
                'Associazione' => 'TestAssociazione',
                'password'     => null,
                'provincia'    => 'AL',
                'citta'        => 'Casale Monferrato',
                'active'       => 1,
                'created_at'   => '2025-06-04 14:12:50',
                'updated_at'   => '2025-06-04 14:12:50',
            ],
            [
                'email'        => 'test@test.com',
                'Associazione' => 'P.A. Croce Verde Ovadese ODV',
                'password'     => null,
                'provincia'    => 'Alessandria',
                'citta'        => 'Ovada',
                'active'       => 1,
                'created_at'   => '2025-06-04 14:14:28',
                'updated_at'   => '2025-06-04 14:14:28',
            ],
            [
                'email'        => 'testvolpianese@gmail.com',
                'Associazione' => 'CB VOLPIANESE ODV',
                'password'     => null,
                'provincia'    => 'TO',
                'citta'        => 'Volpiano',
                'active'       => 1,
                'created_at'   => '2025-06-06 13:52:03',
                'updated_at'   => '2025-06-06 13:52:03',
            ],*/
        ];

        foreach ($associazioni as $assoc) {
            DB::table('associazioni')
              ->updateOrInsert(
                  ['email' => $assoc['email']],
                  $assoc
              );
        }
    }
}
