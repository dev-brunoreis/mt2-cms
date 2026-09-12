<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\Definitions\ProtoGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Admin\Grid\GridSpec;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Game\Proto\ProtoFormFields;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Repository\ShopRepository;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Service\MobDropService;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminGameProtoController extends AdminController
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
        private GameProtoService $protos,
        private ProtoFormFields $protoFields,
        private MobDropService $mobDrops,
        private ProtoEnums $protoEnums,
        private ShopRepository $shops,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(string $kind): Response
    {
        $route = $this->routeKind($kind);
        $internal = $this->protos->kindFromRoute($route);
        $prefix = $this->i18nPrefix($route);
        $spec = $this->protoGridSpec($route);
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->protos->countForGrid($internal, $q),
            fn ($q) => $this->protos->listForGrid($internal, $q),
        );

        return $this->adminView($route, 'pages/proto-list.twig', [
            'title' => $this->t($prefix . '.title'),
            'pageLead' => $this->t($prefix . '.lead'),
            'headerHref' => $this->protoPath($route) . '/new',
            'headerActionLabel' => $this->t($prefix . '.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(string $kind): Response
    {
        $route = $this->routeKind($kind);
        $internal = $this->protos->kindFromRoute($route);
        $prefix = $this->i18nPrefix($route);

        return $this->runMassActions(
            $this->protoGridSpec($route),
            $this->protoPath($route),
            [
                'delete' => fn (int $id): bool => $this->protos->delete($internal, $id),
            ],
            'proto_' . $route,
            $prefix . '.mass_done',
            'game-data/' . $route . '/mass',
        );
    }

    public function create(string $kind): Response
    {
        $route = $this->routeKind($kind);

        return $this->formView($route, $this->protos->emptyRecord($this->protos->kindFromRoute($route)), null, 200, false);
    }

    public function store(string $kind): Response
    {
        $route = $this->routeKind($kind);

        if ($redirect = $this->requireAdminResource('game-data/' . $route . '/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/' . $route . '/new');
        }

        $internal = $this->protos->kindFromRoute($route);
        $input = array_merge($this->protos->emptyRecord($internal), $this->formInput($internal));

        try {
            $this->protos->create($internal, $input);
            $vnum = (int) ($input['vnum'] ?? 0);
            $this->audit($route . '.create', 'proto_' . $route, $vnum > 0 ? $vnum : null);
            $this->flash('success', $this->t($this->i18nPrefix($route) . '.created'));

            return $this->redirect($this->protoPath($route));
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

            return $this->redirect($this->protoPath($route));
        }

        return $this->formView($route, $record, null, 200, true);
    }

    public function update(string $kind, string $id): Response
    {
        $route = $this->routeKind($kind);

        if ($redirect = $this->requireAdminResource('game-data/' . $route . '/edit')) {
            return $redirect;
        }

        $internal = $this->protos->kindFromRoute($route);
        $vnum = (int) $id;
        $record = $this->protos->find($internal, $vnum);

        if ($record === null) {
            $this->flash('error', $this->t($this->i18nPrefix($route) . '.not_found'));

            return $this->redirect($this->protoPath($route));
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($this->protoPath($route) . '/' . $vnum);
        }

        $input = $this->formInput($internal);

        try {
            $this->protos->update($internal, $vnum, $input);
            $this->auditChange(
                $route . '.update',
                'proto_' . $route,
                $vnum,
                $record,
                array_merge($record, $input),
            );
            $this->flash('success', $this->t($this->i18nPrefix($route) . '.updated'));

            return $this->redirect($this->protoPath($route) . '/' . $vnum);
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

        if ($redirect = $this->requireAdminResource('game-data/' . $route . '/delete')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/' . $route);
        }

        $internal = $this->protos->kindFromRoute($route);
        $prefix = $this->i18nPrefix($route);

        try {
            if (!$this->protos->delete($internal, (int) $id)) {
                $this->flash('error', $this->t($prefix . '.not_found'));
            } else {
                $this->audit($route . '.delete', 'proto_' . $route, (int) $id);
                $this->flash('success', $this->t($prefix . '.deleted'));
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect($this->protoPath($route));
    }

    private function protoPath(string $route): string
    {
        return $route === GameProtoService::ROUTE_MOBS
            ? AdminPaths::gameDataMobs()
            : AdminPaths::gameDataItems();
    }

    private function protoGridSpec(string $route): GridSpec
    {
        return ProtoGrid::definition($this->protos, $route)->spec();
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
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $internal = $this->protos->kindFromRoute($route);
        $prefix = $this->i18nPrefix($route);
        $tabs = $this->protoFields->decorateTabs(
            $internal,
            $prefix,
            $this->protos->formTabs($internal),
            $record,
        );
        $mobDrops = null;
        $itemShops = null;

        if ($isEdit && $internal === ProtoSchemas::KIND_MOB) {
            $tabs[] = [
                'id' => 'drops',
                'label' => $this->t($prefix . '.tab_drops'),
                'active' => false,
                'fields' => [],
                'kind' => 'drops',
            ];
        }

        if ($isEdit && $internal === ProtoSchemas::KIND_ITEM) {
            $tabs[] = [
                'id' => 'shops',
                'label' => $this->t($prefix . '.tab_shops'),
                'active' => false,
                'fields' => [],
                'kind' => 'shops',
            ];
        }

        $tabIds = array_map(static fn (array $tab): string => $tab['id'], $tabs);
        $tab = $this->requestedTab($tabIds, $tabs[0]['id'] ?? 'identity');

        foreach ($tabs as $index => $entry) {
            $tabs[$index]['active'] = $entry['id'] === $tab;
        }

        if ($tab === 'drops' && $isEdit && $internal === ProtoSchemas::KIND_MOB) {
            $mobDrops = $this->mobDrops->forMob($record);
        }

        if ($tab === 'shops' && $isEdit && $internal === ProtoSchemas::KIND_ITEM) {
            $itemShops = $this->shopsSellingItem((int) ($record['vnum'] ?? 0));
        }

        $data = [
            'title' => $this->t($isEdit ? $prefix . '.edit_title' : $prefix . '.create_title'),
            'pageLead' => $this->t($isEdit ? $prefix . '.edit_lead' : $prefix . '.create_lead'),
            'formId' => 'admin-proto-form',
            'saveLabel' => $this->t('admin.save'),
            'routeKind' => $route,
            'protoBase' => $this->protoPath($route),
            'i18nPrefix' => $prefix,
            'protoKind' => $internal,
            'record' => $record,
            'tabs' => $tabs,
            'activeTab' => $tab,
            'mobDrops' => $mobDrops,
            'itemShops' => $itemShops,
            'subtypesByType' => $this->protoFields->subtypesJsonMap(),
            'valueLabelsByType' => $this->protoFields->valueLabelsJsonMap(),
            'isEdit' => $isEdit,
            'error' => $error,
        ];

        if ($this->wantsTabPartial()) {
            $template = match ($tab) {
                'drops' => 'components/mob-drops.twig',
                'shops' => 'components/item-shops.twig',
                default => null,
            };

            if ($template === null) {
                return Response::notFound();
            }

            return $this->adminFragment($template, $data);
        }

        return $this->adminView($route, 'pages/proto-form.twig', $data, $status);
    }

    /**
     * @return array<string, string>
     */
    private function formInput(string $kind): array
    {
        $input = [];

        foreach ($this->protos->formTabs($kind) as $tab) {
            foreach ($tab['fields'] as $key) {
                if ($this->protoEnums->widgetForField($kind, $key) === 'bitmask') {
                    $posted = $_POST[$key . '_flags'] ?? [];
                    $input[$key] = $this->protoEnums->joinBitmask(
                        is_array($posted) ? array_map('strval', $posted) : [],
                        $key,
                        $kind,
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

    /**
     * @return list<array<string, mixed>>
     */
    private function shopsSellingItem(int $itemVnum): array
    {
        $shops = $this->shops->listByItemVnum($itemVnum);

        foreach ($shops as $index => $shop) {
            $shops[$index]['npc_name'] = $this->mobLabel((int) ($shop['npc_vnum'] ?? 0));
        }

        return $shops;
    }

    private function mobLabel(int $vnum): string
    {
        if ($vnum < 1) {
            return '';
        }

        $row = $this->protos->find(ProtoSchemas::KIND_MOB, $vnum);

        if ($row === null) {
            return '';
        }

        $locale = trim((string) ($row['locale_name'] ?? ''));

        return $locale !== '' ? $locale : trim((string) ($row['name'] ?? ''));
    }
}
