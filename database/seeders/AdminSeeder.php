<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crea il ruolo se non esiste
        $role = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Amministratore di associazione']
        );

        // 2. Associazione di default
        $assocRecord = DB::table('associazioni')
            ->where('email', 'AdminAnpas@associazione.it')
            ->first();

        if ($assocRecord) {
            $associazioneId = $assocRecord->IdAssociazione;
        } else {
            $now = now();
            $associazioneId = DB::table('associazioni')->insertGetId([
                'Associazione' => 'Anpas Admin',
                'email'        => 'AdminAnpas@associazione.it',
                'password'     => Hash::make('secret'),
                'provincia'    => 'RM',
                'citta'        => 'Roma',
                'active'       => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // 3. Crea o aggiorna l'utente Admin
        $userRecord = DB::table('users')
            ->where('email', 'anpasAdmin@anpas.it')
            ->first();

        if ($userRecord) {
            DB::table('users')->where('id', $userRecord->id)->update([
                'firstname'      => 'Alessandra',
                'lastname'       => 'D’Angela',
                'username'       => 'adminuser',
                'password'       => Hash::make('Admin'),
                'role_id'        => $role->id,
                'active'         => true,
                'idAssociazione' => $associazioneId,
                'updated_at'     => now(),
            ]);
            $userId = $userRecord->id;
        } else {
            $userId = DB::table('users')->insertGetId([
                'firstname'      => 'Alessandra',
                'lastname'       => 'D’Angela',
                'username'       => 'adminuser',
                'email'          => 'anpasAdmin@anpas.it',
                'password'       => Hash::make('Admin'),
                'role_id'        => $role->id,
                'active'         => true,
                'idAssociazione' => $associazioneId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        // 4. Assegna ruolo con Spatie se non già assegnato
        $userModel = User::find($userId);
        if ($userModel && ! $userModel->hasRole($role->name)) {
            $userModel->assignRole($role);
        }
    }
}
