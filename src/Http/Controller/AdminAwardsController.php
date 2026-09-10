<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\ItemAwardRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Theme\ThemeEngine;

class AdminAwardsController extends AdminController
{
    private const PER_PAGE = 20;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private ItemAwardRepository $awards,
        private AccountRepository $accounts,
        private PlayerRepository $players,
        private GameProtoService $protos,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(): Response
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $status = (string) ($_GET['status'] ?? '');
        $statusFilter = in_array($status, ['pending', 'taken'], true) ? $status : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->awards->countForAdmin($query, $statusFilter);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->adminView('awards', 'pages/awards.twig', [
            'title' => $this->t('admin.awards.title'),
            'pageLead' => $this->t('admin.awards.lead'),
            'headerHref' => '/admin/awards/new',
            'headerActionLabel' => $this->t('admin.awards.create'),
            'awards' => $this->awards->listForAdmin($page, self::PER_PAGE, $query, $statusFilter),
            'query' => $q,
            'status' => $statusFilter ?? '',
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function create(): Response
    {
        return $this->formView($this->prefill());
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/awards/new');
        }

        $input = $this->formInput();

        try {
            $this->validateAwardInput($input);
            $this->awards->create($input);
            $this->flash('success', $this->t('admin.awards.created'));

            return $this->redirect('/admin/awards');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/awards');
        }

        if (!$this->awards->deletePending((int) $id)) {
            $this->flash('error', $this->t('admin.awards.delete_failed'));
        } else {
            $this->flash('success', $this->t('admin.awards.deleted'));
        }

        return $this->redirect('/admin/awards');
    }

    /**
     * @param array<string, mixed> $award
     */
    private function formView(array $award = [], ?string $error = null, int $status = 200): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        return $this->adminView('awards', 'pages/award-form.twig', [
            'title' => $this->t('admin.awards.create_title'),
            'pageLead' => $this->t('admin.awards.create_lead'),
            'formId' => 'admin-award-form',
            'saveLabel' => $this->t('admin.save'),
            'award' => $award,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function prefill(): array
    {
        return [
            'login' => trim((string) ($_GET['login'] ?? '')),
            'pid' => (int) ($_GET['pid'] ?? 0),
            'vnum' => (int) ($_GET['vnum'] ?? 0),
            'count' => 1,
            'socket0' => 0,
            'socket1' => 0,
            'socket2' => 0,
            'mall' => 0,
            'why' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(): array
    {
        return [
            'login' => trim((string) ($_POST['login'] ?? '')),
            'pid' => (int) ($_POST['pid'] ?? 0),
            'vnum' => (int) ($_POST['vnum'] ?? 0),
            'count' => max(1, (int) ($_POST['count'] ?? 1)),
            'socket0' => (int) ($_POST['socket0'] ?? 0),
            'socket1' => (int) ($_POST['socket1'] ?? 0),
            'socket2' => (int) ($_POST['socket2'] ?? 0),
            'mall' => (int) ($_POST['mall'] ?? 0),
            'why' => trim((string) ($_POST['why'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function validateAwardInput(array $input): void
    {
        $account = $this->accounts->findByLogin((string) $input['login']);

        if ($account === null) {
            throw new \InvalidArgumentException('admin.awards.account_not_found');
        }

        $pid = (int) ($input['pid'] ?? 0);

        if ($pid > 0) {
            $player = $this->players->findById($pid);

            if ($player === null || (int) ($player['account_id'] ?? 0) !== (int) $account['id']) {
                throw new \InvalidArgumentException('admin.awards.invalid_pid');
            }
        }

        if ($this->protos->find(ProtoSchemas::KIND_ITEM, (int) $input['vnum']) === null) {
            throw new \InvalidArgumentException('admin.awards.item_not_found');
        }
    }
}
