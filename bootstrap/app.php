<?php

use App\Http\Middleware\EnsureInstallationCompleted;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\ForceCanonicalDomain;
use App\Http\Middleware\LogUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ForceCanonicalDomain::class);
        $middleware->append(EnsureInstallationCompleted::class);
        $middleware->web(append: [LogUserActivity::class]);
        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
