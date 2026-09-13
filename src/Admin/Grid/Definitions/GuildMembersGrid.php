<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class GuildMembersGrid
{
    public static function definition(int $guildId): GridDefinition
    {
        return GridDefinition::create('/admin/game/guilds/' . $guildId . '?tab=members', 'admin.guilds.members_grid')
            ->searchable(false)
            ->defaultSort('name', 'asc')
            ->orderBy([
                'name' => 'name',
                'level' => 'level',
                'grade' => 'grade',
                'offer' => 'offer',
            ])
            ->columns([
                ['key' => 'name', 'label' => 'admin.characters.name', 'sort' => 'name', 'type' => 'link', 'href' => '/admin/game/characters/{id}'],
                ['key' => 'job', 'label' => 'admin.characters.job', 'type' => 'job'],
                ['key' => 'level', 'label' => 'admin.characters.level', 'sort' => 'level', 'type' => 'number'],
                ['key' => 'grade_name', 'label' => 'admin.characters.guild_grade', 'sort' => 'grade', 'type' => 'text'],
                ['key' => 'offer', 'label' => 'admin.characters.guild_offer', 'sort' => 'offer', 'type' => 'number'],
                [
                    'key' => '_actions',
                    'type' => 'actions',
                    'actions' => [
                        [
                            'type' => 'form',
                            'href' => '/admin/game/guilds/' . $guildId . '/kick/{id}',
                            'label' => 'admin.guilds.kick',
                            'danger' => true,
                            'confirm' => 'admin.guilds.confirm_kick_row',
                            'when' => ['field' => 'can_kick', 'equals' => '1'],
                        ],
                    ],
                ],
            ]);
    }
}
