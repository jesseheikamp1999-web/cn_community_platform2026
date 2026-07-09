<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class InstallController extends Controller
{
    public function index()
    {
        abort_if(config('platform.installation_locked'), 404);

        $databaseAvailable = false;
        $databaseMessage = null;

        try {
            DB::connection()->getPdo();
            $databaseAvailable = true;
        } catch (Throwable $exception) {
            $databaseMessage = $exception->getMessage();
        }

        return view('pages.install', [
            'checks' => [
                'PHP 8.3+' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'PDO MySQL' => extension_loaded('pdo_mysql'),
                'OpenSSL' => extension_loaded('openssl'),
                'Storage schrijfbaar' => is_writable(storage_path()),
                'Applicatiesleutel ingesteld' => filled(config('app.key')),
                'Database bereikbaar' => $databaseAvailable,
            ],
            'databaseMessage' => $databaseMessage,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(config('platform.installation_locked'), 404);
        $request->validate(['confirm' => ['accepted']]);

        try {
            DB::connection()->getPdo();
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
        } catch (Throwable $exception) {
            return back()->withErrors(['install' => $exception->getMessage()]);
        }

        return view('pages.install-complete');
    }
}
