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
     * Load layout JSON across the theme chain, resolve same-theme `extends`, then return.
     *
     * @return array<string, mixed>
     */
    public function resolveLayout(string $layoutName): array
    {
        return $this->resolveExtendsChain($layoutName, []);
    }

    /**
     * @param array<string, true> $seen
     * @return array<string, mixed>
     */
    private function resolveExtendsChain(string $layoutName, array $seen): array
    {
        if (isset($seen[$layoutName])) {
            throw new \RuntimeException('Circular layout extends: ' . $layoutName);
        }

        $seen[$layoutName] = true;
        $merged = $this->loadThemeChainLayout($layoutName);
        $extends = $merged['extends'] ?? null;
        unset($merged['extends']);

        if (!is_string($extends) || $extends === '') {
            return $merged;
        }

        $base = $this->resolveExtendsChain($extends, $seen);

        return LayoutMerger::mergeById($base, $merged);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadThemeChainLayout(string $layoutName): array
    {
        $chain = $this->chain();
        $merged = null;

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

        if (!ThemeAssetFile::isSafeRelativePath($relativePath)) {
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
