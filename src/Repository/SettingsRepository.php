<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class SettingsRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->db()->fetch(
            'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1',
            [$key],
        );

        if ($row === null) {
            return $default;
        }

        return (string) $row['setting_value'];
    }

    public function set(string $key, string $value): void
    {
        $this->db()->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value],
        );
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>
     */
    public function getJson(string $key, array $default = []): array
    {
        $raw = $this->get($key);

        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * @param array<int|string, mixed> $value
     */
    public function setJson(string $key, array $value): void
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        $this->set($key, $encoded);
    }
}
