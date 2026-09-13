<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class ShopItemsGrid
{
    public static function definition(int $shopVnum): GridDefinition
    {
        return GridDefinition::create('/admin/game-data/shops/' . $shopVnum . '?tab=items', 'admin.shops.items_grid')
            ->searchable(false)
            ->idField('row_key')
            ->defaultSort('item_vnum', 'asc')
            ->orderBy([
                'item_vnum' => 'item_vnum',
                'count' => 'count',
            ])
            ->columns([
                ['key' => 'item_vnum', 'label' => 'admin.shops.item_vnum', 'sort' => 'item_vnum', 'type' => 'link', 'href' => '/admin/game-data/items/{item_vnum}'],
                ['key' => 'item_label', 'label' => 'admin.shops.item', 'type' => 'icon_link', 'icon' => 'item', 'href' => '/admin/game-data/items/{item_vnum}'],
                ['key' => 'count', 'label' => 'admin.shops.item_count', 'sort' => 'count', 'type' => 'number'],
                [
                    'key' => '_actions',
                    'type' => 'actions',
                    'actions' => [
                        [
                            'type' => 'form',
                            'href' => '/admin/game-data/shops/' . $shopVnum . '/items/delete/{item_vnum}/{count}',
                            'label' => 'admin.shops.remove_item',
                            'danger' => true,
                            'confirm' => 'admin.shops.confirm_remove_item',
                        ],
                    ],
                ],
            ]);
    }
}
