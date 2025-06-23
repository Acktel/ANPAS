<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssociazioniAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Recupera tutte le associazioni
        $associazioni = DB::table('associazioni')->get();

        // Recupera o crea il ruolo 'admin_associazione'
        $role = Role::firstOrCreate(['name' => 'AdminUser']);

        foreach ($associazioni as $assoc) {
            if( $assoc->IdAssociazione == 1) {
                continue; 
            }
            $email = 'admin_' . Str::slug($assoc->Associazione, '_') . '@associazione.test';
            $username = 'admin_' . Str::slug($assoc->Associazione, '_');

            // Verifica se esiste già un utente con questa email
            $existingUser = DB::table('users')->where('email', $email)->first();

            if ($existingUser) {
                DB::table('users')
                    ->where('id', $existingUser->id)
                    ->update([
                        'firstname' => 'Admin',
                        'lastname' => $assoc->Associazione,
                        'username' => $username,
                        'password' => Hash::make('admin123'), // fallback password
                        'role_id' => $role->id,
                        'active' => true,
                        'idAssociazione' => $assoc->IdAssociazione,
                        'updated_at' => now(),
                    ]);
                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'firstname' => 'Admin',
                    'lastname' => $assoc->Associazione,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make('admin123'),
                    'role_id' => $role->id,
                    'active' => true,
                    'idAssociazione' => $assoc->IdAssociazione,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Assegna ruolo Spatie se non già assegnato
            $userModel = User::find($userId);
            if ($userModel && ! $userModel->hasRole($role->name)) {
                $userModel->assignRole($role);
            }
        }
    }
}
