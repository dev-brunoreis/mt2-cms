<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class GuildsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game/guilds', 'admin.guilds')
            ->orderBy([
                'id' => 'g.id',
                'name' => 'g.name',
                'level' => 'g.level',
                'member_count' => 'member_count',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.guilds.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'name', 'label' => 'admin.guilds.name', 'sort' => 'name', 'type' => 'link', 'href' => '/admin/game/guilds/{id}'],
                ['key' => 'level', 'label' => 'admin.guilds.level', 'sort' => 'level', 'type' => 'number'],
                ['key' => 'member_count', 'label' => 'admin.guilds.members', 'sort' => 'member_count', 'type' => 'number'],
                ['key' => 'master', 'label' => 'admin.guilds.master', 'type' => 'text', 'filterSql' => 'p.name'],
            ]);
        }
}
