<?php

namespace App\Http\Middleware;

use App\Models\DoctorApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticateDoctor
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $token = DoctorApiToken::findForToken($bearer);

        if (!$token || !$token->doctor) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $token->update(['LastUsedAt' => now()]);

        $request->setUserResolver(fn () => $token->doctor);
        $request->attributes->set('DoctorToken', $token);

        return $next($request);
    }
}
