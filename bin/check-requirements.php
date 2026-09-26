#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Print host dependency checks for Mt2 CMS. Exit 0 when all required checks pass.
 * Usable before or after `composer install` (loads SetupRequirements without vendor if needed).
 */

define('BASE_DIR', dirname(__DIR__));

$autoload = BASE_DIR . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    require BASE_DIR . '/src/Setup/SetupRequirements.php';
}

$checker = new Mt2Cms\Setup\SetupRequirements(BASE_DIR);
$checks = $checker->checks();
$ok = $checker->allRequiredOk();

$labels = [
    'setup.req.php_version' => 'PHP 8.3.x',
    'setup.req.ext_pdo_mysql' => 'ext-pdo_mysql',
    'setup.req.ext_gd' => 'ext-gd',
    'setup.req.ext_curl' => 'ext-curl',
    'setup.req.ext_mbstring' => 'ext-mbstring',
    'setup.req.ext_iconv' => 'ext-iconv',
    'setup.req.ext_fileinfo' => 'ext-fileinfo',
    'setup.req.ext_openssl' => 'ext-openssl',
    'setup.req.gd_jpeg' => 'GD JPEG support',
    'setup.req.gd_png' => 'GD PNG support',
    'setup.req.gd_webp' => 'GD WebP support',
    'setup.req.vendor' => 'Composer vendor/',
    'setup.req.base_writable' => 'Project root writable',
    'setup.req.var_writable' => 'var/ writable',
    'setup.req.uploads_writable' => 'public/uploads/ writable',
];

fwrite(STDOUT, "Mt2 CMS host requirements\n");
fwrite(STDOUT, str_repeat('-', 48) . "\n");

foreach ($checks as $check) {
    $status = $check['ok'] ? 'OK  ' : 'FAIL';
    $label = $labels[$check['labelKey']] ?? $check['labelKey'];
    $detail = isset($check['detail']) ? ' (' . $check['detail'] . ')' : '';
    fwrite(STDOUT, sprintf("[%s] %s%s\n", $status, $label, $detail));
}

fwrite(STDOUT, str_repeat('-', 48) . "\n");

if ($ok) {
    fwrite(STDOUT, "All required checks passed.\n");
    exit(0);
}

fwrite(STDERR, "One or more required checks failed. Fix them before running /setup.\n");
exit(1);
