<?php

namespace WP2FA_Vendor;

// phpcs:ignore
/**
 * Plugin Name: WP 2FA - Two-factor authentication for WordPress (Premium)
 * Plugin URI:  https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/
 * Description: Easily add an additional layer of security to your WordPress login pages. Enable Two-Factor Authentication for you and all your website users with this easy to use plugin.
 * Version:     1.7.0
 * Author:      WP White Security
 * Author URI:  https://www.wpwhitesecurity.com
 * Text Domain: wp-2fa
 * Domain Path: /languages
 * Network:     true
 * @package WP2FA
 */
// Useful global constants.
\define('WP2FA_Vendor\\WP_2FA_VERSION', '1.7.0');
\define('WP2FA_Vendor\\WP_2FA_URL', \plugin_dir_url(__FILE__));
\define('WP2FA_Vendor\\WP_2FA_PATH', \WP2FA_Vendor\plugin_dir_path(__FILE__));
\define('WP2FA_Vendor\\WP_2FA_INC', \WP2FA_Vendor\WP_2FA_PATH . 'includes/');
\define('WP2FA_Vendor\\WP_2FA_FILE', __FILE__);
\define('WP2FA_Vendor\\WP_2FA_BASE', \WP2FA_Vendor\plugin_basename(__FILE__));
\define('WP2FA_Vendor\\WP_2FA_LOGS_DIR', 'wp-2fa-logs');
// Prefix used in usermetas, settings and transients
\define('WP2FA_Vendor\\WP_2FA_PREFIX', 'wp_2fa_');
\define('WP2FA_Vendor\\WP_2FA_SETTINGS_NAME', \WP2FA_Vendor\WP_2FA_PREFIX . 'settings');
\define('WP2FA_Vendor\\WP_2FA_EMAIL_SETTINGS_NAME', \WP2FA_Vendor\WP_2FA_PREFIX . 'email_settings');
// Include files.
require_once \WP2FA_Vendor\WP_2FA_INC . 'functions/core.php';
// Require Composer autoloader if it exists.
if (\file_exists(\WP2FA_Vendor\WP_2FA_PATH . 'vendor/autoload.php')) {
    require_once \WP2FA_Vendor\WP_2FA_PATH . 'vendor/autoload.php';
}
if (\file_exists(\WP2FA_Vendor\WP_2FA_PATH . 'third-party/vendor/autoload.php')) {
    require_once \WP2FA_Vendor\WP_2FA_PATH . 'third-party/vendor/autoload.php';
}
// run any required update routines
\WP2FA_Vendor\WP2FA\Utils\Migration::migrate();
$wp2fa = \WP2FA_Vendor\WP2FA\WP2FA::get_instance();
$wp2fa->init();
