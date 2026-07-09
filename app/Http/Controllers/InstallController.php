<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
                'Bootstrap cache beschikbaar' => $this->bootstrapCacheReady(),
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
            if (! $this->bootstrapCacheReady()) {
                throw new \RuntimeException('Bootstrap cache is niet beschikbaar voor Laravel.');
            }

            DB::connection()->getPdo();
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
        } catch (Throwable $exception) {
            return back()->withErrors(['install' => $exception->getMessage()]);
        }

        return view('pages.install-complete');
    }

    private function bootstrapCacheReady(): bool
    {
        $path = base_path('bootstrap/cache');

        try {
            if (! File::exists($path)) {
                File::ensureDirectoryExists($path);
            }

            if (! is_dir($path)) {
                return false;
            }

            if ($this->canWriteToBootstrapCache($path)) {
                return true;
            }

            return $this->hasCompiledBootstrapCache($path);
        } catch (Throwable) {
            return false;
        }
    }

    private function canWriteToBootstrapCache(string $path): bool
    {
        if (! is_writable($path)) {
            return false;
        }

        $probe = $path.DIRECTORY_SEPARATOR.'.write-test-'.uniqid('', true);
        $written = @file_put_contents($probe, 'ok');

        if ($written === false) {
            return false;
        }

        @unlink($probe);

        return true;
    }

    private function hasCompiledBootstrapCache(string $path): bool
    {
        $requiredFiles = [
            $path.DIRECTORY_SEPARATOR.'packages.php',
            $path.DIRECTORY_SEPARATOR.'services.php',
        ];

        foreach ($requiredFiles as $file) {
            if (! File::exists($file) || ! is_readable($file)) {
                return false;
            }
        }

        return true;
    }
}
