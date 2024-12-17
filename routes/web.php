<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WordSearchController;

Route::middleware(['web'])->group(function () {
    Route::get('/', [WordSearchController::class, 'index']);
    Route::post('/search', [WordSearchController::class, 'search'])->name('search');
    Route::post('/search-frequency', [WordSearchController::class, 'searchFrequency'])->name('search.frequency');
});