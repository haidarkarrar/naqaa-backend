<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RefreshTokenRequest;
use App\Models\User;
use App\Models\UserRefreshToken;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with('doctor')
            ->where('username', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $blockedReason = $this->loginBlockedReason($user);
        if ($blockedReason) {
            return response()->json(['message' => $blockedReason], 403);
        }

        $tokenPayload = $this->issueTokens($user, $request);

        return response()->json([
            'Token' => $tokenPayload['Token'],
            'refreshToken' => $tokenPayload['refreshToken'],
            'deviceId' => $tokenPayload['deviceId'],
            'user' => $this->serializeUser($user),
            'doctor' => $this->serializeDoctor($user),
        ]);
    }

    public function doctorLogin(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with('doctor')
            ->where('username', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($this->loginBlockedReason($user, legacyDoctorOnly: true)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $tokenPayload = $this->issueTokens($user, $request);
        $doctor = $this->serializeDoctor($user);

        return response()->json([
            'Token' => $tokenPayload['Token'],
            'refreshToken' => $tokenPayload['refreshToken'],
            'deviceId' => $tokenPayload['deviceId'],
            'doctor' => $doctor,
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $payload = $this->performRefresh($request);
        if ($payload === null) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'Token' => $payload['Token'],
            'refreshToken' => $payload['refreshToken'],
            'deviceId' => $payload['deviceId'],
            'user' => $this->serializeUser($payload['user']),
            'doctor' => $this->serializeDoctor($payload['user']),
        ]);
    }

    public function doctorRefresh(RefreshTokenRequest $request): JsonResponse
    {
        $payload = $this->performRefresh($request, legacyDoctorOnly: true);
        if ($payload === null) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'Token' => $payload['Token'],
            'refreshToken' => $payload['refreshToken'],
            'deviceId' => $payload['deviceId'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('UserToken');
        $user = $request->user();
        $deviceId = $request->input('deviceId') ?? $request->header('X-Device-Id');

        if ($token) {
            $token->delete();
        }

        if ($user && $deviceId) {
            $user->refreshTokens()
                ->where('DeviceId', $deviceId)
                ->whereNull('RevokedAt')
                ->update([
                    'RevokedAt' => now(),
                    'UpdatedAt' => now(),
                ]);
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function doctorLogout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->doctor_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->logout($request);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->loadMissing('doctor');

        return response()->json([
            'user' => $this->serializeUser($user),
            'doctor' => $this->serializeDoctor($user),
        ]);
    }

    private function issueTokens(User $user, Request $request): array
    {
        $plain = Str::random(60);

        $user->tokens()->create([
            'Name' => 'mobile',
            'Token' => $plain,
            'ExpiresAt' => now()->addMinutes(5),
        ]);

        $deviceId = Str::uuid()->toString();
        $refreshPlain = bin2hex(random_bytes(64));

        $user->refreshTokens()->create([
            'DeviceId' => $deviceId,
            'TokenHash' => $refreshPlain,
            'ExpiresAt' => now()->addDays(90),
            'UserAgent' => $request->userAgent(),
            'IpAddress' => $request->ip(),
            'LastUsedAt' => now(),
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        return [
            'Token' => $plain,
            'refreshToken' => $refreshPlain,
            'deviceId' => $deviceId,
        ];
    }

    private function performRefresh(RefreshTokenRequest $request, bool $legacyDoctorOnly = false): ?array
    {
        $deviceId = $request->deviceId;
        $hashed = hash('sha256', $request->refreshToken);

        DB::beginTransaction();

        try {
            $refreshToken = UserRefreshToken::where('TokenHash', $hashed)
                ->where('DeviceId', $deviceId)
                ->lockForUpdate()
                ->first();

            if (!$refreshToken) {
                DB::rollBack();
                return null;
            }

            if ($refreshToken->RevokedAt || ($refreshToken->ExpiresAt && $refreshToken->ExpiresAt->isPast())) {
                if ($refreshToken->UserId) {
                    UserRefreshToken::where('UserId', $refreshToken->UserId)
                        ->where('DeviceId', $deviceId)
                        ->whereNull('RevokedAt')
                        ->update([
                            'RevokedAt' => now(),
                            'UpdatedAt' => now(),
                        ]);
                }

                DB::commit();
                return null;
            }

            /** @var User|null $user */
            $user = $refreshToken->user()->with('doctor')->first();
            if (!$user || $this->loginBlockedReason($user, legacyDoctorOnly: $legacyDoctorOnly)) {
                DB::rollBack();
                return null;
            }

            $refreshToken->update([
                'RevokedAt' => now(),
                'UpdatedAt' => now(),
                'LastUsedAt' => now(),
            ]);

            $plain = Str::random(60);
            $user->tokens()->create([
                'Name' => 'mobile',
                'Token' => $plain,
                'ExpiresAt' => now()->addMinutes(5),
            ]);

            $newRefreshPlain = bin2hex(random_bytes(64));
            $user->refreshTokens()->create([
                'DeviceId' => $deviceId,
                'TokenHash' => $newRefreshPlain,
                'ExpiresAt' => now()->addDays(90),
                'UserAgent' => $request->userAgent(),
                'IpAddress' => $request->ip(),
                'LastUsedAt' => now(),
                'CreatedAt' => now(),
                'UpdatedAt' => now(),
            ]);

            DB::commit();

            return [
                'Token' => $plain,
                'refreshToken' => $newRefreshPlain,
                'deviceId' => $deviceId,
                'user' => $user,
            ];
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    private function serializeDoctor(User $user): ?array
    {
        $doctor = $user->doctor;

        if (!$doctor) {
            return null;
        }

        return [
            'id' => $doctor->Id,
            'Email' => $doctor->Email,
            'FullName' => $doctor->FullName ?? trim("{$doctor->FirstName} {$doctor->LastName}"),
            'SpecialtyId' => $doctor->SpecialtyId,
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name,
            'isActive' => (bool) $user->is_active,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }

    private function loginBlockedReason(User $user, bool $legacyDoctorOnly = false): ?string
    {
        if (!$user->is_active) {
            return 'Account is inactive.';
        }

        if ($user->hasRole(PermissionCatalog::ROLE_DOCTOR) && !$user->doctor_id) {
            return 'Doctor accounts must be linked to a doctor.';
        }

        if ($legacyDoctorOnly && (!$user->doctor_id || !$user->hasRole(PermissionCatalog::ROLE_DOCTOR))) {
            return 'Forbidden';
        }

        return null;
    }
}
