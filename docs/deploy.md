# Production deployment

Guide for running Mt2 CMS on a public server. The Docker Compose stack in this repo is a **local dev** environment — do not expose it as-is to the internet.

Related: [security.md](security.md), [improvements.md](improvements.md).

## Architecture

```
Internet → TLS reverse proxy (Caddy / Nginx / Traefik)
              ↓ HTTP to localhost:8000 (or php-fpm socket)
         Mt2 CMS (Nginx + PHP-FPM)
              ↓ private network
         MySQL (game + CMS) — never on 0.0.0.0
```

## Before go-live

1. **Complete `/setup`** or copy `.env` from `.env-example` with strong passwords (not `admin123@`).
2. **Run migrations** after every deploy that changes schema:
   ```bash
   docker compose exec php php bin/migrate.php
   ```
   From the host (outside Docker), point `.env` at the mapped CMS port: `CMS_DB_HOST=127.0.0.1`, `CMS_DB_PORT=8002`, then run `php bin/migrate.php`.
3. **Build front-end assets** on the build host (Node is not required in the PHP container):
   ```bash
   npm ci && npm run build
   ```
4. **Set `APP_INSTALLED=true`** in `.env` after setup.
5. **Set `APP_TRUST_PROXY=1`** when TLS terminates at a reverse proxy so session cookies get the `Secure` flag.

## Docker Compose (development only)

| Service | Port | Notes |
| --- | --- | --- |
| Site (Nginx) | `8000` | OK to expose locally |
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
| `DB_PASSWORD` | Yes | Strong password for game MySQL |
| `CMS_DB_PASSWORD` | Yes | Strong password for CMS MySQL |
| `APP_INSTALLED` | Yes | `true` after setup |
| `APP_TRUST_PROXY` | When behind TLS proxy | `1` to honor `X-Forwarded-Proto` for secure cookies |

See `.env-example` for the full list.

## Post-deploy checklist

- [ ] MySQL not reachable from the internet
- [ ] Adminer / phpMyAdmin not exposed
- [ ] Fixture passwords replaced
- [ ] `php bin/migrate.php` run successfully
- [ ] HTTPS + HSTS on the proxy
- [ ] `APP_TRUST_PROXY=1` if behind reverse proxy
- [ ] `var/` and `.env` not web-accessible (document root is `public/` only)
- [ ] Default admin password changed after first login

## Out of scope

- WAF
- Replacing Metin2-compatible player password hashes (`*SHA1(SHA1)`)

Captcha and admin TOTP 2FA are built in — configure under **Settings → Security** after deploy.

See [security.md](security.md) for coding rules that must stay intact on every change.
