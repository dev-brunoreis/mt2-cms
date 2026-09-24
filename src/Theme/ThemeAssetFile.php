<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

final class ThemeAssetFile
{
    private const RELATIVE_PATTERN = '#^(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:css|woff2|jpe?g|webp|png|svg|js)$#';
    private const THEME_PATTERN = '/^[A-Za-z0-9_-]+$/';
    private const URI_PATTERN = '#^/theme-assets/([A-Za-z0-9_-]+)/((?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:css|woff2|jpe?g|webp|png|svg|js))$#';

    /**
     * @return array{theme: string, asset: string}|null
     */
    public static function parseUri(string $uri): ?array
    {
        $path = urldecode(parse_url($uri, PHP_URL_PATH) ?: '');

        if ($path === '' || !preg_match(self::URI_PATTERN, $path, $matches)) {
            return null;
        }

        return [
            'theme' => $matches[1],
            'asset' => $matches[2],
        ];
    }

    public static function isSafeRelativePath(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');

        return $relativePath !== ''
            && !str_contains($relativePath, '..')
            && (bool) preg_match(self::RELATIVE_PATTERN, $relativePath);
    }

    public static function absolutePath(string $themesRoot, string $theme, string $relativePath): ?string
    {
        if (!preg_match(self::THEME_PATTERN, $theme) || !self::isSafeRelativePath($relativePath)) {
            return null;
        }

        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');
        $assetsRoot = $themesRoot . '/' . $theme . '/assets';
        $candidate = $assetsRoot . '/' . $relativePath;
        $base = realpath($assetsRoot);
        $file = realpath($candidate);

        if ($base === false || $file === false || !is_file($file)) {
            return null;
        }

        $prefix = $base . DIRECTORY_SEPARATOR;

        if (!str_starts_with($file, $prefix)) {
            return null;
        }

        return $file;
    }

    public static function mimeType(string $relativePath): string
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'woff2' => 'font/woff2',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
