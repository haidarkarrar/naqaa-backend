<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->firstOrCreate([
            'name' => PermissionCatalog::ADMISSIONS_PATIENT_EDIT,
            'guard_name' => 'web',
        ]);

        $nurseRole = Role::query()->where('name', PermissionCatalog::ROLE_NURSE)->first();
        if ($nurseRole) {
            $nurseRole->givePermissionTo(PermissionCatalog::ADMISSIONS_PATIENT_EDIT);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
