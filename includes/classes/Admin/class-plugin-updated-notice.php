<?php
/**
 * Responsible for WP2FA update notices.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

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
			add_action( 'admin_init', array( __CLASS__, 'on_plugin_update' ), 10 );
			add_action( 'admin_notices', array( __CLASS__, 'plugin_update_banner' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'plugin_update_banner' ) );
			add_action( 'wp_ajax_dismiss_update_notice', array( __CLASS__, 'dismiss_update_notice' ) );
		}

		/**
		 * The nag content
		 *
		 * @since 2.7.0
		 * @return void
		 */
		public static function plugin_update_banner() {
			$screen         = get_current_screen();
			$correct_screen = ( 'toplevel_page_wp-2fa-policies-network' === $screen->base || 'toplevel_page_wp-2fa-policies' === $screen->base ) ? true : false;

			if ( $correct_screen && Settings_Utils::get_option( 'wp_2fa_update_notice_needed', false ) ) {
				/* translators: %s: version number. */
				printf( '<div id="wp_2fa_update_notice" class="notice notice-success is-dismissible"><img src="' . esc_url( WP_2FA_URL . 'dist/images/wp-2fa-square.png' ) . '"><p><strong>' . esc_html__( 'Thank you for updating WP 2FA.', 'wp-2fa' ) . '</strong></p><p>' . esc_html__( 'This is version %s. Check out the release notes to see what is new and improved in this update.', 'wp-2fa' ) . '</p><a href="https://melapress.com/wordpress-2fa/releases/" target="_blank" class="button button-primary dismiss_update_notice" data-dismiss-nonce="%2s">' . esc_html__( 'Release notes', 'wp-2fa' ) . '</a></p></div>', WP_2FA_VERSION, wp_create_nonce( 'wp_2fa_dismiss_update_notice_nonce' ) );
				?>
					<script type="text/javascript">
					//<![CDATA[
					jQuery(document).ready(function( $ ) {
						jQuery( 'body' ).on( 'click', 'a.dismiss_update_notice, #wp_2fa_update_notice .notice-dismiss', function ( e ) {
							var nonce  = jQuery( '#wp_2fa_update_notice [data-dismiss-nonce]' ).attr( 'data-dismiss-nonce' );
							
							jQuery.ajax({
								type: 'POST',
								url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
								async: true,
								data: {
									action: 'dismiss_update_notice',
									nonce : nonce,
								},
								success: function ( result ) {		
									jQuery( '#wp_2fa_update_notice' ).slideUp( 300 );
								}
							});
						});
					});
					//]]>
					</script>
					<style>
						#wp_2fa_update_notice {
							border: 2px solid #0f5cf2
						}
						#wp_2fa_update_notice .button-primary {
							background: #0f5cf2;
							border-color: #0f5cf2;
						}
						#wp_2fa_update_notice img {
							float: left;
							max-width: 100px;
							margin: 10px 12px 10px 0;
						}
					</style>
				<?php
			}
		}

		/**
		 * Redirects user to admin on plugin update.
		 *
		 * @since 2.7.0
		 * @return void
		 */
		public static function on_plugin_update() {
			if ( Settings_Utils::get_option( 'wp_2fa_update_redirection_needed', false ) ) {
				delete_site_option( 'wp_2fa_update_redirection_needed' );
				update_site_option( 'wp_2fa_update_notice_needed', true );
				$args = array(
					'page' => 'wp-2fa-policies',
				);
				$url  = add_query_arg( $args, network_admin_url( 'admin.php' ) );
				wp_safe_redirect( $url );
				exit;
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
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : false;
			// Check nonce.
			if ( ! current_user_can( 'manage_options' ) || empty( $nonce ) || ! $nonce || ! wp_verify_nonce( $nonce, 'wp_2fa_dismiss_update_notice_nonce' ) ) {
				wp_send_json_error( esc_html__( 'Nonce Verification Failed.', 'wp-2fa' ) );
			}

			delete_site_option( 'wp_2fa_update_notice_needed' );

			wp_send_json_success( esc_html__( 'Complete.', 'wp-2fa' ) );
		}
	}
}
