<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\LogCatalog;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\LogRepository;
use Mt2Cms\Theme\ThemeEngine;

class AdminLogsController extends AdminController
{
    private const PER_PAGE = 25;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private LogRepository $logs,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
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

        $filters = $this->filters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $missing = !$this->logs->tableExists($log['table']);
        $total = $missing ? 0 : $this->logs->countForAdmin($table, $filters);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->adminView('log-' . $table, 'pages/logs.twig', [
            'title' => $this->t($log['label']),
            'pageLead' => $this->t('admin.logs.lead'),
            'logId' => $table,
            'logPath' => '/admin/logs/' . $table,
            'columns' => $this->columnLabels($log['columns']),
            'dateColumns' => $this->dateColumns($log['columns']),
            'rows' => $missing ? [] : $this->logs->listForAdmin($table, $page, self::PER_PAGE, $filters),
            'missingTable' => $missing,
            'query' => $filters['q'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'hasDateFilter' => $log['dateColumn'] !== null,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    private function connections(): Response
    {
        $filters = $this->filters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $missing = !$this->logs->tableExists('loginlog2');
        $total = $missing ? 0 : $this->logs->countConnectionIps($filters);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->adminView('log-' . LogCatalog::CONNECTIONS_ID, 'pages/account-ips.twig', [
            'title' => $this->t('admin.logs.connections_title'),
            'pageLead' => $this->t('admin.logs.connections_lead'),
            'logPath' => '/admin/logs/' . LogCatalog::CONNECTIONS_ID,
            'rows' => $missing ? [] : $this->logs->listConnectionIps($page, self::PER_PAGE, $filters),
            'missingTable' => $missing,
            'query' => $filters['q'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @return array{q: string, from: string, to: string}
     */
    private function filters(): array
    {
        return [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<array{key: string, label: string}>
     */
    private function columnLabels(array $columns): array
    {
        $labels = [];

        foreach ($columns as $column) {
            $key = 'admin.logs.columns.' . $column;
            $labels[] = [
                'key' => $column,
                'label' => $this->translator->has($key) ? $this->t($key) : $column,
            ];
        }

        return $labels;
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
