<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

session_name('cn_installer');
session_start();

$basePath = dirname(__DIR__, 2);
$autoload = $basePath.'/vendor/autoload.php';
$bootstrap = $basePath.'/bootstrap/app.php';
$examplePath = $basePath.'/.env.example';
$installedMarker = $basePath.'/storage/app/installed';
$bootstrapCachePath = $basePath.'/bootstrap/cache';
$csrf = $_SESSION['installer_csrf'] ??= bin2hex(random_bytes(24));

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

        foreach (['APP_NAME', 'APP_ENV', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
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

function envQuote(string $value): string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_:\/.@+\-]+$/', $value)) {
        return $value;
    }

    return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

function ensureEnvironmentFile(string $path, string $examplePath): void
{
    if (is_file($path)) {
        return;
    }

    if (!is_file($examplePath) || !copy($examplePath, $path)) {
        throw new RuntimeException('Het .env-bestand kon niet worden aangemaakt.');
    }
}

function writeEnvironment(string $path, string $examplePath, array $updates): void
{
    ensureEnvironmentFile($path, $examplePath);

    $contents = (string) file_get_contents($path);

    foreach ($updates as $key => $value) {
        $line = $key.'='.envQuote((string) $value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            $contents = (string) preg_replace($pattern, $line, $contents);
        } else {
            $contents .= PHP_EOL.$line;
        }
    }

    if (file_put_contents($path, rtrim($contents).PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Het .env-bestand is niet schrijfbaar.');
    }
}

function hasAppKey(array $environment): bool
{
    return isset($environment['APP_KEY']) && trim((string) $environment['APP_KEY']) !== '';
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Map kon niet worden aangemaakt: '.$path);
    }

    if (!is_writable($path)) {
        @chmod($path, 0775);
    }

    if (!is_writable($path)) {
        throw new RuntimeException('De map is niet schrijfbaar: '.$path);
    }
}

function ensureRuntimeDirectories(string $basePath, string $bootstrapCachePath): void
{
    $directories = [
        $bootstrapCachePath,
        $basePath.'/storage',
        $basePath.'/storage/app',
        $basePath.'/storage/framework',
        $basePath.'/storage/framework/cache',
        $basePath.'/storage/framework/cache/data',
        $basePath.'/storage/framework/sessions',
        $basePath.'/storage/framework/views',
        $basePath.'/storage/logs',
    ];

    foreach ($directories as $directory) {
        ensureDirectory($directory);
    }
}

function syncEnvironmentToRuntime(array $environment): void
{
    $keys = [
        'APP_NAME',
        'APP_ENV',
        'APP_DEBUG',
        'APP_URL',
        'APP_KEY',
        'APP_LOCALE',
        'APP_FALLBACK_LOCALE',
        'APP_TIMEZONE',
        'LOG_CHANNEL',
        'LOG_LEVEL',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_SOCKET',
        'CACHE_STORE',
        'QUEUE_CONNECTION',
        'SESSION_DRIVER',
        'SESSION_COOKIE',
        'SESSION_LIFETIME',
        'INSTALLATION_LOCKED',
    ];

    foreach ($keys as $key) {
        if (!array_key_exists($key, $environment)) {
            continue;
        }

        $value = (string) $environment[$key];

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function testDatabase(array $environment, ?string &$message = null): bool
{
    if (!extension_loaded('pdo_mysql')) {
        $message = 'PDO MySQL ontbreekt.';

        return false;
    }

    $host = $environment['DB_HOST'] ?? '';
    $database = $environment['DB_DATABASE'] ?? '';
    $username = $environment['DB_USERNAME'] ?? '';

    if ($host === '' || $database === '' || $username === '') {
        $message = 'Database-instellingen zijn nog niet volledig ingevuld in .env.';

        return false;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $environment['DB_PORT'] ?? '3306',
            $database
        );

        new PDO(
            $dsn,
            $username,
            $environment['DB_PASSWORD'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );

        $message = 'Databaseverbinding geslaagd.';

        return true;
    } catch (Throwable $exception) {
        $message = $exception->getMessage();

        return false;
    }
}

function installerRequirements(string $basePath, array $environment, bool $databaseOk): array
{
    $bootstrapCachePath = $basePath.'/bootstrap/cache';

    return [
        ['label' => 'PHP 8.3+', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'value' => PHP_VERSION],
        ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? 'Geinstalleerd' : 'Ontbreekt'],
        ['label' => 'OpenSSL', 'ok' => extension_loaded('openssl'), 'value' => extension_loaded('openssl') ? 'Geinstalleerd' : 'Ontbreekt'],
        ['label' => 'Mbstring', 'ok' => extension_loaded('mbstring') && function_exists('mb_split'), 'value' => extension_loaded('mbstring') ? 'Geinstalleerd' : 'Ontbreekt'],
        ['label' => 'Storage schrijfbaar', 'ok' => is_dir($basePath.'/storage') && is_writable($basePath.'/storage'), 'value' => is_dir($basePath.'/storage') && is_writable($basePath.'/storage') ? 'Schrijfbaar' : 'Niet schrijfbaar'],
        ['label' => 'Bootstrap cache', 'ok' => is_dir($bootstrapCachePath) && is_writable($bootstrapCachePath), 'value' => is_dir($bootstrapCachePath) && is_writable($bootstrapCachePath) ? 'Schrijfbaar' : 'Ontbreekt of niet schrijfbaar'],
        ['label' => 'Vendor-bestanden', 'ok' => is_file($basePath.'/vendor/autoload.php'), 'value' => is_file($basePath.'/vendor/autoload.php') ? 'Aanwezig' : 'Ontbreken'],
        ['label' => 'Applicatiesleutel', 'ok' => hasAppKey($environment), 'value' => hasAppKey($environment) ? 'Ingesteld' : 'Wordt automatisch aangemaakt'],
        ['label' => 'Database bereikbaar', 'ok' => $databaseOk, 'value' => $databaseOk ? 'Verbonden' : 'Niet bereikbaar'],
    ];
}

function clearBootstrapCaches(string $bootstrapCachePath): void
{
    if (!is_dir($bootstrapCachePath)) {
        return;
    }

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

function renderPage(
    string $title,
    string $lead,
    array $requirements,
    ?string $notice = null,
    bool $success = false,
    ?string $databaseMessage = null,
    bool $showForm = true,
    string $csrf = '',
    ?string $environmentPath = null
): never {
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
        body{margin:0;background:#eef3fb;color:#111827;font:16px/1.6 Inter,system-ui,sans-serif}
        .wrap{max-width:980px;margin:56px auto;padding:24px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:28px;box-shadow:0 30px 80px rgba(15,23,42,.08);padding:34px}
        h1{font-size:40px;line-height:1.08;margin:0 0 10px}
        p{margin:0 0 18px;color:#52607a}
        .notice{margin:22px 0;padding:16px 18px;border-radius:18px;border:1px solid}
        .notice.error{background:#fff5f5;border-color:#f9c0c0;color:#c62a2a}
        .notice.success{background:#eefcf3;border-color:#b8edc7;color:#067647}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:24px}
        .check{border:1px solid #e5e7eb;border-radius:20px;padding:16px 18px;display:flex;justify-content:space-between;align-items:center;gap:16px}
        .check strong{display:block;font-size:15px}
        .check small{display:block;color:#667085}
        .ok{color:#067647;font-weight:800}
        .fail{color:#c62a2a;font-weight:800}
        .db-note{margin-top:18px;font-size:13px;color:#667085}
        form{margin-top:28px}
        label{display:flex;align-items:flex-start;gap:12px;font-weight:600;color:#1f2937}
        input[type=checkbox]{margin-top:4px}
        button,a.button{display:inline-flex;align-items:center;justify-content:center;margin-top:18px;padding:14px 20px;border:none;border-radius:16px;background:#d71920;color:#fff;font-weight:800;text-decoration:none;cursor:pointer;box-shadow:0 16px 32px rgba(215,25,32,.22)}
        a.button.secondary{background:#111827;box-shadow:none}
        .actions{display:flex;gap:12px;flex-wrap:wrap}
        @media (max-width: 720px){.grid{grid-template-columns:1fr}.wrap{padding:16px;margin:28px auto}.card{padding:24px}h1{font-size:32px}}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p>

            <?php if ($notice !== null): ?>
                <div class="notice <?= $success ? 'success' : 'error' ?>"><?= nl2br(htmlspecialchars($notice, ENT_QUOTES, 'UTF-8')) ?></div>
            <?php endif; ?>

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

            <?php if ($databaseMessage !== null): ?>
                <div class="db-note"><strong>Databasecontrole:</strong> <?= htmlspecialchars($databaseMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($environmentPath !== null): ?>
                <div class="db-note"><strong>Actief .env pad:</strong> <?= htmlspecialchars($environmentPath, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($showForm): ?>
                <form method="post" action="">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <label>
                        <input type="checkbox" name="confirm" value="1" required>
                        Ik heb `.env` ingevuld en een lege database aangemaakt. CN mag nu de tabellen en basisdata installeren.
                    </label>
                    <div class="actions">
                        <button type="submit">Nu installeren -></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="actions">
                    <a class="button" href="/auth/discord">Inloggen met Discord -></a>
                    <a class="button secondary" href="/nl">Naar het platform</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

$environmentPath = resolveEnvironmentPath($basePath);
$environment = envData($environmentPath);
$isInstalled = is_file($installedMarker) || filter_var($environment['INSTALLATION_LOCKED'] ?? false, FILTER_VALIDATE_BOOLEAN);
$databaseMessage = null;
$databaseOk = testDatabase($environment, $databaseMessage);
$requirements = installerRequirements($basePath, $environment, $databaseOk);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($isInstalled) {
            throw new RuntimeException('CN Community is al geinstalleerd.');
        }

        if (!hash_equals($csrf, (string) ($_POST['_token'] ?? ''))) {
            throw new RuntimeException('De installatiesessie is verlopen. Vernieuw de pagina en probeer opnieuw.');
        }

        if (($_POST['confirm'] ?? null) !== '1') {
            throw new RuntimeException('Bevestig eerst dat .env en de database klaarstaan.');
        }

        if (!$databaseOk) {
            throw new RuntimeException('De databaseverbinding werkt nog niet. Controleer eerst de DB_* waarden in .env.');
        }

        ensureEnvironmentFile($environmentPath, $examplePath);
        $environment = envData($environmentPath);

        if (!hasAppKey($environment)) {
            $generatedKey = 'base64:'.base64_encode(random_bytes(32));
            writeEnvironment($environmentPath, $examplePath, ['APP_KEY' => $generatedKey]);
            $environment['APP_KEY'] = $generatedKey;
        }

        writeEnvironment($environmentPath, $examplePath, ['INSTALLATION_LOCKED' => 'false']);
        $environment['INSTALLATION_LOCKED'] = 'false';

        syncEnvironmentToRuntime($environment);

        ensureRuntimeDirectories($basePath, $bootstrapCachePath);
        clearBootstrapCaches($bootstrapCachePath);

        if (!is_file($autoload) || !is_file($bootstrap)) {
            throw new RuntimeException('Laravel kon niet worden geladen. Controleer vendor/ en bootstrap/app.php.');
        }

        require_once $autoload;

        /** @var \Illuminate\Foundation\Application $app */
        $app = require $bootstrap;

        /** @var ConsoleKernel $kernel */
        $kernel = $app->make(ConsoleKernel::class);
        $kernel->bootstrap();
        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', ['--class' => 'Database\\Seeders\\ProductionSeeder', '--force' => true]);
        $kernel->call('storage:link');
        $kernel->call('optimize:clear');

        writeEnvironment($environmentPath, $examplePath, ['INSTALLATION_LOCKED' => 'true']);

        if (!is_dir(dirname($installedMarker))) {
            mkdir(dirname($installedMarker), 0775, true);
        }

        file_put_contents($installedMarker, date(DATE_ATOM));

        $environment = envData($environmentPath);
        $requirements = installerRequirements($basePath, $environment, true);

        renderPage(
            'Installatie voltooid',
            'CN Community is succesvol geinstalleerd. Je kunt nu inloggen met Discord.',
            $requirements,
            'De database, basisdata en storage-link zijn succesvol aangemaakt.',
            true,
            'Databaseverbinding geslaagd.',
            false
            ,
            '',
            $environmentPath
        );
    } catch (Throwable $exception) {
        $environment = envData($environmentPath);
        $databaseOk = testDatabase($environment, $databaseMessage);
        $requirements = installerRequirements($basePath, $environment, $databaseOk);

        renderPage(
            'Installatie voorlopig geblokkeerd',
            'De zelfstandige installer kon de setup nog niet afronden. Controleer de melding hieronder.',
            $requirements,
            $exception->getMessage(),
            false,
            $databaseMessage,
            true,
            $csrf,
            $environmentPath
        );
    }
}

if ($isInstalled) {
    renderPage(
        'Installatie voltooid',
        'CN Community staat al als geinstalleerd gemarkeerd.',
        $requirements,
        'De installer is afgesloten omdat het platform al geinstalleerd is.',
        true,
        $databaseMessage,
        false,
        '',
        $environmentPath
    );
}

renderPage(
    'CN Community installeren',
    'Deze pagina installeert het platform zonder afhankelijk te zijn van Laravel Blade of de web-kernel.',
    $requirements,
    null,
    false,
    $databaseMessage,
    true,
    $csrf,
    $environmentPath
);
