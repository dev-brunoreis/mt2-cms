<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

final class CmsVersion
{
    private const FALLBACK = 'dev';

    private const MAX_LENGTH = 64;

    /** Semver-ish: 1.0.0, 1.0.0-beta.1, v-stripped tags already. */
    private const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9.+-]*$/';

    public static function read(?string $baseDir = null): string
    {
        $dir = $baseDir ?? (defined('BASE_DIR') ? (string) BASE_DIR : '');
        if ($dir === '') {
            return self::FALLBACK;
        }

        $path = $dir . '/VERSION';
        if (!is_file($path) || !is_readable($path)) {
            return self::FALLBACK;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return self::FALLBACK;
        }

        $version = trim($raw);
        if ($version === '' || strlen($version) > self::MAX_LENGTH) {
            return self::FALLBACK;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $version) === 1) {
            return self::FALLBACK;
        }

        if (preg_match(self::PATTERN, $version) !== 1) {
            return self::FALLBACK;
        }

        return $version;
    }
}
