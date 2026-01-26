<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RefreshTokenRequest;
use App\Models\Doctor;
use App\Models\DoctorRefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $doctor = Doctor::where('Username', $request->username)->first();

        $password = $request->password;
        $validPassword = $doctor && (Hash::check($password, $doctor->Password) || $doctor->Password === $password);

        if (!$doctor || !$validPassword) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $plain = Str::random(60);

        $doctor->tokens()->create([
            'Name' => 'mobile',
            'Token' => $plain,
            'ExpiresAt' => now()->addMinutes(5),
        ]);

        $deviceId = Str::uuid()->toString();
        $refreshPlain = bin2hex(random_bytes(64));

        $doctor->refreshTokens()->create([
            'DeviceId' => $deviceId,
            'TokenHash' => $refreshPlain,
            'ExpiresAt' => now()->addDays(90),
            'UserAgent' => $request->userAgent(),
            'IpAddress' => $request->ip(),
            'LastUsedAt' => now(),
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        return response()->json([
            'Token' => $plain,
            'refreshToken' => $refreshPlain,
            'deviceId' => $deviceId,
            'doctor' => [
                'id' => $doctor->Id,
                'Email' => $doctor->Email,
                'FullName' => $doctor->FullName ?? trim("{$doctor->FirstName} {$doctor->LastName}"),
                'SpecialtyId' => $doctor->SpecialtyId,
            ],
        ]);
    }
    
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $deviceId = $request->deviceId;
        $refreshTokenValue = $request->refreshToken;
        $hashed = hash('sha256', $refreshTokenValue);

        DB::beginTransaction();

        try {
            $refreshToken = DoctorRefreshToken::where('TokenHash', $hashed)
                ->where('DeviceId', $deviceId)
                ->lockForUpdate()
                ->first();

            if (!$refreshToken) {
                DB::rollBack();

                return new JsonResponse(['message' => 'Unauthenticated.'], 401);
            }

            if ($refreshToken->RevokedAt) {
                $refreshToken->doctor?->refreshTokens()
                    ->whereNull('RevokedAt')
                    ->update([
                        'RevokedAt' => now(),
                        'UpdatedAt' => now(),
                    ]);

                DB::rollBack();

                return new JsonResponse(['message' => 'Unauthenticated.'], 401);
            }

            $refreshToken->update([
                'RevokedAt' => now(),
                'UpdatedAt' => now(),
                'LastUsedAt' => now(),
            ]);

            $doctor = $refreshToken->doctor;

            if (!$doctor) {
                DB::rollBack();

                return new JsonResponse(['message' => 'Unauthenticated.'], 401);
            }

            $plain = Str::random(60);
            $doctor->tokens()->create([
                'Name' => 'mobile',
                'Token' => $plain,
                'ExpiresAt' => now()->addMinutes(5),
            ]);

            $newRefreshPlain = bin2hex(random_bytes(64));
            $doctor->refreshTokens()->create([
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

            return response()->json([
                'Token' => $plain,
                'refreshToken' => $newRefreshPlain,
                'deviceId' => $deviceId,
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('DoctorToken');
        $doctor = $request->user();
        $deviceId = $request->input('deviceId') ?? $request->header('X-Device-Id');

        if ($token) {
            $token->delete();
        }

        if ($doctor && $deviceId) {
            $doctor->refreshTokens()
                ->where('DeviceId', $deviceId)
                ->whereNull('RevokedAt')
                ->update([
                    'RevokedAt' => now(),
                    'UpdatedAt' => now(),
                ]);
        }

        return response()->json(['message' => 'Logged out']);
    }
}
