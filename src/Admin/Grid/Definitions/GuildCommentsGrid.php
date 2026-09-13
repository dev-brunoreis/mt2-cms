<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class GuildCommentsGrid
{
    public static function definition(int $guildId): GridDefinition
    {
        return GridDefinition::create('/admin/game/guilds/' . $guildId . '?tab=comments', 'admin.guilds.comments_grid')
            ->searchable(false)
            ->defaultSort('time', 'desc')
            ->orderBy([
                'name' => 'name',
                'content' => 'content',
                'time' => 'time',
            ])
            ->columns([
                ['key' => 'name', 'label' => 'admin.characters.name', 'sort' => 'name', 'type' => 'text'],
                ['key' => 'content', 'label' => 'admin.characters.guild_comment', 'sort' => 'content', 'type' => 'text'],
                ['key' => 'time', 'label' => 'admin.characters.guild_comment_time', 'sort' => 'time', 'type' => 'date'],
                [
                    'key' => '_actions',
                    'type' => 'actions',
                    'actions' => [
                        [
                            'type' => 'form',
                            'href' => '/admin/game/guilds/' . $guildId . '/comment/{id}/delete',
                            'label' => 'admin.guilds.delete_comment',
                            'danger' => true,
                            'confirm' => 'admin.guilds.confirm_delete_comment',
                        ],
                    ],
                ],
            ]);
    }
}
