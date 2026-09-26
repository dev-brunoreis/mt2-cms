# Docs map (start here)

Compiled index of how the CMS is wired. **Read this before grepping the repo.** Then [patterns.md](patterns.md) (how to write code) and [testing.md](testing.md) (how to lock it). Then open only the one linked feature doc. Pull requests: [CONTRIBUTING.md](../CONTRIBUTING.md).

When behavior changes, update this file **and** the linked doc in the same turn.

## Request flow

```
public/index.php
  → ComposerAutoload            `src/bootstrap/autoload.php` — HTTP 503 if `vendor/` is missing
  → Application::run()          session, DI (`src/bootstrap/`), FastRoute
  → PublicRoutes / AdminRoutes  path → [Controller, method]
  → controller_factories.php    constructor DI
  → Controller                  CSRF / ACL / audit
  → Service / Repository        CMS (`cms`) or game schemas
  → Twig                        themes/default or themes/admin
```

Cron (not the HTTP hot path): `bin/migrate.php`, `bin/payments-process.php`, `bin/economy-tick.php`.

## Do not mix these catalogs

| Class | Job |
| --- | --- |
| `AdminSections` | Sidebar, breadcrumbs, super-only section ids |
| `AdminPaths` | URL helpers (`admin_path()` / PHP) |
| `AdminResourceCatalog` | Assignable ACL ids (`{area}/{module}/[{entity}/]{action}`) |
| `AdminPermissions` | Super role constant only |
| `LogCatalog` | Log hub tabs (also feeds ACL `logs/{tab}/view`) |

New admin screen: menu in `AdminSections`, path in `AdminPaths`, **permissions in `AdminResourceCatalog`**, routes in `AdminRoutes.php`. Missing the catalog = the page exists but cannot be granted.

## Feature index

| Area | Read | Key files |
| --- | --- | --- |
| **How code is written** | [patterns.md](patterns.md) | layers, `Response`, `FormInput`, SQL, i18n |
| **Tests** | [testing.md](testing.md) | `tests/Unit/`, `SecurityContractTest`, opt-in `tests/Integration/` smoke; CI unit + Psalm taint (`.github/workflows/`) |
| Admin section / grid / hubs | [add-admin-section.md](add-admin-section.md) | `AdminSections`, `AdminRoutes.php`, `src/Admin/Grid/`, `themes/admin`, `public/js/admin/admin-sidebar.js` (keeps sidebar scroll) |
| **ACL** | [acl.md](acl.md) | `AdminResourceCatalog`, `AclService`, `AdminController` |
| Public page | [add-page.md](add-page.md) | `PublicRoutes.php`, `src/Http/Controller/` |
| Repository / SQL | [add-repository.md](add-repository.md) | `src/Repository/`, `src/Support/Database.php` |
| Payments / donate | [payments.md](payments.md) | `src/Payment/`, `PaymentCheckoutService`, `bin/payments-process.php` |
| Economy | [economy.md](economy.md) | `EconomyTickService`, `AdminEconomyController` (alert ack `back` via `Locales::safeRedirectUnder`) |
| Notifications | [notifications.md](notifications.md) | `NotificationService`, `/account/notifications` |
| SEO | [seo.md](seo.md) | `SeoService`, `SeoController`, settings tab `seo`, news/events form tab `seo` |
| Security invariants | [security.md](security.md) | `tests/Unit/Contract/SecurityContractTest.php` |
| Theme overlay | [add-theme.md](add-theme.md) | Author guide: child overlay, data globals, tutorials. `themes/{name}/` (`^[A-Za-z0-9_-]+$`), `theme.json` `layout_columns`, layout JSON by node `id`, `/theme-assets/` via nginx or `ThemeAssetController`. Shipped example: `themes/starter` (parent `default`). Default footer keeps an "Mt2 CMS" credit. Admin panel footer links to GitHub Sponsors |
| Locale | [add-locale.md](add-locale.md) | Shipped: `en` (`lang/en.json`). Public `POST /locale`; admin sidebar `POST /admin/locale`. Codes `^[A-Za-z0-9_-]+$`. Optional: `bin/i18n-deepl.php` to generate more packs |
| Game dumps / proto | [game-files.md](game-files.md) | `game/config.json` + `schema/` ship; proto, drops, and client files are copied by the operator (`game/README.md`, 40.250 reference). Icons are TGA under `game/client/icon/`; PNG is generated on request |
| Install / first boot | [setup.md](setup.md) | `SetupController`, `SetupInstaller`, `SetupRequirements` (`bin/check-requirements.php` / `composer check`), `SetupDatabaseDefaults`, `BannerSeedService`, `NewsSeedService`, `EventSeedService` |
| Deploy | [deploy.md](deploy.md), [game-mysql.md](game-mysql.md) | Linux CMS host (Compose or `dist/` from `bin/package-release.sh`); GitHub Release on version tags such as `1.0.0-beta.0` (`.github/workflows/release.yml`); release `game/` has schema only (no proto/client dumps); dev MySQL SQL is operator-supplied under `docker/mysql/backup/`; remote game MySQL grants; `APP_KEY`, TLS |

## Admin URL areas

| Prefix | What |
| --- | --- |
| `/admin` | Dashboard |
| `/admin/population` | Online / census |
| `/admin/game/` | accounts, characters, guilds, awards, bans, referrals, economy |
| `/admin/content/` | news hub, tickets, downloads, banners, events |
| `/admin/store/` | item shop hub, packages, payments |
| `/admin/game-data/` | shops, refine, drops, items, mobs, gms |
| `/admin/logs` | Hub `?tab=` |
| `/admin/system/` | admins, roles, audit-log (super-only) |
| `/admin/settings` | Hub `?tab=` (registration, themes, locale, security, community, seo, payment-methods, unstuck, news, banners) |
| `/admin/account/security` | Own TOTP (no ACL resource) |

## Public surfaces

Auth/account: login, register, forgot/reset, verify-email, password, email, PIN, characters/unstuck, orders, payments, notifications, tickets.

Content: news + comments, events, downloads, shop buy, donate, ranking, player profile (optional equipment with MySQL-lag caveat), `/status`, `/robots.txt`, `/sitemap.xml`.

First HTTP boot after `/setup` seeds class banners, one published welcome news post, and classic Metin2 events when those tables are empty (see [setup.md](setup.md)).

## Change → update

| You touched | Also update |
| --- | --- |
| Routes, menus, DI, new module | this map + the feature doc |
| Layering / HTTP / SQL / i18n convention | [patterns.md](patterns.md) |
| New test kind, skip list, or `composer test` workflow | [testing.md](testing.md) |
| New production behavior | unit test under `tests/Unit/` (see [testing.md](testing.md)) |
| `AdminResourceCatalog` / admin POST / Twig buttons | [acl.md](acl.md) + catalog tree |
| Grid list page | [add-admin-section.md](add-admin-section.md) §6 |
| PayPal, webhooks, cash credit | [payments.md](payments.md) |
| Economy tick / alerts | [economy.md](economy.md) |
| In-app notices | [notifications.md](notifications.md) |
| Meta / sitemap / robots | [seo.md](seo.md) |
| Install wizard / first-run seeds | [setup.md](setup.md) |
| Remote game MySQL / grants | [game-mysql.md](game-mysql.md) |
| Production Linux / Compose | [deploy.md](deploy.md) |
| CSRF, SQL, audit, rate limit | [security.md](security.md) + keep `SecurityContractTest` green |

How-to checklists stay in the `add-*.md` guides. This folder is **how it works now**, not a second implementation stack.
