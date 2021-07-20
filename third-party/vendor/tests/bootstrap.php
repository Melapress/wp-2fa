<?php

namespace WP2FA_Vendor;

if (!\defined('WP2FA_Vendor\\PROJECT')) {
    \define('WP2FA_Vendor\\PROJECT', __DIR__ . '/../includes/');
}
if (!\defined('WP2FA_Vendor\\WP_2FA_DIR')) {
    \define('WP2FA_Vendor\\WP_2FA_DIR', __DIR__ . '/');
}
// Place any additional bootstrapping requirements here for PHP Unit.
if (!\defined('WP2FA_Vendor\\WP_LANG_DIR')) {
    \define('WP2FA_Vendor\\WP_LANG_DIR', 'lang_dir');
}
if (!\defined('WP2FA_Vendor\\WP_2FA_PATH')) {
    \define('WP2FA_Vendor\\WP_2FA_PATH', 'path');
}
if (!\file_exists(__DIR__ . '/../vendor/autoload.php')) {
    throw new \WP2FA_Vendor\PHPUnit_Framework_Exception('ERROR' . \PHP_EOL . \PHP_EOL . 'You must use Composer to install the test suite\'s dependencies!' . \PHP_EOL);
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../tests/phpunit/test-tools/TestCase.php';
\WP2FA_Vendor\WP_Mock::setUsePatchwork(\true);
\WP2FA_Vendor\WP_Mock::bootstrap();
\WP2FA_Vendor\WP_Mock::tearDown();
