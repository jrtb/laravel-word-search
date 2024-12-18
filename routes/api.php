<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LongestWordController;
use App\Http\Controllers\Api\PlayerSessionController;

// Handle preflight OPTIONS requests
Route::options('/{any}', function() {
    return response()->json([], 200);
})->where('any', '.*');

Route::prefix('v1')->group(function () {
    // Longest word endpoints
    Route::post('/longest-word', [LongestWordController::class, 'store']);
    Route::get('/longest-word', [LongestWordController::class, 'show']);
    Route::get('/longest-word/top', [LongestWordController::class, 'topWords']);

    // Session tracking endpoints
    Route::post('/session', [PlayerSessionController::class, 'store']);
    Route::get('/session/streak', [PlayerSessionController::class, 'getStreak']);
}); 