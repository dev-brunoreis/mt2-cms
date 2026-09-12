<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class ItemShopOrdersGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/store/orders', 'admin.item_shop.orders')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'id',
                'account_login' => 'account_login',
                'count' => 'count',
                'price' => 'price',
                'status' => 'status',
                'created_at' => 'created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.item_shop.orders.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'account_login', 'label' => 'admin.item_shop.orders.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'item_name', 'label' => 'admin.item_shop.orders.item', 'type' => 'text', 'filter' => false],
                ['key' => 'count', 'label' => 'admin.item_shop.orders.count_label', 'sort' => 'count', 'type' => 'number'],
                ['key' => 'price', 'label' => 'admin.item_shop.orders.price', 'sort' => 'price', 'type' => 'number'],
                ['key' => 'status', 'label' => 'admin.item_shop.orders.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'pending' => ['class' => 'admin-badge-warn', 'label' => 'admin.item_shop.orders.status_pending'],
                    'completed' => ['class' => 'admin-badge-ok', 'label' => 'admin.item_shop.orders.status_completed'],
                    'failed' => ['class' => 'admin-badge-danger', 'label' => 'admin.item_shop.orders.status_failed'],
                ]],
                ['key' => 'created_at', 'label' => 'admin.item_shop.orders.created', 'sort' => 'created_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.item_shop.orders.status', 'type' => 'select', 'options' => [
                    'pending' => 'admin.item_shop.orders.status_pending',
                    'completed' => 'admin.item_shop.orders.status_completed',
                    'failed' => 'admin.item_shop.orders.status_failed',
                ]],
            ]);
        }
}
