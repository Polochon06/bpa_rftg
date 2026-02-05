<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToadCategoryService
{
    protected string $baseUrl;
    protected const TIMEOUT = 10;
    protected const RETRY_TIMES = 3;
    protected const RETRY_SLEEP = 100;

    public function __construct()
    {
        $toadUrl = config('services.toad.url');
        if (empty($toadUrl)) {
            Log::error('URL de l\'API Toad non configurée');
            throw new \RuntimeException('La configuration de l\'API Toad est manquante.');
        }
        $this->baseUrl = rtrim($toadUrl, '/');
    }

    protected function prepareRequest(): PendingRequest
    {
        // Priorité 1: Token de l'utilisateur connecté (session)
        $userToken = session('toad_user.token');

        // Priorité 2: Token de configuration (pour l'API générique)
        $configToken = config('services.toad.token');

        // Utiliser le token utilisateur si disponible, sinon le token de config
        $token = $userToken ?: $configToken;

        $request = Http::timeout(self::TIMEOUT)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP)
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]);

        if ($token) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * Récupère toutes les catégories depuis l'API
     */
    public function getAllCategories(): ?array
    {
        try {
            $response = $this->prepareRequest()->get($this->baseUrl . '/categories');

            if ($response->successful()) {
                $categories = $response->json() ?: [];
                Log::info('Catégories récupérées avec succès', ['count' => count($categories)]);
                return $categories;
            }

            Log::error('Erreur lors de la récupération des catégories depuis l\'API', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des catégories', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Récupère les films d'une catégorie spécifique
     */
    public function getFilmsByCategory(int $categoryId): ?array
    {
        try {
            Log::info('Récupération des films pour la catégorie', ['categoryId' => $categoryId]);

            $response = $this->prepareRequest()->get($this->baseUrl . '/films');

            if (!$response->successful()) {
                Log::error('Erreur récupération des films', ['status' => $response->status()]);
                return [];
            }

            $allFilms = $response->json() ?: [];
            Log::info('Films récupérés', ['count' => count($allFilms)]);

            // Récupérer les associations film-catégorie
            $filmCategoryResponse = $this->prepareRequest()->get($this->baseUrl . '/film-categories');

            if (!$filmCategoryResponse->successful()) {
                Log::warning('Endpoint /film-categories non disponible', ['status' => $filmCategoryResponse->status()]);
                return $allFilms; // Retourner tous les films si on ne peut pas filtrer
            }

            $filmCategories = $filmCategoryResponse->json() ?: [];
            Log::info('Associations film-catégorie récupérées', ['count' => count($filmCategories)]);

            // Filtrer les films appartenant à cette catégorie
            $filmIdsInCategory = [];
            foreach ($filmCategories as $fc) {
                $fcCategoryId = $fc['categoryId'] ?? $fc['category_id'] ?? null;
                if ($fcCategoryId == $categoryId) {
                    $filmId = $fc['filmId'] ?? $fc['film_id'] ?? null;
                    if ($filmId) {
                        $filmIdsInCategory[] = $filmId;
                    }
                }
            }

            Log::info('IDs de films dans la catégorie', ['filmIds' => $filmIdsInCategory, 'count' => count($filmIdsInCategory)]);

            $filteredFilms = array_filter($allFilms, function($film) use ($filmIdsInCategory) {
                $filmId = $film['filmId'] ?? $film['film_id'] ?? $film['id'] ?? null;
                return in_array($filmId, $filmIdsInCategory);
            });

            Log::info('Films filtrés', ['count' => count($filteredFilms)]);

            return array_values($filteredFilms);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des films par catégorie', [
                'categoryId' => $categoryId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Récupère les catégories d'un film
     */
    public function getCategoriesForFilm($filmId): ?array
    {
        try {
            $response = $this->prepareRequest()->get($this->baseUrl . '/film-categories');

            if (!$response->successful()) {
                return [];
            }

            $filmCategories = $response->json() ?: [];
            $categories = $this->getAllCategories();

            // Trouver les catégories du film
            $categoryIds = array_column(
                array_filter($filmCategories, function($fc) use ($filmId) {
                    return ($fc['filmId'] ?? $fc['film_id'] ?? null) == $filmId;
                }),
                'categoryId',
                'category_id'
            );

            // Retourner les objets catégories complets
            return array_filter($categories, function($cat) use ($categoryIds) {
                $catId = $cat['categoryId'] ?? $cat['category_id'] ?? null;
                return in_array($catId, $categoryIds);
            });

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des catégories du film', [
                'filmId' => $filmId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
