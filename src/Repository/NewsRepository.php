<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class NewsRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/news', 'admin.news')
            ->orderBy([
                'id' => 'id',
                'title' => 'title',
                'author_login' => 'author_login',
                'status' => 'status',
                'published_at' => 'published_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.news.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'title', 'label' => 'admin.news.post_title', 'sort' => 'title', 'type' => 'link', 'href' => '/admin/news/{id}'],
                ['key' => 'author_login', 'label' => 'admin.news.author', 'sort' => 'author_login', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.news.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'published' => ['class' => 'admin-badge-ok', 'label' => 'admin.news.status_published'],
                    'draft' => ['class' => 'admin-badge-muted', 'label' => 'admin.news.status_draft'],
                ]],
                ['key' => 'published_at', 'label' => 'admin.news.published', 'sort' => 'published_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.news.status', 'type' => 'select', 'options' => [
                    'draft' => 'admin.news.status_draft',
                    'published' => 'admin.news.status_published',
                ]],
            ])
            ->massActions('/admin/news/mass', [
                ['id' => 'publish', 'label' => 'admin.grid.publish', 'confirm' => 'admin.news.confirm_mass_publish'],
                ['id' => 'draft', 'label' => 'admin.grid.draft', 'confirm' => 'admin.news.confirm_mass_draft'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.news.confirm_mass_delete'],
            ]);
    }

    protected function database(): string
    {
        return 'cms';
    }

    public function countPublished(?string $query = null): int
    {
        [$where, $params] = $this->publishedFilter($query);

        return (int) $this->db()->fetchColumn(
            "SELECT COUNT(*) FROM news WHERE {$where}",
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublished(int $page, int $perPage, ?string $query = null): array
    {
        [$where, $params] = $this->publishedFilter($query);
        $offset = max(0, ($page - 1) * $perPage);
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db()->fetchAll(
            "SELECT id, title, body, cover_image, author_login, views, published_at, created_at
             FROM news
             WHERE {$where}
             ORDER BY published_at DESC, id DESC
             LIMIT ? OFFSET ?",
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latestPublished(int $limit = 5): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, body, cover_image, author_login, views, published_at, created_at
             FROM news
             WHERE status = ?
             ORDER BY published_at DESC, id DESC
             LIMIT ?',
            ['published', $limit],
        );
    }

    public function findPublishedById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, title, body, cover_image, author_admin_id, author_login, status,
                    comments_enabled, views, published_at, created_at, updated_at
             FROM news
             WHERE id = ? AND status = ?
             LIMIT 1',
            [$id, 'published'],
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, title, body, cover_image, author_admin_id, author_login, status,
                    comments_enabled, views, published_at, created_at, updated_at
             FROM news
             WHERE id = ?
             LIMIT 1',
            [$id],
        );
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM news' . $where,
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
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'updated_at DESC, id DESC');

        return $this->db()->fetchAll(
            'SELECT id, title, cover_image, author_login, status, comments_enabled, views,
                    published_at, created_at, updated_at
             FROM news' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    /**
     * @param array{
     *   title: string,
     *   body: string,
     *   cover_image: ?string,
     *   author_admin_id: int,
     *   author_login: string,
     *   status: string,
     *   comments_enabled: bool
     * } $data
     */
    public function create(array $data): int
    {
        $publishedAt = $data['status'] === 'published' ? date('Y-m-d H:i:s') : null;

        $this->db()->execute(
            'INSERT INTO news (
                title, body, cover_image, author_admin_id, author_login,
                status, comments_enabled, published_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['title'],
                $data['body'],
                $data['cover_image'],
                $data['author_admin_id'],
                $data['author_login'],
                $data['status'],
                $data['comments_enabled'] ? 1 : 0,
                $publishedAt,
            ],
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array{
     *   title: string,
     *   body: string,
     *   cover_image: ?string,
     *   status: string,
     *   comments_enabled: bool
     * } $data
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return false;
        }

        $publishedAt = $existing['published_at'];

        if ($data['status'] === 'published' && ($existing['status'] !== 'published' || $publishedAt === null)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($data['status'] === 'draft') {
            $publishedAt = $existing['published_at'];
        }

        return $this->db()->execute(
            'UPDATE news
             SET title = ?, body = ?, cover_image = ?, status = ?, comments_enabled = ?, published_at = ?
             WHERE id = ?',
            [
                $data['title'],
                $data['body'],
                $data['cover_image'],
                $data['status'],
                $data['comments_enabled'] ? 1 : 0,
                $publishedAt,
                $id,
            ],
        ) >= 0;
    }

    public function delete(int $id): bool
    {
        return $this->db()->execute('DELETE FROM news WHERE id = ?', [$id]) > 0;
    }

    public function incrementViews(int $id): void
    {
        $this->db()->execute(
            'UPDATE news SET views = views + 1 WHERE id = ?',
            [$id],
        );
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function publishedFilter(?string $query): array
    {
        $where = ['status = ?'];
        $params = ['published'];

        if ($query !== null && $query !== '') {
            $where[] = 'title LIKE ?';
            $params[] = '%' . $query . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $clauses = [];
        $params = [];

        $status = $query->filter('status');

        if ($status !== '' && in_array($status, ['draft', 'published'], true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        if ($query->q !== null && $query->q !== '') {
            $clauses[] = '(title LIKE ? OR author_login LIKE ?)';
            $params[] = '%' . $query->q . '%';
            $params[] = '%' . $query->q . '%';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
