<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LongestWordController;
use App\Providers\RateLimitingServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        RateLimitingServiceProvider::class,
    ])
    ->withRouting(function () {
        require __DIR__.'/../routes/web.php';
        
        // API Routes with versioning
        Route::prefix('api/v1')->middleware('api')->group(function () {
            Route::middleware(['throttle:60,1', 'cache.headers:public;max_age=60;etag'])->group(function () {
                Route::post('/longest-word', [LongestWordController::class, 'store']);
                Route::get('/longest-word', [LongestWordController::class, 'show']);
            });
        });
        
        require __DIR__.'/../routes/console.php';
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        ]);
        
        $middleware->group('api', [
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        ]);

        $middleware->web([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
