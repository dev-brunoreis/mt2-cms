<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Support\AppCrypto;
use Mt2Cms\Support\Database;
use Mt2Cms\Support\Env;

/**
 * Persists .env + CMS schema during the wizard and decides whether an admin must be created.
 */
final class SetupInstaller
{
    public const COMPLETE_SESSION_KEY = '_setup_complete';

    public function __construct(private EnvWriter $envWriter)
    {
    }

    /**
     * Write credentials (APP_INSTALLED=false), ensure schema/seeds, return admin count.
     *
     * @param array{
     *     db_host: string,
     *     db_port: string,
     *     db_user: string,
     *     db_password: string,
     *     cms_db_host: string,
     *     cms_db_port: string,
     *     cms_db_user: string,
     *     cms_db_password: string,
     *     theme: string,
     *     app_trust_proxy?: bool
     * } $setup
     * @return array{cmsDb: Database, adminCount: int}
     */
    public function persistAndPrepare(array $setup, string $envPath): array
    {
        $existingKey = trim(Env::getInstance()->get('APP_KEY', ''));
        $appKey = $existingKey !== '' ? $existingKey : AppCrypto::generateKey();

        $this->envWriter->upsert([
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
            'APP_INSTALLED' => 'false',
        ], $envPath);
        Env::load();

        $cmsDb = $this->cmsDatabaseFromSetup($setup);
        $schema = new CmsSchema($cmsDb);
        $schema->ensure();
        $schema->seedDefaults(array_merge([
            'registration_enabled' => '1',
            'active_theme' => (string) $setup['theme'],
            'layout_columns' => '3',
            'layout_sidebar' => 'left',
            'default_locale' => 'en',
            'news_comments_enabled' => '1',
            'news_comments_require_approval' => '0',
        ], CmsSchema::defaultSecuritySettings()));

        return [
            'cmsDb' => $cmsDb,
            'adminCount' => (new AdminRepository($cmsDb))->count(),
        ];
    }

    /**
     * @param array{
     *     cms_db_host: string,
     *     cms_db_port: string,
     *     cms_db_user: string,
     *     cms_db_password: string
     * } $setup
     */
    public function cmsDatabaseFromSetup(array $setup): Database
    {
        return new Database([
            'host' => (string) $setup['cms_db_host'],
            'port' => (string) $setup['cms_db_port'],
            'user' => (string) $setup['cms_db_user'],
            'password' => (string) $setup['cms_db_password'],
            'database' => 'cms',
            'requirePassword' => false,
        ]);
    }

    public function markInstalled(string $envPath): void
    {
        $this->envWriter->upsert(['APP_INSTALLED' => 'true'], $envPath);
        Env::load();
    }

    public function nextStepAfterPrepare(int $adminCount): string
    {
        return $adminCount > 0 ? 'done' : 'admin';
    }

    /**
     * @param array{existing_admins?: bool} $meta
     */
    public function markCompleteInSession(array $meta = []): void
    {
        $_SESSION[self::COMPLETE_SESSION_KEY] = [
            'existing_admins' => !empty($meta['existing_admins']),
        ];
    }

    public static function hasCompleteSession(): bool
    {
        return isset($_SESSION[self::COMPLETE_SESSION_KEY])
            && is_array($_SESSION[self::COMPLETE_SESSION_KEY]);
    }

    /**
     * @return array{existing_admins: bool}|null
     */
    public static function peekCompleteSession(): ?array
    {
        if (!self::hasCompleteSession()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $_SESSION[self::COMPLETE_SESSION_KEY];

        return [
            'existing_admins' => !empty($data['existing_admins']),
        ];
    }

    public static function clearCompleteSession(): void
    {
        unset($_SESSION[self::COMPLETE_SESSION_KEY]);
    }
}
