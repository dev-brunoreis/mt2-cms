<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class AccountsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game/accounts', 'admin.accounts')
            ->orderBy([
                'id' => 'id',
                'login' => 'login',
                'email' => 'email',
                'status' => 'status',
                'cash' => 'cash',
                'mileage' => 'mileage',
                'last_play' => 'last_play',
                'empire' => 'empire',
                'ip' => 'ip',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.accounts.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'login', 'label' => 'admin.accounts.login', 'sort' => 'login', 'type' => 'link', 'href' => '/admin/game/accounts/{id}'],
                ['key' => 'email', 'label' => 'admin.accounts.email', 'sort' => 'email', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.accounts.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'OK' => ['class' => 'admin-badge-ok', 'label' => 'admin.accounts.status_ok'],
                    'BLOCK' => ['class' => 'admin-badge-danger', 'label' => 'admin.accounts.status_block'],
                ]],
                ['key' => 'empire', 'label' => 'admin.accounts.empire', 'type' => 'empire'],
                ['key' => 'cash', 'label' => 'admin.accounts.cash', 'sort' => 'cash', 'type' => 'number'],
                ['key' => 'mileage', 'label' => 'admin.accounts.mileage', 'sort' => 'mileage', 'type' => 'number'],
                ['key' => 'last_play', 'label' => 'admin.accounts.last_play', 'sort' => 'last_play', 'type' => 'date'],
                ['key' => 'ip', 'label' => 'admin.accounts.last_ip', 'type' => 'text'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.accounts.status', 'type' => 'select', 'options' => [
                    'OK' => 'admin.accounts.status_ok',
                    'BLOCK' => 'admin.accounts.status_block',
                ]],
            ])
            ->massActions('/admin/game/accounts/mass', [
                ['id' => 'block', 'label' => 'admin.grid.block', 'confirm' => 'admin.accounts.confirm_mass_block'],
                ['id' => 'unblock', 'label' => 'admin.grid.unblock', 'confirm' => 'admin.accounts.confirm_mass_unblock'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.accounts.confirm_mass_delete'],
            ]);
        }
}
