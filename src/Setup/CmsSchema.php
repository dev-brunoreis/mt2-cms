<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

use Mt2Cms\Model\Database;
use Mt2Cms\Repository\AdminRoleRepository;

class CmsSchema
{
    public function __construct(private Database $db)
    {
    }

    public function ensure(): void
    {
        (new MigrationRunner($this->db))->migrate();
        (new AdminAclColumnMigrator($this->db))->migrateIfNeeded();
        (new AclResourceMigrator($this->db))->migrateIfNeeded();
        (new AdminRoleRepository($this->db))->seedDefaults();
    }

    /**
     * @return array<string, string>
     */
    public static function defaultSecuritySettings(): array
    {
        return [
            'captcha_public' => '1',
            'captcha_admin' => '1',
            'admin_2fa_required' => '0',
        ];
    }

    /**
     * @param array<string, string> $defaults
     */
    public function seedDefaults(array $defaults): void
    {
        $this->db->useDatabase('cms');

        foreach ($defaults as $key => $value) {
            $this->db->execute(
                'INSERT INTO settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = setting_value',
                [$key, $value],
            );
        }
    }
}
