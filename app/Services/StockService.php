<?php

namespace App\Services;

use App\Models\DvdStock;
use App\Models\MouvementStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    protected ToadInventoryService $inventoryService;

    public function __construct(ToadInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Récupérer le stock d'un film (avec synchronisation API si disponible)
     */
    public function getStock($filmId, bool $syncFromApi = true)
    {
        $stock = DvdStock::where('filmId', $filmId)->first();

        // Synchroniser avec l'API si demandé et si le stock existe
        if ($syncFromApi && $stock) {
            try {
                $this->inventoryService->syncStockFromApi($filmId, $stock);
            } catch (\Exception $e) {
                Log::warning('Impossible de synchroniser le stock avec l\'API', [
                    'filmId' => $filmId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stock;
    }

    /**
     * Récupérer tous les stocks
     */
    public function getAllStocks()
    {
        return DvdStock::orderBy('filmId')->get();
    }

    /**
     * Ajouter du stock (réception de DVD)
     */
    public function ajouterStock($filmId, $quantite, $prixAchat = null, $notes = null)
    {
        try {
            DB::beginTransaction();

            // Récupérer ou créer le stock
            $stock = DvdStock::firstOrCreate(
                ['filmId' => $filmId],
                [
                    'quantite_totale' => 0,
                    'quantite_disponible' => 0,
                    'quantite_louee' => 0,
                    'prix_location' => 3.99,
                ]
            );

            // Mettre à jour les quantités
            $stock->quantite_totale += $quantite;
            $stock->quantite_disponible += $quantite;
            $stock->save();

            // Enregistrer le mouvement
            MouvementStock::create([
                'filmId' => $filmId,
                'type' => 'entree',
                'quantite' => $quantite,
                'motif' => 'Réception',
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            Log::info('Stock ajouté', ['filmId' => $filmId, 'quantite' => $quantite]);

            return $stock;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur ajout stock', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Retirer du stock (casse, perte, vol)
     */
    public function retirerStock($filmId, $quantite, $motif, $description = null)
    {
        try {
            DB::beginTransaction();

            $stock = DvdStock::where('filmId', $filmId)->firstOrFail();

            // Vérifier qu'on a assez de stock disponible
            if ($stock->quantite_disponible < $quantite) {
                throw new \Exception("Stock disponible insuffisant. Disponible: {$stock->quantite_disponible}, Demandé: {$quantite}");
            }

            // Mettre à jour les quantités
            $stock->quantite_totale -= $quantite;
            $stock->quantite_disponible -= $quantite;
            $stock->save();

            // Enregistrer le mouvement
            MouvementStock::create([
                'filmId' => $filmId,
                'type' => 'sortie',
                'quantite' => -$quantite,
                'motif' => $motif,
                'notes' => $description,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            Log::info('Stock retiré', ['filmId' => $filmId, 'quantite' => $quantite, 'motif' => $motif]);

            return $stock;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur retrait stock', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Louer un DVD
     */
    public function louerDvd($filmId)
    {
        try {
            DB::beginTransaction();

            $stock = DvdStock::where('filmId', $filmId)->firstOrFail();

            // Vérifier disponibilité
            if ($stock->quantite_disponible < 1) {
                throw new \Exception("Aucun DVD disponible pour ce film");
            }

            // Mettre à jour les quantités
            $stock->quantite_disponible -= 1;
            $stock->quantite_louee += 1;
            $stock->save();

            // Enregistrer le mouvement
            MouvementStock::create([
                'filmId' => $filmId,
                'type' => 'location',
                'quantite' => -1,
                'motif' => 'Location',
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            Log::info('DVD loué', ['filmId' => $filmId]);

            return $stock;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur location DVD', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Retourner un DVD
     */
    public function retournerDvd($filmId)
    {
        try {
            DB::beginTransaction();

            $stock = DvdStock::where('filmId', $filmId)->firstOrFail();

            // Vérifier qu'il y a des DVD loués
            if ($stock->quantite_louee < 1) {
                throw new \Exception("Aucun DVD loué pour ce film");
            }

            // Mettre à jour les quantités
            $stock->quantite_louee -= 1;
            $stock->quantite_disponible += 1;
            $stock->save();

            // Enregistrer le mouvement
            MouvementStock::create([
                'filmId' => $filmId,
                'type' => 'retour',
                'quantite' => 1,
                'motif' => 'Retour de location',
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            Log::info('DVD retourné', ['filmId' => $filmId]);

            return $stock;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur retour DVD', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Récupérer l'historique des mouvements d'un film
     */
    public function getHistorique($filmId, $limit = 50)
    {
        return MouvementStock::where('filmId', $filmId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Vérifier la cohérence du stock
     */
    public function verifierCoherence($filmId)
    {
        $stock = $this->getStock($filmId);

        if (!$stock) {
            return true;
        }

        $coherent = ($stock->quantite_totale == ($stock->quantite_disponible + $stock->quantite_louee));

        if (!$coherent) {
            Log::alert('Incohérence stock détectée', [
                'filmId' => $filmId,
                'total' => $stock->quantite_totale,
                'disponible' => $stock->quantite_disponible,
                'louee' => $stock->quantite_louee
            ]);
        }

        return $coherent;
    }

    /**
     * Initialiser le stock pour un film
     */
    public function initialiserStock($filmId, $prixLocation = 3.99)
    {
        return DvdStock::firstOrCreate(
            ['filmId' => $filmId],
            [
                'quantite_totale' => 0,
                'quantite_disponible' => 0,
                'quantite_louee' => 0,
                'prix_location' => $prixLocation,
            ]
        );
    }
}
