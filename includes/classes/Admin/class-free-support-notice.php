<?php
/**
 * Shows a support notice banner to free users on the new interface.
 *
 * @package    wp2fa
 * @subpackage admin
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin;

use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Settings_Page;
use WP2FA\Licensing\Licensing_Factory;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Free_Support_Notice' ) ) {

	/**
	 * Displays a dismissible info banner offering free email support to free edition users
	 * who are using the new interface (fresh installs or upgrades that switched).
	 *
	 * @since 4.0.0
	 */
	class Free_Support_Notice {

		/**
		 * Option name for dismiss state.
		 *
		 * @var string
		 */
		private const DISMISS_OPTION = 'wp_2fa_free_support_notice_dismissed';

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 */
		public static function init() {
			// Only for free users.
			if ( Licensing_Factory::has_active_valid_license() ) {
				return;
			}

			// Only when new interface is enabled.
			if ( ! Settings_Page::is_new_interface_enabled() ) {
				return;
			}

			// Already dismissed.
			if ( Settings_Utils::get_option( self::DISMISS_OPTION, false ) ) {
				return;
			}

			\add_action( 'admin_notices', array( __CLASS__, 'render_banner' ) );
			\add_action( 'network_admin_notices', array( __CLASS__, 'render_banner' ) );
			\add_action( 'wp_ajax_wp2fa_dismiss_free_support_notice', array( __CLASS__, 'ajax_dismiss' ) );
		}

		/**
		 * AJAX handler to dismiss the banner.
		 *
		 * @return void
		 */
		public static function ajax_dismiss() {
			$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';

			if ( ! \current_user_can( 'manage_options' ) || ! \wp_verify_nonce( $nonce, 'wp2fa_free_support_notice' ) ) {
				\wp_send_json_error( \esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			Settings_Utils::update_option( self::DISMISS_OPTION, true );

			\wp_send_json_success();
		}

		/**
		 * Renders the banner on plugin admin pages.
		 *
		 * @return void
		 */
		public static function render_banner() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			// Only show on WP 2FA plugin pages.
			$screen = \get_current_screen();
			if ( ! $screen || ! \in_array( $screen->id, WP_Helper::PLUGIN_PAGES, true ) ) {
				return;
			}

			$nonce = \wp_create_nonce( 'wp2fa_free_support_notice' );
			?>
			<div class="wp2fa-free-support-banner" id="wp2fa-free-support-banner">
				<div class="wp2fa-free-support-banner__content">
					<span class="wp2fa-free-support-banner__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
							<path d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm1 15H9v-2h2v2zm0-4H9V5h2v6z" fill="#2271b1"/>
						</svg>
					</span>
					<div class="wp2fa-free-support-banner__text">
						<strong><?php \esc_html_e( 'New interface. We\'re here to help.', 'wp-2fa' ); ?></strong>
						<span>
							<?php
							printf(
								/* translators: %s: email link */
								\esc_html__( 'WP 2FA 4.0 now has a completely redesigned interface. To help with the transition, we\'re temporarily offering free one-to-one email support to all users, including the free edition users. Contact us on %s if you need any assistance.', 'wp-2fa' ),
								'<a href="mailto:support@melapress.com">support@melapress.com</a>'
							);
							?>
						</span>
					</div>
				</div>
				<button type="button" class="wp2fa-free-support-banner__close" id="wp2fa-free-support-banner-close" aria-label="<?php \esc_attr_e( 'Dismiss', 'wp-2fa' ); ?>">&times;</button>
			</div>
			<style>
			.wp2fa-free-support-banner {
				display: flex;
				align-items: flex-start;
				justify-content: space-between;
				background: #f0f6fc;
				border: 1px solid #c3c4c7;
				border-left-color: #2271b1;
				border-left-width: 4px;
				border-radius: 2px;
				padding: 12px 16px;
				margin: 10px 20px 16px 0;
				gap: 12px;
			}
			.wp2fa-free-support-banner__content {
				display: flex;
				align-items: flex-start;
				gap: 10px;
				flex: 1;
				min-width: 0;
			}
			.wp2fa-free-support-banner__icon {
				flex-shrink: 0;
				margin-top: 2px;
			}
			.wp2fa-free-support-banner__icon svg {
				display: block;
			}
			.wp2fa-free-support-banner__text {
				font-size: 13px;
				color: #50575e;
				line-height: 1.6;
			}
			.wp2fa-free-support-banner__text strong {
				display: block;
				color: #1d2327;
				font-size: 14px;
				margin-bottom: 2px;
			}
			.wp2fa-free-support-banner__text a {
				color: #2271b1;
				text-decoration: underline;
			}
			.wp2fa-free-support-banner__close {
				background: none;
				border: none;
				font-size: 20px;
				line-height: 1;
				color: #787c82;
				cursor: pointer;
				padding: 4px 8px;
				border-radius: 3px;
				flex-shrink: 0;
			}
			.wp2fa-free-support-banner__close:hover,
			.wp2fa-free-support-banner__close:focus {
				color: #1d2327;
				background: #dcdcde;
				outline: none;
			}
			</style>
			<script>
			(function() {
				var banner = document.getElementById('wp2fa-free-support-banner');
				if (!banner) return;
				document.getElementById('wp2fa-free-support-banner-close').addEventListener('click', function() {
					banner.style.display = 'none';
					var xhr = new XMLHttpRequest();
					xhr.open('POST', '<?php echo \esc_url( \admin_url( 'admin-ajax.php' ) ); ?>', true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=wp2fa_dismiss_free_support_notice&nonce=' + encodeURIComponent('<?php echo \esc_attr( $nonce ); ?>'));
				});
			})();
			</script>
			<?php
		}
	}
}
