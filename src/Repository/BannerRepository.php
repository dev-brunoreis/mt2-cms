<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\BannersGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class BannerRepository extends Repository implements ProvidesAdminGrid
{
    protected function database(): string
    {
        return 'cms';
    }

    public function countForGrid(GridQuery $query): int
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM cms_banners');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        $params = [$query->perPage, $query->offset()];
        $order = GridSql::orderBy($query, BannersGrid::definition()->sortMap(), 'sort_order ASC, id ASC');

        $rows = $this->db()->fetchAll(
            'SELECT id, title, alt, link_url, original_path, variants_json, sort_order, enabled
             FROM cms_banners' . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEnabled(): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT id, title, alt, link_url, original_path, variants_json, sort_order, enabled
             FROM cms_banners
             WHERE enabled = 1
             ORDER BY sort_order ASC, id ASC',
        );

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    public function countAll(): int
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM cms_banners');
    }

    public function findById(int $id): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, title, alt, link_url, original_path, variants_json, sort_order, enabled,
                    created_at, updated_at
             FROM cms_banners WHERE id = ?',
            [$id],
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $this->db()->execute(
            'INSERT INTO cms_banners (title, alt, link_url, original_path, variants_json, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['title'],
                $data['alt'],
                $data['link_url'] ?? null,
                $data['original_path'],
                $this->encodeVariants($data['variants'] ?? []),
                $data['sort_order'] ?? 0,
                ($data['enabled'] ?? true) ? 1 : 0,
            ],
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->db()->execute(
            'UPDATE cms_banners
             SET title = ?, alt = ?, link_url = ?, original_path = ?, variants_json = ?,
                 sort_order = ?, enabled = ?
             WHERE id = ?',
            [
                $data['title'],
                $data['alt'],
                $data['link_url'] ?? null,
                $data['original_path'],
                $this->encodeVariants($data['variants'] ?? []),
                $data['sort_order'] ?? 0,
                ($data['enabled'] ?? true) ? 1 : 0,
                $id,
            ],
        );
    }

    public function setEnabled(int $id, bool $enabled): bool
    {
        return $this->db()->execute(
            'UPDATE cms_banners SET enabled = ? WHERE id = ?',
            [$enabled ? 1 : 0, $id],
        ) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db()->execute('DELETE FROM cms_banners WHERE id = ?', [$id]) > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $variants = $row['variants_json'] ?? [];

        if (is_string($variants)) {
            $decoded = json_decode($variants, true);
            $variants = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($variants)) {
            $variants = [];
        }

        $row['variants'] = $variants;
        $row['variants_json'] = $variants;
        $row['enabled'] = (string) ((int) ($row['enabled'] ?? 0));
        $row['preview_url'] = $this->previewUrl($variants, (string) ($row['original_path'] ?? ''));

        return $row;
    }

    /**
     * @param array<string, mixed> $variants
     */
    private function encodeVariants(array $variants): string
    {
        return json_encode($variants, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $variants
     */
    private function previewUrl(array $variants, string $originalPath): string
    {
        $fallback = $variants['fallback'] ?? null;

        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        $jpg960 = $variants['960']['jpg'] ?? null;

        if (is_string($jpg960) && $jpg960 !== '') {
            return $jpg960;
        }

        return $originalPath;
    }
}
