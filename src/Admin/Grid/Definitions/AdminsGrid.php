<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class AdminsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/system/admins', 'admin.admins')
            ->defaultSort('login', 'asc')
            ->orderBy([
                'id' => 'id',
                'login' => 'login',
                'role' => 'role',
                'created_at' => 'created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.admins.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'login', 'label' => 'admin.admins.login', 'sort' => 'login', 'type' => 'link', 'href' => '/admin/system/admins/{id}'],
                ['key' => 'role_label', 'label' => 'admin.admins.role', 'sort' => 'role', 'type' => 'text'],
                ['key' => 'use_custom_acl', 'label' => 'admin.admins.custom_acl', 'type' => 'bool'],
                ['key' => 'created_at', 'label' => 'admin.admins.created_at', 'sort' => 'created_at', 'type' => 'date'],
            ])
            ->massActions('/admin/system/admins/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.admins.confirm_mass_delete'],
            ])
            ->filters([
                ['key' => 'role', 'label' => 'admin.admins.role', 'type' => 'select', 'options' => [], 'translateOptions' => false],
            ]);
        }
}
