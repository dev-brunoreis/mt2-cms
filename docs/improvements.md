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

---

## Organization, files, naming

### `Application.php` is the bottleneck

[`src/Application.php`](../src/Application.php) (~830 lines) owns routes, session, bootstrap, and a huge `match` for DI. Every new section touches it twice (route + `resolveController`).

**Fix:** split `PublicRoutes` / `AdminRoutes` (or `src/Http/routes.php`) and a small factory map instead of the manual `match`. Do this before the next large admin section.

### Controllers mixed in one folder

29 classes in `src/Http/Controller/` (public + `Admin*`). The prefix works; the folder will not.

Suggested layout:

```
src/Http/Controller/          # public
src/Http/Controller/Admin/    # panel
```

### Fat controllers

- `AdminItemShopController` (~770 lines): products + categories + orders + item search
- `AdminNewsController`: posts + comments + settings + upload
- `AdminSettingsController`: registration + themes + locale

Split by resource when those screens are next touched (`AdminItemShopProductsController`, `…Categories`, `…Orders`).

### Grid UI lives in repositories

`gridDefinition()` on `AccountRepository`, `NewsRepository`, etc. mixes hrefs, badge CSS, and i18n keys with SQL.

Better split:

- Repository: `countForGrid` / `listForGrid` + SQL `sortMap()`
- Definition: `src/Admin/Grid/Definitions/AccountsGrid.php` (or the controller)

`ProvidesAdminGrid` only requires `gridDefinition()`. If it is the contract, add `countForGrid` / `listForGrid` (or drop the interface).

Two ways to build a spec today:

- News/accounts: definition on the repository
- Logs, proto, item-shop products: spec built in the controller (`logGridSpec`, `protoGridSpec`, `productsGridSpec` for dynamic filter options)

Padronize: same place for the definition; use `GridDefinition::filterOptions()` for dynamic selects (already exists).

### Dead or misleading names

| Current | Issue |
| --- | --- |
| `countForAdmin` / `listForAdmin` | Wrappers around `*ForGrid`. Callers are gone except `LogRepository` and one awards lookup. Remove. |
| `GameProtoService::page()` | Overlaps `countForGrid` / `listForGrid`. Still used by item-shop category item search. |
| `AdminController::gridView()` | Dead — `GridRunner` already builds the view. |
| `CommonRepository` | GMs + hosts. Rename to `GmRepository`. |
| `GridUrl` | Hardcoded `limit !== 20` and `sort !== 'id'`. Tickets/logs use other defaults, so URLs are noisy or drop sort. Use `spec->defaultPerPage` / `defaultSort`. |

### Front-end JS is a flat folder

`public/js/` mixes `admin-grid.js`, `locale-switcher.js`, `shop-category-tree.js`. Split `public/js/admin/` vs `public/js/site/` when moving assets.

### i18n

Only `lang/en.json`. `implementing.mdc` still says “both `lang/en.json`” (leftover). When PT-BR lands, split by namespace (`admin.json`, `auth.json`) or `lang/en/*.json`.

### Schema as inline SQL

[`src/Setup/CmsSchema.php`](../src/Setup/CmsSchema.php) has all `CREATE TABLE` inline and no schema version table. Required before any `ALTER` (roles, audit log).

---

## Expanding systems

The shared grid is the right list primitive. To add sections without growing `Application.php` and stuffing UI into repositories, lock these contracts.

### Module boundary

Example for a new section (bans, events, cash log):

```
src/Ban/
  BanService.php
  BanRepository.php
src/Http/Controller/Admin/AdminBansController.php
src/Admin/Grid/Definitions/BansGrid.php
themes/admin/templates/pages/bans.twig
```

Register routes in the route list, not by editing a 300-line `addRoute` block. Menu stays in `AdminSections`; section `id` should match the permission key once RBAC exists.

### Request helper

Controllers still read `$_GET` / `$_POST` directly. `GridRequest` already wraps query/mass POST. The same idea should cover forms (`string()`, `int()`, `postArray()`) so tests do not need a full front controller.

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

`GameProtoService` loads the whole proto file to filter/paginate. Large files will hurt. Cache an index (`var/cache/proto-index.json` or SQLite) for list columns; the form still opens the full row.

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

Do these as separate changes. Do not mix a rename pass with a security change.

1. **Grid leftovers** — delete unused `*ForAdmin` / `gridView()`, fix `GridUrl` defaults, keep definitions in one pattern. Update [add-admin-section.md](add-admin-section.md).
2. **Split `Application.php`** (routes + DI factories) before the next large section.
3. **Split fat controllers** (item shop, news) when those screens are edited anyway.

Done (production hardening): file-backed rate limit, RBAC, audit log, admin session cookie split, self-hosted assets/CSP, migrations off hot path. See [deploy.md](deploy.md).

Later / opportunistic: `CommonRepository` → `GmRepository`, `public/js` split, i18n file split, proto index, PHPUnit, nested grids on character/guild.

---

## Checklist for a new admin list (today)

Until phase 2 lands, still follow [add-admin-section.md](add-admin-section.md):

- [ ] `gridDefinition()` + `countForGrid` / `listForGrid` (do **not** add new `countForAdmin` / `listForAdmin`)
- [ ] `GridSql::orderBy` with a hardcoded `sortMap`
- [ ] `GridRunner::fetch` in the controller
- [ ] Mass POST: `assertCsrf()`, ids via `gridMassIds()`, action in a `match` that only allows spec actions
- [ ] Audit write once the log table exists (phase 1)
- [ ] Translation keys in `lang/en.json`
- [ ] Route + DI in `Application.php` (until phase 3)
