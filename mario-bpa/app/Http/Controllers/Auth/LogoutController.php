<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LogoutController extends Controller
{
    /**
     * Déconnecte l'utilisateur
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function __invoke(Request $request)
    {
        // Récupère l'URL de redirection avant de nettoyer la session
        $redirect = route('login');

        // Nettoyage complet de la session
        Session::flush();
        
        // Supprime le cookie de session
        $request->session()->invalidate();
        
        // Régénère le token CSRF
        $request->session()->regenerateToken();
        
        // Déconnexion
        Auth::logout();

        // Redirige vers la page de connexion
        return redirect($redirect)->with('status', 'Vous avez été déconnecté avec succès.');
    }
}