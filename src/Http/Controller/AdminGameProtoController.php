<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Game\Proto\ProtoFormFields;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Theme\ThemeEngine;

class AdminGameProtoController extends AdminController
{
    private const PER_PAGE = 20;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private GameProtoService $protos,
        private ProtoFormFields $protoFields,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(string $kind): Response
    {
        $route = $this->routeKind($kind);
        $internal = $this->protos->kindFromRoute($route);
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->protos->page($internal, $page, self::PER_PAGE, $query);
        $totalPages = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
            $result = $this->protos->page($internal, $page, self::PER_PAGE, $query);
        }

        $prefix = $this->i18nPrefix($route);

        return $this->adminView($route, 'pages/proto-list.twig', [
            'title' => $this->t($prefix . '.title'),
            'pageLead' => $this->t($prefix . '.lead'),
            'headerHref' => '/admin/' . $route . '/new',
            'headerActionLabel' => $this->t($prefix . '.create'),
            'routeKind' => $route,
            'i18nPrefix' => $prefix,
            'records' => $result['rows'],
            'columns' => $this->columnLabels($prefix, $this->protos->listColumns($internal)),
            'query' => $q,
            'page' => $page,
            'total' => $result['total'],
            'totalPages' => $totalPages,
        ]);
    }

    public function create(string $kind): Response
    {
        $route = $this->routeKind($kind);

        return $this->formView($route, $this->protos->emptyRecord($this->protos->kindFromRoute($route)), null, 200, false);
    }

    public function store(string $kind): Response
    {
        $route = $this->routeKind($kind);

        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/' . $route . '/new');
        }

        $internal = $this->protos->kindFromRoute($route);
        $input = array_merge($this->protos->emptyRecord($internal), $this->formInput($internal));

        try {
            $this->protos->create($internal, $input);
            $this->flash('success', $this->t($this->i18nPrefix($route) . '.created'));

            return $this->redirect('/admin/' . $route);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($route, $input, $this->t($e->getMessage()), 422, false);
        }
    }

    public function edit(string $kind, string $id): Response
    {
        $route = $this->routeKind($kind);
        $internal = $this->protos->kindFromRoute($route);
        $record = $this->protos->find($internal, (int) $id);

        if ($record === null) {
            $this->flash('error', $this->t($this->i18nPrefix($route) . '.not_found'));

            return $this->redirect('/admin/' . $route);
        }

        return $this->formView($route, $record, null, 200, true);
    }

    public function update(string $kind, string $id): Response
    {
        $route = $this->routeKind($kind);

        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $internal = $this->protos->kindFromRoute($route);
        $vnum = (int) $id;
        $record = $this->protos->find($internal, $vnum);

        if ($record === null) {
            $this->flash('error', $this->t($this->i18nPrefix($route) . '.not_found'));

            return $this->redirect('/admin/' . $route);
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/' . $route . '/' . $vnum);
        }

        $input = $this->formInput($internal);

        try {
            $this->protos->update($internal, $vnum, $input);
            $this->flash('success', $this->t($this->i18nPrefix($route) . '.updated'));

            return $this->redirect('/admin/' . $route . '/' . $vnum);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(
                $route,
                array_merge($record, $input),
                $this->t($e->getMessage()),
                422,
                true,
            );
        }
    }

    public function destroy(string $kind, string $id): Response
    {
        $route = $this->routeKind($kind);

        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/' . $route);
        }

        $internal = $this->protos->kindFromRoute($route);
        $prefix = $this->i18nPrefix($route);

        try {
            if (!$this->protos->delete($internal, (int) $id)) {
                $this->flash('error', $this->t($prefix . '.not_found'));
            } else {
                $this->flash('success', $this->t($prefix . '.deleted'));
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/' . $route);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function formView(
        string $route,
        array $record = [],
        ?string $error = null,
        int $status = 200,
        bool $isEdit = false,
    ): Response {
        $internal = $this->protos->kindFromRoute($route);
        $prefix = $this->i18nPrefix($route);

        return $this->adminView($route, 'pages/proto-form.twig', [
            'title' => $this->t($isEdit ? $prefix . '.edit_title' : $prefix . '.create_title'),
            'pageLead' => $this->t($isEdit ? $prefix . '.edit_lead' : $prefix . '.create_lead'),
            'formId' => 'admin-proto-form',
            'saveLabel' => $this->t($isEdit ? 'admin.save' : $prefix . '.create'),
            'routeKind' => $route,
            'i18nPrefix' => $prefix,
            'protoKind' => $internal,
            'record' => $record,
            'tabs' => $this->protoFields->decorateTabs(
                $internal,
                $prefix,
                $this->protos->formTabs($internal),
                $record,
            ),
            'subtypesByType' => $this->protoFields->subtypesJsonMap(),
            'valueLabelsByType' => $this->protoFields->valueLabelsJsonMap(),
            'isEdit' => $isEdit,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array<string, string>
     */
    private function formInput(string $kind): array
    {
        $input = [];

        foreach ($this->protos->formTabs($kind) as $tab) {
            foreach ($tab['fields'] as $key) {
                if (ProtoEnums::widgetForField($kind, $key) === 'bitmask') {
                    $posted = $_POST[$key . '_flags'] ?? [];
                    $input[$key] = ProtoEnums::joinBitmask(
                        is_array($posted) ? array_map('strval', $posted) : [],
                        $key,
                    );

                    continue;
                }

                $input[$key] = trim((string) ($_POST[$key] ?? ''));
            }
        }

        $input['locale_name'] = trim((string) ($_POST['locale_name'] ?? ''));
        $input['vnum'] = trim((string) ($_POST['vnum'] ?? ''));

        return $input;
    }

    /**
     * @param list<string> $columns
     * @return list<array{key: string, label: string}>
     */
    private function columnLabels(string $prefix, array $columns): array
    {
        $labels = [];

        foreach ($columns as $column) {
            $key = $prefix . '.fields.' . $column;
            $labels[] = [
                'key' => $column,
                'label' => $this->translator->has($key) ? $this->t($key) : $column,
            ];
        }

        return $labels;
    }

    private function routeKind(string $kind): string
    {
        if ($kind !== GameProtoService::ROUTE_ITEMS && $kind !== GameProtoService::ROUTE_MOBS) {
            throw new \InvalidArgumentException('admin.proto.unknown');
        }

        return $kind;
    }

    private function i18nPrefix(string $route): string
    {
        return $route === GameProtoService::ROUTE_MOBS ? 'admin.mobs' : 'admin.items';
    }
}
