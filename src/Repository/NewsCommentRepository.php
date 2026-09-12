<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\NewsCommentsGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class NewsCommentRepository extends Repository implements ProvidesAdminGrid
{

    protected function database(): string
    {
        return 'cms';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVisibleForNews(int $newsId, ?int $viewerAccountId): array
    {
        if ($viewerAccountId !== null) {
            return $this->db()->fetchAll(
                'SELECT id, news_id, account_id, account_login, body, status, created_at
                 FROM news_comments
                 WHERE news_id = ?
                   AND (status = ? OR (account_id = ? AND status = ?))
                 ORDER BY created_at ASC, id ASC',
                [$newsId, 'approved', $viewerAccountId, 'pending'],
            );
        }

        return $this->db()->fetchAll(
            'SELECT id, news_id, account_id, account_login, body, status, created_at
             FROM news_comments
             WHERE news_id = ? AND status = ?
             ORDER BY created_at ASC, id ASC',
            [$newsId, 'approved'],
        );
    }

    public function countPending(): int
    {
        return $this->countForGrid(new GridQuery(null, 1, 20, 'created_at', 'asc', []));
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*)
             FROM news_comments c
             INNER JOIN news n ON n.id = c.news_id' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPending(int $page, int $perPage): array
    {
        return $this->listForGrid(new GridQuery(null, $page, $perPage, 'created_at', 'asc', []));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, NewsCommentsGrid::definition()->sortMap(), 'c.created_at ASC, c.id ASC');

        return $this->db()->fetchAll(
            'SELECT c.id, c.news_id, c.account_id, c.account_login, c.body, c.status, c.created_at,
                    n.title AS news_title
             FROM news_comments c
             INNER JOIN news n ON n.id = c.news_id' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    public function create(int $newsId, int $accountId, string $accountLogin, string $body, string $status): int
    {
        $this->db()->execute(
            'INSERT INTO news_comments (news_id, account_id, account_login, body, status)
             VALUES (?, ?, ?, ?, ?)',
            [$newsId, $accountId, $accountLogin, $body, $status],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, news_id, account_id, account_login, body, status, created_at
             FROM news_comments
             WHERE id = ?
             LIMIT 1',
            [$id],
        );
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->db()->execute(
            'UPDATE news_comments SET status = ? WHERE id = ?',
            [$status, $id],
        ) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db()->execute('DELETE FROM news_comments WHERE id = ?', [$id]) > 0;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        return GridSql::append(
            GridSql::where($query, NewsCommentsGrid::definition()->filterSql()),
            'c.status = ?',
            ['pending'],
        );
    }
}
