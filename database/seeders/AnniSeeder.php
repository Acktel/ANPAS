<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnniSeeder extends Seeder
{
    public function run()
    {
        // Supponendo che la colonna chiave si chiami idAnno e il valore "anno" contenga l’intero
        for ($y = 2000; $y <= 2025; $y++) {
            DB::table('anni')->updateOrInsert(
                ['idAnno' => $y],
                ['anno' => $y]
            );
        }
    }
}
