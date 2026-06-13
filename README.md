# CN Community Platform 2026

**Samen zijn we één.**

Een zelfstandig community-platform op Laravel 12 met Discord-integratie. Het platform combineert CN Awards, Mini Awards, MijnCN, Academy, HR, Partner CRM, taken, nieuws, events en staffbeheer in één moderne SaaS-interface.

## Techniek

- PHP 8.3+ en Laravel 12
- MySQL 8 / MariaDB 10.6+
- Blade, plain CSS en plain JavaScript
- Discord OAuth2 en Discord Bot/Webhook API
- Laravel Sanctum API-tokens
- Geen Node.js, Vite, React, Vue, Livewire of WordPress

## Installatie

1. Stel in Plesk PHP 8.3 of hoger in.
2. Upload het project buiten de publieke webroot.
3. Zet de document root van het domein op `public/`.
4. Kopieer `.env.example` naar `.env` en vul database, domein en Discord-gegevens in.
5. Voer via Plesk SSH of een eenmalige Composer-taak uit:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan storage:link
```

6. Open `/install`, controleer de server en start de installatie.
7. Zet daarna `INSTALLATION_LOCKED=true` in `.env`.
8. Na een geslaagde installatie kun je voor database-backed processen instellen:

```dotenv
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

9. Optimaliseer de productie-installatie:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Plesk cronjobs

Elke minuut:

```bash
php /var/www/vhosts/example.nl/httpdocs/artisan schedule:run
```

Elke vijf minuten voor queues op shared hosting:

```bash
php /var/www/vhosts/example.nl/httpdocs/artisan queue:work --stop-when-empty --tries=3
```

De scheduler verwerkt automatisch de ingestelde Awards-fasen, eenmalige Discord-aankondigingen en verjaardagsmeldingen voor MijnCN en Discord. Gebruik op deze installatie bijvoorbeeld:

```bash
cd /var/www/vhosts/amplitudefm.nl/cncommunity.nl && php artisan schedule:run
```

## Discord-configuratie

Maak in het Discord Developer Portal een applicatie met redirect:

```text
https://jouwdomein.nl/auth/discord/callback
```

Vul `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_BOT_TOKEN`, `DISCORD_GUILD_ID` en optioneel `DISCORD_WEBHOOK_URL` in. Nomineren en stemmen weigeren accounts zonder gekoppelde Discord-ID.

Voor het volledige Communityleden-overzicht moet bij de Discord-app onder **Bot > Privileged Gateway Intents** de optie **Server Members Intent** actief zijn. Synchroniseer daarna via MijnCN als Eigenaar/Management of met:

```bash
php artisan cn:sync-discord-members
```

Op Plesk kan dit commando bijvoorbeeld ieder uur als geplande taak worden uitgevoerd.

## API

Alle endpoints staan onder `/api/v1` en gebruiken Sanctum bearer tokens met abilities:

| Module | Endpoint | Ability |
|---|---|---|
| MijnCN | `GET /api/v1/mijncn/profile` | `mijncn:read` |
| Awards | `GET /api/v1/awards` | `awards:read` |
| Academy | `GET /api/v1/academy` | `academy:read` |
| Taken | `GET /api/v1/tasks` | `tasks:read` |
| Partners | `GET /api/v1/partners` | `partners:read` |
| Nieuws | `GET /api/v1/news` | `content:read` |
| Events | `GET /api/v1/events` | `content:read` |
| Discord Bot | `GET /api/v1/discord/members/{discordId}` | `discord:read` |

Maak tokens aan via een beveiligde beheeractie:

```php
$token = $user->createToken('Discord Bot', ['discord:read', 'awards:read'])->plainTextToken;
```

## Beveiliging

- Stemmen zijn uniek per gebruiker, ronde en categorie.
- IP-adressen en user agents worden uitsluitend als keyed hashes opgeslagen.
- Nieuwe accounts en opvallend veel accounts op één IP verhogen de fraudescore.
- Stemmen met een score vanaf 70 worden automatisch geblokkeerd voor telling.
- Staffacties worden in `activity_logs` vastgelegd.
- Rollen worden aangevuld met fijnmazige permissions.
- Sessiecookies zijn encrypted, HTTP-only en secure in productie.

## Belangrijke mappen

- `app/Services`: Awards, fraude, Discord en publicatiebusinesslogica
- `app/Repositories`: herbruikbare querylaag
- `database/migrations`: volledige relationele datastructuur
- `database/seeders`: rollen, demo-users, Awards, Academy, partners en taken
- `resources/views`: publieke site, MijnCN, staff en installatiewizard
- `public/assets`: build-vrije CSS en JavaScript voor Plesk

## Ontwikkelen

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan test
```

De seed bevat fictieve demo-accounts. Productieaccounts ontstaan via Discord OAuth.

## Bestaande CN-database upgraden

De huidige installatie gebruikt geen handmatige importknop of SQL-upload. Gebruik rechtstreeks de bestaande CN-database en voer `php artisan migrate --force` uit. De migraties herkennen het oude schema, bewaren conflicterende tabellen onder een `legacy_`-naam en zetten gebruikers, Discord-profielen, stafffuncties, Awards, partners, taken, Academy-data en afwezigheid automatisch over.

Maak vooraf altijd een volledige databaseback-up. De onderstaande losse importinstructies zijn uitsluitend historische documentatie en worden niet meer door het platform gebruikt.

Tijdens stap 6 van de installatiewizard kan `cn_community_awards_2026.sql` direct worden geüpload. De installer neemt gebruikers, Discord-profielen, categorieën en nominaties over en voorkomt dubbele invoer.

Dezelfde import kan na installatie via de terminal worden uitgevoerd:

```bash
php artisan cn:import-legacy --confirm --sql="/pad/naar/cn_community_awards_2026.sql"
```

Voor een volledige import inclusief partners en open taken kan ook een aparte tijdelijke legacy-database worden gebruikt. Gebruik nooit de nieuwe productiedatabase als legacy-bron.

Vul daarna tijdelijk in `.env`:

```dotenv
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=cn_legacy
LEGACY_DB_USERNAME=...
LEGACY_DB_PASSWORD=...
```

Voer vervolgens uit:

```bash
php artisan cn:import-legacy --confirm
```

De database-importer neemt gebruikers, rollen, categorieën, nominaties, partners en open taken over. Oude templates, CSS, sessies en configuratiegeheimen worden bewust niet geïmporteerd.
