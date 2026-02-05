<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StockService;
use App\Services\ToadFilmService;

class StockController extends Controller
{
    protected $stockService;
    protected $filmService;

    public function __construct(StockService $stockService, ToadFilmService $filmService)
    {
        $this->middleware('auth');
        $this->stockService = $stockService;
        $this->filmService = $filmService;
    }

    /**
     * Liste des stocks
     */
    public function index(Request $request)
    {
        $stocks = $this->stockService->getAllStocks();
        $films = $this->filmService->getAllFilms();

        // Créer un tableau associatif filmId => film pour faciliter l'accès
        $filmsById = collect($films)->keyBy(function ($film) {
            return $film['filmId'] ?? $film['id'];
        });

        // Enrichir les stocks avec les infos des films
        $stocksEnrichis = $stocks->map(function ($stock) use ($filmsById) {
            $film = $filmsById->get($stock->filmId);
            $stock->film = $film;
            return $stock;
        });

        // Filtrer par recherche si nécessaire
        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $stocksEnrichis = $stocksEnrichis->filter(function ($stock) use ($search) {
                return $stock->film && str_contains(strtolower($stock->film['title'] ?? ''), $search);
            });
        }

        return view('stocks.index', [
            'stocks' => $stocksEnrichis,
            'search' => $request->search ?? '',
        ]);
    }

    /**
     * Afficher l'historique d'un film
     */
    public function show($filmId)
    {
        $stock = $this->stockService->getStock($filmId);
        $film = $this->filmService->getFilmById($filmId);
        $historique = $this->stockService->getHistorique($filmId);

        if (!$stock) {
            return redirect()->route('stocks.index')->with('error', 'Stock non trouvé');
        }

        return view('stocks.show', [
            'stock' => $stock,
            'film' => $film,
            'historique' => $historique,
        ]);
    }

    /**
     * Réception de DVD
     */
    public function reception(Request $request)
    {
        $request->validate([
            'filmId' => 'required|string',
            'quantite' => 'required|integer|min:1',
            'prix_achat' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->stockService->ajouterStock(
                $request->filmId,
                $request->quantite,
                $request->prix_achat,
                $request->notes
            );

            return redirect()->route('stocks.index')->with('success', "{$request->quantite} DVD ajouté(s) au stock");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Erreur: " . $e->getMessage());
        }
    }

    /**
     * Retrait de stock (casse/perte/vol)
     */
    public function retrait(Request $request)
    {
        $request->validate([
            'filmId' => 'required|string',
            'quantite' => 'required|integer|min:1',
            'motif' => 'required|string',
            'description' => 'nullable|string',
        ]);

        try {
            $this->stockService->retirerStock(
                $request->filmId,
                $request->quantite,
                $request->motif,
                $request->description
            );

            return redirect()->route('stocks.index')->with('success', "{$request->quantite} DVD retiré(s) du stock");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Erreur: " . $e->getMessage());
        }
    }

    /**
     * Louer un DVD
     */
    public function louer($filmId)
    {
        try {
            $this->stockService->louerDvd($filmId);
            return redirect()->back()->with('success', 'DVD loué avec succès');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Erreur: " . $e->getMessage());
        }
    }

    /**
     * Retourner un DVD
     */
    public function retourner($filmId)
    {
        try {
            $this->stockService->retournerDvd($filmId);
            return redirect()->back()->with('success', 'DVD retourné avec succès');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Erreur: " . $e->getMessage());
        }
    }
}
