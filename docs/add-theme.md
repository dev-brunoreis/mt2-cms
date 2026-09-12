# Add a theme overlay

Child themes override the parent without forking the whole tree.

## 1. Folder + meta

```
themes/my-theme/
  theme.json
  templates/          # optional overrides
  layouts/            # optional layout overlays
  assets/             # optional static files (css, images, …)
```

`theme.json`:

```json
{
  "name": "my-theme",
  "parent": "default"
}
```

See [README Themes](../README.md#themes) and create a minimal child as above (override only the Twig paths you need).

## 2. Theme assets (CSS, images)

Static files belong **inside the theme**, not under `public/`:

```
themes/my-theme/assets/
  css/theme.css
  img/hero.webp
  src/              # optional masters / sources
```

In Twig, resolve URLs with `theme_asset()` (walks active theme → parents; first hit wins):

```twig
<link rel="stylesheet" href="{{ theme_asset('css/theme.css') }}">
<img src="{{ theme_asset('img/hero.webp') }}" alt="">
```

That becomes `/theme-assets/{theme}/css/theme.css`, served by nginx from `themes/{theme}/assets/…` (cacheable; only safe extensions).

## 3. Override a Twig template

Use the **same relative path** as the parent:

| Parent | Child override |
| --- | --- |
| `themes/default/templates/components/navbar.twig` | `themes/my-theme/templates/components/navbar.twig` |

Search order is active theme first, then parents (`ThemeResolver::templatePaths()`).

## 4. Override a layout node

Place `themes/my-theme/layouts/{page}.json` with the same node `id` you want to change. `LayoutMerger` merges by `id`, not by array index — so you can replace only the navbar node without copying the full layout.

## 5. Activate

Set in `.env`:

```env
THEME=my-theme
```

## PR checklist

- [ ] `parent` points at an existing theme
- [ ] Override paths match parent paths
- [ ] Layout node `id`s preserved for merge
- [ ] No circular `parent` chains
- [ ] New static files live under `themes/{name}/assets/` and are linked via `theme_asset()`
