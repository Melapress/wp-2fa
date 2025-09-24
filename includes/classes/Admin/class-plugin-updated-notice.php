<?php
/**
 * Responsible for WP2FA update notices.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

use WP2FA\Utils\Abstract_Migration;
use WP2FA\Utils\Settings_Utils;

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
			\add_action( 'admin_notices', array( __CLASS__, 'plugin_update_banner' ) );
			\add_action( 'network_admin_notices', array( __CLASS__, 'plugin_update_banner' ) );
			if ( Settings_Utils::get_option( Abstract_Migration::UPGRADE_NOTICE, false ) ) {
				\add_action( 'wp_ajax_dismiss_update_notice', array( __CLASS__, 'dismiss_update_notice' ) );
			}
		}

		/**
		 * The nag content
		 *
		 * @since 2.7.0
		 * @return void
		 */
		public static function plugin_update_banner() {
			global $current_screen;

			if ( ! isset( $current_screen ) ) {
				return;
			}

			$screen = \get_current_screen();

			$correct_screen = ( 'toplevel_page_wp-2fa-policies-network' === $screen->base || 'toplevel_page_wp-2fa-policies' === $screen->base ||
			'wp-2fa_page_wp-2fa-settings' === $screen->base || 'wp-2fa_page_wp-2fa-settings-network' === $screen->base ||
			'wp-2fa_page_wp-2fa-reports' === $screen->base || 'wp-2fa_page_wp-2fa-reports-network' === $screen->base ||
			'wp-2fa_page_wp-2fa-help-contact-us' === $screen->base || 'wp-2fa_page_wp-2fa-help-contact-us-network' === $screen->base ||
			'wp-2fa_page_wp-2fa-premium-features' === $screen->base || 'wp-2fa_page_wp-2fa-premium-features-network' === $screen->base ||
			'wp-2fa_page_wp-2fa-policies-account' === $screen->base || 'wp-2fa_page_wp-2fa-policies-account' === $screen->base );

			if ( $correct_screen && Settings_Utils::get_option( Abstract_Migration::UPGRADE_NOTICE, false ) ) {
				include_once WP_2FA_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Free' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugin-update-card.php';
				?>
					<script type="text/javascript">
					//<![CDATA[
					jQuery(document).ready(function( $ ) {
						jQuery( 'body' ).on( 'click', '.wp-2fa-plugin-update-close', function ( e ) {
							e.preventDefault();
							var nonce  = jQuery( '.wp-2fa-plugin-update' ).data( 'nonce' );
							
							jQuery.ajax({
								type: 'POST',
								url: '<?php echo esc_url( \admin_url( 'admin-ajax.php' ) ); ?>',
								data: {
									action: 'dismiss_update_notice',
									nonce : nonce,
								},
								success: function ( result ) {		
									jQuery( '.wp-2fa-plugin-update' ).slideUp( 300 );
								}
							});
						});
					});
					//]]>
					</script>
				<?php
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
