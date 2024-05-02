<?php

declare(strict_types=1);

// phpcs:disable PSR1

$testsDir = str_replace('\\', '/', __DIR__);
$libDir = dirname($testsDir);
$vendorDir = "{$libDir}/vendor";
$autoload = "{$vendorDir}/autoload.php";

if (!is_file($autoload)) {
    die('Please install via Composer before running tests.');
}

// At the moment we have too many deprecations due to dependencies so we hide them on PHP 8.1+
// to avoid tests failures.
error_reporting(E_ALL ^ E_DEPRECATED);

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', $autoload);
    require_once $autoload;
}

unset($libDir, $testsDir, $vendorDir, $autoload);
