<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToadInventoryService
{
    protected string $baseUrl;
    protected ?string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.toad.url'), '/');
        $this->token = config('services.toad.token');
    }

    /**
     * Récupère le stock réel d'un film depuis l'API
     */
    public function getFilmInventory(int $filmId): array
    {
        try {
            $request = Http::acceptJson()->timeout(10);

            if (!empty($this->token)) {
                $request = $request->withToken($this->token);
            }

            // Récupérer tous les inventaires du film
            $inventoriesResponse = $request->get($this->baseUrl . '/inventories');

            if (!$inventoriesResponse->successful()) {
                Log::warning('Impossible de récupérer les inventaires', [
                    'filmId' => $filmId,
                    'status' => $inventoriesResponse->status()
                ]);
                return [
                    'total' => 0,
                    'available' => 0,
                    'rented' => 0
                ];
            }

            $allInventories = $inventoriesResponse->json();

            // Filtrer les inventaires pour ce film
            $filmInventories = array_filter($allInventories, function($inv) use ($filmId) {
                return ($inv['filmId'] ?? $inv['film_id'] ?? null) == $filmId;
            });

            $total = count($filmInventories);

            if ($total === 0) {
                return [
                    'total' => 0,
                    'available' => 0,
                    'rented' => 0
                ];
            }

            // Récupérer toutes les locations
            $rentalsResponse = $request->get($this->baseUrl . '/rentals');

            if (!$rentalsResponse->successful()) {
                Log::warning('Impossible de récupérer les locations');
                return [
                    'total' => $total,
                    'available' => $total,
                    'rented' => 0
                ];
            }

            $allRentals = $rentalsResponse->json();

            // Compter les DVD actuellement loués (returnDate = null)
            $rentedCount = 0;
            $inventoryIds = array_column($filmInventories, 'inventoryId');

            foreach ($allRentals as $rental) {
                $invId = $rental['inventoryId'] ?? $rental['inventory_id'] ?? null;
                $returnDate = $rental['returnDate'] ?? $rental['return_date'] ?? null;

                // Si ce DVD est loué et pas encore retourné
                if (in_array($invId, $inventoryIds) && $returnDate === null) {
                    $rentedCount++;
                }
            }

            $available = $total - $rentedCount;

            return [
                'total' => $total,
                'available' => max(0, $available),
                'rented' => $rentedCount
            ];

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'inventaire', [
                'filmId' => $filmId,
                'error' => $e->getMessage()
            ]);

            return [
                'total' => 0,
                'available' => 0,
                'rented' => 0
            ];
        }
    }

    /**
     * Synchronise le stock local avec l'API
     */
    public function syncStockFromApi(int $filmId, \App\Models\DvdStock $stock): bool
    {
        try {
            $inventory = $this->getFilmInventory($filmId);

            $stock->quantite_totale = $inventory['total'];
            $stock->quantite_disponible = $inventory['available'];
            $stock->quantite_louee = $inventory['rented'];
            $stock->save();

            Log::info('Stock synchronisé depuis l\'API', [
                'filmId' => $filmId,
                'inventory' => $inventory
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la synchronisation du stock', [
                'filmId' => $filmId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Loue un DVD via l'API
     */
    public function rentDvd(int $filmId, int $customerId, int $staffId): ?array
    {
        try {
            // Trouver un inventaire disponible
            $inventory = $this->getFilmInventory($filmId);

            if ($inventory['available'] <= 0) {
                Log::warning('Aucun DVD disponible', ['filmId' => $filmId]);
                return null;
            }

            // Récupérer tous les inventaires du film
            $request = Http::acceptJson()->timeout(10);
            if (!empty($this->token)) {
                $request = $request->withToken($this->token);
            }

            $inventoriesResponse = $request->get($this->baseUrl . '/inventories');
            $allInventories = $inventoriesResponse->json();

            $filmInventories = array_filter($allInventories, function($inv) use ($filmId) {
                return ($inv['filmId'] ?? $inv['film_id'] ?? null) == $filmId;
            });

            // Récupérer les locations actuelles
            $rentalsResponse = $request->get($this->baseUrl . '/rentals');
            $allRentals = $rentalsResponse->json();

            // Trouver un DVD non loué
            $rentedInventoryIds = [];
            foreach ($allRentals as $rental) {
                if (($rental['returnDate'] ?? $rental['return_date'] ?? null) === null) {
                    $rentedInventoryIds[] = $rental['inventoryId'] ?? $rental['inventory_id'];
                }
            }

            $availableInventory = null;
            foreach ($filmInventories as $inv) {
                $invId = $inv['inventoryId'] ?? $inv['inventory_id'];
                if (!in_array($invId, $rentedInventoryIds)) {
                    $availableInventory = $inv;
                    break;
                }
            }

            if (!$availableInventory) {
                return null;
            }

            // Créer la location via l'API
            $rentalData = [
                'rental_date' => now()->toIso8601String(),
                'inventory_id' => $availableInventory['inventoryId'] ?? $availableInventory['inventory_id'],
                'customer_id' => $customerId,
                'staff_id' => $staffId,
                'return_date' => null
            ];

            $response = $request->post($this->baseUrl . '/rentals', $rentalData);

            if ($response->successful()) {
                Log::info('DVD loué via l\'API', [
                    'filmId' => $filmId,
                    'inventoryId' => $rentalData['inventory_id']
                ]);
                return $response->json();
            }

            Log::warning('Échec de la création de location', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la location de DVD', [
                'filmId' => $filmId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
