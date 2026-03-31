<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, PermissionCatalog::ROLES_VIEW);

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->serializeRole($role));

        return response()->json(['roles' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, PermissionCatalog::ROLES_CREATE);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('naqaa.roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('naqaa.permissions', 'name')],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (array_key_exists('permissions', $validated)) {
            $this->authorizePermission($request, PermissionCatalog::ROLES_ASSIGN_PERMISSIONS);
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        $role->load('permissions');

        return response()->json(['role' => $this->serializeRole($role)], 201);
    }

    public function update(int $roleId, Request $request): JsonResponse
    {
        $this->authorizePermission($request, PermissionCatalog::ROLES_UPDATE);

        $role = Role::query()->with('permissions')->findOrFail($roleId);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('naqaa.roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('naqaa.permissions', 'name')],
        ]);

        if (array_key_exists('name', $validated)) {
            $role->name = $validated['name'];
            $role->save();
        }

        if (array_key_exists('permissions', $validated)) {
            $this->authorizePermission($request, PermissionCatalog::ROLES_ASSIGN_PERMISSIONS);
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        $role->load('permissions');

        return response()->json(['role' => $this->serializeRole($role)]);
    }

    public function destroy(int $roleId, Request $request): JsonResponse
    {
        $this->authorizePermission($request, PermissionCatalog::ROLES_DELETE);

        $role = Role::query()->findOrFail($roleId);
        if ($role->name === PermissionCatalog::ROLE_ADMIN) {
            return response()->json(['message' => 'Admin role cannot be deleted'], 422);
        }

        $assignedUsersCount = DB::connection('naqaa')
            ->table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->id)
            ->where('model_type', \App\Models\User::class)
            ->count();

        if ($assignedUsersCount > 0) {
            return response()->json(['message' => 'Cannot delete a role assigned to users'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if (!$request->user()?->can($permission)) {
            abort(403, 'Forbidden');
        }
    }

    private function serializeRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'is_protected' => $role->name === PermissionCatalog::ROLE_ADMIN,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ];
    }
}

