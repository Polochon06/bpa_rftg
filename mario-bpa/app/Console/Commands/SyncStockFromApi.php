<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ToadFilmService;
use App\Services\StockService;
use App\Services\ToadInventoryService;
use App\Models\DvdStock;

class SyncStockFromApi extends Command
{
    protected $signature = 'stock:sync';
    protected $description = 'Synchronise les stocks depuis l\'API Toad/Peach (inventaires et locations)';

    protected $filmService;
    protected $stockService;
    protected $inventoryService;

    public function __construct(
        ToadFilmService $filmService,
        StockService $stockService,
        ToadInventoryService $inventoryService
    ) {
        parent::__construct();
        $this->filmService = $filmService;
        $this->stockService = $stockService;
        $this->inventoryService = $inventoryService;
    }

    public function handle()
    {
        $this->info('🔄 Synchronisation des stocks depuis l\'API...');

        try {
            $films = $this->filmService->getAllFilms();

            if (empty($films)) {
                $this->warn('Aucun film trouvé.');
                return 0;
            }

            $this->info('Nombre de films trouvés : ' . count($films));

            $bar = $this->output->createProgressBar(count($films));
            $bar->start();

            $synced = 0;
            $created = 0;
            $errors = 0;

            foreach ($films as $film) {
                $filmId = $film['filmId'] ?? $film['id'] ?? null;

                if (!$filmId) {
                    $bar->advance();
                    continue;
                }

                try {
                    // Récupérer l'inventaire réel depuis l'API
                    $inventory = $this->inventoryService->getFilmInventory($filmId);

                    // Créer ou mettre à jour le stock local
                    $stock = DvdStock::firstOrCreate(
                        ['filmId' => $filmId],
                        [
                            'quantite_totale' => 0,
                            'quantite_disponible' => 0,
                            'quantite_louee' => 0,
                            'prix_location' => $film['rentalRate'] ?? 3.99,
                        ]
                    );

                    $wasNew = $stock->wasRecentlyCreated;

                    $stock->quantite_totale = $inventory['total'];
                    $stock->quantite_disponible = $inventory['available'];
                    $stock->quantite_louee = $inventory['rented'];

                    if (isset($film['rentalRate'])) {
                        $stock->prix_location = $film['rentalRate'];
                    }

                    $stock->save();

                    if ($wasNew) {
                        $created++;
                    } else {
                        $synced++;
                    }

                } catch (\Exception $e) {
                    $this->error("\nErreur pour film {$filmId}: " . $e->getMessage());
                    $errors++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ Synchronisation terminée !");
            $this->table(
                ['Type', 'Nombre'],
                [
                    ['Stocks synchronisés', $synced],
                    ['Stocks créés', $created],
                    ['Erreurs', $errors],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('Erreur lors de la synchronisation : ' . $e->getMessage());
            return 1;
        }
    }
}
