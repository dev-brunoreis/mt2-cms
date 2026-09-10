# Security

What the CMS already enforces, and what every change must keep intact.

## Already covered

| Area | Behavior |
| --- | --- |
| SQL injection | PDO prepared statements; `ATTR_EMULATE_PREPARES = false`; schema names via `quoteIdentifier` |
| XSS | Twig `autoescape` = `html`; `|raw` only for layout slots already rendered by Twig |
| CSRF | Session token + `hash_equals`; required on all mutating POSTs |
| Auth | `hash_equals` for password compare; hash never returned; `reveal()` strips secrets |
| Open redirect | `Locales::safeRedirect` — internal paths only |
| Cookies | Locale cookie: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS |
| Session | Hardened cookie params; separate admin cookie (`MT2ADMIN`, path `/admin`) vs public (`MT2CMS`); `session_regenerate_id(true)` on successful login |
| Brute force | File-backed IP + action rate limit on login/register/admin login and other sensitive POSTs (`var/rate-limit/`); fail-closed when storage is unavailable |
| Admin ACL | Resource-based access via `AclService` (Magento-style hierarchical IDs in `acl_role_resources` / `acl_admin_resources`, catalog in `AdminResourceCatalog`). Section checks in nav map to resource prefixes; mutations use `requireAdminResource()`. Super-only: admins, roles, audit log. Game-data modules are assignable but hidden from the sidebar — access by URL when permitted |
| Admin audit | Mutating admin POSTs write to `admin_audit_log`; super admins can browse `/admin/audit-log` |
| Response headers | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP (self-hosted CSS/JS only) |
| Config | `DB_PASSWORD` required in `.env` (no hardcoded runtime default) |

## Password hashing

Accounts use Metin2 / MySQL `PASSWORD()` style (`*` + uppercase `SHA1(SHA1(password, binary))`) so the **game client** can authenticate the same row. Do not switch to bcrypt/argon2 without a separate game-side plan.

## Rules for new code

1. **SQL** — never concatenate request data into SQL. Use `?` + bind. Identifiers only through validated helpers.
2. **POST** — always `assertCsrf()` before writes. Include `_csrf` in forms.
3. **Twig** — never `|raw` on user input, flash text from untrusted sources, or DB strings. Prefer autoescape.
4. **Auth responses** — generic failure messages (do not reveal whether a login exists).
5. **Redirects** — only relative same-origin paths (`/`…), reject `//` and CR/LF.
6. **Secrets** — never log passwords, PINs, or hashes; never put them in Twig context.
7. **Headers** — add via `Response::withHeader()` / defaults on `Response`, not ad-hoc `header()` in controllers.

## PR checklist

- [ ] New query uses prepared statements
- [ ] New POST validates CSRF
- [ ] New cookie is HttpOnly + SameSite (Secure when HTTPS)
- [ ] New Twig output is escaped (no `|raw` on data)
- [ ] No new hardcoded DB credentials in source
- [ ] Rate-sensitive auth endpoints stay behind the limiter

## Production deployment

See [deploy.md](deploy.md): TLS and HSTS on the reverse proxy, MySQL/Adminer not on `0.0.0.0`, `php bin/migrate.php` after deploy, `APP_TRUST_PROXY=1` when TLS terminates at a proxy.

## Out of scope (for now)

- HSTS in PHP (Compose serves HTTP on `:8000`; set HSTS on the proxy)
- Captcha / 2FA / WAF
- Replacing game-compatible password hash
