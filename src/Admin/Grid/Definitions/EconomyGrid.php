<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class EconomyGrid
{
    public static function definition(): GridDefinition
    {
        return GridDefinition::create('/admin/game/economy', 'admin.economy')
            ->defaultSort('units')
            ->idField('vnum')
            ->orderBy([
                'vnum' => 'c.vnum',
                'units' => 'c.units',
                'stacks' => 'c.stacks',
                'median_price' => 'm.median_price',
                'watched' => 'watched',
            ])
            ->columns([
                ['key' => 'vnum', 'label' => 'admin.economy.vnum', 'sort' => 'vnum', 'type' => 'muted'],
                ['key' => 'item_name', 'label' => 'admin.economy.item', 'type' => 'link', 'href' => '/admin/game/economy/{vnum}', 'filter' => false],
                ['key' => 'units', 'label' => 'admin.economy.units', 'sort' => 'units', 'type' => 'number'],
                ['key' => 'stacks', 'label' => 'admin.economy.stacks', 'sort' => 'stacks', 'type' => 'number'],
                ['key' => 'delta_1d_pct', 'label' => 'admin.economy.delta_1d', 'type' => 'text', 'filter' => false],
                ['key' => 'delta_7d_pct', 'label' => 'admin.economy.delta_7d', 'type' => 'text', 'filter' => false],
                ['key' => 'median_price', 'label' => 'admin.economy.median_price', 'sort' => 'median_price', 'type' => 'number'],
                ['key' => 'price_delta_7d_pct', 'label' => 'admin.economy.price_delta', 'type' => 'text', 'filter' => false],
                ['key' => 'watched', 'label' => 'admin.economy.watched', 'sort' => 'watched', 'type' => 'badge', 'badgeMap' => [
                    '1' => ['class' => 'admin-badge-ok', 'label' => 'admin.economy.watched_yes'],
                    '0' => ['class' => 'admin-badge-muted', 'label' => 'admin.economy.watched_no'],
                ]],
            ])
            ->filters([
                ['key' => 'movers', 'label' => 'admin.economy.movers', 'type' => 'select', 'options' => [
                    '' => 'admin.economy.movers_all',
                    '1' => 'admin.economy.movers_only',
                ]],
            ])
            ->massActions('/admin/game/economy/mass', [
                ['id' => 'watch', 'label' => 'admin.economy.watch', 'confirm' => 'admin.economy.confirm_mass_watch'],
                ['id' => 'unwatch', 'label' => 'admin.economy.unwatch', 'confirm' => 'admin.economy.confirm_mass_unwatch'],
            ]);
    }
}
