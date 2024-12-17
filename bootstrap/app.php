<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withMiddleware(function (Middleware $middleware) {
        // Core middleware aliases
        $middleware->alias([
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'cors' => \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Web middleware group
        $middleware->web([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            'bindings',
        ]);

        // API middleware group
        $middleware->api([
            'cors',
            \Illuminate\Session\Middleware\StartSession::class,
            'bindings',
        ]);

        // Global middleware
        $middleware->use([
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
    })
    ->withRouting(function () {
        // Web routes
        Route::middleware('web')
            ->group(base_path('routes/web.php'));

        // API routes
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API exception handling
        $exceptions->render(function (Request $request, \Throwable $e) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                        ? $e->getStatusCode()
                        : 500
                ], $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $e->getStatusCode()
                    : 500);
            }
        });
    })
    ->create();
