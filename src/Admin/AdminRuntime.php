<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

use Mt2Cms\Service\SettingsService;

/**
 * Runtime bindings for admin controllers (avoids threading SettingsService through every constructor).
 */
final class AdminRuntime
{
    private static ?SettingsService $settings = null;

    public static function bind(SettingsService $settings): void
    {
        self::$settings = $settings;
    }

    public static function settings(): SettingsService
    {
        if (self::$settings === null) {
            throw new \RuntimeException('AdminRuntime is not bound.');
        }

        return self::$settings;
    }
}
