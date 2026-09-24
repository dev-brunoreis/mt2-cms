# Setup wizard and first-run seeds

`GET/POST /setup` (`SetupController`) writes `.env` (`APP_INSTALLED=true`), runs `CmsSchema::ensure()`, and creates the first admin. Admin recovery uses the same admin step when the `admins` table is empty.

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
