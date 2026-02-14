<?php

namespace App\Http\Middleware;

use App\Models\DoctorApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthenticateDoctor
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            Log::info('API doctor auth failed: no bearer token', ['url' => $request->fullUrl()]);
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $token = DoctorApiToken::findForToken($bearer);

        if (!$token || !$token->doctor) {
            Log::info('API doctor auth failed: invalid or expired token', ['url' => $request->fullUrl()]);
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $token->update(['LastUsedAt' => now()]);

        $request->setUserResolver(fn () => $token->doctor);
        $request->attributes->set('DoctorToken', $token);

        return $next($request);
    }
}
