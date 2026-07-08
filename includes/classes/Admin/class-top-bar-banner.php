<?php
/**
 * Top bar banner displayed on all WP 2FA admin pages.
 *
 * @package    wp2fa
 * @subpackage admin
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Licensing\Licensing_Factory;

if ( ! class_exists( '\WP2FA\Admin\Top_Bar_Banner' ) ) {
	/**
	 * Top_Bar_Banner - Renders a persistent top bar on all plugin admin pages.
	 *
	 * @since 4.0.0
	 */
	class Top_Bar_Banner {

		/**
		 * Initialize the top bar banner hooks.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function init() {
			\add_action( 'in_admin_header', array( __CLASS__, 'render' ) );
			\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		}

		/**
		 * Enqueue the top bar CSS only on plugin pages.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function enqueue_styles() {
			$screen = \get_current_screen();
			if ( ! $screen || ! \in_array( $screen->base, WP_Helper::PLUGIN_PAGES, true ) ) {
				return;
			}

			\wp_enqueue_style(
				'wp2fa-top-bar-banner',
				WP_2FA_URL . 'includes/assets/css/top-bar-banner.css',
				array(),
				WP_2FA_VERSION
			);
		}

		/**
		 * Render the top bar banner.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function render() {
			$screen = \get_current_screen();
			if ( ! $screen || ! \in_array( $screen->base, WP_Helper::PLUGIN_PAGES, true ) ) {
				return;
			}

			$is_premium    = self::is_premium();
			$update_info   = self::get_update_info();
			$has_update    = ! empty( $update_info );
			$update_url    = $has_update ? self::get_update_url() : '';
			$new_version   = $has_update ? $update_info['new_version'] : '';
			$release_url   = 'https://melapress.com/wordpress-2fa/releases/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=topbar_releasenotes';
			$changelog_url = 'https://melapress.com/support/kb/wp-2fa-plugin-changelog/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=topbar_changelog';
			$upgrade_url   = 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=upgrade_to_premium_top_banner';
			?>
			<div id="wp2fa-top-bar-banner">
				<div class="wp2fa-top-bar-inner">
					<div class="wp2fa-top-bar-left">
						<img src="<?php echo \esc_url( WP_2FA_URL . 'dist/images/wizard-logo.png' ); ?>" alt="WP 2FA" class="wp2fa-top-bar-logo">
						<span class="wp2fa-top-bar-title">WP 2FA</span>
						<?php if ( $has_update ) : ?>
							<span class="wp2fa-top-bar-update-badge">
								<span class="wp2fa-update-dot"></span>
								<?php
								printf(
									/* translators: %s: new version number */
									\esc_html__( 'Version %s available!', 'wp-2fa' ),
									\esc_html( $new_version )
								);
								?>
								<a href="<?php echo \esc_url( $update_url ); ?>" class="wp2fa-update-link"><?php \esc_html_e( 'Update Now', 'wp-2fa' ); ?></a>
							</span>
						<?php endif; ?>
					</div>
					<div class="wp2fa-top-bar-right">
						<span class="wp2fa-top-bar-version"><?php echo \esc_html( 'v.' . WP_2FA_VERSION ); ?></span>
						<span class="wp2fa-top-bar-separator">|</span>
						<a href="<?php echo \esc_url( $release_url ); ?>" class="wp2fa-top-bar-link" target="_blank" rel="noopener noreferrer"><?php \esc_html_e( 'Release Notes', 'wp-2fa' ); ?></a>
						<span class="wp2fa-top-bar-separator">|</span>
						<a href="<?php echo \esc_url( $changelog_url ); ?>" class="wp2fa-top-bar-link" target="_blank" rel="noopener noreferrer"><?php \esc_html_e( 'Changelog', 'wp-2fa' ); ?></a>
						<?php if ( ! $is_premium ) : ?>
							<a href="<?php echo \esc_url( $upgrade_url ); ?>" class="wp2fa-top-bar-upgrade" target="_blank" rel="noopener noreferrer"><?php \esc_html_e( 'Upgrade to Premium', 'wp-2fa' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Check if the current installation is premium.
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		private static function is_premium(): bool {
			if ( class_exists( '\WP2FA\Licensing\Licensing_Factory' ) ) {
				return Licensing_Factory::has_active_valid_license();
			}
			return false;
		}

		/**
		 * Get plugin update information if available.
		 *
		 * @return array Empty array if no update, otherwise contains 'new_version'.
		 *
		 * @since 4.0.0
		 */
		private static function get_update_info(): array {
			$update_plugins = \get_site_transient( 'update_plugins' );
			if ( ! empty( $update_plugins->response ) && isset( $update_plugins->response[ WP_2FA_BASE ] ) ) {
				$plugin_data = $update_plugins->response[ WP_2FA_BASE ];
				return array(
					'new_version' => isset( $plugin_data->new_version ) ? $plugin_data->new_version : '',
				);
			}
			return array();
		}

		/**
		 * Get the URL to trigger the plugin update.
		 *
		 * @return string
		 *
		 * @since 4.0.0
		 */
		private static function get_update_url(): string {
			return \wp_nonce_url(
				\self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . WP_2FA_BASE ),
				'upgrade-plugin_' . WP_2FA_BASE
			);
		}
	}
}
