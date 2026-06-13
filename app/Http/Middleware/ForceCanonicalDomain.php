<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceCanonicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = (string) config('app.url');
        $canonicalHost = parse_url($appUrl, PHP_URL_HOST);

        if ($canonicalHost && $request->getHost() !== $canonicalHost) {
            $canonicalScheme = parse_url($appUrl, PHP_URL_SCHEME) ?: $request->getScheme();
            $isDiscordCallback = $request->is('auth/discord/callback');
            $target = $isDiscordCallback ? '/auth/discord' : $request->getRequestUri();

            return redirect()->to(
                $canonicalScheme.'://'.$canonicalHost.$target,
                $isDiscordCallback ? 302 : 301
            );
        }

        return $next($request);
    }
}
