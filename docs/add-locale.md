# Add translations / locale

## Existing languages

- `lang/en.json` — fallback (single file)

Always add new keys to **every shipped locale**. Missing keys fall back to `en`, then to the key string itself.

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
| Single file | `lang/de.json` |
| Namespace directory | `lang/de/*.json` (e.g. `nav.json`, `admin.json`, `locale.json`) |

`Translator` loads a directory by merging all `*.json` files. Filename stem becomes the top-level key unless the file already wraps that key. Put switcher metadata in `locale.json` as `{ "name": "Deutsch" }` or `{ "locale": { "name": "Deutsch" } }`.

## New language

1. Create `lang/{code}.json` **or** `lang/{code}/` with namespace files.
2. Include locale name for the switcher (`locale.name`).
3. Translate from `en` (copy structure).
4. No PHP/route changes — `Locales::available()` scans files and directories under `lang/`.
5. Optional flag: drop `{code}.webp` in `public/flag/` (aliases: `cs`→`cz`, `da`→`dk`, `el`→`gr`).

Default when no cookie: `LOCALE` in `.env` (default `en`).

Locale cookie is set via `POST /locale` (CSRF + `safeRedirect`).

## PR checklist

- [ ] Key present in `en` (file or directory)
- [ ] No hardcoded UI strings in controllers/Twig
