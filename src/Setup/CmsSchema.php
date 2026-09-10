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

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS news (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(200) NOT NULL,
                body MEDIUMTEXT NOT NULL,
                cover_image VARCHAR(255) NULL,
                author_admin_id INT UNSIGNED NOT NULL,
                author_login VARCHAR(64) NOT NULL,
                status ENUM(\'draft\', \'published\') NOT NULL DEFAULT \'draft\',
                comments_enabled TINYINT(1) NOT NULL DEFAULT 1,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_news_status_published (status, published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS news_comments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                news_id INT UNSIGNED NOT NULL,
                account_id INT UNSIGNED NOT NULL,
                account_login VARCHAR(64) NOT NULL,
                body TEXT NOT NULL,
                status ENUM(\'pending\', \'approved\', \'rejected\') NOT NULL DEFAULT \'pending\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_news_comments_news (news_id, status),
                KEY idx_news_comments_status (status),
                CONSTRAINT fk_news_comments_news FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS tickets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                account_id INT UNSIGNED NOT NULL,
                account_login VARCHAR(64) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                status ENUM(\'open\', \'answered\', \'closed\') NOT NULL DEFAULT \'open\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_tickets_account (account_id),
                KEY idx_tickets_status (status, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS ticket_messages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_id INT UNSIGNED NOT NULL,
                author_type ENUM(\'user\', \'admin\') NOT NULL,
                author_id INT UNSIGNED NOT NULL,
                author_login VARCHAR(64) NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ticket_messages_ticket (ticket_id, created_at),
                CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS ticket_attachments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                ticket_id INT UNSIGNED NOT NULL,
                message_id INT UNSIGNED NOT NULL,
                stored_name VARCHAR(64) NOT NULL,
                original_name VARCHAR(180) NOT NULL,
                mime VARCHAR(64) NOT NULL,
                size_bytes INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_ticket_attachment_stored (stored_name),
                KEY idx_ticket_attachments_ticket (ticket_id),
                KEY idx_ticket_attachments_message (message_id),
                CONSTRAINT fk_ticket_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
                CONSTRAINT fk_ticket_attachments_message FOREIGN KEY (message_id) REFERENCES ticket_messages (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS item_shop_categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                parent_id INT UNSIGNED NULL,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_item_shop_category_slug (slug),
                KEY idx_item_shop_categories_parent (parent_id, sort_order, id),
                KEY idx_item_shop_categories_sort (enabled, sort_order, id),
                CONSTRAINT fk_item_shop_categories_parent
                    FOREIGN KEY (parent_id) REFERENCES item_shop_categories (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->ensureItemShopCategoryParentColumn();

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS item_shop_products (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                category_id INT UNSIGNED NOT NULL,
                vnum INT UNSIGNED NOT NULL,
                count INT UNSIGNED NOT NULL DEFAULT 1,
                price INT UNSIGNED NOT NULL,
                socket0 INT NOT NULL DEFAULT 0,
                socket1 INT NOT NULL DEFAULT 0,
                socket2 INT NOT NULL DEFAULT 0,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_item_shop_products_category (category_id, enabled, sort_order, id),
                KEY idx_item_shop_products_enabled (enabled, sort_order, id),
                CONSTRAINT fk_item_shop_products_category
                    FOREIGN KEY (category_id) REFERENCES item_shop_categories (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS item_shop_orders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                account_id INT UNSIGNED NOT NULL,
                account_login VARCHAR(30) NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                vnum INT UNSIGNED NOT NULL,
                count INT UNSIGNED NOT NULL,
                price INT UNSIGNED NOT NULL,
                socket0 INT NOT NULL DEFAULT 0,
                socket1 INT NOT NULL DEFAULT 0,
                socket2 INT NOT NULL DEFAULT 0,
                item_award_id INT UNSIGNED NULL,
                cash_debited TINYINT(1) NOT NULL DEFAULT 0,
                status ENUM(\'pending\', \'completed\', \'failed\') NOT NULL DEFAULT \'pending\',
                idempotency_key VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_item_shop_order_idempotency (idempotency_key),
                KEY idx_item_shop_orders_account (account_id, created_at),
                KEY idx_item_shop_orders_status (status, created_at),
                KEY idx_item_shop_orders_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    private function ensureItemShopCategoryParentColumn(): void
    {
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

        // FK may already exist on fresh installs created with parent_id; ignore if add fails on older MySQL quirks.
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
