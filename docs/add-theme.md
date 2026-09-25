# Create a theme

Child themes restyle and rearrange the public site without forking PHP or the whole `themes/default/` tree. Copy [`themes/starter`](../themes/starter), change colors and layout, activate it. That is the fast path.

There is **no zip upload**. Drop a folder under `themes/{name}/` (deploy or the repo), then pick it in **Admin → Settings → Themes** or set `THEME=` in `.env`.

The admin panel (`themes/admin`, `"public": false`) is not a public theme. You cannot select it for the site.

## Contents

- [What a theme is](#what-a-theme-is)
- [10-minute start](#10-minute-start)
- [What you can and cannot do](#what-you-can-and-cannot-do)
- [Data the templates already have](#data-the-templates-already-have)
- [Layout and widgets](#layout-and-widgets)
- [Tutorials](#tutorials)
- [Keep it safe](#keep-it-safe)
- [Keep it fast](#keep-it-fast)
- [Activate](#activate)
- [PR checklist](#pr-checklist)

## What a theme is

```
themes/my-theme/
  theme.json          # name, parent, optional features
  templates/          # optional Twig overrides (same path as the parent)
  layouts/            # optional layout JSON overlays
  assets/             # css, js, images — served at /theme-assets/
```

A theme is files only. It does not run PHP, register routes, or query the database. The CMS loads the active theme, then each parent, and the first file that exists wins:

| Layer | Rule |
| --- | --- |
| Twig | Same relative path replaces the parent (`ThemeResolver::templatePaths()`) |
| Layout JSON | Merged by node `id` (`LayoutMerger`). `"remove": true` drops a node |
| Assets | `theme_asset('css/tokens.css')` walks active → parents; first file wins |

`theme.json`:

```json
{
  "name": "my-theme",
  "parent": "default",
  "features": {
    "layout_columns": true
  }
}
```

| Key | Meaning |
| --- | --- |
| `name` | Label. The **folder name** is what you activate |
| `parent` | Theme to inherit. `"default"` for a public child. `null` only for a root theme |
| `public` | Omit or `true` to list it in Settings. `false` hides it (admin does this) |
| `features` | Flags the core already understands. Only `layout_columns` today |

Folder and feature names: theme `^[A-Za-z0-9_-]+$`. `ThemeCatalog` does not read `theme.json` until the name is valid. A broken `parent` fails when the page renders, not when you save settings.

Shipped public themes: `default` (Kingdoms) and `starter` (child example). New public pages still land in `default`; a child only overrides what it needs. See [add-page.md](add-page.md).

The default footer (`templates/components/footer.twig`) includes an "Mt2 CMS" credit. Keep that line if you override the footer.

### Columns (optional feature)

Column controls are per theme, not global. Declare the feature (or inherit it from `default`) and **Admin → Settings → Themes** shows Columns / Sidebar side. A child can set `"layout_columns": false` to opt out; the first explicit `features.layout_columns` on the parent chain wins.

| Setting | Values | Effect |
| --- | --- | --- |
| Columns | `3` (default) or `2` | `3`: left \| main \| right. `2`: one sidebar + main |
| Sidebar side | `left` or `right` | Only when columns = `2` |

Globals `layout_columns` and `layout_sidebar` are forced to `3` / `left` when the active theme lacks the feature. `themes/default/templates/layouts/shell.twig` and `theme.css` read them. Switching themes does not wipe stored column values.

## 10-minute start

1. Copy `themes/starter` to `themes/my-theme` (letters, digits, `_`, `-` only).
2. Set `"name": "my-theme"` in `theme.json`. Leave `"parent": "default"`.
3. Edit `assets/css/tokens.css` — change the `--k-*` colors. Keep the `@font-face` blocks (the whole file replaces the parent; drop the fonts and Cinzel / Source Serif disappear).
4. **Admin → Settings → Themes** → Active theme `my-theme` → save. Or set `THEME=my-theme` in `.env` before the database setting exists.
5. Reload the public home page. Discord widget is gone (starter removes it). Colors follow your tokens. Everything else is still `default`.

`starter` is a working example, not the site default. Do not edit `themes/default` to recolor one server — override in the child.

## What you can and cannot do

| You can | You cannot (without core PHP) |
| --- | --- |
| Recolor with `assets/css/tokens.css` | Add a new URL. That needs a route + controller. See [add-page.md](add-page.md) |
| Replace one Twig file by matching its path | Run PHP, SQL, or a controller from the theme |
| Overlay a layout and remove widgets by `id` | Upload a theme zip in the admin |
| Add images/CSS/JS under `assets/` via `theme_asset()` | Ship `lang/` inside the theme. Strings live in [lang/en.json](../lang/en.json). Use `{{ t('key') }}` |
| Show ranking, online, news, shop, player data the core already passes in | Invent a `theme.json` feature and expect admin UI. Only `layout_columns` is wired |
| Keep admin column controls by leaving `layout_columns` on | Serve `.gif`, `.ico`, video, or `.woff` (not woff2). Whitelist: `css`, `js`, `woff2`, `jpeg`/`jpg`, `webp`, `png`, `svg` |

Shared site CSS/JS (`/css/app.css`, `/js/site/*`) is not inside the theme. Override `layouts/shell.twig` only if you must change those `<link>` / `<script>` tags. Prefer tokens and component overrides.

## Data the templates already have

Themes do not fetch data. Controllers and `src/bootstrap/installed_services.php` pass it in. Widgets should use the **globals** below so the same block works on every page. Page templates use the keys for that route.

Every public HTML page also gets:

| Key | Use |
| --- | --- |
| `auth.check`, `auth.login`, `auth.user` | Logged-in player |
| `csrf` | Hidden `_csrf` on forms you add |
| `flash.type`, `flash.message` | One-shot notice after redirect |
| `locale`, `html_lang`, `locales`, `current_path` | Language |
| `seo.title`, `seo.description`, `seo.canonical`, `seo.image`, `seo.json_ld` | `<head>` in `shell.twig` |
| `registration_enabled`, `captchaEnabled` | Register / captcha UI |
| `notification_unread` | Badge when logged in |

### Chrome globals (any template, including widgets)

Set once per request in `installed_services.php`:

| Global | What it is |
| --- | --- |
| `theme_players_online`, `theme_accounts_online`, `theme_window_minutes` | Who is online |
| `theme_top_players` | Top 10 by level. Rows: `name`, `job`, `level`, `skill_group` |
| `theme_upcoming_events` | Next published events (`id`, `title`, `starts_at`) |
| `theme_client_download` | First public download, or empty. `id`, `title`, `external_url`, `stored_name` |
| `site_banners`, `banner_settings` | Homepage carousel |
| `site_title`, `site_logo`, `footer_text`, `social_links`, `discord_invite_url` | Community settings |
| `has_news`, `news_show_views` | News flags |
| `layout_columns`, `layout_sidebar` | `3`/`2` and `left`/`right` |

Empty Discord URL: the default widget hides itself. Empty download: the widget still links to `/downloads`.

### Per page

Layout name = `themes/*/layouts/{name}.json`. Only that page's template should rely on these keys.

| Page | Layout | Main keys |
| --- | --- | --- |
| Home | `home` | `posts`, `events`, `playersOnline`, `accountsOnline`, `windowMinutes` |
| News | `news`, `news-show` | `posts` or `post`, `comments`, `canComment`, pagination |
| Events | `events`, `event-show` | `events` or `event` |
| Ranking | `ranking` | `tab` (`level` \| `playtime` \| `guilds`), `ranking`, `query`, pagination |
| Status | `status` | `playersOnline`, `accountsOnline`, `channels` |
| Player | `player` | `player`, `guild`, `marriage`, `levelRank`, `playtimeRank`, `online`, `equipmentLayout` |
| Shop | `shop` | `categories`, `products`, `cash`, `activeCategory`, `query` |
| Donate | `donate`, `donate-pay` | `packages`, `currency`, `paymentGateways`; pay step has `checkout_url` |
| Downloads | `downloads` | `downloads` |
| Account | `account`, `characters`, `account-*` | `account`, lists (orders, payments, notifications), unstuck on characters |
| Tickets | `tickets`, `ticket-form`, `ticket-show` | `tickets` / `ticket`, `messages` |
| Auth | `auth`, `forgot-password`, `reset-password` | `form`, errors, captcha flags |

Copy the matching file under `themes/default/templates/pages/` instead of guessing fields.

### Twig helpers

Functions: `t('key')`, `theme_asset('css/tokens.css')`, `item_icon(vnum)`, `face_icon(job)`.

Filters: `job_name`, `skill_name`, `empire_name`, `map_name`, `playtime`, `duration`, `unix_date`, `game_date`, `money`, `news_html`, `news_excerpt`, `ticket_html`, `json_ld`.

`news_html` and `ticket_html` are already sanitized. Do not add `|raw` on news, comments, names, or anything from the database. `|raw` is only for layout `slots.*` inside the shell (the CMS already escaped those chunks).

## Layout and widgets

Public pages extend `layouts/_shell.json`. The shell template is `templates/layouts/shell.twig`.

| Slot | Default node `id` | Template |
| --- | --- | --- |
| `header` | `navbar` | `components/navbar.twig` |
| `banner` | `banner` | `components/banner.twig` |
| `sidebar` | `sidebar` | `components/sidebar-auth.twig` (account layouts swap this `id`) |
| `left` | `widget-online`, `widget-download` | matching `components/widget-*.twig` |
| `main` | `content` (filled by each page) | `pages/….twig` |
| `right` | `widget-ranking`, `widget-events`, `widget-discord` | matching widgets |
| `footer` | `footer` | `components/footer.twig` |

A page layout only fills `main` (and `sidebar` on account pages):

```json
{
  "extends": "_shell",
  "slots": {
    "main": [{ "id": "content", "template": "pages/home.twig" }]
  }
}
```

Child overlay of the shell — same `id` replaces, `"remove": true` deletes. `themes/starter/layouts/_shell.json` removes Discord:

```json
{
  "slots": {
    "right": [{ "id": "widget-discord", "remove": true }]
  }
}
```

To replace a widget, keep the `id` and point `template` at your file. To add one, use a **new** `id` so merge does not clobber `widget-ranking`. Do not create circular `parent` or `extends` chains.

Setup (`layouts/setup.json`) does not use `_shell`. Leave it on `default` unless you mean to restyle the installer.

## Tutorials

Snippets assume a child of `default`. Class names (`k-panel`, `k-title`) come from the parent `theme.css`.

### 1. Recolor

Override `assets/css/tokens.css` only. `theme.css` in the parent is large; do not copy it.

The child file **replaces** the parent file. Copy `@font-face` from `themes/default/assets/css/tokens.css` (or from `themes/starter`) and change colors:

```css
.theme-kingdoms {
  --k-bg: #070b14;
  --k-bronze: #7eb6ff;
  --k-text: #e4eefc;
  /* …the rest of the --k-* tokens… */
}
```

`--k-bronze` is the accent used across the parent CSS (links, titles, prices). Changing it recolors the chrome. `themes/starter/assets/css/tokens.css` is a full night/cobalt example.

### 2. Remove or move a widget

`layouts/_shell.json` in the child:

```json
{
  "slots": {
    "right": [{ "id": "widget-discord", "remove": true }]
  }
}
```

To reorder a slot, list the node `id`s in the order you want (repeat `template` only if you are also replacing the file). Listed nodes move to the front; any parent node you omit is appended after them. To own the full order, list every `id` in that slot.

### 3. Online count and top players

These globals exist on every page. Default widgets: `widget-online.twig`, `widget-ranking.twig`.

```twig
<p>{{ theme_players_online|default(0) }}</p>
{% for row in theme_top_players|default([]) %}
    <a href="/player/{{ row.name|url_encode }}">{{ row.name }}</a>
    {{ row.job|job_name(row.skill_group) }} · Lv {{ row.level }}
{% endfor %}
```

`face_icon(row.job)` returns an image URL or empty. Events: `theme_upcoming_events` with `event.starts_at|game_date`.

### 4. News on the home page

`posts` exists on the `home` layout (and the news list). Override `templates/pages/home.twig` or keep the parent and only restyle.

```twig
{% for post in posts|default([]) %}
    <a href="/news/{{ post.id }}">{{ post.title }}</a>
    <time>{{ (post.published_at|default(post.created_at))|game_date }}</time>
    <p>{{ post.body|news_excerpt(120) }}</p>
{% endfor %}
```

Full article HTML on the news page: `post.body|news_html` (sanitized). Do not use `|raw`.

### 5. Shop icons and a player face

Shop page (`products`):

```twig
<img src="{{ item_icon(product.vnum) }}" alt="" width="48" height="48">
{{ product.item_name }} — {{ t('shop.price', {price: product.price}) }}
```

`product.price` is the cash amount (integer). Logged-in balance is `cash` (also an integer). `money` is for donate/payment cents, not shop cash.

Player page:

```twig
<img src="{{ face_icon(player.job) }}" alt="">
{{ player.name }} — {{ player.job|job_name }}
```

Equipment uses `equipmentLayout` plus `components/public-equipment.twig` in the default player page. Include that partial instead of reimplementing slots.

## Keep it safe

- No PHP, no `.php` under `assets/` (the asset whitelist rejects it).
- Twig autoescape is `html`. `|raw` only on `slots.header|raw`, `banner`, `sidebar`, `left`, `main`, `right`, `footer` in the shell.
- User and database text: print it plain, or `news_html` / `ticket_html` / `news_excerpt`.
- New forms: `method="post"` and `<input type="hidden" name="_csrf" value="{{ csrf }}">`. The matching controller must already accept that POST — a theme cannot add a route.
- Do not put passwords, PINs, emails, or API secrets in templates. They are not in the view data; do not read files from Twig.
- Asset paths cannot contain `..`. Extensions are `css`, `js`, `woff2`, `jpg`/`jpeg`, `webp`, `png`, `svg`.
- If you override `footer.twig`, keep the Mt2 CMS credit link (`t('theme.kingdoms.cms_credit')` in the default footer).

## Keep it fast

- Override the files you change. Do not copy `themes/default`.
- Recolor with `tokens.css`. Copying `theme.css` means you maintain 1000+ lines on every CMS upgrade.
- `theme_asset()` adds `?v={filemtime}` so a changed CSS/image cache-busts. Nginx serves `/theme-assets/…` from `themes/{theme}/assets/` when it matches the whitelist.
- Prefer small `.webp` images. The parent shell already loads `/css/app.css` and the site scripts; do not duplicate them.
- Extra `<script src>` must be same-origin (`'self'`). The public CSP does not allow a third-party script CDN.

## Activate

1. Folder `themes/{name}/theme.json` with `"parent": "default"` (or another public theme).
2. **Admin → Settings → Themes**, or `THEME={name}` in `.env` when no `active_theme` is stored yet.
3. Reload a public page. If the parent chain loops or a parent folder is missing, render throws — fix `theme.json` rather than the database.

Dev server without nginx still serves whitelisted assets through `ThemeAssetController`:

```bash
php -S localhost:8000 -t public
```

## PR checklist

- [ ] `parent` points at an existing theme
- [ ] Folder name matches `^[A-Za-z0-9_-]+$` and `theme.json` `name`
- [ ] Override paths match parent paths
- [ ] Layout node `id`s preserved for merge; `remove` used to drop widgets
- [ ] No circular `parent` or layout `extends` chains
- [ ] Static files live under `themes/{name}/assets/` and are linked with `theme_asset()`
- [ ] No `|raw` on user or database data
- [ ] Footer credit kept if `footer.twig` is overridden
