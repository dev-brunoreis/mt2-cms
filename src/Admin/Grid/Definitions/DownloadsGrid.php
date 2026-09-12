<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class DownloadsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/content/downloads', 'admin.downloads')
            ->defaultSort('sort_order')
            ->orderBy([
                'id' => 'id',
                'title' => 'title',
                'category' => 'category',
                'sort_order' => 'sort_order',
                'enabled' => 'enabled',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.downloads.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'title', 'label' => 'admin.downloads.title_field', 'sort' => 'title', 'type' => 'link', 'href' => '/admin/content/downloads/{id}'],
                ['key' => 'category', 'label' => 'admin.downloads.category', 'sort' => 'category', 'type' => 'text', 'filterOptions' => [
                    'client' => 'admin.downloads.category_client',
                    'patch' => 'admin.downloads.category_patch',
                    'tools' => 'admin.downloads.category_tools',
                    'other' => 'admin.downloads.category_other',
                ]],
                ['key' => 'sort_order', 'label' => 'admin.downloads.sort', 'sort' => 'sort_order', 'type' => 'number'],
                ['key' => 'enabled', 'label' => 'admin.downloads.enabled', 'type' => 'badge', 'badgeMap' => [
                    '1' => ['class' => 'admin-badge-ok', 'label' => 'admin.yes'],
                    '0' => ['class' => 'admin-badge-danger', 'label' => 'admin.no'],
                ]],
            ])
            ->massActions('/admin/content/downloads/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.downloads.confirm_mass_delete'],
            ]);
        }
}
