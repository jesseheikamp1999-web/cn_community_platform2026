<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__, 2);
$autoload = $basePath.'/vendor/autoload.php';
$bootstrap = $basePath.'/bootstrap/app.php';
$environmentPath = $basePath.'/.env';
$bootstrapCachePath = $basePath.'/bootstrap/cache';

if (!function_exists('installerRequirements')) {
    function installerRequirements(string $basePath): array
    {
        return [
            ['label' => 'PHP 8.3+', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'value' => PHP_VERSION],
            ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? 'Geinstalleerd' : 'Ontbreekt'],
            ['label' => 'OpenSSL', 'ok' => extension_loaded('openssl'), 'value' => extension_loaded('openssl') ? 'Geinstalleerd' : 'Ontbreekt'],
            ['label' => 'Mbstring', 'ok' => extension_loaded('mbstring') && function_exists('mb_split'), 'value' => extension_loaded('mbstring') ? 'Geinstalleerd' : 'Ontbreekt'],
            ['label' => 'Storage schrijfbaar', 'ok' => is_dir($basePath.'/storage') && is_writable($basePath.'/storage'), 'value' => is_dir($basePath.'/storage') && is_writable($basePath.'/storage') ? 'Schrijfbaar' : 'Niet schrijfbaar'],
            ['label' => 'Vendor-bestanden', 'ok' => is_file($basePath.'/vendor/autoload.php'), 'value' => is_file($basePath.'/vendor/autoload.php') ? 'Aanwezig' : 'Ontbreken'],
        ];
    }
}

if (!function_exists('renderInstallerFallback')) {
    function renderInstallerFallback(string $title, string $message, array $requirements = []): never
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body{margin:0;background:#f5f7fb;color:#121826;font:16px/1.6 Inter,system-ui,sans-serif}
        .wrap{max-width:900px;margin:48px auto;padding:24px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:24px;box-shadow:0 20px 60px rgba(15,23,42,.08);padding:32px}
        h1{font-size:36px;line-height:1.1;margin:0 0 12px}
        p{margin:0 0 16px;color:#52607a}
        .notice{margin:24px 0;padding:16px 18px;border-radius:16px;background:#fff4f4;border:1px solid #f5c2c7;color:#b42318}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:24px}
        .check{border:1px solid #e5e7eb;border-radius:18px;padding:14px 16px;display:flex;justify-content:space-between;gap:16px;align-items:center}
        .check strong{display:block;font-size:15px}
        .check small{display:block;color:#667085}
        .ok{color:#067647;font-weight:700}
        .fail{color:#b42318;font-weight:700}
        @media (max-width: 700px){.grid{grid-template-columns:1fr}.wrap{margin:24px auto;padding:16px}.card{padding:24px}}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>De Laravel-installer kon nog niet volledig opstarten. Hieronder zie je wat de server op dit moment mist of blokkeert.</p>
            <div class="notice"><?= nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) ?></div>
            <?php if ($requirements !== []): ?>
                <div class="grid">
                    <?php foreach ($requirements as $requirement): ?>
                        <div class="check">
                            <div>
                                <strong><?= htmlspecialchars($requirement['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars((string) $requirement['value'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <span class="<?= $requirement['ok'] ? 'ok' : 'fail' ?>"><?= $requirement['ok'] ? 'OK' : 'FOUT' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

if (!is_file($autoload) || !is_file($bootstrap)) {
    renderInstallerFallback(
        'Installatie niet beschikbaar',
        "Laravel kon niet worden geladen. Controleer of vendor/ en bootstrap/app.php aanwezig zijn.",
        installerRequirements($basePath)
    );
}

if (!function_exists('installerHasAppKey')) {
    function installerHasAppKey(string $environmentPath): bool
    {
        $runtimeKey = getenv('APP_KEY');
        if (is_string($runtimeKey) && trim($runtimeKey) !== '') {
            return true;
        }

        if (!is_file($environmentPath)) {
            return false;
        }

        $environment = (string) file_get_contents($environmentPath);

        return preg_match('/^APP_KEY\s*=\s*(?!\s*$).+/mi', $environment) === 1;
    }
}

if (!installerHasAppKey($environmentPath)) {
    $temporaryKey = 'base64:'.base64_encode(random_bytes(32));
    putenv('APP_KEY='.$temporaryKey);
    $_ENV['APP_KEY'] = $temporaryKey;
    $_SERVER['APP_KEY'] = $temporaryKey;
}

if (!extension_loaded('mbstring') || !function_exists('mb_split')) {
    renderInstallerFallback(
        'Mbstring ontbreekt',
        "De PHP-extensie mbstring is vereist om Laravel te starten.\nSchakel mbstring in voor dit domein in Plesk en laad daarna deze pagina opnieuw.",
        installerRequirements($basePath)
    );
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/install';
$uriParts = parse_url($requestUri) ?: [];
$path = rtrim((string) ($uriParts['path'] ?? '/install'), '/');

if ($path === '' || $path === '/install/index.php') {
    $path = '/install';
}

if (str_starts_with($path, '/install/')) {
    $path = '/install';
}

$_SERVER['REQUEST_URI'] = $path.(isset($uriParts['query']) ? '?'.$uriParts['query'] : '');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

if (is_dir($bootstrapCachePath)) {
    foreach ([
        'config.php',
        'packages.php',
        'services.php',
        'events.php',
        'routes.php',
        'routes-v7.php',
    ] as $cacheFile) {
        $fullPath = $bootstrapCachePath.'/'.$cacheFile;

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    foreach (glob($bootstrapCachePath.'/routes-*.php') ?: [] as $routeCache) {
        if (is_file($routeCache)) {
            @unlink($routeCache);
        }
    }
}

require $autoload;

try {
    /** @var Application $app */
    $app = require $bootstrap;
    $app->handleRequest(Request::capture());
} catch (Throwable $exception) {
    renderInstallerFallback(
        'Installatie voorlopig geblokkeerd',
        $exception->getMessage(),
        installerRequirements($basePath)
    );
}
