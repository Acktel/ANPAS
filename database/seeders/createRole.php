<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class createRole extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => 'Admin', 'description' => "Il livello più alto di autorizzazione. Gli amministratori possono vedere e gestire i dati di tutte le associazioni e le tabelle di servizio."]);
        Role::create(['name' => 'Supervisor', 'description' => "I Supervisor  possono vedere e gestire i dati di tutte le associazioni ma non le tabelle di servizio."]);
        Role::create(['name' => 'AdminUser', 'description' => "L'AdminUser è come uno User delle associzioni che però può creare anche gli User."]);
        Role::create(['name' => 'User', 'description' => "Sono gli utenti legati alle associazioni."]);
    }
}
