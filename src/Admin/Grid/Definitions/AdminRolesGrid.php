<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class AdminRolesGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/system/roles', 'admin.roles')
            ->defaultSort('label', 'asc')
            ->idField('slug')
            ->massIdType('string')
            ->massActions('/admin/system/roles/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.roles.confirm_mass_delete'],
            ])
            ->orderBy([
                'slug' => 'r.slug',
                'label' => 'r.label',
                'admin_count' => 'admin_count',
                'section_count' => 'resource_count',
                'created_at' => 'r.created_at',
            ])
            ->columns([
                ['key' => 'label', 'label' => 'admin.roles.label', 'sort' => 'label', 'type' => 'link', 'href' => '/admin/system/roles/{id}'],
                ['key' => 'slug', 'label' => 'admin.roles.slug', 'sort' => 'slug', 'type' => 'muted'],
                ['key' => 'admin_count', 'label' => 'admin.roles.admin_count', 'sort' => 'admin_count', 'type' => 'number'],
                ['key' => 'section_count', 'label' => 'admin.roles.resource_count', 'sort' => 'section_count', 'type' => 'number'],
                ['key' => 'created_at', 'label' => 'admin.roles.created_at', 'sort' => 'created_at', 'type' => 'date'],
            ]);
        }
}
