CREATE TABLE IF NOT EXISTS cms_bans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    account_login VARCHAR(30) NOT NULL,
    reason VARCHAR(512) NOT NULL,
    expires_at DATETIME NULL,
    created_by_admin_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lifted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cms_bans_account (account_id, lifted_at),
    KEY idx_cms_bans_expires (expires_at, lifted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
