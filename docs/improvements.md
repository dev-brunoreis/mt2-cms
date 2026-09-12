# Improvements

Notes from a codebase review: what is already solid, what to fix, and in which order. Do not treat this as a single PR — each phase should ship on its own.

Related guides: [security.md](security.md), [add-admin-section.md](add-admin-section.md), [add-page.md](add-page.md), [add-repository.md](add-repository.md).

## Already solid

Keep these intact while changing anything else.

- SQL: PDO prepared statements, `ATTR_EMULATE_PREPARES = false`, identifiers through `Database::quoteIdentifier`
- XSS: Twig autoescape; `|raw` only on layout slots already rendered by Twig
- CSRF on mutating POSTs (`assertCsrf()` + `_csrf`)
- Auth: `hash_equals` for game passwords; admin uses `password_hash`; `reveal()` strips secrets
- Open redirects: `Locales::safeRedirect`
- Session cookie flags (`HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS); `session_regenerate_id(true)` on login
- Response security headers (including CSP)
- Ticket uploads: real MIME check, random stored name, files outside `public/`
- HTML sanitizer for news/tickets (blocks `javascript:`, protocol-relative URLs, event handlers)
- Admin panel is a separate theme and auth (`AdminAuth` vs `Auth`)
- Shared Magento-style admin grid (`src/Admin/Grid/*` + `components/grid.twig`) is the right list primitive

Game account hashes must stay Metin2-compatible (`*SHA1(SHA1)`) unless there is an explicit client migration plan.

---

## Security

### 1. ~~Rate limiter is session-backed~~ — done

File-backed limiter in `src/Auth/RateLimiter.php` (`var/rate-limit/`). Fail-closed when storage is unavailable.

### 2. ~~Every admin is a superadmin~~ — done

RBAC via `AclService`, `admin_roles`, resource-based ACL. Super-only routes for admins, roles, audit log.

### 3. ~~No admin audit trail~~ — done

`admin_audit_log` + `AdminAuditService` on mutating admin POSTs.

### 4. ~~Player and admin share one PHP session~~ — done

Separate cookies: `MT2CMS` (path `/`) and `MT2ADMIN` (path `/admin`). See `SessionConfig`.

### 5. ~~CSP allows jsDelivr~~ — done

Tailwind built to `public/css/app.css`; TinyMCE vendored under `public/vendor/tinymce/`. CSP is `'self'` only for scripts/styles.

### 6. ~~`CmsSchema::ensure()` on every request~~ — done

Migrations via `php bin/migrate.php` and `/setup` only. Hot path checks schema version and returns 503 if behind.

### 7. ~~Grid SQL and mass actions (defense in depth)~~ — done

`GridSql::orderBy` still concatenates expressions from hardcoded `sortMap()` only — keep that rule on new grids.

Mass actions are whitelisted via `AdminController::runMassActions()` + `isAllowedMassAction()` against `GridSpec::$massActions`.

### 8. Intentionally out of scope (same as security.md)

- HSTS (Compose serves HTTP on `:8000`)
- WAF
- Replacing the game-compatible password hash

Captcha (self-hosted SVG) and admin TOTP 2FA are implemented — see `/admin/settings/security` and `/admin/account/security`.

### 9. ~~Production runtime gaps~~ — done

- Global exception handler (generic 500, logged server-side)
- Idle session timeout (`SessionGuard`: admin 30 min, public 2 h)
- PHP hardening ini in Docker image (`display_errors=Off`)
- Nginx denies PHP under `/uploads/`
- Compose site bound to loopback (`127.0.0.1:8000`)
- `APP_KEY` + encrypted admin TOTP secrets (`AppCrypto`)
- Optional admin 2FA (off by default on new installs)
- Player self-service password change (`/account/password`)

---

## Organization, files, naming

### ~~`Application.php` is the bottleneck~~ — done

Routes: [`PublicRoutes.php`](../src/Http/PublicRoutes.php) / [`AdminRoutes.php`](../src/Http/AdminRoutes.php). Controllers: [`controller_factories.php`](../src/Http/controller_factories.php). Service wiring: [`src/bootstrap/`](../src/bootstrap/).

### ~~Controllers mixed in one folder~~ — done

Public controllers live in [`src/Http/Controller/`](../src/Http/Controller/); admin panel controllers in [`src/Http/Controller/Admin/`](../src/Http/Controller/Admin/).

### ~~Fat controllers~~ — done

Item shop split into Products / Categories / CategoryProducts / Orders (+ Base). News split into Posts / Comments / Settings / Hub. `AdminSettingsController` (~214 lines) stays as one screen.

### ~~Grid UI lives in repositories~~ — done

Definitions live in [`src/Admin/Grid/Definitions/`](../src/Admin/Grid/Definitions/). Repositories implement `countForGrid` / `listForGrid` only. Controllers call `XxxGrid::definition()`. Exception: dynamic log specs in `AdminLogsController`. Proto uses `ProtoGrid`.

### Dead or misleading names

| Current | Issue |
| --- | --- |
| ~~`countForAdmin` / `listForAdmin`~~ | Removed — use `*ForGrid` only. |
| ~~`AdminController::gridView()`~~ | Removed — `GridRunner` builds the view. |
| ~~`CommonRepository`~~ | Renamed to `GmRepository`. |
| ~~`GridUrl` defaults~~ | Uses `spec->defaultPerPage` / `defaultSort` / `defaultDir`. |
| ~~`GameProtoService::page()`~~ | Removed — use `countForGrid` / `listForGrid`. |

### Front-end JS is a flat folder

~~`public/js/` flat folder~~ — split into `public/js/admin/` and `public/js/site/`.

### i18n

Only `lang/en.json`. When PT-BR lands, split by namespace (`admin.json`, `auth.json`) or `lang/en/*.json` and extend `Translator` / `Locales` (single-file today).

### ~~Schema as inline SQL~~ — done

[`CmsSchema.php`](../src/Setup/CmsSchema.php) orchestrates; SQL lives in [`src/Setup/migrations/`](../src/Setup/migrations/).
---

## Expanding systems

The shared grid is the right list primitive. To add sections without growing `Application.php` and stuffing UI into repositories, lock these contracts.

### Module boundary

Application code is layered: repositories in `src/Repository/`, services in `src/Service/`, admin controllers in `src/Http/Controller/Admin/`. Do not add new top-level feature folders (e.g. `src/Ban/`).

Example for a new section (bans, events, cash log):

```
src/Repository/BanRepository.php
src/Service/BanService.php
src/Http/Controller/Admin/AdminBansController.php
src/Admin/Grid/Definitions/BansGrid.php
themes/admin/templates/pages/bans.twig
```

Register routes in the route list, not by editing a 300-line `addRoute` block. Menu stays in `AdminSections`; section `id` should match the permission key once RBAC exists.

### Request helper

Form helpers: [`FormInput`](../src/Http/FormInput.php) (`string()`, `int()`, `postArray()`). Prefer over raw `$_POST` in new code.

### Mass-action dispatcher

Seven `mass()` methods are the same shape: CSRF, ids, `match`, flash, redirect. Collapse to something like:

```php
return $this->runMassActions($spec, [
    'block' => fn (int $id) => $this->accounts->block($id),
    'delete' => fn (int $id) => $this->accounts->delete($id),
]);
```

Action whitelist comes from the spec.

### Detail pages are a second UI

[`themes/admin/templates/pages/character.twig`](../themes/admin/templates/pages/character.twig) (tabs + lazy partials) is fine. Nested lists (character logs, guild wars, comments) are still one-off tables. Next step: reuse `grid.twig` in fragments (`partial=1`), not a third table markup.

### Proto / drops do not fit the SQL grid

Proto list/filter uses [`ProtoIndexCache`](../src/Game/Proto/ProtoIndexCache.php) (`var/cache/proto-index-*.json`); forms still read full rows from tab files.

### Tests

PHPUnit is set up (`composer test`). Current unit coverage:

- `GridRequest` (sort/limit whitelist, mass ids)
- `RateLimiter` (file-backed hits/window)
- `Csrf`
- `AclService`

Still useful to add later: `GridSql`, `HtmlSanitizer`, `TicketUploadService`, `Locales::safeRedirect`, integration tests with MySQL.

### Plugins / events

Everything is a concrete class in `resolveController()`. An event dispatcher (`AccountBlocked`, `NewsPublished`) only pays off if third-party modules/themes are a goal. Skip until then.

---

## Suggested order

Organization backlog below is largely done (DI bootstrap, proto `page()` removal, grid Definitions, Categories split, catalog docs). Remaining opportunistic work:

1. Nested read-only admin tables → `grid.twig` fragments
2. i18n multi-file when PT-BR lands (`Translator` / `Locales`)
3. Optionally fold `AdminSectionCatalog` helpers into Sections/ResourceCatalog (do not merge ACL catalogs casually)

Done (production hardening): file-backed rate limit, RBAC, audit log, admin session cookie split, self-hosted assets/CSP, migrations off hot path, exception handler, idle sessions, `APP_KEY`/encrypted TOTP, 2FA defaults, player password change. See [deploy.md](deploy.md).

Done: `GmRepository` rename, JS split, proto index cache, `FormInput`, controller factories, public vs Admin controller folders, Model→Support, feature folders→Repository/Service, Application DI bootstrap, grid Definitions, Categories/CategoryProducts split, game-data nav, PayPal hardening, production Compose/CI/backups.

---

## Checklist for a new admin list (today)

Follow [add-admin-section.md](add-admin-section.md):

- [ ] `src/Admin/Grid/Definitions/YourSectionGrid.php` + `countForGrid` / `listForGrid` on the repository
- [ ] `GridSql::orderBy` with `YourSectionGrid::definition()->sortMap()`
- [ ] `GridRunner::fetch` in the controller
- [ ] Mass POST: `runMassActions()` (or `assertCsrf()` + whitelist from the spec)
- [ ] Audit via `audit()` / `auditChange()` / `runMassActions()`
- [ ] Translation keys in `lang/en.json`
- [ ] Route in `AdminRoutes.php` + factory in `controller_factories.php`
