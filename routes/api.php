<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\StockController;

/*
|--------------------------------------------------------------------------
| Inventaires
|--------------------------------------------------------------------------
*/
Route::prefix('inventories')->group(function () {
    Route::get('film/{filmId}',           [InventoryController::class, 'byFilm']);
    Route::get('film/{filmId}/available', [InventoryController::class, 'available']);
    Route::get('film/{filmId}/stock',     [InventoryController::class, 'stock']);
    Route::post('film/{filmId}',          [InventoryController::class, 'store']);
    Route::delete('{id}',                 [InventoryController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Stocks
|--------------------------------------------------------------------------
*/
Route::prefix('stocks')->group(function () {
    Route::get('/',                     [StockController::class, 'index']);
    Route::get('/{filmId}',             [StockController::class, 'show']);
    Route::get('/{filmId}/historique',  [StockController::class, 'historique']);
    Route::get('/{filmId}/coherence',   [StockController::class, 'coherence']);
    Route::post('/reception',           [StockController::class, 'reception']);
    Route::post('/retrait',             [StockController::class, 'retrait']);
});
