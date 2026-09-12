<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class ItemShopProductsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/store/products', 'admin.item_shop.products')
            ->orderBy([
                'id' => 'p.id',
                'vnum' => 'p.vnum',
                'category_name' => 'c.name',
                'count' => 'p.count',
                'price' => 'p.price',
                'enabled' => 'p.enabled',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.item_shop.products.id', 'sort' => 'id', 'type' => 'link', 'href' => '/admin/store/products/{id}'],
                ['key' => 'vnum', 'label' => 'admin.item_shop.products.vnum', 'sort' => 'vnum', 'type' => 'number'],
                ['key' => 'item_name', 'label' => 'admin.item_shop.products.item', 'type' => 'text'],
                ['key' => 'category_name', 'label' => 'admin.item_shop.products.category', 'sort' => 'category_name', 'type' => 'text'],
                ['key' => 'count', 'label' => 'admin.item_shop.products.count_label', 'sort' => 'count', 'type' => 'number'],
                ['key' => 'price', 'label' => 'admin.item_shop.products.price', 'sort' => 'price', 'type' => 'number'],
                ['key' => 'enabled', 'label' => 'admin.item_shop.products.enabled', 'sort' => 'enabled', 'type' => 'badge', 'badgeMap' => [
                    '1' => ['class' => 'admin-badge-ok', 'label' => 'admin.item_shop.enabled_yes'],
                    '0' => ['class' => 'admin-badge-muted', 'label' => 'admin.item_shop.enabled_no'],
                ]],
            ])
            ->filters([
                ['key' => 'category_id', 'label' => 'admin.item_shop.products.category', 'type' => 'select', 'options' => []],
            ])
            ->massActions('/admin/store/products/mass', [
                ['id' => 'enable', 'label' => 'admin.grid.enable'],
                ['id' => 'disable', 'label' => 'admin.grid.disable'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.item_shop.products.confirm_mass_delete'],
            ]);
        }
}
