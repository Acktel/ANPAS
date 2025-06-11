<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DipendentiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $idAssociazione = 5;
        $idAnno = 2024;
        $now = Carbon::now();

        $rows = [
            ['DipendenteCognome' => 'PAPONELLI',    'DipendenteNome' => 'IVAN',         'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C4'],
            ['DipendenteCognome' => 'DEL CORE',      'DipendenteNome' => 'YURI',         'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'ZOTA',          'DipendenteNome' => 'IONEL',        'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C4'],
            ['DipendenteCognome' => 'PESCETTO',      'DipendenteNome' => 'MATTEO',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C4'],
            ['DipendenteCognome' => 'PIETRONIRO',    'DipendenteNome' => 'LORENZO',      'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C4'],
            ['DipendenteCognome' => 'BALDINU',       'DipendenteNome' => 'ANDREA',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'GALLIZIOLI',    'DipendenteNome' => 'CHIARA',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'QUONDAMATTEO',  'DipendenteNome' => 'MARCO',        'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'COLASANTO',     'DipendenteNome' => 'SIMONE',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => "DELL'AQUILA",  'DipendenteNome' => 'GIUSEPPE',     'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => ''],
            ['DipendenteCognome' => 'TESAURO',       'DipendenteNome' => 'CARMINE',      'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'SAVINI',        'DipendenteNome' => 'MARCO',        'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'MARCATTO',      'DipendenteNome' => 'ARIANNA',      'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'LERA',          'DipendenteNome' => 'GIULIA',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C2'],
            ['DipendenteCognome' => 'BUOSO',         'DipendenteNome' => 'VALENTINO',    'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C2'],
            ['DipendenteCognome' => 'POSSETTO',      'DipendenteNome' => 'CHRISTIAN',    'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C2'],
            ['DipendenteCognome' => 'EMARELLI',      'DipendenteNome' => 'FEDERICA',     'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C2'],
            ['DipendenteCognome' => 'MENZIO',        'DipendenteNome' => 'ROBERTO',      'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'VURRO',         'DipendenteNome' => 'LORENZO',      'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'MIGLIACCIO',    'DipendenteNome' => 'MAURIZIO',     'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'B1'],
            ['DipendenteCognome' => 'INCANI',        'DipendenteNome' => 'MARIANGELA',   'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'BOLOGNINO',     'DipendenteNome' => 'ANDREA',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'SIMANI',        'DipendenteNome' => 'MARIO CORRADO','Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C3'],
            ['DipendenteCognome' => 'PALUMBO',       'DipendenteNome' => 'GIULIANA',     'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => "D'AMELIO",     'DipendenteNome' => 'MATTEO',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'B1'],
            ['DipendenteCognome' => 'CRESTA',        'DipendenteNome' => 'MARA',         'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'ACELLA',        'DipendenteNome' => 'ROBERTO',      'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'NAPOLITANO',    'DipendenteNome' => 'TOMMASO',      'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'BUZDUGA',       'DipendenteNome' => 'ADRIAN',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'C1'],
            ['DipendenteCognome' => 'CIRRINCIONE',    'DipendenteNome' => 'LORETO',       'Qualifica' => 'AUTISTA,SOCCORRITORE', 'ContrattoApplicato' => 'CCNL ANPAS', 'LivelloMansione' => 'D1'],
        ];

        foreach ($rows as &$row) {
            $row['idAssociazione'] = $idAssociazione;
            $row['idAnno'] = $idAnno;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        DB::table('dipendenti')->insert($rows);
    }
}
