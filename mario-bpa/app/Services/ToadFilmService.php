<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToadFilmService
{
    private string $baseUrl;
    private const TIMEOUT = 10;
    private const RETRY_TIMES = 3;
    private const RETRY_SLEEP = 100;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.toad.url', 'http://localhost:8180'), '/');
    }

    /**
     * Récupère tous les films
     *
     * @return array|null
     * @throws \RuntimeException
     */
    public function getAllFilms(): ?array
    {
        $url = $this->baseUrl . '/films';

        try {
            $response = $this->prepareRequest()
                ->get($url);

            $this->handleResponseError($response, 'Récupération de tous les films');

            return $response->json();

        } catch (\Throwable $e) {
            $this->handleException($e, 'Erreur lors de la récupération de tous les films');
            return null;
        }
    }

    /**
     * Récupère un film par son ID
     *
     * @param int $id
     * @return array|null
     * @throws \RuntimeException
     */
    public function getFilmById($id): ?array
    {
        $url = $this->baseUrl . '/films/' . $id;

        try {
            $response = $this->prepareRequest()
                ->get($url);

            $this->handleResponseError($response, "Récupération du film {$id}");

            return $response->json();

        } catch (\Throwable $e) {
            $this->handleException($e, "Erreur lors de la récupération du film {$id}");
            return null;
        }
    }

    /**
     * Crée un nouveau film
     *
     * @param array $data
     * @return array|null
     * @throws \RuntimeException
     */
    public function createFilm(array $data): ?array
    {
        $url = $this->baseUrl . '/films';

        try {
            $response = $this->prepareRequest()
                ->post($url, $data);

            $this->handleResponseError($response, 'Création d\'un film');

            // Invalider le cache
            Cache::tags(['films'])->flush();

            return $response->json();

        } catch (\Throwable $e) {
            $this->handleException($e, 'Erreur lors de la création du film');
            return null;
        }
    }

    /**
     * Met à jour un film existant
     *
     * @param int $id
     * @param array $data
     * @return array|null
     * @throws \RuntimeException
     */
    public function updateFilm($id, array $data): ?array
    {
        $url = $this->baseUrl . '/films/' . $id;

        try {
            $response = $this->prepareRequest()
                ->put($url, $data);

            $this->handleResponseError($response, "Mise à jour du film {$id}");

            // Invalider le cache
            Cache::forget("film.{$id}");
            Cache::tags(['films'])->flush();

            return $response->json();

        } catch (\Throwable $e) {
            $this->handleException($e, "Erreur lors de la mise à jour du film {$id}");
            return null;
        }
    }

    /**
     * Supprime un film
     *
     * @param int $id
     * @return bool
     * @throws \RuntimeException
     */
    public function deleteFilm($id): bool
    {
        $url = $this->baseUrl . '/films/' . $id;

        try {
            $response = $this->prepareRequest()
                ->delete($url);

            $this->handleResponseError($response, "Suppression du film {$id}");

            // Invalider le cache
            Cache::forget("film.{$id}");
            Cache::tags(['films'])->flush();

            return true;

        } catch (\Throwable $e) {
            $this->handleException($e, "Erreur lors de la suppression du film {$id}");
            return false;
        }
    }

    /**
     * Prépare une requête HTTP avec les en-têtes et options communes
     *
     * @return PendingRequest
     */
    private function prepareRequest(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        if ($token = $this->getUserToken()) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return Http::withHeaders($headers)
            ->timeout(self::TIMEOUT)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP);
    }

    /**
     * Gère les erreurs de réponse HTTP
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param string $context
     * @throws \RuntimeException
     */
    private function handleResponseError($response, string $context): void
    {
        if (!$response->successful()) {
            $error = $response->json()['error'] ?? $response->body();
            $message = "{$context} - Statut: {$response->status()} - Erreur: {$error}";
            
            Log::error($message, [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            throw new \RuntimeException($message);
        }
    }

    /**
     * Gère les exceptions
     *
     * @param \Throwable $e
     * @param string $context
     * @throws \RuntimeException
     */
    private function handleException(\Throwable $e, string $context): void
    {
        $message = "{$context}: {$e->getMessage()}";
        
        Log::error($message, [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        throw new \RuntimeException($message, 0, $e);
    }

    /**
     * Récupère le token JWT depuis la session utilisateur
     *
     * @return string|null
     */
    private function getUserToken(): ?string
    {
        try {
            $userData = session('toad_user');
            
            if (!$userData || empty($userData['token'])) {
                Log::warning('Token utilisateur non trouvé dans la session');
                return null;
            }

            return $userData['token'];

        } catch (\Throwable $e) {
            Log::error('Erreur lors de la récupération du token: ' . $e->getMessage());
            return null;
        }
    }
}