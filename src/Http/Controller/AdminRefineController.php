<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\RefineRepository;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminRefineController extends AdminController
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
        private RefineRepository $refine,
        private GameProtoService $protos,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->refine->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $usedByRefineIds = $query->q !== null && ctype_digit($query->q)
            ? $this->protos->refineIdsForItemVnumPrefix($query->q)
            : null;
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->refine->countForGrid($q, $usedByRefineIds),
            fn ($q) => $this->enrichRecipesForList($this->refine->listForGrid($q, $usedByRefineIds)),
        );

        return $this->adminView('refine', 'pages/refine-list.twig', [
            'title' => $this->t('admin.refine.title'),
            'pageLead' => $this->t('admin.refine.lead'),
            'headerHref' => '/admin/refine/new',
            'headerActionLabel' => $this->t('admin.refine.create'),
            'grid' => $grid,
        ]);
    }

    public function create(): Response
    {
        return $this->formView($this->emptyRecipe());
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminSection('refine')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/refine/new');
        }

        $input = $this->formInput();

        try {
            $this->validateVnums($input);
            $recipe = $this->refine->create($input);
            $this->audit('refine.create', 'refine', (int) $recipe['id']);
            $this->flash('success', $this->t('admin.refine.created'));

            return $this->redirect('/admin/refine/' . $recipe['id']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(array_merge($this->emptyRecipe(), $input), $this->t($e->getMessage()), 422);
        }
    }

    public function edit(string $id): Response
    {
        $recipe = $this->refine->findForAdmin((int) $id);

        if ($recipe === null) {
            $this->flash('error', $this->t('admin.refine.not_found'));

            return $this->redirect('/admin/refine');
        }

        return $this->formView($recipe);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('refine')) {
            return $redirect;
        }

        $recipeId = (int) $id;
        $recipe = $this->refine->findForAdmin($recipeId);

        if ($recipe === null) {
            $this->flash('error', $this->t('admin.refine.not_found'));

            return $this->redirect('/admin/refine');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/refine/' . $recipeId);
        }

        $input = $this->formInput();

        try {
            $this->validateVnums($input);
            $this->refine->update($recipeId, $input);
            $this->audit('refine.update', 'refine', $recipeId);
            $this->flash('success', $this->t('admin.refine.updated'));

            return $this->redirect('/admin/refine/' . $recipeId);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(array_merge($recipe, $input), $this->t($e->getMessage()), 422);
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('refine')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/refine');
        }

        if (!$this->refine->delete((int) $id)) {
            $this->flash('error', $this->t('admin.refine.not_found'));
        } else {
            $this->audit('refine.delete', 'refine', (int) $id);
            $this->flash('success', $this->t('admin.refine.deleted'));
        }

        return $this->redirect('/admin/refine');
    }

    /**
     * @param array<string, mixed> $recipe
     */
    private function formView(array $recipe, ?string $error = null, int $status = 200): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $isEdit = isset($recipe['id']) && (int) $recipe['id'] > 0;

        return $this->adminView('refine', 'pages/refine-form.twig', [
            'title' => $this->t($isEdit ? 'admin.refine.edit_title' : 'admin.refine.create_title', ['id' => (string) ($recipe['id'] ?? '')]),
            'pageLead' => $this->t($isEdit ? 'admin.refine.edit_lead' : 'admin.refine.create_lead'),
            'formId' => 'admin-refine-form',
            'saveLabel' => $this->t('admin.save'),
            'recipe' => $recipe,
            'isEdit' => $isEdit,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRecipe(): array
    {
        return [
            'materials' => array_fill(0, 5, ['vnum' => 0, 'count' => 0]),
            'cost' => 0,
            'src_vnum' => 0,
            'result_vnum' => 0,
            'prob' => 100,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(): array
    {
        $input = [
            'cost' => (int) ($_POST['cost'] ?? 0),
            'src_vnum' => (int) ($_POST['src_vnum'] ?? 0),
            'result_vnum' => (int) ($_POST['result_vnum'] ?? 0),
            'prob' => (int) ($_POST['prob'] ?? 100),
        ];

        for ($i = 0; $i < 5; $i++) {
            $input['vnum' . $i] = (int) ($_POST['vnum' . $i] ?? 0);
            $input['count' . $i] = (int) ($_POST['count' . $i] ?? 0);
        }

        return $input;
    }

    /**
     * @param list<array<string, mixed>> $recipes
     * @return list<array<string, mixed>>
     */
    private function enrichRecipesForList(array $recipes): array
    {
        foreach ($recipes as &$recipe) {
            $recipe['source_label'] = $this->itemLabel((int) ($recipe['src_vnum'] ?? 0));
            $recipe['result_label'] = $this->itemLabel((int) ($recipe['result_vnum'] ?? 0));
        }

        unset($recipe);

        return $recipes;
    }

    private function itemLabel(int $vnum): string
    {
        if ($vnum < 1) {
            return '—';
        }

        $row = $this->protos->find(ProtoSchemas::KIND_ITEM, $vnum);

        if ($row === null) {
            return (string) $vnum;
        }

        $locale = trim((string) ($row['locale_name'] ?? ''));

        return $locale !== '' ? $locale : trim((string) ($row['name'] ?? (string) $vnum));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function validateVnums(array $input): void
    {
        $vnums = [
            (int) ($input['src_vnum'] ?? 0),
            (int) ($input['result_vnum'] ?? 0),
        ];

        for ($i = 0; $i < 5; $i++) {
            $vnums[] = (int) ($input['vnum' . $i] ?? 0);
        }

        foreach ($vnums as $vnum) {
            if ($vnum < 1) {
                continue;
            }

            if ($this->protos->find(ProtoSchemas::KIND_ITEM, $vnum) === null) {
                throw new \InvalidArgumentException('admin.refine.item_not_found');
            }
        }
    }
}
