<?php

/**
 * SERVICE STOCK (StockService)
 *
 * Rôle : couche métier entre le StockController et l'API Toad (via ToadInventoryService).
 * Traduit les opérations de stock (ajouter, retirer, consulter) en appels API.
 *
 * Pas de base de données locale pour le stock : tout passe par l'API Toad.
 * Le service encapsule la logique (boucles, vérifications) pour alléger le contrôleur.
 */

namespace App\Services;

use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Injection du ToadInventoryService (appels API réels) via le constructeur.
     * Promoted property PHP 8 : déclare et assigne en une seule ligne.
     */
    public function __construct(protected ToadInventoryService $inventoryService) {}

    /**
     * Retourne un résumé du stock d'un film sous forme d'objet PHP.
     *
     * L'objet retourné a trois propriétés :
     *  - quantite_totale     : nombre total d'exemplaires (loués + disponibles)
     *  - quantite_disponible : exemplaires libres (peut être emprunté maintenant)
     *  - quantite_louee      : exemplaires occupés (en location ou en panier)
     *
     * @param int|string $filmId  ID du film (entier ou chaîne comme "local_5")
     * @return object|null        Objet stock, ou null si filmId non numérique
     */
    public function getStock(int|string $filmId): ?object
    {
        // Les films locaux ont un ID du type "local_5" — pas d'inventaire API possible
        if (!is_numeric($filmId)) {
            return null;
        }

        // Appel API : GET /inventories/film/{filmId}/stock → { total, available, occupied }
        $data = $this->inventoryService->getFilmStock((int) $filmId);

        // On convertit le tableau en objet stdClass avec des noms explicites pour les vues
        return (object) [
            'filmId'              => (int) $filmId,
            'quantite_totale'     => $data['total']     ?? 0, // ?? 0 = valeur par défaut si clé absente
            'quantite_disponible' => $data['available'] ?? 0,
            'quantite_louee'      => $data['occupied']  ?? 0,
        ];
    }

    /**
     * Ajoute N exemplaires DVD au stock d'un film.
     *
     * Stratégie : appeler addInventory() en boucle autant de fois que la quantité demandée.
     * L'API ne supporte pas l'ajout de N exemplaires en un seul appel.
     *
     * Le storeId (magasin) est résolu dans cet ordre :
     *  1. Paramètre $storeId passé explicitement
     *  2. storeId du staff connecté (stocké en session après login)
     *  3. Valeur par défaut : 1
     *
     * @throws \Exception Si filmId invalide ou si un appel API échoue
     */
    public function ajouterStock(int|string $filmId, int $quantite, ?int $storeId = null): object
    {
        if (!is_numeric($filmId)) {
            throw new \Exception("filmId invalide : {$filmId}");
        }

        // Résolution du magasin : session > paramètre > défaut 1
        if ($storeId === null) {
            // session('toad_user.staff', []) : accès nested en session avec valeur par défaut
            $staff   = session('toad_user.staff', []);
            $storeId = (int) ($staff['storeId'] ?? $staff['store_id'] ?? 1);
        }

        // Boucle : un appel API par exemplaire ajouté
        for ($i = 0; $i < $quantite; $i++) {
            $result = $this->inventoryService->addInventory((int) $filmId, $storeId);

            // Si un exemplaire échoue, on arrête immédiatement et on lève une exception
            if ($result === null) {
                throw new \Exception("Échec de l'ajout de l'exemplaire #{$i} pour le film {$filmId}");
            }
        }

        // Journaliser le succès de l'opération
        Log::info('Stock ajouté via API', [
            'filmId'   => $filmId,
            'quantite' => $quantite,
            'storeId'  => $storeId,
        ]);

        // Retourner le stock mis à jour (le contrôleur peut l'ignorer)
        return $this->getStock($filmId);
    }

    /**
     * Retire N exemplaires disponibles du stock d'un film.
     *
     * Stratégie :
     *  1. Récupérer tous les exemplaires disponibles
     *  2. Vérifier qu'il y en a assez
     *  3. Prendre les N premiers et les supprimer via l'API (DELETE /inventories/{id})
     *
     * @param string $motif  Raison du retrait (casse, perte, vol, obsolète, autre)
     * @throws \Exception    Si stock insuffisant ou si une suppression échoue
     */
    public function retirerStock(int|string $filmId, int $quantite, string $motif): object
    {
        if (!is_numeric($filmId)) {
            throw new \Exception("filmId invalide : {$filmId}");
        }

        // Récupérer uniquement les exemplaires disponibles (pas loués, pas en panier)
        $disponibles = $this->inventoryService->getAvailableInventories((int) $filmId);

        // Vérification métier : impossible de retirer plus qu'il n'y a de disponibles
        if (count($disponibles) < $quantite) {
            throw new \Exception(
                "Stock disponible insuffisant. Disponibles : " . count($disponibles) . ", Demandé : {$quantite}"
            );
        }

        // Prendre les N premiers exemplaires à supprimer (array_slice ne modifie pas l'original)
        $aSupprimer = array_slice($disponibles, 0, $quantite);

        foreach ($aSupprimer as $exemplaire) {
            // L'API peut nommer la clé "inventoryId" ou "inventory_id" selon la version
            $inventoryId = $exemplaire['inventoryId'] ?? $exemplaire['inventory_id'] ?? null;

            if (!$inventoryId) {
                throw new \Exception("ID exemplaire manquant dans la réponse API");
            }

            // Appel API : DELETE /inventories/{inventoryId}
            $result = $this->inventoryService->deleteInventory((int) $inventoryId);

            if (!$result) {
                throw new \Exception("Échec de la suppression de l'exemplaire #{$inventoryId}");
            }
        }

        // Journaliser le motif du retrait (traçabilité)
        Log::info('Stock retiré via API', [
            'filmId'   => $filmId,
            'quantite' => $quantite,
            'motif'    => $motif,
        ]);

        // Retourner le stock mis à jour
        return $this->getStock($filmId);
    }

    /**
     * Retourne une collection vide.
     * Il n'existe pas d'endpoint "tous les stocks" dans l'API Toad.
     */
    public function getAllStocks(): \Illuminate\Support\Collection
    {
        return collect();
    }

    /**
     * Retourne une collection vide.
     * L'historique des mouvements de stock n'est pas disponible sans base locale.
     */
    public function getHistorique(): \Illuminate\Support\Collection
    {
        return collect();
    }
}
