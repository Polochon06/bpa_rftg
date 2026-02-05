<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\Auth\LogoutController;

// Page d'accueil
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Routes d'authentification
Auth::routes(['logout' => false]); // Désactive la route de logout par défaut
Route::post('logout', LogoutController::class)->name('logout')->middleware('auth');

// Dashboard et Home
Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
Route::get('/home', [HomeController::class, 'index'])->name('home');

// Routes films protégées par authentification
Route::middleware(['auth'])->group(function () {
    Route::get('/films', [FilmController::class, 'index'])->name('films.index');
    Route::get('/films/create', [FilmController::class, 'create'])->name('films.create');
    Route::post('/films', [FilmController::class, 'store'])->name('films.store');
    Route::get('/films/{id}', [FilmController::class, 'show'])->name('films.show');
    Route::get('/films/{id}/edit', [FilmController::class, 'edit'])->name('films.edit');
    Route::put('/films/{id}', [FilmController::class, 'update'])->name('films.update');
    Route::delete('/films/{id}', [FilmController::class, 'destroy'])->name('films.destroy');

    // Routes stocks
    Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
    Route::get('/stocks/{filmId}', [StockController::class, 'show'])->name('stocks.show');
    Route::post('/stocks/reception', [StockController::class, 'reception'])->name('stocks.reception');
    Route::post('/stocks/retrait', [StockController::class, 'retrait'])->name('stocks.retrait');
    Route::post('/stocks/{filmId}/louer', [StockController::class, 'louer'])->name('stocks.louer');
    Route::post('/stocks/{filmId}/retourner', [StockController::class, 'retourner'])->name('stocks.retourner');
});
