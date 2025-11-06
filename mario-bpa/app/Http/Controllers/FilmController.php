<?php

namespace App\Http\Controllers;

use App\Services\ToadFilmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FilmController extends Controller
{
    private ToadFilmService $filmService;
    private const CACHE_TTL = 3600; // 1 heure
    private const ITEMS_PER_PAGE = 10;

    public function __construct(ToadFilmService $filmService)
    {
        $this->middleware('auth');
        $this->filmService = $filmService;
    }

    /**
     * Affiche la liste des films avec pagination et filtres
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

            $search = $request->get('search');
            $year = $request->get('year');
            $rating = $request->get('rating');

            // Filtrage si nécessaire
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
                    return ($film['rating'] ?? 0) >= $rating;
                });
            }

            // Reset array keys after filtering
            $films = array_values($films);

            if ($request->ajax()) {
                return response()->json([
                    'films' => $films,
                    'total' => count($films)
                ]);
            }

            return view('films.index', [
                'films' => $films,
                'total' => count($films)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des films: ' . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la récupération des films.'
                ], 500);
            }

            return view('films.index', [
                'films' => [],
                'error' => 'Une erreur est survenue lors de la récupération des films.'
            ]);
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
     * Enregistre un nouveau film
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
                'releaseYear' => 'required|integer|min:1900|max:' . (date('Y') + 5),
                'length' => 'required|integer|min:1',
                'rating' => 'required|numeric|min:0|max:5'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $result = $this->filmService->createFilm($request->all());

            if (!$result) {
                throw new \RuntimeException('Erreur lors de la création du film');
            }

            Cache::tags(['films'])->flush();

            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Film créé avec succès',
                    'film' => $result
                ], 201);
            }

            return redirect()
                ->route('films.show', $result['id'])
                ->with('success', 'Film créé avec succès');

        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du film: ' . $e->getMessage());

            if ($request->ajax()) {
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
     * Affiche les détails d'un film
     *
     * @param string $id
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $film = $this->filmService->getFilmById($id);

            if (!$film) {
                throw new \RuntimeException('Film non trouvé');
            }

            if (request()->ajax()) {
                return response()->json($film);
            }

            return view('films.show', ['film' => $film]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération du film {$id}: " . $e->getMessage());

            if (request()->ajax()) {
                return response()->json([
                    'error' => 'Film non trouvé'
                ], 404);
            }

            abort(404, 'Film non trouvé');
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
        $film = $this->filmService->getFilmById($id);

        if (!$film) {
            abort(404, 'Film non trouvé');
        }

        return view('films.edit', ['film' => $film]);
    }

    /**
     * Met à jour un film existant
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'releaseYear' => 'required|integer|min:1900|max:' . (date('Y') + 5),
                'length' => 'required|integer|min:1',
                'rating' => 'required|numeric|min:0|max:5'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $result = $this->filmService->updateFilm($id, $request->all());

            if (!$result) {
                throw new \RuntimeException('Erreur lors de la mise à jour du film');
            }

            Cache::forget("film.{$id}");
            Cache::tags(['films'])->flush();

            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Film mis à jour avec succès',
                    'film' => $result
                ]);
            }

            return redirect()
                ->route('films.show', $id)
                ->with('success', 'Film mis à jour avec succès');

        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour du film {$id}: " . $e->getMessage());

            if ($request->ajax()) {
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

    /**
     * Supprime un film
     *
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $result = $this->filmService->deleteFilm($id);

            if (!$result) {
                throw new \RuntimeException('Erreur lors de la suppression du film');
            }

            Cache::forget("film.{$id}");
            Cache::tags(['films'])->flush();

            if (request()->ajax()) {
                return response()->json([
                    'message' => 'Film supprimé avec succès'
                ]);
            }

            return redirect()
                ->route('films.index')
                ->with('success', 'Film supprimé avec succès');

        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression du film {$id}: " . $e->getMessage());

            if (request()->ajax()) {
                return response()->json([
                    'error' => 'Une erreur est survenue lors de la suppression du film.'
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'Une erreur est survenue lors de la suppression du film.');
        }
    }
}