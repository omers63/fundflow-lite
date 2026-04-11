<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ReconciliationPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = [
            'reconciliation_view',
            'reconciliation_run',
            'reconciliation_export',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');
        $role = Role::firstOrCreate(
            ['name' => $superAdminName, 'guard_name' => 'web'],
        );

        foreach ($names as $name) {
            $role->givePermissionTo($name);
        }

        $adminUser = User::query()->where('email', 'admin@fundflow.sa')->first();
        if ($adminUser !== null && !$adminUser->hasRole($superAdminName)) {
            $adminUser->assignRole($superAdminName);
        }
    }
}
