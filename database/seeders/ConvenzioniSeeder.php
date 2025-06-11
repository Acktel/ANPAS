<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConvenzioniSeeder extends Seeder
{
    public function run()
    {
        DB::table('convenzioni')->insert([
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'AZIENDA 0 MSBH24 TO09-2C',
                'lettera_identificativa'  => 'A',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'ASL TO4 LOTTO 1 ATS (TRASP EMERG)',
                'lettera_identificativa'  => 'B',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'ASL TO4 LOTTO 2',
                'lettera_identificativa'  => 'C',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'ASL TO4 LOTTO 5 ATS',
                'lettera_identificativa'  => 'D',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'ASL TO4 LOTTO 1 ATS (TRASP PROG)',
                'lettera_identificativa'  => 'E',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'ASL CITTÀ DI TORINO LOTTO 6',
                'lettera_identificativa'  => 'F',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'ASL CITTÀ DI TORINO LOTTO 7 ATS',
                'lettera_identificativa'  => 'G',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'idAssociazione'          => 5,
                'idAnno'                  => 2024,
                'Convenzione'             => 'ALTRO',
                'lettera_identificativa'  => 'H',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
        ]);
    }
}
