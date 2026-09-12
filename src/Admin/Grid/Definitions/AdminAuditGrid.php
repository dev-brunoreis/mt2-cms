<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class AdminAuditGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/system/audit-log', 'admin.audit_log')
            ->defaultSort('created_at', 'desc')
            ->searchable(false)
            ->orderBy([
                'id' => 'id',
                'login' => 'login',
                'action' => 'action',
                'target_type' => 'target_type',
                'target_id' => 'target_id',
                'ip' => 'ip',
                'created_at' => 'created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.audit_log.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'created_at', 'label' => 'admin.audit_log.created', 'sort' => 'created_at', 'type' => 'date'],
                ['key' => 'login', 'label' => 'admin.audit_log.login', 'sort' => 'login', 'type' => 'text'],
                ['key' => 'action', 'label' => 'admin.audit_log.action', 'sort' => 'action', 'type' => 'text'],
                ['key' => 'target_type', 'label' => 'admin.audit_log.target_type', 'sort' => 'target_type', 'type' => 'text'],
                ['key' => 'target_id', 'label' => 'admin.audit_log.target_id', 'sort' => 'target_id', 'type' => 'number'],
                ['key' => 'ip', 'label' => 'admin.audit_log.ip', 'sort' => 'ip', 'type' => 'text'],
                ['key' => 'before', 'label' => 'admin.audit_log.before', 'type' => 'template', 'template' => 'components/audit-diff.twig', 'class' => 'admin-audit-diff-cell'],
                ['key' => 'after', 'label' => 'admin.audit_log.after', 'type' => 'template', 'template' => 'components/audit-diff.twig', 'class' => 'admin-audit-diff-cell'],
            ])
            ->filters([
                ['key' => 'login', 'label' => 'admin.audit_log.login', 'type' => 'text'],
                ['key' => 'action', 'label' => 'admin.audit_log.action', 'type' => 'text'],
                ['key' => 'target_type', 'label' => 'admin.audit_log.target_type', 'type' => 'text'],
            ]);
        }
}
