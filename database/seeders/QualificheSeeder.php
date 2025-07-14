<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualificheSeeder extends Seeder
{
    public function run()
    {
        $qualifiche = [
            'AUTISTA',
            'SOCCORRITORE',
            'IMPIEGATO AMM.VO',
            'COORDINATORE TECNICO',
        ];

        foreach ($qualifiche as $nome) {
            DB::table('qualifiche')->updateOrInsert(
                ['nome' => $nome],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $livelli = ['C1', 'C2', 'C3', 'C4', 'B1', 'D1', 'D3'];

        foreach ($livelli as $livello) {
            DB::table('livello_mansione')->updateOrInsert(
                ['nome' => $livello],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
