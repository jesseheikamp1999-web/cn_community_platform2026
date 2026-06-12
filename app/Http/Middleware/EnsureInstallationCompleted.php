<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallationCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $installationLocked = (bool) config('platform.installation_locked');

        // The database tables do not exist yet while the installer is active.
        // File-backed sessions and cache keep /install reachable before migrations.
        if (!$installationLocked) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }

        if (!app()->environment('testing') && !$installationLocked && !$request->is('install*')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
