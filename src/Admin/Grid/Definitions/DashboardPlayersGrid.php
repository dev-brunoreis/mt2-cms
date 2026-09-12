<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class DashboardPlayersGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin', 'admin.dashboard')
            ->searchable(false)
            ->defaultSort('last_play')
            ->orderBy([
                'id' => 'p.id',
                'name' => 'p.name',
                'job' => 'p.job',
                'level' => 'p.level',
                'last_play' => 'p.last_play',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.characters.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'name', 'label' => 'admin.characters.name', 'sort' => 'name', 'type' => 'icon_link', 'icon' => 'face', 'href' => '/admin/game/characters/{id}'],
                ['key' => 'account_login', 'label' => 'admin.characters.account', 'type' => 'text'],
                ['key' => 'job', 'label' => 'admin.characters.job', 'sort' => 'job', 'type' => 'job'],
                ['key' => 'level', 'label' => 'admin.characters.level', 'sort' => 'level', 'type' => 'number'],
                ['key' => 'last_play', 'label' => 'admin.characters.last_play', 'sort' => 'last_play', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'range', 'label' => 'admin.dashboard.range_label', 'type' => 'select', 'options' => [
                    '5m' => 'admin.dashboard.range.5m',
                    '1h' => 'admin.dashboard.range.1h',
                    '12h' => 'admin.dashboard.range.12h',
                    '24h' => 'admin.dashboard.range.24h',
                    '7d' => 'admin.dashboard.range.7d',
                ]],
            ]);
        }
}
