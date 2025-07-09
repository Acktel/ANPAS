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
        $idAnno         = 2024;
        $now            = Carbon::now();

        // Definisco ogni dipendente con i campi base
        // 'QualificaPrincipale' è quella che userò per ricavare idQualifica (ad es. "AUTISTA" o "SOCCORRITORE")
        $rows = [
            [
              'DipendenteCognome'    => 'PAPONELLI',
              'DipendenteNome'       => 'IVAN',
              'QualificaPrincipale'  => 'AUTISTA',
              'ContrattoApplicato'   => 'CCNL ANPAS',
              'LivelloMansione'      => 'C4',
            ],
            [
              'DipendenteCognome'    => 'DEL CORE',
              'DipendenteNome'       => 'YURI',
              'QualificaPrincipale'  => 'AUTISTA',
              'ContrattoApplicato'   => 'CCNL ANPAS',
              'LivelloMansione'      => 'C3',
            ],
            // ... continua fino al 30 come avevi elencato ...
            [
              'DipendenteCognome'    => 'CIRRINCIONE',
              'DipendenteNome'       => 'LORETO',
              'QualificaPrincipale'  => 'AUTISTA',
              'ContrattoApplicato'   => 'CCNL ANPAS',
              'LivelloMansione'      => 'D1',
            ],
            // infine i due amministrativi
            [
              'DipendenteCognome'    => 'VERNIERI',
              'DipendenteNome'       => 'RITA',
              'QualificaPrincipale'  => 'IMPIEGATO AMM.VO',
              'ContrattoApplicato'   => 'CCNL ANPAS',
              'LivelloMansione'      => 'D3',
            ],
            [
              'DipendenteCognome'    => 'NARETTO',
              'DipendenteNome'       => 'GIORGIA',
              'QualificaPrincipale'  => 'IMPIEGATO AMM.VO',
              'ContrattoApplicato'   => 'CCNL ANPAS',
              'LivelloMansione'      => 'D3',
            ],
            // ... se vuoi aggiungere altri ruoli ...
        ];

        $inserts = [];

        foreach ($rows as $r) {
            // Trovo l'idQualifica nella tabella qualifiche
            $qual = DB::table('qualifiche')
                ->where('nome', $r['QualificaPrincipale'])
                ->where('livello_mansione', $r['LivelloMansione'])
                ->first();

            $idQual = $qual ? $qual->id : null;

            $inserts[] = [
                'idAssociazione'      => $idAssociazione,
                'idAnno'              => $idAnno,
                'idQualifica'         => $idQual,
                'DipendenteNome'      => $r['DipendenteNome'],
                'DipendenteCognome'   => $r['DipendenteCognome'],
                'ContrattoApplicato'  => $r['ContrattoApplicato'],
                'LivelloMansione'     => $r['LivelloMansione'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        // Inserisco in un’unica batch
        DB::table('dipendenti')->insert($inserts);
    }
}
