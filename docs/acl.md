# Admin ACL

Resource-based permissions (Magento-style ids). Runtime reads `acl_role_resources` / `acl_admin_resources`. Legacy `acl_*_sections` exist only for migration rollback.

Full how-to for a new section: [add-admin-section.md](add-admin-section.md). Catalog source of truth: `src/Admin/AdminResourceCatalog.php`.

## Who is allowed

| Principal | Rule |
| --- | --- |
| Role `super` | Always allowed (`AdminPermissions::isSuper`) |
| Other roles | IDs granted on the role (prefix match: `game/accounts` grants `game/accounts/edit`) |
| Admin with `use_custom_acl` | Per-admin resource list instead of the role |
| `system/*` | Super-only (`AdminResourceCatalog::isSuperOnly`) — not assignable |

`AclService::canAccess($admin, $sectionId)` is **section-level** (sidebar + `adminView()`). Mutations need a **resource** check.

## Resource ids

`{area}/{module}/[{entity}/]{action}`

Typical actions: `view`, `create`, `edit`, `delete`, `mass`, plus verbs like `block`, `unstuck`, `reply`.

Examples: `game/economy/edit`, `content/news/posts/view`, `store/payments/edit`, `settings/seo/view`, `logs/loginlog/view`.

Hubs (news, store, logs, settings, banners) expose **per-tab** view resources. Use `resolveResourceTab()` so a denied default tab is not forced.

## Required checks

| Surface | Call |
| --- | --- |
| List / page GET | `adminView('section-id', …)` → `denyUnlessCanAccess` |
| Hub tab GET | `requireAdminResourceView('area/module/view')` (403 on `?partial=1`) |
| Mutating POST | `requireAdminResource('area/module/action')` |
| JSON POST with more than one write resource | `requireAnyAdminResource([...])` then return 401/403 JSON |
| Mass POST | `runMassActions(..., $massResource)` (already calls `requireAdminResource`) |
| Super-only (admins, roles, audit) | `requireAdminSection('admins'|'roles'|…)` or `adminView` on those sections |
| Own 2FA | `/admin/account/security` — login only, no resource |
| Twig buttons / tabs | `{% if acl_allowed('area/module/create') %}` |

`adminView()` is **not** enough for POST. A user who can open the grid (`view`) must not save without `edit` / `create` / `mass`.

JSON endpoints (e.g. news/event image upload) must still check a write resource and return 401/403 JSON — do not skip ACL because the handler is not an HTML form.

`adminFragment()` only checks login. Call `requireAdminResourceView` (or `adminView`) **before** rendering a partial.

## New admin POST checklist

1. Add the id to `AdminResourceCatalog::tree()` (and `sectionIdForResource` / `sectionResourcePrefix` / `pathForResource` if it is a new module).
2. Gate the handler with `requireAdminResource` / `runMassActions` / `requireAdminSection`.
3. Hide the control in Twig with `acl_allowed()`.
4. Keep `tests/Unit/Contract/SecurityContractTest.php` green (`testAdminPostHandlersCheckAcl`).

Skip ACL only for: admin login, logout, 2FA verify, own TOTP enroll/confirm.

## Tests

- `tests/Unit/Service/AclServiceTest.php` — super, custom ACL, prefix match
- `tests/Unit/Admin/AdminResourceCatalogTest.php` — known ids and section mapping
- `SecurityContractTest::testAdminPostHandlersCheckAcl` — every `POST /admin/*` must reach an ACL helper
