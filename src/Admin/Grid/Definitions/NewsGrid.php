<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class NewsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/content/news?tab=posts', 'admin.news')
            ->orderBy([
                'id' => 'id',
                'title' => 'title',
                'author_login' => 'author_login',
                'status' => 'status',
                'published_at' => 'published_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.news.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'title', 'label' => 'admin.news.post_title', 'sort' => 'title', 'type' => 'link', 'href' => '/admin/content/news/posts/{id}'],
                ['key' => 'author_login', 'label' => 'admin.news.author', 'sort' => 'author_login', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.news.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'published' => ['class' => 'admin-badge-ok', 'label' => 'admin.news.status_published'],
                    'draft' => ['class' => 'admin-badge-muted', 'label' => 'admin.news.status_draft'],
                ]],
                ['key' => 'published_at', 'label' => 'admin.news.published', 'sort' => 'published_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.news.status', 'type' => 'select', 'options' => [
                    'draft' => 'admin.news.status_draft',
                    'published' => 'admin.news.status_published',
                ]],
            ])
            ->massActions('/admin/content/news/posts/mass', [
                ['id' => 'publish', 'label' => 'admin.grid.publish', 'confirm' => 'admin.news.confirm_mass_publish'],
                ['id' => 'draft', 'label' => 'admin.grid.draft', 'confirm' => 'admin.news.confirm_mass_draft'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.news.confirm_mass_delete'],
            ]);
        }
}
