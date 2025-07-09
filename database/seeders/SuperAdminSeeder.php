<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;                      
use Spatie\Permission\Models\Role;
use App\Mail\SupervisorInvite;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Assicurati che il ruolo "SuperAdmin" esista (usiamo ancora Eloquent perché Role è Spatie)
        $role = Role::firstOrCreate(
            ['name' => 'SuperAdmin'],
            ['description' => 'GOD']
        );

        // 2) Verifica/crea l'associazione di default col Query Builder
        $assocRecord = DB::table('associazioni')
            ->where('email', 'AdminAnpas@associazione.it')
            ->first();

        if ($assocRecord) {
            $associazioneId = $assocRecord->IdAssociazione;
        } else {
            // Se non esiste, inseriscila
            $now = now();
            $associazioneId = DB::table('associazioni')->insertGetId([
                'Associazione' => 'Default Association',
                'email'        => 'AdminAnpas@associazione.it',
                'password'     => Hash::make('secret'), // se nella tua tabella associazioni c'è la colonna password
                'provincia'    => 'RM',
                'citta'        => 'Roma',
                'active'       => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // 3) Verifica/crea l'utente SuperAdmin
        $userRecord = DB::table('users')
            ->where('email', 'massimo.pisano@acktel.com')
            ->first();

        if ($userRecord) {
            // Se esiste, aggiorna i campi principali
            DB::table('users')
                ->where('id', $userRecord->id)
                ->update([
                    'firstname'       => 'Massimo',
                    'lastname'        => 'Pisano',
                    'username'        => 'massimo',
                    'password'        => Hash::make('Aktel'),
                    'role_id'         => $role->id,
                    'active'          => true,
                    'idAssociazione' => $associazioneId,
                    'updated_at'      => now(),
                ]);
            $userId = $userRecord->id;
        } else {
            // Altrimenti inseriscilo e prendi l'ID
            $now = now();
            $userId = DB::table('users')->insertGetId([
                'firstname'       => 'Massimo',
                'lastname'        => 'Pisano',
                'username'        => 'massimo',
                'email'           => 'massimo.pisano@acktel.com',
                'password'        => Hash::make('Aktel'),
                'role_id'         => $role->id,
                'active'          => true,
                'idAssociazione' => $associazioneId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // 4) Recupera l'istanza Eloquent di User per poter chiamare assignRole()
        $userModel = User::find($userId);
        if ($userModel && ! $userModel->hasRole($role->name)) {
            $userModel->assignRole($role);
        }
    }
}
