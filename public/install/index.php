<?php

declare(strict_types=1);

session_name('cn_installer');
session_start();

$basePath = dirname(__DIR__, 2);
$envPath = $basePath.'/.env';
$examplePath = $basePath.'/.env.example';
$installedMarker = $basePath.'/storage/app/installed';

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
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $values[trim($key)] = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }

    return $values;
}

function envQuote(string $value): string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_:\/.@+-]+$/', $value)) {
        return $value;
    }

    return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

function writeEnvironment(string $path, string $examplePath, array $updates): void
{
    if (!is_file($path)) {
        if (!is_file($examplePath) || !copy($examplePath, $path)) {
            throw new RuntimeException('Het .env-bestand kon niet worden aangemaakt.');
        }
    }

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

function redirectStep(int $step): never
{
    header('Location: ./?step='.$step);
    exit;
}

function dbConnection(array $values): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $values['DB_HOST'] ?? '127.0.0.1',
        $values['DB_PORT'] ?? '3306',
        $values['DB_DATABASE'] ?? ''
    );

    return new PDO($dsn, $values['DB_USERNAME'] ?? '', $values['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function requirements(string $basePath): array
{
    return [
        ['PHP-versie', PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>=')],
        ['PDO MySQL', extension_loaded('pdo_mysql') ? 'Geïnstalleerd' : 'Ontbreekt', extension_loaded('pdo_mysql')],
        ['OpenSSL', extension_loaded('openssl') ? 'Geïnstalleerd' : 'Ontbreekt', extension_loaded('openssl')],
        ['Fileinfo', extension_loaded('fileinfo') ? 'Geïnstalleerd' : 'Ontbreekt', extension_loaded('fileinfo')],
        ['Mbstring', extension_loaded('mbstring') ? 'Geïnstalleerd' : 'Ontbreekt', extension_loaded('mbstring')],
        ['cURL', extension_loaded('curl') ? 'Geïnstalleerd' : 'Ontbreekt', extension_loaded('curl')],
        ['Storage schrijfbaar', is_writable($basePath.'/storage') ? 'Schrijfbaar' : 'Niet schrijfbaar', is_writable($basePath.'/storage')],
        ['Bootstrap cache', is_writable($basePath.'/bootstrap/cache') ? 'Schrijfbaar' : 'Niet schrijfbaar', is_writable($basePath.'/bootstrap/cache')],
        ['Vendor-bestanden', is_file($basePath.'/vendor/autoload.php') ? 'Aanwezig' : 'Ontbreken', is_file($basePath.'/vendor/autoload.php')],
    ];
}

$env = envData($envPath);
$isInstalled = is_file($installedMarker) || filter_var($env['INSTALLATION_LOCKED'] ?? false, FILTER_VALIDATE_BOOLEAN);
$step = $isInstalled ? 7 : max(1, min(7, (int) ($_GET['step'] ?? 1)));
$error = null;
$success = null;
$csrf = $_SESSION['installer_csrf'] ??= bin2hex(random_bytes(24));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($isInstalled) {
            throw new RuntimeException('CN Community is al geïnstalleerd.');
        }
        if (!hash_equals($csrf, (string) ($_POST['_token'] ?? ''))) {
            throw new RuntimeException('De beveiligingssessie is verlopen. Vernieuw de pagina.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'database') {
            $updates = [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => trim((string) $_POST['db_host']),
                'DB_PORT' => trim((string) $_POST['db_port']),
                'DB_DATABASE' => trim((string) $_POST['db_database']),
                'DB_USERNAME' => trim((string) $_POST['db_username']),
            ];
            $password = (string) ($_POST['db_password'] ?? '');
            $updates['DB_PASSWORD'] = $password !== '' ? $password : ($env['DB_PASSWORD'] ?? '');
            dbConnection($updates);
            writeEnvironment($envPath, $examplePath, $updates);
            $_SESSION['database_ok'] = true;
            redirectStep(4);
        }

        if ($action === 'environment') {
            $appUrl = rtrim(trim((string) $_POST['app_url']), '/');
            if (!filter_var($appUrl, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Vul een geldige website-URL in, inclusief https://.');
            }
            writeEnvironment($envPath, $examplePath, [
                'APP_NAME' => trim((string) $_POST['app_name']),
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $appUrl,
                'APP_TIMEZONE' => (string) $_POST['timezone'],
                'APP_LOCALE' => 'nl',
                'APP_KEY' => !empty($env['APP_KEY']) ? $env['APP_KEY'] : ('base64:'.base64_encode(random_bytes(32))),
                'SESSION_DRIVER' => 'file',
                'CACHE_STORE' => 'file',
                'QUEUE_CONNECTION' => 'sync',
                'INSTALLATION_LOCKED' => 'false',
            ]);
            redirectStep(5);
        }

        if ($action === 'discord') {
            $clientId = trim((string) $_POST['discord_client_id']);
            $guildId = trim((string) $_POST['discord_guild_id']);
            if ($clientId === '' || $guildId === '') {
                throw new RuntimeException('Discord Client ID en Guild ID zijn verplicht.');
            }
            $current = envData($envPath);
            $appUrl = rtrim($current['APP_URL'] ?? '', '/');
            $updates = [
                'DISCORD_CLIENT_ID' => $clientId,
                'DISCORD_CLIENT_SECRET' => (string) (($_POST['discord_client_secret'] ?? '') ?: ($current['DISCORD_CLIENT_SECRET'] ?? '')),
                'DISCORD_REDIRECT_URI' => $appUrl.'/auth/discord/callback',
                'DISCORD_BOT_TOKEN' => (string) (($_POST['discord_bot_token'] ?? '') ?: ($current['DISCORD_BOT_TOKEN'] ?? '')),
                'DISCORD_GUILD_ID' => $guildId,
                'DISCORD_ROLE_MEMBER' => trim((string) $_POST['discord_role_member']),
                'DISCORD_ROLE_HELPER' => trim((string) $_POST['discord_role_helper']),
                'DISCORD_ROLE_MODERATOR' => trim((string) $_POST['discord_role_moderator']),
                'DISCORD_ROLE_ADMIN' => trim((string) $_POST['discord_role_admin']),
                'DISCORD_ROLE_MANAGEMENT' => trim((string) $_POST['discord_role_management']),
                'DISCORD_ROLE_OWNER' => trim((string) $_POST['discord_role_owner']),
                'DISCORD_ROLE_JURY' => trim((string) $_POST['discord_role_jury']),
            ];
            writeEnvironment($envPath, $examplePath, $updates);
            redirectStep(6);
        }

        if ($action === 'install') {
            foreach (requirements($basePath) as $requirement) {
                if (!$requirement[2]) {
                    throw new RuntimeException('Installatie gestopt: '.$requirement[0].' voldoet niet.');
                }
            }
            dbConnection(envData($envPath));
            @unlink($basePath.'/bootstrap/cache/config.php');
            @unlink($basePath.'/bootstrap/cache/routes-v7.php');
            @unlink($basePath.'/bootstrap/cache/events.php');

            require $basePath.'/vendor/autoload.php';
            $app = require $basePath.'/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();
            Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProductionSeeder', '--force' => true]);
            Illuminate\Support\Facades\Artisan::call('storage:link');

            writeEnvironment($envPath, $examplePath, ['INSTALLATION_LOCKED' => 'true']);
            if (!is_dir(dirname($installedMarker))) {
                mkdir(dirname($installedMarker), 0775, true);
            }
            file_put_contents($installedMarker, date(DATE_ATOM));
            Illuminate\Support\Facades\Artisan::call('optimize:clear');
            redirectStep(7);
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
    $env = envData($envPath);
}

$steps = [
    1 => ['Welkom', 'Introductie'],
    2 => ['Systeemvereisten', 'Controleer je server'],
    3 => ['Database', 'Databaseconfiguratie'],
    4 => ['Omgeving', 'Algemene instellingen'],
    5 => ['Discord-integratie', 'OAuth en rollen'],
    6 => ['Installeren', 'Database en basisdata'],
    7 => ['Voltooid', 'Installatie afronden'],
];
$checks = requirements($basePath);
$allRequirementsPass = !in_array(false, array_column($checks, 2), true);
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Onbekend';
$memoryLimit = ini_get('memory_limit') ?: 'Onbekend';
$uploadLimit = ini_get('upload_max_filesize') ?: 'Onbekend';
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CN Community installeren</title>
    <link rel="stylesheet" href="./install.css">
</head>
<body>
<div class="installer">
    <aside class="installer-side">
        <img class="logo" src="../assets/images/cn-logo.png" alt="CN Community">
        <div class="product"><strong>CN Community</strong><span>Installatiewizard <b>v1.0</b></span></div>
        <div class="steps">
            <?php foreach ($steps as $number => [$title, $subtitle]): ?>
                <div class="step <?= $number === $step ? 'active' : ($number < $step ? 'done' : '') ?>">
                    <span class="step-number"><?= $number < $step ? '✓' : $number ?></span>
                    <div><strong><?= htmlspecialchars($title) ?></strong><small><?= htmlspecialchars($subtitle) ?></small></div>
                </div>
            <?php endforeach ?>
        </div>
        <div class="help-card"><strong>Hulp nodig?</strong><p>Controleer de installatiehandleiding of neem contact op met Cloud86 wanneer een servermodule ontbreekt.</p><a href="https://www.cncommunity.nl">Naar CN Community ↗</a></div>
    </aside>
    <main class="installer-main">
        <header class="installer-head">
            <div>
                <h1><?= $step === 7 ? 'Installatie voltooid' : 'Welkom bij de installatie' ?> <?= $step === 1 ? '👋' : '' ?></h1>
                <p><?= $step === 7 ? 'CN Community is klaar voor gebruik. Rollen worden bij Discord-login automatisch gesynchroniseerd.' : 'Deze wizard helpt je stap voor stap bij het veilig installeren van CN Community op jouw server.' ?></p>
            </div>
            <div class="server-art"><div class="server"><i></i><i></i><i></i></div><div class="shield">CN</div></div>
        </header>
        <div class="installer-content">
            <?php if ($error): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif ?>

            <?php if ($step === 1): ?>
                <section class="card"><h2>Klaar om CN Community te installeren?</h2><p>De wizard controleert de hosting, configureert Laravel en koppelt Discord. Er wordt geen apart administratoraccount aangemaakt.</p>
                    <div class="install-summary">
                        <div class="summary-row"><span>Platform</span><strong>Laravel 12 · PHP 8.3+</strong></div>
                        <div class="summary-row"><span>Authenticatie</span><strong>Discord OAuth2</strong></div>
                        <div class="summary-row"><span>Beheerder</span><strong>Discord-eigenaarrol</strong></div>
                        <div class="summary-row"><span>Database</span><strong>MySQL / MariaDB</strong></div>
                    </div>
                </section>
                <div class="actions"><span></span><a class="button button-primary" href="./?step=2">Installatie starten →</a></div>
            <?php elseif ($step === 2): ?>
                <section class="card"><h2>Systeemcontrole</h2><p>We controleren of de server voldoet aan de minimale vereisten.</p>
                    <div class="checks"><?php foreach ($checks as [$label, $value, $ok]): ?><div class="check <?= $ok ? '' : 'fail' ?>"><span class="check-icon"><?= strtoupper(substr($label, 0, 2)) ?></span><div><strong><?= htmlspecialchars($label) ?></strong><small><?= htmlspecialchars((string) $value) ?></small></div><b><?= $ok ? '✓' : '×' ?></b></div><?php endforeach ?></div>
                    <div class="notice <?= $allRequirementsPass ? '' : 'error' ?>"><?= $allRequirementsPass ? '✓ Alle systeemvereisten zijn voldaan. Je kunt verder.' : 'Niet alle vereisten zijn beschikbaar. Los de rode controles eerst op.' ?></div>
                </section>
                <section class="card"><h2>Omgevingsoverzicht</h2><p>Belangrijke informatie over je serveromgeving.</p><div class="environment-grid"><div class="environment-item"><strong>Server</strong><span><?= htmlspecialchars($serverSoftware) ?></span></div><div class="environment-item"><strong>PHP memory limit</strong><span><?= htmlspecialchars($memoryLimit) ?></span></div><div class="environment-item"><strong>Max upload</strong><span><?= htmlspecialchars($uploadLimit) ?></span></div><div class="environment-item"><strong>Tijdzone</strong><span>Europe/Amsterdam</span></div></div></section>
                <div class="actions"><a class="button button-secondary" href="./?step=1">← Terug</a><?php if ($allRequirementsPass): ?><a class="button button-primary" href="./?step=3">Volgende →</a><?php endif ?></div>
            <?php elseif ($step === 3): ?>
                <form method="post"><input type="hidden" name="_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="database">
                    <section class="card"><h2>Databaseconfiguratie</h2><p>Vul de databasegegevens uit Plesk in. De verbinding wordt getest voordat je verdergaat.</p><div class="form-grid">
                        <div class="field"><label>Databasehost</label><input name="db_host" required value="<?= htmlspecialchars($env['DB_HOST'] ?? 'localhost') ?>"></div>
                        <div class="field"><label>Poort</label><input name="db_port" required value="<?= htmlspecialchars($env['DB_PORT'] ?? '3306') ?>"></div>
                        <div class="field"><label>Databasenaam</label><input name="db_database" required value="<?= htmlspecialchars($env['DB_DATABASE'] ?? '') ?>"></div>
                        <div class="field"><label>Databasegebruiker</label><input name="db_username" required value="<?= htmlspecialchars($env['DB_USERNAME'] ?? '') ?>"></div>
                        <div class="field full"><label>Databasewachtwoord</label><input type="password" name="db_password" placeholder="<?= !empty($env['DB_PASSWORD']) ? 'Bestaand wachtwoord behouden indien leeg' : '' ?>"></div>
                    </div></section><div class="actions"><a class="button button-secondary" href="./?step=2">← Terug</a><button class="button button-primary">Verbinding testen →</button></div>
                </form>
            <?php elseif ($step === 4): ?>
                <form method="post"><input type="hidden" name="_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="environment">
                    <section class="card"><h2>Algemene instellingen</h2><p>Deze waarden bepalen de naam, URL en standaardomgeving van het platform.</p><div class="form-grid">
                        <div class="field"><label>Platformnaam</label><input name="app_name" required value="<?= htmlspecialchars($env['APP_NAME'] ?? 'CN Community Platform 2026') ?>"></div>
                        <div class="field"><label>Website-URL</label><input name="app_url" required value="<?= htmlspecialchars($env['APP_URL'] ?? 'https://www.cncommunity.nl') ?>"></div>
                        <div class="field"><label>Tijdzone</label><select name="timezone"><option value="Europe/Amsterdam">Europe/Amsterdam</option></select></div>
                        <div class="field"><label>Omgeving</label><input value="Productie" disabled><small>Debugmodus blijft uitgeschakeld.</small></div>
                    </div></section><div class="actions"><a class="button button-secondary" href="./?step=3">← Terug</a><button class="button button-primary">Instellingen opslaan →</button></div>
                </form>
            <?php elseif ($step === 5): ?>
                <form method="post"><input type="hidden" name="_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="discord">
                    <section class="card"><h2>Discord-integratie</h2><p>Gebruikers loggen in met Discord. De bot leest hun serverrollen en kent automatisch de juiste platformrol toe.</p><div class="form-grid">
                        <div class="field"><label>Discord Client ID</label><input name="discord_client_id" required value="<?= htmlspecialchars($env['DISCORD_CLIENT_ID'] ?? '') ?>"></div>
                        <div class="field"><label>Discord Client Secret</label><input type="password" name="discord_client_secret" placeholder="<?= !empty($env['DISCORD_CLIENT_SECRET']) ? 'Bestaand geheim behouden indien leeg' : '' ?>"></div>
                        <div class="field"><label>Discord Bot Token</label><input type="password" name="discord_bot_token" placeholder="<?= !empty($env['DISCORD_BOT_TOKEN']) ? 'Bestaande token behouden indien leeg' : '' ?>"></div>
                        <div class="field"><label>Discord Server/Guild ID</label><input name="discord_guild_id" required value="<?= htmlspecialchars($env['DISCORD_GUILD_ID'] ?? '') ?>"></div>
                    </div></section>
                    <section class="card"><h2>Discord-rollen</h2><p>Vul de rol-ID’s in. De hoogste gevonden Discord-rol bepaalt de rol binnen MijnCN.</p><div class="form-grid">
                        <?php foreach (['MEMBER'=>'Lid','HELPER'=>'Helper','MODERATOR'=>'Moderator','ADMIN'=>'Admin','MANAGEMENT'=>'Management','OWNER'=>'Eigenaar','JURY'=>'Jury'] as $key => $label): ?>
                            <div class="field"><label><?= $label ?> rol-ID</label><input name="discord_role_<?= strtolower($key) ?>" value="<?= htmlspecialchars($env['DISCORD_ROLE_'.$key] ?? '') ?>"></div>
                        <?php endforeach ?>
                    </div><div class="notice">Er wordt geen beheeraccount aangemaakt. Iemand met de Discord-eigenaarrol krijgt bij het inloggen automatisch de rol Eigenaar.</div></section>
                    <div class="actions"><a class="button button-secondary" href="./?step=4">← Terug</a><button class="button button-primary">Discord opslaan →</button></div>
                </form>
            <?php elseif ($step === 6): ?>
                <form method="post"><input type="hidden" name="_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="install">
                    <section class="card"><h2>Klaar voor installatie</h2><p>Laravel maakt nu alle tabellen, permissies, Awards-categorieën en basisinstellingen aan.</p><div class="install-summary">
                        <div class="summary-row"><span>Database</span><strong><?= htmlspecialchars($env['DB_DATABASE'] ?? 'Niet ingesteld') ?></strong></div>
                        <div class="summary-row"><span>Website</span><strong><?= htmlspecialchars($env['APP_URL'] ?? 'Niet ingesteld') ?></strong></div>
                        <div class="summary-row"><span>Discord server</span><strong><?= htmlspecialchars($env['DISCORD_GUILD_ID'] ?? 'Niet ingesteld') ?></strong></div>
                        <div class="summary-row"><span>Eigenaar</span><strong>Automatisch via Discord-rol</strong></div>
                    </div><div class="notice">Er worden geen demo-accounts, nepnieuws of fictieve partners aangemaakt.</div></section>
                    <div class="actions"><a class="button button-secondary" href="./?step=5">← Terug</a><button class="button button-primary">Nu installeren →</button></div>
                </form>
            <?php else: ?>
                <section class="card complete"><div class="complete-mark">✓</div><h2>CN Community is geïnstalleerd</h2><p>Je kunt nu inloggen met Discord. Rollen worden automatisch uit de gekoppelde Discord-server overgenomen.</p>
                    <a class="button button-primary" href="../auth/discord">Inloggen met Discord →</a></section>
            <?php endif ?>
        </div>
    </main>
</div>
</body>
</html>
