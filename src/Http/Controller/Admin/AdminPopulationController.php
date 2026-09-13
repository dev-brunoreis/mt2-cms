<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\PlayerCensusService;
use Mt2Cms\Theme\ThemeEngine;

class AdminPopulationController extends AdminController
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        private PlayerCensusService $census,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $census = $this->census->snapshot();

        return $this->adminView('population', 'pages/population.twig', [
            'title' => $this->t('admin.population.title'),
            'pageLead' => $this->t('admin.population.lead'),
            'census' => $census,
        ]);
    }
}
