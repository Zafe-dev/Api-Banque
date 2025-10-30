<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;
use App\Models\Admin;
use App\Models\Client;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Définition des gates pour les rôles
        Gate::define('admin', function ($user) {
            return $user instanceof Admin && $user->isAdmin();
        });

        Gate::define('client', function ($user) {
            return $user instanceof Client;
        });

        // Configuration de Passport
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // Configuration des scopes OAuth2
        Passport::tokensCan([
            'admin' => 'Accès administrateur complet',
            'client' => 'Accès client limité',
        ]);

        Passport::setDefaultScope([
            'admin'
        ]);
    }
}
