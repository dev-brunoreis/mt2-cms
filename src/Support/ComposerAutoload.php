<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

/**
 * Loads vendor/autoload.php before the rest of the app. This file is required
 * directly from public/index.php and bin scripts — Composer is not available yet.
 */
final class ComposerAutoload
{
    public static function file(string $baseDir): string
    {
        return rtrim($baseDir, '/\\') . '/vendor/autoload.php';
    }

    public static function exists(string $baseDir): bool
    {
        return is_file(self::file($baseDir));
    }

    public static function load(string $baseDir): void
    {
        if (self::exists($baseDir)) {
            require_once self::file($baseDir);

            return;
        }

        self::fail();
    }

    public static function cliMessage(): string
    {
        return "Dependencies are not installed. Run: composer install\n";
    }

    /**
     * @return array<string, string>
     */
    public static function httpHeaders(): array
    {
        return [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Retry-After' => '60',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",
        ];
    }

    public static function html(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dependencies not installed — Mt2 CMS</title>
    <style>
        body { margin: 0; min-height: 100vh; background: #020617; color: #f1f5f9; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.5; }
        main { max-width: 32rem; margin: 0 auto; padding: 3rem 1rem; }
        .badge { margin: 0; font-size: 0.875rem; letter-spacing: 0.1em; text-transform: uppercase; color: #60a5fa; }
        h1 { margin: 0.5rem 0 0; font-size: 1.875rem; font-weight: 700; color: #fff; }
        p { margin: 0.75rem 0 0; color: #94a3b8; }
        pre { margin: 1rem 0 0; padding: 1rem; overflow: auto; background: #0f172a; border: 1px solid #1e293b; border-radius: 0.5rem; color: #e2e8f0; }
    </style>
</head>
<body>
    <main>
        <p class="badge">Local setup</p>
        <h1>Dependencies not installed</h1>
        <p>PHP packages are missing. From the project root, install them and reload this page.</p>
        <pre>composer install</pre>
    </main>
</body>
</html>
HTML;
    }

    /** @return never */
    public static function fail(): void
    {
        error_log('Autoload file not found. Run: composer install');

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, self::cliMessage());
            exit(1);
        }

        http_response_code(503);
        foreach (self::httpHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }
        echo self::html();
        exit(1);
    }
}
