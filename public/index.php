<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__);
$installedMarker = $basePath.'/storage/app/installed';
$environmentPath = $basePath.'/.env';
$installationLocked = false;

if (is_file($environmentPath)) {
    $environment = (string) file_get_contents($environmentPath);
    $installationLocked = preg_match('/^INSTALLATION_LOCKED\s*=\s*(true|1|yes)\s*$/mi', $environment) === 1;
}

if (!is_file($installedMarker) && !$installationLocked) {
    header('Location: /install/');
    exit;
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
