<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Log;

class ToadUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        try {
            $data = session('toad_user');
            
            if (!$data) {
                Log::warning('Session toad_user non trouvée');
                return null;
            }

            $id = $data['id'] ?? $data['email'] ?? null;

            if (!$id) {
                Log::warning('ID utilisateur non trouvé dans les données de session', ['data' => $data]);
                return null;
            }

            if ($id == $identifier) {
                return new ToadUser($data);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'utilisateur: ' . $e->getMessage());
            return null;
        }
    }

    public function retrieveByToken($identifier, $token)
    {
        return null; // pas de remember token
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // no-op
    }

    public function retrieveByCredentials(array $credentials)
    {
        return null; // on ne valide pas ici (fait via l’API Toad)
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return false; // non utilisé
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // non utilisé (pas de password local)
    }
}