<?php

namespace App\Http\Controllers;

use App\Services\ToadFilmService;
use App\Services\ToadCategoryService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FilmController extends Controller
{
    private ToadFilmService $filmService;
    private ToadCategoryService $categoryService;
    private StockService $stockService;
    private const CACHE_TTL = 3600; // 1 heure
    private const ITEMS_PER_PAGE = 10;

    public function __construct(ToadFilmService $filmService, ToadCategoryService $categoryService, StockService $stockService)
    {
        $this->middleware('auth');
        $this->filmService = $filmService;
        $this->categoryService = $categoryService;
        $this->stockService = $stockService;
    }

    /**
     * Affiche la liste des films
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $films = $this->filmService->getAllFilms();

            if ($films === null) {
                $films = [];
            }

            // Récupérer toutes les catégories pour le filtre
            $categories = $this->categoryService->getAllCategories();

            $search = $request->get('search');
            $year = $request->get('year');
            $rating = $request->get('rating');
            $categoryId = $request->get('category');

            // Filtrage des films
            if ($search) {
                $films = array_filter($films, function($film) use ($search) {
                    return str_contains(strtolower($film['title'] ?? ''), strtolower($search));
                });
            }

            if ($year) {
                $films = array_filter($films, function($film) use ($year) {
                    return ($film['releaseYear'] ?? '') == $year;
                });
            }

            if ($rating) {
                $films = array_filter($films, function($film) use ($rating) {
                    return isset($film['rating']) && (string)$film['rating'] === (string)$rating;
                });
            }

            // Filtrage par catégorie
            if ($categoryId) {
                $filmsInCategory = $this->categoryService->getFilmsByCategory((int)$categoryId);
                $filmIdsInCategory = array_map(function($f) {
                    return $f['filmId'] ?? $f['film_id'] ?? $f['id'];
                }, $filmsInCategory);

                $films = array_filter($films, function($film) use ($filmIdsInCategory) {
                    $filmId = $film['filmId'] ?? $film['film_id'] ?? $film['id'];
                    return in_array($filmId, $filmIdsInCategory);
                });
            }

            // Réinitialiser les clés du tableau après filtrage
            $films = array_values($films);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'films' => $films,
                    'total' => count($films)
                ]);
            }

            return view('films.index', [
                'films' => $films,
                'categories' => $categories,
                'total' => count($films)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des films: ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la récupération des films.'
                ], 500);
            }

            return view('films.index', [
                'films' => [],
                'categories' => [],
                'error' => 'Une erreur est survenue lors de la récupération des films.'
            ]);
        }
    }

    /**
     * Supprime un film
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            Log::info('Tentative de suppression du film', ['id' => $id]);
            $result = $this->filmService->deleteFilm($id);

            if (!$result) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['error' => 'Erreur lors de la suppression du film'], 500);
                }
                return redirect()->route('films.index')->with('error', 'Erreur lors de la suppression du film');
            }

            // Nettoyer le cache si la suppression a réussi
            Cache::forget("film.{$id}");
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
     * Affiche les détails d'un film
     *
     * @param string $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        try {
            $film = $this->filmService->getFilmById($id);

            if (!$film) {
                return redirect()->route('films.index')->with('error', 'Film non trouvé');
            }

            // Récupérer le stock du film
            $stock = $this->stockService->getStock($id);

            // Si pas de stock, l'initialiser
            if (!$stock) {
                $stock = $this->stockService->initialiserStock($id);
            }

            return view('films.show', [
                'film' => $film,
                'stock' => $stock
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du film: ' . $e->getMessage());
            return redirect()->route('films.index')->with('error', 'Erreur lors de la récupération du film');
        }
    }

    /**
     * Affiche le formulaire de création d'un film
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('films.create');
    }

    /**
     * Stocke un nouveau film
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'releaseYear' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'length' => 'required|integer|min:1',
                'rating' => 'required|string|max:10'
            ]);

            if ($validator->fails()) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                return redirect()
                    ->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $film = $this->filmService->createFilm($request->all());

            if (!$film) {
                throw new \RuntimeException('Erreur lors de la création du film');
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Film créé avec succès',
                    'film' => $film
                ]);
            }

            return redirect()
                ->route('films.index')
                ->with('success', 'Film créé avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du film: ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la création du film.'
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'Une erreur est survenue lors de la création du film.')
                ->withInput();
        }
    }

    /**
     * Affiche le formulaire d'édition d'un film
     *
     * @param string $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        try {
            $film = $this->filmService->getFilmById($id);

            if (!$film) {
                return redirect()
                    ->route('films.index')
                    ->with('error', 'Film non trouvé.');
            }

            return view('films.edit', compact('film'));

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération du film {$id}: " . $e->getMessage());

            return redirect()
                ->route('films.index')
                ->with('error', 'Une erreur est survenue lors de la récupération du film.');
        }
    }

    /**
     * Met à jour un film
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            Log::info('Tentative de mise à jour du film', [
                'id' => $id,
                'data' => $request->except(['_token', '_method'])
            ]);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'releaseYear' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'length' => 'required|integer|min:1',
                'rating' => 'required|string|max:10'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation échouée pour la mise à jour du film', [
                    'errors' => $validator->errors()->toArray()
                ]);

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                return redirect()
                    ->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $film = $this->filmService->updateFilm($id, $request->all());

            if (!$film) {
                Log::error('Le service a retourné null pour la mise à jour du film', ['id' => $id]);
                throw new \RuntimeException('Erreur lors de la mise à jour du film');
            }

            Log::info('Film mis à jour avec succès', ['film' => $film]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Film mis à jour avec succès',
                    'film' => $film
                ]);
            }

            return redirect()
                ->route('films.index')
                ->with('success', 'Film mis à jour avec succès');

        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour du film {$id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la mise à jour du film.'
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'Une erreur est survenue lors de la mise à jour du film.')
                ->withInput();
        }
    }
}