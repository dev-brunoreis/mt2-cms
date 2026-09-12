CREATE TABLE IF NOT EXISTS item_census (
    vnum INT UNSIGNED NOT NULL,
    units BIGINT UNSIGNED NOT NULL DEFAULT 0,
    stacks INT UNSIGNED NOT NULL DEFAULT 0,
    holders_players INT UNSIGNED NOT NULL DEFAULT 0,
    holders_accounts INT UNSIGNED NOT NULL DEFAULT 0,
    units_player BIGINT UNSIGNED NOT NULL DEFAULT 0,
    units_safebox BIGINT UNSIGNED NOT NULL DEFAULT 0,
    units_mall BIGINT UNSIGNED NOT NULL DEFAULT 0,
    units_pending BIGINT UNSIGNED NOT NULL DEFAULT 0,
    stacks_pending INT UNSIGNED NOT NULL DEFAULT 0,
    captured_at DATETIME NOT NULL,
    PRIMARY KEY (vnum),
    KEY idx_item_census_units (units),
    KEY idx_item_census_captured (captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_census_daily (
    day DATE NOT NULL,
    vnum INT UNSIGNED NOT NULL,
    units BIGINT UNSIGNED NOT NULL DEFAULT 0,
    stacks INT UNSIGNED NOT NULL DEFAULT 0,
    holders_players INT UNSIGNED NOT NULL DEFAULT 0,
    holders_accounts INT UNSIGNED NOT NULL DEFAULT 0,
    units_player BIGINT UNSIGNED NOT NULL DEFAULT 0,
    units_safebox BIGINT UNSIGNED NOT NULL DEFAULT 0,
    units_mall BIGINT UNSIGNED NOT NULL DEFAULT 0,
    units_pending BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, vnum),
    KEY idx_item_census_daily_vnum (vnum, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economy_yang_daily (
    day DATE NOT NULL,
    player_yang BIGINT NOT NULL DEFAULT 0,
    safebox_yang BIGINT NOT NULL DEFAULT 0,
    guild_yang BIGINT NOT NULL DEFAULT 0,
    money_monster BIGINT NOT NULL DEFAULT 0,
    money_drop BIGINT NOT NULL DEFAULT 0,
    money_shop BIGINT NOT NULL DEFAULT 0,
    money_refine BIGINT NOT NULL DEFAULT 0,
    money_quest BIGINT NOT NULL DEFAULT 0,
    money_guild BIGINT NOT NULL DEFAULT 0,
    money_misc BIGINT NOT NULL DEFAULT 0,
    money_kill BIGINT NOT NULL DEFAULT 0,
    captured_at DATETIME NOT NULL,
    PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_market_trades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trade_key CHAR(40) NOT NULL,
    vnum INT UNSIGNED NOT NULL,
    count INT UNSIGNED NOT NULL DEFAULT 1,
    price_yang BIGINT NOT NULL,
    unit_price BIGINT NOT NULL,
    sold_at DATETIME NOT NULL,
    source VARCHAR(16) NOT NULL DEFAULT 'goldlog',
    PRIMARY KEY (id),
    UNIQUE KEY uniq_item_market_trades_key (trade_key),
    KEY idx_item_market_trades_vnum_sold (vnum, sold_at),
    KEY idx_item_market_trades_sold (sold_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_market_daily (
    day DATE NOT NULL,
    vnum INT UNSIGNED NOT NULL,
    trades INT UNSIGNED NOT NULL DEFAULT 0,
    units INT UNSIGNED NOT NULL DEFAULT 0,
    median_price BIGINT UNSIGNED NULL,
    p25_price BIGINT UNSIGNED NULL,
    p75_price BIGINT UNSIGNED NULL,
    source VARCHAR(16) NOT NULL DEFAULT 'goldlog',
    PRIMARY KEY (day, vnum),
    KEY idx_item_market_daily_vnum (vnum, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economy_watchlist (
    vnum INT UNSIGNED NOT NULL,
    crash_pct SMALLINT UNSIGNED NULL,
    spike_pct SMALLINT UNSIGNED NULL,
    min_sample SMALLINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (vnum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economy_alerts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    day DATE NOT NULL,
    vnum INT UNSIGNED NOT NULL,
    kind VARCHAR(32) NOT NULL,
    payload TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_economy_alerts_day_vnum_kind (day, vnum, kind),
    KEY idx_economy_alerts_unacked (acked_at, created_at),
    KEY idx_economy_alerts_vnum (vnum, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economy_tick_state (
    state_key VARCHAR(64) NOT NULL,
    state_value VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (state_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
