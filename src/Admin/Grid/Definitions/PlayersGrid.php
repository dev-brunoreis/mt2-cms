<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class PlayersGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game/characters', 'admin.characters')
            ->orderBy([
                'id' => 'p.id',
                'name' => 'p.name',
                'account_id' => 'p.account_id',
                'job' => 'p.job',
                'level' => 'p.level',
                'playtime' => 'p.playtime',
                'last_play' => 'p.last_play',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.characters.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'name', 'label' => 'admin.characters.name', 'sort' => 'name', 'type' => 'icon_link', 'icon' => 'face', 'href' => '/admin/game/characters/{id}'],
                ['key' => 'account_id', 'label' => 'admin.characters.account', 'sort' => 'account_id', 'type' => 'muted'],
                ['key' => 'job', 'label' => 'admin.characters.job', 'sort' => 'job', 'type' => 'job'],
                ['key' => 'level', 'label' => 'admin.characters.level', 'sort' => 'level', 'type' => 'number'],
                ['key' => 'playtime', 'label' => 'admin.characters.playtime', 'sort' => 'playtime', 'type' => 'playtime'],
                ['key' => 'last_play', 'label' => 'admin.characters.last_play', 'sort' => 'last_play', 'type' => 'date'],
            ]);
        }
}
