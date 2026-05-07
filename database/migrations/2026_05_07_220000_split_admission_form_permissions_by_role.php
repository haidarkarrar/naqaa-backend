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

        $newPermissions = [
            PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ASSIGNED,
            PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ALL,
            PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ASSIGNED,
            PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ALL,
        ];

        foreach ($newPermissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::query()->where('name', PermissionCatalog::ROLE_ADMIN)->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($newPermissions);
        }

        $doctorRole = Role::query()->where('name', PermissionCatalog::ROLE_DOCTOR)->first();
        if ($doctorRole) {
            $doctorRole->givePermissionTo(PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ASSIGNED);
            if ($doctorRole->hasPermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ASSIGNED)) {
                $doctorRole->revokePermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ASSIGNED);
            }
            if ($doctorRole->hasPermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ALL)) {
                $doctorRole->revokePermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ALL);
            }
        }

        $nurseRole = Role::query()->where('name', PermissionCatalog::ROLE_NURSE)->first();
        if ($nurseRole) {
            $nurseRole->givePermissionTo(PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ALL);
            if ($nurseRole->hasPermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ASSIGNED)) {
                $nurseRole->revokePermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ASSIGNED);
            }
            if ($nurseRole->hasPermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ALL)) {
                $nurseRole->revokePermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ALL);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $doctorRole = Role::query()->where('name', PermissionCatalog::ROLE_DOCTOR)->first();
        if ($doctorRole) {
            if ($doctorRole->hasPermissionTo(PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ASSIGNED)) {
                $doctorRole->revokePermissionTo(PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ASSIGNED);
            }
            $doctorRole->givePermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ASSIGNED);
        }

        $nurseRole = Role::query()->where('name', PermissionCatalog::ROLE_NURSE)->first();
        if ($nurseRole) {
            if ($nurseRole->hasPermissionTo(PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ALL)) {
                $nurseRole->revokePermissionTo(PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ALL);
            }
            $nurseRole->givePermissionTo(PermissionCatalog::ADMISSIONS_FORM_EDIT_ALL);
        }

        $adminRole = Role::query()->where('name', PermissionCatalog::ROLE_ADMIN)->first();
        if ($adminRole) {
            foreach ([
                PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ASSIGNED,
                PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ALL,
                PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ASSIGNED,
                PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ALL,
            ] as $permissionName) {
                if ($adminRole->hasPermissionTo($permissionName)) {
                    $adminRole->revokePermissionTo($permissionName);
                }
            }
        }

        Permission::query()->whereIn('name', [
            PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ASSIGNED,
            PermissionCatalog::ADMISSIONS_FORM_VITALS_EDIT_ALL,
            PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ASSIGNED,
            PermissionCatalog::ADMISSIONS_FORM_CLINICAL_EDIT_ALL,
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
