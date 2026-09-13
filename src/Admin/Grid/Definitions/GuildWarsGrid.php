<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class GuildWarsGrid
{
    public static function definition(int $guildId): GridDefinition
    {
        return GridDefinition::create('/admin/game/guilds/' . $guildId . '?tab=wars', 'admin.guilds.wars_grid')
            ->searchable(false)
            ->defaultSort('time', 'desc')
            ->orderBy([
                'id' => 'id',
                'time' => 'time',
                'type' => 'type',
                'warprice' => 'warprice',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.guilds.war_id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'opponent_name', 'label' => 'admin.guilds.war_opponent', 'type' => 'link', 'href' => '/admin/game/guilds/{opponent_id}'],
                ['key' => 'time', 'label' => 'admin.guilds.war_time', 'sort' => 'time', 'type' => 'date'],
                ['key' => 'type', 'label' => 'admin.guilds.war_type', 'sort' => 'type', 'type' => 'text'],
                ['key' => 'warprice', 'label' => 'admin.guilds.war_price', 'sort' => 'warprice', 'type' => 'number'],
                ['key' => 'started_label', 'label' => 'admin.guilds.war_started', 'type' => 'text'],
                ['key' => 'result_label', 'label' => 'admin.guilds.war_result', 'type' => 'text'],
            ]);
    }
}
