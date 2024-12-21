<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WordSearchController;
use App\Http\Controllers\PlayerStatsController;

Route::middleware(['web'])->group(function () {
    // Word Search Routes
    Route::get('/', [WordSearchController::class, 'index'])->name('word.search');
    Route::post('/search', [WordSearchController::class, 'search'])->name('search');
    Route::post('/search-frequency', [WordSearchController::class, 'searchFrequency'])->name('search.frequency');

    // Player Stats Routes
    Route::get('/player-stats', [PlayerStatsController::class, 'index'])->name('player.stats');
    Route::get('/longest-word/top', [App\Http\Controllers\Api\LongestWordController::class, 'topWords'])->name('longest-word.top');
});