CREATE TABLE IF NOT EXISTS cms_account_email (
    account_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    verified_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id),
    KEY idx_cms_account_email_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_email_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    type ENUM('verify', 'reset', 'change_email') NOT NULL,
    email VARCHAR(255) NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_cms_email_tokens_hash (token_hash),
    KEY idx_cms_email_tokens_account (account_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
