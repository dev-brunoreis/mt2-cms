<?php

declare(strict_types=1);

use Mt2Cms\Application;

define('BASE_DIR', dirname(__DIR__));

$autoloadPath = BASE_DIR . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new \RuntimeException('Autoload file not found. Run composer install.');
}

require_once $autoloadPath;

(new Application())->run();
