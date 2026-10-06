<?php
/**
 * Responsible for WP2FA update notices.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

use WP2FA\Utils\Settings_Utils;
use WP2FA\Utils\Abstract_Migration;

/**
 * Plugin_Updated_Notice class with user notification filters
 *
 * @since 2.7.0
 */
if ( ! class_exists( '\WP2FA\Admin\Plugin_Updated_Notice' ) ) {
	/**
	 * Plugin_Updated_Notice - Class for displaying notices to our users.
	 */
	class Plugin_Updated_Notice {

		/**
		 * Lets set things up
		 *
		 * @since 2.7.0
		 */
		public static function init() {
			if ( Settings_Utils::get_option( Abstract_Migration::UPGRADE_NOTICE, false ) ) {
				\add_action( 'wp_ajax_dismiss_update_notice', array( __CLASS__, 'dismiss_update_notice' ) );
			}
		}

		/**
		 * Handle notice dismissal.
		 *
		 * @since 2.7.0
		 * @return void
		 */
		public static function dismiss_update_notice() {
			// Grab POSTed data.
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : false;
			// Check nonce.
			if ( ! \current_user_can( 'manage_options' ) || empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'dismiss_upgrade_notice' ) ) {
				\wp_send_json_error( esc_html__( 'Nonce Verification Failed.', 'wp-2fa' ) );
			}

			Settings_Utils::delete_option( Abstract_Migration::UPGRADE_NOTICE );

			\wp_send_json_success( esc_html__( 'Complete.', 'wp-2fa' ) );
		}
	}
}
