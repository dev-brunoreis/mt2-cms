<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class CashPackageRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/store/packages', 'admin.packages')
            ->defaultSort('sort_order')
            ->orderBy([
                'id' => 'id',
                'title' => 'title',
                'cash_amount' => 'cash_amount',
                'price_cents' => 'price_cents',
                'sort_order' => 'sort_order',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.packages.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'title', 'label' => 'admin.packages.title_field', 'sort' => 'title', 'type' => 'link', 'href' => '/admin/store/packages/{id}'],
                ['key' => 'cash_amount', 'label' => 'admin.packages.cash', 'sort' => 'cash_amount', 'type' => 'number'],
                ['key' => 'price_cents', 'label' => 'admin.packages.price', 'sort' => 'price_cents', 'type' => 'number'],
                ['key' => 'currency', 'label' => 'admin.packages.currency', 'type' => 'text'],
                ['key' => 'enabled', 'label' => 'admin.packages.enabled', 'type' => 'badge', 'badgeMap' => [
                    '1' => ['class' => 'admin-badge-ok', 'label' => 'admin.yes'],
                    '0' => ['class' => 'admin-badge-danger', 'label' => 'admin.no'],
                ]],
            ])
            ->massActions('/admin/store/packages/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.packages.confirm_mass_delete'],
            ]);
    }

    protected function database(): string
    {
        return 'cms';
    }

    public function countForGrid(GridQuery $query): int
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM cms_cash_packages');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        $params = [$query->perPage, $query->offset()];
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'sort_order ASC, id ASC');
        $rows = $this->db()->fetchAll(
            'SELECT id, title, cash_amount, price_cents, currency, sort_order, enabled
             FROM cms_cash_packages' . $order . ' LIMIT ? OFFSET ?',
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
    public function listEnabled(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, cash_amount, price_cents, currency
             FROM cms_cash_packages
             WHERE enabled = 1
             ORDER BY sort_order ASC, id ASC',
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, title, cash_amount, price_cents, currency, sort_order, enabled
             FROM cms_cash_packages WHERE id = ?',
            [$id],
        );
    }

    public function findEnabledById(int $id): ?array
    {
        $row = $this->findById($id);

        if ($row === null || !(int) ($row['enabled'] ?? 0)) {
            return null;
        }

        return $row;
    }

    public function create(array $data): int
    {
        $this->db()->execute(
            'INSERT INTO cms_cash_packages (title, cash_amount, price_cents, currency, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['title'],
                $data['cash_amount'],
                $data['price_cents'],
                $data['currency'],
                $data['sort_order'] ?? 0,
                ($data['enabled'] ?? true) ? 1 : 0,
            ],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->db()->execute(
            'UPDATE cms_cash_packages
             SET title = ?, cash_amount = ?, price_cents = ?, currency = ?, sort_order = ?, enabled = ?
             WHERE id = ?',
            [
                $data['title'],
                $data['cash_amount'],
                $data['price_cents'],
                $data['currency'],
                $data['sort_order'] ?? 0,
                ($data['enabled'] ?? true) ? 1 : 0,
                $id,
            ],
        );
    }

    public function delete(int $id): bool
    {
        return $this->db()->execute('DELETE FROM cms_cash_packages WHERE id = ?', [$id]) > 0;
    }
}
