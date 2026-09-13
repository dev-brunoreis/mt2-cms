#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Seed fictional multi-day economy data into CMS tables so the admin
 * Economy dashboard looks like a busy live server.
 *
 * Usage:
 *   php bin/economy-seed-demo.php              # 30 days of demo data
 *   php bin/economy-seed-demo.php --days=14
 *   php bin/economy-seed-demo.php --reset      # remove previous demo rows only
 *   php bin/economy-seed-demo.php --reset --days=30
 *
 * Demo rows are tagged (source=demo / transfer_key prefix demo|) so --reset
 * does not wipe real tick-ingested data.
 */

define('BASE_DIR', dirname(__DIR__));

require BASE_DIR . '/vendor/autoload.php';

use Mt2Cms\Service\Economy\EconomyStats;
use Mt2Cms\Support\Database;

Mt2Cms\Application::loadConfigs();

$days = 30;
$reset = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--reset') {
        $reset = true;
        continue;
    }

    if (preg_match('/^--days=(\d+)$/', $arg, $m) === 1) {
        $days = max(3, min(90, (int) $m[1]));
    }
}

try {
    $cms = Database::forCms();
    $game = new Database();

    if ($reset) {
        $removed = resetDemoData($cms);
        echo "Removed demo rows: {$removed}\n";
    }

    $players = loadPlayers($game);
    $items = loadItems($cms, $game);

    if ($players === []) {
        fwrite(STDERR, "No players found in game DB — cannot seed trades.\n");
        exit(1);
    }

    if ($items === []) {
        fwrite(STDERR, "No items in item_census — run economy-tick.php first.\n");
        exit(1);
    }

    $stats = seedDemo($cms, $players, $items, $days);

    echo "Economy demo seed OK ({$days} days):\n";
    echo "  yang days:     {$stats['yang_days']}\n";
    echo "  census days:   {$stats['census_rows']}\n";
    echo "  shop trades:   {$stats['shop_trades']}\n";
    echo "  npc trades:    {$stats['npc_trades']}\n";
    echo "  transfers:     {$stats['transfers']}\n";
    echo "  market daily:  {$stats['market_daily']}\n";
    echo "  wealth days:   {$stats['wealth_days']}\n";
    echo "  alerts:        {$stats['alerts']}\n";
    echo "\nRefresh /admin/game/economy (try range=30d) and /admin/game/economy/players.\n";
    echo "To wipe demo data later: php bin/economy-seed-demo.php --reset\n";
} catch (\PDOException $e) {
    fwrite(STDERR, "Database connection failed.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * @return list<array{pid: int, name: string, gold: int}>
 */
function loadPlayers(Database $game): array
{
    if ($game->useDatabase('player')->fetch(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
        ['player', 'player'],
    ) === null) {
        return [];
    }

    $rows = $game->useDatabase('player')->fetchAll(
        'SELECT id, name, gold FROM `player` ORDER BY gold DESC, id ASC LIMIT 50',
    );

    return array_map(static fn (array $r): array => [
        'pid' => (int) $r['id'],
        'name' => (string) $r['name'],
        'gold' => (int) $r['gold'],
    ], $rows);
}

/**
 * @return list<array{vnum: int, units: int, base_price: int, trend: string}>
 */
function loadItems(Database $cms, Database $game): array
{
    $rows = $cms->fetchAll(
        'SELECT vnum, units FROM item_census ORDER BY units DESC LIMIT 25',
    );

    if ($rows === []) {
        return [];
    }

    // Hot items with scripted price stories (crash / spike / stable).
    $stories = [
        229 => ['base' => 180_000_000, 'trend' => 'spike'],   // Lunar Sword-ish
        27003 => ['base' => 2_500, 'trend' => 'stable'],
        76007 => ['base' => 850_000, 'trend' => 'crash'],
        27051 => ['base' => 3_200, 'trend' => 'stable'],
        76018 => ['base' => 1_200_000, 'trend' => 'spike'],
        76012 => ['base' => 450_000, 'trend' => 'crash'],
        76003 => ['base' => 380_000, 'trend' => 'stable'],
        50200 => ['base' => 12_000_000, 'trend' => 'spike'],
        76016 => ['base' => 95_000_000, 'trend' => 'crash'],
        30270 => ['base' => 25_000_000, 'trend' => 'spike'],
    ];

    $out = [];

    foreach ($rows as $i => $row) {
        $vnum = (int) $row['vnum'];
        $units = max(1, (int) $row['units']);
        $story = $stories[$vnum] ?? null;
        $base = $story['base'] ?? (int) max(5_000, 50_000 * (25 - $i));
        $trend = $story['trend'] ?? ($i % 3 === 0 ? 'spike' : ($i % 3 === 1 ? 'crash' : 'stable'));

        $out[] = [
            'vnum' => $vnum,
            'units' => $units,
            'base_price' => $base,
            'trend' => $trend,
        ];
    }

    // Ensure Lunar Sword story exists even if not in census.
    $has229 = false;

    foreach ($out as $item) {
        if ($item['vnum'] === 229) {
            $has229 = true;
            break;
        }
    }

    if (!$has229) {
        array_unshift($out, [
            'vnum' => 229,
            'units' => 8,
            'base_price' => 180_000_000,
            'trend' => 'spike',
        ]);
    }

    return $out;
}

function resetDemoData(Database $cms): int
{
    $n = 0;
    $n += $cms->execute("DELETE FROM item_market_trades WHERE source = 'demo'");
    $n += $cms->execute("DELETE FROM economy_yang_transfers WHERE source = 'demo'");
    $n += $cms->execute("DELETE FROM economy_alerts WHERE payload LIKE '%\"demo\":true%'");
    $n += $cms->execute("DELETE FROM item_market_daily WHERE source = 'demo'");

    // Census/yang/wealth demo history is not tagged — wipe the seeded window only when resetting.
    // Keep today's live census snapshot (item_census) and current tick yang if present after re-seed.

    return $n;
}

/**
 * @param list<array{pid: int, name: string, gold: int}> $players
 * @param list<array{vnum: int, units: int, base_price: int, trend: string}> $items
 * @return array<string, int>
 */
function seedDemo(Database $cms, array $players, array $items, int $days): array
{
    $stats = [
        'yang_days' => 0,
        'census_rows' => 0,
        'shop_trades' => 0,
        'npc_trades' => 0,
        'transfers' => 0,
        'market_daily' => 0,
        'wealth_days' => 0,
        'alerts' => 0,
    ];

    $today = new DateTimeImmutable('today');
    $pidList = array_column($players, 'pid');
    $rich = $players[0];
    $mid = $players[min(1, count($players) - 1)];
    $poor = $players[min(2, count($players) - 1)];

    // Baseline circulating yang (grows over time = mild inflation).
    $baseYang = max(500_000_000, array_sum(array_column($players, 'gold')));

    for ($d = $days - 1; $d >= 0; $d--) {
        $day = $today->modify("-{$d} days")->format('Y-m-d');
        $progress = ($days - 1 - $d) / max(1, $days - 1); // 0 → 1 over the window
        $captured = $day . ' 23:45:00';

        $playerYang = (int) round($baseYang * (0.82 + 0.28 * $progress));
        $safeboxYang = (int) round($playerYang * 0.18);
        $guildYang = (int) round($playerYang * 0.04);
        $created = (int) round(45_000_000 + 8_000_000 * $progress + random_int(0, 5_000_000));
        $destroyed = (int) round($created * (0.55 + 0.15 * sin($progress * M_PI)));

        $cms->execute(
            'INSERT INTO economy_yang_daily
             (day, player_yang, safebox_yang, guild_yang,
              money_monster, money_drop, money_shop, money_refine,
              money_quest, money_guild, money_misc, money_kill,
              money_created, money_destroyed, captured_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                player_yang = VALUES(player_yang),
                safebox_yang = VALUES(safebox_yang),
                guild_yang = VALUES(guild_yang),
                money_monster = VALUES(money_monster),
                money_drop = VALUES(money_drop),
                money_shop = VALUES(money_shop),
                money_refine = VALUES(money_refine),
                money_quest = VALUES(money_quest),
                money_created = VALUES(money_created),
                money_destroyed = VALUES(money_destroyed),
                captured_at = VALUES(captured_at)',
            [
                $day,
                $playerYang,
                $safeboxYang,
                $guildYang,
                (int) round($created * 0.55),
                (int) round($created * 0.25),
                (int) round($created * 0.05),
                -(int) round($destroyed * 0.4),
                (int) round($created * 0.1),
                (int) round($created * 0.02),
                (int) round($created * 0.03),
                (int) round($created * 0.05),
                $created,
                $destroyed,
                $captured,
            ],
        );
        $stats['yang_days']++;

        // Wealth concentration drifts slightly.
        $top1 = 28.0 + 8.0 * $progress;
        $top5 = min(92.0, $top1 + 22.0);
        $top10 = min(97.0, $top5 + 10.0);
        $cms->execute(
            'INSERT INTO economy_wealth_daily
             (day, player_count, total_yang, top1_pct, top5_pct, top10_pct, captured_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                player_count = VALUES(player_count),
                total_yang = VALUES(total_yang),
                top1_pct = VALUES(top1_pct),
                top5_pct = VALUES(top5_pct),
                top10_pct = VALUES(top10_pct),
                captured_at = VALUES(captured_at)',
            [
                $day,
                count($players),
                $playerYang,
                round($top1, 2),
                round($top5, 2),
                round($top10, 2),
                $captured,
            ],
        );
        $cms->execute('DELETE FROM economy_wealth_top_daily WHERE day = ?', [$day]);
        $rank = 1;

        foreach ($players as $p) {
            if ($rank > 20) {
                break;
            }

            $share = $rank === 1 ? 0.55 : ($rank === 2 ? 0.25 : 0.08);
            $yang = (int) round($playerYang * $share / max(1, $rank === 1 ? 1 : 1));
            // Distribute remaining roughly by real gold weight.
            $yang = max(0, (int) round($p['gold'] * (0.7 + 0.3 * $progress)));

            if ($rank === 1) {
                $yang = max($yang, (int) round($playerYang * 0.45));
            }

            $cms->execute(
                'INSERT INTO economy_wealth_top_daily (day, rank_pos, pid, yang) VALUES (?, ?, ?, ?)',
                [$day, $rank, $p['pid'], $yang],
            );
            $rank++;
        }
        $stats['wealth_days']++;

        // Census history with supply swings on hot items.
        foreach ($items as $item) {
            $vnum = $item['vnum'];
            $unitsNow = $item['units'];
            $wave = match ($item['trend']) {
                'spike' => 1.0 - 0.35 * $progress,   // supply shrinks → price up
                'crash' => 0.7 + 0.9 * $progress,    // supply floods → price down
                default => 0.95 + 0.1 * sin($progress * 4),
            };
            $units = max(1, (int) round($unitsNow * $wave));

            $cms->execute(
                'INSERT INTO item_census_daily
                 (day, vnum, units, stacks, holders_players, holders_accounts,
                  units_player, units_safebox, units_mall, units_pending, units_delta)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    units = VALUES(units),
                    stacks = VALUES(stacks),
                    holders_players = VALUES(holders_players),
                    units_player = VALUES(units_player),
                    units_safebox = VALUES(units_safebox),
                    units_delta = VALUES(units_delta)',
                [
                    $day,
                    $vnum,
                    $units,
                    max(1, (int) ceil($units / 3)),
                    max(1, (int) ceil($units / 4)),
                    max(0, (int) ceil($units / 8)),
                    (int) round($units * 0.7),
                    (int) round($units * 0.2),
                    (int) round($units * 0.05),
                    (int) round($units * 0.05),
                    0,
                ],
            );
            $stats['census_rows']++;
        }

        // Shop trades through the day.
        $tradesToday = 12 + (int) round(25 * $progress) + random_int(0, 8);

        for ($t = 0; $t < $tradesToday; $t++) {
            $item = $items[array_rand($items)];
            $price = demoUnitPrice($item['base_price'], $item['trend'], $progress);
            // Occasional outlier (will sit outside median but we still insert for realism).
            if (random_int(1, 40) === 1) {
                $price = (int) round($price * (random_int(0, 1) === 1 ? 8 : 0.15));
            }

            $count = $price > 10_000_000 ? 1 : random_int(1, 5);
            $total = $price * $count;
            $hour = random_int(8, 23);
            $minute = random_int(0, 59);
            $second = random_int(0, 59);
            $soldAt = sprintf('%s %02d:%02d:%02d', $day, $hour, $minute, $second);

            $seller = $pidList[array_rand($pidList)];
            $buyer = $pidList[array_rand($pidList)];

            if ($buyer === $seller && count($pidList) > 1) {
                $buyer = $pidList[($seller === $pidList[0]) ? 1 : 0];
            }

            // Concentration story: last 7 days, vnum 76016 dominated by rich player.
            if ($item['vnum'] === 76016 && $d <= 6) {
                $seller = $rich['pid'];
                $buyer = $mid['pid'];
            }

            $key = hash('sha1', 'demo|shop|' . $day . '|' . $t . '|' . $item['vnum'] . '|' . $soldAt . '|' . $total);

            try {
                $cms->execute(
                    'INSERT INTO item_market_trades
                     (trade_key, vnum, count, price_yang, unit_price, sold_at, source,
                      seller_pid, buyer_pid, channel)
                     VALUES (?, ?, ?, ?, ?, ?, \'demo\', ?, ?, \'shop\')',
                    [$key, $item['vnum'], $count, $total, $price, $soldAt, $seller, $buyer],
                );
                $stats['shop_trades']++;
            } catch (\PDOException) {
                // duplicate key — ignore
            }
        }

        // A few NPC channel rows (excluded from market median rebuild).
        for ($n = 0; $n < 3; $n++) {
            $item = $items[array_rand($items)];
            $price = max(100, (int) round($item['base_price'] * 0.35));
            $soldAt = sprintf('%s %02d:%02d:00', $day, random_int(10, 20), random_int(0, 59));
            $key = hash('sha1', 'demo|npc|' . $day . '|' . $n . '|' . $item['vnum'] . '|' . $soldAt);

            try {
                $cms->execute(
                    'INSERT INTO item_market_trades
                     (trade_key, vnum, count, price_yang, unit_price, sold_at, source,
                      seller_pid, buyer_pid, channel)
                     VALUES (?, ?, 1, ?, ?, ?, \'demo\', ?, NULL, \'npc\')',
                    [$key, $item['vnum'], $price, $price, $soldAt, $pidList[array_rand($pidList)]],
                );
                $stats['npc_trades']++;
            } catch (\PDOException) {
            }
        }

        // Yang transfers (exchange) — burst today for velocity alert on rich receiver.
        $xferCount = $d === 0 ? 12 : random_int(1, 4);

        for ($x = 0; $x < $xferCount; $x++) {
            $from = $d === 0 ? $pidList[array_rand($pidList)] : $mid['pid'];
            $to = $d === 0 ? $rich['pid'] : $pidList[array_rand($pidList)];

            if ($from === $to && count($pidList) > 1) {
                $from = $poor['pid'];
            }

            $amount = $d === 0
                ? random_int(80_000_000, 120_000_000)
                : random_int(1_000_000, 25_000_000);
            $at = $d === 0
                ? sprintf('%s 14:%02d:%02d', $day, min(59, 5 + $x), random_int(0, 59))
                : sprintf('%s %02d:%02d:00', $day, random_int(12, 22), random_int(0, 59));
            $key = hash('sha1', 'demo|xfer|' . $day . '|' . $x . '|' . $from . '|' . $to . '|' . $amount . '|' . $at);

            try {
                $cms->execute(
                    'INSERT INTO economy_yang_transfers
                     (transfer_key, from_pid, to_pid, yang_amount, transferred_at, source)
                     VALUES (?, ?, ?, ?, ?, \'demo\')',
                    [$key, $from, $to, $amount, $at],
                );
                $stats['transfers']++;
            } catch (\PDOException) {
            }
        }
    }

    // Fill units_delta from adjacent census days (signed math — units is UNSIGNED).
    $cms->execute(
        'UPDATE item_census_daily today
         LEFT JOIN item_census_daily yday
           ON yday.vnum = today.vnum AND yday.day = DATE_SUB(today.day, INTERVAL 1 DAY)
         SET today.units_delta = CAST(today.units AS SIGNED) - CAST(COALESCE(yday.units, today.units) AS SIGNED)
         WHERE today.day >= DATE_SUB(CURDATE(), INTERVAL ? DAY)',
        [$days],
    );

    // Rebuild shop market daily from demo+real shop trades (last N days).
    $fromDay = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $rows = $cms->fetchAll(
        'SELECT DATE(sold_at) AS day, vnum, unit_price, count, price_yang, source,
                seller_pid, buyer_pid
         FROM item_market_trades
         WHERE channel = \'shop\' AND DATE(sold_at) >= ?
         ORDER BY day ASC, vnum ASC',
        [$fromDay],
    );

    /** @var array<string, array<int, array{prices: list<int>, units: int, volume: int, sellers: array<int,true>, buyers: array<int,true>, source: string}>> $grouped */
    $grouped = [];

    foreach ($rows as $row) {
        $day = (string) $row['day'];
        $vnum = (int) $row['vnum'];

        if (!isset($grouped[$day][$vnum])) {
            $grouped[$day][$vnum] = [
                'prices' => [],
                'units' => 0,
                'volume' => 0,
                'sellers' => [],
                'buyers' => [],
                'source' => (string) ($row['source'] ?? 'demo'),
            ];
        }

        $grouped[$day][$vnum]['prices'][] = (int) $row['unit_price'];
        $grouped[$day][$vnum]['units'] += (int) $row['count'];
        $grouped[$day][$vnum]['volume'] += (int) $row['price_yang'];
        $s = (int) ($row['seller_pid'] ?? 0);
        $b = (int) ($row['buyer_pid'] ?? 0);

        if ($s > 0) {
            $grouped[$day][$vnum]['sellers'][$s] = true;
        }

        if ($b > 0) {
            $grouped[$day][$vnum]['buyers'][$b] = true;
        }

        if (($row['source'] ?? '') === 'demo') {
            $grouped[$day][$vnum]['source'] = 'demo';
        }
    }

    foreach ($grouped as $day => $byVnum) {
        foreach ($byVnum as $vnum => $info) {
            $median = EconomyStats::median($info['prices']);
            $p25 = EconomyStats::percentile($info['prices'], 25);
            $p75 = EconomyStats::percentile($info['prices'], 75);
            $cms->execute(
                'INSERT INTO item_market_daily
                 (day, vnum, trades, units, volume_yang, unique_sellers, unique_buyers,
                  median_price, p25_price, p75_price, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    trades = VALUES(trades),
                    units = VALUES(units),
                    volume_yang = VALUES(volume_yang),
                    unique_sellers = VALUES(unique_sellers),
                    unique_buyers = VALUES(unique_buyers),
                    median_price = VALUES(median_price),
                    p25_price = VALUES(p25_price),
                    p75_price = VALUES(p75_price),
                    source = VALUES(source)',
                [
                    $day,
                    $vnum,
                    count($info['prices']),
                    $info['units'],
                    $info['volume'],
                    count($info['sellers']),
                    count($info['buyers']),
                    $median !== null ? (int) round($median) : null,
                    $p25 !== null ? (int) round($p25) : null,
                    $p75 !== null ? (int) round($p75) : null,
                    $info['source'],
                ],
            );
            $stats['market_daily']++;
        }
    }

    // Open alerts for today's demo stories.
    $alertDay = $today->format('Y-m-d');
    $alerts = [
        ['type' => 'item', 'id' => 76007, 'kind' => 'price_crash', 'payload' => [
            'demo' => true,
            'median_now' => 400_000,
            'median_baseline' => 850_000,
            'change' => -0.53,
            'trades' => 18,
        ]],
        ['type' => 'item', 'id' => 229, 'kind' => 'price_spike', 'payload' => [
            'demo' => true,
            'median_now' => 260_000_000,
            'median_baseline' => 180_000_000,
            'change' => 0.44,
            'trades' => 14,
        ]],
        ['type' => 'item', 'id' => 76016, 'kind' => 'concentration', 'payload' => [
            'demo' => true,
            'share' => 0.82,
            'player_count' => 2,
            'trades' => 22,
            'change' => 0.82,
        ]],
        ['type' => 'item', 'id' => 50200, 'kind' => 'volume_spike', 'payload' => [
            'demo' => true,
            'trades_now' => 28,
            'trades_avg' => 6,
            'change' => 3.6,
        ]],
        ['type' => 'item', 'id' => 76018, 'kind' => 'supply_down', 'payload' => [
            'demo' => true,
            'units_now' => 6,
            'units_baseline' => 14,
            'change' => -0.57,
        ]],
        ['type' => 'player', 'id' => $rich['pid'], 'kind' => 'yang_velocity', 'payload' => [
            'demo' => true,
            'yang_received' => 1_100_000_000,
            'unique_sources' => 12,
            'window_minutes' => 18,
            'change' => 1_100_000_000,
        ]],
    ];

    foreach ($alerts as $a) {
        $vnum = $a['type'] === 'item' ? $a['id'] : 0;

        try {
            $cms->execute(
                'INSERT INTO economy_alerts (day, subject_type, subject_id, vnum, kind, payload)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $alertDay,
                    $a['type'],
                    $a['id'],
                    $vnum,
                    $a['kind'],
                    json_encode($a['payload'], JSON_THROW_ON_ERROR),
                ],
            );
            $stats['alerts']++;
        } catch (\PDOException) {
            // already exists for today
        }
    }

    $cms->execute(
        'INSERT INTO economy_tick_state (state_key, state_value)
         VALUES (\'last_ok_at\', ?)
         ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)',
        [date('Y-m-d H:i:s')],
    );

    return $stats;
}

function demoUnitPrice(int $base, string $trend, float $progress): int
{
    $mult = match ($trend) {
        'spike' => 0.85 + 0.75 * $progress,   // climbs
        'crash' => 1.35 - 0.7 * $progress,    // falls
        default => 1.0 + 0.08 * sin($progress * M_PI * 2),
    };

    $noise = 1.0 + (random_int(-8, 8) / 100.0);

    return max(1, (int) round($base * $mult * $noise));
}
