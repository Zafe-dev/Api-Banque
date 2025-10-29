<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentification requise'
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Vérifier le rôle de l'utilisateur
        $userRole = $this->getUserRole($user);

        if (!$this->hasRole($userRole, $role)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => 'Permissions insuffisantes pour cette action',
                    'details' => [
                        'required_role' => $role,
                        'user_role' => $userRole
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Déterminer le rôle de l'utilisateur
     */
    private function getUserRole($user): string
    {
        // Vérifier d'abord le champ role dans la table users
        if ($user instanceof \App\Models\User && $user->role === 'admin') {
            return 'admin';
        }

        // Puis vérifier le type de modèle
        if ($user instanceof \App\Models\Client) {
            return 'client';
        }

        if ($user instanceof \App\Models\Admin) {
            return 'admin';
        }

        // Vérifier si c'est un User avec un rôle admin
        if ($user instanceof \App\Models\User && isset($user->role) && $user->role === 'admin') {
            return 'admin';
        }

        // Par défaut
        return 'client';
    }

    /**
     * Vérifier si l'utilisateur a le rôle requis
     */
    private function hasRole(string $userRole, string $requiredRole): bool
    {
        $roles = explode('|', $requiredRole);

        return in_array($userRole, $roles);
    }
}
