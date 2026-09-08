# Add an admin section

Checklist for a new tab in the protected `/admin` area.

## 1. Register the section

In `src/Admin/AdminSections.php`, add an entry:

```php
[
    'id' => 'your-section',
    'path' => '/admin/your-section',
    'label' => 'admin.nav.your_section',
],
```

Keep stable `id` values — the sidebar highlights the active tab by `id`.

## 2. Routes + DI

In `src/Application.php`:

1. `addRoute` for `GET` and `POST` (if mutating) under `/admin/...`.
2. Wire the controller in `resolveController()` with admin dependencies (`AdminAuth`, `SettingsService`, etc.).

Admin routes must call `requireAdmin()` (via `AdminController::adminView()` or explicitly).

## 3. Controller

Extend `Mt2Cms\Http\Controller\AdminController`.

- `GET`: return `$this->adminView('your-section', 'pages/admin-your-section.twig', $data)`.
- `POST`: `assertCsrf()` first, validate input, persist via repository/service, flash, redirect back.

Use `$this->t('admin....')` for all copy.

## 4. Twig template

Add `themes/default/templates/pages/admin-your-section.twig`.

Match existing admin cards (rounded border, slate palette, primary blue submit button).

## 5. i18n

Add keys to **both** `lang/en.json` and `lang/pt-BR.json`:

- `admin.nav.your_section` — sidebar label
- Section-specific keys under `admin.your_section.*`

## PR checklist

- [ ] Section registered in `AdminSections`
- [ ] Routes + controller wired in `Application.php`
- [ ] GET/POST handlers with CSRF on POST
- [ ] `requireAdmin()` on every admin page
- [ ] Twig template + `en` / `pt-BR` keys
- [ ] No secrets in templates or logs
