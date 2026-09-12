<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\DownloadsGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class DownloadRepository extends Repository implements ProvidesAdminGrid
{

    protected function database(): string
    {
        return 'cms';
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = GridSql::where($query, DownloadsGrid::definition()->filterSql());

        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM cms_downloads' . $where, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = GridSql::where($query, DownloadsGrid::definition()->filterSql());
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, DownloadsGrid::definition()->sortMap(), 'sort_order ASC, id ASC');

        $rows = $this->db()->fetchAll(
                'SELECT id, title, category, sort_order, enabled
             FROM cms_downloads' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        foreach ($rows as &$row) {
            $row['enabled'] = (string) ((int) ($row['enabled'] ?? 0));
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublic(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, category, description, external_url, stored_name, original_name
             FROM cms_downloads
             WHERE enabled = 1
             ORDER BY sort_order ASC, id ASC',
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, title, category, description, external_url, stored_name, original_name, sort_order, enabled
             FROM cms_downloads WHERE id = ?',
            [$id],
        );
    }

    public function create(array $data): int
    {
        $this->db()->execute(
            'INSERT INTO cms_downloads (title, category, description, external_url, stored_name, original_name, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['title'],
                $data['category'],
                $data['description'] ?? null,
                $data['external_url'] ?? null,
                $data['stored_name'] ?? null,
                $data['original_name'] ?? null,
                $data['sort_order'] ?? 0,
                ($data['enabled'] ?? true) ? 1 : 0,
            ],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->db()->execute(
            'UPDATE cms_downloads
             SET title = ?, category = ?, description = ?, external_url = ?,
                 stored_name = ?, original_name = ?, sort_order = ?, enabled = ?
             WHERE id = ?',
            [
                $data['title'],
                $data['category'],
                $data['description'] ?? null,
                $data['external_url'] ?? null,
                $data['stored_name'] ?? null,
                $data['original_name'] ?? null,
                $data['sort_order'] ?? 0,
                ($data['enabled'] ?? true) ? 1 : 0,
                $id,
            ],
        );
    }

    public function delete(int $id): bool
    {
        return $this->db()->execute('DELETE FROM cms_downloads WHERE id = ?', [$id]) > 0;
    }
}
