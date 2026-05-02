<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Filament Shield page permissions for admin profile pages (non–super-admin roles).
 */
class AdminPanelPagePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = [
            'View:AdminProfilePage',
            'View:EditAdminProfilePage',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');
        $role = Role::firstOrCreate(['name' => $superAdminName, 'guard_name' => 'web']);

        foreach ($names as $name) {
            $role->givePermissionTo($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
