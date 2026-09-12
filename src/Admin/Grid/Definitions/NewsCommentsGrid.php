<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class NewsCommentsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/content/news?tab=comments', 'admin.news')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'c.id',
                'news_title' => 'n.title',
                'author_login' => 'c.account_login',
                'body' => 'c.body',
                'created_at' => 'c.created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.news.comment_id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'news_title', 'label' => 'admin.news.post_title', 'type' => 'text'],
                ['key' => 'author_login', 'label' => 'admin.news.comment_author', 'sort' => 'author_login', 'type' => 'text'],
                ['key' => 'body', 'label' => 'admin.news.comment_body', 'type' => 'text'],
                ['key' => 'created_at', 'label' => 'admin.news.comment_date', 'sort' => 'created_at', 'type' => 'date'],
            ])
            ->massActions('/admin/content/news/comments/mass', [
                ['id' => 'approve', 'label' => 'admin.grid.approve'],
                ['id' => 'reject', 'label' => 'admin.grid.reject'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.news.confirm_mass_delete_comments'],
            ]);
        }
}
