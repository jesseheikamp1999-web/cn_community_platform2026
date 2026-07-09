<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $locale = 'nl';

        if (! $this->app->runningInConsole()) {
            $request = request();

            if ($request->route('locale')) {
                $locale = $request->route('locale');
            } elseif (!config('platform.installation_locked') && $request->is('install*')) {
                $locale = 'nl';
            } else {
                try {
                    $locale = session('public_locale')
                        ?? App::currentLocale()
                        ?? 'nl';
                } catch (Throwable) {
                    $locale = App::currentLocale() ?? 'nl';
                }
            }
        }

        URL::defaults(['locale' => $locale]);
    }
}
