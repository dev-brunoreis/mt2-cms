# Add an admin section

Admin UI lives in **`themes/admin`** (white panel, Magento-style page header).

## 1. Register menu + submenu

In `src/Admin/AdminSections.php`, add the item under the right group `children` array (**Game**, **Logs**, or **Configuration**). Game log tables belong in the **Logs** group via `LogCatalog`.

```php
[
    'id' => 'your-section',
    'path' => '/admin/your-section',
    'label' => 'admin.nav.your_section',
],
```

The sidebar auto-expands **Configuration** when that section is active.

If the section should appear in the role permissions matrix (`/admin/roles`), ensure its `id` is listed in `AdminSectionCatalog::grouped()` (usually via `AdminSections` or the **Game data** group for shops/proto/drops).

## 2. Routes + controller

Extend `AdminController`, register routes in `src/Http/AdminRoutes.php`, and wire the controller in `src/Http/ControllerMap.php`.

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
    'headerHref' => '/admin/your-section/new', // optional
    'headerActionLabel' => $this->t('admin.your_section.create'),
    'grid' => $grid,
]);
```

4. For mass actions: set `massActionPath` on the spec, register `POST /admin/your-section/mass`, and delegate to `runMassActions()` on `AdminController` — it handles CSRF, ID/action parsing, per-row handlers, audit logging, and the success flash. Example:

```php
public function mass(): Response
{
    return $this->runMassActions(
        $this->repo->gridDefinition()->spec(),
        '/admin/your-section',
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

### i18n

- Shared labels: `admin.grid.*` (search, filter, pagination, mass submit).
- Section-specific: `admin.your_section.count`, `.empty`, `.search_placeholder`, mass confirm keys.

### Cell types

`text`, `muted`, `number`, `date`, `link`, `icon_link`, `badge`, `actions`, `template` (custom Twig partial). Special columns (log cells, guild master) use `type: template`.

### Actions: mass **or** row column — not both

Use **one** action model per grid:

- **Mass actions** (default for list CRUD): checkboxes + mass-action bar. Edit via a **link** column (name/title → detail). Wire `->massActions(...)`, `POST …/mass`, `runMassActions()`, and i18n `confirm_mass_*` / `mass_done`.
- **Actions column** (`type: actions`): per-row links only when the grid has **no** mass actions.

Never combine `massActions()` with an `actions` column.

Non-selectable rows set `'can_mass' => false`. String row keys: `->idField('slug')->massIdType('string')`.

Do **not** build list `<table>` markup by hand — use `components/grid.twig` only.
