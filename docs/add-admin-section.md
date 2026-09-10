# Add an admin section

Admin UI lives in **`themes/admin`** (white panel, Magento-style page header).

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
| Settings | `/admin/settings/` | registration, themes, locale |

Use [`AdminPaths.php`](../src/Admin/AdminPaths.php) for paths (and `admin_path()` in Twig).

Hub pages (news, store, logs) use `[data-admin-tabs]` with lazy `?partial=1` fragments — see §5.

## 1. Register menu + submenu

In [`src/Admin/AdminSections.php`](../src/Admin/AdminSections.php), add the item under the right group `children` array. Use `AdminPaths::*()` for `path`.

```php
[
    'id' => 'your-section',
    'path' => AdminPaths::gameDataShops(), // example
    'label' => 'admin.nav.your_section',
],
```

If the section should appear in the role permissions matrix (`/admin/system/roles`), ensure its `id` is listed via `AdminSectionCatalog::grouped()` (derived from `AdminSections`).

## 2. Routes + controller

Extend `AdminController`, register routes in [`src/Http/AdminRoutes.php`](../src/Http/AdminRoutes.php), and wire the controller in [`src/Http/ControllerMap.php`](../src/Http/ControllerMap.php).

Pass page header data in `adminView()`:

```php
return $this->adminView('your-section', 'pages/your-section.twig', [
    'title' => $this->t('admin.your_section.title'),
    'pageLead' => $this->t('admin.your_section.lead'),
    'formId' => 'admin-your-section-form',
]);
```

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

Hub examples: [`AdminLogsController`](../src/Http/Controller/AdminLogsController.php), [`AdminNewsHubController`](../src/Http/Controller/AdminNewsHubController.php), [`AdminStoreHubController`](../src/Http/Controller/AdminStoreHubController.php).

## 6. List pages (admin grid)

Use the shared Magento-style grid for index/list pages — do not copy table markup.

### Controller

1. Implement `ProvidesAdminGrid` on the repository and add `gridDefinition(): GridDefinition` next to `listForGrid()` — columns, filters, and `orderBy()` map stay in one place.
2. Add `countForGrid()` / `listForGrid()` (accept `GridQuery`; use `GridSql::orderBy($query, $this->gridDefinition()->sortMap(), …)`).
3. In the controller `index()`:

```php
$spec = $this->repo->gridDefinition()->spec();
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

4. For mass actions: set `massActionPath` on the spec, register `POST /admin/…/mass`, and delegate to `runMassActions()` on `AdminController` — it handles CSRF, ID/action parsing, per-row handlers, audit logging, and the success flash. Example:

```php
public function mass(): Response
{
    return $this->runMassActions(
        $this->repo->gridDefinition()->spec(),
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

5. On mutating POST handlers, call `requireAdminSection('your-section')` (not only `requireAdmin()`).

### Twig

```twig
{% include 'components/grid.twig' with {grid: grid} %}
```

See [`.cursor/rules/admin-grid.mdc`](../.cursor/rules/admin-grid.mdc) for mass actions vs row actions.
