<?php

if (! defined('PROJECT')) {
    define('PROJECT', __DIR__ . '/../src/');
}

if (! defined('PROJECT_TESTS')) {
    define('PROJECT_TESTS', __DIR__ . '/');
}

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/tools/');
}

if (! file_exists(__DIR__ . '/../vendor/autoload.php')) {
    throw new \PHPUnit\Framework\Exception('ERROR' . PHP_EOL . PHP_EOL . 'You must use Composer to install the test suite\'s dependencies!' . PHP_EOL);
}

require_once __DIR__ . '/../vendor/autoload.php';

WP_Mock::setUsePatchwork(true);
WP_Mock::bootstrap();
WP_Mock::tearDown();
