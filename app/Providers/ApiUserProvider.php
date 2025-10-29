<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Client;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;

class ApiUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        // Essayer d'abord de trouver dans les admins (table users)
        $user = Admin::find($identifier);
        if ($user) {
            return $user;
        }

        // Sinon chercher dans les clients (table clients)
        return Client::find($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // Pour les admins
        $user = Admin::where('id', $identifier)->where('remember_token', $token)->first();
        if ($user) {
            return $user;
        }

        // Pour les clients
        return Client::where('id', $identifier)->where('remember_token', $token)->first();
    }

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        if ($user instanceof Model) {
            $user->setRememberToken($token);
            $user->save();
        }
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array<string, mixed>  $credentials Les identifiants de connexion (email, password, etc.)
     * @return \Illuminate\Contracts\Auth\Authenticatable|null L'utilisateur trouvé ou null si non trouvé
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || !isset($credentials['email'])) {
            return null;
        }

        // Essayer d'abord de trouver dans les admins
        $user = Admin::where('email', $credentials['email'])->first();
        if ($user) {
            return $user;
        }

        // Sinon chercher dans les clients
        return Client::where('email', $credentials['email'])->first();
    }

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return isset($credentials['password']) && Hash::check($credentials['password'], $user->getAuthPassword());
    }

    /**
     * Rehash the user's password if required and supported.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Not implemented for this example
    }
}
