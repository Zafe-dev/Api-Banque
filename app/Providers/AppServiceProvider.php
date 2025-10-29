<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Laravel\Passport\Passport::ignoreRoutes();

        // Enregistrer le provider personnalisé
        \Illuminate\Support\Facades\Auth::provider('api_users', function ($app, array $config) {
            return new \App\Providers\ApiUserProvider();
        });

        // Charger les clés Passport depuis les variables d'environnement si elles existent
        if (env('PASSPORT_PRIVATE_KEY') && env('PASSPORT_PUBLIC_KEY')) {
            file_put_contents(storage_path('oauth-private.key'), env('PASSPORT_PRIVATE_KEY'));
            file_put_contents(storage_path('oauth-public.key'), env('PASSPORT_PUBLIC_KEY'));
            \Laravel\Passport\Passport::loadKeysFrom(storage_path());
        }



        // Force HTTPS in production
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
