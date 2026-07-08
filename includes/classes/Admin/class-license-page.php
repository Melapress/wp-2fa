<?php
/**
 * License page rendering class.
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
use WP2FA\Licensing\Licensing_Factory;

if ( ! class_exists( '\WP2FA\Admin\License_Page' ) ) {

	/**
	 * Handles the License admin page.
	 *
	 * Provides a submenu item that allows users to activate or manage
	 * their license key when using Freemius as the licensing provider.
	 *
	 * @since 4.0.0
	 */
	class License_Page {

		const PAGE_SLUG = 'wp-2fa-license';

		/**
		 * Create admin submenu entry for the License page.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function add_extra_menu_item() {

			// Only show when Freemius is the active provider.
			if ( 'freemius' !== Licensing_Factory::get_provider_type() ) {
				return;
			}

			// Hide the License page when license is already active and valid.
			if ( Licensing_Factory::is_registered() && Licensing_Factory::has_active_valid_license() ) {
				return;
			}

			\add_submenu_page(
				Settings_Page::TOP_MENU_SLUG,
				\esc_html__( 'License', 'wp-2fa' ),
				\esc_html__( 'License', 'wp-2fa' ),
				'manage_options',
				self::PAGE_SLUG,
				array( __CLASS__, 'render' ),
				5
			);

			// Redirect to Freemius account page if user is registered and has valid license.
			\add_action( 'current_screen', array( __CLASS__, 'maybe_redirect' ) );
		}

		/**
		 * Register AJAX handlers. Must be called independently of menu registration
		 * since AJAX requests don't trigger admin_menu.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function register_ajax_handlers() {
			// Freemius handles its own license activation AJAX.
			// We just need to ensure the dialog is rendered on our page.
			\add_action( 'current_screen', array( __CLASS__, 'maybe_enqueue_freemius_dialog' ) );
		}

		/**
		 * Enqueue Freemius license activation dialog on our License page.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function maybe_enqueue_freemius_dialog() {
			$screen = \get_current_screen();
			if ( null === $screen || ! self::is_license_screen( $screen ) ) {
				return;
			}

			// Get the Freemius instance to render its license activation dialog.
			$fs = Licensing_Factory::provider_call( 'get_provider_instance' );
			if ( $fs && false !== $fs && \is_object( $fs ) ) {
				// Render the Freemius license activation dialog in footer.
				\add_action( 'admin_footer', array( $fs, '_add_license_activation_dialog_box' ) );
			}
		}

		/**
		 * Redirect to Freemius account page when user already has an active license.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function maybe_redirect() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			$screen = \get_current_screen();
			if ( null === $screen || ! self::is_license_screen( $screen ) ) {
				return;
			}

			// If user is registered and has a valid license, redirect to account page.
			if ( Licensing_Factory::is_registered() && Licensing_Factory::has_active_valid_license() ) {
				$account_url = Licensing_Factory::get_account_url();
				if ( ! empty( $account_url ) ) {
					\wp_safe_redirect( $account_url );
					exit;
				}
			}
		}

		/**
		 * Check if the current screen is the license page (single site or network admin).
		 *
		 * @param \WP_Screen $screen The current screen object.
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		private static function is_license_screen( $screen ) {
			$base_id = 'wp-2fa_page_' . self::PAGE_SLUG;

			return $screen->id === $base_id || $screen->id === $base_id . '-network';
		}

		/**
		 * Render the License page.
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

			$account_url = Licensing_Factory::get_account_url();
			$pricing_url = Licensing_Factory::get_pricing_url();

			echo '<div class="wrap">';
			echo '<h1>' . \esc_html__( 'License', 'wp-2fa' ) . '</h1>';

			if ( Licensing_Factory::has_active_valid_license() ) {
				echo '<div class="notice notice-success inline"><p>';
				echo \esc_html__( 'Your license is active and valid.', 'wp-2fa' );
				echo '</p></div>';
				if ( $account_url ) {
					echo '<p><a href="' . \esc_url( $account_url ) . '" class="button button-primary">';
					echo \esc_html__( 'Manage License', 'wp-2fa' );
					echo '</a></p>';
				}
			} else {
				self::render_license_activation_form();
			}

			echo '</div>';
		}

		/**
		 * Render the license activation form.
		 *
		 * Uses Freemius's own license activation dialog which handles
		 * the full activation flow including API communication.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function render_license_activation_form() {
			$pricing_url = Licensing_Factory::get_pricing_url();

			// Get the Freemius unique affix for the dialog trigger class.
			$fs           = Licensing_Factory::provider_call( 'get_provider_instance' );
			$unique_affix = ( $fs && false !== $fs && \is_object( $fs ) ) ? $fs->get_unique_affix() : 'wp-2fa';

			?>
			<div class="wp2fa-license-activation-wrap">
				<div class="notice notice-warning inline">
					<p><?php \esc_html_e( 'No active license found. Activate your license key below to unlock all premium features.', 'wp-2fa' ); ?></p>
				</div>

				<div class="wp2fa-license-form" style="margin-top: 20px;">
					<p>
						<a href="#" class="button button-primary activate-license-trigger <?php echo \esc_attr( $unique_affix ); ?>">
							<?php \esc_html_e( 'Activate License', 'wp-2fa' ); ?>
						</a>
					</p>
				</div>

				<p class="description" style="margin-top: 20px;">
					<?php
					printf(
						/* translators: %s: link to pricing page */
						\esc_html__( 'Don\'t have a license key? %s', 'wp-2fa' ),
						'<a href="' . \esc_url( $pricing_url ) . '" target="_blank">' . \esc_html__( 'Purchase one here', 'wp-2fa' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
		}

	}
}
