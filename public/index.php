<?php

define('BASE_DIR', dirname(__DIR__));

$autoloadPath = BASE_DIR . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new \Exception('Autoload file not found');
}

require_once $autoloadPath;

$app = new \Mt2Cms\Application();
$app->run();