# Add an admin section

Admin UI lives in **`themes/admin`** (white panel, Magento-style page header).

## 1. Register menu + submenu

In `src/Admin/AdminSections.php`, add the item under the **Configuration** group `children` array:

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
