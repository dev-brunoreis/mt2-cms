#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));

require BASE_DIR . '/src/bootstrap/autoload.php';

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

try {
    $app = new Mt2Cms\Application();
    $result = $app->paymentWebhookProcessor->run();

    if ($result['skipped']) {
        echo "Payment webhook processor skipped (lock held).\n";
        exit(0);
    }

    echo sprintf(
        "Payment webhook processor OK: processed=%d retried=%d failed=%d\n",
        $result['processed'],
        $result['retried'],
        $result['failed'],
    );
} catch (\PDOException $e) {
    fwrite(STDERR, "Database connection failed.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Payment webhook processor failed: ' . $e->getMessage() . "\n");
    exit(1);
}
