<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class ShopsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game-data/shops', 'admin.shops')
            ->idField('vnum')
            ->orderBy([
                'vnum' => 's.vnum',
                'name' => 's.name',
                'npc_vnum' => 's.npc_vnum',
            ])
            ->columns([
                ['key' => 'vnum', 'label' => 'admin.shops.vnum', 'sort' => 'vnum', 'type' => 'muted'],
                ['key' => 'name', 'label' => 'admin.shops.name', 'sort' => 'name', 'type' => 'link', 'href' => '/admin/game-data/shops/{vnum}'],
                ['key' => 'npc_vnum', 'label' => 'admin.shops.npc', 'sort' => 'npc_vnum', 'type' => 'number'],
                ['key' => 'item_count', 'label' => 'admin.shops.items', 'type' => 'number'],
            ])
            ->massActions('/admin/game-data/shops/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.shops.confirm_mass_delete'],
            ]);
        }
}
