#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));

require BASE_DIR . '/vendor/autoload.php';

Mt2Cms\Application::loadConfigs();

try {
    $gameDb = new Mt2Cms\Support\Database();
    $cmsDb = Mt2Cms\Support\Database::forCms();

    $scan = new Mt2Cms\Repository\GameEconomyScanRepository($gameDb);
    $economy = new Mt2Cms\Repository\EconomyRepository($cmsDb);
    $settings = new Mt2Cms\Service\SettingsService(
        new Mt2Cms\Repository\SettingsRepository($cmsDb),
        new Mt2Cms\Setup\ThemeCatalog(BASE_DIR . '/themes'),
    );
    $discord = new Mt2Cms\Service\DiscordWebhookService($settings);
    $tick = new Mt2Cms\Service\EconomyTickService($scan, $economy, $discord, BASE_DIR);

    $result = $tick->run();

    if ($result['skipped']) {
        echo "Economy tick skipped (lock held).\n";
        exit(0);
    }

    echo sprintf(
        "Economy tick OK: census=%d trades=%d unmatched=%d alerts=%d\n",
        $result['census_rows'],
        $result['trades_ingested'],
        $result['trades_unmatched'],
        $result['alerts_created'],
    );
} catch (\PDOException $e) {
    fwrite(STDERR, "Database connection failed.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Economy tick failed: ' . $e->getMessage() . "\n");
    exit(1);
}
