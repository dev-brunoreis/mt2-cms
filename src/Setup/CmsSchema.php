<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

use Mt2Cms\Model\Database;

class CmsSchema
{
    public function __construct(private Database $db)
    {
    }

    public function ensure(): void
    {
        $this->db->useDatabase('cms');

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS admins (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                login VARCHAR(64) NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_login (login)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(64) NOT NULL,
                setting_value TEXT NOT NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /**
     * @param array<string, string> $defaults
     */
    public function seedDefaults(array $defaults): void
    {
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
