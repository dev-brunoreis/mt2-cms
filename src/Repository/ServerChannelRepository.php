<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class ServerChannelRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEnabled(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, name, label, host, port, sort_order
             FROM cms_server_channels
             WHERE enabled = 1
             ORDER BY sort_order ASC, id ASC',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForAdmin(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, name, label, host, port, sort_order, enabled, created_at, updated_at
             FROM cms_server_channels
             ORDER BY sort_order ASC, id ASC',
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, name, label, host, port, sort_order, enabled
             FROM cms_server_channels WHERE id = ?',
            [$id],
        );
    }

    public function create(string $name, string $label, ?string $host, ?int $port, int $sortOrder, bool $enabled): int
    {
        $this->db()->execute(
            'INSERT INTO cms_server_channels (name, label, host, port, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $label, $host, $port, $sortOrder, $enabled ? 1 : 0],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, string $name, string $label, ?string $host, ?int $port, int $sortOrder, bool $enabled): void
    {
        $this->db()->execute(
            'UPDATE cms_server_channels
             SET name = ?, label = ?, host = ?, port = ?, sort_order = ?, enabled = ?
             WHERE id = ?',
            [$name, $label, $host, $port, $sortOrder, $enabled ? 1 : 0, $id],
        );
    }

    public function delete(int $id): bool
    {
        return $this->db()->execute('DELETE FROM cms_server_channels WHERE id = ?', [$id]) > 0;
    }
}
