<?php
/**
 * Freemius Licensing Provider for WP2FA plugin.
 *
 * Wrapper around the existing Freemius SDK integration, implementing the
 * Licensing_Provider interface for unified licensing API.
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

use WP2FA\WP2FA;
use WP2FA\Extensions_Loader;
use WP2FA\Freemius\User_Licensing;

if ( ! class_exists( '\WP2FA\Licensing\Freemius_Provider' ) ) {

	/**
	 * Freemius licensing provider implementation.
	 *
	 * @since 3.2.0
	 */
	class Freemius_Provider implements Licensing_Provider {

		/**
		 * Cache for availability check.
		 *
		 * @var bool|null
		 * @since 3.2.0
		 */
		private static $is_available = null;

		/**
		 * Freemius instance cache.
		 *
		 * @var mixed|null
		 * @since 3.2.0
		 */
		private static $freemius_instance = null;

		/**
		 * Initialize the Freemius licensing provider.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function init() {
			if ( ! self::is_available() ) {
				return;
			}

			// Initialize Freemius SDK and helper.
			add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_to_external_pricing_page' ), 9 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_sync_premium_license' ) );
			add_action( 'wp2fa_freemius_loaded', array( __CLASS__, 'adjust_freemius_strings' ) );

			self::add_filter( 'connect_message', array( __CLASS__, 'change_connect_message' ), 10, 6 );
			self::add_filter(
				'connect_message_on_update',
				array(
					__CLASS__,
					'change_connect_message_on_update',
				),
				10,
				6
			);

			self::add_filter( 'show_admin_notice', array( __CLASS__, 'can_show_admin_notice' ), 10, 2 );
			self::add_filter( 'show_delegation_option', '__return_false' );
			self::add_filter( 'enable_per_site_activation', '__return_false' );
			self::add_filter( 'show_trial', '__return_false' );

			self::add_filter(
				'opt_in_error_message',
				array(
					__CLASS__,
					'limited_license_activation_error',
				),
				10,
				1
			);

			self::add_action( 'after_account_plan_sync', array( __CLASS__, 'sync_premium_license' ), 10, 1 );
			self::add_action(
				'after_premium_version_activation',
				array(
					__CLASS__,
					'on_premium_version_activation',
				)
			);
			self::add_filter(
				'plugin_icon',
				function ( $plugin_icon ) {
					return WP_2FA_PATH . 'dist/images/wp-2fa-square.png';
				}
			);

			self::add_filter(
				'pricing_url',
				function ( $url ) {
					return 'https://melapress.com/wordpress-2fa/pricing/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=priciing_url';
				}
			);

			self::add_action( 'is_submenu_visible', array( __CLASS__, 'hide_submenu_items' ), 10, 2 );
			self::add_filter( 'default_to_anonymous_feedback', '__return_true' );
			self::add_filter( 'show_deactivation_feedback_form', '__return_false' );

			if ( Extensions_Loader::use_proxytron() ) {
				if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) ) {
					User_Licensing::init();
				}
			}
		}

		/**
		 * Check if the license is active and valid.
		 *
		 * @return bool True if license is active and valid, false otherwise.
		 * @since 3.2.0
		 */
		public static function has_active_valid_license(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return false;
			}

			return $fs->is_registered() && $fs->has_active_valid_license();
		}

		/**
		 * Get the Freemius provider instance.
		 *
		 * @return mixed|false Freemius instance or false if unavailable.
		 * @since 3.2.0
		 */
		public static function get_provider_instance() {
			if ( ! self::is_available() ) {
				return false;
			}

			return self::wp2fa_freemius();
		}

		/**
		 * Check if the premium version is active.
		 *
		 * @return bool True if premium is active, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_premium(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			return 'yes' === get_option( 'fs_wp2fap' );
		}

		/**
		 * Check if the plugin is registered with Freemius.
		 *
		 * @return bool True if registered, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_registered(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return false;
			}

			return $fs->is_registered();
		}

		/**
		 * Get the Freemius license object.
		 *
		 * @return mixed License object or null.
		 * @since 3.2.0
		 */
		public static function get_license() {
			if ( ! self::is_available() ) {
				return null;
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return null;
			}

			return $fs->_get_license();
		}

		/**
		 * Get the license quota.
		 *
		 * @return int Number of allowed users/sites.
		 * @since 3.2.0
		 */
		public static function get_license_quota(): int {
			if ( ! self::is_available() ) {
				return -1;
			}

			$quota = 0;
			/**
			 * If the quota of the license is null, that in terms of freemius means unlimited - set the quota to the maximum integer which is allowed by the PHP
			 */
			if ( null === self::wp2fa_freemius()->_get_license()->quota ) {
				$quota = PHP_INT_MAX;
			} else {
				$quota = (int) self::wp2fa_freemius()->_get_license()->quota;
			}

			return $quota;
		}

		/**
		 * Check if license quota has been exceeded.
		 *
		 * @return bool True if quota exceeded, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_quota_exceeded(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) ) {
				return User_Licensing::quota_check();
			}

			return false;
		}

		/**
		 * Get the pricing page URL.
		 *
		 * @return string Pricing page URL.
		 * @since 3.2.0
		 */
		public static function get_pricing_url(): string {
			if ( ! self::is_available() ) {
				return 'https://melapress.com/wordpress-2fa/pricing/';
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return 'https://melapress.com/wordpress-2fa/pricing/';
			}

			return $fs->pricing_url();
		}

		/**
		 * Get the account/dashboard URL.
		 *
		 * @return string Account URL.
		 * @since 3.2.0
		 */
		public static function get_account_url(): string {
			if ( ! self::is_available() ) {
				return 'https://melapress.com/account/';
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return 'https://melapress.com/account/';
			}

			return $fs->get_account_url();
		}

		/**
		 * Sync/refresh the license status.
		 *
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 */
		public static function sync_license(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			$option_name = 'fs_wp2fap';
			$old_value   = get_option( $option_name );

			// determine new value via Freemius SDK.
			$new_value = self::has_active_valid_license() ? 'yes' : 'no';

			// update the db option only if the value changed.
			if ( $new_value !== $old_value ) {
				\update_option( $option_name, $new_value );
			}

			// always update the transient to extend the expiration window.
			\set_transient( $option_name, $new_value, DAY_IN_SECONDS );

			return true;
		}

		/**
		 * Activate a license key (Freemius handles this through its UI).
		 *
		 * @param string $license_key The license key to activate.
		 * @return bool|array True on success, array with error info on failure.
		 * @since 3.2.0
		 */
		public static function activate_license( string $license_key ) {
			if ( ! self::is_available() ) {
				return array(
					'success' => false,
					'message' => 'Freemius is not available.',
				);
			}

			// Freemius SDK handles activation through its own UI/API.
			// This method is here for interface compliance.
			// Actual activation happens through Freemius account connection.
			return true;
		}

		/**
		 * Deactivate the current license.
		 *
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 */
		public static function deactivate_license(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			// Freemius SDK handles deactivation through its own UI/API.
			// This would typically be done through wp2fa_freemius()->deactivate_license().
			return true;
		}

		/**
		 * Get the provider name.
		 *
		 * @return string Provider name.
		 * @since 3.2.0
		 */
		public static function get_provider_name(): string {
			return 'freemius';
		}

		/**
		 * Check if Freemius is available.
		 *
		 * @return bool True if Freemius is available, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_available(): bool {
			if ( null !== self::$is_available ) {
				return self::$is_available;
			}

			// Include Freemius SDK.
			$freemius_path = self::get_freemius_path();

			if ( ! file_exists( $freemius_path ) ) {
				return (bool) self::$is_available;
			}

			self::$is_available = true;

			return self::$is_available;
		}

		/**
		 * Get the plugin basename.
		 *
		 * @return string Plugin basename.
		 * @since 3.2.0
		 */
		public static function get_plugin_basename(): string {
			if ( ! self::is_available() ) {
				return plugin_basename( WP_2FA_FILE );
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return plugin_basename( WP_2FA_FILE );
			}

			return $fs->get_plugin_basename();
		}

		/**
		 * Add a Freemius action hook.
		 *
		 * @param string          $tag      The action hook name.
		 * @param callable|string $callback The callback function.
		 * @param int             $priority Priority.
		 * @param int             $args     Number of arguments.
		 * @return void
		 * @since 3.2.0
		 */
		public static function add_action( string $tag, $callback, int $priority = 10, int $args = 1 ) {
			if ( ! self::is_available() ) {
				return;
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return;
			}

			$fs->add_action( $tag, $callback, $priority, $args );
		}

		/**
		 * Add a Freemius filter hook.
		 *
		 * @param string   $tag      The filter hook name.
		 * @param callable $callback The callback function.
		 * @param int      $priority Priority.
		 * @param int      $args     Number of arguments.
		 * @return void
		 * @since 3.2.0
		 */
		public static function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ) {
			if ( ! self::is_available() ) {
				return;
			}

			$fs = self::wp2fa_freemius();
			if ( null === $fs ) {
				return;
			}

			$fs->add_filter( $tag, $callback, $priority, $args );
		}

		/**
		 * Ensure Freemius dynamic init is registered and return the instance.
		 *
		 * This returns the Freemius instance for Melapress Login Security.
		 *
		 * @return mixed|null Freemius instance or null when unavailable.
		 */
		private static function wp2fa_freemius() {
			if ( ! self::is_available() ) {
				return null;
			}

			if ( null !== self::$freemius_instance ) {
				return self::$freemius_instance;
			}

			self::$freemius_instance = \false;
			// Use helper to get Freemius SDK path.
			$freemius_path = self::get_freemius_path();

			if ( ! file_exists( $freemius_path ) ) {
				return self::$freemius_instance;
			}

			require_once $freemius_path;

			if ( function_exists( 'fs_dynamic_init' ) ) {
				if ( ! defined( 'WP_FS__PRODUCT_8257_MULTISITE' ) ) {
					define( 'WP_FS__PRODUCT_8257_MULTISITE', true );
				}

				// Trial arguments.
				$trial_args = array(
					'days'               => 14,
					'is_require_payment' => false,
				);

				// Check anonymous mode.
				$freemius_state = get_site_option( 'melapress_login_security_freemius_state', 'anonymous' );
				$is_anonymous   = 'anonymous' === $freemius_state || 'skipped' === $freemius_state;
				$is_premium     = true;
				$is_anonymous   = ( $is_premium ? false : $is_anonymous );

				self::$freemius_instance = \fs_dynamic_init(
					array(
						'id'                  => '8257',
						'slug'                => 'wp-2fa',
						'type'                => 'plugin',
						'public_key'          => 'pk_b8cc4c0bbe2df3365f23c225a7889',
						'premium_suffix'      => '',
						'is_premium'          => true,
						// If your plugin is a serviceware, set this option to false.
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'has_affiliation'     => false,
						'trial'               => $trial_args,
						'menu'                => array(
							'slug'        => 'wp-2fa-policies',
							'support'     => false,
							'affiliation' => false,
							'network'     => true,
						),
						'anonymous_mode'      => $is_anonymous,
						'is_live'             => true,
					)
				);

				/**
				 * Notifies the freemius helper the the library is loaded.
				 *
				 * @since 2.0.0
				 */
				do_action( 'wp2fa_freemius_loaded' );
			}

			return self::$freemius_instance;
		}

		/**
		 * Get Freemius SDK file path.
		 *
		 * @return string
		 */
		private static function get_freemius_path(): string {
			return WP_2FA_PATH . DIRECTORY_SEPARATOR . implode(
				DIRECTORY_SEPARATOR,
				array(
					'third-party',
					'freemius',
					'wordpress-sdk',
					'start.php',
				)
			);
		}

		/**
		 * Resource cautious function to check if the premium license is active and valid. It only checks if WordPress
		 * option "fs_wp2fap" is present and set to true.
		 *
		 * Function is intended for quick check during initial stages of plugin bootstrap, especially on front-end.
		 *
		 * @return boolean
		 */
		public static function is_premium_freemius() {
			return 'yes' === get_option( 'fs_wp2fap' );
		}

		/**
		 * Function runs Freemius license check only if our Freemius licensing transient has already expired. This is
		 * intended to run on admin_init action.
		 *
		 * @since 2.0.0
		 */
		public static function maybe_sync_premium_license() {
			// we don't want to slow down any AJAX requests.
			if ( wp_doing_ajax() ) {
				return;
			}

			$freemius_transient = get_transient( 'fs_mls_premium' );
			if ( false === $freemius_transient || ! in_array( $freemius_transient, array( 'yes', 'no' ) ) ) {
				// transient expired or invalid.
				self::sync_premium_license();
			}
		}

		/**
		 * Runs Freemius license check, updates our db option if necessary and creates/extends a transient we use to
		 * optimize the check. Should run only on couple of Freemius actions related to account sync and plugin activation.
		 *
		 * It might be also called by WP2FA\Freemius\Freemius_Helper::maybe_sync_premium_license() if the transient is not set or valid.
		 *
		 * @see WP2FA\Freemius\Freemius_Helper::maybe_sync_premium_license()
		 */
		public static function sync_premium_license() {
			$option_name = 'fs_wp2fap';
			$old_value   = \get_option( $option_name );

			// determine new value via Freemius SDK.
			$new_value = self::has_active_valid_license() ? 'yes' : 'no';

			// update the db option only if the value changed.
			if ( $new_value !== $old_value ) {
				\update_option( $option_name, $new_value );
			}

			// always update the transient to extend the expiration window.
			\set_transient( $option_name, $new_value, DAY_IN_SECONDS );
		}

		/**
		 * Customize Freemius connect message for new users.
		 *
		 * @param string $message - Connect message.
		 * @param string $user_first_name - User first name.
		 * @param string $plugin_title - Plugin title.
		 * @param string $user_login - Username.
		 * @param string $site_link - Site link.
		 * @param string $_freemius_link - Freemius link.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function change_connect_message( $message, $user_first_name, $plugin_title, $user_login, $site_link, $_freemius_link ) {
			$result = sprintf(
			/* translators: User's first name */
				esc_html__( 'Hey %s', 'melapress-login-security' ),
				$user_first_name
			);
			$result .= ',<br>';
			$result .= esc_html__( 'Never miss an important update! Opt-in to our security and feature updates notifications, and non-sensitive diagnostic tracking with freemius.com.', 'wp-2fa' ) .
			$result .= '<br /><br /><strong>' . esc_html__( 'Note: ', 'wp-2fa' ) . '</strong>';
			$result .= esc_html__( 'NO ACTIVITY LOG ACTIVITY & DATA IS SENT BACK TO OUR SERVERS.', 'wp-2fa' );

			return $result;
		}

		/**
		 * Customize Freemius connect message on update.
		 *
		 * @param string $message - Connect message.
		 * @param string $user_first_name - User first name.
		 * @param string $plugin_title - Plugin title.
		 * @param string $user_login - Username.
		 * @param string $site_link - Site link.
		 * @param string $_freemius_link - Freemius link.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function change_connect_message_on_update( $message, $user_first_name, $plugin_title, $user_login, $site_link, $_freemius_link ) {
			$result = sprintf(
			/* translators: User's first name */
				esc_html__( 'Hey %s', 'wp-2fa' ),
				$user_first_name
			);
			$result .= ',<br>';
			$result .= sprintf(
			/* translators: 1: Plugin name. 2: Plugin name. 2: Freemius link. 4: Plugin name. */
				esc_html__( 'Please help us improve %1$s! If you opt-in, some non-sensitive data about your usage of %2$s will be sent to %3$s, a diagnostic tracking service we use. If you skip this, that\'s okay! %2$s will still work just fine.', 'wp-2fa' ) .
				'<strong>' . $plugin_title . '</strong>',
				'<strong>' . $plugin_title . '</strong>',
				'<a href="https://melapress.com/wordpress-activity-log/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=optin_message" target="_blank" tabindex="1">freemius.com</a>',
				'<strong>' . $plugin_title . '</strong>'
			);
			$result .= '<br /><br /><strong>' . esc_html__( 'Note: ', 'wp-2fa' ) . '</strong>';
			$result .= esc_html__( 'NO ACTIVITY LOG ACTIVITY & DATA IS SENT BACK TO OUR SERVERS.', 'wp-2fa' );

			return $result;
		}

		/**
		 * Check to see if the user has permission to view Freemius
		 * admin notices or not.
		 *
		 * @param bool  $show – If show then set to true, otherwise false.
		 * @param array $msg -  Possible values
		 *      string $message The actual message.
		 *      string $title An optional message title.
		 *      string $type The type of the message ('success', 'update', 'warning', 'promotion').
		 *      string $id The unique identifier of the message.
		 *      string $manager_id The unique identifier of the notices manager. For plugins it would be the plugin's slug, for themes - `<slug>-theme`.
		 *      string $plugin The product's title.
		 *      string $wp_user_id An optional WP user ID that this admin notice is for.
		 * }.
		 *
		 * @return bool
		 *
		 * @since 2.0.0
		 */
		public static function can_show_admin_notice( $show, $msg ) {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Runs on premium version activation.
		 *
		 * @since 2.0.0
		 */
		public static function on_premium_version_activation() {
			self::sync_premium_license();
		}

		/**
		 * Use filter to hide Freemius submenu items.
		 *
		 * @param boolean $is_visible Default visibility.
		 * @param string  $submenu_id Menu slug.
		 *
		 * @return boolean New visibility.
		 *
		 * @since 2.0.0
		 */
		public static function hide_submenu_items( $is_visible, $submenu_id ) {
			if ( 'contact' === $submenu_id ) {
				return false;
			}

			return $is_visible;
		}

		/**
		 * Limited License Activation Error.
		 *
		 * @param string $error - Error Message.
		 *
		 * @return string
		 */
		public static function limited_license_activation_error( $error ) {
			$site_count = null;
			preg_match( '!\d+!', $error, $site_count );

			// Check if this is an expired error.
			if ( strpos( $error, 'expired' ) !== false ) {
				/* Translators: Expired message and time */
				$error = sprintf( esc_html__( '%s You need to renew your license to continue using premium features.', 'wp-2fa' ), preg_replace( '/\([^)]+\)/', '', $error ) );
			} elseif ( ! empty( $site_count[0] ) ) {
				/* Translators: Number of sites */
				$error = sprintf( esc_html__( 'The license is limited to %s sub-sites. You need to upgrade your license to cover all the sub-sites on this network.', 'wp-2fa' ), $site_count[0] );
			}

			return $error;
		}

		/**
		 * Redirect to external pricing page when the in-place pricing page is being loaded.
		 *
		 * Freemius doesn't directly support rendering an external pricing page link.
		 *
		 * @since 2.0.0
		 */
		public static function maybe_redirect_to_external_pricing_page() {
			if ( array_key_exists( 'page', $_GET ) && 'wp-2fa-policies-pricing' === \wp_unslash( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				\wp_redirect( 'https://melapress.com/wordpress-2fa/pricing/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=redirect_to_external_price_page' );
				exit;
			}
		}

		/**
		 * Changes some strings that Freemius outputs without own.
		 *
		 * @since 2.0.0
		 */
		public static function adjust_freemius_strings() {
			// only update these messages if using premium plugin.
			if ( ( ! self::wp2fa_freemius()->is_premium() ) || ( ! method_exists( self::wp2fa_freemius(), 'override_il8n' ) ) ) {
				return;
			}

			self::wp2fa_freemius()->override_i18n(
				array(
					/* translators: plugin version */
					'few-plugin-tweaks' => __( 'You need to activate the license key to use WP 2FA - Two-factor authentication for WordPress . %2$s', 'wp-2fa' ),
					'optin-x-now'       => __( 'Activate the license key now', 'wp-2fa' ),
				)
			);
		}
	}
}
