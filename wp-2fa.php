<?php // phpcs:ignore
/**
 * Plugin Name: WP 2FA - Two-factor authentication for WordPress
 * Plugin URI:  https://wp2fa.io
 * Description: Easily add an additional layer of security to your WordPress login pages. Enable Two-Factor Authentication for you and all your website users with this easy to use plugin.
 * Version:     2.0.0
 * Author:      WP White Security
 * Author URI:  https://www.wpwhitesecurity.com
 * Text Domain: wp-2fa
 * Domain Path: /languages
 * Network:     true
 *
 * @package WP2FA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Useful global constants.
if ( ! defined( 'WP_2FA_VERSION' ) ) {
	define( 'WP_2FA_VERSION', '2.0.0' );
	define( 'WP_2FA_URL', plugin_dir_url( __FILE__ ) );
	define( 'WP_2FA_PATH', plugin_dir_path( __FILE__ ) );
	define( 'WP_2FA_INC', WP_2FA_PATH . 'includes/' );
	define( 'WP_2FA_FILE', __FILE__ );
	define( 'WP_2FA_BASE', plugin_basename( __FILE__ ) );
	define( 'WP_2FA_LOGS_DIR', 'wp-2fa-logs' );

	// Prefix used in usermetas, settings and transients.
	define( 'WP_2FA_PREFIX', 'wp_2fa_' );
	define( 'WP_2FA_POLICY_SETTINGS_NAME', WP_2FA_PREFIX . 'policy' );
	define( 'WP_2FA_SETTINGS_NAME', WP_2FA_PREFIX . 'settings' );
	define( 'WP_2FA_WHITE_LABEL_SETTINGS_NAME', WP_2FA_PREFIX . 'white_label' );
	define( 'WP_2FA_EMAIL_SETTINGS_NAME', WP_2FA_PREFIX . 'email_settings' );
}
		// Include files.
		require_once WP_2FA_INC . 'functions/core.php';

		// Require Composer autoloader if it exists.
		if ( file_exists( WP_2FA_PATH . 'vendor/autoload.php' ) ) {
			require_once WP_2FA_PATH . 'vendor/autoload.php';
		}

		if ( file_exists( WP_2FA_PATH . 'third-party/vendor/autoload.php' ) ) {
			require_once WP_2FA_PATH . 'third-party/vendor/autoload.php';
		}

		// run any required update routines.
		\WP2FA\Utils\Migration::migrate();

		$wp2fa = \WP2FA\WP2FA::get_instance();
		$wp2fa->init();
if ( ! function_exists( 'wp2fa_free_on_plugin_activation' ) ) {
	/**
	 * Takes care of deactivation of the premium plugin when the free plugin is activated.
	 *
	 * Note: This code MUST NOT be present in the premium version an is removed automatically during the build process.
	 *
	 * @since 2.0.0
	 */
	function wp2fa_free_on_plugin_activation() {
		$premium_version_slug = 'wp-2fa-premium/wp-2fa.php';
		if ( is_plugin_active( $premium_version_slug ) ) {
			deactivate_plugins( $premium_version_slug, true );
		}
	}

	register_activation_hook( __FILE__, 'wp2fa_free_on_plugin_activation' );
}
