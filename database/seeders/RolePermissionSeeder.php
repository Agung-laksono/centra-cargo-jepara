<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create basic permissions
        $permissions = [
            'manage-shipments',
            'manage-manifests',
            'manage-warehouses',
            'manage-users',
            'manage-settings'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign created permissions
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $roleSuperAdmin->givePermissionTo(Permission::all());

        $roleAdminGudang = Role::firstOrCreate(['name' => 'admin-gudang']);
        $roleAdminGudang->givePermissionTo(['manage-shipments', 'manage-manifests', 'manage-warehouses']);

        $roleKurir = Role::firstOrCreate(['name' => 'kurir']);
        // Kurir might only need to see specific manifests, so maybe no global permissions for now, 
        // or just a custom one later.
    }
}
