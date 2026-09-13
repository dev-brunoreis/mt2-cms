# Add a page

Checklist for a new public or authenticated page.

## 1. Route + DI

In `src/Http/PublicRoutes.php` or `src/Http/AdminRoutes.php`:

1. `addRoute` for the method/path → `[YourController::class, 'action']`.
2. If the controller is new, register it in `src/Http/controller_factories.php` and inject dependencies (theme, auth, csrf, translator, repositories).

Do not instantiate repositories inside the controller.

## 2. Controller

- Public: put the class in `src/Http/Controller/` and extend `Mt2Cms\Http\Controller\Controller`.
- Admin: put the class in `src/Http/Controller/Admin/` and extend `Mt2Cms\Http\Controller\Admin\AdminController`.

- Public page: return `$this->view('layoutName', $data)`.
- Auth required: call `$this->requireAuth()` first (see `AccountController`).
- Guests only: `$this->requireGuest()` (see `AuthController`).
- Every `POST`: `$this->assertCsrf()` before side effects.
- User-facing strings: `$this->t('key')` — never hardcode copy.

Example layout name must match a file under `themes/*/layouts/{name}.json`.

## 3. Layout JSON

Extend the shared shell; only override `main` (and `sidebar` for account pages):

```json
{
  "extends": "_shell",
  "slots": {
    "main": [{ "id": "content", "template": "pages/your-page.twig" }]
  }
}
```

Keep stable `id` values so child themes can override nodes by id. See [add-theme.md](add-theme.md).

## 4. Twig template

Add `themes/default/templates/pages/your-page.twig`.

- Twig autoescapes HTML; do not use `|raw` on user or DB data.
- Use `{{ t('section.key') }}` for labels.

## 5. i18n + nav

1. Add keys to `lang/en.json`.
2. If the page is public navigation, link it in `themes/default/templates/components/navbar.twig`.

## PR checklist

- [ ] Route registered
- [ ] Controller wired in `controller_factories.php` (if new)
- [ ] Layout JSON + Twig page
- [ ] `en` + `pt-BR` keys
- [ ] CSRF on POST / `requireAuth` when needed
- [ ] Unit test if you added non-trivial logic (`docs/testing.md`)
