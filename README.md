# Mt2 CMS

Metin2 CMS: public site + admin panel, overridable themes, dual MySQL (game + CMS), and i18n.

## What it includes

- **Auth / account** — register, login, forgot/reset, email verify, password/email/PIN, characters/unstuck, orders, payments
- **Content** — news (+ comments), events, downloads, banners
- **Commerce** — item shop, donate (PayPal), cash credit
- **Player** — ranking, profiles, tickets, in-app notifications
- **SEO / ops** — meta, `/robots.txt`, `/sitemap.xml`, `/status`, `/health`
- **Admin** — RBAC ACL, audit log, optional TOTP 2FA; game (accounts, characters, guilds, bans, economy), content, store, game-data, logs, system, settings hubs
- **Themes** — JSON layout trees + Twig; child theme overlays (shipped `slate` example)

Full route and admin URL index: [docs/map.md](docs/map.md).

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
bin/                      migrate, payments-process, economy-tick, backups
src/
  Application.php         Bootstrap, session, DI, FastRoute dispatch
  bootstrap/              Autoload, installed services wiring
  Auth/                   Session auth + CSRF + rate limit
  Admin/                  Grid definitions, ACL catalogs, section menus
  Http/Controller/        Public controllers
  Http/Controller/Admin/  Admin panel controllers
  Service/                Application services
  Payment/                Gateways + webhook processing
  Game/                   Proto / dumps / display helpers
  Setup/                  Install wizard + SQL migrations
  Theme/                  Theme chain, layout JSON merge, Twig render
  I18n/                   Locale files + Translator
  Support/                Env, PDO Database, crypto, sanitizer
  Repository/             Game schemas + CMS tables
themes/
  default/                Base public theme (layouts + Twig atoms)
  admin/                  Admin panel theme
  slate/                  Minimal child overlay example
lang/                     Locale JSON (`en.json`; add locales via docs)
docs/                     Compiled map + how-to guides
```

`Database` connects without a default schema; each repository calls `useDatabase()`. Game tables live in `account` / `player` / `common` / `log`; CMS tables in schema `cms`. Repositories strip sensitive fields (`password`, `social_id`, `email`, `ip`, …) before public results. See [docs/add-repository.md](docs/add-repository.md).

## Routes

Public routes: [`src/Http/PublicRoutes.php`](src/Http/PublicRoutes.php). Grouped highlights (not a full catalog):

| Area | Paths |
| --- | --- |
| Health / SEO | `/health`, `/robots.txt`, `/sitemap.xml`, `/status` |
| Auth | `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email/{token}` |
| Account | `/account` (characters/unstuck, password, email, PIN, orders, payments, notifications, tickets) |
| Content | `/news`, `/events`, `/downloads` |
| Shop / donate | `/shop`, `/donate` (+ pay/return/cancel), `POST /payments/webhook/{provider}` |
| Players | `/ranking`, `/player/{name}` |

Admin routes: [`src/Http/AdminRoutes.php`](src/Http/AdminRoutes.php). Areas: `/admin`, `/admin/population`, `/admin/game/…`, `/admin/content/…`, `/admin/store/…`, `/admin/game-data/…`, `/admin/logs`, `/admin/system/…`, `/admin/settings`. Controller wiring: [`src/Http/controller_factories.php`](src/Http/controller_factories.php). Full prefixes and ACL: [docs/map.md](docs/map.md).

## Themes

Set `THEME` in `.env` (default `default`), or pick an active theme under **Admin → Settings → Themes**.

Themes live under `themes/{name}/`:

- `theme.json` — `{ "name", "parent" }`
- `layouts/*.json` — layout trees with optional `"extends": "_shell"`; merge is deep **by node `id`**
- `templates/` — Twig atoms; child theme paths win over parents
- `assets/` — theme CSS/images; link with `theme_asset('css/theme.css')` (served as `/theme-assets/{name}/…`)

**Overlay (child themes):** create `themes/{name}/` with `theme.json` `{ "name", "parent": "default" }` and override only what you need — e.g. `assets/css/tokens.css` for colors, or a layout node to hide a widget. The shipped `slate` theme is a minimal example. See [docs/add-theme.md](docs/add-theme.md).

## Payments

Donate uses **PayPal** (Checkout + fail-closed webhooks). Gateways implement `Mt2Cms\Payment\PaymentGateway` and register in `GatewayRegistry` — additional providers can plug in without rewriting the donate flow. Capture and cash credit run via `bin/payments-process.php` (see Ops). Details: [docs/payments.md](docs/payments.md).

## Ops (CLI / cron)

| Command | Role |
| --- | --- |
| `php bin/migrate.php` | Apply CMS schema migrations; may generate `APP_KEY` |
| `php bin/payments-process.php` | Drain payment webhook queue (capture + credit) |
| `php bin/economy-tick.php` | Refresh economy census / alerts snapshots |

Schedule payments and economy workers in production; do not run them on the HTTP path. See [docs/deploy.md](docs/deploy.md).

## Implementing

Start from **[docs/map.md](docs/map.md)** (compiled index), then **[docs/patterns.md](docs/patterns.md)** and **[docs/testing.md](docs/testing.md)**. Then open only the one guide for the area you are changing.

| Guide | When to use |
| --- | --- |
| [docs/map.md](docs/map.md) | Architecture index — read this first |
| [docs/patterns.md](docs/patterns.md) | Layers, HTTP, SQL, i18n — how to write code |
| [docs/testing.md](docs/testing.md) | Unit + contract tests (`composer test`) |
| [docs/acl.md](docs/acl.md) | Admin ACL resources and POST checks |
| [docs/add-admin-section.md](docs/add-admin-section.md) | New admin screen / grid / hub |
| [docs/add-page.md](docs/add-page.md) | New public or authenticated page |
| [docs/add-repository.md](docs/add-repository.md) | New game DB queries |
| [docs/add-theme.md](docs/add-theme.md) | Child theme / overlay |
| [docs/add-locale.md](docs/add-locale.md) | Translations / new language |
| [docs/setup.md](docs/setup.md) | Install wizard and first-run seeds |
| [docs/payments.md](docs/payments.md) | Donate, webhooks, cash credit |
| [docs/economy.md](docs/economy.md) | Economy tick, alerts, admin UI |
| [docs/notifications.md](docs/notifications.md) | Player in-app inbox |
| [docs/seo.md](docs/seo.md) | Meta, robots, sitemap |
| [docs/game-files.md](docs/game-files.md) | Game dumps, JSON config, custom source |
| [docs/security.md](docs/security.md) | Security rules and PR checklist |
| [docs/deploy.md](docs/deploy.md) | Production deployment and TLS |

## Security

HTTP responses send security headers (`X-Frame-Options`, `nosniff`, `Referrer-Policy`, CSP with self-hosted assets). Sessions use hardened cookies (separate admin cookie at `/admin`), idle timeouts (admin 30 min, public 2 h), and regenerate on login. Login/register and password change are rate limited (file-backed, fail-closed). Player passwords use Metin2-compatible `*SHA1(SHA1)` hashing; admins use `password_hash` with TOTP 2FA (encrypted at rest via `APP_KEY`). Unhandled exceptions return a generic 500 (no stack traces to clients).

See [docs/security.md](docs/security.md) for the full checklist and [docs/deploy.md](docs/deploy.md) for production (`cp .env.prod-example .env`, then `compose.prod.yml`, immutable images, MySQL app users, `/health`, backups, PayPal webhook id).

## Requirements

- Docker and Docker Compose
- Composer (on the host, or run inside a container that has Composer)

## Quick start

```bash
docker compose up --build
composer install
npm ci && npm run build
```

If the site shows **Dependencies not installed**, `vendor/` is missing — run `composer install` and reload. The first-run wizard at `/setup` writes `.env`. PHP-FPM runs as UID/GID `1000` by default (override with `PUID` / `PGID` when building) so it can write files in the project directory. Rebuild the PHP image after changing those values: `PUID=$(id -u) PGID=$(id -g) docker compose up --build`.

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
| `CMS_DB_HOST` | `mysql` | CMS MySQL service (from host: `127.0.0.1` + port `8002`) |
| `CMS_DB_PORT` | `3306` | Internal Docker port for CMS MySQL |
| `CMS_DB_USER` | `root` | Dev fixture; production uses a dedicated app user |
| `CMS_DB_PASSWORD` | *(required)* | No default; must be set in `.env` |
| `CMS_DB_NAME` | `cms` | CMS schema name |
| `THEME` | `default` | Active theme folder under `themes/` |
| `LOCALE` | `en` | Default locale when no cookie is set |
| `GAME_DIR` | `game/` | Game data root (`config.json`, client/db/server dumps) |
| `APP_INSTALLED` | `false` | `true` after `/setup` |
| `APP_KEY` | *(required when installed)* | 32-byte hex; `/setup` or `php bin/migrate.php` generates it |
| `APP_TRUST_PROXY` | `0` | Set `1` behind TLS reverse proxy (secure session cookies) |

Mail (`MAIL_*` / `MAIL_DSN`), PayPal (`PAYPAL_*`), and public site URL (`APP_URL`) are optional in `.env` — see [`.env-example`](.env-example) and configure further under **Admin → Settings** (Community / Payment methods).

For registration/login against `account.account`, use:

```env
DB_HOST=game
DB_PORT=3306
DB_USER=root
DB_PASSWORD=admin123@
CMS_DB_HOST=mysql
CMS_DB_PORT=3306
CMS_DB_USER=root
CMS_DB_PASSWORD=admin123@
CMS_DB_NAME=cms
THEME=default
LOCALE=en
```

From the host (not from a container), use `127.0.0.1` and ports `8001` (game) / `8002` (CMS).

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

After setup, open `/admin`. The first superadmin is prompted to enroll TOTP when **Settings → Security → Require 2FA** is on (off by default on new installs). Configure captcha and 2FA policy under **Settings → Security**.

Permissions are resource-based (RBAC); mutating POSTs require ACL + audit. Own admin TOTP lives at `/admin/account/security` (no ACL resource). Section list and grantable ids: [docs/map.md](docs/map.md) and [docs/acl.md](docs/acl.md).

First HTTP boot after `/setup` can seed class banners, a welcome news post, and classic events when those tables are empty — see [docs/setup.md](docs/setup.md).

## Local dumps

`docker/mysql/backup/` ships sample Mt2 data, including accounts `admin` and `test`. Treat them as local fixtures, not production credentials.

## Current status

- Working: Docker stack (dev + production compose), game + CMS MySQL, admin panel (RBAC, audit log, 2FA), news/tickets/events/downloads/banners, item shop + donate (PayPal fail-closed webhooks + payment worker), economy tick/alerts, player inbox notifications, SEO (meta/robots/sitemap), themes (including `slate` overlay), public auth/account (password/email/PIN, unstuck, orders), ranking/player pages, referrals, i18n (`lang/en.json`), security headers, session hardening, rate limits, migrations off hot path, first-run seeds, `/health` endpoint.
- Production: `compose.prod.yml` builds immutable PHP/Nginx images, uses dedicated MySQL app users, and ships container healthchecks. Cron workers for payments and economy — see [docs/deploy.md](docs/deploy.md) post-deploy checklist.
- See [docs/improvements.md](docs/improvements.md) for remaining organizational notes (not blockers for production).

## License

[MIT](LICENSE)