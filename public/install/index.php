<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__, 2);
$autoload = $basePath.'/vendor/autoload.php';
$bootstrap = $basePath.'/bootstrap/app.php';
$environmentPath = $basePath.'/.env';

if (!is_file($autoload) || !is_file($bootstrap)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Laravel kon niet worden geladen. Controleer of vendor/ en bootstrap/app.php aanwezig zijn.";
    exit;
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

require $autoload;

/** @var Application $app */
$app = require $bootstrap;

$app->handleRequest(Request::capture());
