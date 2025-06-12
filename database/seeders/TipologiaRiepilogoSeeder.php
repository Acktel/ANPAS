<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TipologiaRiepilogoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('tipologia_riepilogo')->insert([
            ['descrizione' => 'Riepilogo costi', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Automezzi', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Attrezzatura Sanitaria', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Telecomunicazioni', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Costi gestione struttura', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Costo del personale', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Materiale sanitario di consumo', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Costi amministrativi', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Quote di ammortamento', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Beni Strumentali inferiori a 516,00 euro', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Altri costi', 'created_at' => $now, 'updated_at' => $now],
            ['descrizione' => 'Totale generale', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
