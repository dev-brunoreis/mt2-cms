<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class CashPackagesGrid
{
    public static function definition(): GridDefinition
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
}
