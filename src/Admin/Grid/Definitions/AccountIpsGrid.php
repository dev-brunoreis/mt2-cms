<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class AccountIpsGrid
{
    public static function definition(int $accountId): GridDefinition
    {
        return GridDefinition::create('/admin/game/accounts/' . $accountId . '?tab=activity', 'admin.accounts.ips_grid')
            ->searchable(false)
            ->idField('ip')
            ->massIdType('string')
            ->defaultSort('last_seen', 'desc')
            ->orderBy([
                'ip' => 'ip',
                'connections' => 'connections',
                'first_seen' => 'first_seen',
                'last_seen' => 'last_seen',
            ])
            ->columns([
                ['key' => 'ip', 'label' => 'admin.logs.columns.ip', 'sort' => 'ip', 'type' => 'text'],
                ['key' => 'connections', 'label' => 'admin.logs.columns.connections', 'sort' => 'connections', 'type' => 'number'],
                ['key' => 'first_seen', 'label' => 'admin.logs.columns.first_seen', 'sort' => 'first_seen', 'type' => 'date'],
                ['key' => 'last_seen', 'label' => 'admin.logs.columns.last_seen', 'sort' => 'last_seen', 'type' => 'date'],
            ]);
    }
}
