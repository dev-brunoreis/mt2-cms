CREATE TABLE IF NOT EXISTS cms_banners (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(120) NOT NULL DEFAULT '',
    alt VARCHAR(180) NOT NULL DEFAULT '',
    link_url VARCHAR(500) NULL,
    original_path VARCHAR(255) NOT NULL,
    variants_json JSON NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_banners_enabled_sort (enabled, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('banner_interval_ms', '5500'),
    ('banner_autoplay', '1'),
    ('banner_show_dots', '1'),
    ('banner_show_arrows', '1');
