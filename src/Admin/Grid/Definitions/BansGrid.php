<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class BansGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game/bans', 'admin.bans')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'id',
                'account_login' => 'account_login',
                'created_at' => 'created_at',
                'expires_at' => 'expires_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.bans.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'account_login', 'label' => 'admin.bans.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'reason', 'label' => 'admin.bans.reason', 'type' => 'text'],
                ['key' => 'expires_at', 'label' => 'admin.bans.expires', 'sort' => 'expires_at', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'admin.bans.created_at', 'sort' => 'created_at', 'type' => 'date'],
                ['key' => 'lifted_at', 'label' => 'admin.bans.lifted', 'type' => 'date'],
            ])
            ->massActions('/admin/game/bans/mass', [
                ['id' => 'lift', 'label' => 'admin.bans.lift', 'confirm' => 'admin.bans.confirm_mass_lift'],
            ]);
        }
}
