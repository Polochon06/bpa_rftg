<?php

/**
 * CONTRÔLEUR DES FILMS (FilmController)
 *
 * Rôle : gère toutes les opérations CRUD sur les films.
 * Lecture → récupérée depuis l'API Toad (via ToadFilmService)
 * Écriture (création, modification, suppression) → stockée en SQLite local
 *   (l'API Peach renvoie 403 sur toutes les mutations de films)
 *
 * Toutes les routes de ce contrôleur sont protégées par le middleware 'auth' :
 * un utilisateur non connecté sera redirigé vers la page de login.
 */

namespace App\Http\Controllers;

use App\Services\ToadFilmService;        // Service : appels API Toad pour les films
use App\Services\ToadCategoryService;    // Service : récupération des catégories
use App\Services\StockService;           // Service : résumé du stock d'un film
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;   // Facade Cache : mise en cache des résultats
use Illuminate\Support\Facades\Log;     // Facade Log : journalisation des erreurs
use Illuminate\Support\Facades\Auth;
use Illuminate\Cache\TaggableStore;      // Interface pour cache avec tags (Redis, Memcached)
use Illuminate\Support\Facades\Validator; // Validation manuelle (hors FormRequest)
use Illuminate\Validation\ValidationException;

class FilmController extends Controller
{
    // Services injectés par le conteneur IoC de Laravel
    private ToadFilmService $filmService;
    private ToadCategoryService $categoryService;
    private StockService $stockService;

    // Durée de mise en cache : 3600 secondes = 1 heure
    private const CACHE_TTL = 3600;

    // Nombre de films par page (pagination)
    private const ITEMS_PER_PAGE = 10;

    /**
     * Injection des services via le constructeur (injection de dépendances).
     * Le middleware 'auth' impose que l'utilisateur soit connecté pour toutes les actions.
     */
    public function __construct(ToadFilmService $filmService, ToadCategoryService $categoryService, StockService $stockService)
    {
        // Toutes les actions de ce contrôleur nécessitent d'être authentifié
        $this->middleware('auth');
        $this->filmService = $filmService;
        $this->categoryService = $categoryService;
        $this->stockService = $stockService;
    }

    /**
     * ACTION : Liste des films (GET /films)
     *
     * Récupère tous les films (API + local), applique les filtres
     * passés en paramètre GET, puis retourne la vue ou du JSON.
     *
     * Paramètres GET acceptés :
     *  - search    : filtre sur le titre (recherche insensible à la casse)
     *  - year      : filtre sur l'année de sortie
     *  - rating    : filtre sur la classification (G, PG, R…)
     *  - category  : filtre par ID de catégorie
     */
    public function index(Request $request)
    {
        try {
            // Récupérer tous les films depuis l'API (+ override local)
            $films = $this->filmService->getAllFilms();

            // Sécurité : si le service retourne null, on force un tableau vide
            if ($films === null) {
                $films = [];
            }

            // Récupérer les catégories pour alimenter le menu de filtrage dans la vue
            $categories = $this->categoryService->getAllCategories();

            // Lire les paramètres de filtrage depuis l'URL (?search=batman&year=1989…)
            $search     = $request->get('search');
            $year       = $request->get('year');
            $rating     = $request->get('rating');
            $categoryId = $request->get('category');

            // --- Filtres successifs (chaque filtre réduit le tableau $films) ---

            // Filtre texte : garde uniquement les films dont le titre contient $search
            if ($search) {
                $films = array_filter($films, function($film) use ($search) {
                    return str_contains(strtolower($film['title'] ?? ''), strtolower($search));
                });
            }

            // Filtre année : comparaison loose (== et non ===) pour gérer int vs string
            if ($year) {
                $films = array_filter($films, function($film) use ($year) {
                    return ($film['releaseYear'] ?? '') == $year;
                });
            }

            // Filtre classification : cast en string pour éviter les problèmes de type
            if ($rating) {
                $films = array_filter($films, function($film) use ($rating) {
                    return isset($film['rating']) && (string)$film['rating'] === (string)$rating;
                });
            }

            // Filtre catégorie : on récupère les IDs des films de la catégorie,
            // puis on garde uniquement les films dont l'ID figure dans cette liste
            if ($categoryId) {
                $filmsInCategory = $this->categoryService->getFilmsByCategory((int)$categoryId);

                // Extraire les IDs (l'API peut utiliser filmId, film_id ou id selon les versions)
                $filmIdsInCategory = array_map(function($f) {
                    return $f['filmId'] ?? $f['film_id'] ?? $f['id'];
                }, $filmsInCategory);

                $films = array_filter($films, function($film) use ($filmIdsInCategory) {
                    $filmId = $film['filmId'] ?? $film['film_id'] ?? $film['id'];
                    return in_array($filmId, $filmIdsInCategory);
                });
            }

            // array_values() réindexe le tableau (array_filter conserve les clés d'origine)
            $films = array_values($films);

            // Si la requête est AJAX ou attend du JSON → retourner du JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'films' => $films,
                    'total' => count($films)
                ]);
            }

            // Sinon → retourner la vue Blade avec les données
            return view('films.index', [
                'films'      => $films,
                'categories' => $categories,
                'total'      => count($films)
            ]);

        } catch (\Exception $e) {
            // Journaliser l'erreur pour le débogage (fichier storage/logs/laravel.log)
            Log::error('Erreur lors de la récupération des films: ' . $e->getMessage());

            // Réponse dégradée : on retourne un tableau vide plutôt que de planter la page
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la récupération des films.'
                ], 500);
            }

            return view('films.index', [
                'films'      => [],
                'categories' => [],
                'error'      => 'Une erreur est survenue lors de la récupération des films.'
            ]);
        }
    }

    /**
     * ACTION : Suppression d'un film (DELETE /films/{id})
     *
     * Supprime uniquement les films locaux (SQLite).
     * Un film de l'API Peach ne peut pas être supprimé (403 → le service retourne false).
     * Après suppression, on vide le cache pour forcer un rechargement des données.
     */
    public function destroy(Request $request, $id)
    {
        try {
            Log::info('Tentative de suppression du film', ['id' => $id]);

            // Appel au service : retourne true si supprimé, false si impossible
            $result = $this->filmService->deleteFilm($id);

            if (!$result) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['error' => 'Erreur lors de la suppression du film'], 500);
                }
                return redirect()->route('films.index')->with('error', 'Erreur lors de la suppression du film');
            }

            // Vider le cache pour ce film spécifique (clé "film.{id}")
            Cache::forget("film.{$id}");

            // Si le driver de cache supporte les tags (Redis, Memcached), vider tout le tag 'films'
            if (Cache::getStore() instanceof TaggableStore) {
                Cache::tags(['films'])->flush();
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('films.index')->with('success', 'Film supprimé avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du film: ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Erreur lors de la suppression du film'], 500);
            }

            return redirect()->route('films.index')
                ->with('error', 'Erreur lors de la suppression du film');
        }
    }

    /**
     * ACTION : Fiche détaillée d'un film (GET /films/{id})
     *
     * Charge le film par son ID, puis son stock (résumé),
     * et affiche la vue films/show avec ces données.
     */
    public function show($id)
    {
        try {
            // Récupère le film (local en priorité, sinon API)
            $film = $this->filmService->getFilmById($id);

            // Si le film n'existe nulle part, rediriger avec un message d'erreur
            if (!$film) {
                return redirect()->route('films.index')->with('error', 'Film non trouvé');
            }

            // Récupère le résumé du stock (total, disponible, loué)
            $stock = $this->stockService->getStock($id);

            // Passe film et stock à la vue Blade
            return view('films.show', [
                'film'  => $film,
                'stock' => $stock
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du film: ' . $e->getMessage());
            return redirect()->route('films.index')->with('error', 'Erreur lors de la récupération du film');
        }
    }

    /**
     * ACTION : Formulaire de création (GET /films/create)
     *
     * Affiche le formulaire vierge pour créer un nouveau film.
     */
    public function create()
    {
        return view('films.create');
    }

    /**
     * ACTION : Enregistrement d'un nouveau film (POST /films)
     *
     * Valide les données du formulaire, puis délègue la création au service.
     * Le film est stocké en SQLite local (l'API est en lecture seule).
     */
    public function store(Request $request)
    {
        try {
            // Validation manuelle avec Validator::make (pour pouvoir personnaliser la réponse JSON)
            $validator = Validator::make($request->all(), [
                'title'       => 'required|string|max:255',
                'description' => 'required|string',
                // releaseYear entre 1900 et l'année prochaine
                'releaseYear' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'length'      => 'required|integer|min:1',
                'rating'      => 'required|string|max:10'
            ]);

            // Si la validation échoue → retourner les erreurs
            if ($validator->fails()) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }
                // withErrors() renvoie les erreurs au formulaire, withInput() pré-remplit les champs
                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Création du film via le service (stockage SQLite)
            $film = $this->filmService->createFilm($request->all());

            if (!$film) {
                throw new \RuntimeException('Erreur lors de la création du film');
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Film créé avec succès',
                    'film'    => $film
                ]);
            }

            return redirect()->route('films.index')->with('success', 'Film créé avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du film: ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la création du film.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Une erreur est survenue lors de la création du film.')
                ->withInput();
        }
    }

    /**
     * ACTION : Formulaire d'édition (GET /films/{id}/edit)
     *
     * Récupère le film et affiche le formulaire pré-rempli.
     */
    public function edit($id)
    {
        try {
            $film = $this->filmService->getFilmById($id);

            if (!$film) {
                return redirect()->route('films.index')->with('error', 'Film non trouvé.');
            }

            // compact('film') est équivalent à ['film' => $film]
            return view('films.edit', compact('film'));

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération du film {$id}: " . $e->getMessage());

            return redirect()->route('films.index')
                ->with('error', 'Une erreur est survenue lors de la récupération du film.');
        }
    }

    /**
     * ACTION : Mise à jour d'un film (PUT /films/{id})
     *
     * Stratégie du service :
     *  - Si le film existe en local → mise à jour directe
     *  - Si c'est un film Peach (API) → création d'un "override" local avec le même filmId
     *    (ainsi il prend la priorité sur le distant lors de la prochaine lecture)
     */
    public function update(Request $request, $id)
    {
        try {
            // Log des données reçues (utile pour le débogage)
            Log::info('Tentative de mise à jour du film', [
                'id'   => $id,
                // except() exclut les champs sensibles du log (_token, _method)
                'data' => $request->except(['_token', '_method'])
            ]);

            $validator = Validator::make($request->all(), [
                'title'       => 'required|string|max:255',
                'description' => 'required|string',
                'releaseYear' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'length'      => 'required|integer|min:1',
                'rating'      => 'required|string|max:10'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation échouée pour la mise à jour du film', [
                    'errors' => $validator->errors()->toArray()
                ]);

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Déléguer la mise à jour au service
            $film = $this->filmService->updateFilm($id, $request->all());

            if (!$film) {
                Log::error('Le service a retourné null pour la mise à jour du film', ['id' => $id]);
                throw new \RuntimeException('Erreur lors de la mise à jour du film');
            }

            Log::info('Film mis à jour avec succès', ['film' => $film]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Film mis à jour avec succès',
                    'film'    => $film
                ]);
            }

            return redirect()->route('films.index')->with('success', 'Film mis à jour avec succès');

        } catch (\Exception $e) {
            // getTraceAsString() : affiche la pile d'appel complète dans les logs
            Log::error("Erreur lors de la mise à jour du film {$id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la mise à jour du film.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Une erreur est survenue lors de la mise à jour du film.')
                ->withInput();
        }
    }
}
