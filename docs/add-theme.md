# Add a theme overlay

Child themes override the parent without forking the whole tree.

## 1. Folder + meta

```
themes/my-theme/
  theme.json
  templates/          # optional overrides
  layouts/            # optional layout overlays
```

`theme.json`:

```json
{
  "name": "my-theme",
  "parent": "default"
}
```

See `themes/overlay-demo/` for a minimal navbar-only override.

## 2. Override a Twig template

Use the **same relative path** as the parent:

| Parent | Child override |
| --- | --- |
| `themes/default/templates/components/navbar.twig` | `themes/my-theme/templates/components/navbar.twig` |

Search order is active theme first, then parents (`ThemeResolver::templatePaths()`).

## 3. Override a layout node

Place `themes/my-theme/layouts/{page}.json` with the same node `id` you want to change. `LayoutMerger` merges by `id`, not by array index — so you can replace only the navbar node without copying the full layout.

## 4. Activate

Set in `.env`:

```env
THEME=my-theme
```

## PR checklist

- [ ] `parent` points at an existing theme
- [ ] Override paths match parent paths
- [ ] Layout node `id`s preserved for merge
- [ ] No circular `parent` chains
