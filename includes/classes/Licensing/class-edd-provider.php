<?php
/**
 * Easy Digital Downloads (EDD) Licensing Provider.
 *
 * Implements licensing through Easy Digital Downloads Software Licensing extension.
 * This provider handles license activation, validation, and updates through the EDD API.
 *
 * Plugin-specific configuration lives in Licensing_Factory. This class only
 * contains EDD-specific implementation constants (store URL, product IDs,
 * option keys, etc.).
 *
 * @since      2.0.0
 * @package    wp2fa
 * @subpackage Licensing
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Licensing;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP2FA\Licensing\EDD_Provider' ) ) {

	/**
	 * EDD licensing provider implementation.
	 *
	 * @since 2.0.0
	 */
	class EDD_Provider implements Licensing_Provider {

		/*
		|----------------------------------------------------------------------
		| EDD-specific configuration.
		|
		| Plugin-wide constants (TEXT_DOMAIN, PLUGIN_NAME, SLUG_PREFIX, etc.)
		| live in Licensing_Factory. Only EDD implementation details below.
		|----------------------------------------------------------------------
		*/

		/**
		 * EDD store URL.
		 *
		 * @var string
		 */
		public const STORE_URL = 'https://store.melapress.com/';

		/**
		 * EDD product ID for the Premium tier.
		 *
		 * TODO(review): 0 is a placeholder — WP 2FA's EDD products do not exist
		 * yet. Every store call that sends an item ID (activation, deactivation,
		 * version checks) will be rejected until real IDs are set here, which is
		 * why EDD is left unavailable by default. Set both before enabling it.
		 *
		 * @var int
		 */
		public const ITEM_ID_PREMIUM = 271;

		/**
		 * EDD product ID for the Enterprise tier.
		 *
		 * TODO(review): placeholder — see ITEM_ID_PREMIUM above.
		 *
		 * @var int
		 */
		public const ITEM_ID_ENTERPRISE = 274;

		/**
		 * Prefix for AJAX action names.
		 *
		 * @var string
		 */
		public const AJAX_PREFIX = 'wp2fa_edd_';

		/**
		 * Nonce action name for license AJAX requests.
		 *
		 * @var string
		 */
		public const NONCE_ACTION = 'wp2fa_edd_license';

		/**
		 * Script handle for the license JS.
		 *
		 * @var string
		 */
		public const SCRIPT_HANDLE = 'edd-licensing';

		/*
		|----------------------------------------------------------------------
		| Option / transient keys (change prefix when adapting for new plugin).
		|----------------------------------------------------------------------
		*/

		/**
		 * Option name for storing license key.
		 *
		 * @var string
		 */
		public const LICENSE_KEY_OPTION = 'wp2fa_edd_license_key';

		/**
		 * Option name for storing license status.
		 *
		 * @var string
		 */
		public const LICENSE_STATUS_OPTION = 'wp2fa_edd_license_status';

		/**
		 * Option name for storing license data.
		 *
		 * @var string
		 */
		public const LICENSE_DATA_OPTION = 'wp2fa_edd_license_data';

		/**
		 * Transient name for caching license checks.
		 *
		 * @var string
		 */
		public const LICENSE_CHECK_TRANSIENT = 'wp2fa_edd_license_check';

		/**
		 * Option name for premium status fast-path check.
		 *
		 * @var string
		 */
		public const PREMIUM_OPTION = 'wp2fa_edd_premium';

		/**
		 * License check interval in seconds (48 hours).
		 * Controls how often the license status is re-validated with the store.
		 *
		 * @var int
		 */
		public const LICENSE_CHECK_INTERVAL = 2 * DAY_IN_SECONDS;

		/**
		 * Option holding the timestamp of the last conclusive "valid" answer
		 * from the store. Used to bound how long a previously valid license
		 * keeps working while the store cannot be reached or answers
		 * inconclusively.
		 *
		 * @var string
		 */
		public const LAST_VALID_OPTION = 'wp2fa_edd_license_last_valid';

		/**
		 * How long a previously valid license keeps working when the store
		 * cannot be reached, or answers with something that may be about this
		 * request rather than about the license itself.
		 *
		 * @var int
		 */
		public const CHECK_GRACE_PERIOD = 7 * DAY_IN_SECONDS;

		/**
		 * How long to wait before retrying after an inconclusive check.
		 *
		 * Without this, a store that cannot be reached means a blocking remote
		 * POST on every single admin page load.
		 *
		 * @var int
		 */
		public const CHECK_RETRY_INTERVAL = HOUR_IN_SECONDS;

		/**
		 * Timeout for the background license check.
		 *
		 * Shorter than the activation timeout: nobody is waiting on this one,
		 * and an inconclusive result is handled gracefully.
		 *
		 * @var int
		 */
		public const CHECK_TIMEOUT = 5;

		/**
		 * Statuses that are a definite statement about the license itself.
		 *
		 * These are applied immediately. Every other non-valid status may be
		 * about this particular request (wrong URL sent by a proxy, product id
		 * missing from a truncated payload, store-side error) rather than about
		 * the license, so it goes through the grace window instead.
		 *
		 * @var string[]
		 */
		public const AUTHORITATIVE_NEGATIVE_STATUSES = array( 'expired', 'disabled', 'revoked' );

		public const CACHE_KEY_PREFIX = 'edd_sl_failed_http_';

		/**
		 * Cache for license data.
		 *
		 * @var array|null
		 * @since 2.4.0
		 */
		private static $license_data = null;

		/**
		 * Whether license state must be read from and written to another blog.
		 *
		 * On multisite the license belongs to the network and lives in the main
		 * site's options. Subsites have no copy of their own, so every read and
		 * write made from a subsite request goes to the main site.
		 *
		 * @return bool - True on a multisite subsite, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function uses_main_site_storage(): bool {
			return \is_multisite() && ! \is_main_site();
		}

		/**
		 * Get a license option, from the main site on multisite.
		 *
		 * @param string $name - The option name.
		 * @param mixed  $default_value - Value returned when the option does not exist.
		 *
		 * @return mixed - The option value.
		 *
		 * @since 4.2.0
		 */
		public static function get_license_option( string $name, $default_value = false ) {
			if ( self::uses_main_site_storage() ) {
				return \get_blog_option( \get_main_site_id(), $name, $default_value );
			}

			return \get_option( $name, $default_value );
		}

		/**
		 * Update a license option, on the main site on multisite.
		 *
		 * @param string $name - The option name.
		 * @param mixed  $value - The option value.
		 *
		 * @return bool - True if the value was updated, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function update_license_option( string $name, $value ): bool {
			if ( self::uses_main_site_storage() ) {
				return (bool) \update_blog_option( \get_main_site_id(), $name, $value );
			}

			return (bool) \update_option( $name, $value );
		}

		/**
		 * Delete a license option, on the main site on multisite.
		 *
		 * @param string $name - The option name.
		 *
		 * @return bool - True if the option was deleted, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function delete_license_option( string $name ): bool {
			if ( self::uses_main_site_storage() ) {
				return (bool) \delete_blog_option( \get_main_site_id(), $name );
			}

			return (bool) \delete_option( $name );
		}

		/**
		 * Get a license transient, from the main site on multisite.
		 *
		 * @param string $name - The transient name.
		 *
		 * @return mixed - The transient value, false when not set or expired.
		 *
		 * @since 4.2.0
		 */
		public static function get_license_transient( string $name ) {
			if ( ! self::uses_main_site_storage() ) {
				return \get_transient( $name );
			}

			\switch_to_blog( \get_main_site_id() );
			$value = \get_transient( $name );
			\restore_current_blog();

			return $value;
		}

		/**
		 * Set a license transient, on the main site on multisite.
		 *
		 * @param string $name - The transient name.
		 * @param mixed  $value - The transient value.
		 * @param int    $expiration - Time until expiration in seconds.
		 *
		 * @return bool - True if the value was set, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function set_license_transient( string $name, $value, int $expiration ): bool {
			if ( ! self::uses_main_site_storage() ) {
				return (bool) \set_transient( $name, $value, $expiration );
			}

			\switch_to_blog( \get_main_site_id() );
			$result = \set_transient( $name, $value, $expiration );
			\restore_current_blog();

			return (bool) $result;
		}

		/**
		 * Delete a license transient, on the main site on multisite.
		 *
		 * @param string $name - The transient name.
		 *
		 * @return bool - True if the transient was deleted, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function delete_license_transient( string $name ): bool {
			if ( ! self::uses_main_site_storage() ) {
				return (bool) \delete_transient( $name );
			}

			\switch_to_blog( \get_main_site_id() );
			$result = \delete_transient( $name );
			\restore_current_blog();

			return (bool) $result;
		}

		/**
		 * Initialize the EDD licensing provider.
		 *
		 * @return void
		 * @since 2.4.0
		 */
		public static function init() {
			if ( ! self::is_available() ) {
				return;
			}

			// Hook into admin_init to check license status.
			\add_action( 'admin_init', array( __CLASS__, 'maybe_check_license' ) );

			/**
			 * Runs on init, not admin_init: update checks also happen in WP-Cron, WP-CLI,
			 * REST and remote managers, where admin_init never fires.
			 */
			\add_action( 'init', array( __CLASS__, 'setup_updater' ), 0 );

			// Override plugin homepage URL in "View details" modal.
			\add_filter( 'plugins_api', array( __CLASS__, 'override_plugin_homepage' ), 20, 3 );

			// Admin notices — use network_admin_notices on multisite.
			\add_action( 'admin_notices', array( __CLASS__, 'license_notices' ) );
			if ( \is_multisite() ) {
				\add_action( 'network_admin_notices', array( __CLASS__, 'license_notices' ) );
			}

			// AJAX handler for license activation/deactivation/sync.
			\add_action( 'wp_ajax_' . self::AJAX_PREFIX . 'activate_license', array( __CLASS__, 'ajax_activate_license' ) );
			\add_action( 'wp_ajax_' . self::AJAX_PREFIX . 'deactivate_license', array( __CLASS__, 'ajax_deactivate_license' ) );
			\add_action( 'wp_ajax_' . self::AJAX_PREFIX . 'sync_license', array( __CLASS__, 'ajax_sync_license' ) );
			\add_action( 'wp_ajax_' . self::AJAX_PREFIX . 'get_activation_progress', array( __CLASS__, 'ajax_get_activation_progress' ) );

			// Initialize the license admin page.
			EDD_License_Page::init();

			// Initialize multisite lifecycle hooks.
			if ( \is_multisite() ) {
				EDD_Network_Licensing::init();
			}
		}

		/*
		|----------------------------------------------------------------------
		| Plugin runtime accessors (deprecated — prefer class constants).
		|
		| Kept for backward compatibility with code outside this directory.
		| Within the Licensing directory, use the class constants directly.
		|----------------------------------------------------------------------
		*/

		/**
		 * Get the main plugin file path.
		 *
		 * @deprecated Use Licensing_Factory::PLUGIN_FILE constant instead.
		 *
		 * @return string
		 * @since 2.4.0
		 */
		public static function get_plugin_file(): string {
			return Licensing_Factory::PLUGIN_FILE;
		}

		/**
		 * Get the plugin slug (directory name).
		 *
		 * @deprecated Use Licensing_Factory::PLUGIN_SLUG constant instead.
		 *
		 * @return string
		 * @since 2.4.0
		 */
		public static function get_plugin_slug(): string {
			return Licensing_Factory::PLUGIN_SLUG;
		}

		/**
		 * Get the plugin directory path.
		 *
		 * @deprecated Use Licensing_Factory::PLUGIN_PATH constant instead.
		 *
		 * @return string
		 * @since 2.4.0
		 */
		public static function get_plugin_path(): string {
			return Licensing_Factory::PLUGIN_PATH;
		}

		/**
		 * Get the plugin URL.
		 *
		 * @deprecated Use Licensing_Factory::PLUGIN_URL constant instead.
		 *
		 * @return string
		 * @since 2.4.0
		 */
		public static function get_plugin_url(): string {
			return Licensing_Factory::PLUGIN_URL;
		}

		/**
		 * Get the plugin version.
		 *
		 * @deprecated Use Licensing_Factory::PLUGIN_VERSION constant instead.
		 *
		 * @return string
		 * @since 2.4.0
		 */
		public static function get_plugin_version(): string {
			return Licensing_Factory::PLUGIN_VERSION;
		}

		/**
		 * Get the admin menu slug.
		 *
		 * @deprecated Use Licensing_Factory::MENU_SLUG constant instead.
		 *
		 * @return string
		 * @since 2.4.0
		 */
		public static function get_menu_slug(): string {
			return Licensing_Factory::MENU_SLUG;
		}

		/**
		 * Check if the license is active and valid.
		 *
		 * @return bool True if license is active and valid, false otherwise.
		 * @since 2.4.0
		 */
		public static function has_active_valid_license(): bool {
			$status = self::get_license_option( self::LICENSE_STATUS_OPTION );
			return 'valid' === $status;
		}

		/**
		 * Check if the premium version is active.
		 *
		 * Resource-cautious function that reads a cached option only.
		 * No remote calls are made here. The option is updated by
		 * maybe_check_license() when the transient expires.
		 *
		 * @return bool True if premium is active, false otherwise.
		 * @since 2.4.0
		 */
		public static function is_premium(): bool {
			return 'yes' === self::get_license_option( self::PREMIUM_OPTION );
		}

		/**
		 * Get the provider instance.
		 *
		 * @return null Always returns null for static provider.
		 * @since 2.4.0
		 */
		public static function get_provider_instance() {
			return null;
		}

		/**
		 * Check if the plugin is registered (has a license key).
		 *
		 * @return bool True if registered, false otherwise.
		 * @since 2.4.0
		 */
		public static function is_registered(): bool {
			$license_key = self::get_license_option( self::LICENSE_KEY_OPTION );
			return ! empty( $license_key );
		}

		/**
		 * Get the license data.
		 *
		 * @return mixed License data array or null.
		 * @since 2.4.0
		 */
		public static function get_license() {
			if ( null !== self::$license_data ) {
				return self::$license_data;
			}

			self::$license_data = self::get_license_option( self::LICENSE_DATA_OPTION );

			if ( ! is_array( self::$license_data ) ) {
				self::$license_data = array();
			}

			return self::$license_data;
		}

		/**
		 * Get the license quota.
		 *
		 * @return int Number of allowed activations/sites.
		 * @since 2.4.0
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
		 * @since 2.4.0
		 */
		public static function is_quota_exceeded(): bool {
			$license_data = self::get_license();

			if ( ! isset( $license_data['activations_left'] ) ) {
				return false;
			}

			return (int) $license_data['activations_left'] <= 0;
		}

		/**
		 * Check whether more activations are in use than the license allows.
		 *
		 * Unlike is_quota_exceeded(), a license that is exactly full is not over
		 * its limit, and an unlimited license never is.
		 *
		 * @return bool - True if activations_left is negative, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function is_over_activation_limit(): bool {
			$license_data = self::get_license_option( self::LICENSE_DATA_OPTION, array() );

			if ( ! is_array( $license_data ) || ! isset( $license_data['activations_left'] ) || ! is_numeric( $license_data['activations_left'] ) ) {
				return false;
			}

			return (int) $license_data['activations_left'] < 0;
		}

		/**
		 * Get the pricing page URL.
		 *
		 * @return string Pricing page URL.
		 * @since 2.4.0
		 */
		public static function get_pricing_url(): string {
			return Licensing_Factory::PRICING_URL;
		}

		/**
		 * Get the account/dashboard URL.
		 *
		 * @return string Account URL.
		 * @since 2.4.0
		 */
		public static function get_account_url(): string {
			return self::STORE_URL . 'my-account/';
		}

		/**
		 * Sync/refresh the license status.
		 *
		 * Routes to single-site or network sync based on multisite detection.
		 *
		 * @return bool True on success, false on failure.
		 *
		 * @since 2.4.0
		 */
		public static function sync_license(): bool {
			if ( \is_multisite() ) {
				return EDD_Network_Licensing::sync_network_license();
			}

			return self::sync_single_site_license();
		}

		/**
		 * Sync/refresh the license status for a single-site installation.
		 *
		 * @return bool True on success, false on failure.
		 *
		 * @since 2.4.0
		 */
		public static function sync_single_site_license(): bool {
			$license_key = self::get_license_option( self::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return false;
			}

			// Reset caches to force a fresh check.
			self::delete_license_transient( self::LICENSE_CHECK_TRANSIENT );
			self::delete_license_transient( self::PREMIUM_OPTION );
			self::$license_data = null;

			return self::check_license( $license_key );
		}

		/**
		 * Activate a license key.
		 *
		 * Routes to single-site or network activation based on multisite detection.
		 *
		 * @param string $license_key - The license key to activate.
		 *
		 * @return bool|array True on success, array with error info on failure.
		 *
		 * @since 2.4.0
		 */
		public static function activate_license( string $license_key ) {
			if ( \is_multisite() ) {
				return EDD_Network_Licensing::activate_network_license( $license_key );
			}

			return self::activate_single_site_license( $license_key );
		}

		/**
		 * Activate a license key for a single-site installation.
		 *
		 * Tries activation against Premium product first. If the key doesn't
		 * match (key_mismatch, item_name_mismatch, invalid_item_id), tries
		 * Enterprise. This is transparent to the user.
		 *
		 * @param string $license_key - The license key to activate.
		 *
		 * @return bool|array True on success, array with error info on failure.
		 *
		 * @since 2.4.0
		 */
		public static function activate_single_site_license( string $license_key ) {
			$item_ids        = array( self::ITEM_ID_PREMIUM, self::ITEM_ID_ENTERPRISE );
			$mismatch_errors = array( 'key_mismatch', 'item_name_mismatch', 'invalid_item_id', 'missing' );

			foreach ( $item_ids as $item_id ) {
				$result = self::try_activate_license( $license_key, $item_id );

				// If activation succeeded, fetch fresh license data and return.
				if ( true === $result ) {
					self::check_license( $license_key );
					return true;
				}

				// If the error is a product mismatch, try the next item_id.
				if ( is_array( $result ) && isset( $result['code'] ) && in_array( $result['code'], $mismatch_errors, true ) ) {
					continue;
				}

				// Any other error — return it to the user.
				return $result;
			}

			// All item_ids exhausted — return generic error.
			return array(
				'success' => false,
				'message' => \__( 'This license key is not valid for this product.', 'wp-2fa' ),
				'code'    => 'item_name_mismatch',
			);
		}

		/**
		 * Try to activate a license key against a specific EDD product.
		 *
		 * @param string $license_key - The license key to activate.
		 * @param int    $item_id     - The EDD product ID to try.
		 * @param string $url         - The site URL to activate against. Defaults to home_url().
		 * @param bool   $store_result - Whether to store the reply as this install's license state. Network code activating other subsites passes false.
		 *
		 * @return bool|array True on success, array with error info on failure.
		 *
		 * @since 2.4.0
		 * @since 4.2.0 Added the $store_result parameter.
		 */
		public static function try_activate_license( string $license_key, int $item_id, string $url = '', bool $store_result = true ) {
			if ( empty( $url ) ) {
				$url = \home_url();
			}

			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license_key,
				'item_id'    => $item_id,
				'url'        => $url,
			);

			$response = \wp_remote_post(
				self::STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
				return array(
					'success' => false,
					'message' => \is_wp_error( $response ) ? $response->get_error_message() : \__( 'An error occurred, please try again.', 'wp-2fa' ),
				);
			}

			$license_data = json_decode( \wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $license_data ) ) {
				return array(
					'success' => false,
					'message' => \__( 'Invalid response from license server.', 'wp-2fa' ),
				);
			}

			$is_valid   = isset( $license_data['license'] ) && 'valid' === $license_data['license'];
			$error_code = isset( $license_data['error'] ) ? $license_data['error'] : 'activation_failed';

			/**
			 * The reply is about one subsite URL, not the network license. Storing it
			 * would let one subsite's failure (e.g. no_activations_left) switch premium
			 * off for the whole network.
			 */
			if ( ! $store_result ) {
				if ( $is_valid ) {
					return true;
				}

				return array(
					'success' => false,
					'message' => self::get_activation_error_message( $error_code ),
					'code'    => $error_code,
				);
			}

			// Store license key and full response data.
			self::update_license_option( self::LICENSE_KEY_OPTION, $license_key );
			self::update_license_option( self::LICENSE_DATA_OPTION, $license_data );

			if ( $is_valid ) {
				self::update_license_option( self::LICENSE_STATUS_OPTION, 'valid' );
				self::update_license_option( self::PREMIUM_OPTION, 'yes' );
				self::update_license_option( self::LAST_VALID_OPTION, time() );
				self::set_license_transient( self::PREMIUM_OPTION, 'yes', self::LICENSE_CHECK_INTERVAL );
				self::delete_license_transient( self::LICENSE_CHECK_TRANSIENT );
				return true;
			}

			// Activation did not return valid — store the status and mark as not premium.
			$status = isset( $license_data['license'] ) ? $license_data['license'] : 'invalid';
			self::update_license_option( self::LICENSE_STATUS_OPTION, $status );
			self::update_license_option( self::PREMIUM_OPTION, 'no' );
			self::set_license_transient( self::PREMIUM_OPTION, 'no', self::LICENSE_CHECK_INTERVAL );

			return array(
				'success' => false,
				'message' => self::get_activation_error_message( $error_code ),
				'code'    => $error_code,
			);
		}

		/**
		 * Get a human-readable error message for an EDD activation error code.
		 *
		 * @param string $error_code - The EDD error code.
		 *
		 * @return string - Human-readable error message.
		 *
		 * @since 2.4.0
		 */
		private static function get_activation_error_message( string $error_code ): string {
			switch ( $error_code ) {
				case 'expired':
					return \__( 'Your license key has expired. Please renew your license.', 'wp-2fa' );

				case 'disabled':
					return \__( 'Your license key has been disabled. Please contact support.', 'wp-2fa' );

				case 'missing':
					return \__( 'The license key you entered is invalid.', 'wp-2fa' );

				case 'invalid':
				case 'site_inactive':
					return \__( 'Your license key is not active for this site.', 'wp-2fa' );

				case 'no_activations_left':
					return \__( 'Your license key has reached its activation limit. Please upgrade your license or deactivate it on another site.', 'wp-2fa' );

				case 'item_name_mismatch':
				case 'invalid_item_id':
					return \__( 'This license key is not valid for this product.', 'wp-2fa' );

				case 'key_mismatch':
					return \__( 'The license key does not match the expected product.', 'wp-2fa' );

				default:
					return \__( 'License activation failed. Please check your license key and try again.', 'wp-2fa' );
			}
		}

		/**
		 * Deactivate the current license.
		 *
		 * Routes to single-site or network deactivation based on multisite detection.
		 *
		 * @return bool True on success, false on failure.
		 *
		 * @since 2.4.0
		 */
		public static function deactivate_license(): bool {
			if ( \is_multisite() ) {
				return EDD_Network_Licensing::deactivate_network_license();
			}

			return self::deactivate_single_site_license();
		}

		/**
		 * Deactivate the current license for a single-site installation.
		 *
		 * @return bool True on success, false on failure.
		 *
		 * @since 2.4.0
		 */
		public static function deactivate_single_site_license(): bool {
			$license_key = self::get_license_option( self::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return false;
			}

			$license_data = self::get_license_option( self::LICENSE_DATA_OPTION, array() );
			$item_id      = is_array( $license_data ) && isset( $license_data['item_id'] ) ? (int) $license_data['item_id'] : 0;

			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license_key,
				'url'        => \home_url(),
			);

			if ( $item_id > 0 ) {
				$api_params['item_id'] = $item_id;
			}

			$response = \wp_remote_post(
				self::STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			/**
			 * Even if the remote call fails, clean up local data.
			 * The user explicitly chose to deactivate — don't leave
			 * stale premium state on their site.
			 */
			if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
				self::clear_local_license_data();
				return false;
			}

			$license_data = json_decode( \wp_remote_retrieve_body( $response ), true );

			if ( isset( $license_data['license'] ) && 'deactivated' === $license_data['license'] ) {
				self::clear_local_license_data();
				return true;
			}

			// If the server says it's already inactive/failed, still clean up locally.
			self::clear_local_license_data();

			return false;
		}

		/**
		 * Clear all local license data (options and transients).
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function clear_local_license_data() {
			self::delete_license_option( self::LICENSE_STATUS_OPTION );
			self::delete_license_option( self::LICENSE_KEY_OPTION );
			self::delete_license_option( self::LICENSE_DATA_OPTION );
			self::delete_license_option( self::PREMIUM_OPTION );
			self::delete_license_option( self::LAST_VALID_OPTION );
			self::delete_license_transient( self::LICENSE_CHECK_TRANSIENT );
			self::delete_license_transient( self::PREMIUM_OPTION );

			// Reset in-memory cache.
			self::$license_data = null;
		}

		/**
		 * Check if the user can use premium code.
		 *
		 * Equivalent to Freemius's can_use_premium_code() method.
		 * Returns true when the license is active and valid.
		 *
		 * @return bool True if premium code can be used, false otherwise.
		 *
		 * @since 2.4.0
		 */
		public static function can_use_premium_code(): bool {
			return self::has_active_valid_license();
		}

		/**
		 * Check if the user has a paying license.
		 *
		 * Equivalent to Freemius's is_paying() method.
		 * Returns true when the license is active and valid.
		 *
		 * @return bool True if the user is paying, false otherwise.
		 *
		 * @since 2.4.0
		 */
		public static function is_paying(): bool {
			return self::has_active_valid_license();
		}

		/**
		 * Check if the user is on a trial.
		 *
		 * Equivalent to Freemius's is_trial() method.
		 * Currently always returns false as EDD trials are not yet implemented.
		 *
		 * @return bool True if on trial, false otherwise.
		 *
		 * @since 2.4.0
		 */
		public static function is_trial(): bool {
			return false;
		}

		/**
		 * Check if the current license is on the given plan or a higher one.
		 *
		 * Equivalent to Freemius's is_plan() method. Plans are ordered
		 * premium < enterprise. Names that EDD never returns (legacy Freemius
		 * plans such as "business" or "ent") never match.
		 *
		 * @param string $plan - The plan name to check against.
		 * @param bool   $exact - Whether the plan must match exactly, instead of the plan or higher.
		 *
		 * @return bool - True if the license is on the plan (or higher when not exact), false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function is_plan( string $plan, bool $exact = false ): bool {
			if ( ! self::has_active_valid_license() ) {
				return false;
			}

			$current_plan  = EDD_Plan::get_plan_name();
			$required_plan = strtolower( $plan );

			if ( $current_plan === $required_plan ) {
				return true;
			}

			if ( $exact ) {
				return false;
			}

			$plans_order    = array( 'premium', 'enterprise' );
			$current_index  = array_search( $current_plan, $plans_order, true );
			$required_index = array_search( $required_plan, $plans_order, true );

			if ( false === $current_index || false === $required_index ) {
				return false;
			}

			return $current_index > $required_index;
		}

		/**
		 * Check if the current license is on the given plan, or on a trial of it.
		 *
		 * Mirrors the Freemius SDK method of the same name, so the plan gates that
		 * call it through Licensing_Factory::provider_call() work with EDD as well.
		 * EDD has no trials, so this is the same as is_plan().
		 *
		 * @param string $plan - The plan name to check against.
		 * @param bool   $exact - Whether the plan must match exactly, instead of the plan or higher.
		 *
		 * @return bool - True if the license is on the plan (or higher when not exact), false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function is_plan_or_trial__premium_only( string $plan, bool $exact = false ): bool {
			return self::is_plan( $plan, $exact ) || self::is_trial();
		}

		/**
		 * Check if the plugin is running without a valid license.
		 *
		 * Equivalent to Freemius's is_free_plan() method.
		 *
		 * @return bool - True if there is no active and valid license, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function is_free(): bool {
			return ! self::has_active_valid_license();
		}

		/**
		 * Get the current license plan.
		 *
		 * Returns an EDD_Plan instance with the normalized plan name
		 * derived from the EDD item_name. Returns null if no valid license.
		 *
		 * @return EDD_Plan|null - Plan instance or null.
		 *
		 * @since 2.4.0
		 */
		public static function get_plan() {
			if ( ! self::has_active_valid_license() ) {
				return null;
			}

			return EDD_Plan::get_plan_name();
		}

		/**
		 * Get the current plan name.
		 *
		 * @return string|null - Plan name or null.
		 *
		 * @since 2.4.0
		 */
		public static function get_plan_name() {
			return self::get_plan();
		}

		/**
		 * Get the provider name.
		 *
		 * @return string Provider name.
		 * @since 2.4.0
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
		 * @since 2.4.0
		 */
		public static function is_available(): bool {
			return \apply_filters( self::AJAX_PREFIX . 'provider_available', true );
		}

		/**
		 * Get the plugin basename.
		 *
		 * @return string Plugin basename.
		 * @since 2.4.0
		 */
		public static function get_plugin_basename(): string {
			return \plugin_basename( Licensing_Factory::PLUGIN_FILE );
		}

		/**
		 * Add an action hook (WordPress standard).
		 *
		 * @param string          $tag      The action hook name.
		 * @param callable|string $callback The callback function.
		 * @param int             $priority Priority.
		 * @param int             $args     Number of arguments.
		 * @return void
		 * @since 2.4.0
		 */
		public static function add_action( string $tag, $callback, int $priority = 10, int $args = 1 ) {
			\add_action( $tag, $callback, $priority, $args );
		}

		/**
		 * Add a filter hook (WordPress standard).
		 *
		 * @param string   $tag      The filter hook name.
		 * @param callable $callback The callback function.
		 * @param int      $priority Priority.
		 * @param int      $args     Number of arguments.
		 * @return void
		 * @since 2.4.0
		 */
		public static function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ) {
			\add_filter( $tag, $callback, $priority, $args );
		}

		/**
		 * Check license status with EDD API.
		 *
		 * @param string $license_key - The license key to check.
		 *
		 * @return bool True if valid, false otherwise.
		 *
		 * @since 2.4.0
		 */
		public static function check_license( string $license_key ): bool {
			$stored_data = self::get_license_option( self::LICENSE_DATA_OPTION, array() );
			$item_id     = is_array( $stored_data ) && isset( $stored_data['item_id'] ) ? (int) $stored_data['item_id'] : 0;

			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license_key,
				'url'        => \home_url(),
			);

			if ( $item_id > 0 ) {
				$api_params['item_id'] = $item_id;
			}

			$response = \wp_remote_post(
				self::STORE_URL,
				array(
					'timeout'   => self::CHECK_TIMEOUT,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			// The store said nothing, so decide nothing.
			if ( \is_wp_error( $response ) ) {
				return self::handle_inconclusive_check();
			}

			// Anything other than a 200 is a web server, proxy or firewall
			// talking, not the licensing API. Never act on it.
			if ( 200 !== (int) \wp_remote_retrieve_response_code( $response ) ) {
				return self::handle_inconclusive_check();
			}

			$license_data = json_decode( \wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $license_data ) || ! isset( $license_data['license'] ) ) {
				return self::handle_inconclusive_check();
			}

			$status = (string) $license_data['license'];

			if ( 'valid' === $status ) {
				self::update_license_option( self::LICENSE_DATA_OPTION, $license_data );
				self::update_license_option( self::LAST_VALID_OPTION, time() );
				self::store_license_status( 'valid' );

				return true;
			}

			// A definite statement about the license itself — apply it now.
			if ( in_array( $status, self::AUTHORITATIVE_NEGATIVE_STATUSES, true ) ) {
				self::update_license_option( self::LICENSE_DATA_OPTION, $license_data );
				self::store_license_status( $status );

				return false;
			}

			return self::handle_inconclusive_check( $status, $license_data );
		}

		/**
		 * Handle a license check that did not produce a trustworthy answer.
		 *
		 * A license that was valid keeps working for CHECK_GRACE_PERIOD so that
		 * a store outage, a proxy error page or a transient API failure cannot
		 * disconnect a paying customer. Either way a short retry transient is
		 * set, so an unreachable store does not mean a blocking remote request
		 * on every admin page load.
		 *
		 * @param string     $status       - Status reported by the store, if any.
		 * @param array|null $license_data - Payload from the store, if any.
		 *
		 * @return bool True while the license is still being honoured.
		 *
		 * @since 2.4.0
		 */
		private static function handle_inconclusive_check( string $status = '', $license_data = null ): bool {
			$was_valid = 'valid' === self::get_license_option( self::LICENSE_STATUS_OPTION );

			if ( $was_valid && ! self::grace_period_expired() ) {
				// Keep the last known good answer and try again sooner. The
				// stored license data is left untouched: overwriting it with an
				// error payload would destroy the item_id the next check needs
				// and the item_name the plan tier is derived from.
				self::set_license_transient( self::PREMIUM_OPTION, self::get_license_option( self::PREMIUM_OPTION, 'yes' ), self::CHECK_RETRY_INTERVAL );

				return true;
			}

			if ( '' === $status ) {
				// Nothing usable came back. Leave the stored status alone rather
				// than inventing one, but stop hammering the store.
				self::set_license_transient( self::PREMIUM_OPTION, self::get_license_option( self::PREMIUM_OPTION, 'no' ), self::CHECK_RETRY_INTERVAL );

				return false;
			}

			if ( is_array( $license_data ) ) {
				self::update_license_option( self::LICENSE_DATA_OPTION, $license_data );
			}

			self::store_license_status( $status );

			return false;
		}

		/**
		 * Whether the grace period for a previously valid license has run out.
		 *
		 * On a site that predates this bookkeeping the window starts at the
		 * first inconclusive check rather than counting as already expired.
		 *
		 * @return bool
		 *
		 * @since 2.4.0
		 */
		private static function grace_period_expired(): bool {
			$last_valid = (int) self::get_license_option( self::LAST_VALID_OPTION, 0 );

			if ( $last_valid <= 0 ) {
				$last_valid = time();
				self::update_license_option( self::LAST_VALID_OPTION, $last_valid );
			}

			return ( time() - $last_valid ) > self::CHECK_GRACE_PERIOD;
		}

		/**
		 * Persist a license status together with the premium fast-path flag.
		 *
		 * @param string $status - Status as reported by the store.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		private static function store_license_status( string $status ) {
			$new_value = 'valid' === $status ? 'yes' : 'no';

			self::update_license_option( self::LICENSE_STATUS_OPTION, $status );
			self::set_license_transient( self::LICENSE_CHECK_TRANSIENT, $status, self::LICENSE_CHECK_INTERVAL );

			if ( $new_value !== self::get_license_option( self::PREMIUM_OPTION ) ) {
				self::update_license_option( self::PREMIUM_OPTION, $new_value );
			}

			self::set_license_transient( self::PREMIUM_OPTION, $new_value, self::LICENSE_CHECK_INTERVAL );

			// The store's activation count may have changed; work out which subsites the license still covers.
			if ( \is_multisite() ) {
				EDD_Network_Licensing::recalculate_coverage();
			}
		}

		/**
		 * Maybe check license status (runs on admin_init).
		 *
		 * @return void
		 * @since 2.4.0
		 */
		public static function maybe_check_license() {
			if ( \wp_doing_ajax() ) {
				return;
			}

			// On multisite the network license is checked from the main site only.
			if ( \is_multisite() && ! \is_main_site() ) {
				return;
			}

			$license_key = self::get_license_option( self::LICENSE_KEY_OPTION );

			if ( empty( $license_key ) ) {
				return;
			}

			// Use the premium transient as the 24h guard.
			$cached_premium = self::get_license_transient( self::PREMIUM_OPTION );

			if ( false !== $cached_premium ) {
				return;
			}

			self::check_license( $license_key );
		}

		/**
		 * Setup the EDD updater.
		 *
		 * @return void
		 * @since 2.4.0
		 */
		public static function setup_updater() {
			// On multisite, license data is stored on the main site.
			$license_key  = self::get_license_option( self::LICENSE_KEY_OPTION );
			$license_data = self::get_license_option( self::LICENSE_DATA_OPTION );

			if ( empty( $license_key ) ) {
				return;
			}

			$item_id = isset( $license_data['item_id'] ) ? (int) $license_data['item_id'] : 0;

			if ( empty( $item_id ) ) {
				return;
			}

			EDD_Plugin_Updater::setup(
				self::STORE_URL,
				array(
					'license' => $license_key,
					'item_id' => $item_id,
					'author'      => 'Melapress',
					'beta'        => false,
					'text_domain' => Licensing_Factory::TEXT_DOMAIN,
				)
			);
		}

		/**
		 * Override the plugin homepage URL in the "View details" modal.
		 *
		 * EDD Software Licensing returns the store download permalink as the
		 * homepage. This filter replaces it with the marketing site URL.
		 *
		 * @param false|object|array $result - The result object or array.
		 * @param string             $action - The type of information being requested.
		 * @param object             $args - Plugin API arguments.
		 *
		 * @return false|object|array
		 *
		 * @since 2.4.0
		 */
		public static function override_plugin_homepage( $result, $action, $args ) {
			if ( 'plugin_information' !== $action ) {
				return $result;
			}

			if ( is_object( $result ) && isset( $result->homepage ) ) {
				$result->homepage = '';
			}

			return $result;
		}

		/**
		 * Display license admin notices.
		 *
		 * Shows dismissible notices for expired, invalid, activation limit,
		 * and missing license states.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function license_notices() {
			if ( ! Licensing_Factory::can_manage_license() ) {
				return;
			}

			// On multisite the license is managed in network admin, so subsite admins get no license notices.
			if ( \is_multisite() && ! \is_network_admin() ) {
				return;
			}

			$license_key  = self::get_license_option( self::LICENSE_KEY_OPTION );
			$status       = self::get_license_option( self::LICENSE_STATUS_OPTION );
			$license_data = self::get_license_option( self::LICENSE_DATA_OPTION, array() );
			$error_code   = is_array( $license_data ) && isset( $license_data['error'] ) ? $license_data['error'] : '';
			$license_url  = EDD_License_Page::get_license_page_url();

			// No license key stored — prompt to activate.
			if ( empty( $license_key ) ) {
				echo '<div class="notice notice-info is-dismissible"><p>';
				printf(
					/* translators: 1: plugin name, 2: license page link */
					\esc_html__( '%1$s — please %2$s to receive updates and support.', 'wp-2fa' ),
					\esc_html( Licensing_Factory::PLUGIN_NAME ),
					'<a href="' . \esc_url( $license_url ) . '">' . \esc_html__( 'activate your license', 'wp-2fa' ) . '</a>'
				);
				echo '</p></div>';
				return;
			}

			// Expired license.
			if ( 'expired' === $status ) {
				echo '<div class="notice notice-error is-dismissible"><p>';
				printf(
					/* translators: 1: plugin name, 2: renew link */
					\esc_html__( 'Your %1$s license has expired. The plugin has been switched to free mode with limited functionality. Please %2$s to restore all premium features.', 'wp-2fa' ),
					\esc_html( Licensing_Factory::PLUGIN_NAME ),
					'<a href="' . \esc_url( self::get_account_url() ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'renew your license', 'wp-2fa' ) . '</a>'
				);
				echo '</p></div>';
				return;
			}

			// Activation limit reached.
			if ( 'no_activations_left' === $error_code ) {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				printf(
					/* translators: 1: plugin name, 2: pricing page link */
					\esc_html__( 'Your %1$s license has reached its activation limit. Please %2$s or deactivate it on another site.', 'wp-2fa' ),
					\esc_html( Licensing_Factory::PLUGIN_NAME ),
					'<a href="' . \esc_url( self::get_pricing_url() ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'upgrade your license', 'wp-2fa' ) . '</a>'
				);
				echo '</p></div>';
				return;
			}

			// License is not activated for this site — previously this produced no
			// notice at all, leaving the user with a license prompt and no reason.
			if ( 'site_inactive' === $status || 'inactive' === $status ) {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				printf(
					/* translators: 1: plugin name, 2: license page link */
					\esc_html__( 'Your %1$s license is not active for this site. Please %2$s to reactivate it.', 'wp-2fa' ),
					\esc_html( Licensing_Factory::PLUGIN_NAME ),
					'<a href="' . \esc_url( $license_url ) . '">' . \esc_html__( 'visit the license page', 'wp-2fa' ) . '</a>'
				);
				echo '</p></div>';
				return;
			}

			// Invalid license.
			if ( 'invalid' === $status || 'disabled' === $status ) {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				printf(
					/* translators: 1: plugin name, 2: license page link */
					\esc_html__( 'Your %1$s license is invalid. Please %2$s or contact support.', 'wp-2fa' ),
					\esc_html( Licensing_Factory::PLUGIN_NAME ),
					'<a href="' . \esc_url( $license_url ) . '">' . \esc_html__( 'check your license key', 'wp-2fa' ) . '</a>'
				);
				echo '</p></div>';
			}

			// Multisite: more activations in use than the license allows (e.g. after a downgrade).
			if ( \is_multisite() && self::is_over_activation_limit() ) {
				$uncovered_sites = EDD_Network_Licensing::get_uncovered_sites();
				$license_limit   = is_array( $license_data ) && isset( $license_data['license_limit'] ) ? (int) $license_data['license_limit'] : 0;
				$site_count      = is_array( $license_data ) && isset( $license_data['site_count'] ) ? (int) $license_data['site_count'] : 0;

				echo '<div class="notice notice-warning"><p>';
				printf(
					/* translators: 1: plugin name, 2: activations the license allows, 3: activations in use, 4: pricing page link, 5: comma-separated list of subsite URLs */
					\esc_html__( 'Your %1$s license allows %2$d activations, but %3$d are in use. On these subsites premium 2FA methods can no longer be set up (users who already use them can still log in) until you %4$s or deactivate the plugin on sites you no longer need: %5$s', 'wp-2fa' ),
					\esc_html( Licensing_Factory::PLUGIN_NAME ),
					(int) $license_limit,
					(int) $site_count,
					'<a href="' . \esc_url( self::get_pricing_url() ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'upgrade your license', 'wp-2fa' ) . '</a>',
					\esc_html( implode( ', ', $uncovered_sites ) )
				);
				echo '</p></div>';
			}

			// Multisite: new subsite could not be activated (no slots).
			if ( \is_multisite() && \get_site_option( EDD_Network_Licensing::ACTIVATION_FAILED_FLAG ) ) {
				echo '<div class="notice notice-warning"><p>';
				printf(
					/* translators: 1: pricing page link, 2: support page link */
					\esc_html__( 'A new subsite could not be activated because your license has no remaining activations. Please %1$s, free a slot by deactivating another site, or %2$s.', 'wp-2fa' ),
					'<a href="' . \esc_url( self::get_pricing_url() ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'upgrade your license', 'wp-2fa' ) . '</a>',
					'<a href="' . \esc_url( 'https://melapress.com/contact/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=multisite_no_remaining_actiations' ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'contact our support team', 'wp-2fa' ) . '</a>'
				);
				echo '</p></div>';
			}
		}

		/**
		 * AJAX handler for license activation.
		 *
		 * @return void
		 * @since 2.4.0
		 */
		public static function ajax_activate_license() {
			\check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			if ( ! Licensing_Factory::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$license_key = isset( $_POST['license_key'] ) ? \sanitize_text_field( \wp_unslash( $_POST['license_key'] ) ) : '';

			if ( empty( $license_key ) ) {
				\wp_send_json_error( array( 'message' => \__( 'License key is required.', 'wp-2fa' ) ) );
			}

			$result = self::activate_license( $license_key );

			if ( true === $result ) {
				\wp_send_json_success( array( 'message' => \__( 'License activated successfully.', 'wp-2fa' ) ) );
			} else {
				\wp_send_json_error( $result );
			}
		}

		/**
		 * AJAX handler for license deactivation.
		 *
		 * @return void
		 * @since 2.4.0
		 */
		public static function ajax_deactivate_license() {
			\check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			if ( ! Licensing_Factory::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$result = self::deactivate_license();

			if ( $result ) {
				\wp_send_json_success( array( 'message' => \__( 'License deactivated successfully.', 'wp-2fa' ) ) );
			} else {
				\wp_send_json_error( array( 'message' => \__( 'Failed to deactivate license.', 'wp-2fa' ) ) );
			}
		}

		/**
		 * AJAX handler for license sync.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function ajax_sync_license() {
			\check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			if ( ! Licensing_Factory::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$result = self::sync_license();

			if ( $result ) {
				\wp_send_json_success( array( 'message' => \__( 'License data synced successfully.', 'wp-2fa' ) ) );
			} else {
				\wp_send_json_error( array( 'message' => \__( 'Failed to sync license data.', 'wp-2fa' ) ) );
			}
		}

		/**
		 * AJAX handler for getting activation progress.
		 *
		 * Used by the frontend to poll batch activation/deactivation status.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function ajax_get_activation_progress() {
			\check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			if ( ! Licensing_Factory::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$progress = EDD_Network_Licensing::get_activation_progress();
			\wp_send_json_success( $progress );
		}

		/**
		 * Try to deactivate a license for a specific URL.
		 *
		 * Used by network licensing to deactivate individual subsites.
		 *
		 * @param string $license_key - The license key.
		 * @param int    $item_id     - The EDD product ID.
		 * @param string $url         - The site URL to deactivate.
		 *
		 * @return bool True on success, false on failure.
		 *
		 * @since 2.4.0
		 */
		public static function try_deactivate_for_url( string $license_key, int $item_id, string $url ): bool {
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license_key,
				'item_id'    => $item_id,
				'url'        => $url,
			);

			$response = \wp_remote_post(
				self::STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$license_data = json_decode( \wp_remote_retrieve_body( $response ), true );

			return isset( $license_data['license'] ) && 'deactivated' === $license_data['license'];
		}
	}
}
