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

        // Configuration des scopes OAuth2 détaillés
        Passport::tokensCan([
            // Scopes pour les comptes
            'comptes.view' => 'Voir la liste des comptes',
            'comptes.view.own' => 'Voir uniquement ses propres comptes',
            'comptes.view.all' => 'Voir tous les comptes (Admin seulement)',
            'comptes.create' => 'Créer de nouveaux comptes (Admin seulement)',
            'comptes.update' => 'Modifier les comptes (Admin seulement)',
            'comptes.delete' => 'Supprimer les comptes (Admin seulement)',
            'comptes.block' => 'Bloquer/Débloquer les comptes (Admin seulement)',

            // Scopes pour les clients
            'clients.view' => 'Voir les informations des clients',
            'clients.view.own' => 'Voir ses propres informations',
            'clients.view.all' => 'Voir tous les clients (Admin seulement)',
            'clients.create' => 'Créer de nouveaux clients (Admin seulement)',
            'clients.update' => 'Modifier les clients (Admin seulement)',

            // Scopes d'administration
            'admin.full' => 'Accès complet administrateur',

            // Scopes de compatibilité
            'admin' => 'Accès administrateur complet',
            'client' => 'Accès client limité',
        ]);

        // Scopes par défaut selon le type d'utilisateur
        Passport::setDefaultScope([
            'comptes.view.own',
            'clients.view.own'
        ]);
    }
}
