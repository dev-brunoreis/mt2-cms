#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));

require BASE_DIR . '/vendor/autoload.php';

Mt2Cms\Application::loadConfigs();

$runner = new Mt2Cms\Setup\MigrationRunner(Mt2Cms\Model\Database::forCms());
$before = $runner->currentVersion();
$runner->migrate();
$after = $runner->currentVersion();

echo 'CMS schema migrated: ' . $before . ' -> ' . $after . PHP_EOL;
