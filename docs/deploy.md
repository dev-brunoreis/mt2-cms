# Production deployment (Linux Compose)

Guide for running Mt2 CMS with **`compose.prod.yml`** on a Linux host. The default `compose.yml` stack is **local dev only** — do not expose it as-is to the internet.

**FreeBSD / bare-metal (recommended for GitHub Releases):** use [deploy-freebsd.md](deploy-freebsd.md) instead. That path installs from a release tarball with Nginx + PHP-FPM — no Docker on the server.

Related: [deploy-freebsd.md](deploy-freebsd.md), [security.md](security.md), [improvements.md](improvements.md).

## Architecture

```
Internet → TLS reverse proxy (Caddy / Nginx / Traefik)
              ↓ HTTP to localhost:8000 (or php-fpm socket)
         Mt2 CMS (Nginx + PHP-FPM)
              ↓ private network
         MySQL (game + CMS) — never on 0.0.0.0
```

## Before go-live

1. Copy **`.env.prod-example`** to `.env` and set **strong passwords** (not the placeholders). This file includes `MYSQL_ROOT_PASSWORD`, `CMS_MYSQL_ROOT_PASSWORD`, and dedicated app users (`DB_USER=mt2cms`, `CMS_DB_USER=cms`) required by `compose.prod.yml`.
   ```bash
   cp .env.prod-example .env
   # edit .env — replace every change-me-* password
   ```
   Do **not** use `.env-example` for production — it targets the dev stack (`compose.yml`) and omits the MySQL root variables.
2. **Build and start** the production stack (assets and Composer deps are baked into the image — Node is not required on the host):
   ```bash
   docker compose -f compose.prod.yml up -d --build
   ```
4. **Run migrations** after every deploy that changes schema (also generates `APP_KEY` if missing):
   ```bash
   docker compose -f compose.prod.yml exec php php bin/migrate.php
   ```
   The PHP image runs as your host UID/GID (`PUID` / `PGID`, default `1000`) so the bind-mounted `.env` stays readable/writable. Rebuild after changing them: `PUID=$(id -u) PGID=$(id -g) docker compose -f compose.prod.yml up -d --build`.
   From the host (outside Docker), point `.env` at the mapped CMS port: `CMS_DB_HOST=127.0.0.1`, `CMS_DB_PORT=8002`, then run `php bin/migrate.php`.
5. **Set `APP_INSTALLED=true`** in `.env` after setup (`/setup` writes this automatically). First HTTP boot then seeds class banners and a welcome news post if those tables are empty ([setup.md](setup.md)).
6. **Confirm `APP_KEY`** is present in `.env` (64 hex chars). Setup and migrate generate it; the app returns a generic 503 without it (details are logged server-side only).
7. **`APP_TRUST_PROXY=1`** is set in `compose.prod.yml`. Keep it when TLS terminates at a reverse proxy so session cookies get the `Secure` flag.
8. **Enroll admin 2FA** on first login (`/admin/account/security`) when the require-2FA policy is enabled (off by default on new installs; enable under **Settings → Security**).
9. Configure **Settings → Community** (site URL, mail, Discord) and **Settings → Payment methods** (PayPal client id/secret, **PayPal webhook id** required for `/donate`).

## Production Compose

Use `compose.prod.yml` for a single-node production stack:

- Immutable PHP and Nginx images (no source bind-mount)
- MySQL/Adminer **not** published on host ports
- Dedicated app users for game and CMS databases (root passwords are separate)
- Persistent volumes: `var/`, `public/uploads/`, database data; read-only mount of `game/` (proto/drops/icons)
- Built-in health checks (`GET /health`, PHP-FPM ping)

```bash
docker compose -f compose.prod.yml up -d --build
docker compose -f compose.prod.yml exec php php bin/migrate.php
```

Configure a TLS reverse proxy in front of `127.0.0.1:8000`.

### External game database

If the game MySQL already runs on the game server, omit the `game` service and point `.env` at that host:

```env
DB_HOST=10.0.0.5
DB_PORT=3306
DB_USER=mt2cms
DB_PASSWORD=...
```

The CMS container still needs reachability to the game MySQL from the Docker network or host routing.

## Docker Compose (development only)

| Service | Port | Notes |
| --- | --- | --- |
| Site (Nginx) | `127.0.0.1:8000` | Loopback only in Compose — not world-reachable |
| Game MySQL | `127.0.0.1:8001` | Loopback only — not reachable from LAN |
| CMS MySQL | `127.0.0.1:8002` | Loopback only |
| Adminer | `127.0.0.1:8080` | Opt-in: `docker compose --profile tools up` |

Do **not** publish MySQL or Adminer on `0.0.0.0` in production. Use a managed database or a private Docker network with no host port mapping.

## Reverse proxy and TLS

- Terminate TLS at the proxy (Let's Encrypt, etc.).
- Proxy to `http://127.0.0.1:8000` or directly to PHP-FPM.
- Set **`Strict-Transport-Security`** (HSTS) on the proxy — not in PHP. The Compose stack serves plain HTTP on `:8000` by design.
- Forward `X-Forwarded-Proto: https` when using HTTPS upstream; the app reads this when `APP_TRUST_PROXY=1`.

Example Nginx snippet (proxy only — adjust `server_name` and cert paths):

```nginx
server {
    listen 443 ssl http2;
    server_name cms.example.com;

    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## Environment variables (production)

| Variable | Required | Notes |
| --- | --- | --- |
| `DB_USER` | Yes | Game DB app user (default `mt2cms` in compose.prod.yml) — **not** root |
| `DB_PASSWORD` | Yes | Password for `DB_USER` |
| `MYSQL_ROOT_PASSWORD` | Yes (compose.prod) | Game MySQL root — separate from `DB_PASSWORD`; not read by PHP |
| `CMS_DB_USER` | Yes | CMS DB app user (default `cms`) |
| `CMS_DB_PASSWORD` | Yes | Password for `CMS_DB_USER` |
| `CMS_MYSQL_ROOT_PASSWORD` | Yes (compose.prod) | CMS MySQL root — separate from `CMS_DB_PASSWORD` |
| `APP_INSTALLED` | Yes | `true` after setup |
| `APP_KEY` | Yes (when installed) | 64-char hex; generated by setup or `php bin/migrate.php` |
| `APP_TRUST_PROXY` | When behind TLS proxy | `1` to honor `X-Forwarded-Proto` for secure cookies |
| `APP_URL` | Recommended | Public site URL for email links (or set in **Settings → Community**) |
| `MAIL_DSN` or `MAIL_*` | For password reset / verify | Symfony Mailer SMTP settings |
| `PAYPAL_CLIENT_ID` / `PAYPAL_CLIENT_SECRET` | For `/donate` | PayPal REST app credentials (or set in admin) |

See `.env-example` for the full list.

Configure **Settings → Community** after deploy: site URL, mail from name/address, online window, Discord invite/webhook URLs, and server channel labels. Configure **Settings → Payment methods** for PayPal mode/currency and **PayPal webhook id** (required when donate is enabled).

Register PayPal webhook URL: `POST /payments/webhook/paypal` (HTTPS, public site URL). Webhook signature verification is **fail-closed** — without a webhook id, `/donate` stays disabled and unsigned webhooks are rejected.

### Upgrading existing MySQL volumes

Init scripts run only on **empty** data directories. If you already have game/CMS volumes from an older stack that used root for the app, create the app users manually:

```sql
-- Game MySQL (as root)
CREATE USER 'mt2cms'@'%' IDENTIFIED BY 'strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON account.* TO 'mt2cms'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON player.* TO 'mt2cms'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON common.* TO 'mt2cms'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON log.* TO 'mt2cms'@'%';
FLUSH PRIVILEGES;
```

```sql
-- CMS MySQL 8 (as root)
CREATE USER IF NOT EXISTS 'cms'@'%' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON cms.* TO 'cms'@'%';
FLUSH PRIVILEGES;
```

From the host (replace passwords with your `.env` values):

```bash
docker compose -f compose.prod.yml exec mysql \
  mysql -uroot -p"$CMS_MYSQL_ROOT_PASSWORD" -e "
    CREATE USER IF NOT EXISTS 'cms'@'%' IDENTIFIED BY '$CMS_DB_PASSWORD';
    GRANT ALL PRIVILEGES ON cms.* TO 'cms'@'%';
    FLUSH PRIVILEGES;"
```

Then update `.env` to use `DB_USER=mt2cms`, `CMS_DB_USER=cms`, and restart PHP.

**Fresh local prod test** (wipes databases): `docker compose -f compose.prod.yml down -v` then `up -d --build` again.

The bundled `game` service imports `docker/mysql/backup/*.sql` on first start (same fixtures as the dev stack). Omit the `game` service in real production and point `DB_HOST` at the live Metin2 MySQL instead.

## Health checks

- **`GET /health`** — returns `200` with body `ok` when both game and CMS databases respond; `503` otherwise (generic message, no internal details).
- Docker healthchecks: PHP-FPM ping (`/ping` → `pong`) and Nginx `wget` against `/health`.

Use `/health` from your external monitoring or load balancer after TLS termination.

## PHP runtime (production)

The production PHP image (`docker/php/Dockerfile` target `php`) ships:

- `docker/php/zz-hardening.ini` — `display_errors=Off`, 512M upload limits
- `docker/php/zz-opcache-prod.ini` — OPcache with `validate_timestamps=0`
- PHP-FPM ping endpoint for container healthchecks
- **GD** with JPEG / PNG / WebP — required for admin banner variant generation

Mirror these in your production `php.ini` if you deploy without the Compose image. Banner CMS uploads need the GD extension (`jpeg`, `png`, `webp`).

Nginx (`docker/nginx/default.conf`): `client_max_body_size 512m` (aligned with download uploads), static `/uploads/` without FastCGI, denies `*.php` under `/uploads/`. Download files are stored under `var/downloads/`, not `public/`. Banner images live under `public/uploads/banners/`.

PHP sessions are stored in `var/sessions/` (0750). Use sticky sessions if you run more than one PHP worker.

## Backups

Schedule regular **mysqldump** (or managed-DB snapshots) for both databases:

- Game MySQL (`account`, `player`, …)
- CMS MySQL (`cms` schema: admins, settings, news, tickets, ACL, …)

Store dumps off-server and test restores periodically. The CMS does not include a backup daemon.

Example cron (daily at 03:00, from the project root on the host). Set `BACKUP_DIR` to a path **outside** the application tree (off-server mount or remote sync):

```cron
0 3 * * * cd /path/to/mt2-cms && BACKUP_DIR=/mnt/backups/mt2-cms ./bin/backup-dbs.sh >> /var/log/mt2-cms-backup.log 2>&1
```

### Economy tick (item census / market snapshots)

Admin **Game → Economy** reads CMS snapshot tables only. Refresh them every 15 minutes (do **not** run this on the HTTP request path):

```cron
*/15 * * * * cd /path/to/mt2-cms && docker compose exec -T php php bin/economy-tick.php >> /var/log/mt2-cms-economy.log 2>&1
```

The tick takes a file lock under `var/economy-tick.lock` so overlapping runs skip. First run after deploy may take longer (full `GROUP BY` on `player.item`).

### Payment webhook worker

`POST /payments/webhook/{provider}` stores the raw postback and returns `200` after signature verification. Capture and cash credit run in the worker (and once after the HTTP response via a shutdown hook). Drain the queue every minute:

```cron
* * * * * cd /path/to/mt2-cms && docker compose exec -T php php bin/payments-process.php >> /var/log/mt2-cms-payments.log 2>&1
```

The worker takes a file lock under `var/payments-process.lock`. Failed captures retry with backoff (1, 2, 5, then 15 minutes) up to 10 attempts. Raw payloads appear on **Admin → Store → Payments → detail**.

Do **not** treat `var/backups/` on the app server as off-site backup storage. Do **not** treat `docker/mysql/backup/*.sql` as production backups — those are dev fixtures only.

For FreeBSD bare-metal installs (no Compose), see [deploy-freebsd.md](deploy-freebsd.md).

## Post-deploy checklist

- [ ] MySQL not reachable from the internet
- [ ] Adminer / phpMyAdmin not exposed
- [ ] App uses dedicated MySQL users (`DB_USER`, `CMS_DB_USER`), not root
- [ ] Fixture passwords replaced
- [ ] `docker compose -f compose.prod.yml up -d --build` succeeded
- [ ] `php bin/migrate.php` run successfully (schema + `APP_KEY`)
- [ ] `GET /health` returns `200`
- [ ] HTTPS + HSTS on the proxy
- [ ] `APP_TRUST_PROXY=1` if behind reverse proxy
- [ ] PayPal webhook id configured when `/donate` is enabled
- [ ] `var/` and `.env` not web-accessible (document root is `public/` only)
- [ ] Admin 2FA enrolled if **Settings → Security → Require 2FA** is enabled
- [ ] Default admin password not reused from setup
- [ ] Database backups scheduled to off-server storage

## Out of scope

- WAF
- Replacing Metin2-compatible player password hashes (`*SHA1(SHA1)`)

Captcha and admin TOTP 2FA are built in — configure under **Settings → Security** after deploy.

See [security.md](security.md) for coding rules that must stay intact on every change.
