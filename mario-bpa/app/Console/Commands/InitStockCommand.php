<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ToadFilmService;
use App\Services\StockService;

class InitStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialise le stock pour tous les films existants';

    protected $filmService;
    protected $stockService;

    public function __construct(ToadFilmService $filmService, StockService $stockService)
    {
        parent::__construct();
        $this->filmService = $filmService;
        $this->stockService = $stockService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initialisation du stock pour tous les films...');

        try {
            // Récupérer tous les films
            $films = $this->filmService->getAllFilms();

            if (empty($films)) {
                $this->warn('Aucun film trouvé.');
                return 0;
            }

            $this->info('Nombre de films trouvés : ' . count($films));

            $bar = $this->output->createProgressBar(count($films));
            $bar->start();

            $created = 0;
            $existing = 0;

            foreach ($films as $film) {
                $filmId = $film['filmId'] ?? $film['id'] ?? null;

                if (!$filmId) {
                    continue;
                }

                // Vérifier si le stock existe déjà
                $stock = $this->stockService->getStock($filmId);

                if (!$stock) {
                    // Créer le stock avec quantité 0
                    $this->stockService->initialiserStock($filmId);
                    $created++;
                } else {
                    $existing++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✓ Stock initialisé avec succès !");
            $this->info("  - Stocks créés : {$created}");
            $this->info("  - Stocks existants : {$existing}");

            return 0;

        } catch (\Exception $e) {
            $this->error('Erreur lors de l\'initialisation : ' . $e->getMessage());
            return 1;
        }
    }
}
