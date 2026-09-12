<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class EventsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/content/events', 'admin.events')
            ->defaultSort('starts_at')
            ->orderBy([
                'id' => 'id',
                'title' => 'title',
                'starts_at' => 'starts_at',
                'ends_at' => 'ends_at',
                'published' => 'published',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.events.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'title', 'label' => 'admin.events.title_col', 'sort' => 'title', 'type' => 'link', 'href' => '/admin/content/events/{id}'],
                ['key' => 'starts_at', 'label' => 'admin.events.starts_at', 'sort' => 'starts_at', 'type' => 'date'],
                ['key' => 'ends_at', 'label' => 'admin.events.ends_at', 'sort' => 'ends_at', 'type' => 'date'],
                ['key' => 'published', 'label' => 'admin.events.published_col', 'sort' => 'published', 'type' => 'badge', 'badgeMap' => [
                    '1' => ['class' => 'admin-badge-ok', 'label' => 'admin.events.published_yes'],
                    '0' => ['class' => 'admin-badge-muted', 'label' => 'admin.events.published_no'],
                ]],
            ])
            ->filters([
                ['key' => 'published', 'label' => 'admin.events.published_col', 'type' => 'select', 'options' => [
                    '1' => 'admin.events.published_yes',
                    '0' => 'admin.events.published_no',
                ]],
            ])
            ->massActions('/admin/content/events/mass', [
                ['id' => 'publish', 'label' => 'admin.grid.publish', 'confirm' => 'admin.events.confirm_mass_publish'],
                ['id' => 'unpublish', 'label' => 'admin.events.unpublish', 'confirm' => 'admin.events.confirm_mass_unpublish'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.events.confirm_mass_delete'],
            ]);
        }
}
