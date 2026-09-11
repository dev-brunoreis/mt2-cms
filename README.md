# Mt2 CMS

Metin2 CMS with routing, overridable themes, account area, player ranking, and i18n.

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | PHP 8.3 FPM |
| Web | Nginx |
| Game database | MySQL 5.6 (`game` service) |
| CMS database | MySQL 8.0 (`mysql` service, database `cms`, reserved) |
| Autoload | Composer PSR-4 (`Mt2Cms\` → `src/`) |
| Config | `vlucas/phpdotenv` |
| Router | `nikic/fast-route` |
| Templates | Twig + JSON layout trees |
| UI | Tailwind CSS (built to `public/css/app.css`) |

## Architecture

```
public/index.php          Front controller → Application::run()
src/
  Application.php         Bootstrap, session, DI, FastRoute dispatch
  Auth/                   Session auth + CSRF + rate limit
  Http/Controller/        Thin controllers
  Theme/                  Theme chain, layout JSON merge, Twig render
  I18n/                   Locale files + Translator
  Model/                  Env + PDO Database
  Repository/             account / player / common / log
themes/
  default/                Base theme (layouts + Twig atoms)
  overlay-demo/           Example child theme (navbar override)
lang/                     Locale JSON (`en`, `pt-BR`, …)
docs/                     How-to guides for new features
```

`Database` connects without a default schema; each repository calls `useDatabase()`.

| Repository | Schema | Role |
| --- | --- | --- |
| `AccountRepository` | `account` | Find, create, authenticate, block/unblock |
| `PlayerRepository` | `player` | Characters, public ranking, public profile |
| `GmRepository` | `common` | GM list and hosts |
| `LogRepository` | `log` | Recent login log rows |

Sensitive fields (`password`, `social_id`, `email`, `ip`, …) are stripped before public results.

## Routes

Public routes are registered in [`src/Http/PublicRoutes.php`](src/Http/PublicRoutes.php). Highlights:

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/` | Home (+ upcoming events) |
| GET/POST | `/register`, `/login` | Auth (CSRF, rate limited) |
| GET | `/news`, `/news/{id}` | News |
| GET | `/events`, `/events/{id}` | Events |
| GET | `/shop` | Item shop |
| GET | `/donate` | Cash packages (PayPal) |
| POST | `/payments/webhook/paypal` | PayPal webhook (configure in production) |
| GET | `/account`, `/account/characters` | Account area |
| POST | `/account/characters/unstuck` | Unstuck (offline, rate limited) |
| GET | `/ranking`, `/player/{name}` | Ranking and profiles |

Admin routes: [`src/Http/AdminRoutes.php`](src/Http/AdminRoutes.php). Controller wiring: [`src/Http/controller_factories.php`](src/Http/controller_factories.php).

## Themes

Set `THEME` in `.env` (default `default`).

Themes live under `themes/{name}/`:

- `theme.json` — `{ "name", "parent" }`
- `layouts/*.json` — recursive atomic layout trees (`id`, `template`, `slots`)
- `templates/` — Twig atoms; child theme paths win over parents

Example child theme `overlay-demo` only overrides `components/navbar.twig`. Set `THEME=overlay-demo` to try it.

Layout merge is deep **by node `id`**, so a child can replace only the navbar without copying the full layout.

## Implementing

| Guide | When to use |
| --- | --- |
| [docs/add-page.md](docs/add-page.md) | New public or authenticated page |
| [docs/add-repository.md](docs/add-repository.md) | New game DB queries |
| [docs/add-theme.md](docs/add-theme.md) | Child theme / overlay |
| [docs/add-locale.md](docs/add-locale.md) | Translations / new language |
| [docs/game-files.md](docs/game-files.md) | Game dumps, JSON config, custom source |
| [docs/security.md](docs/security.md) | Security rules and PR checklist |
| [docs/deploy.md](docs/deploy.md) | Production deployment and TLS |

## Security

HTTP responses send security headers (`X-Frame-Options`, `nosniff`, `Referrer-Policy`, CSP with self-hosted assets). Sessions use hardened cookies (separate admin cookie at `/admin`), idle timeouts (admin 30 min, public 2 h), and regenerate on login. Login/register and password change are rate limited (file-backed, fail-closed). Player passwords use Metin2-compatible `*SHA1(SHA1)` hashing; admins use `password_hash` with TOTP 2FA (encrypted at rest via `APP_KEY`). Unhandled exceptions return a generic 500 (no stack traces to clients).

See [docs/security.md](docs/security.md) for the full checklist and [docs/deploy.md](docs/deploy.md) for production (`compose.prod.yml`, backups, PayPal webhook URL).

## Requirements

- Docker and Docker Compose
- Composer (on the host, or run inside a container that has Composer)

## Quick start

```bash
docker compose up --build
composer install
npm ci && npm run build
```

The first-run wizard at `/setup` writes `.env`. PHP-FPM runs as UID/GID `1000` by default (override with `PUID` / `PGID` when building) so it can write files in the project directory. Rebuild the PHP image after changing those values: `PUID=$(id -u) PGID=$(id -g) docker compose up --build`.

| Service | URL / port |
| --- | --- |
| Site (Nginx) | http://localhost:8000 |
| Adminer | http://127.0.0.1:8080 (opt-in: `docker compose --profile tools up`) |
| Game MySQL 5.6 | `127.0.0.1:8001` (loopback only) |
| CMS MySQL 8.0 | `127.0.0.1:8002` (loopback only) |

Default MySQL root password in Compose (dev fixture): `admin123@`. Set it explicitly in `.env` — there is no hardcoded runtime fallback.

On first start of `game`, `docker/mysql/init` creates the four schemas and imports `docker/mysql/backup/*.sql`.

## Environment

Copy `.env-example` to `.env`. Variables read by the app:

| Variable | Default in code | Notes |
| --- | --- | --- |
| `DB_HOST` | `game` | Must be the **game** MySQL service for Mt2 tables |
| `DB_PORT` | `3306` | Internal Docker port |
| `DB_USER` | `root` | |
| `DB_PASSWORD` | *(required)* | No default; must be set in `.env` |
| `DB_NAME` | *(empty)* | Optional; repositories switch schema themselves |
| `THEME` | `default` | Active theme folder under `themes/` |
| `LOCALE` | `en` | Default locale when no cookie is set |
| `GAME_DIR` | `game/` | Game data root (`config.json`, client/db/server dumps) |
| `APP_INSTALLED` | `false` | `true` after `/setup` |
| `APP_KEY` | *(required when installed)* | 32-byte hex; `/setup` or `php bin/migrate.php` generates it |
| `APP_TRUST_PROXY` | `0` | Set `1` behind TLS reverse proxy (secure session cookies) |

For registration/login against `account.account`, use:

```env
DB_HOST=game
DB_PORT=3306
DB_USER=root
DB_PASSWORD=admin123@
THEME=default
LOCALE=en
```

From the host (not from a container), use `127.0.0.1` and port `8001`.

## Account registration

`POST /register` creates a row in `account.account`.

| Field | Rules |
| --- | --- |
| Username (`login`) | 2–30 chars, `[A-Za-z0-9_]` |
| Email | Valid email |
| Password | 5–16 characters |
| Delete character PIN (`social_id`) | Digits only, at least 7 characters |

Passwords are stored in MySQL `PASSWORD()` style (`*` + SHA1(SHA1(password, binary)), uppercase) so they match the game client.

Duplicate logins raise `Login already exists`. Blocked accounts (`status = BLOCK`) cannot log in.

Logged-in players can change their password at `/account/password` (requires current password, CSRF, rate limited).

## Admin panel

After setup, open `/admin`. The first superadmin is prompted to enroll TOTP when **Settings → Security → Require 2FA** is on (default for new installs). Configure captcha and 2FA policy under **Settings → Security**.

## Local dumps

`docker/mysql/backup/` ships sample Mt2 data, including accounts `admin` and `test`. Treat them as local fixtures, not production credentials.

## Current status

- Working: Docker stack, game + CMS MySQL, admin panel (RBAC, audit log, 2FA), news/tickets/item shop, themes, public auth/account (including password change), ranking/player pages, i18n, security headers, session hardening, rate limits, migrations off hot path.
- See [docs/improvements.md](docs/improvements.md) for remaining organizational refactors (not blockers for production).

## License

Unspecified. Private / local use unless you add a license.
