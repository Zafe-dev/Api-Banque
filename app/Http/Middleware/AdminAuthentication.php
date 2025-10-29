<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!$token = $request->bearerToken()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Token manquant'
                    ]
                ], 401);
            }

            if (!Auth::guard('api-admin')->user()) {
                Auth::shouldUse('api-admin');
                $user = Auth::user();
            } else {
                $user = Auth::guard('api-admin')->user();
            }
            
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ACCESS_DENIED',
                        'message' => 'Type d\'utilisateur non autorisé'
                    ]
                ], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_ERROR',
                    'message' => 'Erreur d\'authentification'
                ]
            ], 401);
        }
    }
}