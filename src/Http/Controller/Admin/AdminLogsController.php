<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridColumnFilters;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Admin\Grid\GridSpec;
use Mt2Cms\Admin\LogCatalog;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\LogRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminLogsController extends AdminController
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
        private LogRepository $logs,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $tabResources = [];

        foreach (LogCatalog::tabIds() as $tabId) {
            $tabResources[$tabId] = $this->tabViewResource($tabId);
        }

        $tab = $this->resolveResourceTab(LogCatalog::tabIds(), $tabResources, LogCatalog::CONNECTIONS_ID);

        if ($deny = $this->requireAdminResourceView($this->tabViewResource($tab))) {
            return $deny;
        }

        if ($this->wantsTabPartial()) {
            return $this->renderTabPartial($tab);
        }

        if ($tab === LogCatalog::CONNECTIONS_ID) {
            $initialPartial = [
                'template' => 'pages/logs-connections-partial.twig',
                'data' => $this->connectionsPartialData(),
            ];
        } else {
            $log = LogCatalog::get($tab);

            if ($log === null) {
                $tab = LogCatalog::CONNECTIONS_ID;
                $initialPartial = [
                    'template' => 'pages/logs-connections-partial.twig',
                    'data' => $this->connectionsPartialData(),
                ];
            } else {
                $initialPartial = [
                    'template' => 'pages/logs-partial.twig',
                    'data' => $this->logPartialData($tab, $log),
                ];
            }
        }

        return $this->adminView('logs', 'pages/logs-hub.twig', [
            'title' => $this->t('admin.logs.title'),
            'pageLead' => $this->t('admin.logs.lead'),
            'activeTab' => $tab,
            'logGroups' => LogCatalog::groupedTabs(),
            'logsBaseUrl' => AdminPaths::logs(),
            'initialPartial' => $initialPartial,
        ]);
    }

    private function renderTabPartial(string $tab): Response
    {
        if ($deny = $this->requireAdminResourceView($this->tabViewResource($tab))) {
            return $deny;
        }

        if ($tab === LogCatalog::CONNECTIONS_ID) {
            return $this->adminFragment('pages/logs-connections-partial.twig', $this->connectionsPartialData());
        }

        $log = LogCatalog::get($tab);

        if ($log === null) {
            return new Response('', 404);
        }

        return $this->adminFragment('pages/logs-partial.twig', $this->logPartialData($tab, $log));
    }

    private function tabViewResource(string $tabId): string
    {
        return 'logs/' . $tabId . '/view';
    }

    /**
     * @param array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   group: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null,
     *   playerColumns: list<string>,
     *   itemColumns: list<string>
     * } $log
     * @return array<string, mixed>
     */
    private function logPartialData(string $table, array $log): array
    {
        $missingTable = !$this->logs->tableExists($log['table']);
        $spec = $this->logGridSpec($table, $log);
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $missingTable ? 0 : $this->logs->countForGrid($table, $q),
            fn ($q) => $missingTable ? [] : $this->logs->listForGrid($table, $q),
        );

        return [
            'missingTable' => $missingTable,
            'grid' => $grid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionsPartialData(): array
    {
        $missingTable = !$this->logs->tableExists('loginlog2');
        $spec = $this->connectionsGridSpec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $missingTable ? 0 : $this->logs->countConnectionsForGrid($q),
            fn ($q) => $missingTable ? [] : $this->logs->listConnectionsForGrid($q),
        );

        return [
            'missingTable' => $missingTable,
            'grid' => $grid,
        ];
    }

    /**
     * @param array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   group: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null,
     *   playerColumns: list<string>,
     *   itemColumns: list<string>
     * } $log
     */
    private function logGridSpec(string $table, array $log): GridSpec
    {
        $dateColumns = $this->dateColumns($log['columns']);
        $columns = [];

        foreach ($log['columns'] as $column) {
            $isDate = in_array($column, $dateColumns, true);
            $columns[] = [
                'key' => $column,
                'label' => $this->translator->has('admin.logs.columns.' . $column)
                    ? 'admin.logs.columns.' . $column
                    : $column,
                'type' => 'template',
                'filterType' => $isDate ? 'date' : 'text',
                'template' => 'components/log-cell.twig',
                'itemColumns' => $log['itemColumns'],
                'dateColumns' => $dateColumns,
            ];
        }

        $filters = [];

        if ($log['dateColumn'] !== null) {
            $filters[] = ['key' => 'from', 'label' => 'admin.logs.from', 'type' => 'date'];
            $filters[] = ['key' => 'to', 'label' => 'admin.logs.to', 'type' => 'date'];
        }

        return $this->decoratedLogSpec(
            AdminPaths::logs($table),
            $columns,
            $filters,
            $log['columns'][0],
            $log['columns'],
        );
    }

    private function connectionsGridSpec(): GridSpec
    {
        return $this->decoratedLogSpec(
            AdminPaths::logs(LogCatalog::CONNECTIONS_ID),
            [
                ['key' => 'ip', 'label' => 'admin.logs.columns.ip', 'type' => 'text'],
                ['key' => 'account_id', 'label' => 'admin.logs.columns.account_id', 'type' => 'template', 'filterType' => 'number', 'template' => 'components/log-cell.twig', 'itemColumns' => [], 'dateColumns' => []],
                ['key' => 'connections', 'label' => 'admin.logs.columns.connections', 'type' => 'number'],
                ['key' => 'first_seen', 'label' => 'admin.logs.columns.first_seen', 'type' => 'date'],
                ['key' => 'last_seen', 'label' => 'admin.logs.columns.last_seen', 'type' => 'date'],
            ],
            [
                ['key' => 'from', 'label' => 'admin.logs.from', 'type' => 'date'],
                ['key' => 'to', 'label' => 'admin.logs.to', 'type' => 'date'],
            ],
            'last_seen',
            ['ip', 'account_id', 'connections', 'first_seen', 'last_seen'],
        );
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $filters
     * @param list<string> $sortWhitelist
     */
    private function decoratedLogSpec(
        string $action,
        array $columns,
        array $filters,
        string $defaultSort,
        array $sortWhitelist,
    ): GridSpec {
        $sqlMap = [];

        foreach ($columns as $column) {
            $key = (string) ($column['key'] ?? '');

            if ($key !== '') {
                $sqlMap[$key] = $key;
            }
        }

        $columns = GridColumnFilters::decorate($columns, $filters, $sqlMap, true);
        $extraFilters = GridColumnFilters::extraFilters($columns, $filters);

        return new GridSpec(
            action: $action,
            i18nPrefix: 'admin.logs',
            columns: $columns,
            filters: GridColumnFilters::requestFilters($columns, $extraFilters),
            extraFilters: $extraFilters,
            searchable: true,
            defaultSort: $defaultSort,
            sortWhitelist: $sortWhitelist,
        );
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private function dateColumns(array $columns): array
    {
        $known = ['time', 'date', 'login_time', 'logout_time', 'start_time', 'end_time', 'first_seen', 'last_seen'];

        return array_values(array_intersect($columns, $known));
    }
}
