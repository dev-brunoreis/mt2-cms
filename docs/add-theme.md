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

Shipped public theme: `default` (plus internal `admin`).

## 2. Public layout columns (theme feature)

Column/sidebar settings are **not** global — only themes that declare the feature get the admin controls.

In `theme.json`:

```json
{
  "name": "default",
  "parent": null,
  "features": {
    "layout_columns": true
  }
}
```

`ThemeCatalog::supportsFeature()` walks the parent chain; the first explicit `features.{name}` wins (a child can set `"layout_columns": false` to opt out).

**Admin → Settings → Themes** shows Columns / Sidebar side only when the selected theme supports `layout_columns` (form syncs on theme change). Saving another theme leaves stored layout values untouched for when you switch back.

| Setting | Values | Effect (supporting themes only) |
| --- | --- | --- |
| Active theme | folder under `themes/` (public) | Theme package (`THEME` / `active_theme`) |
| Columns | `3` (default) or `2` | `3`: left \| main \| right. `2`: one sidebar + main |
| Sidebar side | `left` or `right` | Only when columns = `2` |

Globals `layout_columns` / `layout_sidebar` are injected in `installed_services.php` (forced to 3 / left when the active theme lacks the feature) and read by `themes/default/templates/layouts/shell.twig` + `theme.css`.

Admin UI: `themes/admin/templates/pages/themes.twig` renders layout fields when the active theme supports the feature; `public/js/admin/admin-themes-form.js` toggles them on theme change and is re-run after settings-hub AJAX tab loads (`admin-tabs.js` → `initAdminThemesForm`).

## 3. Theme assets (CSS, images)

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

That becomes `/theme-assets/{theme}/css/theme.css`. Nginx serves `themes/{theme}/assets/…` directly (cacheable; only safe extensions). When nginx is not in front (PHP built-in server, or `try_files` falling through to `index.php`), `ThemeAssetController` serve the same whitelist via `ThemeAssetFile`. Host PHP:

```bash
php -S localhost:8000 -t public
```

## 4. Override a Twig template

Use the **same relative path** as the parent:

| Parent | Child override |
| --- | --- |
| `themes/default/templates/components/navbar.twig` | `themes/my-theme/templates/components/navbar.twig` |

Search order is active theme first, then parents (`ThemeResolver::templatePaths()`).

## 5. Layout `extends` and node overlays

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

## 6. Activate

Set in `.env` (`THEME=my-theme`) or **Admin → Settings → Themes** (active theme + layout).

## PR checklist

- [ ] `parent` points at an existing theme
- [ ] Override paths match parent paths
- [ ] Layout node `id`s preserved for merge
- [ ] No circular `parent` or layout `extends` chains
- [ ] New static files live under `themes/{name}/assets/` and are linked via `theme_asset()`
