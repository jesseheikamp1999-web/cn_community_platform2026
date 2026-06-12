<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceCanonicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($canonicalHost && $request->getHost() !== $canonicalHost) {
            return redirect()->to(
                $request->getScheme().'://'.$canonicalHost.$request->getRequestUri(),
                301
            );
        }

        return $next($request);
    }
}
