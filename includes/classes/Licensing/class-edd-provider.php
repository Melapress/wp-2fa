<?php
/**
 * Easy Digital Downloads (EDD) Licensing Provider for WP2FA plugin.
 *
 * Implements licensing through Easy Digital Downloads Software Licensing extension.
 * This provider handles license activation, validation, and updates through the EDD API.
 *
 * @since      3.2.0
 * @package    wp2fa
 * @subpackage Licensing
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Licensing;

if ( ! class_exists( '\WP2FA\Licensing\EDD_Provider' ) ) {

	/**
	 * EDD licensing provider implementation.
	 *
	 * @since 3.2.0
	 */
	class EDD_Provider implements Licensing_Provider {

		/**
		 * Option name for storing license key.
		 *
		 * @var string
		 */
		const LICENSE_KEY_OPTION = 'wp2fa_edd_license_key';

		/**
		 * Option name for storing license status.
		 *
		 * @var string
		 */
		const LICENSE_STATUS_OPTION = 'wp2fa_edd_license_status';

		/**
		 * Option name for storing license data.
		 *
		 * @var string
		 */
		const LICENSE_DATA_OPTION = 'wp2fa_edd_license_data';

		/**
		 * Transient name for caching license checks.
		 *
		 * @var string
		 */
		const LICENSE_CHECK_TRANSIENT = 'wp2fa_edd_license_check';

		/**
		 * EDD store URL.
		 *
		 * @var string
		 */
		const STORE_URL = 'https://melapress.com';

		/**
		 * EDD product name/ID.
		 *
		 * @var string
		 */
		const ITEM_NAME = 'WP 2FA Premium';

		/**
		 * EDD product ID (alternative to item name).
		 *
		 * @var int
		 */
		const ITEM_ID = 0; // Set to actual product ID if known.

		/**
		 * Cache for license data.
		 *
		 * @var array|null
		 * @since 3.2.0
		 */
		private static $license_data = null;

		/**
		 * Initialize the EDD licensing provider.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function init() {
			if ( ! self::is_available() ) {
				return;
			}

			// Hook into admin_init to check license status.
			add_action( 'admin_init', array( __CLASS__, 'maybe_check_license' ) );

			// Hook for plugin updates.
			add_action( 'admin_init', array( __CLASS__, 'setup_updater' ), 0 );

			// Admin notices.
			add_action( 'admin_notices', array( __CLASS__, 'license_notices' ) );

			// AJAX handler for license activation/deactivation.
			add_action( 'wp_ajax_wp2fa_edd_activate_license', array( __CLASS__, 'ajax_activate_license' ) );
			add_action( 'wp_ajax_wp2fa_edd_deactivate_license', array( __CLASS__, 'ajax_deactivate_license' ) );
		}

		/**
		 * Check if the license is active and valid.
		 *
		 * @return bool True if license is active and valid, false otherwise.
		 * @since 3.2.0
		 */
		public static function has_active_valid_license(): bool {
			$status = get_option( self::LICENSE_STATUS_OPTION );
			return 'valid' === $status;
		}

		/**
		 * Check if the premium version is active.
		 *
		 * @return bool True if premium is active, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_premium(): bool {
			return self::has_active_valid_license();
		}

		/**
		 * Get the provider instance.
		 *
		 * @return null Always returns null for static provider.
		 * @since 3.2.0
		 */
		public static function get_provider_instance() {
			return null;
		}

		/**
		 * Check if the plugin is registered (has a license key).
		 *
		 * @return bool True if registered, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_registered(): bool {
			$license_key = get_option( self::LICENSE_KEY_OPTION );
			return ! empty( $license_key );
		}

		/**
		 * Get the license data.
		 *
		 * @return mixed License data array or null.
		 * @since 3.2.0
		 */
		public static function get_license() {
			if ( null !== self::$license_data ) {
				return self::$license_data;
			}

			self::$license_data = get_option( self::LICENSE_DATA_OPTION );

			if ( ! is_array( self::$license_data ) ) {
				self::$license_data = array();
			}

			return self::$license_data;
		}

		/**
		 * Get the license quota.
		 *
		 * @return int Number of allowed activations/sites.
		 * @since 3.2.0
		 */
		public static function get_license_quota(): int {
			$license_data = self::get_license();

			if ( isset( $license_data['license_limit'] ) ) {
				return (int) $license_data['license_limit'];
			}

			return -1;
		}

		/**
		 * Check if license quota has been exceeded.
		 *
		 * @return bool True if quota exceeded, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_quota_exceeded(): bool {
			$license_data = self::get_license();

			if ( ! isset( $license_data['activations_left'] ) ) {
				return false;
			}

			return (int) $license_data['activations_left'] <= 0;
		}

		/**
		 * Get the pricing page URL.
		 *
		 * @return string Pricing page URL.
		 * @since 3.2.0
		 */
		public static function get_pricing_url(): string {
			return self::STORE_URL . '/wordpress-2fa/pricing/';
		}

		/**
		 * Get the account/dashboard URL.
		 *
		 * @return string Account URL.
		 * @since 3.2.0
		 */
		public static function get_account_url(): string {
			return self::STORE_URL . '/account/';
		}

		/**
		 * Sync/refresh the license status.
		 *
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 */
		public static function sync_license(): bool {
			$license_key = get_option( self::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return false;
			}

			delete_transient( self::LICENSE_CHECK_TRANSIENT );

			return self::check_license( $license_key );
		}

		/**
		 * Activate a license key.
		 *
		 * @param string $license_key The license key to activate.
		 * @return bool|array True on success, array with error info on failure.
		 * @since 3.2.0
		 */
		public static function activate_license( string $license_key ) {
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license_key,
				'item_name'  => rawurlencode( self::ITEM_NAME ),
				'url'        => home_url(),
			);

			if ( self::ITEM_ID > 0 ) {
				$api_params['item_id'] = self::ITEM_ID;
			}

			$response = wp_remote_post(
				self::STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return array(
					'success' => false,
					'message' => is_wp_error( $response ) ? $response->get_error_message() : __( 'An error occurred, please try again.', 'wp-2fa' ),
				);
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $license_data ) ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid response from license server.', 'wp-2fa' ),
				);
			}

			// Store license key and data.
			update_option( self::LICENSE_KEY_OPTION, $license_key );
			update_option( self::LICENSE_DATA_OPTION, $license_data );

			if ( isset( $license_data['license'] ) && 'valid' === $license_data['license'] ) {
				update_option( self::LICENSE_STATUS_OPTION, 'valid' );
				delete_transient( self::LICENSE_CHECK_TRANSIENT );
				return true;
			}

			// License activation failed.
			$error_message = isset( $license_data['error'] ) ? $license_data['error'] : __( 'License activation failed.', 'wp-2fa' );

			return array(
				'success' => false,
				'message' => $error_message,
				'code'    => isset( $license_data['error'] ) ? $license_data['error'] : 'activation_failed',
			);
		}

		/**
		 * Deactivate the current license.
		 *
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 */
		public static function deactivate_license(): bool {
			$license_key = get_option( self::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return false;
			}

			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license_key,
				'item_name'  => rawurlencode( self::ITEM_NAME ),
				'url'        => home_url(),
			);

			if ( self::ITEM_ID > 0 ) {
				$api_params['item_id'] = self::ITEM_ID;
			}

			$response = wp_remote_post(
				self::STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $license_data['license'] ) && 'deactivated' === $license_data['license'] ) {
				delete_option( self::LICENSE_STATUS_OPTION );
				delete_option( self::LICENSE_KEY_OPTION );
				delete_option( self::LICENSE_DATA_OPTION );
				delete_transient( self::LICENSE_CHECK_TRANSIENT );
				return true;
			}

			return false;
		}

		/**
		 * Get the provider name.
		 *
		 * @return string Provider name.
		 * @since 3.2.0
		 */
		public static function get_provider_name(): string {
			return 'edd';
		}

		/**
		 * Check if EDD provider is available.
		 *
		 * This checks if EDD licensing is configured (not if Freemius is available).
		 *
		 * @return bool True if EDD provider is available, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_available(): bool {
			// EDD is always available as a fallback option.
			// Could add a filter here to allow enabling/disabling via configuration.
			return apply_filters( 'wp2fa_edd_provider_available', false );
		}

		/**
		 * Get the plugin basename.
		 *
		 * @return string Plugin basename.
		 * @since 3.2.0
		 */
		public static function get_plugin_basename(): string {
			return plugin_basename( WP_2FA_FILE );
		}

		/**
		 * Add an action hook (WordPress standard).
		 *
		 * @param string   $tag      The action hook name.
		 * @param callable|string $callback The callback function.
		 * @param int      $priority Priority.
		 * @param int      $args     Number of arguments.
		 * @return void
		 * @since 3.2.0
		 */
		public static function add_action( string $tag, $callback, int $priority = 10, int $args = 1 ) {
			add_action( $tag, $callback, $priority, $args );
		}

		/**
		 * Add a filter hook (WordPress standard).
		 *
		 * @param string   $tag      The filter hook name.
		 * @param callable $callback The callback function.
		 * @param int      $priority Priority.
		 * @param int      $args     Number of arguments.
		 * @return void
		 * @since 3.2.0
		 */
		public static function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ) {
			add_filter( $tag, $callback, $priority, $args );
		}

		/**
		 * Check license status with EDD API.
		 *
		 * @param string $license_key The license key to check.
		 * @return bool True if valid, false otherwise.
		 * @since 3.2.0
		 */
		private static function check_license( string $license_key ): bool {
			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license_key,
				'item_name'  => rawurlencode( self::ITEM_NAME ),
				'url'        => home_url(),
			);

			if ( self::ITEM_ID > 0 ) {
				$api_params['item_id'] = self::ITEM_ID;
			}

			$response = wp_remote_post(
				self::STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $license_data ) ) {
				return false;
			}

			update_option( self::LICENSE_DATA_OPTION, $license_data );

			if ( isset( $license_data['license'] ) ) {
				update_option( self::LICENSE_STATUS_OPTION, $license_data['license'] );
				set_transient( self::LICENSE_CHECK_TRANSIENT, $license_data['license'], DAY_IN_SECONDS );
				return 'valid' === $license_data['license'];
			}

			return false;
		}

		/**
		 * Maybe check license status (runs on admin_init).
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function maybe_check_license() {
			if ( wp_doing_ajax() ) {
				return;
			}

			$license_key = get_option( self::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return;
			}

			$cached_status = get_transient( self::LICENSE_CHECK_TRANSIENT );

			if ( false !== $cached_status ) {
				return;
			}

			self::check_license( $license_key );
		}

		/**
		 * Setup the EDD updater.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function setup_updater() {
			$license_key = get_option( self::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return;
			}

			// Check if EDD_SL_Plugin_Updater class exists.
			if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
				// Include the updater class if needed.
				$updater_file = WP_2FA_PATH . 'includes/libraries/EDD_SL_Plugin_Updater.php';
				if ( file_exists( $updater_file ) ) {
					require_once $updater_file;
				}
			}

			if ( class_exists( 'EDD_SL_Plugin_Updater' ) ) {
				$updater = new \EDD_SL_Plugin_Updater(
					self::STORE_URL,
					WP_2FA_FILE,
					array(
						'version' => WP_2FA_VERSION,
						'license' => $license_key,
						'item_id' => self::ITEM_ID > 0 ? self::ITEM_ID : false,
						'item_name' => self::ITEM_NAME,
						'author'  => 'Melapress',
						'beta'    => false,
					)
				);
			}
		}

		/**
		 * Display license notices.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function license_notices() {
			$status = get_option( self::LICENSE_STATUS_OPTION );

			if ( 'expired' === $status ) {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: %s: account URL */
					esc_html__( 'Your WP 2FA Premium license has expired. Please %s to renew your license and continue receiving updates and support.', 'wp-2fa' ),
					'<a href="' . esc_url( self::get_account_url() ) . '" target="_blank">' . esc_html__( 'renew your license', 'wp-2fa' ) . '</a>'
				);
				echo '</p></div>';
			} elseif ( 'invalid' === $status ) {
				echo '<div class="notice notice-warning"><p>';
				esc_html_e( 'Your WP 2FA Premium license is invalid. Please check your license key or contact support.', 'wp-2fa' );
				echo '</p></div>';
			}
		}

		/**
		 * AJAX handler for license activation.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function ajax_activate_license() {
			check_ajax_referer( 'wp2fa_edd_license', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

			if ( empty( $license_key ) ) {
				wp_send_json_error( array( 'message' => __( 'License key is required.', 'wp-2fa' ) ) );
			}

			$result = self::activate_license( $license_key );

			if ( true === $result ) {
				wp_send_json_success( array( 'message' => __( 'License activated successfully.', 'wp-2fa' ) ) );
			} else {
				wp_send_json_error( $result );
			}
		}

		/**
		 * AJAX handler for license deactivation.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function ajax_deactivate_license() {
			check_ajax_referer( 'wp2fa_edd_license', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$result = self::deactivate_license();

			if ( $result ) {
				wp_send_json_success( array( 'message' => __( 'License deactivated successfully.', 'wp-2fa' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to deactivate license.', 'wp-2fa' ) ) );
			}
		}
	}
}
