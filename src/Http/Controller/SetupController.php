<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Request;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Model\Database;
use Mt2Cms\Model\Env;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Setup\CmsSchema;
use Mt2Cms\Setup\EnvWriter;
use Mt2Cms\Setup\ThemeCatalog;
use Mt2Cms\Support\AppCrypto;
use Mt2Cms\Support\Log;
use Mt2Cms\Theme\ThemeEngine;

class SetupController extends Controller
{
    private const SESSION_KEY = '_setup';

    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private ThemeCatalog $themes,
        private EnvWriter $envWriter,
        private bool $adminRecoveryOnly = false,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
    }

    public function show(): Response
    {
        if ($this->adminRecoveryOnly) {
            return $this->setupView('admin');
        }

        $step = $this->currentStep();

        if ($step === 'admin' && !$this->hasSetupSession()) {
            return $this->redirect('/setup?step=db');
        }

        return $this->setupView($step);
    }

    public function submit(): Response
    {
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

        if ($step === 'db') {
            return $this->submitDatabaseStep($bucket);
        }

        return $this->submitAdminStep($bucket);
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

        if (!Database::testConnection([
            'host' => $cmsHost,
            'port' => $cmsPort,
            'user' => $cmsUser,
            'password' => $cmsPassword,
            'database' => 'cms',
        ])) {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->dbConnectionError('setup.cms_db_connection_failed'), status: 422);
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

        $this->rateLimiter->clear($bucket);

        return $this->redirect('/setup?step=admin');
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
            } else {
                /** @var array<string, mixed> $setup */
                $setup = $_SESSION[self::SESSION_KEY];

                $existingKey = trim(Env::getInstance()->get('APP_KEY', ''));
                $appKey = $existingKey !== '' ? $existingKey : AppCrypto::generateKey();

                $envValues = [
                    'DB_HOST' => (string) $setup['db_host'],
                    'DB_PORT' => (string) $setup['db_port'],
                    'DB_USER' => (string) $setup['db_user'],
                    'DB_PASSWORD' => (string) $setup['db_password'],
                    'THEME' => (string) $setup['theme'],
                    'LOCALE' => 'en',
                    'APP_KEY' => $appKey,
                    'CMS_DB_HOST' => (string) $setup['cms_db_host'],
                    'CMS_DB_PORT' => (string) $setup['cms_db_port'],
                    'CMS_DB_USER' => (string) $setup['cms_db_user'],
                    'CMS_DB_PASSWORD' => (string) $setup['cms_db_password'],
                    'CMS_DB_NAME' => 'cms',
                    'APP_TRUST_PROXY' => !empty($setup['app_trust_proxy']) ? '1' : '0',
                    'APP_INSTALLED' => 'true',
                ];

                $this->envWriter->upsert($envValues, BASE_DIR . '/.env');
                Env::load();

                $cmsDb = new Database([
                    'host' => (string) $setup['cms_db_host'],
                    'port' => (string) $setup['cms_db_port'],
                    'user' => (string) $setup['cms_db_user'],
                    'password' => (string) $setup['cms_db_password'],
                    'database' => 'cms',
                    'requirePassword' => false,
                ]);

                $schema = new CmsSchema($cmsDb);
                $schema->ensure();
                $schema->seedDefaults(array_merge([
                    'registration_enabled' => '1',
                    'available_themes' => json_encode([(string) $setup['theme']], JSON_THROW_ON_ERROR),
                    'active_theme' => (string) $setup['theme'],
                    'default_locale' => 'en',
                    'news_comments_enabled' => '1',
                    'news_comments_require_approval' => '0',
                ], CmsSchema::defaultSecuritySettings()));
            }

            $admins = new AdminRepository($cmsDb);
            $admins->create($login, $password);

            unset($_SESSION[self::SESSION_KEY]);
            $this->rateLimiter->clear($bucket);

            $adminAuth = new \Mt2Cms\Auth\AdminAuth($admins);
            $adminAuth->attempt($login, $password);

            return $this->redirect('/admin');
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

        return $this->view('setup', [
            'title' => $this->t('setup.title'),
            'step' => $step,
            'error' => $error,
            'adminRecoveryOnly' => $this->adminRecoveryOnly,
            'themes' => $this->themes->available(),
            'values' => [
                'db_host' => (string) ($setup['db_host'] ?? $env->get('DB_HOST', 'game')),
                'db_port' => (string) ($setup['db_port'] ?? $env->get('DB_PORT', '3306')),
                'db_user' => (string) ($setup['db_user'] ?? $env->get('DB_USER', 'root')),
                'db_password' => (string) ($setup['db_password'] ?? ''),
                'cms_db_host' => (string) ($setup['cms_db_host'] ?? $env->get('CMS_DB_HOST', 'mysql')),
                'cms_db_port' => (string) ($setup['cms_db_port'] ?? $env->get('CMS_DB_PORT', '3306')),
                'cms_db_user' => (string) ($setup['cms_db_user'] ?? $env->get('CMS_DB_USER', 'cms')),
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

        $step = trim((string) ($_GET['step'] ?? 'db'));

        return $step === 'admin' ? 'admin' : 'db';
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
