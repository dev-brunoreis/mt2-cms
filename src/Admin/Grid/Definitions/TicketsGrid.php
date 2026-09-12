<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class TicketsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/content/tickets', 'admin.tickets')
            ->defaultSort('updated_at')
            ->orderBy([
                'id' => 't.id',
                'subject' => 't.subject',
                'account_login' => 't.account_login',
                'status' => 't.status',
                'updated_at' => 't.updated_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.tickets.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'subject', 'label' => 'admin.tickets.subject', 'sort' => 'subject', 'type' => 'link', 'href' => '/admin/content/tickets/{id}'],
                ['key' => 'account_login', 'label' => 'admin.tickets.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.tickets.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'open' => ['class' => 'admin-badge-warn', 'label' => 'admin.tickets.status_open'],
                    'answered' => ['class' => 'admin-badge-ok', 'label' => 'admin.tickets.status_answered'],
                    'closed' => ['class' => 'admin-badge-muted', 'label' => 'admin.tickets.status_closed'],
                ]],
                ['key' => 'updated_at', 'label' => 'admin.tickets.updated', 'sort' => 'updated_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.tickets.status', 'type' => 'select', 'options' => [
                    'open' => 'admin.tickets.status_open',
                    'answered' => 'admin.tickets.status_answered',
                    'closed' => 'admin.tickets.status_closed',
                ]],
            ])
            ->massActions('/admin/content/tickets/mass', [
                ['id' => 'close', 'label' => 'admin.grid.close', 'confirm' => 'admin.tickets.confirm_mass_close'],
            ]);
        }
}
