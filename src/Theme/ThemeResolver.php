<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

class ThemeResolver
{
    public function __construct(
        private string $themesPath,
        private string $activeTheme,
    ) {
    }

    /**
     * @return list<string> Theme names from active to root parent
     */
    public function chain(): array
    {
        $chain = [];
        $current = $this->activeTheme;
        $seen = [];

        while ($current !== null && $current !== '') {
            if (isset($seen[$current])) {
                throw new \RuntimeException('Circular theme parent: ' . $current);
            }

            $seen[$current] = true;
            $path = $this->themePath($current);

            if (!is_dir($path)) {
                throw new \RuntimeException('Theme not found: ' . $current);
            }

            $chain[] = $current;
            $meta = $this->meta($current);
            $parent = $meta['parent'] ?? null;
            $current = is_string($parent) && $parent !== '' ? $parent : null;
        }

        return $chain;
    }

    public function themePath(string $name): string
    {
        return $this->themesPath . '/' . $name;
    }

    /**
     * @return array{name?: string, parent?: string|null}
     */
    public function meta(string $name): array
    {
        $file = $this->themePath($name) . '/theme.json';

        if (!is_file($file)) {
            return ['name' => $name, 'parent' => null];
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid theme.json for theme: ' . $name);
        }

        return $data;
    }

    /**
     * Template search paths: active first, then parents.
     *
     * @return list<string>
     */
    public function templatePaths(): array
    {
        $paths = [];

        foreach ($this->chain() as $theme) {
            $templates = $this->themePath($theme) . '/templates';

            if (is_dir($templates)) {
                $paths[] = $templates;
            }
        }

        return $paths;
    }

    /**
     * Load layout JSON from the first theme in the chain that has it,
     * then deep-merge overlays from child themes.
     *
     * @return array<string, mixed>
     */
    public function resolveLayout(string $layoutName): array
    {
        $chain = $this->chain();
        $merged = null;

        // Walk from root parent to active so child overrides win
        foreach (array_reverse($chain) as $theme) {
            $file = $this->themePath($theme) . '/layouts/' . $layoutName . '.json';

            if (!is_file($file)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($file), true);

            if (!is_array($data)) {
                throw new \RuntimeException("Invalid layout JSON: {$theme}/{$layoutName}");
            }

            $merged = $merged === null
                ? $data
                : LayoutMerger::mergeById($merged, $data);
        }

        if ($merged === null) {
            throw new \RuntimeException('Layout not found: ' . $layoutName);
        }

        return $merged;
    }

    /**
     * Resolve a theme asset path (relative to assets/) through the active theme chain.
     * Returns a public URL under /theme-assets/{theme}/… or empty string if missing.
     */
    public function resolveAsset(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === ''
            || str_contains($relativePath, '..')
            || !preg_match('#^(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:css|woff2|jpe?g|webp|png|svg|js)$#', $relativePath)
        ) {
            return '';
        }

        foreach ($this->chain() as $theme) {
            $file = $this->themePath($theme) . '/assets/' . $relativePath;

            if (is_file($file)) {
                $url = '/theme-assets/' . rawurlencode($theme) . '/' . implode('/', array_map('rawurlencode', explode('/', $relativePath)));
                $mtime = filemtime($file);

                if ($mtime !== false) {
                    $url .= '?v=' . $mtime;
                }

                return $url;
            }
        }

        return '';
    }
}
