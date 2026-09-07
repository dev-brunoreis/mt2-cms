<?php

declare(strict_types=1);

namespace Mt2Cms\I18n;

class Locales
{
    public const COOKIE = 'locale';

    public function __construct(
        private string $path,
    ) {
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function available(): array
    {
        $files = glob($this->path . '/*.json');

        if ($files === false) {
            return [];
        }

        $list = [];

        foreach ($files as $file) {
            $code = basename($file, '.json');

            if ($code === '' || !$this->isSupported($code)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($file), true);
            $name = is_array($data) ? (string) ($data['locale']['name'] ?? $code) : $code;

            $list[] = [
                'code' => $code,
                'name' => $name !== '' ? $name : $code,
            ];
        }

        usort($list, static function (array $a, array $b): int {
            if ($a['code'] === 'en') {
                return -1;
            }

            if ($b['code'] === 'en') {
                return 1;
            }

            return strcmp($a['code'], $b['code']);
        });

        return $list;
    }

    public function isSupported(string $locale): bool
    {
        if ($locale === '' || str_contains($locale, '/') || str_contains($locale, '\\') || str_contains($locale, '..')) {
            return false;
        }

        return is_file($this->path . '/' . $locale . '.json');
    }

    public function resolve(string $default): string
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;

        if (is_string($cookie) && $this->isSupported($cookie)) {
            return $cookie;
        }

        if ($this->isSupported($default)) {
            return $default;
        }

        return 'en';
    }

    public function safeRedirect(?string $target): string
    {
        if (!is_string($target) || $target === '' || $target[0] !== '/' || str_starts_with($target, '//')) {
            return '/';
        }

        if (str_contains($target, "\r") || str_contains($target, "\n")) {
            return '/';
        }

        return $target;
    }
}
