<?php

/**
 * SERVICE INVENTAIRE TOAD (ToadInventoryService)
 *
 * Rôle : passerelle directe vers les endpoints /inventories de l'API Toad.
 * Un "inventaire" = un exemplaire physique d'un DVD dans un magasin.
 *
 * Ce service ne contient aucune logique métier : il se contente
 * de faire des appels HTTP et de retourner les données brutes de l'API.
 * La logique (boucles, vérifications) est dans StockService.
 *
 * Endpoints couverts :
 *  GET  /inventories/film/{filmId}/stock      → résumé (total / disponible / occupé)
 *  GET  /inventories/film/{filmId}            → liste de tous les exemplaires
 *  GET  /inventories/film/{filmId}/available  → liste des exemplaires libres
 *  POST /inventories/film/{filmId}            → ajouter un exemplaire
 *  DELETE /inventories/{id}                   → supprimer un exemplaire
 */

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToadInventoryService
{
    // URL de base de l'API Toad (ex: http://localhost:3001/api)
    protected string $baseUrl;

    // Token de service (fallback si aucun utilisateur connecté)
    protected ?string $token;

    /**
     * Constructeur : lecture de la configuration depuis config/services.php
     * qui elle-même lit les variables .env (TOAD_API_URL, TOAD_API_TOKEN).
     */
    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.toad.url'), '/'); // Enlève le '/' final
        $this->token   = config('services.toad.token');
    }

    /**
     * Crée le client HTTP configuré pour chaque requête.
     *
     * Résolution du token (priorité décroissante) :
     *  1. Token JWT de l'utilisateur connecté (stocké en session après login)
     *  2. Token de service défini dans .env (TOAD_API_TOKEN)
     */
    private function http()
    {
        // session('toad_user.token') : accès à la clé imbriquée 'token' dans 'toad_user'
        // ?: : opérateur "ou" : utilise $this->token si la session est vide/null
        $token = session('toad_user.token') ?: $this->token;

        // acceptJson() ajoute "Accept: application/json"
        // timeout(10) : limite les requêtes à 10 secondes pour éviter les blocages
        $client = Http::acceptJson()->timeout(10);

        if (!empty($token)) {
            // withToken() ajoute l'en-tête "Authorization: Bearer {token}"
            $client = $client->withToken($token);
        }

        return $client;
    }

    /**
     * Retourne le résumé du stock d'un film.
     *
     * Endpoint : GET /inventories/film/{filmId}/stock
     * Réponse attendue : { "total": 10, "available": 4, "occupied": 6 }
     *
     * En cas d'erreur, retourne des zéros pour ne pas planter la vue.
     */
    public function getFilmStock(int $filmId): array
    {
        try {
            $response = $this->http()->get("{$this->baseUrl}/inventories/film/{$filmId}/stock");

            if ($response->successful()) {
                return $response->json(); // Retourne le tableau PHP décodé depuis le JSON
            }

            Log::warning('Impossible de récupérer le stock du film', [
                'filmId' => $filmId,
                'status' => $response->status(), // Ex: 404, 403, 500
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getFilmStock', ['filmId' => $filmId, 'error' => $e->getMessage()]);
        }

        // Valeur de secours : pas de stock plutôt qu'une erreur dans la vue
        return ['total' => 0, 'available' => 0, 'occupied' => 0];
    }

    /**
     * Retourne la liste complète de tous les exemplaires d'un film.
     *
     * Endpoint : GET /inventories/film/{filmId}
     * Réponse attendue : tableau d'objets { inventoryId, storeId, lastUpdate, ... }
     *
     * Utile pour afficher chaque exemplaire individuellement avec son statut.
     */
    public function getFilmInventories(int $filmId): array
    {
        try {
            $response = $this->http()->get("{$this->baseUrl}/inventories/film/{$filmId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Impossible de récupérer les inventaires du film', [
                'filmId' => $filmId,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getFilmInventories', ['filmId' => $filmId, 'error' => $e->getMessage()]);
        }

        return []; // Tableau vide en cas d'erreur : la vue affichera "Aucun exemplaire"
    }

    /**
     * Retourne uniquement les exemplaires libres d'un film.
     *
     * Endpoint : GET /inventories/film/{filmId}/available
     * "Disponible" = ni en location active, ni dans un panier client
     *
     * Utilisé par StockService pour savoir combien de DVD on peut retirer.
     */
    public function getAvailableInventories(int $filmId): array
    {
        try {
            $response = $this->http()->get("{$this->baseUrl}/inventories/film/{$filmId}/available");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Impossible de récupérer les inventaires disponibles', [
                'filmId' => $filmId,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getAvailableInventories', ['filmId' => $filmId, 'error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Ajoute un nouvel exemplaire DVD au stock d'un film.
     *
     * Endpoint : POST /inventories/film/{filmId}
     * Corps JSON envoyé : { "storeId": 1 }
     * Réponse : le nouvel exemplaire créé avec son inventoryId
     *
     * @return array|null  L'exemplaire créé, ou null si l'API a refusé
     */
    public function addInventory(int $filmId, int $storeId = 1): ?array
    {
        try {
            // post() avec tableau = envoi automatique en JSON avec Content-Type: application/json
            $response = $this->http()->post("{$this->baseUrl}/inventories/film/{$filmId}", [
                'storeId' => $storeId,
            ]);

            if ($response->successful()) {
                Log::info('Inventaire ajouté', ['filmId' => $filmId, 'storeId' => $storeId]);
                return $response->json(); // Retourne le nouvel exemplaire
            }

            Log::warning('Échec ajout inventaire', [
                'filmId' => $filmId,
                'status' => $response->status(),
                'body'   => $response->body(), // Corps brut de la réponse pour le débogage
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur addInventory', ['filmId' => $filmId, 'error' => $e->getMessage()]);
        }

        return null; // null = l'ajout a échoué (StockService lèvera une exception)
    }

    /**
     * Supprime un exemplaire DVD par son ID d'inventaire.
     *
     * Endpoint : DELETE /inventories/{inventoryId}
     *
     * @return bool  true si suppression réussie, false sinon
     */
    public function deleteInventory(int $inventoryId): bool
    {
        try {
            $response = $this->http()->delete("{$this->baseUrl}/inventories/{$inventoryId}");

            if ($response->successful()) {
                Log::info('Inventaire supprimé', ['inventoryId' => $inventoryId]);
                return true;
            }

            Log::warning('Échec suppression inventaire', [
                'inventoryId' => $inventoryId,
                'status'      => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur deleteInventory', ['inventoryId' => $inventoryId, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Supprime le premier exemplaire disponible d'un film.
     *
     * Méthode utilitaire : récupère la liste des disponibles
     * puis supprime uniquement le premier (reset() retourne le premier élément).
     *
     * Utilisé pour les retraits unitaires sans préciser l'ID exact.
     */
    public function deleteFirstAvailableInventory(int $filmId): bool
    {
        $available = $this->getAvailableInventories($filmId);

        if (empty($available)) {
            Log::warning('Aucun exemplaire disponible à supprimer', ['filmId' => $filmId]);
            return false;
        }

        // reset() positionne le pointeur interne sur le premier élément et le retourne
        $first       = reset($available);
        $inventoryId = $first['inventoryId'] ?? $first['inventory_id'] ?? null;

        if (!$inventoryId) {
            return false; // Réponse API malformée : clé inventoryId absente
        }

        return $this->deleteInventory((int) $inventoryId);
    }
}
