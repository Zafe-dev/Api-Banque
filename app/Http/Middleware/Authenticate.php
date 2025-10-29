<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : null; // Pas de redirection pour les APIs
    }

    /**
     * Handle an incoming request.
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

        // Essayer les guards API personnalisés
        if (Auth::guard('api')->check()) {
            return Auth::shouldUse('api');
        }

        if (Auth::guard('api-admin')->check()) {
            return Auth::shouldUse('api-admin');
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Handle unauthenticated request.
     */
    protected function unauthenticated($request, array $guards)
    {
        if ($request->expectsJson()) {
            abort(response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Token d\'authentification manquant ou invalide'
                ]
            ], 401));
        }

        parent::unauthenticated($request, $guards);
    }
}
