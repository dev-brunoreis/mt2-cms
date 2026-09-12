<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class BannersGrid
{
    public static function definition(): GridDefinition
    {
        return GridDefinition::create('/admin/content/banners', 'admin.banners')
            ->defaultSort('sort_order', 'asc')
            ->orderBy([
                'id' => 'id',
                'title' => 'title',
                'sort_order' => 'sort_order',
                'enabled' => 'enabled',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.banners.id', 'sort' => 'id', 'type' => 'muted'],
                [
                    'key' => 'preview_url',
                    'label' => 'admin.banners.preview',
                    'type' => 'template',
                    'template' => 'components/banner-thumb.twig',
                    'filter' => false,
                ],
                [
                    'key' => 'title',
                    'label' => 'admin.banners.title_field',
                    'sort' => 'title',
                    'type' => 'link',
                    'href' => '/admin/content/banners/{id}',
                ],
                ['key' => 'sort_order', 'label' => 'admin.banners.sort', 'sort' => 'sort_order', 'type' => 'number'],
                ['key' => 'enabled', 'label' => 'admin.banners.enabled', 'type' => 'badge', 'badgeMap' => [
                    '1' => ['class' => 'admin-badge-ok', 'label' => 'admin.yes'],
                    '0' => ['class' => 'admin-badge-danger', 'label' => 'admin.no'],
                ]],
            ])
            ->massActions('/admin/content/banners/mass', [
                ['id' => 'enable', 'label' => 'admin.grid.enable'],
                ['id' => 'disable', 'label' => 'admin.grid.disable'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.banners.confirm_mass_delete'],
            ]);
    }
}
