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

## 2. Routes + controller

Same as before — extend `AdminController`, wire in `Application.php`.

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

Add `admin.nav.*` and section keys to `lang/en.json` and `lang/pt-BR.json`.

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
