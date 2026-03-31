<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::query()->firstOrCreate([
            'name' => PermissionCatalog::ROLE_ADMIN,
            'guard_name' => 'web',
        ]);
        $adminRole->syncPermissions(PermissionCatalog::all());

        $doctorRole = Role::query()->firstOrCreate([
            'name' => PermissionCatalog::ROLE_DOCTOR,
            'guard_name' => 'web',
        ]);
        $doctorRole->syncPermissions(PermissionCatalog::doctorDefaults());

        $nurseRole = Role::query()->firstOrCreate([
            'name' => PermissionCatalog::ROLE_NURSE,
            'guard_name' => 'web',
        ]);
        $nurseRole->syncPermissions(PermissionCatalog::nurseDefaults());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

