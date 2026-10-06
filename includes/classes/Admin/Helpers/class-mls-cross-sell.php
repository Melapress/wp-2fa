<?php
/**
 * Cross-sell for Melapress Login Security.
 *
 * WP 2FA points at a second, free plugin in two places: the last step of the setup
 * wizard, and the white labeling page where customising the 2FA page URL is offered.
 * Both need the same three answers — is it installed, is it active, and can this user
 * put it there — so they are settled once here rather than in each screen.
 *
 * @package    wp-2fa
 * @since      4.2.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP2FA\Admin\Helpers\MLS_Cross_Sell' ) ) {
	/**
	 * Installs and reports on the companion plugin.
	 *
	 * @since 4.2.0
	 */
	class MLS_Cross_Sell {

		/**
		 * Plugin file, relative to the plugins directory.
		 */
		public const PLUGIN_FILE = 'melapress-login-security/melapress-login-security.php';

		/**
		 * Slug the plugin is published under on wordpress.org.
		 */
		public const PLUGIN_SLUG = 'melapress-login-security';

		/**
		 * AJAX action that installs and activates it.
		 */
		public const AJAX_ACTION = 'wp2fa_install_mls';

		/**
		 * Nonce action guarding that request.
		 */
		public const NONCE_ACTION = 'wp2fa_install_mls';

		/**
		 * Hook the installer up.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function init(): void {
			\add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_install' ) );
		}

		/**
		 * Whether the plugin is present on disk.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_installed(): bool {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugins = \get_plugins();

			return isset( $plugins[ self::PLUGIN_FILE ] );
		}

		/**
		 * Whether the plugin is installed and running.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_active(): bool {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			return \is_plugin_active( self::PLUGIN_FILE );
		}

		/**
		 * Whether the current user is allowed to put it there.
		 *
		 * Installing and activating are separate capabilities, and a site can be
		 * configured to forbid installs entirely (DISALLOW_FILE_MODS), so the offer is
		 * only made to someone who could actually accept it.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function current_user_can_install(): bool {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return false;
			}

			if ( self::is_installed() ) {
				return \current_user_can( 'activate_plugins' );
			}

			return \current_user_can( 'install_plugins' ) && \current_user_can( 'activate_plugins' );
		}

		/**
		 * Whether there is anything to offer at all.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function should_offer(): bool {
			if ( ! self::current_user_can_install() ) {
				return false;
			}

			/*
			 * On a network, installing is all this can do — activation belongs to the network
			 * dashboard — so the offer stops once the plugin is there, rather than repeating an
			 * install that has already happened.
			 */
			if ( \is_multisite() ) {
				return ! self::is_installed();
			}

			return ! self::is_active();
		}

		/**
		 * Installs the plugin when it is missing, then activates it.
		 *
		 * @return true|\WP_Error True once the plugin is active.
		 *
		 * @since 4.2.0
		 */
		public static function install() {
			if ( self::is_active() ) {
				return true;
			}

			if ( ! self::current_user_can_install() ) {
				return new \WP_Error( 'wp2fa_mls_forbidden', \__( 'You are not allowed to install plugins on this site.', 'wp-2fa' ) );
			}

			if ( ! self::is_installed() ) {
				$installed = self::download();

				if ( \is_wp_error( $installed ) ) {
					return $installed;
				}
			}

			/*
			 * On a network the plugin is installed but not activated from here.
			 *
			 * Melapress Login Security refuses to be activated anywhere but the network
			 * dashboard: its activation hook prints a notice telling the administrator to go
			 * there and ends the request. Called from admin-ajax that takes the whole response
			 * with it, so the wizard's background request would return that plugin's HTML
			 * instead of an answer — and nothing would be installed or activated.
			 *
			 * Downloading it is still worth doing, and is the slow part. Activation is one
			 * click on a screen the super admin already has.
			 */
			if ( \is_multisite() ) {
				return true;
			}

			$activated = \activate_plugin( self::PLUGIN_FILE );

			if ( \is_wp_error( $activated ) ) {
				return $activated;
			}

			return true;
		}

		/**
		 * Fetches the plugin from wordpress.org and unpacks it.
		 *
		 * @return true|\WP_Error
		 *
		 * @since 4.2.0
		 */
		private static function download() {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

			$api = \plugins_api(
				'plugin_information',
				array(
					'slug'   => self::PLUGIN_SLUG,
					'fields' => array( 'sections' => false ),
				)
			);

			if ( \is_wp_error( $api ) ) {
				return $api;
			}

			if ( empty( $api->download_link ) ) {
				return new \WP_Error( 'wp2fa_mls_no_package', \__( 'The plugin could not be downloaded.', 'wp-2fa' ) );
			}

			/*
			 * Automatic_Upgrader_Skin prints nothing and asks nothing, which is what a
			 * background install needs: the wizard has already moved on, and there is no
			 * screen left for an upgrader to write its progress to.
			 */
			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->install( $api->download_link );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			if ( true !== $result ) {
				return new \WP_Error( 'wp2fa_mls_install_failed', \__( 'The plugin could not be installed.', 'wp-2fa' ) );
			}

			return true;
		}

		/**
		 * AJAX endpoint behind the wizard toggle and the white labeling button.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function ajax_install(): void {
			\check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			if ( ! self::current_user_can_install() ) {
				\wp_send_json_error(
					array( 'message' => \__( 'You are not allowed to install plugins on this site.', 'wp-2fa' ) ),
					403
				);
			}

			$result = self::install();

			if ( \is_wp_error( $result ) ) {
				\wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			\wp_send_json_success(
				array(
					'message' => \is_multisite()
						? \__( 'Melapress Login Security is installed. Activate it from the network dashboard.', 'wp-2fa' )
						: \__( 'Melapress Login Security is installed and active.', 'wp-2fa' ),
				)
			);
		}
	}
}
