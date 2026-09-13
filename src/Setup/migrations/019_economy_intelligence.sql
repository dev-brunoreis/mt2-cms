-- Phase 1: yang created vs destroyed from money_log
ALTER TABLE economy_yang_daily
    ADD COLUMN money_created BIGINT NOT NULL DEFAULT 0 AFTER money_kill,
    ADD COLUMN money_destroyed BIGINT NOT NULL DEFAULT 0 AFTER money_created;

-- Phase 2: trade channels + parties; daily volume metrics
ALTER TABLE item_market_trades
    ADD COLUMN seller_pid INT UNSIGNED NULL AFTER source,
    ADD COLUMN buyer_pid INT UNSIGNED NULL AFTER seller_pid,
    ADD COLUMN channel VARCHAR(16) NOT NULL DEFAULT 'shop' AFTER buyer_pid,
    ADD KEY idx_item_market_trades_channel_sold (channel, sold_at),
    ADD KEY idx_item_market_trades_seller (seller_pid, sold_at),
    ADD KEY idx_item_market_trades_buyer (buyer_pid, sold_at);

ALTER TABLE item_market_daily
    ADD COLUMN volume_yang BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER units,
    ADD COLUMN unique_sellers INT UNSIGNED NOT NULL DEFAULT 0 AFTER volume_yang,
    ADD COLUMN unique_buyers INT UNSIGNED NOT NULL DEFAULT 0 AFTER unique_sellers;

ALTER TABLE item_census_daily
    ADD COLUMN units_delta BIGINT NOT NULL DEFAULT 0 AFTER units_pending;

CREATE TABLE IF NOT EXISTS economy_yang_transfers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transfer_key CHAR(40) NOT NULL,
    from_pid INT UNSIGNED NOT NULL DEFAULT 0,
    to_pid INT UNSIGNED NOT NULL DEFAULT 0,
    yang_amount BIGINT NOT NULL,
    transferred_at DATETIME NOT NULL,
    source VARCHAR(16) NOT NULL DEFAULT 'goldlog',
    PRIMARY KEY (id),
    UNIQUE KEY uniq_economy_yang_transfers_key (transfer_key),
    KEY idx_economy_yang_transfers_to (to_pid, transferred_at),
    KEY idx_economy_yang_transfers_from (from_pid, transferred_at),
    KEY idx_economy_yang_transfers_at (transferred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 3: wealth concentration snapshots
CREATE TABLE IF NOT EXISTS economy_wealth_daily (
    day DATE NOT NULL,
    player_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_yang BIGINT NOT NULL DEFAULT 0,
    top1_pct DECIMAL(6,2) NOT NULL DEFAULT 0,
    top5_pct DECIMAL(6,2) NOT NULL DEFAULT 0,
    top10_pct DECIMAL(6,2) NOT NULL DEFAULT 0,
    captured_at DATETIME NOT NULL,
    PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economy_wealth_top_daily (
    day DATE NOT NULL,
    rank_pos SMALLINT UNSIGNED NOT NULL,
    pid INT UNSIGNED NOT NULL,
    yang BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (day, rank_pos),
    KEY idx_economy_wealth_top_pid (pid, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 4: alerts for items and players
ALTER TABLE economy_alerts
    ADD COLUMN subject_type VARCHAR(16) NOT NULL DEFAULT 'item' AFTER day,
    ADD COLUMN subject_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER subject_type;

UPDATE economy_alerts SET subject_type = 'item', subject_id = vnum WHERE subject_id = 0;

ALTER TABLE economy_alerts
    DROP INDEX uniq_economy_alerts_day_vnum_kind,
    ADD UNIQUE KEY uniq_economy_alerts_day_subject_kind (day, subject_type, subject_id, kind),
    ADD KEY idx_economy_alerts_subject (subject_type, subject_id, created_at);
