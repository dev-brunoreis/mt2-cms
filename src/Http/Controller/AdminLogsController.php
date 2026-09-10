<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Admin\Grid\GridSpec;
use Mt2Cms\Admin\LogCatalog;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\LogRepository;
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
        AdminAuditService $auditLog,
        private LogRepository $logs,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog);
    }

    public function show(string $table): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if ($table === LogCatalog::CONNECTIONS_ID) {
            return $this->connections();
        }

        $log = LogCatalog::get($table);

        if ($log === null) {
            $this->flash('error', $this->t('admin.logs.unknown'));

            return $this->redirect('/admin/logs/' . LogCatalog::CONNECTIONS_ID);
        }

        $missingTable = !$this->logs->tableExists($log['table']);
        $spec = $this->logGridSpec($table, $log);
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $missingTable ? 0 : $this->logs->countForGrid($table, $q),
            fn ($q) => $missingTable ? [] : $this->logs->listForGrid($table, $q),
        );

        return $this->adminView('log-' . $table, 'pages/logs.twig', [
            'title' => $this->t($log['label']),
            'pageLead' => $this->t('admin.logs.lead'),
            'missingTable' => $missingTable,
            'grid' => $grid,
        ]);
    }

    private function connections(): Response
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

        return $this->adminView('log-' . LogCatalog::CONNECTIONS_ID, 'pages/account-ips.twig', [
            'title' => $this->t('admin.logs.connections_title'),
            'pageLead' => $this->t('admin.logs.connections_lead'),
            'missingTable' => $missingTable,
            'grid' => $grid,
        ]);
    }

    /**
     * @param array{
     *   id: string,
     *   table: string,
     *   label: string,
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
            $columns[] = [
                'key' => $column,
                'label' => $this->translator->has('admin.logs.columns.' . $column)
                    ? 'admin.logs.columns.' . $column
                    : $column,
                'type' => 'template',
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

        return new GridSpec(
            action: '/admin/logs/' . $table,
            i18nPrefix: 'admin.logs',
            columns: $columns,
            filters: $filters,
            searchable: true,
            defaultSort: $log['columns'][0],
            sortWhitelist: $log['columns'],
        );
    }

    private function connectionsGridSpec(): GridSpec
    {
        return new GridSpec(
            action: '/admin/logs/' . LogCatalog::CONNECTIONS_ID,
            i18nPrefix: 'admin.logs',
            columns: [
                ['key' => 'ip', 'label' => 'admin.logs.columns.ip', 'type' => 'text'],
                ['key' => 'account_id', 'label' => 'admin.logs.columns.account_id', 'type' => 'template', 'template' => 'components/log-cell.twig', 'itemColumns' => [], 'dateColumns' => []],
                ['key' => 'connections', 'label' => 'admin.logs.columns.connections', 'type' => 'number'],
                ['key' => 'first_seen', 'label' => 'admin.logs.columns.first_seen', 'type' => 'date'],
                ['key' => 'last_seen', 'label' => 'admin.logs.columns.last_seen', 'type' => 'date'],
            ],
            filters: [
                ['key' => 'from', 'label' => 'admin.logs.from', 'type' => 'date'],
                ['key' => 'to', 'label' => 'admin.logs.to', 'type' => 'date'],
            ],
            defaultSort: 'last_seen',
            sortWhitelist: ['ip', 'account_id', 'connections', 'first_seen', 'last_seen'],
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
