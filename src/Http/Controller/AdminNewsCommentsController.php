<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Http\Response;

class AdminNewsCommentsController extends AdminNewsBaseController
{
    public function comments(): Response
    {
        $spec = $this->comments->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->comments->countForGrid($q),
            fn ($q) => $this->comments->listForGrid($q),
        );

        return $this->adminView('news', 'pages/news-comments.twig', [
            'title' => $this->t('admin.news.comments_title'),
            'pageLead' => $this->t('admin.news.comments_lead'),
            'grid' => $grid,
        ]);
    }

    public function massComments(): Response
    {
        if ($redirect = $this->requireAdminResource('content/news/comments/mass')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/news?tab=comments');
        }

        $spec = $this->comments->gridDefinition()->spec();
        $action = $this->gridMassAction();
        $ids = $this->gridMassIds($spec);
        $count = 0;
        $succeeded = [];

        foreach ($ids as $id) {
            try {
                $ok = match ($action) {
                    'approve' => $this->comments->setStatus($id, 'approved'),
                    'reject' => $this->comments->setStatus($id, 'rejected'),
                    'delete' => $this->comments->delete($id),
                    default => throw new \InvalidArgumentException('invalid'),
                };

                if (!$ok) {
                    throw new \RuntimeException('skip');
                }

                $count++;
                $succeeded[] = $id;
            } catch (\InvalidArgumentException | \RuntimeException) {
                continue;
            }
        }

        if ($count > 0) {
            $this->audit('news.comments_mass', 'news_comment', null, [
                'action' => $action,
                'ids' => $succeeded,
                'count' => $count,
            ]);
        }

        $this->flash('success', $this->t('admin.news.mass_comments_done', ['count' => $count]));

        return $this->redirect('/admin/content/news?tab=comments');
    }

    public function approveComment(string $id): Response
    {
        return $this->setCommentStatus((int) $id, 'approved');
    }

    public function rejectComment(string $id): Response
    {
        return $this->setCommentStatus((int) $id, 'rejected');
    }

    public function deleteComment(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('content/news/comments/delete')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/news?tab=comments');
        }

        if (!$this->comments->delete((int) $id)) {
            $this->flash('error', $this->t('admin.news.comment_delete_failed'));
        } else {
            $this->audit('news.comment_delete', 'news_comment', (int) $id);
            $this->flash('success', $this->t('admin.news.comment_deleted'));
        }

        return $this->redirect('/admin/content/news?tab=comments');
    }

    private function setCommentStatus(int $id, string $status): Response
    {
        if ($redirect = $this->requireAdminResource('content/news/comments/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/news?tab=comments');
        }

        if (!$this->comments->setStatus($id, $status)) {
            $this->flash('error', $this->t('admin.news.comment_update_failed'));
        } else {
            $this->audit(
                $status === 'approved' ? 'news.comment_approve' : 'news.comment_reject',
                'news_comment',
                $id,
            );
            $this->flash(
                'success',
                $status === 'approved'
                    ? $this->t('admin.news.comment_approved')
                    : $this->t('admin.news.comment_rejected'),
            );
        }

        return $this->redirect('/admin/content/news?tab=comments');
    }
}
