<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ToadInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected ToadInventoryService $inventoryService) {}

    /**
     * GET /inventories/film/{filmId}
     * Tous les exemplaires d'un film
     */
    public function byFilm(int $filmId): JsonResponse
    {
        $inventories = $this->inventoryService->getFilmInventories($filmId);

        return response()->json($inventories);
    }

    /**
     * GET /inventories/film/{filmId}/available
     * Exemplaires libres (ni en panier ni en location)
     */
    public function available(int $filmId): JsonResponse
    {
        $inventories = $this->inventoryService->getAvailableInventories($filmId);

        return response()->json($inventories);
    }

    /**
     * GET /inventories/film/{filmId}/stock
     * Résumé : { total, available, occupied }
     */
    public function stock(int $filmId): JsonResponse
    {
        $stock = $this->inventoryService->getFilmStock($filmId);

        return response()->json($stock);
    }

    /**
     * POST /inventories/film/{filmId}
     * Ajouter un exemplaire — body: { "storeId": 1 }
     */
    public function store(Request $request, int $filmId): JsonResponse
    {
        $validated = $request->validate([
            'storeId' => 'required|integer|min:1',
        ]);

        $result = $this->inventoryService->addInventory($filmId, $validated['storeId']);

        if ($result === null) {
            return response()->json(['message' => "Échec de l'ajout de l'exemplaire."], 500);
        }

        return response()->json($result, 201);
    }

    /**
     * DELETE /inventories/{id}
     * Supprimer un exemplaire
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->inventoryService->deleteInventory($id);

        if (!$deleted) {
            return response()->json(['message' => 'Exemplaire introuvable ou suppression impossible.'], 404);
        }

        return response()->json(null, 204);
    }
}
