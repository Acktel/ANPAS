<?php


namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;



class createPermission extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();


        /******************* Permissions *******************/
        Permission::create(['name' => 'view_association']);
        Permission::create(['name' => 'add_association']);
        Permission::create(['name' => 'edit_association']);
        Permission::create(['name' => 'delete_association']);



        /******************* Roles *******************/
        $admin = Role::findByName('Admin');
        $admin->givePermissionTo('view_association');
        $admin->givePermissionTo('add_association');
        $admin->givePermissionTo('edit_association');
        $admin->givePermissionTo('delete_association');
    }
}
