<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WelcomeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Route Sanctum par défaut (si utilisée)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes versionnées v1
Route::prefix('v1')->group(function () {

    // ============================================
    // ROUTES PUBLIQUES (pas d'authentification)
    // ============================================

    // Diagnostic système
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

        try {
            DB::connection()->getPdo();
            $health['database'] = 'connected ✓';
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

        return response()->json($health);
    });

    // Route de bienvenue
    Route::get('/welcome', [WelcomeController::class, 'welcome']);

    // Auth : login & register
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);

    // Route de test d'authentification
    Route::get('/auth/test', function () {
        $user = auth()->user();
        return response()->json([
            'authenticated' => !is_null($user),
            'user' => $user ? [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role ?? 'no_role',
                'type' => get_class($user)
            ] : null
        ]);
    })->middleware('auth:api');

    // ============================================
    // ROUTES PROTÉGÉES (authentification requise)
    // ============================================

    Route::middleware(['auth:api'])->group(function () {

        // Auth protégée
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Comptes protégés
        Route::prefix('comptes')->group(function () {
            Route::get('/', [CompteController::class, 'index']);       // liste tous les comptes
            Route::post('/', [CompteController::class, 'store']);       // créer un compte (admin seulement)
            Route::get('/{compte}', [CompteController::class, 'show']); // détail compte
            Route::patch('/{compte}', [CompteController::class, 'update']);
            Route::delete('/{compte}', [CompteController::class, 'destroy']);
            Route::post('/{compte}/bloquer', [CompteController::class, 'bloquer']);
            Route::post('/{compte}/debloquer', [CompteController::class, 'debloquer']);
        });

    });

});
