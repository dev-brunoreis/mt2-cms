<?php

declare(strict_types=1);

namespace Mt2Cms\I18n;

class Translator
{
    /** @var array<string, mixed> */
    private array $messages = [];

    public function __construct(
        private string $path,
        private string $locale = 'en',
        private string $fallback = 'en',
    ) {
        $this->locale = $this->normalize($locale);
        $this->fallback = $this->normalize($fallback);
        $this->messages = $this->load($this->fallback);

        if ($this->locale !== $this->fallback) {
            $this->messages = $this->merge($this->messages, $this->load($this->locale));
        }
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function htmlLang(): string
    {
        return $this->locale;
    }

    public function has(string $key): bool
    {
        return $this->lookup($key) !== null;
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function get(string $key, array $replace = []): string
    {
        $value = $this->lookup($key);

        if (is_array($value)) {
            $n = (int) ($replace['n'] ?? $replace['count'] ?? 0);
            $value = $n === 1
                ? ($value['one'] ?? $value['other'] ?? null)
                : ($value['other'] ?? $value['one'] ?? null);
        }

        if (!is_string($value) || $value === '') {
            return $key;
        }

        foreach ($replace as $name => $replacement) {
            $value = str_replace('{' . $name . '}', (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $locale): array
    {
        $dir = $this->path . '/' . $locale;

        if (is_dir($dir)) {
            return $this->loadDirectory($dir);
        }

        $file = $this->path . '/' . $locale . '.json';

        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid locale JSON: ' . $locale);
        }

        return $data;
    }

    /**
     * Merge lang/{locale}/*.json namespace files (filename stem = top-level key unless locale.json).
     *
     * @return array<string, mixed>
     */
    private function loadDirectory(string $dir): array
    {
        $files = glob($dir . '/*.json');

        if ($files === false || $files === []) {
            return [];
        }

        sort($files);
        $merged = [];

        foreach ($files as $file) {
            $stem = basename($file, '.json');
            $data = json_decode((string) file_get_contents($file), true);

            if (!is_array($data)) {
                throw new \RuntimeException('Invalid locale JSON: ' . $file);
            }

            if ($stem === 'locale') {
                $merged = $this->merge($merged, ['locale' => $data['locale'] ?? $data]);
                continue;
            }

            // File may be a full tree slice or a single namespace object.
            if (array_key_exists($stem, $data) && count($data) === 1) {
                $merged = $this->merge($merged, $data);
            } else {
                $merged = $this->merge($merged, [$stem => $data]);
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !$this->isLeafMap($value)) {
                $base[$key] = $this->merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param array<mixed> $value
     */
    private function isLeafMap(array $value): bool
    {
        return isset($value['one']) || isset($value['other']);
    }

    private function lookup(string $key): mixed
    {
        $node = $this->messages;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * Locale codes are folder/file stems under lang/. Reject path junk.
     *
     * @psalm-taint-escape file
     */
    private function normalize(string $locale): string
    {
        $locale = trim($locale);

        if ($locale === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $locale)) {
            return 'en';
        }

        return $locale;
    }
}
