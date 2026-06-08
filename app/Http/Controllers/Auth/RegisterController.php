<?php

/**
 * CONTRÔLEUR D'INSCRIPTION (RegisterController)
 *
 * Rôle : permet à un nouvel utilisateur de créer un compte local.
 * Comme l'API Toad ne propose pas d'endpoint d'inscription,
 * on enregistre l'utilisateur dans la base SQLite locale,
 * puis on le connecte immédiatement au même format que LoginController.
 */

namespace App\Http\Controllers\Auth;

use App\Auth\ToadUser;                      // Objet "utilisateur en mémoire" pour Auth Laravel
use App\Http\Controllers\Controller;
use App\Models\User;                        // Modèle Eloquent → table users (SQLite local)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;       // Facade Auth : connexion automatique après inscription
use Illuminate\Support\Facades\Hash;       // Hash::make() pour chiffrer le mot de passe (bcrypt)
use Illuminate\Validation\Rules\Password;  // Règle de validation de mot de passe (min 8 caractères, etc.)

class RegisterController extends Controller
{
    // Redirection après inscription réussie
    protected string $redirectTo = '/home';

    /**
     * Constructeur : seul un visiteur non connecté peut accéder à l'inscription.
     * middleware('guest') redirige automatiquement si déjà connecté.
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Affiche le formulaire d'inscription.
     * Retourne la vue resources/views/auth/register.blade.php
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Traite la soumission du formulaire d'inscription.
     *
     * Étapes :
     *  1. Validation des données saisies
     *  2. Création en base SQLite (avec mot de passe hashé)
     *  3. Construction des données de session (même format que LoginController)
     *  4. Connexion automatique du nouvel utilisateur
     *  5. Redirection vers la page d'accueil
     */
    public function register(Request $request)
    {
        // Étape 1 : Validation du formulaire
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            // 'unique:users' vérifie que l'email n'est pas déjà utilisé dans la table users
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            // 'confirmed' exige qu'un champ 'password_confirmation' identique soit présent
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Étape 2 : Créer l'enregistrement en base SQLite
        // Hash::make() transforme le mot de passe en clair en hash bcrypt sécurisé
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Étape 3 : Construire le tableau de session au même format que LoginController
        // Cela garantit que tout le reste de l'application (accès au storeId, au token…) fonctionne
        $userData = [
            'id'    => $user->id,
            'email' => $user->email,
            'name'  => $user->name,
            'token' => null, // Pas de token JWT pour un compte local (pas passé par l'API Toad)
            'staff' => [
                'staffId'  => $user->id,
                'email'    => $user->email,
                'name'     => $user->name,
                'storeId'  => 1, // Magasin par défaut
            ],
        ];

        // Sauvegarder en session (clé 'toad_user' — même clé que LoginController)
        $request->session()->put('toad_user', $userData);

        // Étape 4 : Connexion automatique avec l'objet ToadUser
        Auth::login(new ToadUser($userData));

        // Étape 5 : Rediriger vers /home
        return redirect($this->redirectTo);
    }
}
