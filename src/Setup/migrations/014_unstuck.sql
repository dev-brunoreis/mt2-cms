CREATE TABLE IF NOT EXISTS cms_unstuck_cooldowns (
    player_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    unstuck_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id),
    KEY idx_cms_unstuck_cooldowns_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
