<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualificheSeeder extends Seeder
{
    public function run()
    {
        // 🔴 Associa ID fissi → nome
        $qualifiche = [
            1 => 'AUTISTA SOCCORRITORE',
            2 => 'ADDETTO LOGISTICA',
            3 => 'ADDETTO PULIZIA',
            4 => 'ALTRO',
            5 => 'COORDINATORE AMMINISTRATIVO',
            6 => 'COORDINATORE TECNICO',
            7 => 'IMPIEGATO AMMINISTRATIVO',
        ];

        foreach ($qualifiche as $id => $nome) {
            DB::table('qualifiche')->updateOrInsert(
                ['id' => $id], // chiave primaria forzata
                ['nome' => $nome, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // 🔴 Id fissi anche per i livelli mansione (adatta gli ID come preferisci)
        $livelli = [
            1 => 'C1',
            2 => 'C2',
            3 => 'C3',
            4 => 'C4',
            5 => 'B1',
            6 => 'D1',
            7 => 'D3',
        ];

        foreach ($livelli as $id => $nome) {
            DB::table('livello_mansione')->updateOrInsert(
                ['id' => $id],
                ['nome' => $nome, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
