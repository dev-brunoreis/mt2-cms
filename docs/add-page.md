# Add a page

Checklist for a new public or authenticated page.

## 1. Route + DI

In `src/Application.php`:

1. `addRoute` for the method/path → `[YourController::class, 'action']`.
2. If the controller is new, register it in `resolveController()` and inject dependencies (theme, auth, csrf, translator, repositories).

Do not instantiate repositories inside the controller.

## 2. Controller

Extend `Mt2Cms\Http\Controller\Controller`.

- Public page: return `$this->view('layoutName', $data)`.
- Auth required: call `$this->requireAuth()` first (see `AccountController`).
- Guests only: `$this->requireGuest()` (see `AuthController`).
- Every `POST`: `$this->assertCsrf()` before side effects.
- User-facing strings: `$this->t('key')` — never hardcode copy.

Example layout name must match a file under `themes/*/layouts/{name}.json`.

## 3. Layout JSON

Copy `themes/default/layouts/home.json` as a starting point:

```json
{
  "id": "root",
  "template": "layouts/shell.twig",
  "slots": {
    "header": [{ "id": "navbar", "template": "components/navbar.twig" }],
    "sidebar": [],
    "main": [{ "id": "content", "template": "pages/your-page.twig" }],
    "footer": [{ "id": "footer", "template": "components/footer.twig" }]
  }
}
```

Keep stable `id` values so child themes can override nodes by id.

## 4. Twig template

Add `themes/default/templates/pages/your-page.twig`.

- Twig autoescapes HTML; do not use `|raw` on user or DB data.
- Use `{{ t('section.key') }}` for labels.

## 5. i18n + nav

1. Add keys to `lang/en.json` **and** `lang/pt-BR.json`.
2. If the page is public navigation, link it in `themes/default/templates/components/navbar.twig`.

## PR checklist

- [ ] Route registered
- [ ] Controller wired in `resolveController` (if new)
- [ ] Layout JSON + Twig page
- [ ] `en` + `pt-BR` keys
- [ ] CSRF on POST / `requireAuth` when needed
