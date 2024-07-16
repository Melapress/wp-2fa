<?php
/**
 * WP 2FA - Two-factor authentication for WordPress .
 *
 * @copyright Copyright (C) 2013-2024, Melapress - support@melapress.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: WP 2FA - Two-factor authentication for WordPress 
 * Version:     2.8.0
 * Plugin URI:  https://melapress.com/
 * Description: Easily add an additional layer of security to your WordPress login pages. Enable Two-Factor Authentication for you and all your website users with this easy to use plugin.
 * Author:      Melapress
 * Author URI:  https://melapress.com/
 * Text Domain: wp-2fa
 * Domain Path: /languages/
 * License:     GPL v3
 * Requires at least: 5.0
 * Requires PHP: 7.3
 * Network: true
 *
 * @package WP2FA
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @fs_ignore /dist/, /extensions/, /freemius/, /includes/, /languages/, /third-party/, /vendor/
 */

use WP2FA\WP2FA;
use WP2FA\Utils\Migration;
use WP2FA\Extensions_Loader;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Freemius\Freemius_Helper;
use WP2FA\Admin\Helpers\File_Writer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( '\DISABLE_2FA_LOGIN' ) && \DISABLE_2FA_LOGIN ) {
	return;
}

// Useful global constants.
if ( ! defined( 'WP_2FA_VERSION' ) ) {
	define( 'WP_2FA_VERSION', '2.8.0' );
	define( 'WP_2FA_BASE', plugin_basename( __FILE__ ) );
	define( 'WP_2FA_URL', plugin_dir_url( __FILE__ ) );
	define( 'WP_2FA_PATH', WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( WP_2FA_BASE ) . DIRECTORY_SEPARATOR );
	define( 'WP_2FA_INC', WP_2FA_PATH . 'includes/' );
	define( 'WP_2FA_FILE', __FILE__ );
	define( 'WP_2FA_LOGS_DIR', 'wp-2fa-logs' );

	// Prefix used in usermetas, settings and transients.
	define( 'WP_2FA_PREFIX', 'wp_2fa_' );
	define( 'WP_2FA_POLICY_SETTINGS_NAME', WP_2FA_PREFIX . 'policy' );
	define( 'WP_2FA_SETTINGS_NAME', WP_2FA_PREFIX . 'settings' );
	define( 'WP_2FA_WHITE_LABEL_SETTINGS_NAME', WP_2FA_PREFIX . 'white_label' );
	define( 'WP_2FA_EMAIL_SETTINGS_NAME', WP_2FA_PREFIX . 'email_settings' );

	define( 'WP_2FA_PREFIX_PAGE', 'wp-2fa-' );
}

// phpcs:disable
		// phpcs:enable
		// Include files.
		require_once WP_2FA_INC . 'functions/core.php';

		// Require Composer autoloader if it exists.
		if ( file_exists( WP_2FA_PATH . 'vendor/autoload.php' ) ) {
			require_once WP_2FA_PATH . 'vendor/autoload.php';
		}

		// run any required update routines.
		Migration::migrate();

		// Setup_Wizard.
		if ( WP_Helper::is_multisite() ) {
			add_action( 'network_admin_menu', array( '\WP2FA\Admin\Setup_Wizard', 'network_admin_menus' ), 10 );
			add_action( 'admin_menu', array( '\WP2FA\Admin\Setup_Wizard', 'admin_menus' ), 10 );
		} else {
			add_action( 'admin_menu', array( '\WP2FA\Admin\Setup_Wizard', 'admin_menus' ), 10 );
		}

		// Activation/Deactivation.
		register_activation_hook( WP_2FA_FILE, '\WP2FA\Core\activate' );
		register_deactivation_hook( WP_2FA_FILE, '\WP2FA\Core\deactivate' );
		// Register our uninstallation hook.
		register_uninstall_hook( WP_2FA_FILE, '\WP2FA\Core\uninstall' );

		add_filter( 'plugins_loaded', array( '\WP2FA\WP2FA', 'init' ) );
		add_action( 'plugins_loaded', array( '\WP2FA\WP2FA', 'add_wizard_actions' ), 10 );


		// phpcs:disable
// phpcs:enable

if ( ! defined( File_Writer::SECRET_NAME ) ) {
	define( File_Writer::SECRET_NAME, WP2FA::get_secret_key() );

	define( 'WP2FA_SECRET_IS_IN_DB', true );
}

// phpcs:disable
/* @free:start */
// phpcs:enable
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
		check_ssl();
	}

	register_activation_hook( __FILE__, 'wp2fa_free_on_plugin_activation' );
}
// phpcs:disable
/* @free:end */
// phpcs:enable

/*
 * Clears the config cache from the DB
 *
 * @return void
 *
 * @since 2.2.0
 */
add_action(
	'upgrader_process_complete',
	function () {
		delete_transient( 'wp_2fa_config_file_hash' );
	},
	10,
	2
);

if ( ! function_exists( 'check_ssl' ) ) {
	/**
	 * Checks if the required library is installed and cancels the process if not.
	 *
	 * @return void
	 *
	 * @since 2.2.0
	 */
	function check_ssl() {
		if ( ! \WP2FA\Authenticator\Open_SSL::is_ssl_available() ) {
			$html = '<div class="updated notice is-dismissible">
			<p>' . \esc_html__( 'This plugin requires OpenSSL. Contact your web host or website administrator so they can enable OpenSSL. Re-activate the plugin once the library has been enabled.', 'wp-2fa' )
			. '</p>
		</div>';

			echo $html; // phpcs:ignore

			exit();
		}
	}
}

if ( \PHP_VERSION_ID < 80000 && ! \interface_exists( 'Stringable' ) ) {
	interface Stringable { // phpcs:ignore
		/**
		 * Mockup function for PHP versions lower than 8.
		 *
		 * @return string
		 */
		public function __toString();
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * PHP lower than 8 is missing that function but it required in the newer versions of our plugin.
	 *
	 * @param string $haystack - The string to search in.
	 * @param string $needle - The needle to search for.
	 *
	 * @return bool
	 *
	 * @since 2.6.4
	 */
	function str_starts_with( $haystack, $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}
