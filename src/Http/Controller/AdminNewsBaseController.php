<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\NewsCommentRepository;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\NewsUploadService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Support\HtmlSanitizer;
use Mt2Cms\Theme\ThemeEngine;

abstract class AdminNewsBaseController extends AdminController
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AdminAuditService $auditLog,
        protected NewsRepository $news,
        protected NewsCommentRepository $comments,
        protected SettingsService $settings,
        protected HtmlSanitizer $sanitizer,
        protected NewsUploadService $uploads,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog);
    }
}
