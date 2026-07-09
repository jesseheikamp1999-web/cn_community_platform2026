<?php

if (!function_exists('mb_split')) {
    /**
     * Fallback for hosting environments where mbstring is enabled
     * but the mb_split helper itself is unavailable.
     */
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        $delimited = '/'.str_replace('/', '\/', $pattern).'/u';

        $result = preg_split($delimited, $string, $limit);

        return $result === false ? false : $result;
    }
}

use App\Http\Middleware\EnsureInstallationCompleted;
use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\EnsureUserHasPermission;
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
        $middleware->append(EnsureInstallationCompleted::class);
        $middleware->web(append: [LogUserActivity::class]);
        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'public.locale' => SetPublicLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
