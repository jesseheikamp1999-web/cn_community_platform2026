<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->isMethod('GET')) {
            $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        if ($request->user() && !$request->isMethod('GET')) {
            DB::table('activity_logs')->insert([
                'user_id' => $request->user()->id,
                'event' => strtolower($request->method()).':'.$request->route()?->getName(),
                'properties' => json_encode(['path' => $request->path()]),
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $response;
    }
}
