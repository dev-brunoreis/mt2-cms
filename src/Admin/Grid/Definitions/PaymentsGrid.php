<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class PaymentsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/store/payments', 'admin.payments')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'id',
                'account_login' => 'account_login',
                'amount_cents' => 'amount_cents',
                'cash_amount' => 'cash_amount',
                'provider' => 'provider',
                'status' => 'status',
                'created_at' => 'created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.payments.id', 'sort' => 'id', 'type' => 'link', 'href' => '/admin/store/payments/{id}'],
                ['key' => 'account_login', 'label' => 'admin.payments.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'cash_amount', 'label' => 'admin.payments.cash', 'type' => 'number'],
                ['key' => 'amount_cents', 'label' => 'admin.payments.amount', 'sort' => 'amount_cents', 'type' => 'number'],
                ['key' => 'provider', 'label' => 'admin.payments.provider', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.payments.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'pending' => ['class' => 'admin-badge-warn', 'label' => 'admin.payments.status_pending'],
                    'paid' => ['class' => 'admin-badge-ok', 'label' => 'admin.payments.status_paid'],
                    'failed' => ['class' => 'admin-badge-danger', 'label' => 'admin.payments.status_failed'],
                    'refunded' => ['class' => 'admin-badge-muted', 'label' => 'admin.payments.status_refunded'],
                ]],
                ['key' => 'created_at', 'label' => 'admin.payments.created', 'sort' => 'created_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.payments.status', 'type' => 'select', 'options' => [
                    'pending' => 'admin.payments.status_pending',
                    'paid' => 'admin.payments.status_paid',
                    'failed' => 'admin.payments.status_failed',
                    'refunded' => 'admin.payments.status_refunded',
                ]],
            ]);
        }
}
