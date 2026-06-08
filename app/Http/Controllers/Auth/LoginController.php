<?php

/**
 * CONTRÔLEUR DE CONNEXION (LoginController)
 *
 * Rôle : gère la connexion des utilisateurs à l'application.
 * Stratégie en deux temps :
 *   1. On tente d'authentifier via l'API externe Toad (base distante Peach)
 *   2. Si l'API est indisponible, on bascule sur une vérification locale (base SQLite)
 * Une fois authentifié, l'utilisateur est mis en session et "connecté" via Auth de Laravel.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ToadAuthService;         // Service qui appelle l'API Toad pour vérifier les identifiants
use Illuminate\Foundation\Auth\AuthenticatesUsers; // Trait Laravel : fournit sendLoginResponse(), etc.
use Illuminate\Http\Request;
use App\Models\User;                      // Modèle Eloquent → table users (SQLite local)
use Illuminate\Support\Facades\Auth;     // Facade Auth : gère la session de connexion Laravel
use Illuminate\Support\Facades\Hash;     // Hash : compare un mot de passe en clair avec son hash bcrypt
use Illuminate\Validation\ValidationException; // Exception levée quand les identifiants sont mauvais
use App\Auth\ToadUser;                    // Objet "utilisateur en mémoire" compatible avec l'Auth Laravel

class LoginController extends Controller
{
    // Le trait AuthenticatesUsers apporte des méthodes prêtes à l'emploi (sendLoginResponse, etc.)
    use AuthenticatesUsers;

    // URL de redirection après connexion réussie
    protected $redirectTo = '/home';

    // Instance du service d'authentification Toad (injectée par le conteneur)
    protected $toadAuth;

    /**
     * Constructeur : injection de dépendance + application du middleware
     *
     * Le middleware 'guest' redirige automatiquement l'utilisateur déjà connecté.
     * Exception pour 'logout' : un utilisateur connecté doit pouvoir se déconnecter.
     */
    public function __construct(ToadAuthService $toadAuth)
    {
        // 'guest' = seul un visiteur non connecté peut accéder à login/register
        $this->middleware('guest')->except('logout');
        $this->toadAuth = $toadAuth;
    }

    /**
     * Traite le formulaire de connexion soumis par l'utilisateur.
     *
     * Étapes :
     *  1. Validation des champs (email + password obligatoires)
     *  2. Tentative d'auth via l'API Toad
     *  3. Fallback sur la base locale si l'API est en panne
     *  4. Construction des données de session
     *  5. Connexion Laravel via Auth::login()
     */
    public function login(Request $request)
    {
        // Étape 1 : valider le formulaire avant d'appeler quoi que ce soit
        $this->validateLogin($request);

        $resp = null; // Contiendra la réponse de l'API (ou du fallback local)

        // Étape 2 : appel à l'API Toad pour vérifier email/password
        try {
            $resp = $this->toadAuth->verify(
                $request->input('email'),
                $request->input('password')
            );
        } catch (\RuntimeException $e) {
            // L'API Toad est inaccessible (timeout, erreur réseau…)
            // On ne propage pas l'erreur : on continue vers le fallback local
        }

        // Étape 3 : Fallback local — si l'API n'a rien renvoyé
        if (!$resp) {
            // Cherche l'utilisateur dans la table SQLite locale par son email
            $localUser = User::where('email', $request->input('email'))->first();

            // Hash::check compare le mot de passe saisi avec le hash stocké en base
            if ($localUser && Hash::check($request->input('password'), $localUser->password)) {
                // On fabrique une réponse au même format que l'API Toad
                $resp = [
                    'staffId' => $localUser->id,
                    'email'   => $localUser->email,
                    'name'    => $localUser->name,
                    'storeId' => 1, // magasin par défaut pour les comptes locaux
                ];
            }
        }

        // Si ni l'API ni le local ne confirment les identifiants → erreur de validation
        if (!$resp) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')], // Message : "These credentials do not match..."
            ]);
        }

        // Étape 4 : Normaliser la réponse (l'API peut renvoyer soit { staff: {...} } soit directement {...})
        $staff = $resp['staff'] ?? $resp;

        $userData = [
            'id'    => $staff['staffId'] ?? $staff['id'] ?? $staff['email'],

            'email' => $staff['email'] ?? null,

            // Construction du nom affiché : "Prénom Nom" ou email si aucun nom trouvé
            'name'  => $staff['name']
                       ?? trim(($staff['firstName'] ?? $staff['first_name'] ?? '').' '.($staff['lastName'] ?? $staff['last_name'] ?? ''))
                       ?: ($staff['email'] ?? 'Utilisateur'),

            // Jeton JWT fourni par l'API (utilisé pour les appels API suivants)
            'token' => $resp['token'] ?? $resp['access_token'] ?? null,

            'staff' => $staff, // Conservé en session pour accéder au storeId plus tard
        ];

        // Étape 5 : Stocker les données en session Laravel (clé 'toad_user')
        $request->session()->put('toad_user', $userData);

        // Créer un objet ToadUser (implémente Authenticatable) et connecter l'utilisateur
        // false = pas de "se souvenir de moi" (cookie persistant non géré par ce provider)
        $user = new ToadUser($userData);
        Auth::login($user, false);

        // Redirige vers $redirectTo avec le message flash de succès géré par le trait
        return $this->sendLoginResponse($request);
    }

    /**
     * Règles de validation du formulaire de connexion.
     * - email : champ obligatoire, format email valide
     * - password : champ obligatoire, chaîne de caractères
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);
    }
}
