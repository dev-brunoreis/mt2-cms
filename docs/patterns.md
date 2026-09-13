# Code patterns

Compiled conventions. Follow these instead of copying a random nearby file.

## Layers (do not invent new ones)

| Layer | Lives in | Does | Does not |
| --- | --- | --- | --- |
| Route | `PublicRoutes` / `AdminRoutes` | `addRoute(method, path, [Class, 'action'])` | Instantiate anything |
| Factory | `controller_factories.php` | `Class => fn ($app) => new Class($app->…)` | `new Repository()` |
| Controller | `src/Http/Controller/` or `…/Admin/` | CSRF, ACL, audit, `$this->t()`, redirect/view | SQL, payment capture, proto parse |
| Service | `src/Service/` | Use-case logic, uploads, ticks, ACL | `$_POST`, Twig |
| Repository | `src/Repository/` | Prepared SQL on one schema | HTTP, i18n copy (except exception **keys**) |
| Theme | `themes/{name}/` | Layout JSON + Twig | Business rules |

No new top-level folders under `src/` (`src/Ban/` is wrong). CMS tables = schema `cms`. Game tables = `account` / `player` / `common` / `log` via `Repository::database()`.

DI: construct in `src/bootstrap/installed_services.php`, expose as `Application` public props, inject in the factory. Cron scripts `new Application()` then call a service (`bin/payments-process.php`).

## HTTP

- Return `Response` (`html`, `redirect`, `json`, `text`, `xml`). Never `header()` / `echo` in controllers.
- Public render: `$this->view('layoutName', $data)` (layout JSON under `themes/*/layouts/`).
- Admin render: `$this->adminView('section-id', 'pages/….twig', $data)` (checks login, 2FA policy, **section** ACL).
- Lazy tab HTML: `adminFragment()` is login-only — call `requireAdminResourceView` first.
- POST: `assertCsrf()` or `runMassActions()`. Read fields with `FormInput::string/int`, not raw `$_POST` in new code.
- Auth: public `$this->requireAuth()` / `requireGuest()`; admin mutations `requireAdminResource` ([acl.md](acl.md)).
- Flash: `$this->flash('error'|'success', $this->t('key'))` then redirect. Exception messages are **i18n keys**; catch and `$this->t($e->getMessage())`.

## Data & SQL

- `$this->db()->fetch|fetchAll|fetchColumn|execute($sql, $params)` with `?` placeholders only.
- Identifiers through `Database::quoteIdentifier`. Raw PDO `query`/`exec` only inside `Database`.
- List columns explicitly. Public/admin rows go through `reveal()` / `revealAll()` (strips password, pin, email, ip, …).
- Game passwords stay `*SHA1(SHA1)` (Metin2). Admin passwords `password_hash`. Compare secrets with `hash_equals`.
- Schema changes: `src/Setup/migrations/NNN_name.sql` + `php bin/migrate.php`. Hot path must not create tables.

## UI

- User strings: `$this->t('dotted.key')` / `{{ t('dotted.key') }}`. Add keys to **every** shipped locale (`lang/en.json` first).
- Twig autoescape is `html`. `|raw` only on layout `slots.*`. News/tickets use `|news_html` / `|ticket_html` (already sanitized).
- Admin lists: shared grid only ([add-admin-section.md](add-admin-section.md) §6). Mass **or** row `actions` column, never both.
- Hubs (news, store, logs, settings, banners): one page, `[data-admin-tabs]`, `?tab=` + optional `?partial=1`.
- Redirects: internal paths only (`Locales::safeRedirect`). After donate POST, hop to `/donate/pay` (CSP `form-action 'self'`).

## Errors & sessions

- Unhandled exceptions → generic HTTP 500, details in `Log` only.
- Schema behind / missing `APP_KEY` → HTTP 503, logged server-side.
- Two cookies: `MT2CMS` (public) and `MT2ADMIN` (path `/admin`). Idle: admin 30 min, public 2 h.
- Rate limit file-backed (`var/rate-limit/`), fail-closed. Sensitive POSTs listed in `SecurityContractTest::RATE_LIMITED_POSTS`.

## Databases in one sentence

`Database` has no default schema; each repository calls `useDatabase()`. CMS MySQL 8 (`cms`) vs game MySQL 5.6 (`account`/`player`/`common`/`log`).
