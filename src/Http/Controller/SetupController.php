<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Model\Database;
use Mt2Cms\Model\Env;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Setup\CmsSchema;
use Mt2Cms\Setup\EnvWriter;
use Mt2Cms\Setup\ThemeCatalog;
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
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
    }

    public function show(): Response
    {
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
        $theme = trim((string) ($_POST['theme'] ?? ''));

        if ($host === '' || $user === '' || $password === '') {
            $this->rateLimiter->hit($bucket);

            return $this->setupView('db', error: $this->t('setup.fields_required'), status: 422);
        }

        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
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

            return $this->setupView('db', error: $this->t('setup.db_connection_failed'), status: 422);
        }

        $_SESSION[self::SESSION_KEY] = [
            'db_host' => $host,
            'db_port' => $port,
            'db_user' => $user,
            'db_password' => $password,
            'theme' => $theme,
        ];

        $this->rateLimiter->clear($bucket);

        return $this->redirect('/setup?step=admin');
    }

    private function submitAdminStep(string $bucket): Response
    {
        if (!$this->hasSetupSession()) {
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

        /** @var array<string, string> $setup */
        $setup = $_SESSION[self::SESSION_KEY];

        try {
            $envValues = [
                'DB_HOST' => $setup['db_host'],
                'DB_PORT' => $setup['db_port'],
                'DB_USER' => $setup['db_user'],
                'DB_PASSWORD' => $setup['db_password'],
                'THEME' => $setup['theme'],
                'LOCALE' => 'en',
                'CMS_DB_HOST' => 'mysql',
                'CMS_DB_PORT' => $setup['db_port'],
                'CMS_DB_USER' => $setup['db_user'],
                'CMS_DB_PASSWORD' => $setup['db_password'],
                'CMS_DB_NAME' => 'cms',
                'APP_INSTALLED' => 'true',
            ];

            $this->envWriter->write($envValues, BASE_DIR . '/.env');
            Env::load();

            $cmsDb = new Database([
                'host' => 'mysql',
                'port' => $setup['db_port'],
                'user' => $setup['db_user'],
                'password' => $setup['db_password'],
                'database' => 'cms',
                'requirePassword' => false,
            ]);

            $schema = new CmsSchema($cmsDb);
            $schema->ensure();
            $schema->seedDefaults([
                'registration_enabled' => '1',
                'available_themes' => json_encode([$setup['theme']], JSON_THROW_ON_ERROR),
                'active_theme' => $setup['theme'],
                'default_locale' => 'en',
            ]);

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
            'themes' => $this->themes->available(),
            'values' => [
                'db_host' => (string) ($setup['db_host'] ?? $env->get('DB_HOST', 'game')),
                'db_port' => (string) ($setup['db_port'] ?? $env->get('DB_PORT', '3306')),
                'db_user' => (string) ($setup['db_user'] ?? $env->get('DB_USER', 'root')),
                'db_password' => (string) ($setup['db_password'] ?? $env->get('DB_PASSWORD', '')),
                'theme' => (string) ($setup['theme'] ?? $env->get('THEME', 'default')),
            ],
        ], $status);
    }

    private function currentStep(): string
    {
        $step = trim((string) ($_GET['step'] ?? 'db'));

        return $step === 'admin' ? 'admin' : 'db';
    }

    private function hasSetupSession(): bool
    {
        $setup = $_SESSION[self::SESSION_KEY] ?? null;

        return is_array($setup)
            && isset($setup['db_host'], $setup['db_port'], $setup['db_user'], $setup['db_password'], $setup['theme']);
    }

    private function authBucket(string $action): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return $action . ':' . $ip;
    }
}
