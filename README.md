# Mt2 CMS

Metin2 site + admin. Beta — public APIs and schema can still change before `1.0.0`.

## Who is this for?

| You… | Do this |
| --- | --- |
| Run a Metin2 server and want the site | Download a **Release** `.tar.gz`. Follow **Install** below. |
| Change the CMS code | Clone this repo. See **Develop** at the bottom. |

Do **not** put the CMS on the same machine as the game server. Use a separate Linux host (VPS/VM). The game MySQL must be reachable from that host on a private network.

---

## Requirements

- Linux host (not the Metin2 box)
- PHP 8.3 FPM with: `pdo_mysql`, `gd` (JPEG/PNG/WebP), `curl`, `mbstring`, `iconv`, `fileinfo`, `openssl`
- Composer
- Nginx or Apache (document root = `public/` only)
- MySQL **8** on the CMS host (schema `cms`) — **not** the old Metin2 game MySQL (5.6/5.7 often lacks JSON)
- Game MySQL (`account`, `player`, `common`, `log`) reachable from the CMS host

Package names and how you enable extensions **depend on the OS**. Examples:

**Arch Linux** (sync mirrors first if install hits 404: `sudo pacman -Syu`):

```bash
sudo pacman -S --needed php php-fpm php-gd composer nginx mysql
# Enable in /etc/php/php.ini if still commented:
#   extension=pdo_mysql
#   extension=gd
#   extension=iconv
```

**Ubuntu 24.04** (PHP 8.3):

```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-gd \
  php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip composer nginx mysql-server
```

`iconv` is usually built into Ubuntu’s PHP packages; on Arch it often ships as a separate module that must be enabled in `php.ini` (Composer needs it).

Then verify: `php bin/check-requirements.php` (after unpack + `composer install`).

Reference pack (files + SQL): [40.250 serverfile + client](https://metin2.dev/topic/27610-40250-reference-serverfile-client-src-15-available-languages/).

---

## Install (release)

### 1. Unpack and install PHP deps

```bash
tar -xzf mt2-cms-VERSION.tar.gz
cd mt2-cms-VERSION
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php bin/check-requirements.php
```

Fix anything the checker reports before continuing. Same check: `composer check`.

### 2. Point the web server at `public/`

Sample Nginx/Apache configs: [`deploy/linux/`](deploy/linux/). The CMS directory must be writable so `/setup` can create `.env`.

### 3. Open `/setup`

Visit `https://your-domain/setup`. The wizard walks:

1. **Requirements** — PHP extensions, writable folders, `vendor/` (same checks as the CLI)
2. **Databases** — game MySQL + CMS MySQL (use **Test connection** before continuing)
3. **Admin** — first CMS admin account

It writes `.env`, creates the CMS tables, and sets `APP_INSTALLED=true`. You do **not** need to copy or hand-edit `.env` first.

Before the database step, the game MySQL user must already allow connections **from the CMS host IP** (not `'%'` on the public internet). Details: [`docs/game-mysql.md`](docs/game-mysql.md).

The site works after setup. Shop/ranking icons and proto admin stay empty until you add game files (next section).

---

## Game files (items, mobs, icons)

The release ships **empty** `game/db/`, `game/client/`, and `game/server/` folders. Copy files from your server pack and an **unpacked** client. Match by **filename** (archive folder names vary). Do **not** copy `.epk` / `.sub` packs — unpack the client first.

Full table and face icon names: [`game/README.md`](game/README.md).

### From the server files → `game/`

| File | Put it here |
| --- | --- |
| `item_proto.txt` | `game/db/item_proto.txt` |
| `mob_proto.txt` | `game/db/mob_proto.txt` |
| English item names (`item_names.txt` / `item_names_en.txt`) | `game/db/item_names_en.txt` |
| English mob names (`mob_names.txt` / `mob_names_en.txt`) | `game/db/mob_names_en.txt` |
| `mob_drop_item.txt`, `common_drop_item.txt`, `etc_drop_item.txt`, `drop_item_group.txt`, `group.txt`, `group_group.txt` | `game/server/` |

On the 40.250 pack these usually live under `share/locale/english/`.

### From the unpacked client → `game/`

| File | Put it here |
| --- | --- |
| `item_list.txt` | `game/client/item_list.txt` |
| `itemdesc.txt` | `game/client/itemdesc.txt` |
| Item icons `*.tga` | `game/client/icon/item/` |
| Face icons `*.tga` | `game/client/icon/face/` |

Leave icons as TGA. The CMS converts them to PNG on request. Missing files = no image on the site (the rest still works).

Optional: keep dumps outside the project and set `GAME_DIR` in `.env` to that folder (same layout: `db/`, `client/`, `server/`, plus `config.json` and `schema/`).

---

## Checklist

1. Linux CMS host + PHP 8.3 + Composer + web server → `public/`
2. CMS MySQL 8 running locally
3. Game MySQL reachable from this host (`account`, `player`, `common`, `log`)
4. `composer install --no-dev …` then `php bin/check-requirements.php`
5. Open `/setup` (requirements → databases → admin — writes `.env`)
6. Copy proto / names / drops into `game/db/` and `game/server/`
7. Unpack the client; copy `item_list`, `itemdesc`, and icons into `game/client/`

---

## Develop (this repo only)

You need Docker + Docker Compose, Composer, and Node.js.

**Before the first** `docker compose up`, copy these four SQL dumps into `docker/mysql/backup/` (imported only when the volume is created):

| File | Source |
| --- | --- |
| `account.sql` | dump of game `account` |
| `common.sql` | dump of game `common` |
| `log.sql` | dump of game `log` |
| `player.sql` | dump of game `player` |

See [`docker/mysql/backup/README.md`](docker/mysql/backup/README.md). Proto and client files: same as **Game files** above ([`game/README.md`](game/README.md)).

```bash
cp .env-example .env
composer install
npm ci && npm run build
docker compose up --build
```

Open http://localhost:8000/setup.

```bash
composer test
./bin/package-release.sh 1.0.0-beta.0
```

Architecture docs (not in the release tarball): [docs/map.md](docs/map.md).

Building a public theme: [docs/add-theme.md](docs/add-theme.md). Copy `themes/starter`, recolor, activate under **Admin → Settings → Themes**.

---

## Contributing

Public project. Send fixes as pull requests.

If this helps you, **star** and **fork** the repo. Need a custom system for your server? Get in touch on [GitHub](https://github.com/dev-brunoreis).

Keep the "Mt2 CMS" line in the site footer. Support development: [GitHub Sponsors](https://github.com/sponsors/dev-brunoreis).

[CONTRIBUTING.md](CONTRIBUTING.md)

## License

[MIT](LICENSE)
