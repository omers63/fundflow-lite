<?php

use App\Routing\Router as AppRouter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\NormalizeDisplayFormatting;
use App\Http\Middleware\SetLocale;

/** @var Application $app */
$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            NormalizeDisplayFormatting::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Register before any service provider runs (Filament, Livewire, etc.): UTF-8-safe
// JsonResponse in Router::toResponse — without resetting the router singleton (that caused 404s).
$app->extend('router', function ($router, Application $application): AppRouter {
    return new AppRouter($application['events'], $application);
});

return $app;
