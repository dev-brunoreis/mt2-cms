<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class AccountCharactersGrid
{
    public static function definition(int $accountId): GridDefinition
    {
        return GridDefinition::create('/admin/game/accounts/' . $accountId . '?tab=activity', 'admin.accounts.characters_grid')
            ->searchable(false)
            ->defaultSort('name', 'asc')
            ->orderBy([
                'name' => 'name',
                'level' => 'level',
                'playtime' => 'playtime',
            ])
            ->columns([
                ['key' => 'name', 'label' => 'account.name', 'sort' => 'name', 'type' => 'icon_link', 'icon' => 'face', 'href' => '/admin/game/characters/{id}'],
                ['key' => 'job', 'label' => 'account.job', 'type' => 'job'],
                ['key' => 'level', 'label' => 'account.level', 'sort' => 'level', 'type' => 'number'],
                ['key' => 'playtime', 'label' => 'account.playtime', 'sort' => 'playtime', 'type' => 'playtime'],
            ]);
    }
}
