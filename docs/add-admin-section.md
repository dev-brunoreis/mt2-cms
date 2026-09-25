# Add an admin section

Admin UI lives in **`themes/admin`** (white panel, Magento-style page header). Architecture index: [map.md](map.md). ACL details: [acl.md](acl.md).

## URL layout

Admin routes use area prefixes:

| Area | Prefix | Examples |
|------|--------|----------|
| Overview | `/admin` | Dashboard |
| Game | `/admin/game/` | accounts, characters, guilds, awards |
| Content | `/admin/content/` | news (hub), tickets |
| Store | `/admin/store/` | categories + orders (hub at `/admin/store`; products live in a category) |
| Game data | `/admin/game-data/` | shops, refine, drops, items, mobs, gms |
| Logs | `/admin/logs` | Hub with `?tab={logId}` |
| System | `/admin/system/` | admins, roles, audit-log |
| Settings | `/admin/settings` | Hub with `?tab=` for registration, themes, locale, security, community, unstuck, banners |

Use [`AdminPaths.php`](../src/Admin/AdminPaths.php) for paths (and `admin_path()` in Twig).

Hub pages (news, store, logs, settings) use `[data-admin-tabs]` with lazy `?partial=1` fragments — see §5.

## 1. Register menu + submenu

Admin navigation and ACL use related but separate catalogs:

| Class | Edit when |
|---|---|
| [`AdminSections`](../src/Admin/AdminSections.php) | Sidebar menu, breadcrumbs, role seed section lists, super-only ids |
| [`AdminPaths`](../src/Admin/AdminPaths.php) | URL helpers (`admin_path()` / PHP) |
| [`AdminResourceCatalog`](../src/Admin/AdminResourceCatalog.php) | Assignable ACL permissions (role form tree) |
| [`AdminPermissions`](../src/Admin/AdminPermissions.php) | Super role constant only |
| [`AdminRuntime`](../src/Admin/AdminRuntime.php) | Settings bind for Twig/helpers (not a catalog) |

In [`AdminSections.php`](../src/Admin/AdminSections.php), add the item under the right group `children` array. Use `AdminPaths::*()` for `path`. Set `'pinned' => true` on a group to keep it fixed in the sidebar footer (next to the admin user) instead of the scrollable menu.

The scrollable `.admin-sidebar-nav` keeps its scroll position across page loads (`public/js/admin/admin-sidebar.js` + `sessionStorage`). The script loads after the sidebar footer so restore is not clamped, and it saves on `pointerdown` (not `scroll`) so a focused link does not nudge the stored position. Do not call `scrollIntoView` on the active item.

```php
[
    'id' => 'your-section',
    'path' => AdminPaths::gameDataShops(), // example
    'label' => 'admin.nav.your_section',
],
```

If the section should appear in the sidebar, add it to `AdminSections`. For **assignable permissions**, add the resource tree under [`AdminResourceCatalog::tree()`](../src/Admin/AdminResourceCatalog.php) (role form uses the full tree, including `navHidden` game-data modules).

## 2. Routes + controller

Extend `AdminController`, register routes in [`src/Http/AdminRoutes.php`](../src/Http/AdminRoutes.php), and add one factory entry in [`src/Http/controller_factories.php`](../src/Http/controller_factories.php).

Pass page header data in `adminView()`:

```php
return $this->adminView('your-section', 'pages/your-section.twig', [
    'title' => $this->t('admin.your_section.title'),
    'pageLead' => $this->t('admin.your_section.lead'),
    'formId' => 'admin-your-section-form',
]);
```

The layout breadcrumb is clickable (`Administration` → dashboard, group → first item, section → list). Nested pages (edit/create/detail) also get a **Back** button to the section list. Override with `'backHref' => '…'` or `'backHref' => null` to hide it.

## 3. Twig page

- Form gets `id="admin-your-section-form"` matching `formId`
- **No** title or Save button inside the form — the layout `page-header.twig` renders title + Save (via `form` attribute on the button)

## 4. i18n

Add `admin.nav.*` and section keys to `lang/en.json`.

## 5. Tabs (optional)

Use `[data-admin-tabs]` when a form has more than one section. Keep **one** `<form>` wrapping every panel so hidden tabs still submit.

```twig
<form id="admin-your-section-form" data-admin-tabs data-default-tab="dados" class="admin-panel admin-tabs overflow-hidden">
    <div class="admin-tabs-nav" role="tablist" aria-label="{{ t('admin.tabs.label') }}">
        {% include 'components/tab.twig' with { id: 'dados', label: t('admin.your_section.tab_data'), active: true } %}
        {% include 'components/tab.twig' with { id: 'extra', label: t('admin.your_section.tab_extra'), active: false } %}
    </div>

    <div id="admin-tab-panel-dados" class="admin-tabs-panel" role="tabpanel" data-tab-panel="dados" aria-labelledby="admin-tab-dados">
        {# editable fields #}
    </div>

    <div id="admin-tab-panel-extra" class="admin-tabs-panel" role="tabpanel" data-tab-panel="extra" aria-labelledby="admin-tab-extra" hidden>
        {# more fields or read-only tables #}
    </div>
</form>
```

`admin-tabs.js` switches panels, keeps the active tab in `?tab=` (so F5 stays on it), and marks a tab dirty (orange dot) when its fields change. Nested tab groups (inventory pages) do not write the query string. Do not `disable` fields in hidden panels — they would drop out of the POST.

Read-only panels that are expensive to build (inventory, logs, drops) can stay empty until opened: set `data-tab-src="/admin/…?tab=items&partial=1"` and only query that data when `tab` matches. The script fetches the fragment on first click. Keep editable form fields in the DOM so Save still posts every tab.

Hub `*BaseUrl` must be the path **without** `?tab=` (or use `admin_path('logs', tab.id) ~ '&partial=1'`). Concatenating `?tab=` onto a URL that already has `?tab=` makes every lazy panel load the default tab.

In-form tabs (one form, every panel still POSTs): news and events CRUD (`data` + `seo`). Hub examples: [`AdminLogsController`](../src/Http/Controller/Admin/AdminLogsController.php), [`AdminNewsHubController`](../src/Http/Controller/Admin/AdminNewsHubController.php), [`AdminStoreHubController`](../src/Http/Controller/Admin/AdminStoreHubController.php), [`AdminSettingsController`](../src/Http/Controller/Admin/AdminSettingsController.php).

## 6. List pages (admin grid)

Use the shared Magento-style grid for index/list pages — do not copy table markup.

### Controller

1. Add `src/Admin/Grid/Definitions/YourSectionGrid.php` with `public static function definition(): GridDefinition` (columns, filters, `orderBy` map).
2. Repository implements `ProvidesAdminGrid` with `countForGrid()` / `listForGrid()` only. Use `YourSectionGrid::definition()->sortMap()` in `GridSql::orderBy` and `->filterSql()` in `GridSql::where`.
3. Column filters are inferred under each header (Magento 1 style): badges/bools/jobs/empires/proto tokens become selects, dates become date inputs, everything else is text. Extra toolbar filters are only for keys that are not a column (`range`, log `from`/`to`). Set `'filter' => false` or `'filterSql' => false` on computed columns. There is no global search box — `searchable(false)` means “do not auto-filter columns” (dashboard).
3. In the controller `index()`:

```php
$spec = YourSectionGrid::definition()->spec();
$query = $this->gridQuery($spec);
$grid = GridRunner::fetch(
    $spec,
    $query,
    fn ($q) => $this->repo->countForGrid($q),
    fn ($q) => $this->repo->listForGrid($q),
);

return $this->adminView('your-section', 'pages/your-section.twig', [
    'title' => $this->t('admin.your_section.title'),
    'pageLead' => $this->t('admin.your_section.lead'),
    'headerHref' => AdminPaths::yourSectionNew(),
    'headerActionLabel' => $this->t('admin.your_section.create'),
    'grid' => $grid,
]);
```

4. For mass actions: set `massActionPath` on the definition, register `POST /admin/…/mass`, and delegate to `runMassActions()` on `AdminController` — it handles CSRF, ID/action parsing, per-row handlers, audit logging, and the success flash. Example:

```php
public function mass(): Response
{
    return $this->runMassActions(
        YourSectionGrid::definition()->spec(),
        AdminPaths::yourSection(),
        [
            'delete' => fn (int $id): bool => $this->repo->delete($id),
        ],
        'your_entity',
        'admin.your_section.mass_done',
        'your-section',
    );
}
```

5. On mutating POST handlers, call `requireAdminResource('area/module/action')` (view/list can keep `adminView('section-id', …)` which checks section-level access). Mass actions pass the `…/mass` resource as the last argument to `runMassActions()`.
6. In Twig, gate buttons with `{% if acl_allowed('area/module/create') %}`.

## 7. ACL resources

See [acl.md](acl.md) for the full contract. Resource IDs follow `{area}/{module}/[{entity}/]{action}` (see `AdminResourceCatalog`). Examples:

| Action | Resource |
|--------|----------|
| Grid / hub tab | `your-area/your-module/view` |
| Create POST | `…/create` |
| Edit POST | `…/edit` |
| Delete | `…/delete` |
| Mass grid | `…/mass` |

Hub tabs: check `requireAdminResourceView('…/view')` per tab; hide tabs with `acl_allowed()` in the hub Twig. Use `resolveResourceTab()` when the default tab may be denied.

Legacy `acl_*_sections` tables remain for migration rollback; runtime ACL reads `acl_role_resources` / `acl_admin_resources`.

### Twig

```twig
{% include 'components/grid.twig' with {grid: grid} %}
```

Clickable images (banner thumbs): wrap with `components/image-preview-trigger.twig`. `admin-image-preview.js` opens the dialog in `layouts/panel.twig`. Banners pass `full_url` (original) to the dialog and `preview_url` to the thumb.

See [`.cursor/rules/admin-grid.mdc`](../.cursor/rules/admin-grid.mdc) for mass actions vs row actions.
