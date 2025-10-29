<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Route Sanctum par défaut (peut être supprimée si non utilisée)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes API versionnées
Route::prefix('v1')->group(function () {
    
    // ============================================
    // ROUTES PUBLIQUES (sans authentification)
    // ============================================
    
    /**
     * Route de diagnostic système
     * Test: GET /api/v1/health
     */
    Route::get('/health', function () {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'app_key' => config('app.key') ? 'configured' : 'MISSING ⚠️',
            'database' => 'unknown',
            'passport_keys' => [
                'private' => file_exists(storage_path('oauth-private.key')),
                'public' => file_exists(storage_path('oauth-public.key'))
            ],
            'storage_writable' => is_writable(storage_path()),
            'counts' => []
        ];

        // Test connexion base de données
        try {
            DB::connection()->getPdo();
            $health['database'] = 'connected ✓';

            // Compteurs
            $health['counts'] = [
                'admins' => \App\Models\Admin::count(),
                'clients' => \App\Models\Client::count(),
                'comptes' => \App\Models\Compte::count(),
            ];
        } catch (\Exception $e) {
            $health['status'] = 'degraded';
            $health['database'] = 'disconnected ✗';
            $health['database_error'] = $e->getMessage();
        }

        // Vérifier les clés Passport
        if (!$health['passport_keys']['private'] || !$health['passport_keys']['public']) {
            $health['status'] = 'degraded';
            $health['passport_warning'] = 'OAuth keys missing! Run: php artisan passport:keys';
        }

        return response()->json($health);
    });

    /**
     * Route de bienvenue avec logging
     * Test: GET /api/v1/welcome
     */
    Route::get('/welcome', [App\Http\Controllers\WelcomeController::class, 'welcome']);

    /**
     * Route de connexion
     * Test: POST /api/v1/auth/login
     */
    Route::post('/auth/login', [App\Http\Controllers\AuthController::class, 'login']);

    /**
     * Route d'inscription
     * Test: POST /api/v1/auth/register
     */
    Route::post('/auth/register', [App\Http\Controllers\AuthController::class, 'register']);

    // Route de test publique pour debug
    Route::get('/comptes/test', function () {
        return response()->json(['message' => 'Route de test accessible']);
    });

    // ============================================
    // ROUTES PROTÉGÉES (authentification requise)
    // ============================================

    Route::middleware('auth:api')->group(function () {

        // Routes d'authentification
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
            Route::post('/refresh', [App\Http\Controllers\AuthController::class, 'refresh']);
            Route::get('/me', [App\Http\Controllers\AuthController::class, 'me']);
        });

        // Routes des comptes (avec rate limiting et contrôle d'accès)
        Route::middleware(['throttle:60,1'])->prefix('comptes')->group(function () {
            // Liste et détails
            Route::get('/', [App\Http\Controllers\CompteController::class, 'index']);
            Route::get('/{compte}', [App\Http\Controllers\CompteController::class, 'show']);

            // CRUD (admin only - à vérifier dans le controller)
            Route::post('/', [App\Http\Controllers\CompteController::class, 'store']);
            Route::patch('/{compte}', [App\Http\Controllers\CompteController::class, 'update']);
            Route::delete('/{compte}', [App\Http\Controllers\CompteController::class, 'destroy']);

            // Actions spéciales
            Route::post('/{compte}/bloquer', [App\Http\Controllers\CompteController::class, 'bloquer']);
            Route::post('/{compte}/debloquer', [App\Http\Controllers\CompteController::class, 'debloquer']);
        });
    });
});