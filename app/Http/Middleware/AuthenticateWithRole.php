<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        // Essayer d'abord le guard api (clients avec UUID)
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            Auth::setUser($user);
        } elseif (Auth::guard('api-admin')->check()) {
            $user = Auth::guard('api-admin')->user();
            Auth::setUser($user);
        } else {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_REQUIRED',
                    'message' => 'Authentification requise. Veuillez vous connecter pour accéder à cette ressource.'
                ]
            ], 401);
        }

        $user = Auth::user();

        // Si un rôle spécifique est requis, vérifier avec message explicite
        if ($role && ($user->role ?? null) !== $role) {
            $requiredRole = ucfirst($role);
            $userRole = ucfirst($user->role ?? 'inconnu');

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => "Permissions insuffisantes. Cette action nécessite le rôle '{$requiredRole}' mais vous avez le rôle '{$userRole}'."
                ]
            ], 403);
        }

        return $next($request);
    }
}