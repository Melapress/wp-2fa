<?php
/**
 * Tells the administrator of a new install that the setup wizard was skipped.
 *
 * @package    wp2fa
 * @subpackage admin
 * @since      4.3.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin;

use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\SettingsPages\Setup_Wizard_New;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Wizard_Skipped_Notice' ) ) {

	/**
	 * A notice on the plugin's pages, after the first-install wizard was skipped, that 2FA is not enforced yet.
	 *
	 * Only a new install is ever flagged: the flag is raised where the plugin
	 * applies the defaults for a wizard that was offered and left unfinished,
	 * and the wizard is only offered on the first install. The notice goes
	 * away for good when dismissed, and stops showing once the policies are
	 * configured - from the wizard or the settings - as it would no longer be
	 * true.
	 *
	 * @since 4.3.0
	 */
	class Wizard_Skipped_Notice {

		/**
		 * Raised when the wizard is skipped on a new install.
		 *
		 * @since 4.3.0
		 */
		public const SHOW_OPTION = 'wizard_skipped_notice';

		/**
		 * Raised when the notice is dismissed; it is never shown again after that.
		 *
		 * @since 4.3.0
		 */
		public const DISMISSED_OPTION = 'wizard_skipped_notice_dismissed';

		/**
		 * The query argument of the dismiss link.
		 *
		 * @since 4.3.0
		 */
		private const DISMISS_ARG = 'wp2fa_dismiss_wizard_skipped';

		/**
		 * The nonce action of the dismiss link.
		 *
		 * @since 4.3.0
		 */
		private const NONCE_ACTION = 'wp2fa_dismiss_wizard_skipped_notice';

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 *
		 * @since 4.3.0
		 */
		public static function init() {
			if ( ! self::is_pending() ) {
				return;
			}

			\add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
			\add_action( 'admin_notices', array( __CLASS__, 'render' ) );
			\add_action( 'network_admin_notices', array( __CLASS__, 'render' ) );
		}

		/**
		 * Flags the notice, unless it was dismissed before. Called when a new install's wizard is skipped.
		 *
		 * @return void
		 *
		 * @since 4.3.0
		 */
		public static function flag() {
			if ( Settings_Utils::get_option( self::DISMISSED_OPTION, false ) ) {
				return;
			}

			Settings_Utils::update_option( self::SHOW_OPTION, true );
		}

		/**
		 * Whether there is a notice to show: flagged, not dismissed, and the policies still the defaults.
		 *
		 * @return bool
		 *
		 * @since 4.3.0
		 */
		public static function is_pending(): bool {
			return (bool) Settings_Utils::get_option( self::SHOW_OPTION, false )
				&& ! Settings_Utils::get_option( self::DISMISSED_OPTION, false )
				&& (bool) Settings_Utils::get_option( 'default_settings_applied', false );
		}

		/**
		 * Dismisses the notice for good when its dismiss link is followed.
		 *
		 * @return void
		 *
		 * @since 4.3.0
		 */
		public static function maybe_dismiss() {
			if ( ! isset( $_GET[ self::DISMISS_ARG ] ) || ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			\check_admin_referer( self::NONCE_ACTION );

			Settings_Utils::update_option( self::DISMISSED_OPTION, true );
			Settings_Utils::delete_option( self::SHOW_OPTION );

			\wp_safe_redirect( \remove_query_arg( array( self::DISMISS_ARG, '_wpnonce' ) ) );
			exit;
		}

		/**
		 * Renders the notice on the plugin's own pages.
		 *
		 * @return void
		 *
		 * @since 4.3.0
		 */
		public static function render() {
			if ( ! \current_user_can( 'manage_options' ) || ! WP_Helper::is_admin_page() || ! self::is_pending() ) {
				return;
			}

			$launch_url  = Setup_Wizard_New::launch_url();
			$dismiss_url = \wp_nonce_url( \add_query_arg( self::DISMISS_ARG, '1' ), self::NONCE_ACTION );
			?>
			<div class="notice notice-warning wp2fa-wizard-skipped-notice">
				<p><?php \esc_html_e( 'You skipped the WP 2FA setup wizard, so 2FA is currently not enforced for any user roles. You can launch the setup wizard again by clicking the button below, or at any time from the plugin settings.', 'wp-2fa' ); ?></p>
				<p>
					<a href="<?php echo \esc_url( $launch_url ); ?>" class="button button-primary"><?php \esc_html_e( 'Launch setup wizard now', 'wp-2fa' ); ?></a>
					<a href="<?php echo \esc_url( $dismiss_url ); ?>" class="button"><?php \esc_html_e( 'Dismiss this notice', 'wp-2fa' ); ?></a>
				</p>
			</div>
			<?php
		}
	}
}
