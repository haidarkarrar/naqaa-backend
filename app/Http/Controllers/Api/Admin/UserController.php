<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.view');

        $query = User::query()
            ->with(['doctor', 'roles', 'permissions'])
            ->when($request->query('q'), function ($builder, $value) {
                $search = trim((string) $value);
                if ($search === '') {
                    return $builder;
                }

                $like = '%' . $search . '%';
                return $builder->where(function ($inner) use ($like) {
                    $inner->where('username', 'like', $like)
                        ->orWhere('display_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($request->query('is_active') !== null, function ($builder) use ($request) {
                return $builder->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('id', 'desc');

        $users = $query->paginate(
            max(1, min(100, (int) $request->query('per_page', 20))),
            ['*'],
            'page',
            max(1, (int) $request->query('page', 1))
        );

        return response()->json([
            'users' => collect($users->items())->map(fn (User $user) => $this->serializeUser($user))->values(),
            'pagination' => [
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.create');

        $validated = $this->validateUserPayload($request, creating: true);
        $this->validateDoctorRoleLink($validated['roles'] ?? [], $validated['doctor_id'] ?? null);

        $user = User::query()->create([
            'username' => $validated['username'],
            'display_name' => $validated['display_name'],
            'email' => $validated['email'] ?? null,
            'password' => $validated['password'],
            'doctor_id' => $validated['doctor_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->syncRolePermissionsFromPayload($request, $user, $validated);

        $user->load(['doctor', 'roles', 'permissions']);

        return response()->json(['user' => $this->serializeUser($user)], 201);
    }

    public function show(int $userId, Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.view');

        $user = User::query()
            ->with(['doctor', 'roles', 'permissions'])
            ->findOrFail($userId);

        return response()->json(['user' => $this->serializeUser($user)]);
    }

    public function update(int $userId, Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.update');

        $user = User::query()->with(['roles', 'permissions'])->findOrFail($userId);

        $validated = $this->validateUserPayload($request, creating: false, currentUser: $user);
        $targetRoles = array_key_exists('roles', $validated) ? ($validated['roles'] ?? []) : $user->getRoleNames()->all();
        $targetDoctorId = array_key_exists('doctor_id', $validated) ? $validated['doctor_id'] : $user->doctor_id;

        $this->validateDoctorRoleLink($targetRoles, $targetDoctorId);

        $user->fill([
            'username' => $validated['username'] ?? $user->username,
            'display_name' => $validated['display_name'] ?? $user->display_name,
            'email' => array_key_exists('email', $validated) ? $validated['email'] : $user->email,
            'doctor_id' => $targetDoctorId,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : $user->is_active,
        ]);

        $user->save();

        if (!$user->is_active) {
            $this->revokeUserTokens($user);
        }

        $this->syncRolePermissionsFromPayload($request, $user, $validated);

        $user->load(['doctor', 'roles', 'permissions']);

        return response()->json(['user' => $this->serializeUser($user)]);
    }

    public function updatePassword(int $userId, Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.update');

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->findOrFail($userId);
        $user->password = Hash::make($validated['password']);
        $user->save();

        $this->revokeUserTokens($user);

        return response()->json(['message' => 'Password updated']);
    }

    public function updateActivation(int $userId, Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.activate');

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user = User::query()->with('roles')->findOrFail($userId);
        $this->validateDoctorRoleLink($user->getRoleNames()->all(), $user->doctor_id);

        $user->is_active = (bool) $validated['is_active'];
        $user->save();

        if (!$user->is_active) {
            $this->revokeUserTokens($user);
        }

        return response()->json([
            'message' => 'Activation updated',
            'is_active' => $user->is_active,
        ]);
    }

    private function validateUserPayload(Request $request, bool $creating, ?User $currentUser = null): array
    {
        $userId = $currentUser?->id;

        return $request->validate([
            'username' => [
                $creating ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('naqaa.users', 'username')->ignore($userId),
            ],
            'display_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('naqaa.users', 'email')->ignore($userId),
            ],
            'password' => $creating
                ? ['required', 'string', 'min:8']
                : ['prohibited'],
            'doctor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::unique('naqaa.users', 'doctor_id')->ignore($userId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('naqaa.roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('naqaa.permissions', 'name')],
        ]);
    }

    private function syncRolePermissionsFromPayload(Request $request, User $user, array $validated): void
    {
        if (array_key_exists('roles', $validated)) {
            $this->authorizePermission($request, 'users.assign_roles');
            $roles = Role::query()->whereIn('name', $validated['roles'] ?? [])->pluck('name')->all();
            $user->syncRoles($roles);
        }

        if (array_key_exists('permissions', $validated)) {
            $this->authorizePermission($request, 'users.assign_permissions');
            $permissions = Permission::query()->whereIn('name', $validated['permissions'] ?? [])->pluck('name')->all();
            $user->syncPermissions($permissions);
        }
    }

    private function validateDoctorRoleLink(array $roles, mixed $doctorId): void
    {
        if (in_array('doctor', $roles, true) && !$doctorId) {
            abort(422, 'Doctor role requires doctor link.');
        }

        if ($doctorId && !Doctor::query()->whereKey((int) $doctorId)->exists()) {
            abort(422, 'Linked doctor not found.');
        }
    }

    private function revokeUserTokens(User $user): void
    {
        $user->tokens()->delete();

        UserRefreshToken::query()
            ->where('UserId', $user->id)
            ->whereNull('RevokedAt')
            ->update([
                'RevokedAt' => now(),
                'UpdatedAt' => now(),
            ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'doctor_id' => $user->doctor_id,
            'doctor' => $user->doctor ? [
                'id' => $user->doctor->Id,
                'FullName' => $user->doctor->FullName ?? trim("{$user->doctor->FirstName} {$user->doctor->LastName}"),
                'Username' => $user->doctor->Username,
                'Email' => $user->doctor->Email,
                'SpecialtyId' => $user->doctor->SpecialtyId,
            ] : null,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getDirectPermissions()->pluck('name')->values()->all(),
            'effective_permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'created_at' => optional($user->created_at)->toDateTimeString(),
            'updated_at' => optional($user->updated_at)->toDateTimeString(),
        ];
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        if (!$request->user()?->can($permission)) {
            abort(403, 'Forbidden');
        }
    }
}
