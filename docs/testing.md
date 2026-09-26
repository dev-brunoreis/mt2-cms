# Tests

PHPUnit 11. Two suites: **unit** (default) and **integration** (opt-in smoke against a live stack).

- `composer test` — `--testsuite unit` only (`tests/Unit`)
- `composer test:integration` — `--testsuite integration` (`tests/Integration`)
- Filter: `composer test -- --filter ClassName`

The unit suite sets `failOnWarning` / `failOnDeprecation` so PHP 8.5+ host noise fails the run.

Bootstrap (`tests/bootstrap.php`) defines `BASE_DIR` and loads Composer. Mirror `src/` namespaces: `Mt2Cms\Service\Foo` → `tests/Unit/Service/FooTest.php`, namespace `Mt2Cms\Tests\Unit\Service`.

## Three kinds

| Kind | Where | What it proves | When to add |
| --- | --- | --- | --- |
| **Unit** | `tests/Unit/{Service,Auth,Admin,Payment,…}` | Behavior of one class with mocks/fakes | New service method, parser, validator, grid helper |
| **Contract** | `tests/Unit/Contract/SecurityContractTest.php` | Repo-wide invariants by **scanning source** | New POST, grid, `|raw`, SQL style, ACL, audit, rate limit |
| **Integration (smoke)** | `tests/Integration/Http/` | Live HTTP + both MySQLs via `GET /health` | Boot/routing/DB regressions; not a substitute for unit tests |

Contracts use `SourceScan` (route list, method source, reachable `$this->` calls). They do not boot the app. Skip lists (`CSRF_SKIP`, `AUDIT_SKIP`, `ACL_SKIP`, `RATE_LIMITED_POSTS`) are explicit — new endpoints must **not** land in a skip without a documented reason.

`SecurityContractTest` currently locks: prepared SQL, no request interpolation, PDO `query`/`exec` only in `Database`, CSRF on POST, Twig autoescape + `|raw` slots only, mass **xor** row actions, `runMassActions` whitelist, admin POST audit, admin POST ACL, named rate-limited POSTs.

## How to write a unit test

- `final class XTest extends TestCase`. One behavior per `test…` method.
- **No real DB.** Mock repositories/gateways (`createMock`) or a small `Fake*` subclass in the same file (`FakeAclRepository extends AclRepository` with in-memory arrays). `new Database(['requirePassword' => false])` is only for constructing a repo to test **validation that runs before SQL**.
- Prefer `expects(self::once())->method(…)` for side effects (notify, markFailed). Prefer `self::assertSame` for values.
- Exceptions: `$this->expectException(InvalidArgumentException::class)` and `expectExceptionMessage('i18n.key')` — messages are keys, not English copy.
- If the SUT reads `$_GET`/`$_POST`/`$_SESSION`, set them in the test and `tearDown()` back to `[]`. Session: `session_start()` in `setUp` if needed (`CsrfTest`).
- Do not hit PayPal, Discord, or the filesystem except tmp under the test when the class under test is a file helper (rate limit, uploads). Prefer mocks.

## Integration smoke

Requires the app installed and reachable (typically `docker compose up -d` on `http://127.0.0.1:8000`). Override with `MT2CMS_BASE_URL`.

```bash
docker compose up -d
composer test:integration
```

If the base URL is unreachable, tests `markTestSkipped` (not a failure). `/health` asserts both CMS and game MySQL respond. There is no browser/Dusk suite — UI stays manual + Twig contracts.

HTTP/MySQL live checks belong only under `tests/Integration/`. Do not add them to `tests/Unit/`.

## GitHub Actions

- `.github/workflows/ci.yml` — `composer test` (unit) on PHP 8.3
- `.github/workflows/psalm.yml` — Psalm 6 taint analysis on PHP 8.3 (`psalm.xml` → `src/`), SARIF to code scanning. Does not use `psalm/psalm-github-security-scan` (that image is PHP 8.2).

## What to add when you change production code

| You added | Also add |
| --- | --- |
| Public or admin **POST** | Nothing extra if CSRF/ACL/audit/rate-limit contracts still scan it. If it must be rate-limited, append to `RATE_LIMITED_POSTS`. |
| Admin **mass** action | Contract already checks `runMassActions` + spec ids. |
| `AdminResourceCatalog` id | Assert the id (or mapping) in `AdminResourceCatalogTest`. |
| Service / payment / SEO behavior | `tests/Unit/Service/…Test` (or `Payment/`) with mocks; see `PaymentCheckoutServiceTest`. |
| Pure helper (money, slug, grid request) | Direct unit test, no mocks. |
| New invariant (all X must Y) | New method on `SecurityContractTest` + a line in [security.md](security.md). |
| New public GET that must stay up | Optional assert in `tests/Integration/Http/PublicSmokeTest` |

## Catalog of existing unit areas

Auth (`Csrf`, `RateLimiter`, `SessionGuard`, `Totp`, `Captcha`, `SessionConfig`), Admin (`AdminResourceCatalog`, `AdminPaths`, `RoleSlug`, `AdminAuditMeta`, `AdminSidebarScript`, `Grid/*`), Service (ACL, payments, SEO, SEO image paths, economy math, unstuck, referral, events, uploads, census, first-run news/banner/event seeds), Payment (gateway registry, webhook envelope/parser, checkout URL), I18n (including locale path reject), Support (`HtmlSanitizer`, `Money`, `AppCrypto`, `DayChart`, `ComposerAutoload`), Setup (`SetupDatabaseDefaults`, `SetupInstaller`, `EnvWriter`, theme meta path reject, shipped theme catalog), Repository (validation-only, banner image URLs), Theme layout extends, theme asset paths (`ThemeAssetFile`), Game proto/display.

Copy the nearest test in that folder. Live stack checks go in `tests/Integration/`, not Unit.
