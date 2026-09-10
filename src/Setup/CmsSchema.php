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
        $this->ensureItemShopCategoryParentColumn();
        $this->ensureAdminRoleColumn();
        $this->ensureAdminRoleVarcharColumn();
        $this->ensureAdminUseCustomAclColumn();
        (new AdminRoleRepository($this->db))->seedDefaults();
    }

    private function ensureAdminRoleColumn(): void
    {
        $this->db->useDatabase('cms');

        $column = $this->db->fetch(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['admins', 'role'],
        );

        if ($column !== null) {
            return;
        }

        $this->db->execute(
            "ALTER TABLE admins
             ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'super' AFTER password",
        );
    }

    private function ensureAdminRoleVarcharColumn(): void
    {
        $this->db->useDatabase('cms');

        $column = $this->db->fetch(
            'SELECT DATA_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['admins', 'role'],
        );

        if ($column === null || strtolower((string) ($column['DATA_TYPE'] ?? '')) === 'varchar') {
            return;
        }

        $this->db->execute(
            "ALTER TABLE admins
             MODIFY COLUMN role VARCHAR(32) NOT NULL DEFAULT 'super'",
        );
    }

    private function ensureAdminUseCustomAclColumn(): void
    {
        $this->db->useDatabase('cms');

        $column = $this->db->fetch(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['admins', 'use_custom_acl'],
        );

        if ($column !== null) {
            return;
        }

        $this->db->execute(
            'ALTER TABLE admins
             ADD COLUMN use_custom_acl TINYINT(1) NOT NULL DEFAULT 0 AFTER role',
        );
    }

    private function ensureItemShopCategoryParentColumn(): void
    {
        $this->db->useDatabase('cms');

        $column = $this->db->fetch(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['item_shop_categories', 'parent_id'],
        );

        if ($column !== null) {
            return;
        }

        $this->db->execute(
            'ALTER TABLE item_shop_categories
             ADD COLUMN parent_id INT UNSIGNED NULL AFTER id,
             ADD KEY idx_item_shop_categories_parent (parent_id, sort_order, id)',
        );

        try {
            $this->db->execute(
                'ALTER TABLE item_shop_categories
                 ADD CONSTRAINT fk_item_shop_categories_parent
                 FOREIGN KEY (parent_id) REFERENCES item_shop_categories (id) ON DELETE RESTRICT',
            );
        } catch (\Throwable) {
            // Column is enough for hierarchy; FK is best-effort on migrate.
        }
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
