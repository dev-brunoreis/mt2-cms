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

See [README Themes](../README.md#themes). Shipped child:

- `twin` — two columns (unified sidebar + main): overlays `_shell.json` to move `right` widgets into `left` (download → online → ranking → events → discord), overrides `layouts/shell.twig` (one aside), `assets/css/layout.css` (2-column grid only — no sticky/overflow sidebar), and a denser `pages/home.twig` (welcome panel, news, events)

## 2. Theme assets (CSS, images)

Static files belong **inside the theme**, not under `public/`:

```
themes/my-theme/assets/
  css/tokens.css    # color/font variables (override to recolor)
  css/theme.css     # imports tokens + chrome
  img/hero.webp
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

## 4. Layout `extends` and node overlays

Public pages use `layouts/_shell.json` as the chrome base. A page layout is usually:

```json
{
  "extends": "_shell",
  "slots": {
    "main": [{ "id": "content", "template": "pages/your-page.twig" }]
  }
}
```

Account pages also override the `sidebar` node (same `id`) to swap login for account nav.

Child themes can overlay `layouts/_shell.json` (or any page) and merge by node `id`. To **remove** a widget:

```json
{
  "slots": {
    "right": [{ "id": "widget-discord", "remove": true }]
  }
}
```

Shell slots: `header`, `banner`, `sidebar`, `left` (column widgets), `main`, `right`, `footer`.

## 5. Activate

Set in `.env` (`THEME=my-theme`) or **Admin → Settings → Themes**.

## PR checklist

- [ ] `parent` points at an existing theme
- [ ] Override paths match parent paths
- [ ] Layout node `id`s preserved for merge
- [ ] No circular `parent` or layout `extends` chains
- [ ] New static files live under `themes/{name}/assets/` and are linked via `theme_asset()`
