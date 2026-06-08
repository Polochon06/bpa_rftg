<?php

/**
 * FICHIER DE ROUTES WEB (routes/web.php)
 *
 * Définit toutes les routes HTTP accessibles depuis le navigateur.
 * Chaque route associe une URL + un verbe HTTP (GET, POST, PUT, DELETE)
 * à une action de contrôleur.
 *
 * Convention de nommage (name) : permet de générer les URLs dans les vues
 * avec route('films.index') au lieu d'écrire l'URL en dur.
 *
 * Middleware 'auth' : redirige vers /login si l'utilisateur n'est pas connecté.
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\Auth\LogoutController;

// ============================================================================
// ROUTES PUBLIQUES (accessibles sans être connecté)
// ============================================================================

// Page d'accueil : affiche la vue resources/views/welcome.blade.php
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Routes d'authentification générées automatiquement par Laravel :
//  GET  /login           → afficher le formulaire de connexion
//  POST /login           → traiter la connexion (LoginController)
//  GET  /register        → afficher le formulaire d'inscription
//  POST /register        → traiter l'inscription (RegisterController)
//  GET  /password/reset  → mot de passe oublié
// ['logout' => false] désactive la route /logout par défaut (on la gère manuellement ci-dessous)
Auth::routes(['logout' => false]);

// Route de déconnexion personnalisée :
// POST /logout → LogoutController (on utilise POST pour éviter les déconnexions accidentelles via lien)
// middleware('auth') : seul un utilisateur connecté peut se déconnecter
Route::post('logout', LogoutController::class)->name('logout')->middleware('auth');

// ============================================================================
// ROUTES SEMI-PUBLIQUES (dashboard/home — accès possible sans auth selon config)
// ============================================================================

Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
Route::get('/home', [HomeController::class, 'index'])->name('home'); // Redirection après login

// ============================================================================
// ROUTES PROTÉGÉES (middleware 'auth' obligatoire)
// Regroupées pour appliquer le middleware une seule fois à toutes les routes du groupe
// ============================================================================

Route::middleware(['auth'])->group(function () {

    // --- FILMS (CRUD complet) ---
    // Respecte les conventions REST de Laravel (Resource Controller)

    // Lister tous les films + filtres (GET /films?search=...&year=...&category=...)
    Route::get('/films', [FilmController::class, 'index'])->name('films.index');

    // Afficher le formulaire de création (GET /films/create)
    // IMPORTANT : cette route doit être AVANT /films/{id} sinon "create" serait interprété comme un ID
    Route::get('/films/create', [FilmController::class, 'create'])->name('films.create');

    // Enregistrer un nouveau film (POST /films)
    Route::post('/films', [FilmController::class, 'store'])->name('films.store');

    // Afficher la fiche détaillée d'un film (GET /films/{id})
    Route::get('/films/{id}', [FilmController::class, 'show'])->name('films.show');

    // Afficher le formulaire d'édition (GET /films/{id}/edit)
    Route::get('/films/{id}/edit', [FilmController::class, 'edit'])->name('films.edit');

    // Mettre à jour un film (PUT /films/{id})
    // Le navigateur envoie POST avec un champ caché _method=PUT (HTML ne supporte pas PUT nativement)
    Route::put('/films/{id}', [FilmController::class, 'update'])->name('films.update');

    // Supprimer un film (DELETE /films/{id})
    // Même principe : POST avec _method=DELETE
    Route::delete('/films/{id}', [FilmController::class, 'destroy'])->name('films.destroy');

    // --- STOCKS ---

    // Liste de tous les films avec lien vers leur stock (GET /stocks?search=...)
    Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');

    // Détail du stock d'un film : résumé + liste des exemplaires (GET /stocks/{filmId})
    Route::get('/stocks/{filmId}', [StockController::class, 'show'])->name('stocks.show');

    // Réception de DVD : ajoute des exemplaires (POST /stocks/reception)
    Route::post('/stocks/reception', [StockController::class, 'reception'])->name('stocks.reception');

    // Retrait de DVD : supprime des exemplaires disponibles (POST /stocks/retrait)
    Route::post('/stocks/retrait', [StockController::class, 'retrait'])->name('stocks.retrait');
});
