<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Request;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Support\Database;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Setup\CmsSchema;
use Mt2Cms\Setup\EnvWriter;
use Mt2Cms\Setup\SetupDatabaseDefaults;
use Mt2Cms\Setup\SetupInstaller;
use Mt2Cms\Setup\SetupRequirements;
use Mt2Cms\Setup\ThemeCatalog;
use Mt2Cms\Support\Env;
use Mt2Cms\Support\Log;
use Mt2Cms\Theme\ThemeEngine;

class SetupController extends Controller
{
    private const SESSION_KEY = '_setup';

    private RateLimiter $rateLimiter;
    private SetupInstaller $installer;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private ThemeCatalog $themes,
        private EnvWriter $envWriter,
        private bool $adminRecoveryOnly = false,
        ?RateLimiter $rateLimiter = null,
        private ?SetupRequirements $requirements = null,
        ?SetupInstaller $installer = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
        $this->requirements ??= new SetupRequirements(BASE_DIR);
        $this->installer = $installer ?? new SetupInstaller($this->envWriter);
    }

    public function show(): Response
    {
        if (SetupInstaller::hasCompleteSession()) {
            return $this->setupView('done');
        }

        if ($this->adminRecoveryOnly) {
            return $this->setupView('admin');
        }

        $step = $this->currentStep();

        if ($step === 'done') {
            return $this->redirect('/setup');
        }

        if ($step !== 'requirements' && !$this->requirements->allRequiredOk()) {
            return $this->redirect('/setup?step=requirements');
        }

        if ($step === 'admin' && !$this->hasSetupSession()) {
            return $this->redirect('/setup?step=db');
        }

        return $this->setupView($step);
    }

    public function submit(): Response
    {
        if (SetupInstaller::hasCompleteSession()) {
            return $this->redirect('/');
        }

        if (!$this->assertCsrf()) {
            return $this->setupView($this->currentStep(), error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $bucket = $this->authBucket('setup');

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->setupView($this->currentStep(), error: $this->t('auth.too_many_attempts'), status: 429);
        }

        if ($this->adminRecoveryOnly) {
            return $this->submitAdminStep($bucket, recovery: true);
        }

        $step = $this->currentStep();

        if ($step === 'requirements') {
            return $this->submitRequirementsStep($bucket);
        }

        if (!$this->requirements->allRequiredOk()) {
            $this->rateLimiter->hit($bucket);

            return $this->redirect('/setup?step=requirements');
        }

        if ($step === 'db') {
            return $this->submitDatabaseStep($bucket);
        }

        if ($step === 'admin') {
            $action = trim((string) ($_POST['setup_action'] ?? 'create'));

            if ($action === 'skip') {
                return $this->submitSkipAdminStep($bucket);
            }

            return $this->submitAdminStep($bucket);
        }

        return $this->redirect('/setup');
    }

    public function testConnection(): Response
    {
        if ($this->adminRecoveryOnly || SetupInstaller::hasCompleteSession()) {
            return Response::json(['ok' => false, 'message' => $this->t('http.not_found')], 404);
        }

        if (!$this->assertCsrf()) {
            return Response::json(['ok' => false, 'message' => $this->t('auth.invalid_csrf')], 400);
        }

        $bucket = $this->authBucket('setup');

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return Response::json(['ok' => false, 'message' => $this->t('auth.too_many_attempts')], 429);
        }

        if (!$this->requirements->allRequiredOk()) {
            $this->rateLimiter->hit($bucket);

            return Response::json(['ok' => false, 'message' => $this->t('setup.requirements_failed')], 422);
        }

        $target = trim((string) ($_POST['target'] ?? ''));

        if ($target !== 'game' && $target !== 'cms') {
            $this->rateLimiter->hit($bucket);

            return Response::json(['ok' => false, 'message' => $this->t('setup.fields_required')], 422);
        }

        $prefix = $target === 'game' ? 'db' : 'cms_db';
        $host = trim((string) ($_POST[$prefix . '_host'] ?? ''));
        $port = trim((string) ($_POST[$prefix . '_port'] ?? '3306'));
        $user = trim((string) ($_POST[$prefix . '_user'] ?? ''));
        $password = (string) ($_POST[$prefix . '_password'] ?? '');

        if ($host === '' || $user === '' || $password === '') {
            $this->rateLimiter->hit($bucket);

            return Response::json(['ok' => false, 'message' => $this->t('setup.fields_required')], 422);
        }

        if (!$this->isValidPort($port)) {
            $this->rateLimiter->hit($bucket);

            return Response::json(['ok' => false, 'message' => $this->t('setup.invalid_port')], 422);
        }

        $inContainer = SetupDatabaseDefaults::inContainer();
        $mapped = SetupDatabaseDefaults::forConnection(
            $host,
            $port,
            $target === 'game' ? 'game' : 'mysql',
            $target === 'game' ? '8001' : '8002',
            $inContainer,
        );
        $host = Database::tcpHost($mapped['host'], $mapped['port']);
        $port = $mapped['port'];

        if ($target === 'game') {
            if (!Database::testConnection([
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'password' => $password,
            ])) {
                $this->rateLimiter->hit($bucket);

                return Response::json([
                    'ok' => false,
                    'message' => $this->dbConnectionError('setup.db_connection_failed'),
                ], 422);
            }

            if (!Database::testGameSchema([
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'password' => $password,
            ])) {
                $this->rateLimiter->hit($bucket);

                return Response::json([
                    'ok' => false,
                    'message' => $this->t('setup.game_schema_missing'),
                ], 422);
            }

            $this->rateLimiter->clear($bucket);

            return Response::json(['ok' => true, 'message' => $this->t('setup.test_game_ok')]);
        }

        $cmsConfig = [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
        ];

        $cmsBlock = Database::cmsServerBlockReason($cmsConfig);

        if ($cmsBlock !== null) {
            $this->rateLimiter->hit($bucket);

            return Response::json([
                'ok' => false,
                'message' => $cmsBlock === 'setup.cms_db_connection_failed'
                    ? $this->dbConnectionError($cmsBlock)
                    : $this->t($cmsBlock),
            ], 422);
        }

        if (!Database::testConnection($cmsConfig + ['database' => 'cms'])
            && !Database::ensureCmsSchema($cmsConfig)
        ) {
            $this->rateLimiter->hit($bucket);

            return Response::json([
                'ok' => false,
                'message' => $this->t('setup.cms_schema_create_failed'),
            ], 422);
        }

        $this->rateLimiter->clear($bucket);

        return Response::json(['ok' => true, 'message' => $this->t('setup.test_cms_ok')]);
    }

    private function submitRequirementsStep(string $bucket): Response
    {
        if (!$this->requirements->allRequiredOk()) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('requirements', error: $this->t('setup.requirements_failed'), status: 422);
        }

        $this->rateLimiter->clear($bucket);

        return $this->redirect('/setup?step=db');
    }

    private function submitDatabaseStep(string $bucket): Response
    {
        $host = trim((string) ($_POST['db_host'] ?? ''));
        $port = trim((string) ($_POST['db_port'] ?? '3306'));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $password = (string) ($_POST['db_password'] ?? '');
        $cmsHost = trim((string) ($_POST['cms_db_host'] ?? ''));
        $cmsPort = trim((string) ($_POST['cms_db_port'] ?? '3306'));
        $cmsUser = trim((string) ($_POST['cms_db_user'] ?? ''));
        $cmsPassword = (string) ($_POST['cms_db_password'] ?? '');
        $theme = trim((string) ($_POST['theme'] ?? ''));
        $trustProxy = isset($_POST['app_trust_proxy']);

        if ($host === '' || $user === '' || $password === '' || $cmsHost === '' || $cmsUser === '' || $cmsPassword === '') {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->t('setup.fields_required'), status: 422);
        }

        if (!$this->isValidPort($port) || !$this->isValidPort($cmsPort)) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->t('setup.invalid_port'), status: 422);
        }

        if (!$this->themes->isValid($theme)) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->t('setup.invalid_theme'), status: 422);
        }

        $inContainer = SetupDatabaseDefaults::inContainer();
        $game = SetupDatabaseDefaults::forConnection($host, $port, 'game', '8001', $inContainer);
        $cms = SetupDatabaseDefaults::forConnection($cmsHost, $cmsPort, 'mysql', '8002', $inContainer);
        $host = Database::tcpHost($game['host'], $game['port']);
        $port = $game['port'];
        $cmsHost = Database::tcpHost($cms['host'], $cms['port']);
        $cmsPort = $cms['port'];

        if (!Database::testConnection([
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
        ])) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->dbConnectionError('setup.db_connection_failed'), status: 422);
        }

        if (!Database::testGameSchema([
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
        ])) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->t('setup.game_schema_missing'), status: 422);
        }

        $cmsConfig = [
            'host' => $cmsHost,
            'port' => $cmsPort,
            'user' => $cmsUser,
            'password' => $cmsPassword,
        ];

        $cmsBlock = Database::cmsServerBlockReason($cmsConfig);

        if ($cmsBlock !== null) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView(
                'db',
                error: $cmsBlock === 'setup.cms_db_connection_failed'
                    ? $this->dbConnectionError($cmsBlock)
                    : $this->t($cmsBlock),
                status: 422,
            );
        }

        if (!Database::testConnection($cmsConfig + ['database' => 'cms'])
            && !Database::ensureCmsSchema($cmsConfig)
        ) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->t('setup.cms_schema_create_failed'), status: 422);
        }

        $_SESSION[self::SESSION_KEY] = [
            'db_host' => $host,
            'db_port' => $port,
            'db_user' => $user,
            'db_password' => $password,
            'cms_db_host' => $cmsHost,
            'cms_db_port' => $cmsPort,
            'cms_db_user' => $cmsUser,
            'cms_db_password' => $cmsPassword,
            'theme' => $theme,
            'app_trust_proxy' => $trustProxy,
        ];

        try {
            /** @var array{
             *     db_host: string,
             *     db_port: string,
             *     db_user: string,
             *     db_password: string,
             *     cms_db_host: string,
             *     cms_db_port: string,
             *     cms_db_user: string,
             *     cms_db_password: string,
             *     theme: string,
             *     app_trust_proxy: bool
             * } $setup
             */
            $setup = $_SESSION[self::SESSION_KEY];
            $prepared = $this->installer->persistAndPrepare($setup, BASE_DIR . '/.env');
            $next = $this->installer->nextStepAfterPrepare($prepared['adminCount']);

            $this->rateLimiter->clear($bucket);

            if ($next === 'done') {
                $this->installer->markInstalled(BASE_DIR . '/.env');
                unset($_SESSION[self::SESSION_KEY]);
                $this->installer->markCompleteInSession(['existing_admins' => true]);

                return $this->redirect('/setup?step=done');
            }

            return $this->redirect('/setup?step=admin');
        } catch (\PDOException $e) {
            $this->rateLimiter->hit($bucket);
            Log::error('setup', 'Install step failed', $e);

            $message = str_contains($e->getMessage(), 'JSON')
                ? $this->t('setup.cms_requires_mysql8')
                : $this->t('setup.install_failed');

            return $this->setupView('db', error: $message, status: 422);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->rateLimiter->hit($bucket);
            Log::error('setup', 'Install step failed', $e);

            $message = $this->t($e->getMessage());

            if ($message === $e->getMessage()) {
                $message = $this->t('setup.install_failed');
            }

            return $this->setupView('db', error: $message, status: 422);
        } catch (\Throwable $e) {
            $this->rateLimiter->hit($bucket);
            Log::error('setup', 'Install step failed', $e);

            return $this->setupView('db', error: $this->t('setup.install_failed'), status: 500);
        }
    }

    private function submitSkipAdminStep(string $bucket): Response
    {
        if (!$this->hasSetupSession()) {
            return $this->redirect('/setup?step=db');
        }

        try {
            /** @var array{
             *     cms_db_host: string,
             *     cms_db_port: string,
             *     cms_db_user: string,
             *     cms_db_password: string
             * } $setup
             */
            $setup = $_SESSION[self::SESSION_KEY];
            $cmsDb = $this->installer->cmsDatabaseFromSetup($setup);
            $adminCount = (new AdminRepository($cmsDb))->count();

            if ($adminCount < 1) {
                $this->rateLimiter->hit($bucket);

                return $this->setupView('admin', error: $this->t('setup.admin_required'), status: 422);
            }

            $this->installer->markInstalled(BASE_DIR . '/.env');
            unset($_SESSION[self::SESSION_KEY]);
            $this->installer->markCompleteInSession(['existing_admins' => true]);
            $this->rateLimiter->clear($bucket);

            return $this->redirect('/setup?step=done');
        } catch (\Throwable $e) {
            $this->rateLimiter->hit($bucket);
            Log::error('setup', 'Install step failed', $e);

            return $this->setupView('admin', error: $this->t('setup.install_failed'), status: 500);
        }
    }

    private function submitAdminStep(string $bucket, bool $recovery = false): Response
    {
        if (!$recovery && !$this->hasSetupSession()) {
            return $this->redirect('/setup?step=db');
        }

        $login = trim((string) ($_POST['admin_login'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');
        $confirm = (string) ($_POST['admin_password_confirm'] ?? '');

        if ($login === '' || $password === '') {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('admin', error: $this->t('setup.fields_required'), status: 422);
        }

        if (strlen($password) < 8) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('admin', error: $this->t('admin.invalid_password'), status: 422);
        }

        if (!hash_equals($password, $confirm)) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('admin', error: $this->t('setup.password_mismatch'), status: 422);
        }

        try {
            if ($recovery) {
                $cmsDb = Database::forCms();
                $schema = new CmsSchema($cmsDb);
                $schema->ensure();
            } else {
                /** @var array{
                 *     cms_db_host: string,
                 *     cms_db_port: string,
                 *     cms_db_user: string,
                 *     cms_db_password: string
                 * } $setup
                 */
                $setup = $_SESSION[self::SESSION_KEY];
                $cmsDb = $this->installer->cmsDatabaseFromSetup($setup);
            }

            $admins = new AdminRepository($cmsDb);
            $admins->create($login, $password);

            if (!$recovery) {
                $this->installer->markInstalled(BASE_DIR . '/.env');
            }

            unset($_SESSION[self::SESSION_KEY]);
            $this->installer->markCompleteInSession(['existing_admins' => false]);
            $this->rateLimiter->clear($bucket);

            return $this->redirect('/setup?step=done');
        } catch (\PDOException $e) {
            $this->rateLimiter->hit($bucket);
            Log::error('setup', 'Install step failed', $e);

            $message = str_contains($e->getMessage(), 'JSON')
                ? $this->t('setup.cms_requires_mysql8')
                : $this->t('setup.install_failed');

            return $this->setupView('admin', error: $message, status: 422);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->rateLimiter->hit($bucket);
            Log::error('setup', 'Install step failed', $e);

            $message = $this->t($e->getMessage());

            if ($message === $e->getMessage()) {
                $message = $this->t('setup.install_failed');
            }

            return $this->setupView('admin', error: $message, status: 422);
        } catch (\Throwable $e) {
            $this->rateLimiter->hit($bucket);
            Log::error('setup', 'Install step failed', $e);

            return $this->setupView('admin', error: $this->t('setup.install_failed'), status: 500);
        }
    }

    private function setupView(string $step, ?string $error = null, int $status = 200): Response
    {
        $env = Env::getInstance();
        $setup = $_SESSION[self::SESSION_KEY] ?? [];
        $defaults = SetupDatabaseDefaults::formValues([
            'DB_HOST' => $env->get('DB_HOST'),
            'DB_PORT' => $env->get('DB_PORT'),
            'DB_USER' => $env->get('DB_USER'),
            'CMS_DB_HOST' => $env->get('CMS_DB_HOST'),
            'CMS_DB_PORT' => $env->get('CMS_DB_PORT'),
            'CMS_DB_USER' => $env->get('CMS_DB_USER'),
        ], SetupDatabaseDefaults::inContainer());

        $requirementRows = [];

        if ($step === 'requirements') {
            foreach ($this->requirements->checks() as $check) {
                $requirementRows[] = [
                    'id' => $check['id'],
                    'label' => $this->t($check['labelKey']),
                    'ok' => $check['ok'],
                    'required' => $check['required'],
                    'detail' => $check['detail'] ?? null,
                ];
            }
        }

        $complete = SetupInstaller::peekCompleteSession();
        $existingAdmins = $complete['existing_admins'] ?? false;

        return $this->view('setup', [
            'title' => $this->t('setup.title'),
            'step' => $step,
            'error' => $error,
            'adminRecoveryOnly' => $this->adminRecoveryOnly,
            'existingAdmins' => $existingAdmins,
            'themes' => $this->themes->available(),
            'requirements' => $requirementRows,
            'requirementsOk' => $this->requirements->allRequiredOk(),
            'values' => [
                'db_host' => (string) ($setup['db_host'] ?? $defaults['db_host']),
                'db_port' => (string) ($setup['db_port'] ?? $defaults['db_port']),
                'db_user' => (string) ($setup['db_user'] ?? $defaults['db_user']),
                'db_password' => (string) ($setup['db_password'] ?? ''),
                'cms_db_host' => (string) ($setup['cms_db_host'] ?? $defaults['cms_db_host']),
                'cms_db_port' => (string) ($setup['cms_db_port'] ?? $defaults['cms_db_port']),
                'cms_db_user' => (string) ($setup['cms_db_user'] ?? $defaults['cms_db_user']),
                'cms_db_password' => (string) ($setup['cms_db_password'] ?? ''),
                'theme' => (string) ($setup['theme'] ?? $env->get('THEME', 'default')),
                'app_trust_proxy' => (bool) ($setup['app_trust_proxy'] ?? $env->get('APP_TRUST_PROXY', '0') === '1'),
            ],
        ], $status);
    }

    private function currentStep(): string
    {
        if ($this->adminRecoveryOnly) {
            return 'admin';
        }

        if (SetupInstaller::hasCompleteSession()) {
            return 'done';
        }

        $step = trim((string) ($_GET['step'] ?? 'requirements'));

        return match ($step) {
            'db' => 'db',
            'admin' => 'admin',
            'done' => 'done',
            default => 'requirements',
        };
    }

    private function hasSetupSession(): bool
    {
        $setup = $_SESSION[self::SESSION_KEY] ?? null;

        return is_array($setup)
            && isset(
                $setup['db_host'],
                $setup['db_port'],
                $setup['db_user'],
                $setup['db_password'],
                $setup['cms_db_host'],
                $setup['cms_db_port'],
                $setup['cms_db_user'],
                $setup['cms_db_password'],
                $setup['theme'],
            );
    }

    private function isValidPort(string $port): bool
    {
        return ctype_digit($port) && (int) $port >= 1 && (int) $port <= 65535;
    }

    private function authBucket(string $action): string
    {
        return $action . ':' . Request::clientIp();
    }

    private function dbConnectionError(string $messageKey): string
    {
        return $this->t($messageKey) . ' ' . $this->t('setup.db_password_hint');
    }
}
