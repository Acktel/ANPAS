<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DipendentiSeeder extends Seeder
{
    public function run(): void
    {
        $idAssociazione = 5;
        $idAnno         = 2024;
        $now            = Carbon::now();

        $rows = [
            [
              'DipendenteCognome'    => 'PAPONELLI',
              'DipendenteNome'       => 'IVAN',
              'QualificaPrincipale'  => 'AUTISTA',
              'LivelloMansione'      => 'C4',
            ],
            [
              'DipendenteCognome'    => 'DEL CORE',
              'DipendenteNome'       => 'YURI',
              'QualificaPrincipale'  => 'AUTISTA',
              'LivelloMansione'      => 'C3',
            ],
            [
              'DipendenteCognome'    => 'CIRRINCIONE',
              'DipendenteNome'       => 'LORETO',
              'QualificaPrincipale'  => 'AUTISTA',
              'LivelloMansione'      => 'D1',
            ],
            [
              'DipendenteCognome'    => 'VERNIERI',
              'DipendenteNome'       => 'RITA',
              'QualificaPrincipale'  => 'IMPIEGATO AMM.VO',
              'LivelloMansione'      => 'D3',
            ],
            [
              'DipendenteCognome'    => 'NARETTO',
              'DipendenteNome'       => 'GIORGIA',
              'QualificaPrincipale'  => 'IMPIEGATO AMM.VO',
              'LivelloMansione'      => 'D3',
            ],
        ];

        foreach ($rows as $r) {
            // 1. Inserisci il dipendente
            $idDip = DB::table('dipendenti')->insertGetId([
                'idAssociazione'      => $idAssociazione,
                'idAnno'              => $idAnno,
                'DipendenteNome'      => $r['DipendenteNome'],
                'DipendenteCognome'   => $r['DipendenteCognome'],
                'ContrattoApplicato'  => 'CCNL ANPAS',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // 2. Collega la qualifica (solo per il nome)
            $idQualifica = DB::table('qualifiche')
                ->where('nome', $r['QualificaPrincipale'])
                ->value('id');

            if ($idQualifica) {
                DB::table('dipendenti_qualifiche')->insert([
                    'idDipendente' => $idDip,
                    'idQualifica' => $idQualifica,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 3. Collega il livello mansione
            $idLivello = DB::table('livello_mansione')
                ->where('nome', $r['LivelloMansione'])
                ->value('id');

            if ($idLivello) {
                DB::table('dipendenti_livelli_mansione')->insert([
                    'idDipendente' => $idDip,
                    'idLivelloMansione' => $idLivello,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
