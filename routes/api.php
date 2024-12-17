<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LongestWordController;

// Handle preflight OPTIONS requests
Route::options('/{any}', function() {
    return response()->json([], 200);
})->where('any', '.*');

Route::prefix('v1')->group(function () {
    Route::post('/longest-word', [LongestWordController::class, 'store']);
    Route::get('/longest-word', [LongestWordController::class, 'show']);
    Route::get('/longest-word/top', [LongestWordController::class, 'topWords']);
}); 