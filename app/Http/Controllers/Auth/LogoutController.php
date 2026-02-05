<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        try {
            // Supprimer les données de session Toad avant la déconnexion Laravel
            Session::forget('toad_user');

            // Déconnexion Laravel
            Auth::guard('web')->logout();

            // Invalider et régénérer la session
            if ($request->session()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            // Assurer que toutes les données de session sont bien supprimées
            Session::flush();

            // Redirection immédiate
            return redirect('/login');

        } catch (\Exception $e) {
            // Log l'erreur mais continuer la déconnexion
            Log::error('Erreur pendant la déconnexion: ' . $e->getMessage());
            
            // Forcer la redirection même en cas d'erreur
            return redirect('/login');
        }
    }
}