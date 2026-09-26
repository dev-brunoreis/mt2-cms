# Setup wizard and first-run seeds

`GET/POST /setup` (`SetupController`) walks **requirements → database → (admin if needed) → done**. The database step writes credentials to `.env` (`APP_INSTALLED=false`), runs `CmsSchema::ensure()`, and seeds defaults via `SetupInstaller`.

- If the CMS `admins` table already has rows (e.g. you deleted only `.env`), setup **skips** creating an admin, sets `APP_INSTALLED=true`, and shows a success screen with links to the public site and `/admin`.
- If there are no admins, the admin step is required; finishing it marks installed and shows the same success screen (no auto-login).
- Admin recovery (installed but empty `admins`) still uses the admin step only (skips requirements).

Host checks (`SetupRequirements`): PHP 8.3.x, required extensions (`pdo_mysql`, `gd` with JPEG/PNG/WebP, `curl`, `mbstring`, `iconv`, `fileinfo`, `openssl`), `vendor/autoload.php`, and writable project root / `var/` / `public/uploads/`. Same checks via CLI: `php bin/check-requirements.php` or `composer check`. The wizard re-checks on every show; Continue is blocked until required checks pass.

Database step: validates game + CMS connections. CMS must be **MySQL 8+ or MariaDB 10.3+** (JSON columns) — typically a separate server from the Metin2 game MySQL. If the `cms` schema is missing, setup runs `CREATE DATABASE IF NOT EXISTS cms` when the user has CREATE privilege (same on AJAX `POST /setup/test-connection`).

Host vs Docker defaults (`SetupDatabaseDefaults`): inside Compose the form uses `game:3306` / `mysql:3306`. When PHP runs on the host (`php -S`, no `/.dockerenv`), Compose service names are rewritten to `127.0.0.1:8001` (game) and `127.0.0.1:8002` (CMS). `localhost` plus a non-3306 port is stored as `127.0.0.1` (`Database::tcpHost`) because PDO MySQL treats `localhost` as a Unix socket and ignores the port. Dev Compose uses MySQL `root`; production should use a dedicated `cms` user.

## First HTTP boot

`src/bootstrap/installed_services.php` seeds empty catalogs once (settings flags, so deleting later does not re-seed):

| Seed | Service | Flag | When |
| --- | --- | --- | --- |
| Four class banners | `BannerSeedService` | `banners_seeded` | Empty `cms_banners` + GD available |
| One published welcome news post | `NewsSeedService` | `news_welcome_seeded` | Empty `news` + at least one admin (author) |
| Five classic Metin2 events | `EventSeedService` | `events_seeded` | Empty `cms_events` |

Welcome news is player-facing English HTML (`Welcome to the realm`), comments on, no cover. Edit or replace it under **Admin → Content → News**. If no admin exists yet (recovery), the news seed waits for the next boot after the admin is created.

Classic events (Fishing, Moonlight Treasure Chest, Double Drop Weekend, OX, Guild War) are published English HTML dated from the current week so the public `/events` list and home widget are not empty. Edit or replace them under **Admin → Content → Events**. The seed writes through `EventRepository` (no Discord notify).

Related: [deploy.md](deploy.md) (`APP_INSTALLED`, migrations).
