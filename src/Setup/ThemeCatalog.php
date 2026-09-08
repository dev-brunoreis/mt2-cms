<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

class ThemeCatalog
{
    public function __construct(private string $themesPath)
    {
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        $dirs = glob($this->themesPath . '/*', GLOB_ONLYDIR);

        if ($dirs === false) {
            return [];
        }

        $themes = [];

        foreach ($dirs as $dir) {
            $name = basename($dir);

            if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
                continue;
            }

            if (!is_file($dir . '/theme.json')) {
                continue;
            }

            $themes[] = $name;
        }

        sort($themes);

        return $themes;
    }

    public function isValid(string $theme): bool
    {
        if ($theme === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $theme)) {
            return false;
        }

        return is_dir($this->themesPath . '/' . $theme)
            && is_file($this->themesPath . '/' . $theme . '/theme.json');
    }
}
