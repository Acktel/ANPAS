<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions with descriptions
        $permissions = [
            ['name' => 'manage all associations', 'description' => 'Permette di gestire i dati di tutte le associazioni'],
            ['name' => 'manage service tables', 'description' => 'Permette di gestire le tabelle di servizio'],
            ['name' => 'manage own association data', 'description' => 'Permette di gestire i dati della propria associazione'],
        ];

        // Create or update permissions
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['description' => $perm['description']]
            );
        }

        // Create roles with descriptions
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Gli Admin possono gestire tutte le associazioni e le tabelle di servizio']
        );
        $adminRole->syncPermissions(array_column($permissions, 'name'));

        $supervisorRole = Role::firstOrCreate(
            ['name' => 'Supervisor'],
            ['description' => 'I Supervisor possono gestire tutte le associazioni ma non le tabelle di servizio']
        );
        $supervisorRole->syncPermissions([
            'manage all associations',
            'manage own association data',
        ]);

        $userRole = Role::firstOrCreate(
            ['name' => 'User'],
            ['description' => 'Gli User possono gestire solo i dati della propria associazione']
        );
        $userRole->syncPermissions([
            'manage own association data',
        ]);
    }
}
