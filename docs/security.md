# Security

What the CMS already enforces, and what every change must keep intact.

## Already covered

| Area | Behavior |
| --- | --- |
| SQL injection | PDO prepared statements; `ATTR_EMULATE_PREPARES = false`; schema names via `quoteIdentifier` |
| XSS | Twig `autoescape` = `html`; `|raw` only for layout slots already rendered by Twig |
| CSRF | Session token + `hash_equals`; required on all mutating POSTs |
| Auth | `hash_equals` for password compare; hash never returned; `reveal()` strips secrets |
| Open redirect | `Locales::safeRedirect` — internal paths only (also the header sanitizer for `Location`) |
| Locale / theme files | Locale and theme names must match `^[A-Za-z0-9_-]+$` before `file_get_contents` |
| Cookies | Locale cookie: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS |
| Session | Hardened cookie params; separate admin cookie (`MT2ADMIN`, path `/admin`) vs public (`MT2CMS`); `session_regenerate_id(true)` on successful login; idle timeout (admin 30 min, public 2 h) via `SessionGuard`. Admin UI language switch posts to `/admin/locale` (not `/locale`) so CSRF uses the admin session |
| Brute force | File-backed IP + action rate limit on login/register/admin login, password change, and other sensitive POSTs (`var/rate-limit/`); fail-closed when storage is unavailable |
| Captcha | Self-hosted SVG captcha on public login/register, guest sidebar login, and admin login (toggle in `/admin/settings?tab=security`; on by default on new installs) |
| Admin 2FA | TOTP + one-time recovery codes; enrollment at `/admin/account/security`; optional policy requiring 2FA for all admins (off by default on new installs); TOTP secrets encrypted at rest with `APP_KEY` |
| Player password | Self-service change at `/account/password` (current password required, CSRF, rate limited); Metin2-compatible hash unchanged |
| Errors | Unhandled exceptions logged via `Log`; generic HTTP 500 to clients (no stack traces) |
| Admin ACL | See [acl.md](acl.md). Resource-based access via `AclService` (hierarchical IDs in `acl_role_resources` / `acl_admin_resources`, catalog in `AdminResourceCatalog`). `adminView()` is section-level; mutations use `requireAdminResource()`. Super-only: admins, roles, audit log |
| Security contracts | `tests/Unit/Contract/SecurityContractTest.php` — CSRF, mass-action whitelist, rate-limited POSTs, prepared statements, **admin POST ACL**. Keep green on every PR |
| Admin audit | Mutating admin POSTs write to `admin_audit_log`; super admins can browse `/admin/audit-log` |
| Response headers | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP (self-hosted CSS/JS only; `form-action 'self'`) |
| Config | `DB_PASSWORD` and `APP_KEY` required in `.env` when installed (no hardcoded runtime default) |
| PayPal webhooks | Signature verification is fail-closed; webhook id required in **Settings → Payment methods** for `/donate` and webhook acceptance. Raw bodies are stored in `cms_payment_events` and shown only on the admin payment detail (`store/payments/view`). Invalid signatures are not attached to a payment. |
| Operational errors | Schema drift and missing `APP_KEY` return generic HTTP 503; details logged server-side only. Missing Composer autoload is a pre-boot HTTP 503 with an install hint (Twig/`Log` cannot load) |

## Password hashing

Accounts use Metin2 / MySQL `PASSWORD()` style (`*` + uppercase `SHA1(SHA1(password, binary))`) so the **game client** can authenticate the same row. Do not switch to bcrypt/argon2 without a separate game-side plan.

## Rules for new code

1. **SQL** — never concatenate request data into SQL. Use `?` + bind. Identifiers only through validated helpers.
2. **POST** — always `assertCsrf()` before writes. Include `_csrf` in forms.
3. **Twig** — never `|raw` on user input, flash text from untrusted sources, or DB strings. Prefer autoescape.
4. **Auth responses** — generic failure messages (do not reveal whether a login exists).
5. **Redirects** — only relative same-origin paths (`/`…), reject `//` and CR/LF. After a donate POST, hop to `/donate/pay` then navigate to the provider; a 302 to PayPal is blocked by `form-action 'self'`.
6. **Secrets** — never log passwords, PINs, or hashes; never put them in Twig context.
7. **Headers** — add via `Response::withHeader()` / defaults on `Response`, not ad-hoc `header()` in controllers.

## PR checklist

- [ ] New query uses prepared statements
- [ ] New POST validates CSRF
- [ ] New cookie is HttpOnly + SameSite (Secure when HTTPS)
- [ ] New Twig output is escaped (no `|raw` on data)
- [ ] No new hardcoded DB credentials in source
- [ ] Rate-sensitive auth endpoints stay behind the limiter
- [ ] New admin POST checks ACL (`requireAdminResource` / `runMassActions` / `requireAdminSection`); id is in `AdminResourceCatalog`
- [ ] Matching `docs/` file updated if behavior changed ([map.md](map.md)); unit test if logic changed ([testing.md](testing.md))
- [ ] `SecurityContractTest` passes (`composer test`)

## Production deployment

See [deploy.md](deploy.md) (Linux CMS host / Compose) and [game-mysql.md](game-mysql.md) (remote game MySQL, host-scoped `mt2cms` user): TLS and HSTS on the reverse proxy, MySQL not on `0.0.0.0`, dedicated app users (not root), `php bin/migrate.php` after deploy (schema + `APP_KEY`), PayPal webhook id when donate is enabled, `APP_TRUST_PROXY=1` when TLS terminates at a proxy, optional admin 2FA enrollment. Keep the CMS off the Metin2 game FreeBSD box.

## Out of scope (for now)

- HSTS in PHP (Compose serves HTTP on `:8000`; set HSTS on the proxy)
- WAF
- Replacing game-compatible password hash
