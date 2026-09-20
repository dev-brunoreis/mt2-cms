# FreeBSD production deployment

Primary bare-metal runbook for FreeBSD **13.2+**. Docker is for local development only — do not ship Compose to the server.

Related: [deploy.md](deploy.md) (Linux Compose alternative), [security.md](security.md), sample configs under [`deploy/freebsd/`](../deploy/freebsd/).

FreeBSD 13.2 is end-of-life; prefer **13.4** or **14.x** for current `pkg` PHP 8.3 packages. Minimum documented baseline remains 13.2+.

## Architecture

```
Internet → TLS reverse proxy (Nginx / Caddy / HAProxy)
              ↓ HTTP to PHP-FPM (unix socket) or local Nginx
         Mt2 CMS (document root = public/)
              ↓ private network / jail
         MySQL (game + CMS) — never on 0.0.0.0
```

## Packages

Install PHP 8.3 FPM, Nginx (or Apache), and the extensions the app requires:

```sh
pkg install \
  php83 php83-extensions \
  php83-pdo_mysql php83-gd php83-curl php83-mbstring \
  php83-filter php83-session php83-opcache php83-openssl php83-fileinfo \
  nginx \
  mysql80-client
```

Confirm GD has WebP (banners need it):

```sh
php -r 'exit(function_exists("imagewebp") ? 0 : 1);' && echo "GD WebP OK"
```

If that fails, rebuild/reinstall `php83-gd` with WebP enabled (ports option or a package build that includes it).

Composer and Node are **not** required on the host when you install from a GitHub Release tarball (vendor + assets are pre-built).

## Quick install (script)

As **root**, one-shot installer (POSIX `sh` — no bash):

```sh
# Set your GitHub repo if different from the default in the script
export MT2CMS_REPO=YOUR_GITHUB_USER/mt2-cms
export MT2CMS_SERVER_NAME=cms.example.com

fetch -o /tmp/mt2-cms-install.sh \
  https://raw.githubusercontent.com/${MT2CMS_REPO}/v0.1.0-beta.1/deploy/freebsd/install.sh
sh /tmp/mt2-cms-install.sh 0.1.0-beta.1
```

Or from an already downloaded tarball:

```sh
export MT2CMS_TARBALL=/path/to/mt2-cms-0.1.0-beta.1.tar.gz
sh /path/to/mt2-cms-0.1.0-beta.1/deploy/freebsd/install.sh 0.1.0-beta.1
```

The script: `pkg install` (PHP 8.3 + Nginx + clients), downloads/verifies the release, extracts to `/usr/local/www/mt2-cms`, sets `var/` permissions, installs Nginx/PHP-FPM snippets, enables services. **You still edit `.env`, run `php bin/migrate.php`, and open `/setup`.**

Script source: [`deploy/freebsd/install.sh`](../deploy/freebsd/install.sh).

## Install from a release tarball (manual)

1. Download `mt2-cms-0.1.0-beta.1.tar.gz` (or newer beta) and its `.sha256` from GitHub Releases.
2. Verify and extract:

```sh
sha256 -c mt2-cms-0.1.0-beta.1.tar.gz.sha256
mkdir -p /usr/local/www
tar -xzf mt2-cms-0.1.0-beta.1.tar.gz -C /usr/local/www
# tree ends up as /usr/local/www/mt2-cms-0.1.0-beta.1/ — rename or symlink:
mv /usr/local/www/mt2-cms-0.1.0-beta.1 /usr/local/www/mt2-cms
```

3. Create writable runtime dirs and set ownership to the FPM user (usually `www`):

```sh
cd /usr/local/www/mt2-cms
mkdir -p var/sessions var/rate-limit var/cache var/uploads/tickets \
  var/downloads var/backups public/uploads/banners public/uploads/logo public/uploads/seo
chown -R www:www var public/uploads
chmod 0750 var var/sessions var/rate-limit
```

4. Configure environment:

```sh
cp .env.prod-example .env
# edit .env — strong passwords; hosts for FreeBSD (not Docker service names)
```

Example production `.env` hosts (adjust jails/IPs):

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=mt2cms
DB_PASSWORD=strong-game-password

CMS_DB_HOST=127.0.0.1
CMS_DB_PORT=3306
CMS_DB_USER=cms
CMS_DB_PASSWORD=strong-cms-password
CMS_DB_NAME=cms

APP_INSTALLED=false
APP_KEY=
APP_TRUST_PROXY=1
```

`MYSQL_ROOT_PASSWORD` / `CMS_MYSQL_ROOT_PASSWORD` in `.env.prod-example` are for Compose only — unused on FreeBSD if you manage MySQL yourself. App users must still be dedicated (`mt2cms` / `cms`), not root.

Do **not** use `.env-example` (dev Docker defaults).

5. Wire Nginx + PHP-FPM using the snippets in [`deploy/freebsd/`](../deploy/freebsd/), then enable services:

```sh
sysrc nginx_enable=YES
sysrc php_fpm_enable=YES
service php-fpm start
service nginx start
```

6. Migrate schema (also generates `APP_KEY` if missing):

```sh
cd /usr/local/www/mt2-cms
php bin/migrate.php
```

7. Open `/setup` in the browser, create the first admin (`APP_INSTALLED=true`), then confirm `GET /health` returns `200` with body `ok`.

8. Put TLS + HSTS on the reverse proxy. Keep `APP_TRUST_PROXY=1` so session cookies get the `Secure` flag.

## Sample configs

| File | Purpose |
| --- | --- |
| [`deploy/freebsd/install.sh`](../deploy/freebsd/install.sh) | Root installer: packages, download release, dirs, Nginx/FPM snippets, enable services |
| [`deploy/freebsd/nginx.conf.snippet`](../deploy/freebsd/nginx.conf.snippet) | Server block: `root` = `…/public`, FastCGI to FPM socket, theme assets, deny PHP under `/uploads/` |
| [`deploy/freebsd/php-fpm-pool.conf.snippet`](../deploy/freebsd/php-fpm-pool.conf.snippet) | Pool user `www`, unix socket |
| [`deploy/freebsd/php.ini.snippet`](../deploy/freebsd/php.ini.snippet) | Hardening + production OPcache |

Copy snippets into `/usr/local/etc/nginx/` and `/usr/local/etc/php-fpm.d/` (or `php.ini` conf.d) and adjust paths.

Document root must be **`public/`** only — never the project root (keeps `.env` and `var/` off the web).

## Cron

Do **not** run workers on the HTTP request path. Example crontab for user `www` or root calling php as `www`:

```cron
* * * * * cd /usr/local/www/mt2-cms && /usr/local/bin/php bin/payments-process.php >> /var/log/mt2-cms-payments.log 2>&1
*/15 * * * * cd /usr/local/www/mt2-cms && /usr/local/bin/php bin/economy-tick.php >> /var/log/mt2-cms-economy.log 2>&1
0 3 * * * cd /usr/local/www/mt2-cms && BACKUP_DIR=/mnt/backups/mt2-cms ./bin/backup-dbs.sh >> /var/log/mt2-cms-backup.log 2>&1
```

`bin/backup-dbs.sh` is POSIX `sh` (FreeBSD base). Needs `mysqldump` and `gzip` on `PATH`. Store dumps **off** the app server when possible.

## Upgrades

1. Extract the new tarball beside the live tree (or into a staging dir).
2. Copy `.env`, `var/`, `public/uploads/`, and any local `game/` overlays from the old tree.
3. Run `php bin/migrate.php`.
4. Switch the Nginx root / symlink atomically and reload Nginx + PHP-FPM.
5. Hit `/health`.

## Post-deploy checklist

- [ ] MySQL not reachable from the internet
- [ ] Dedicated app DB users (not root)
- [ ] Fixture / `change-me-*` passwords replaced
- [ ] `php bin/migrate.php` succeeded (`APP_KEY` present)
- [ ] `GET /health` returns `200`
- [ ] HTTPS + HSTS on the proxy; `APP_TRUST_PROXY=1`
- [ ] Document root is `public/` only; `.env` and `var/` not web-accessible
- [ ] GD WebP works (`imagewebp`)
- [ ] Payment / economy / backup crons scheduled
- [ ] PayPal webhook id set when `/donate` is enabled
- [ ] Admin 2FA enrolled if **Settings → Security → Require 2FA** is on
- [ ] Default admin password changed after setup

## Creating a release (maintainers)

Current line is **beta** (`0.x`). Prefer tags `v0.1.0-beta.1`, `v0.1.0-beta.2`, … until `v1.0.0`. GitHub Actions runs [`bin/package-release.sh`](../bin/package-release.sh), attaches `mt2-cms-0.1.0-beta.1.tar.gz` (+ `.sha256`), and marks beta/rc/alpha tags as pre-release.

Locally:

```sh
./bin/package-release.sh 0.1.0-beta.1
# or: SKIP_ASSETS=1 ./bin/package-release.sh 0.1.0-beta.1   # if public/ assets already built
```

The tarball excludes Docker Compose, `docker/`, tests, and `node_modules/`.

## Out of scope

- WAF
- Replacing Metin2-compatible player password hashes (`*SHA1(SHA1)`)
- Shipping Docker images as the FreeBSD install path
