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
| UI | Tailwind CSS (CDN) via theme shell |

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
| `CommonRepository` | `common` | GM list and locales |
| `LogRepository` | `log` | Recent login log rows |

Sensitive fields (`password`, `social_id`, `email`, `ip`, …) are stripped before public results.

## Routes

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/` | Home |
| GET/POST | `/register` | Account registration (CSRF, rate limited) |
| GET/POST | `/login` | Login (CSRF, rate limited) |
| POST | `/logout` | Logout (CSRF) |
| POST | `/locale` | Switch locale cookie (CSRF) |
| GET | `/account` | My account + characters (auth required) |
| GET | `/ranking` | Paged ranking (`?q=&page=`) |
| GET | `/player/{name}` | Public player profile |

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

## Security

HTTP responses send security headers (`X-Frame-Options`, `nosniff`, `Referrer-Policy`, CSP compatible with the Tailwind CDN). Sessions use hardened cookies and regenerate on login. Login/register are rate limited. Passwords use Metin2-compatible `*SHA1(SHA1)` hashing (game client requirement).

See [docs/security.md](docs/security.md) for the full checklist.

## Requirements

- Docker and Docker Compose
- Composer (on the host, or run inside a container that has Composer)

## Quick start

```bash
cp .env-example .env
docker compose up --build
composer install
```

| Service | URL / port |
| --- | --- |
| Site (Nginx) | http://localhost:8000 |
| Adminer | http://localhost:8080 |
| Game MySQL 5.6 | `localhost:8001` |
| CMS MySQL 8.0 | `localhost:8002` |

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

## Local dumps

`docker/mysql/backup/` ships sample Mt2 data, including accounts `admin` and `test`. Treat them as local fixtures, not production credentials.

## Current status

- Working: Docker stack, PDO layer, repositories, front controller, themes, auth/account, ranking/player pages, i18n (`en`, `pt-BR`), locale cookie, security headers, session hardening, auth rate limit.
- Not built yet: CMS MySQL usage, admin panels, password change.

## License

Unspecified. Private / local use unless you add a license.
