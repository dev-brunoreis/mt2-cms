# Tests

PHPUnit 11, suite `tests/Unit` only (no HTTP/MySQL integration). Run `composer test` or `composer test -- --filter ClassName`.

Bootstrap (`tests/bootstrap.php`) defines `BASE_DIR` and loads Composer. Mirror `src/` namespaces: `Mt2Cms\Service\Foo` → `tests/Unit/Service/FooTest.php`, namespace `Mt2Cms\Tests\Unit\Service`.

## Two kinds

| Kind | Where | What it proves | When to add |
| --- | --- | --- | --- |
| **Unit** | `tests/Unit/{Service,Auth,Admin,Payment,…}` | Behavior of one class with mocks/fakes | New service method, parser, validator, grid helper |
| **Contract** | `tests/Unit/Contract/SecurityContractTest.php` | Repo-wide invariants by **scanning source** | New POST, grid, `|raw`, SQL style, ACL, audit, rate limit |

Contracts use `SourceScan` (route list, method source, reachable `$this->` calls). They do not boot the app. Skip lists (`CSRF_SKIP`, `AUDIT_SKIP`, `ACL_SKIP`, `RATE_LIMITED_POSTS`) are explicit — new endpoints must **not** land in a skip without a documented reason.

`SecurityContractTest` currently locks: prepared SQL, no request interpolation, PDO `query`/`exec` only in `Database`, CSRF on POST, Twig autoescape + `|raw` slots only, mass **xor** row actions, `runMassActions` whitelist, admin POST audit, admin POST ACL, named rate-limited POSTs.

## How to write a unit test

- `final class XTest extends TestCase`. One behavior per `test…` method.
- **No real DB.** Mock repositories/gateways (`createMock`) or a small `Fake*` subclass in the same file (`FakeAclRepository extends AclRepository` with in-memory arrays). `new Database(['requirePassword' => false])` is only for constructing a repo to test **validation that runs before SQL**.
- Prefer `expects(self::once())->method(…)` for side effects (notify, markFailed). Prefer `self::assertSame` for values.
- Exceptions: `$this->expectException(InvalidArgumentException::class)` and `expectExceptionMessage('i18n.key')` — messages are keys, not English copy.
- If the SUT reads `$_GET`/`$_POST`/`$_SESSION`, set them in the test and `tearDown()` back to `[]`. Session: `session_start()` in `setUp` if needed (`CsrfTest`).
- Do not hit PayPal, Discord, or the filesystem except tmp under the test when the class under test is a file helper (rate limit, uploads). Prefer mocks.

## What to add when you change production code

| You added | Also add |
| --- | --- |
| Public or admin **POST** | Nothing extra if CSRF/ACL/audit/rate-limit contracts still scan it. If it must be rate-limited, append to `RATE_LIMITED_POSTS`. |
| Admin **mass** action | Contract already checks `runMassActions` + spec ids. |
| `AdminResourceCatalog` id | Assert the id (or mapping) in `AdminResourceCatalogTest`. |
| Service / payment / SEO behavior | `tests/Unit/Service/…Test` (or `Payment/`) with mocks; see `PaymentCheckoutServiceTest`. |
| Pure helper (money, slug, grid request) | Direct unit test, no mocks. |
| New invariant (all X must Y) | New method on `SecurityContractTest` + a line in [security.md](security.md). |

There is no browser/Dusk suite. UI changes: keep Twig contracts (`|raw`, autoescape) green and exercise via the app when the behavior is visual.

## Catalog of existing unit areas

Auth (`Csrf`, `RateLimiter`, `SessionGuard`, `Totp`, `Captcha`, `SessionConfig`), Admin (`AdminResourceCatalog`, `AdminPaths`, `RoleSlug`, `AdminAuditMeta`, `Grid/*`), Service (ACL, payments, SEO, SEO image paths, economy math, unstuck, referral, events, uploads, census, first-run news/banner/event seeds), Payment (gateway registry, webhook envelope/parser, checkout URL), I18n, Support (`HtmlSanitizer`, `Money`, `AppCrypto`, `DayChart`, `ComposerAutoload`), Repository (validation-only, banner image URLs), Theme layout extends, Game proto/display.

Copy the nearest test in that folder; do not start an integration test with Docker MySQL unless the project later adds a second suite.
