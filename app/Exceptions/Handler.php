<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Override the unauthenticated method to prevent redirect to login route
        $this->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Token d\'authentification manquant ou invalide'
                    ]
                ], 401);
            }

            // For non-JSON requests, return JSON to avoid redirect
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentification requise'
                ]
            ], 401);
        });

        // Gérer les erreurs de route non trouvée pour les APIs
        $this->renderable(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, $request) {
            // Vérifier si c'est une route helper qui n'existe pas (comme 'login')
            if (str_contains($e->getMessage(), 'Route [login] not defined')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'UNAUTHENTICATED',
                            'message' => 'Token d\'authentification manquant ou invalide'
                        ]
                    ], 401);
                }
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ROUTE_NOT_FOUND',
                        'message' => 'Route non trouvée'
                    ]
                ], 404);
            }
        });

        // Gérer les erreurs générales pour les APIs
        $this->renderable(function (Throwable $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INTERNAL_ERROR',
                        'message' => 'Une erreur interne s\'est produite'
                    ]
                ], 500);
            }
        });
    }
}
