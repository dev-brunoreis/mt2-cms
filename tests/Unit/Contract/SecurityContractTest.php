<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Contract;

use Mt2Cms\Http\Controller\AdminAccountSecurityController;
use Mt2Cms\Http\Controller\AdminAuthController;
use Mt2Cms\Http\Controller\PaymentWebhookController;
use Mt2Cms\Http\Controller\SetupController;
use PHPUnit\Framework\TestCase;

/**
 * Source-level security contracts. New SQL, POST, grid, Twig, audit, or
 * auth endpoints must keep these green — see docs/security.md.
 */
final class SecurityContractTest extends TestCase
{
    private const CSRF_SKIP = [
        PaymentWebhookController::class . '::paypal',
    ];

    private const AUDIT_SKIP = [
        AdminAuthController::class . '::login',
        AdminAuthController::class . '::verifyTwoFactor',
        AdminAuthController::class . '::logout',
        AdminAccountSecurityController::class . '::startEnroll',
    ];

    /** @var list<string> */
    private const RATE_LIMITED_POSTS = [
        'Mt2Cms\\Http\\Controller\\AuthController::login',
        'Mt2Cms\\Http\\Controller\\AuthController::register',
        'Mt2Cms\\Http\\Controller\\PasswordController::forgot',
        'Mt2Cms\\Http\\Controller\\PasswordController::reset',
        'Mt2Cms\\Http\\Controller\\AccountController::updatePassword',
        'Mt2Cms\\Http\\Controller\\AccountController::unstuck',
        'Mt2Cms\\Http\\Controller\\AdminAuthController::login',
        'Mt2Cms\\Http\\Controller\\AdminAuthController::verifyTwoFactor',
        'Mt2Cms\\Http\\Controller\\NewsController::comment',
        'Mt2Cms\\Http\\Controller\\TicketController::store',
        'Mt2Cms\\Http\\Controller\\ItemShopController::buy',
        'Mt2Cms\\Http\\Controller\\DonateController::buy',
        'Mt2Cms\\Http\\Controller\\SetupController::submit',
    ];

    public function testDatabaseAlwaysPreparesStatements(): void
    {
        $source = SourceScan::read(BASE_DIR . '/src/Model/Database.php');

        self::assertStringContainsString('ATTR_EMULATE_PREPARES => false', $source);
        self::assertMatchesRegularExpression('/function query\(.*\$stmt = \$this->conn->prepare\(\$sql\);/s', $source);
        self::assertMatchesRegularExpression(
            '/function useDatabase\(.*quoteIdentifier\(\$database\).*\$this->conn->exec\("USE `/s',
            $source,
        );
    }

    public function testSqlDoesNotInterpolateRequestData(): void
    {
        $violations = [];

        foreach (SourceScan::phpFiles(BASE_DIR . '/src') as $file) {
            $relative = substr($file, strlen(BASE_DIR) + 1);
            $source = SourceScan::read($file);

            if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)\s*\[/', $source)
                && preg_match('/\$this->db(?:\(\))?->(?:query|fetch|fetchAll|fetchColumn|execute)\s*\(/', $source)
                && preg_match('/(?:query|fetch|fetchAll|fetchColumn|execute)\s*\(\s*(?:[\'"][^\'"]*[\'"]\s*\.\s*)?\$_(GET|POST|REQUEST)/', $source)
            ) {
                $violations[] = $relative;
            }

            if (preg_match('/\$_(GET|POST|REQUEST).{0,80}(?:SELECT|INSERT|UPDATE|DELETE|FROM|WHERE)/is', $source)
                && str_contains($relative, 'Repository')
            ) {
                $violations[] = $relative . ' (request data near SQL in repository)';
            }
        }

        self::assertSame([], $violations, "Request data must not be interpolated into SQL:\n" . implode("\n", $violations));
    }

    public function testPdoQueryAndExecStayInsideDatabase(): void
    {
        $violations = [];

        foreach (SourceScan::phpFiles(BASE_DIR . '/src') as $file) {
            $relative = substr($file, strlen(BASE_DIR) + 1);
            $source = SourceScan::read($file);

            if ($relative === 'src/Model/Database.php') {
                continue;
            }

            if (preg_match('/\$this->conn->(?:query|exec)\s*\(/', $source)
                || preg_match('/\b(?:PDO|\$pdo|\$conn)->(?:query|exec)\s*\(/', $source)
            ) {
                $violations[] = $relative;
            }
        }

        self::assertSame([], $violations, "Raw PDO query/exec must go through Database:\n" . implode("\n", $violations));
    }

    public function testPostHandlersAssertCsrf(): void
    {
        $missing = [];

        foreach (SourceScan::postRoutes() as $route) {
            $id = $route['class'] . '::' . $route['method'];

            if (in_array($id, self::CSRF_SKIP, true)) {
                continue;
            }

            $source = SourceScan::reachableSource($route['class'], $route['method']);

            if (!str_contains($source, 'assertCsrf(')
                && !str_contains($source, 'runMassActions(')
                && !str_contains($source, 'csrf->validate(')
            ) {
                $missing[] = $route['path'] . ' → ' . $id;
            }
        }

        self::assertNotSame([], SourceScan::postRoutes());
        self::assertSame([], $missing, "POST handlers must call assertCsrf() or runMassActions():\n" . implode("\n", $missing));
    }

    public function testTwigAutoescapeIsHtml(): void
    {
        $engine = SourceScan::read(BASE_DIR . '/src/Theme/ThemeEngine.php');

        self::assertStringContainsString("'autoescape' => 'html'", $engine);
        self::assertSame(
            1,
            preg_match_all('/new Environment\s*\(/', $engine),
        );

        foreach (SourceScan::phpFiles(BASE_DIR . '/src') as $file) {
            if (str_ends_with($file, 'ThemeEngine.php')) {
                continue;
            }

            self::assertStringNotContainsString(
                'new Environment(',
                SourceScan::read($file),
                substr($file, strlen(BASE_DIR) + 1) . ' must not create a Twig environment',
            );
        }
    }

    public function testRawFilterIsOnlyUsedOnLayoutSlots(): void
    {
        $violations = [];

        foreach (SourceScan::twigFiles() as $file) {
            $relative = substr($file, strlen(BASE_DIR) + 1);
            $source = SourceScan::read($file);

            if (preg_match('/\{%\s*autoescape\s+(false|off|js)/', $source)) {
                $violations[] = $relative . ' disables HTML autoescape';
            }

            if (!preg_match_all('/\{\{\s*([^}]+)\|raw\s*\}\}/', $source, $matches)) {
                continue;
            }

            foreach ($matches[1] as $expr) {
                if (!preg_match('/^slots\.(main|sidebar|header|footer)$/', trim($expr))) {
                    $violations[] = $relative . ': {{ ' . trim($expr) . '|raw }}';
                }
            }
        }

        self::assertSame([], $violations, "Twig |raw is only allowed on layout slots:\n" . implode("\n", $violations));
    }

    public function testSafeHtmlFiltersSanitizeFirst(): void
    {
        $source = SourceScan::read(BASE_DIR . '/src/Theme/TwigExtension.php');

        self::assertMatchesRegularExpression(
            "/TwigFilter\\('news_html'.*?sanitize\\(\\(string\\) \\\$html\\).*?\\['is_safe' => \\['html'\\]\\]/s",
            $source,
        );
        self::assertMatchesRegularExpression(
            "/TwigFilter\\('ticket_html'.*?ticketHtml\\(\\(string\\) \\\$html\\).*?\\['is_safe' => \\['html'\\]\\]/s",
            $source,
        );
    }

    public function testGridsDoNotCombineMassActionsAndRowActions(): void
    {
        $violations = [];

        foreach (SourceScan::phpFiles(BASE_DIR . '/src') as $file) {
            $source = SourceScan::read($file);

            if (!str_contains($source, 'GridDefinition::create')) {
                continue;
            }

            if (!preg_match_all(
                '/function\s+\w+\s*\([^)]*\)[^{]*\{(?:[^{}]|(?R))*\}/s',
                $source,
                $methods,
            )) {
                if (str_contains($source, '->massActions(')
                    && preg_match("/['\"]type['\"]\s*=>\s*['\"]actions['\"]/", $source)
                ) {
                    $violations[] = substr($file, strlen(BASE_DIR) + 1);
                }

                continue;
            }

            foreach ($methods[0] as $method) {
                if (!str_contains($method, 'GridDefinition::create') && !str_contains($method, '->columns(')) {
                    continue;
                }

                $hasMass = str_contains($method, '->massActions(');
                $hasRowActions = (bool) preg_match("/['\"]type['\"]\s*=>\s*['\"]actions['\"]/", $method);

                if ($hasMass && $hasRowActions) {
                    $violations[] = substr($file, strlen(BASE_DIR) + 1);
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "Grids must use mass actions or a row actions column, never both:\n" . implode("\n", $violations),
        );
    }

    public function testMassPostHandlersUseRunMassActions(): void
    {
        $missing = [];

        foreach (SourceScan::postRoutes() as $route) {
            if (!str_contains($route['path'], '/mass')) {
                continue;
            }

            $source = SourceScan::methodSource(new \ReflectionMethod($route['class'], $route['method']));

            if (!str_contains($source, '$this->runMassActions(')) {
                $missing[] = $route['path'] . ' → ' . $route['class'] . '::' . $route['method'];
            }
        }

        self::assertSame([], $missing, "Mass POST routes must delegate to runMassActions():\n" . implode("\n", $missing));
    }

    public function testRunMassActionHandlersAreOnTheSpecWhitelist(): void
    {
        $calls = SourceScan::runMassActionCalls();
        $violations = [];

        self::assertNotSame([], $calls);

        $runMass = SourceScan::methodSource(
            new \ReflectionMethod(\Mt2Cms\Http\Controller\AdminController::class, 'runMassActions'),
        );
        self::assertStringContainsString('allowsMassAction($action)', $runMass);
        self::assertStringContainsString('!isset($handlers[$action])', $runMass);

        foreach ($calls as $call) {
            if ($call['handlers'] === []) {
                $violations[] = $call['class'] . '::' . $call['method'] . ' has no handler keys';
                continue;
            }

            $allowed = SourceScan::massActionIdsForSpec($call['class'], $call['spec']);
            $unknown = array_diff($call['handlers'], $allowed);

            if ($unknown !== []) {
                $violations[] = $call['class'] . '::' . $call['method']
                    . ' extra handlers: ' . implode(', ', $unknown)
                    . ' (spec allows ' . implode(', ', $allowed) . ')';
            }
        }

        self::assertSame([], $violations, "runMassActions handlers must be spec ids:\n" . implode("\n", $violations));
    }

    public function testAdminPostHandlersWriteAuditLog(): void
    {
        $missing = [];

        foreach (SourceScan::postRoutes() as $route) {
            if ($route['class'] === SetupController::class) {
                continue;
            }

            if (!str_starts_with($route['path'], '/admin/')) {
                continue;
            }

            $id = $route['class'] . '::' . $route['method'];

            if (in_array($id, self::AUDIT_SKIP, true)) {
                continue;
            }

            $source = SourceScan::reachableSource($route['class'], $route['method']);

            if (!str_contains($source, '->audit(')
                && !str_contains($source, '->auditChange(')
                && !str_contains($source, 'runMassActions(')
            ) {
                $missing[] = $route['path'] . ' → ' . $id;
            }
        }

        self::assertSame([], $missing, "Admin POSTs must audit (or use runMassActions):\n" . implode("\n", $missing));
    }

    public function testSensitivePostsAreRateLimited(): void
    {
        $missing = [];

        foreach (self::RATE_LIMITED_POSTS as $id) {
            [$class, $method] = explode('::', $id);
            $source = SourceScan::reachableSource($class, $method);

            if (!str_contains($source, 'tooManyAttempts(')) {
                $missing[] = $id;
            }
        }

        $routes = [];

        foreach (SourceScan::postRoutes() as $route) {
            $routes[$route['class'] . '::' . $route['method']] = $route['path'];
        }

        foreach (self::RATE_LIMITED_POSTS as $id) {
            if ($id === SetupController::class . '::submit') {
                continue;
            }

            self::assertArrayHasKey($id, $routes, $id . ' must stay registered as a POST route');
        }

        self::assertSame([], $missing, "Sensitive POSTs must call RateLimiter::tooManyAttempts():\n" . implode("\n", $missing));
    }
}
