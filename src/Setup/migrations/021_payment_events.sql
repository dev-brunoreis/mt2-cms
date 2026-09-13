CREATE TABLE IF NOT EXISTS cms_payment_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NULL,
    provider VARCHAR(32) NOT NULL,
    provider_event_id VARCHAR(191) NOT NULL,
    event_type VARCHAR(64) NOT NULL DEFAULT '',
    headers_json TEXT NOT NULL,
    raw_body MEDIUMTEXT NOT NULL,
    status ENUM('queued', 'processing', 'processed', 'failed', 'rejected') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_error VARCHAR(255) NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_cms_payment_events_provider_event (provider, provider_event_id),
    KEY idx_cms_payment_events_queue (status, available_at, attempts),
    KEY idx_cms_payment_events_payment (payment_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
