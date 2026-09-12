CREATE TABLE IF NOT EXISTS cms_notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,
    ref VARCHAR(64) NOT NULL DEFAULT '',
    payload TEXT NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_cms_notifications_ref (account_id, type, ref),
    KEY idx_cms_notifications_account (account_id, read_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cms_payments
    MODIFY COLUMN status ENUM('pending', 'paid', 'failed', 'refunded', 'expired') NOT NULL DEFAULT 'pending';

INSERT INTO settings (setting_key, setting_value)
VALUES ('paypal_pending_minutes', '30')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
