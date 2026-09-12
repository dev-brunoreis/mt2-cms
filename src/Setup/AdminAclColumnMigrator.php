<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

use Mt2Cms\Support\Database;

/**
 * Idempotent ALTERs for admins.role / use_custom_acl (legacy installs).
 * MySQL 8.0 in Docker does not accept ADD COLUMN IF NOT EXISTS on all builds.
 */
final class AdminAclColumnMigrator
{
    public function __construct(private Database $db)
    {
    }

    public function migrateIfNeeded(): void
    {
        $this->db->useDatabase('cms');
        $this->ensureRoleColumn();
        $this->ensureRoleVarcharColumn();
        $this->ensureUseCustomAclColumn();
    }

    private function ensureRoleColumn(): void
    {
        if ($this->columnExists('admins', 'role')) {
            return;
        }

        $this->db->execute(
            "ALTER TABLE admins
             ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'super' AFTER password",
        );
    }

    private function ensureRoleVarcharColumn(): void
    {
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

    private function ensureUseCustomAclColumn(): void
    {
        if ($this->columnExists('admins', 'use_custom_acl')) {
            return;
        }

        $this->db->execute(
            'ALTER TABLE admins
             ADD COLUMN use_custom_acl TINYINT(1) NOT NULL DEFAULT 0 AFTER role',
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->fetch(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            [$table, $column],
        ) !== null;
    }
}
