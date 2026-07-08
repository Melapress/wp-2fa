<?php
/**
 * Docs and Support page rendering class.
 *
 * @package    wp2fa
 * @subpackage admin
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 * @since 4.0.0
 */

declare( strict_types=1 );

namespace WP2FA\Admin;

use WP2FA\WP2FA;

if ( ! class_exists( '\WP2FA\Admin\Docs_And_Support' ) ) {

	/**
	 * Handles the Docs and Support admin page.
	 */
	class Docs_And_Support {

		const TOP_MENU_SLUG = 'wp-2fa-help-contact-us';

		/**
		 * Create admin submenu entry for the Docs and Support page.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function add_extra_menu_item() {

			\add_submenu_page(
				Settings_Page::TOP_MENU_SLUG,
				\esc_html__( 'Docs and Support', 'wp-2fa' ),
				\esc_html__( 'Docs and Support', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				array( __CLASS__, 'render' ),
				100
			);

			\add_action(
				'admin_enqueue_scripts',
				function ( $hook ) {
					if ( 'wp-2fa_page_' . self::TOP_MENU_SLUG !== $hook ) {
						return;
					}

					\wp_enqueue_style(
						'wp_2fa_settings_new_css',
						WP_2FA_URL . 'includes/assets/css/settings.css',
						array(),
						WP_2FA_VERSION
					);
				}
			);
		}

		/**
		 * Render the Docs and Support page.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function render() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-2fa' ) );
			}

			$main_user = ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ? (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) : \get_current_user_id();
			if ( ! empty( WP2FA::get_wp2fa_general_setting( 'limit_access' ) ) && $main_user !== \get_current_user_id() ) {
				echo \esc_html__( 'These settings have been disabled by your site administrator, please contact them for further assistance.', 'wp-2fa' );
				return;
			}

			include_once \WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/support/docs-and-support.php';
		}

		/**
		 * Gather basic settings and system information.
		 *
		 * @return string
		 */
		public static function get_sysinfo() {
			return Help_Contact_Us::get_sysinfo();
		}
	}
}
