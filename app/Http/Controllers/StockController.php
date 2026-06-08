<?php

/**
 * CONTRÔLEUR DU STOCK (StockController)
 *
 * Rôle : gère l'affichage et la modification du stock de DVD.
 * Chaque film peut avoir plusieurs "exemplaires" (inventaires) physiques.
 *
 * Accès protégé par le middleware 'auth' : connexion obligatoire.
 *
 * Routes associées :
 *  GET  /stocks              → index()     : liste tous les films avec lien vers leur stock
 *  GET  /stocks/{filmId}     → show()      : détail du stock d'un film + liste des exemplaires
 *  POST /stocks/reception    → reception() : ajouter des DVD (entrée de stock)
 *  POST /stocks/retrait      → retrait()   : retirer des DVD (casse, vol, perte…)
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StockService;           // Service : résumé du stock + ajout/retrait
use App\Services\ToadFilmService;        // Service : récupérer les infos d'un film
use App\Services\ToadInventoryService;   // Service : lister les exemplaires un par un

class StockController extends Controller
{
    /**
     * Injection de dépendances via le constructeur (promoted properties PHP 8).
     * Les trois services sont automatiquement fournis par le conteneur IoC de Laravel.
     */
    public function __construct(
        protected StockService $stockService,           // Gestion des quantités
        protected ToadFilmService $filmService,         // Informations sur les films
        protected ToadInventoryService $inventoryService, // Liste des exemplaires physiques
    ) {
        // Middleware 'auth' : redirige vers login si l'utilisateur n'est pas connecté
        $this->middleware('auth');
    }

    /**
     * ACTION : Liste des films (GET /stocks)
     *
     * Affiche tous les films disponibles avec un bouton "Voir stock" pour chacun.
     * Un filtre de recherche par titre est disponible via le paramètre GET 'search'.
     */
    public function index(Request $request)
    {
        // Récupère tous les films (API + local) ; ?? [] évite null si l'API est hors ligne
        $films = $this->filmService->getAllFilms() ?? [];

        // Filtre texte : si un terme de recherche est présent dans l'URL
        if ($request->filled('search')) {
            $search = strtolower($request->search); // Normaliser en minuscules pour comparaison

            // array_filter conserve uniquement les films dont le titre contient le terme cherché
            $films = array_filter($films, fn($f) =>
                str_contains(strtolower($f['title'] ?? ''), $search)
            );
        }

        // array_values() réindexe le tableau après array_filter (qui conserve les clés d'origine)
        return view('stocks.index', [
            'films'  => array_values($films),
            'search' => $request->search ?? '', // Repasse le terme dans le champ de recherche
        ]);
    }

    /**
     * ACTION : Détail du stock d'un film (GET /stocks/{filmId})
     *
     * Affiche :
     *  - Le résumé du stock (total / disponible / occupé)
     *  - La liste de tous les exemplaires avec leur statut (disponible ou occupé)
     */
    public function show($filmId)
    {
        // Récupérer les informations du film
        $film  = $this->filmService->getFilmById($filmId);

        // Récupérer le résumé du stock (objet avec quantite_totale, quantite_disponible, quantite_louee)
        $stock = $this->stockService->getStock($filmId);

        // Si le film n'existe pas, rediriger avec un message d'erreur
        if (!$film) {
            return redirect()->route('stocks.index')->with('error', 'Film non trouvé');
        }

        // Récupérer la liste détaillée de tous les exemplaires du film
        $inventaires = $this->inventoryService->getFilmInventories((int) $filmId);

        // Récupérer uniquement les exemplaires libres (non en location, non en panier)
        $disponibles = $this->inventoryService->getAvailableInventories((int) $filmId);

        // Construire un tableau associatif : inventoryId => true
        // Permet un lookup en O(1) dans la vue (isset($disponiblesIds[$id]) au lieu d'in_array)
        $disponiblesIds = [];
        foreach ($disponibles as $d) {
            $id = $d['inventoryId'] ?? $d['inventory_id'] ?? null;
            if ($id !== null) {
                $disponiblesIds[(string) $id] = true; // cast en string pour cohérence de la clé
            }
        }

        // Passer toutes les données à la vue
        return view('stocks.show', [
            'film'           => $film,
            'stock'          => $stock,
            'inventaires'    => $inventaires,
            'disponiblesIds' => $disponiblesIds, // Utilisé dans la vue pour colorier chaque exemplaire
        ]);
    }

    /**
     * ACTION : Réception de DVD (POST /stocks/reception)
     *
     * Ajoute N exemplaires physiques au stock d'un film.
     * Chaque exemplaire est créé individuellement via l'API Toad.
     * Le storeId indique dans quel magasin les DVD sont reçus.
     */
    public function reception(Request $request)
    {
        // Validation des données du formulaire
        $request->validate([
            'filmId'   => 'required|string',
            'quantite' => 'required|integer|min:1',   // Au moins 1 DVD
            'storeId'  => 'nullable|integer|min:1',   // Optionnel : magasin par défaut si absent
        ]);

        try {
            // Déléguer l'ajout au service (boucle interne sur la quantité demandée)
            $this->stockService->ajouterStock(
                $request->filmId,
                (int) $request->quantite,
                $request->storeId ? (int) $request->storeId : null,
            );

            // Rediriger vers la page de stock du film avec un message de succès
            return redirect()
                ->route('stocks.show', $request->filmId)
                ->with('success', "{$request->quantite} DVD ajouté(s) au stock");

        } catch (\Exception $e) {
            // En cas d'erreur (API hors ligne, stock insuffisant…), afficher le message d'erreur
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * ACTION : Retrait de stock (POST /stocks/retrait)
     *
     * Supprime N exemplaires disponibles du stock d'un film.
     * Utilisé en cas de casse, vol, perte ou obsolescence.
     * Ne peut retirer que des exemplaires disponibles (pas loués).
     */
    public function retrait(Request $request)
    {
        // Validation des données du formulaire
        $request->validate([
            'filmId'   => 'required|string',
            'quantite' => 'required|integer|min:1',
            'motif'    => 'required|string', // Obligatoire : casse / perte / vol / obsolète / autre
        ]);

        try {
            // Déléguer le retrait au service (vérifie qu'il y a assez d'exemplaires disponibles)
            $this->stockService->retirerStock($request->filmId, (int) $request->quantite, $request->motif);

            return redirect()
                ->route('stocks.show', $request->filmId)
                ->with('success', "{$request->quantite} DVD retiré(s) du stock");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
