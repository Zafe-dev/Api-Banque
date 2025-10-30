<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwaggerDocsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'API Banque - Bienvenue',
        'version' => '1.0.0',
        'documentation' => url('/api/documentation'),
        'endpoints' => [
            'health' => url('/api/v1/health'),
            'login' => url('/api/v1/auth/login'),
            'register' => url('/api/v1/auth/register'),
            'comptes' => url('/api/v1/comptes'),
        ]
    ]);
});

// Route factice pour éviter l'erreur "Route [login] not defined"
Route::get('/login', function () {
    return response()->json(['message' => 'Login endpoint not available for API'], 404);
})->name('login');

/*
|--------------------------------------------------------------------------
| Routes Swagger / L5-Swagger
|--------------------------------------------------------------------------
|
| Les routes suivantes sont AUTOMATIQUEMENT générées par L5-Swagger :
|
| GET  /docs                  -> Interface Swagger UI
| GET  /docs/api-docs.json    -> Fichier JSON OpenAPI
| GET  /docs/asset/*          -> Assets Swagger UI (CSS, JS, etc.)
|
| ⚠️ NE PAS définir manuellement ces routes ici !
| Elles sont gérées par le package L5-Swagger via le service provider.
|
| Configuration : voir config/l5-swagger.php
*/

// Route pour l'interface Swagger UI
Route::get('/docs', [SwaggerDocsController::class, 'redirectToSwagger'])->name('l5-swagger.default.docs');

// Route pour le fichier JSON directement
Route::get('/docs/api-docs.json', [SwaggerDocsController::class, 'getJson'])->name('l5-swagger.default.docs_json');

/*
|--------------------------------------------------------------------------
| Routes de Debug et Maintenance Swagger (Non-production uniquement)
|--------------------------------------------------------------------------
*/

// Route de debug - Affiche les informations de configuration Swagger
// URL: /swagger/debug
// Accessible uniquement en développement
if (!app()->environment('production')) {
    Route::get('/swagger/debug', [SwaggerDocsController::class, 'debug'])
        ->name('swagger.debug');
}

// Route pour forcer la génération de la documentation
// URL: /swagger/generate
// Utile après le déploiement ou en cas de problème
Route::get('/swagger/generate', [SwaggerDocsController::class, 'generate'])
    ->name('swagger.generate');

// Route de vérification rapide (health check)
Route::get('/swagger/health', function () {
    $path = storage_path('api-docs/api-docs.json');
    $exists = file_exists($path);

    return response()->json([
        'status' => $exists ? 'ok' : 'error',
        'swagger_json_exists' => $exists,
        'swagger_ui_url' => url('/api/documentation'),
        'json_url' => url('/docs/api-docs.json'),
        'timestamp' => now()->toIso8601String(),
    ], $exists ? 200 : 503);
})->name('swagger.health');

// Route pour gérer les requêtes avec paramètres de requête vers /docs
Route::get('/docs', function (Illuminate\Http\Request $request) {
    // Si c'est une requête avec paramètre api-docs.json, rediriger vers la vraie route
    if ($request->has('api-docs.json')) {
        return redirect('/docs/api-docs.json');
    }

    // Sinon, rediriger vers l'interface Swagger UI
    return redirect('/api/documentation');
})->name('l5-swagger.default.docs');

/*
|--------------------------------------------------------------------------
| Routes personnalisées de l'application
|--------------------------------------------------------------------------
*/

// Exemple : Page d'accueil de l'API
Route::get('/api', function () {
    return response()->json([
        'name' => 'API Banque',
        'version' => '1.0.0',
        'description' => 'API RESTful pour la gestion des comptes bancaires',
        'documentation' => url('/api/documentation'),
        'openapi_json' => url('/docs/api-docs.json'),
        'environment' => app()->environment(),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('api.index');

