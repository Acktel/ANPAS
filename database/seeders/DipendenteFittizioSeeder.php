<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DipendenteFittizioSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('dipendenti')->updateOrInsert(
            ['idDipendente' => 999999],
            [
                'idAssociazione'     => 1, // cambia se serve
                'idAnno'             => now()->year,
                'DipendenteNome'     => 'TOTALE',
                'DipendenteCognome'  => 'VOLONTARI',
                'ContrattoApplicato' => 'ALTRO',
                'LivelloMansione'    => 'D1',       
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );

        DB::table('dipendenti')->updateOrInsert(
            ['idDipendente' => 999998],
            [
                'idAssociazione'     => 1, // cambia se serve
                'idAnno'             => now()->year,
                'DipendenteNome'     => 'TOTALE',
                'DipendenteCognome'  => 'SERVIZIO CIVILE',
                'ContrattoApplicato' => 'ALTRO',
                'LivelloMansione'    => 'D2',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );
    }
}
