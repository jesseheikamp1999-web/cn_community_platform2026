<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (!function_exists('mb_split')) {
    /**
     * Lightweight fallback for hosts where mbstring is present but mb_split is unavailable.
     * Laravel mainly relies on this for internal string helpers during bootstrap.
     */
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        $delimited = '/'.str_replace('/', '\/', $pattern).'/u';

        $result = preg_split($delimited, $string, $limit);

        return $result === false ? false : $result;
    }
}

if (!function_exists('envData')) {
    function envData(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[trim($key)] = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }

        return $values;
    }
}

if (!function_exists('resolveEnvironmentPath')) {
    function resolveEnvironmentPath(string $basePath): string
    {
        $candidates = [
            $basePath.'/.env',
            dirname($basePath).'/.env',
            dirname($basePath, 2).'/.env',
            dirname($basePath, 3).'/.env',
            $basePath.'/public/.env',
        ];

        $fallback = $candidates[0];
        $bestPath = $fallback;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $environment = envData($candidate);
            $score = 0;

            foreach (['APP_NAME', 'APP_ENV', 'APP_KEY', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
                if (!empty($environment[$key])) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPath = $candidate;
            }
        }

        return $bestPath;
    }
}

if (!function_exists('syncEnvironmentToRuntime')) {
    function syncEnvironmentToRuntime(array $environment): void
    {
        foreach ($environment as $key => $value) {
            $value = (string) $value;

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$basePath = dirname(__DIR__);
$installedMarker = $basePath.'/storage/app/installed';
$environmentPath = resolveEnvironmentPath($basePath);
$installationLocked = false;
$environmentValues = envData($environmentPath);

if ($environmentValues !== []) {
    syncEnvironmentToRuntime($environmentValues);
    $installationLocked = filter_var($environmentValues['INSTALLATION_LOCKED'] ?? false, FILTER_VALIDATE_BOOLEAN);
}

if (!is_file($installedMarker) && !$installationLocked) {
    header('Location: /install/');
    exit;
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

try {
    require __DIR__.'/../vendor/autoload.php';

    /** @var Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';

    $app->handleRequest(Request::capture());
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Connect Next kan nog niet opstarten</title>
    <style>
        body{margin:0;background:#eef3fb;color:#111827;font:16px/1.6 Inter,system-ui,sans-serif}
        .wrap{max-width:980px;margin:56px auto;padding:24px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:28px;box-shadow:0 30px 80px rgba(15,23,42,.08);padding:34px}
        h1{font-size:40px;line-height:1.08;margin:0 0 10px}
        p{margin:0 0 18px;color:#52607a}
        .notice{margin:22px 0;padding:16px 18px;border-radius:18px;border:1px solid;background:#fff5f5;border-color:#f9c0c0;color:#c62a2a}
        code{display:block;white-space:pre-wrap;word-break:break-word;font:14px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace}
        .actions{display:flex;gap:12px;flex-wrap:wrap}
        a.button{display:inline-flex;align-items:center;justify-content:center;padding:14px 20px;border-radius:16px;background:#d71920;color:#fff;font-weight:800;text-decoration:none;box-shadow:0 16px 32px rgba(215,25,32,.22)}
        a.button.secondary{background:#111827;box-shadow:none}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Connect Next kon nog niet opstarten</h1>
            <p>De installatie is afgerond, maar de normale Laravel-app geeft nog een fout tijdens het laden.</p>
            <div class="notice">
                <strong>Melding:</strong>
                <code><?= htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div class="actions">
                <a class="button" href="/install/">Terug naar install</a>
                <a class="button secondary" href="/nl">Opnieuw proberen</a>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
}
