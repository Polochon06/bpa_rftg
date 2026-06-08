<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(protected StockService $stockService) {}

    /**
     * Liste tous les stocks
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->stockService->getAllStocks(),
        ]);
    }

    /**
     * Résumé du stock d'un film
     */
    public function show(string $filmId): JsonResponse
    {
        $stock = $this->stockService->getStock($filmId);

        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Stock non trouvé pour ce film.'], 404);
        }

        return response()->json(['success' => true, 'data' => $stock]);
    }

    /**
     * Historique des mouvements (vide — pas de base locale)
     */
    public function historique(string $filmId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->stockService->getHistorique(),
        ]);
    }

    /**
     * Réception de DVD (entrée en stock)
     */
    public function reception(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filmId'   => 'required|string',
            'quantite' => 'required|integer|min:1',
            'storeId'  => 'nullable|integer',
        ]);

        try {
            $stock = $this->stockService->ajouterStock(
                $validated['filmId'],
                $validated['quantite'],
                $validated['storeId'] ?? 1
            );

            return response()->json([
                'success' => true,
                'message' => "{$validated['quantite']} DVD ajouté(s) au stock.",
                'data'    => $stock,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrait de stock (casse/perte/vol)
     */
    public function retrait(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filmId'   => 'required|string',
            'quantite' => 'required|integer|min:1',
            'motif'    => 'required|string',
        ]);

        try {
            $stock = $this->stockService->retirerStock(
                $validated['filmId'],
                $validated['quantite'],
                $validated['motif']
            );

            return response()->json([
                'success' => true,
                'message' => "{$validated['quantite']} DVD retiré(s) du stock.",
                'data'    => $stock,
            ]);
        } catch (\Exception $e) {
            $code = str_contains($e->getMessage(), 'insuffisant') ? 400 : 500;

            return response()->json(['success' => false, 'message' => $e->getMessage()], $code);
        }
    }

    /**
     * Cohérence du stock (délégué à l'API)
     */
    public function coherence(string $filmId): JsonResponse
    {
        $stock = $this->stockService->getStock($filmId);

        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Stock non trouvé pour ce film.'], 404);
        }

        return response()->json(['success' => true, 'data' => ['stock' => $stock]]);
    }
}
