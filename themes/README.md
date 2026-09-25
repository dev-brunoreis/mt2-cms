# Themes

A public theme is a folder next to `default`. It overlays Twig, layout JSON, and CSS. It does not run PHP.

`default` is the Kingdoms parent. `starter` is a child example: night/cobalt colors and the Discord widget removed. `admin` is the panel (`"public": false`) — do not pick it for the site.

## Add one

1. Copy `starter` to `themes/my-theme` (letters, digits, `_`, `-`).
2. Set `"name": "my-theme"` in `theme.json`. Keep `"parent": "default"`.
3. Edit `assets/css/tokens.css`. Keep the `@font-face` blocks.
4. **Admin → Settings → Themes** → activate `my-theme`. Or set `THEME=my-theme` in `.env`.

Full guide (what data templates can show, widgets, safety): [docs/add-theme.md](https://github.com/dev-brunoreis/mt2-cms/blob/main/docs/add-theme.md) in the source repo. This `docs/` tree is not in the release tarball.

Keep the "Mt2 CMS" line if you override the footer.
