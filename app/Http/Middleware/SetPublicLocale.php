<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['nl', 'en'];
        $locale = (string) $request->route('locale');

        if (! in_array($locale, $supported, true)) {
            $locale = $this->preferredLocale($request, $supported);
        }

        App::setLocale($locale);
        $request->session()->put('public_locale', $locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }

    public static function preferredLocale(Request $request, array $supported = ['nl', 'en']): string
    {
        $sessionLocale = (string) $request->session()->get('public_locale', '');
        if (in_array($sessionLocale, $supported, true)) {
            return $sessionLocale;
        }

        $browserLocale = substr((string) $request->getPreferredLanguage($supported), 0, 2);

        return in_array($browserLocale, $supported, true) ? $browserLocale : 'nl';
    }
}
