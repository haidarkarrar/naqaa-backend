<?php

namespace App\Http\Middleware;

use App\Models\UserApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthenticateApiUser
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            Log::info('API user auth failed: no bearer token', ['url' => $request->fullUrl()]);
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $token = UserApiToken::findForToken($bearer);

        if (!$token || !$token->user || !$token->user->is_active) {
            Log::info('API user auth failed: invalid or expired token', ['url' => $request->fullUrl()]);
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $token->update(['LastUsedAt' => now()]);

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('UserToken', $token);

        return $next($request);
    }
}

