<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $token = $doctor->tokens()->create([
            'Name' => 'mobile',
            'Token' => $plain,
            'ExpiresAt' => now()->addHours(12),
        ]);

        return response()->json([
            'Token' => $plain,
            'doctor' => [
                'id' => $doctor->Id,
                'Email' => $doctor->Email,
                'FullName' => $doctor->FullName ?? trim("{$doctor->FirstName} {$doctor->LastName}"),
                'SpecialtyId' => $doctor->SpecialtyId,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('DoctorToken');

        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }
}
