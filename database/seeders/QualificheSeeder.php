<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualificheSeeder extends Seeder
{
    public function run()
    {
        $qualifiche = [
            // Autisti (senza livello)
            ['nome' => 'AUTISTA',           'livello_mansione' => 'C1'],
            ['nome' => 'AUTISTA',           'livello_mansione' => 'C4'],
            ['nome' => 'AUTISTA',           'livello_mansione' => 'C3'],
            ['nome' => 'AUTISTA',           'livello_mansione' => 'C2'],
            ['nome' => 'AUTISTA',           'livello_mansione' => 'C1'],
            ['nome' => 'AUTISTA',           'livello_mansione' => 'B1'],
            ['nome' => 'AUTISTA',           'livello_mansione' => 'D1'],

            // Soccorritori
            ['nome' => 'SOCCORRITORE',           'livello_mansione' => 'C4'],
            ['nome' => 'SOCCORRITORE',           'livello_mansione' => 'C3'],
            ['nome' => 'SOCCORRITORE',           'livello_mansione' => 'C2'],
            ['nome' => 'SOCCORRITORE',           'livello_mansione' => 'C1'],
            ['nome' => 'SOCCORRITORE',           'livello_mansione' => 'B1'],
            ['nome' => 'SOCCORRITORE',           'livello_mansione' => 'D1'],

            // Amministrativi
            ['nome' => 'IMPIEGATO AMM.VO',       'livello_mansione' => 'D3'],

            // Coordinatori tecnici
            ['nome' => 'COORDINATORE TECNICO',   'livello_mansione' => 'D1'],
        ];

        // Inserisco a blocchi, evitando duplicati
        foreach ($qualifiche as $q) {
            DB::table('qualifiche')->updateOrInsert(
                ['nome' => $q['nome'], 'livello_mansione' => $q['livello_mansione']],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
