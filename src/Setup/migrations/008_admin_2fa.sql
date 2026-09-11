-- Admin two-factor authentication (TOTP + recovery codes)

ALTER TABLE admins
    ADD COLUMN totp_secret VARCHAR(255) NULL DEFAULT NULL AFTER password,
    ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret;

CREATE TABLE IF NOT EXISTS admin_totp_recovery_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_totp_recovery_admin (admin_id),
    CONSTRAINT fk_admin_totp_recovery_admin
        FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
