<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\EventsGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;
use Mt2Cms\Repository\Repository;

class EventRepository extends Repository implements ProvidesAdminGrid
{

    protected function database(): string
    {
        return 'cms';
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM cms_events' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, EventsGrid::definition()->sortMap(), 'starts_at DESC, id DESC');

        return $this->db()->fetchAll(
            'SELECT id, title, starts_at, ends_at, published, published_at, created_at, updated_at
             FROM cms_events' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, title, body, starts_at, ends_at, published, published_at, created_at, updated_at,
                    seo_title, seo_description, seo_og_image
             FROM cms_events WHERE id = ?',
            [$id],
        );
    }

    public function findPublishedById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, title, body, starts_at, ends_at, published, published_at, created_at, updated_at,
                    seo_title, seo_description, seo_og_image
             FROM cms_events WHERE id = ? AND published = 1',
            [$id],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublished(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->db()->fetchAll(
            'SELECT id, title, body, starts_at, ends_at, published_at, created_at
             FROM cms_events
             WHERE published = 1
             ORDER BY starts_at DESC, id DESC
             LIMIT ? OFFSET ?',
            [$perPage, $offset],
        );
    }

    public function countPublished(): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM cms_events WHERE published = 1',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function upcomingPublished(int $limit = 5): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, body, starts_at, ends_at, published_at, created_at
             FROM cms_events
             WHERE published = 1 AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY starts_at ASC, id ASC
             LIMIT ?',
            [max(1, $limit)],
        );
    }

    /**
     * @param array{
     *   title: string,
     *   body: string,
     *   starts_at: string,
     *   ends_at: ?string,
     *   published: bool,
     *   seo_title?: ?string,
     *   seo_description?: ?string,
     *   seo_og_image?: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $publishedAt = $data['published'] ? date('Y-m-d H:i:s') : null;

        $this->db()->execute(
            'INSERT INTO cms_events (title, body, starts_at, ends_at, published, published_at,
                seo_title, seo_description, seo_og_image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['title'],
                $data['body'],
                $data['starts_at'],
                $data['ends_at'],
                $data['published'] ? 1 : 0,
                $publishedAt,
                $data['seo_title'] ?? null,
                $data['seo_description'] ?? null,
                $data['seo_og_image'] ?? null,
            ],
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array{
     *   title: string,
     *   body: string,
     *   starts_at: string,
     *   ends_at: ?string,
     *   published: bool,
     *   seo_title?: ?string,
     *   seo_description?: ?string,
     *   seo_og_image?: ?string
     * } $data
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return false;
        }

        $publishedAt = $existing['published_at'];

        if ($data['published'] && ((int) ($existing['published'] ?? 0) !== 1 || $publishedAt === null)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if (!$data['published']) {
            $publishedAt = null;
        }

        return $this->db()->execute(
            'UPDATE cms_events
             SET title = ?, body = ?, starts_at = ?, ends_at = ?, published = ?, published_at = ?,
                 seo_title = ?, seo_description = ?, seo_og_image = ?
             WHERE id = ?',
            [
                $data['title'],
                $data['body'],
                $data['starts_at'],
                $data['ends_at'],
                $data['published'] ? 1 : 0,
                $publishedAt,
                array_key_exists('seo_title', $data) ? $data['seo_title'] : ($existing['seo_title'] ?? null),
                array_key_exists('seo_description', $data) ? $data['seo_description'] : ($existing['seo_description'] ?? null),
                array_key_exists('seo_og_image', $data) ? $data['seo_og_image'] : ($existing['seo_og_image'] ?? null),
                $id,
            ],
        ) >= 0;
    }

    /**
     * @return list<array{id: int, updated_at: mixed, published_at: mixed}>
     */
    public function listPublishedForSitemap(int $limit = 1000): array
    {
        return $this->db()->fetchAll(
            'SELECT id, updated_at, published_at
             FROM cms_events
             WHERE published = 1
             ORDER BY starts_at DESC, id DESC
             LIMIT ?',
            [max(1, $limit)],
        );
    }

    public function delete(int $id): bool
    {
        return $this->db()->execute('DELETE FROM cms_events WHERE id = ?', [$id]) > 0;
    }

    public function setPublished(int $id, bool $published): bool
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return false;
        }

        $wasPublished = (int) ($existing['published'] ?? 0) === 1;
        $publishedAt = $existing['published_at'];

        if ($published && (!$wasPublished || $publishedAt === null)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if (!$published) {
            $publishedAt = null;
        }

        return $this->db()->execute(
            'UPDATE cms_events SET published = ?, published_at = ? WHERE id = ?',
            [$published ? 1 : 0, $publishedAt, $id],
        ) >= 0;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        return GridSql::where($query, EventsGrid::definition()->filterSql());
    }
}
