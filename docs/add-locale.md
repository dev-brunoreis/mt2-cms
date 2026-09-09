# Add translations / locale

## Existing languages

- `lang/en.json` — fallback

Always add new keys to **both** (at least `en` + every shipped locale). Missing keys fall back to `en`, then to the key string itself.

## Keys

Nested JSON → dotted keys:

```json
{
  "nav": { "home": "Home" }
}
```

- PHP: `$this->t('nav.home')`
- Twig: `{{ t('nav.home') }}`

Placeholders: `"Hello {name}"` with `$this->t('key', ['name' => $value])`.

Plurals:

```json
"items": { "one": "{n} item", "other": "{n} items" }
```

Pass `{n}` or `{count}`.

## New language

1. Create `lang/{code}.json` (e.g. `es.json`). The filename stem is the locale code and cookie value.
2. Include `"locale": { "label": "...", "name": "..." }` so the switcher can label it.
3. Copy structure from `en.json` and translate.
4. No PHP/route changes — `Locales::available()` scans `lang/*.json`.

Default when no cookie: `LOCALE` in `.env` (default `en`).

Locale cookie is set via `POST /locale` (CSRF + `safeRedirect`).

## PR checklist

- [ ] Key present in `en.json`
- [ ] No hardcoded UI strings in controllers/Twig
