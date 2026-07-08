<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
            $locale = request()->route('locale')
                ?? session('public_locale')
                ?? App::currentLocale()
                ?? 'nl';
        }

        URL::defaults(['locale' => $locale]);
    }
}
