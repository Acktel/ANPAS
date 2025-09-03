<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialeSanitarioSeeder extends Seeder
{
    public function run(): void
    {
        $dati = [
            ['sigla' => 'MSA', 'descrizione' => 'MEZZO SOCCORSO AVANZATO'],
            ['sigla' => 'MSAB', 'descrizione' => 'MEZZO SOCCORSO AVANZATO DI BASE'],
            ['sigla' => 'MSB', 'descrizione' => 'MEZZO SOCCORSO DI BASE'],
            ['sigla' => 'ASA', 'descrizione' => 'AUTOMEZZO SOCCORSO AVANZATO'],
        ];

        foreach ($dati as $row) {
            DB::table('materiale_sanitario')->insert([
                'sigla'       => $row['sigla'],
                'descrizione' => $row['descrizione'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
