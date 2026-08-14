<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/pos.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        $middleware->alias([
            'dashboard.auth' => \App\Http\Middleware\AuthenticateDashboard::class,
            'pos.auth' => \App\Http\Middleware\AuthenticatePos::class,
            'pos.shift' => \App\Http\Middleware\RequireOpenShift::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'can.do' => \App\Http\Middleware\EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

/*
 * The app is served straight from the project root (no /public folder), so
 * public_path() must resolve there. This keeps asset(), the uploads disk and
 * `artisan serve` all pointing at the same directory.
 */
$app->usePublicPath(dirname(__DIR__));

return $app;
