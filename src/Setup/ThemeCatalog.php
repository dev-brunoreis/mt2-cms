<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

class ThemeCatalog
{
    public function __construct(private string $themesPath)
    {
    }

    /**
     * Themes selectable for the public site (excludes admin/internal themes).
     *
     * @return list<string>
     */
    public function available(): array
    {
        $themes = [];

        foreach ($this->all() as $name) {
            if ($this->isPublic($name)) {
                $themes[] = $name;
            }
        }

        return $themes;
    }

    /**
     * @return list<string>
     */
    private function all(): array
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

    public function isPublic(string $theme): bool
    {
        if (!$this->isValid($theme)) {
            return false;
        }

        $meta = $this->meta($theme);

        return ($meta['public'] ?? true) !== false;
    }

    /**
     * @return array{name?: string, parent?: string|null, public?: bool}
     */
    private function meta(string $name): array
    {
        $file = $this->themesPath . '/' . $name . '/theme.json';
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
