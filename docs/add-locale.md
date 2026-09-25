# Add translations / locale

## Existing languages

Shipped under `lang/`:

| Code | Name | Notes |
| --- | --- | --- |
| `en` | English | Fallback / source of truth (`lang/en.json`) |

Missing keys fall back to `en`, then to the key string itself.

## Keys

Nested JSON → dotted keys:

```json
{
  "nav": { "home": "Home" }
}
```

- PHP: `$this->t('nav.home')`
- Twig: `{{ t('section.key') }}`

Placeholders: `"Hello {name}"` with `$this->t('key', ['name' => $value])`.

Plurals:

```json
"items": { "one": "{n} item", "other": "{n} items" }
```

Pass `{n}` or `{count}`.

## Layout: single file or directory

A locale is supported if either exists:

| Layout | Example |
| --- | --- |
| Single file | `lang/en.json` |
| Namespace directory | `lang/{code}/*.json` (e.g. `nav.json`, `admin.json`, `locale.json`) |

`Translator` loads a directory by merging all `*.json` files. Filename stem becomes the top-level key unless the file already wraps that key. Put switcher metadata in `locale.json` as `{ "name": "Locale Name" }` or `{ "locale": { "name": "Locale Name" } }`.

## New language

1. Create `lang/{code}.json` **or** `lang/{code}/` with namespace files.
2. Include locale name for the switcher (`locale.name`).
3. Translate from `en` (copy structure), or generate with DeepL (below).
4. No PHP/route changes — `Locales::available()` scans files and directories under `lang/`. Codes must match `^[A-Za-z0-9_-]+$` (`Translator` falls back to `en` otherwise).
5. Optional flag: drop `{code}.webp` in `public/flag/` (aliases: `cs`→`cz`, `da`→`dk`, `el`→`gr`).
6. Add the code to `ShippedLocalesTest::SHIPPED` / `NAMES`.

Default for visitors **without** a `locale` cookie: CMS setting `default_locale` (Settings → Locale), else `LOCALE` in `.env` (default `en`). Cookie always wins when set (`Locales::resolve`). Saving the default locale also sets the admin’s cookie so the panel switches immediately.

Locale cookie is set via `POST /locale` (public session) or `POST /admin/locale` (admin session `MT2ADMIN` — the sidebar switcher must use this path so CSRF matches). Saving Settings → Locale also sets the cookie.

## Money formats

Admin Settings → Locale picks one of three display styles (`Money::FORMATS` in `src/Support/Money.php`):

| Format id | Example |
| --- | --- |
| `dot` | `1,234.56` |
| `comma` | `1.234,56` |
| `space` | `1 234,56` |

i18n keys: `admin.locale.money_format_dot` / `_comma` / `_space`.

## DeepL offline generation

Runtime CMS never calls DeepL. To generate a new pack from `en.json`:

```bash
# Free key — never commit. See .env-example DEEPL_AUTH_KEY=
export DEEPL_AUTH_KEY='…'
php bin/i18n-deepl.php --only=fr
php bin/i18n-deepl.php --dry-run --only=de
php bin/i18n-deepl.php --force --only=pt
```

Helpers: `Mt2Cms\I18n\LocaleJsonTree` (flatten / placeholders). Progress cache: `lang/.deepl-cache/` (gitignored). Use `--force` to redo a complete file. Supported target codes live in `LOCALES` inside `bin/i18n-deepl.php`.

## PR checklist

- [ ] Key present in `en` (file or directory)
- [ ] Same key in every shipped locale (or accept `en` fallback)
- [ ] Placeholders `{…}` unchanged across locales
- [ ] No hardcoded UI strings in controllers/Twig
