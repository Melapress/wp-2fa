<?php
/**
 * About Us page rendering class.
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

if ( ! class_exists( '\WP2FA\Admin\About_Us' ) ) {

	/**
	 * Handles the About Us admin page.
	 */
	class About_Us {

		const TOP_MENU_SLUG = 'wp-2fa-about-us';

		/**
		 * Create admin submenu entry for the About Us page.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function add_extra_menu_item() {

			\add_submenu_page(
				Settings_Page::TOP_MENU_SLUG,
				\esc_html__( 'About Us', 'wp-2fa' ),
				\esc_html__( 'About Us', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				array( __CLASS__, 'render' ),
				200
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
		 * Render the About Us page.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function render() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-2fa' ) );
			}

			include_once \WP_2FA_PATH . 'includes/classes/Admin/Settings/templates/about-us/about-us.php';
		}
	}
}
