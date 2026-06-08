<?php

/**
 * POINT D'ENTRÉE DE L'APPLICATION LARAVEL (bootstrap/app.php)
 *
 * Ce fichier configure et crée l'instance principale de l'application Laravel.
 * C'est ici qu'on déclare :
 *  - les fichiers de routes à charger
 *  - les middlewares globaux
 *  - les providers de services personnalisés
 *
 * Il est chargé automatiquement par public/index.php à chaque requête HTTP.
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Providers\AppServiceProvider;   // Provider principal (liaisons, macros…)
use App\Providers\AuthServiceProvider;  // Provider d'authentification personnalisée (ToadUser)


return Application::configure(basePath: dirname(__DIR__))

    // --- ROUTES ---
    // Déclare les fichiers de routes que Laravel doit charger au démarrage
    ->withRouting(
        web: __DIR__.'/../routes/web.php',      // Routes HTTP classiques (navigateur)
        api: __DIR__.'/../routes/api.php',      // Routes API REST (JSON)
        commands: __DIR__.'/../routes/console.php', // Commandes Artisan personnalisées
        health: '/up',                          // Endpoint de health-check (retourne 200 si OK)
    )

    // --- MIDDLEWARES GLOBAUX ---
    // Middlewares appliqués à toutes les requêtes (CSRF, session, auth…)
    // La configuration par défaut de Laravel est utilisée ici.
    ->withMiddleware(function (Middleware $middleware): void {
        // Aucun middleware global supplémentaire n'est ajouté ici
    })

    // --- GESTION DES EXCEPTIONS ---
    // Personnalise le comportement en cas d'exception non attrapée
    ->withExceptions(function (Exceptions $exceptions): void {
        // Comportement par défaut de Laravel conservé
    })

    // --- PROVIDERS DE SERVICES ---
    // Les providers enregistrent les liaisons dans le conteneur IoC (injection de dépendances)
    ->withProviders([
        AppServiceProvider::class,

        // IMPORTANT : AuthServiceProvider enregistre le "guard" Toad
        // (le UserProvider qui sait recréer un ToadUser depuis la session)
        // Sans ce provider, Auth::user() retournerait null après chaque rechargement de page
        AuthServiceProvider::class,
    ])

    ->create(); // Construit et retourne l'instance Application configurée
