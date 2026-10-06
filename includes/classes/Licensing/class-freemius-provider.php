<?php
/**
 * Freemius Licensing Provider for WP 2FA plugin.
 *
 * Wrapper around the existing Freemius SDK integration, implementing the
 * Licensing_Provider interface for unified licensing API.
 *
 * @since      2.0.0
 * @package    wp2fa
 * @subpackage Licensing
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://melapress.com/wordpress-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Licensing;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP2FA\Licensing\Freemius_Provider' ) ) {

	/**
	 * Freemius licensing provider implementation.
	 *
	 * All plugin-specific values are sourced from Licensing_Factory constants
	 * — no direct coupling to plugin globals.
	 *
	 * @since 2.0.0
	 */
	class Freemius_Provider implements Licensing_Provider {

		/**
		 * Freemius premium option name.
		 *
		 * References the canonical constant in Licensing_Factory.
		 *
		 * @var string
		 * @since 4.0.0
		 */
		public const FS_WP2FAP_OPTION = Licensing_Factory::FREEMIUS_PREMIUM_OPTION;

		/**
		 * The SDK's account record, holding its users, plans and site installs.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const ACCOUNTS_OPTION = 'fs_accounts';

		/**
		 * Timestamp of the last conclusive "licensed" answer from the SDK.
		 *
		 * @var string
		 */
		public const FS_LAST_VALID_OPTION = 'wp2fa_fs_license_last_valid';

		/**
		 * How long a previously licensed site keeps working while the SDK says
		 * otherwise. Same reasoning, and the same window, as the EDD provider.
		 *
		 * @var int
		 */
		public const CHECK_GRACE_PERIOD = 7 * DAY_IN_SECONDS;

		/**
		 * How soon to retry after an inconclusive sync.
		 *
		 * @var int
		 */
		public const CHECK_RETRY_INTERVAL = HOUR_IN_SECONDS;

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

			// Intercept Freemius disconnect form submissions early (during admin_menu,
			// which fires BEFORE WordPress's page access check in menu.php). The form
			// posts to admin.php?page=wp-2fa-policies-account which may no longer be a
			// registered page, causing "not allowed" errors.
			$menu_hook = \is_multisite() ? 'network_admin_menu' : 'admin_menu';
			add_action( $menu_hook, array( __CLASS__, 'maybe_handle_freemius_disconnect' ), 1 );

			add_action( Licensing_Factory::FREEMIUS_INTERNAL_SLUG . '_freemius_loaded', array( __CLASS__, 'adjust_freemius_strings' ) );

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
			self::add_action( 'after_account_delete', array( __CLASS__, 'on_account_disconnect' ) );
			self::add_action( 'after_network_account_delete', array( __CLASS__, 'on_account_disconnect' ) );
			self::add_action( 'after_license_deactivation', array( __CLASS__, 'on_license_deactivation' ) );
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
					return Licensing_Factory::PLUGIN_PATH . Licensing_Factory::PLUGIN_ICON_PATH;
				}
			);

			self::add_filter(
				'pricing_url',
				function ( $url ) {
					return Licensing_Factory::FREEMIUS_PRICING_URL;
				}
			);

			self::add_action( 'is_submenu_visible', array( __CLASS__, 'hide_submenu_items' ), 10, 2 );
			self::add_filter( 'default_to_anonymous_feedback', '__return_true' );
			self::add_filter( 'show_deactivation_feedback_form', '__return_false' );
			self::add_action(
				'connect/before',
				function () {
					echo '<style>.fs-freemium-licensing { display: none !important; }</style>';
				}
			);
		}

		/**
		 * Check if the license is active and valid.
		 *
		 * @return bool True if license is active and valid, false otherwise.
		 * @since 3.2.0
		 */
		public static function has_active_valid_license(): bool {
			if ( self::sdk_reports_valid_license() ) {
				return true;
			}

			// The SDK install record may not exist yet, or may not be readable in
			// this context, causing is_registered() to return false on a site that
			// is in fact licensed. Fall back to the cached premium status written
			// by sync_premium_license() rather than treating the site as
			// unlicensed.
			if ( \is_multisite() ) {
				$main_blog_id    = \get_main_site_id();
				$current_blog_id = \get_current_blog_id();

				// For subsites, check the main site's option.
				if ( $current_blog_id !== $main_blog_id ) {
					if ( 'yes' === \get_blog_option( $main_blog_id, self::FS_WP2FAP_OPTION, 'no' ) ) {
						return true;
					}
				} elseif ( \is_main_site() ) {
					// On the main site (network admin, admin or AJAX), check the local option.
					if ( 'yes' === \get_option( self::FS_WP2FAP_OPTION, 'no' ) ) {
						return true;
					}
				}

				return false;
			}

			// Single site: the same fallback the multisite branch has always had.
			// Without it is_premium() can report true while this method reports
			// false, which registers the "activate your license" page on a
			// licensed site.
			return 'yes' === \get_option( self::FS_WP2FAP_OPTION, 'no' );
		}

		/**
		 * What the Freemius SDK itself says, with no fallback.
		 *
		 * Kept separate from has_active_valid_license() on purpose.
		 * sync_premium_license() writes the cached flag that method falls back
		 * to, so asking it would make the flag decide its own next value: once
		 * 'yes', always 'yes', and a license that expired or was deactivated
		 * would never be noticed. This is the question sync has to ask.
		 *
		 * @return bool
		 *
		 * @since 2.4.0
		 */
		private static function sdk_reports_valid_license(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			$fs = self::plugin_freemius();

			// plugin_freemius() returns false, not null, when the SDK could not be
			// loaded — calling a method on that would be fatal.
			if ( ! is_object( $fs ) ) {
				return false;
			}

			return (bool) ( $fs->is_registered() && $fs->has_active_valid_license() );
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

			return self::plugin_freemius();
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

			return 'yes' === get_option( self::FS_WP2FAP_OPTION );
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

			$fs = self::plugin_freemius();
			if ( null === $fs ) {
				return false;
			}

			$is_registered = $fs->is_registered();

			// On multisite, the per-site or network install may not exist yet.
			// Check the main site's premium status as a proxy for registration.
			if ( ! $is_registered && \is_multisite() ) {
				$main_blog_id    = \get_main_site_id();
				$current_blog_id = \get_current_blog_id();

				if ( $current_blog_id !== $main_blog_id ) {
					$main_site_premium = \get_blog_option( $main_blog_id, self::FS_WP2FAP_OPTION, 'no' );
					if ( 'yes' === $main_site_premium ) {
						$is_registered = true;
					}
				} elseif ( \is_main_site() ) {
					// On the main site (network admin, admin or AJAX), check the local option directly.
					if ( 'yes' === \get_option( self::FS_WP2FAP_OPTION, 'no' ) ) {
						$is_registered = true;
					}
				}
			}

			return $is_registered;
		}

		/**
		 * Get the Freemius license object.
		 *
		 * @return mixed License object or null.
		 *
		 * @since 3.2.0
		 */
		public static function get_license() {
			if ( ! self::is_available() ) {
				return null;
			}

			$fs = self::plugin_freemius();
			if ( null === $fs ) {
				return null;
			}

			return $fs->_get_license();
		}

		/**
		 * Check if the current license is a free license.
		 *
		 * @return bool True if it's a trial, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function is_free(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			$fs = self::plugin_freemius();
			if ( null === $fs ) {
				return false;
			}

			return $fs->is_free_plan();
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
			if ( null === self::plugin_freemius()->_get_license()->quota ) {
				$quota = PHP_INT_MAX;
			} else {
				$quota = (int) self::plugin_freemius()->_get_license()->quota;
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
				return Licensing_Factory::FALLBACK_PRICING_URL;
			}

			$fs = self::plugin_freemius();
			if ( null === $fs ) {
				return Licensing_Factory::FALLBACK_PRICING_URL;
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
				return Licensing_Factory::FALLBACK_ACCOUNT_URL;
			}

			$fs = self::plugin_freemius();
			if ( null === $fs ) {
				return Licensing_Factory::FALLBACK_ACCOUNT_URL;
			}

			return $fs->get_account_url();
		}

		/**
		 * Sync/refresh the license status.
		 *
		 * Delegates to sync_premium_license(), which asks the SDK directly and
		 * honours the grace period. Deriving the value from has_active_valid_license()
		 * could write 'no' whenever the SDK could not answer in this request.
		 *
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 * @since 4.2.0 Uses sync_premium_license().
		 */
		public static function sync_license(): bool {
			if ( ! self::is_available() ) {
				return false;
			}

			self::sync_premium_license();

			return true;
		}

		/**
		 * Activate a license key via Freemius SDK.
		 *
		 * Uses the Freemius SDK's opt_in/install methods to activate
		 * a license key programmatically without requiring the Freemius
		 * connect UI.
		 *
		 * @param string $license_key The license key to activate (sk_ prefixed).
		 * @return bool|array True on success, array with error info on failure.
		 * @since 3.2.0
		 */
		/**
		 * Whether this site actually has a Freemius install behind the stored preference.
		 *
		 * Read straight out of the SDK's own account record rather than by asking the
		 * SDK, because the question is asked precisely when loading the SDK would be
		 * the wrong move: with no install to manage it renders its connect screen over
		 * the plugin's menu page, and that screen offers no way to reach the unified
		 * form or to enter a key for the other provider.
		 *
		 * The provider preference alone is not evidence of a connection. It survives a
		 * deactivation, and an install record can be lost while the preference stays
		 * behind, leaving a site pinned to a provider it is no longer connected to.
		 *
		 * @return bool True when an install record exists for this plugin.
		 * @since 4.2.0
		 */
		/**
		 * The SDK's stored account records, read without going through the option cache.
		 *
		 * This is asked on every request while plugin files are still being included, which is
		 * long before the SDK has declared FS_Plugin and the rest. Reading the option the
		 * ordinary way unserialises it at that moment, so the entities come back as
		 * __PHP_Incomplete_Class — and WordPress then keeps those in its option cache for the
		 * remainder of the request. The SDK loads later, asks for the same option, is handed
		 * the broken objects, and builds its entities out of them:
		 *
		 *   FS_Plugin::__construct(): The script tried to access a property on an
		 *   incomplete object.
		 *
		 * A page of those warnings, and a plugin that looks unlicensed behind them. Reading
		 * the rows directly leaves the cache untouched, so when the SDK asks for the option it
		 * is unserialised for the first time with its classes present and comes back whole.
		 *
		 * The records this returns are only ever inspected with array casts and emptiness
		 * checks, never by reading a property, so an incomplete entity here is harmless.
		 *
		 * @return array<int,array<string,mixed>>
		 *
		 * @since 4.2.0
		 */
		public static function account_records(): array {
			global $wpdb;

			/*
			 * Read afresh every time rather than held for the request. An activation writes
			 * this record and then goes on to ask whether it exists; a remembered "no" from
			 * earlier in the same request would answer that wrongly, and the site would look
			 * unlicensed the moment after it was licensed.
			 */
			$records = array();

			if ( ! isset( $wpdb ) || ! \is_object( $wpdb ) ) {
				return $records;
			}

			$raw = array();

			if ( \is_multisite() ) {
				$raw[] = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read deliberately uncached; see above.
					$wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d LIMIT 1", self::ACCOUNTS_OPTION, \get_current_network_id() )
				);

				// Non network-activated multisite keeps the record against the main blog.
				$raw[] = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
					$wpdb->prepare( "SELECT option_value FROM {$wpdb->base_prefix}options WHERE option_name = %s LIMIT 1", self::ACCOUNTS_OPTION )
				);
			}

			$raw[] = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
				$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::ACCOUNTS_OPTION )
			);

			foreach ( $raw as $value ) {
				if ( ! \is_string( $value ) || '' === $value ) {
					continue;
				}

				/*
				 * Nothing is instantiated. The classes are not loaded yet and asking for them
				 * would be the whole problem again; the shape is all this needs.
				 */
				$decoded = @\unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A malformed record is simply not a record.

				if ( \is_array( $decoded ) ) {
					$records[] = $decoded;
				}
			}

			return $records;
		}

		public static function has_install_record(): bool {
			$slug = Licensing_Factory::FREEMIUS_SLUG;

			foreach ( self::account_records() as $accounts ) {
				if ( ! is_array( $accounts ) || empty( $accounts['sites'] ) || ! is_array( $accounts['sites'] ) ) {
					continue;
				}

				if ( ! empty( $accounts['sites'][ $slug ] ) ) {
					return true;
				}
			}

			return false;
		}

		public static function activate_license( string $license_key ) {
			if ( ! self::is_available() ) {
				return array(
					'success' => false,
					'message' => \__( 'Freemius is not available.', 'wp-2fa' ),
				);
			}

			$fs = self::plugin_freemius();
			if ( null === $fs || false === $fs ) {
				return array(
					'success' => false,
					'message' => \__( 'Could not initialize Freemius SDK.', 'wp-2fa' ),
				);
			}

			$license_key = trim( $license_key );

			if ( empty( $license_key ) ) {
				return array(
					'success' => false,
					'message' => \__( 'License key is required.', 'wp-2fa' ),
				);
			}

			// If the site is already registered with Freemius, use install_with_current_user.
			// Otherwise, use opt_in which handles new registrations.

			// On multisite, pass network sites so the SDK performs a network-level
			// activation. An empty array causes a site-level-only install which
			// makes the SDK show its connect page on the next network admin load.
			$sites = array();
			if ( \is_multisite() && method_exists( $fs, 'get_sites_for_network_level_optin' ) ) {
				$sites = $fs->get_sites_for_network_level_optin();
			}

			/*
			 * install_with_current_user() resolves the Freemius user from the logged-in
			 * WordPress user's email address and hands the result straight to
			 * install_with_user(), which is typed FS_User. When no Freemius user matches
			 * that address the lookup returns false and the SDK fatals on the type — the
			 * Activate button white-screens with no error the user can act on.
			 *
			 * A registered site with no matching user is ordinary: the licence was
			 * activated by one administrator and a different one is now managing it, or
			 * the stored account record has lost its user. opt_in() handles exactly that
			 * case by creating or linking the user, so it is the correct path to take.
			 */
			$has_fs_user = false;
			if ( $fs->is_registered() && method_exists( '\Freemius', '_get_user_by_email' ) ) {
				$has_fs_user = ( \Freemius::_get_user_by_email( \wp_get_current_user()->user_email ) instanceof \FS_User );
			}

			if ( $has_fs_user ) {
				$result = $fs->install_with_current_user( $license_key, false, $sites, false );
			} else {
				$current_user = \wp_get_current_user();
				$result       = $fs->opt_in(
					$current_user->user_email,
					$current_user->first_name,
					$current_user->last_name,
					$license_key,
					false,  // is_uninstall
					false,  // trial_plan_id
					false,  // is_disconnected
					null,   // is_marketing_allowed
					$sites, // sites - pass network sites on multisite for network-level activation
					false   // redirect - IMPORTANT: don't redirect/exit
				);
			}

			// Check if activation was successful.
			if ( is_object( $result ) && isset( $result->error ) ) {
				/*
				 * The SDK reports errors either as an object carrying a 'message' or,
				 * for key rejections such as "Invalid license key.", as a plain string.
				 * Both are surfaced so the reason reaches the user unchanged.
				 */
				if ( is_object( $result->error ) && isset( $result->error->message ) ) {
					$error_message = $result->error->message;
				} elseif ( is_string( $result->error ) && '' !== $result->error ) {
					$error_message = $result->error;
				} else {
					$error_message = \__( 'License activation failed.', 'wp-2fa' );
				}

				return array(
					'success' => false,
					'message' => $error_message,
				);
			}

			// Sync premium license status after successful activation.
			self::sync_premium_license();

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

			// $fs = self::plugin_freemius();
			// if ( null === $fs || false === $fs ) {
			//  return false;
			// }

			// if ( ! $fs->is_registered() ) {
			//  return false;
			// }

			// // Use Freemius SDK's delete_account method to disconnect.
			// $fs->delete_account_event();

			// // Clear local premium status.
			// \update_option( self::FS_WP2FAP_OPTION, 'no' );
			// \delete_transient( self::FS_WP2FAP_OPTION );

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
				return plugin_basename( Licensing_Factory::PLUGIN_FILE );
			}

			$fs = self::plugin_freemius();
			if ( null === $fs ) {
				return plugin_basename( Licensing_Factory::PLUGIN_FILE );
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

			$fs = self::plugin_freemius();
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

			$fs = self::plugin_freemius();
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
		private static function plugin_freemius() {
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
				if ( ! defined( 'WP_FS__PRODUCT_' . Licensing_Factory::FREEMIUS_PLUGIN_ID . '_MULTISITE' ) ) {
					define( 'WP_FS__PRODUCT_' . Licensing_Factory::FREEMIUS_PLUGIN_ID . '_MULTISITE', true );
				}

				// Trial arguments.
				$trial_args = array(
					'days'               => 14,
					'is_require_payment' => false,
				);

				// Check anonymous mode.
				$freemius_state = get_site_option( Licensing_Factory::FREEMIUS_INTERNAL_SLUG . '_freemius_state', 'anonymous' );
				$is_anonymous   = 'anonymous' === $freemius_state || 'skipped' === $freemius_state;
				$is_premium     = true;
				$is_anonymous   = ( $is_premium ? false : $is_anonymous );

				self::$freemius_instance = \fs_dynamic_init(
					array(
						'id'                  => Licensing_Factory::FREEMIUS_PLUGIN_ID,
						'slug'                => Licensing_Factory::FREEMIUS_SLUG,
						'type'                => 'plugin',
						'public_key'          => Licensing_Factory::FREEMIUS_PUBLIC_KEY,
						'premium_suffix'      => '',
						'is_premium'          => true,
						// If your plugin is a serviceware, set this option to false.
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'has_affiliation'     => false,
						'trial'               => $trial_args,
						'menu'                => array(
							'slug'        => Licensing_Factory::MENU_SLUG,
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
				do_action( Licensing_Factory::FREEMIUS_INTERNAL_SLUG . '_freemius_loaded' );
			}

			return self::$freemius_instance;
		}

		/**
		 * Get Freemius SDK file path.
		 *
		 * @return string
		 */
		private static function get_freemius_path(): string {
			return Licensing_Factory::PLUGIN_PATH . DIRECTORY_SEPARATOR . implode(
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
			return 'yes' === get_option( self::FS_WP2FAP_OPTION );
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

			$freemius_transient = get_transient( self::FS_WP2FAP_OPTION );
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
			$option_name = self::FS_WP2FAP_OPTION;
			$old_value   = \get_option( $option_name );

			// The SDK, not has_active_valid_license() — see sdk_reports_valid_license().
			if ( self::sdk_reports_valid_license() ) {
				\update_option( self::FS_LAST_VALID_OPTION, time() );

				if ( 'yes' !== $old_value ) {
					\update_option( $option_name, 'yes' );
				}

				\set_transient( $option_name, 'yes', DAY_IN_SECONDS );

				return;
			}

			/*
			 * A negative answer does not immediately unlicense the site.
			 *
			 * This runs on admin_init, and the first admin load after an upgrade is
			 * exactly when the SDK is most likely to answer badly — which is the
			 * shape of the reported "licensed 2.3 becomes unlicensed on 2.4". Once
			 * the flag is written as 'no' the site is unlicensed for good, because
			 * the flag is also what the fallback in has_active_valid_license()
			 * reads.
			 *
			 * So a previously licensed site is honoured for CHECK_GRACE_PERIOD and
			 * retried sooner, giving a real sync time to succeed. Same trade, and
			 * the same window, as the EDD provider's check.
			 */
			if ( 'yes' === $old_value && ! self::grace_period_expired() ) {
				\set_transient( $option_name, 'yes', self::CHECK_RETRY_INTERVAL );

				return;
			}

			if ( 'no' !== $old_value ) {
				\update_option( $option_name, 'no' );
			}

			\set_transient( $option_name, 'no', DAY_IN_SECONDS );
		}

		/**
		 * Whether the grace period for a previously licensed site has run out.
		 *
		 * On a site that predates this bookkeeping the window starts at the first
		 * negative answer rather than counting as already expired — which is the
		 * upgrade case this exists for.
		 *
		 * @return bool
		 *
		 * @since 2.4.0
		 */
		private static function grace_period_expired(): bool {
			$last_valid = (int) \get_option( self::FS_LAST_VALID_OPTION, 0 );

			if ( $last_valid <= 0 ) {
				$last_valid = time();
				\update_option( self::FS_LAST_VALID_OPTION, $last_valid );
			}

			return ( time() - $last_valid ) > self::CHECK_GRACE_PERIOD;
		}

		/**
		 * Intercept Freemius disconnect form submission before WordPress's page
		 * access check runs. The disconnect form posts to
		 * admin.php?page=wp-2fa-policies-account which may not be a registered page
		 * (the unified licensing system removes it). This handler fires during
		 * admin_menu (before the access check in menu.php line 375) so we can
		 * process the disconnect and redirect to the correct page.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function maybe_handle_freemius_disconnect() {
			if ( ! isset( $_POST['fs_action'] ) || 'delete_account' !== $_POST['fs_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return;
			}

			// Only act when the request targets the (potentially removed) account page.
			$menu_slug = Licensing_Factory::MENU_SLUG;
			if ( ! isset( $_GET['page'] ) || $menu_slug . '-account' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			// Verify nonce (Freemius uses 'delete_account' as nonce action).
			if ( ! \check_admin_referer( 'delete_account' ) ) {
				return;
			}

			// Let the Freemius SDK process the account deletion.
			$fs = self::plugin_freemius();
			if ( $fs && \is_object( $fs ) && \method_exists( $fs, 'delete_account_event' ) ) {
				$fs->delete_account_event();
			}

			// Clean up provider data.
			\delete_option( Licensing_Factory::PROVIDER_OPTION );
			\update_option( self::FS_WP2FAP_OPTION, 'no' );
			\delete_transient( self::FS_WP2FAP_OPTION );

			// Redirect to the plugin's main page.
			$admin_url = \is_multisite() ? \network_admin_url( 'admin.php?page=' . $menu_slug ) : \admin_url( 'admin.php?page=' . $menu_slug );
			\wp_safe_redirect( $admin_url );
			exit;
		}

		/**
		 * Called when the Freemius account is disconnected (deleted).
		 *
		 * Clears the provider preference so the unified license page shows
		 * on next page load, allowing the user to activate with either provider.
		 * Redirects to the plugin's default page to avoid landing on the
		 * non-existent wp-2fa-policies-account page.
		 *
		 * Hooked to: after_account_delete, after_network_account_delete.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function on_account_disconnect() {
			\delete_option( Licensing_Factory::PROVIDER_OPTION );
			\update_option( self::FS_WP2FAP_OPTION, 'no' );
			\delete_transient( self::FS_WP2FAP_OPTION );

			$menu_slug = Licensing_Factory::MENU_SLUG;
			$admin_url = \is_multisite() ? \network_admin_url( 'admin.php?page=' . $menu_slug ) : \admin_url( 'admin.php?page=' . $menu_slug );
			\wp_safe_redirect( $admin_url );
			exit;
		}

		/**
		 * Called when the Freemius license is deactivated (but account remains connected).
		 *
		 * Clears the provider preference and premium status so the unified license
		 * page shows on next page load.
		 *
		 * Hooked to: after_license_deactivation.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function on_license_deactivation() {
			// \delete_option( Licensing_Factory::PROVIDER_OPTION );
			\update_option( self::FS_WP2FAP_OPTION, 'no' );
			\delete_transient( self::FS_WP2FAP_OPTION );
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
				esc_html__( 'Hey %s', 'wp-2fa' ),
				$user_first_name
			);
			$result .= ',<br>';
			$result .= esc_html__( 'Never miss an important update! Opt-in to our security and feature updates notifications, and non-sensitive diagnostic tracking with freemius.com.', 'wp-2fa' ) .
			$result .= '<br /><br /><strong>' . esc_html__( 'Note:', 'wp-2fa' ) . '</strong> ';
			$result .= esc_html( Licensing_Factory::OPTIN_DISCLAIMER );

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
				'<a href="' . esc_url( Licensing_Factory::FREEMIUS_OPTIN_URL ) . '" target="_blank" tabindex="1">freemius.com</a>',
				'<strong>' . $plugin_title . '</strong>'
			);
			$result .= '<br /><br /><strong>' . esc_html__( 'Note:', 'wp-2fa' ) . '</strong> ';
			$result .= esc_html( Licensing_Factory::OPTIN_DISCLAIMER );

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
			if ( isset( $msg['id'] ) && 'connect_account' === $msg['id'] ) {
				return false;
			}

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

			if ( is_object( $error ) && property_exists( $error, 'message' ) ) {
				$error = $error->message;
			}

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
			$pricing_page = Licensing_Factory::MENU_SLUG . '-pricing';
			if ( array_key_exists( 'page', $_GET ) && $pricing_page === \wp_unslash( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				\wp_redirect( Licensing_Factory::FREEMIUS_PRICING_REDIRECT_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- intentional external redirect to configured URL.
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
			if ( ( ! self::plugin_freemius()->is_premium() ) || ( ! method_exists( self::plugin_freemius(), 'override_il8n' ) ) ) {
				return;
			}

			self::plugin_freemius()->override_i18n(
				array(
					/* translators: %2$s: activation link */
					'few-plugin-tweaks' => sprintf(
						/* translators: 1: plugin name, 2: activation link */
						__( 'You need to activate the license key to use %1$s. %%2$s', 'wp-2fa' ),
						Licensing_Factory::PLUGIN_NAME
					),
					'optin-x-now'       => __( 'Activate the license key now', 'wp-2fa' ),
				)
			);
		}
	}
}
