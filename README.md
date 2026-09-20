# Mt2 CMS

Metin2 CMS: public site + admin panel, overridable themes, dual MySQL (game + CMS), and i18n.

This README is the **runbook** — how to install, configure, and run the app. Architecture and how-to guides live under [`docs/`](docs/map.md).

---

## Requirements

| Tool | Notes |
| --- | --- |
| Docker + Docker Compose | Runs Nginx, PHP-FPM, game MySQL 5.6, CMS MySQL 8.0 |
| Composer | On the **host**, or via the official `composer:2` image (see below) |
| Node.js + npm | Build Tailwind CSS and vendor assets |

The long-running `php` service is FPM only — it does **not** ship `composer`, `bash`, or `npm`. Runtime needs `vendor/` on disk (bind-mounted in dev). Production bakes `vendor/` into the image at build time.

Optional: PHP 8.3 on the host only if you run `bin/*.php` or PHPUnit outside Docker.

---

## Local development (step by step)

### 1. Clone and enter the project

```bash
cd mt2-cms
```

### 2. Create `.env`

```bash
cp .env-example .env
```

The defaults match the Docker Compose stack (password `admin123@`). You do **not** need to change them for a first local run.

Minimum that must be set (already present in `.env-example`):

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
APP_INSTALLED=false
APP_KEY=
```

> From the **host** (CLI outside Docker), use `127.0.0.1` and ports `8001` (game) / `8002` (CMS) instead of service names `game` / `mysql`.

### 3. Install PHP and frontend dependencies

Run these on the **host** (or the one-shot Composer container). Do **not** use `docker compose exec php composer …` — that binary is not in the image.

```bash
composer install
npm ci && npm run build
```

Without Composer on the host:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install
```

That writes `vendor/` into the project directory; the FPM container reads it via the bind mount. Platform PHP is pinned in `composer.json` (`8.3.33`) to match the container.

If the site shows **Dependencies not installed**, `vendor/` is missing — install again and reload.

### 4. Start the stack

```bash
docker compose up --build
```

PHP-FPM runs as UID/GID `1000` by default so it can write into the project directory. If permission errors appear on `.env` or `var/`, rebuild with your user:

```bash
PUID=$(id -u) PGID=$(id -g) docker compose up --build
```

First start of `game` creates schemas and imports sample dumps from `docker/mysql/backup/*.sql` (accounts `admin` and `test` — local fixtures only).

### 5. Open the site and finish setup

| Service | URL |
| --- | --- |
| Site | http://localhost:8000 |
| Setup wizard | http://localhost:8000/setup |
| Admin panel | http://localhost:8000/admin |
| Adminer (optional) | http://127.0.0.1:8080 — `docker compose --profile tools up` |
| Game MySQL | `127.0.0.1:8001` (loopback only) |
| CMS MySQL | `127.0.0.1:8002` (loopback only) |

1. Open **http://localhost:8000/setup**
2. Complete the wizard (creates the first admin, writes `APP_INSTALLED=true`, generates `APP_KEY`)
3. Log in at **http://localhost:8000/admin**

The first HTTP boot after setup can seed class banners, a welcome news post, and classic events when those tables are empty — see [docs/setup.md](docs/setup.md).

### 6. (Optional) Run migrations manually

Usually covered by `/setup`. To apply CMS schema changes later:

```bash
docker compose exec php php bin/migrate.php
```

Or from the host (with `CMS_DB_HOST=127.0.0.1` and `CMS_DB_PORT=8002` in `.env`):

```bash
php bin/migrate.php
```

### 7. (Optional) Cron-style workers

Do **not** run these on the HTTP request path. In production, schedule them; locally, run when needed:

```bash
docker compose exec php php bin/payments-process.php   # PayPal webhook queue → capture + cash credit
docker compose exec php php bin/economy-tick.php        # Economy census / alerts snapshots
```

### 8. Stop the stack

```bash
docker compose down
```

Data volumes (`mysql-data`, `cms-data`) persist until you remove them with `docker compose down -v`.

---

## Day-to-day commands

```bash
# Start (foreground)
docker compose up

# Start (background)
docker compose up -d

# Rebuild after Dockerfile / PUID changes
docker compose up --build

# Rebuild CSS / vendor assets after frontend changes
npm run build

# Unit tests
composer test

# Shell inside PHP container
docker compose exec php sh
```

---

## Configure the app (after first login)

| Where | What |
| --- | --- |
| **Admin → Settings → Community** | Public site URL, mail, Discord |
| **Admin → Settings → Themes** | Active theme (`THEME` / overlay) |
| **Admin → Settings → Security** | Captcha, require 2FA |
| **Admin → Settings → Payment methods** | PayPal client id/secret + webhook id |
| **Admin → Settings → Locale** | Default language |
| `.env` | DB hosts, `THEME`, `LOCALE`, `MAIL_*`, `PAYPAL_*`, `APP_URL`, `APP_TRUST_PROXY` |

Mail and PayPal can also be set in `.env` — see [`.env-example`](.env-example). Own admin TOTP: `/admin/account/security`.

### Player registration (game DB)

`POST /register` writes `account.account`:

| Field | Rules |
| --- | --- |
| Username (`login`) | 2–30 chars, `[A-Za-z0-9_]` |
| Email | Valid email |
| Password | 5–16 characters |
| Delete character PIN (`social_id`) | Digits only, ≥ 7 characters |

Passwords use Metin2-compatible `*SHA1(SHA1)` hashing.

---

## Environment reference

| Variable | Default | Notes |
| --- | --- | --- |
| `DB_*` | `game` / `3306` / `root` | Game MySQL (`account`, `player`, …) |
| `CMS_DB_*` | `mysql` / `3306` / `root` / `cms` | CMS MySQL schema |
| `DB_PASSWORD` / `CMS_DB_PASSWORD` | *(required)* | No runtime default |
| `THEME` | `default` | Folder under `themes/` |
| `LOCALE` | `en` | Default locale |
| `GAME_DIR` | `game/` | Client/db/server dumps |
| `APP_INSTALLED` | `false` | `true` after `/setup` |
| `APP_KEY` | *(required when installed)* | 32-byte hex; setup or migrate generates it |
| `APP_TRUST_PROXY` | `0` | `1` behind TLS reverse proxy |
| `APP_URL` | optional | Public URL for email links |
| `MAIL_*` / `MAIL_DSN` | optional | SMTP |
| `PAYPAL_*` | optional | Donate gateway |

---

## Production

Do **not** expose the dev Compose stack to the internet.

1. Copy **`.env.prod-example`** → `.env` and replace every `change-me-*` password  
2. `docker compose -f compose.prod.yml up -d --build`  
3. `docker compose -f compose.prod.yml exec php php bin/migrate.php`  
4. Finish `/setup`, put TLS reverse proxy in front of `127.0.0.1:8000`

Full checklist: [docs/deploy.md](docs/deploy.md).

---

## What it includes

- **Auth / account** — register, login, forgot/reset, email verify, password/email/PIN, characters/unstuck, orders, payments  
- **Content** — news (+ comments), events, downloads, banners  
- **Commerce** — item shop, donate (PayPal), cash credit  
- **Player** — ranking, profiles, tickets, in-app notifications  
- **SEO / ops** — meta, `/robots.txt`, `/sitemap.xml`, `/status`, `/health`  
- **Admin** — RBAC ACL, audit log, optional TOTP 2FA; game, content, store, game-data, logs, system, settings  
- **Themes** — JSON layout trees + Twig; child overlays (shipped `slate` example)

---

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | PHP 8.3 FPM |
| Web | Nginx |
| Game database | MySQL 5.6 (`game` service) |
| CMS database | MySQL 8.0 (`mysql` service, database `cms`) |
| Autoload | Composer PSR-4 (`Mt2Cms\` → `src/`) |
| Templates | Twig + JSON layout trees |
| UI | Tailwind CSS → `public/css/app.css` |

```
public/index.php          Front controller
bin/                      migrate, payments-process, economy-tick
src/                      Application, Auth, Admin, Http, Service, …
themes/                   default, admin, slate
lang/                     Locale JSON
docs/                     Architecture map + how-to guides
```

---

## Docs (for developers)

Start from **[docs/map.md](docs/map.md)**, then [patterns.md](docs/patterns.md) and [testing.md](docs/testing.md).

| Guide | When |
| --- | --- |
| [docs/setup.md](docs/setup.md) | Install wizard / first-run seeds |
| [docs/deploy.md](docs/deploy.md) | Production + TLS |
| [docs/acl.md](docs/acl.md) | Admin ACL |
| [docs/add-admin-section.md](docs/add-admin-section.md) | New admin screen |
| [docs/add-page.md](docs/add-page.md) | New public page |
| [docs/add-theme.md](docs/add-theme.md) | Child theme |
| [docs/payments.md](docs/payments.md) | Donate / webhooks |
| [docs/security.md](docs/security.md) | Security checklist |

---

## License

[MIT](LICENSE)
