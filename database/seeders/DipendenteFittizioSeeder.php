<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DipendenteFittizioSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $anno = $now->year;

        $fittizi = [
            [
                'idDipendente' => 999999,
                'nome' => 'TOTALE',
                'cognome' => 'VOLONTARI',
                'livello' => 'D1',
            ],
            [
                'idDipendente' => 999998,
                'nome' => 'TOTALE',
                'cognome' => 'SERVIZIO CIVILE',
                'livello' => 'D2',
            ],
        ];

        foreach ($fittizi as $f) {
            // 1. Inserimento o aggiornamento del dipendente
            DB::table('dipendenti')->updateOrInsert(
                ['idDipendente' => $f['idDipendente']],
                [
                    'idAssociazione'     => 1,
                    'idAnno'             => $anno,
                    'DipendenteNome'     => $f['nome'],
                    'DipendenteCognome'  => $f['cognome'],
                    'ContrattoApplicato' => 'ALTRO',
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]
            );

            // 2. Recupera idLivelloMansione
            $idLivello = DB::table('livello_mansione')->where('nome', $f['livello'])->value('id');

            // 3. Inserisce o aggiorna nella tabella pivot
            if ($idLivello) {
                DB::table('dipendenti_livelli_mansione')->updateOrInsert(
                    [
                        'idDipendente' => $f['idDipendente'],
                        'idLivelloMansione' => $idLivello,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
