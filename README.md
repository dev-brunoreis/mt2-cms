# Mt2 CMS

Simple page registration.

The project is early-stage: repositories and Docker are in place; routing and a full admin UI are not.

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | PHP 8.3 FPM |
| Web | Nginx |
| Game database | MySQL 5.6 (`game` service) |
| CMS database | MySQL 8.0 (`mysql` service, database `cms`) |
| Autoload | Composer PSR-4 (`Mt2Cms\` → `src/`) |
| Config | `vlucas/phpdotenv` |
| UI | Tailwind CSS (CDN) on `public/index.php` |

## Architecture

```
public/                 HTTP entry (document root)
src/
  Application.php       Env bootstrap (not used by the registration page yet)
  Model/
    Env.php             Singleton env loader
    Database.php        PDO wrapper, multi-database `USE`
  Repository/
    Repository.php      Base class, strips sensitive columns
    AccountRepository.php
    PlayerRepository.php
    CommonRepository.php
    LogRepository.php
docker/
  php/                  PHP-FPM image
  nginx/                vhost → public/
  mysql/init/           Create + import Mt2 dumps
  mysql/backup/         SQL dumps: account, player, common, log
```

`Database` connects without a default schema, then each repository calls `useDatabase()`:

| Repository | Schema | Role |
| --- | --- | --- |
| `AccountRepository` | `account` | Find, create, block/unblock, delete accounts |
| `PlayerRepository` | `player` | Characters by id, name, or account |
| `CommonRepository` | `common` | GM list and locales |
| `LogRepository` | `log` | Recent login log rows |

Sensitive fields (`password`, `social_id`, `email`, `ip`, …) are removed before results are returned.

## Requirements

- Docker and Docker Compose
- Composer (on the host, or run inside the `php` container)

## Quick start

```bash
cp .env-example .env
docker compose up --build
```

Install PHP dependencies:

```bash
docker compose exec php composer install
```

| Service | URL / port |
| --- | --- |
| Registration (Nginx) | http://localhost:8000 |
| Adminer | http://localhost:8080 |
| Game MySQL 5.6 | `localhost:8001` |
| CMS MySQL 8.0 | `localhost:8002` |

Default MySQL root password in Compose: `admin123@`.

On first start of `game`, `docker/mysql/init` creates the four schemas and imports `docker/mysql/backup/*.sql`.

## Environment

Copy `.env-example` to `.env`. Variables read by `Mt2Cms\Model\Database`:

| Variable | Default in code | Notes |
| --- | --- | --- |
| `DB_HOST` | `game` | Must be the **game** MySQL service for Mt2 tables |
| `DB_PORT` | `3306` | Internal Docker port |
| `DB_USER` | `root` | |
| `DB_PASSWORD` | `admin123@` | |
| `DB_NAME` | *(empty)* | Optional; repositories switch schema themselves |

`.env-example` currently sets `DB_HOST=mysql`. That host is the CMS MySQL 8 instance (`cms`), **not** the Mt2 dumps. For registration against `account.account`, use:

```env
DB_HOST=game
DB_PORT=3306
DB_USER=root
DB_PASSWORD=admin123@
```

From the host (not from a container), use `127.0.0.1` and port `8001`.

## Account registration

`POST /` (`public/index.php`) creates a row in `account.account`.

| Field | Rules |
| --- | --- |
| Username (`login`) | 2–30 chars, `[A-Za-z0-9_]` |
| Email | Valid email |
| Password | 5–16 characters |
| Delete character PIN (`social_id`) | Digits only, at least 7 characters |

Passwords are stored in MySQL `PASSWORD()` style (`*` + SHA1(SHA1(password, binary)), uppercase) so they match the game client.

Duplicate logins raise `Login already exists`.

## Local dumps

`docker/mysql/backup/` ships sample Mt2 data, including accounts `admin` and `test`. Treat them as local fixtures, not production credentials.

## Current status

- Working: Docker stack, PDO layer, account/player/common/log repositories, registration form.
- Not built yet: `Application::run()` routing, CMS MySQL usage, admin panels, auth for staff.

## License

Unspecified. Private / local use unless you add a license.
