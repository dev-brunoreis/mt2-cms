<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\Definitions\BannersGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\BannerRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\BannerUploadService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AdminBannersHubController extends AdminController
{
    private const TABS = ['slides', 'settings'];

    /** @var array<string, string> */
    private const TAB_VIEW_RESOURCES = [
        'slides' => 'content/banners/slides/view',
        'settings' => 'content/banners/settings/view',
    ];

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        private BannerRepository $banners,
        private BannerUploadService $uploads,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $tab = $this->resolveResourceTab(self::TABS, self::TAB_VIEW_RESOURCES, 'slides');

        if ($deny = $this->requireAdminResourceView(self::TAB_VIEW_RESOURCES[$tab])) {
            return $deny;
        }

        if ($this->wantsTabPartial()) {
            return $this->renderTabPartial($tab);
        }

        $header = match ($tab) {
            'slides' => [
                'headerHref' => AdminPaths::contentBannerNew(),
                'headerActionLabel' => $this->t('admin.banners.create'),
            ],
            'settings' => [
                'formId' => 'admin-banner-settings-form',
            ],
            default => [],
        };

        return $this->adminView('banners', 'pages/banners-hub.twig', array_merge([
            'title' => $this->t('admin.banners.hub_title'),
            'pageLead' => $this->t('admin.banners.hub_lead'),
            'activeTab' => $tab,
            'bannersBaseUrl' => AdminPaths::contentBanners(),
            'initialPartial' => $this->partialPayload($tab),
        ], $header));
    }

    public function create(): Response
    {
        if ($redirect = $this->requireAdminResource('content/banners/slides/create')) {
            return $redirect;
        }

        return $this->formView(null);
    }

    public function edit(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('content/banners/slides/edit')) {
            return $redirect;
        }

        $row = $this->banners->findById((int) $id);

        if ($row === null) {
            $this->flash('error', $this->t('admin.banners.not_found'));

            return $this->redirect(AdminPaths::contentBanners('slides'));
        }

        return $this->formView($row);
    }

    public function store(): Response
    {
        return $this->save(null);
    }

    public function update(string $id): Response
    {
        return $this->save((int) $id);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            BannersGrid::definition()->spec(),
            AdminPaths::contentBanners('slides'),
            [
                'enable' => function (int $id): bool {
                    return $this->banners->findById($id) !== null && $this->banners->setEnabled($id, true);
                },
                'disable' => function (int $id): bool {
                    return $this->banners->findById($id) !== null && $this->banners->setEnabled($id, false);
                },
                'delete' => function (int $id): bool {
                    $row = $this->banners->findById($id);

                    if ($row === null) {
                        return false;
                    }

                    $this->uploads->deleteByOriginalPath((string) ($row['original_path'] ?? ''));

                    return $this->banners->delete($id);
                },
            ],
            'banner',
            'admin.banners.mass_done',
            'content/banners/slides/mass',
        );
    }

    public function saveSettings(): Response
    {
        if ($redirect = $this->requireAdminResource('content/banners/settings/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::contentBanners('settings'));
        }

        $before = $this->settings->bannerSettings();
        $after = [
            'interval_ms' => max(2000, min(60000, (int) ($_POST['banner_interval_ms'] ?? 5500))),
            'autoplay' => isset($_POST['banner_autoplay']),
            'show_dots' => isset($_POST['banner_show_dots']),
            'show_arrows' => isset($_POST['banner_show_arrows']),
        ];

        $this->settings->setBannerIntervalMs($after['interval_ms']);
        $this->settings->setBannerAutoplay($after['autoplay']);
        $this->settings->setBannerShowDots($after['show_dots']);
        $this->settings->setBannerShowArrows($after['show_arrows']);
        $this->auditChange('banner.settings_save', 'banner_settings', null, $before, $after);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::contentBanners('settings'));
    }

    private function save(?int $id): Response
    {
        $resource = $id === null ? 'content/banners/slides/create' : 'content/banners/slides/edit';

        if ($redirect = $this->requireAdminResource($resource)) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($id === null ? AdminPaths::contentBannerNew() : AdminPaths::contentBanner((int) $id));
        }

        $data = [];

        try {
            $data = $this->readForm();
            $hasUpload = isset($_FILES['image']) && is_array($_FILES['image'])
                && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            if ($id === null && !$hasUpload) {
                throw new \InvalidArgumentException('admin.banners.image_required');
            }

            if ($hasUpload) {
                $stored = $this->uploads->store($_FILES['image']);
                $data['original_path'] = $stored['original_path'];
                $data['variants'] = $stored['variants'];
            }

            if ($id === null) {
                $newId = $this->banners->create($data);
                $this->audit('banner.create', 'banner', $newId);
            } else {
                $existing = $this->banners->findById($id);

                if ($existing === null) {
                    $this->flash('error', $this->t('admin.banners.not_found'));

                    return $this->redirect(AdminPaths::contentBanners('slides'));
                }

                if (!isset($data['original_path'])) {
                    $data['original_path'] = $existing['original_path'];
                    $data['variants'] = $existing['variants'];
                } else {
                    $this->uploads->deleteByOriginalPath((string) ($existing['original_path'] ?? ''));
                }

                $this->banners->update($id, $data);
                $this->audit('banner.update', 'banner', $id);
            }

            $this->flash('success', $this->t('admin.saved'));

            return $this->redirect(AdminPaths::contentBanners('slides'));
        } catch (\InvalidArgumentException | \RuntimeException | \JsonException $e) {
            $message = $this->t($e->getMessage());

            return $this->formView(
                $id !== null ? array_merge($this->banners->findById($id) ?? [], $data) : $data,
                $message,
                422,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readForm(): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $alt = trim((string) ($_POST['alt'] ?? ''));
        $link = trim((string) ($_POST['link_url'] ?? ''));

        if ($title === '') {
            throw new \InvalidArgumentException('admin.banners.title_required');
        }

        if ($alt === '') {
            $alt = $title;
        }

        if ($link !== '' && !preg_match('#^https?://#i', $link) && !str_starts_with($link, '/')) {
            throw new \InvalidArgumentException('admin.banners.invalid_link');
        }

        return [
            'title' => mb_substr($title, 0, 120),
            'alt' => mb_substr($alt, 0, 180),
            'link_url' => $link !== '' ? mb_substr($link, 0, 500) : null,
            'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
            'enabled' => isset($_POST['enabled']),
        ];
    }

    private function renderTabPartial(string $tab): Response
    {
        if (!in_array($tab, self::TABS, true)) {
            return new Response('', 404);
        }

        if ($deny = $this->requireAdminResourceView(self::TAB_VIEW_RESOURCES[$tab])) {
            return $deny;
        }

        $payload = $this->partialPayload($tab);

        return $this->adminFragment($payload['template'], $payload['data']);
    }

    /**
     * @return array{template: string, data: array<string, mixed>}
     */
    private function partialPayload(string $tab): array
    {
        return match ($tab) {
            'settings' => [
                'template' => 'pages/banner-settings-partial.twig',
                'data' => [
                    'formId' => 'admin-banner-settings-form',
                    'bannerSettings' => $this->settings->bannerSettings(),
                ],
            ],
            default => [
                'template' => 'pages/banner-slides-partial.twig',
                'data' => ['grid' => $this->slidesGrid()],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function slidesGrid(): array
    {
        $spec = BannersGrid::definition()->spec();

        return GridRunner::fetch(
            $spec,
            $this->gridQuery($spec),
            fn ($q) => $this->banners->countForGrid($q),
            fn ($q) => $this->banners->listForGrid($q),
        );
    }

    /**
     * @param array<string, mixed>|null $values
     */
    private function formView(?array $values, ?string $error = null, int $status = 200): Response
    {
        $values ??= [
            'title' => '',
            'alt' => '',
            'link_url' => '',
            'sort_order' => 0,
            'enabled' => true,
        ];

        return $this->adminView('banners', 'pages/banner-form.twig', [
            'title' => isset($values['id']) ? $this->t('admin.banners.edit') : $this->t('admin.banners.create'),
            'pageLead' => $this->t('admin.banners.form_lead'),
            'formId' => 'admin-banner-form',
            'saveLabel' => $this->t('admin.save'),
            'banner' => $values,
            'error' => $error,
        ], $status);
    }
}
