# Docs map (start here)

Compiled index of how the CMS is wired. **Read this before grepping the repo.** Then [patterns.md](patterns.md) (how to write code) and [testing.md](testing.md) (how to lock it). Then open only the one linked feature doc.

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
| **Tests** | [testing.md](testing.md) | `tests/Unit/`, `SecurityContractTest`, opt-in `tests/Integration/` smoke |
| Admin section / grid / hubs | [add-admin-section.md](add-admin-section.md) | `AdminSections`, `AdminRoutes.php`, `src/Admin/Grid/` |
| **ACL** | [acl.md](acl.md) | `AdminResourceCatalog`, `AclService`, `AdminController` |
| Public page | [add-page.md](add-page.md) | `PublicRoutes.php`, `src/Http/Controller/` |
| Repository / SQL | [add-repository.md](add-repository.md) | `src/Repository/`, `src/Support/Database.php` |
| Payments / donate | [payments.md](payments.md) | `src/Payment/`, `PaymentCheckoutService`, `bin/payments-process.php` |
| Economy | [economy.md](economy.md) | `EconomyTickService`, `AdminEconomyController` |
| Notifications | [notifications.md](notifications.md) | `NotificationService`, `/account/notifications` |
| SEO | [seo.md](seo.md) | `SeoService`, `SeoController`, settings tab `seo`, news/events form tab `seo` |
| Security invariants | [security.md](security.md) | `tests/Unit/Contract/SecurityContractTest.php` |
| Theme overlay | [add-theme.md](add-theme.md) | `themes/{name}/`, `theme.json` features (e.g. `layout_columns`), layout JSON by node `id` |
| Locale | [add-locale.md](add-locale.md) | Shipped: `en` (`lang/en.json`). Public `POST /locale`; admin sidebar `POST /admin/locale`. Optional: `bin/i18n-deepl.php` to generate more packs |
| Game dumps / proto | [game-files.md](game-files.md) | `game/config.json`, `src/Game/` |
| Install / first boot | [setup.md](setup.md) | `SetupController`, `BannerSeedService`, `NewsSeedService`, `EventSeedService` |
| Deploy | [deploy.md](deploy.md), [game-mysql.md](game-mysql.md) | Linux CMS host (Compose or release tarball); remote game MySQL grants; `APP_KEY`, TLS; `bin/package-release.sh` |

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

Overview lives in the [README](../README.md). Auth/account: login, register, forgot/reset, verify-email, password, email, PIN, characters/unstuck, orders, payments, notifications, tickets.

Content: news + comments, events, downloads, shop buy, donate, ranking, player profile, `/status`, `/robots.txt`, `/sitemap.xml`.

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
