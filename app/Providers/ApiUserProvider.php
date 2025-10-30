<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Client;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class ApiUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        Log::info('Retrieving user by ID', ['id' => $identifier, 'type' => gettype($identifier)]);

        // Convertir l'identifiant en string si c'est un UUID
        $identifier = (string) $identifier;

        // Déterminer si c'est un UUID ou un entier
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier);

        if ($isUuid) {
            // C'est un UUID, chercher dans les clients
            Log::info('Looking for client with UUID', ['id' => $identifier]);
            $client = Client::find($identifier);
            if ($client) {
                Log::info('Client found by UUID', ['id' => $identifier]);
                $client->role = 'client';
                return $client;
            }
        } else {
            // C'est un entier, chercher dans les admins
            Log::info('Looking for admin with integer ID', ['id' => $identifier]);
            $user = Admin::find($identifier);
            if ($user) {
                Log::info('Admin found by integer ID', ['id' => $identifier]);
                $user->role = 'admin';
                return $user;
            }
        }

        Log::warning('No user found by ID', ['id' => $identifier, 'is_uuid' => $isUuid]);
        return null;
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
            Log::warning('Empty credentials or missing email');
            return null;
        }

        Log::info('Retrieving user by credentials', ['email' => $credentials['email']]);

        // Essayer d'abord de trouver dans les admins (table users)
        $user = Admin::where('email', $credentials['email'])->first();
        if ($user) {
            Log::info('Admin trouvé', [
                'email' => $credentials['email'], 
                'id' => $user->id,
                'class' => get_class($user)
            ]);
            $user->role = 'admin'; // Explicitement définir le rôle
            return $user;
        }

        // Sinon chercher dans les clients (table clients)
        $client = Client::where('email', $credentials['email'])->first();
        if ($client) {
            Log::info('Client trouvé', [
                'email' => $credentials['email'], 
                'id' => $client->id,
                'class' => get_class($client)
            ]);
            $client->role = 'client'; // Explicitement définir le rôle
            return $client;
        }

        Log::warning('Utilisateur non trouvé', ['email' => $credentials['email']]);
        return null;
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
