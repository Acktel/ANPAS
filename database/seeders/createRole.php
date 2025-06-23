<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CreateRole extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'Admin'      => "Il livello più alto di autorizzazione. Gli amministratori possono vedere e gestire i dati di tutte le associazioni e le tabelle di servizio.",
            'Supervisor' => "I Supervisor possono vedere e gestire i dati di tutte le associazioni ma non le tabelle di servizio.",
            'AdminUser'  => "L'AdminUser è come uno User delle associazioni che però può creare anche gli User.",
            'User'       => "Sono gli utenti legati alle associazioni.",
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }
    }
}
