<?php
/**
 * Licensing Factory for WP 2FA plugin.
 *
 * Central entry point for licensing operations. This factory determines which
 * licensing provider to use (Freemius or EDD) and routes all licensing calls
 * through the appropriate provider implementation.
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
use WP2FA\Admin\Settings_Page;
use WP2FA\Licensing\EDD_Provider;
use WP2FA\Licensing\Freemius_Provider;

if ( ! class_exists( '\WP2FA\Licensing\Licensing_Factory' ) ) {

	/**
	 * Factory class for licensing providers.
	 *
	 * Implements a singleton pattern and provides a unified interface
	 * to whichever licensing provider is active.
	 *
	 * @since 2.0.0
	 */
	class Licensing_Factory {

		/**
		 * The active licensing provider instance.
		 *
		 * @var Licensing_Provider|null
		 * @since 3.2.0
		 */
		private static $provider = null;

		/**
		 * The provider type being used.
		 *
		 * @var string|null
		 * @since 3.2.0
		 */
		private static $provider_type = null;

		/*
		|----------------------------------------------------------------------
		| Plugin-specific configuration.
		|
		| Change ONLY these constants when adapting for a different plugin.
		| All other Licensing classes reference Licensing_Factory for these.
		|----------------------------------------------------------------------
		*/

		/**
		 * Text domain used for translations.
		 *
		 * @var string
		 */
		public const TEXT_DOMAIN = 'wp-2fa';

		/**
		 * Human-readable plugin name (used in admin notices).
		 *
		 * @var string
		 */
		public const PLUGIN_NAME = 'WP 2FA Premium';

		/**
		 * Pricing page URL template.
		 *
		 * @var string
		 */
		public const PRICING_URL = 'https://melapress.com/wordpress-2fa/pricing/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=edd_pricing';

		/**
		 * Admin menu icon render callback class (fully qualified).
		 * Set to empty string if not applicable.
		 *
		 * @var string
		 */
		// 2FA embeds its menu icon as a base64 SVG directly in add_menu_page(), so
		// there is no separate admin_head style callback to register. The license
		// page guards this with ! empty() and method_exists(), so empty is valid.
		public const MENU_ICON_CLASS = '';

		/**
		 * Admin menu icon render callback method.
		 *
		 * @var string
		 */
		public const MENU_ICON_METHOD = 'render_menu_icon_styles';

		/**
		 * Human-readable menu title for the top-level admin menu.
		 *
		 * @var string
		 */
		public const MENU_TITLE = 'WP 2FA';

		/**
		 * Short prefix used for unified AJAX actions, nonce, script handles, and HTML IDs.
		 * Must be unique per plugin and URL-safe (lowercase, no spaces).
		 *
		 * @var string
		 */
		public const SLUG_PREFIX = 'wp2fa';

		/**
		 * Freemius plugin ID.
		 *
		 * @var string
		 */
		public const FREEMIUS_PLUGIN_ID = '8257';

		/**
		 * Freemius internal slug (underscored).
		 *
		 * @var string
		 */
		public const FREEMIUS_INTERNAL_SLUG = 'wp_2fa';

		/**
		 * Freemius external slug (hyphenated).
		 *
		 * @var string
		 */
		public const FREEMIUS_SLUG = 'wp-2fa';

		/**
		 * Freemius public key.
		 *
		 * @var string
		 */
		public const FREEMIUS_PUBLIC_KEY = 'pk_b8cc4c0bbe2df3365f23c225a7889';

		/**
		 * Freemius premium status option name.
		 *
		 * @var string
		 */
		public const FREEMIUS_PREMIUM_OPTION = 'fs_wp2fap';

		/**
		 * Plugin icon path (relative to plugin root) for Freemius.
		 *
		 * @var string
		 */
		public const PLUGIN_ICON_PATH = 'dist/images/wp-2fa-white-icon20x28.svg';

		/**
		 * External pricing page URL for Freemius redirect.
		 *
		 * @var string
		 */
		public const FREEMIUS_PRICING_URL = 'https://melapress.com/wordpress-2fa/pricing/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=priciing_url';

		/**
		 * External pricing page redirect URL (used in Freemius redirect handler).
		 *
		 * @var string
		 */
		public const FREEMIUS_PRICING_REDIRECT_URL = 'https://melapress.com/wordpress-2fa/pricing/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=redirect_to_external_price_page';

		/**
		 * Freemius connect message URL for the opt-in message.
		 *
		 * @var string
		 */
		public const FREEMIUS_OPTIN_URL = 'https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=optin_message';

		/*
		|----------------------------------------------------------------------
		| Plugin identity constants (wrap the plugin's global defines).
		|----------------------------------------------------------------------
		*/

		/**
		 * Absolute path to the main plugin file.
		 *
		 * @var string
		 */
		public const PLUGIN_FILE = WP_2FA_FILE;

		/**
		 * Absolute path to the plugin directory (with trailing slash).
		 *
		 * @var string
		 */
		public const PLUGIN_PATH = WP_2FA_PATH;

		/**
		 * URL to the plugin directory (with trailing slash).
		 *
		 * @var string
		 */
		public const PLUGIN_URL = WP_2FA_URL;

		/**
		 * Current plugin version string.
		 *
		 * @var string
		 */
		public const PLUGIN_VERSION = WP_2FA_VERSION;

		/**
		 * Plugin basename (e.g. 'wp-2fa-premium/wp-2fa.php').
		 *
		 * @var string
		 */
		// 2FA defines WP_2FA_BASE (not ..._BASENAME) as plugin_basename( __FILE__ ).
		public const PLUGIN_BASENAME = WP_2FA_BASE;

		/**
		 * Plugin directory slug (folder name).
		 *
		 * @var string
		 */
		public const PLUGIN_SLUG = 'wp-2fa-premium';

		/**
		 * Admin menu slug (top-level page).
		 *
		 * @var string
		 */
		// Sourced from the class that actually registers the top-level menu, so the
		// license submenu cannot drift away from it.
		public const MENU_SLUG = Settings_Page::TOP_MENU_SLUG;

		/*
		|----------------------------------------------------------------------
		| Unified licensing configuration.
		|----------------------------------------------------------------------
		*/

		/**
		 * Option name for storing the preferred licensing provider.
		 *
		 * @var string
		 */
		public const PROVIDER_OPTION = 'wp2fa_licensing_provider';

		/**
		 * Query-string parameter for switching providers.
		 *
		 * @var string
		 */
		public const SWITCH_PROVIDER_PARAM = 'wp2fa_switch_provider';

		/**
		 * Nonce action for unified license AJAX requests.
		 *
		 * @var string
		 */
		public const UNIFIED_NONCE_ACTION = 'wp2fa_unified_license';

		/**
		 * Script handle for the unified license JS.
		 *
		 * @var string
		 */
		public const UNIFIED_SCRIPT_HANDLE = 'wp2fa-unified-licensing';

		/**
		 * Page slug for the unified license submenu page.
		 *
		 * @var string
		 */
		public const UNIFIED_LICENSE_PAGE_SLUG = 'wp2fa-license';

		/**
		 * AJAX action: unified license activation.
		 *
		 * @var string
		 */
		public const UNIFIED_AJAX_ACTIVATE = 'wp2fa_unified_activate_license';

		/**
		 * AJAX action: unified license deactivation.
		 *
		 * @var string
		 */
		public const UNIFIED_AJAX_DEACTIVATE = 'wp2fa_unified_deactivate_license';

		/**
		 * AJAX action: unified license sync.
		 *
		 * @var string
		 */
		public const UNIFIED_AJAX_SYNC = 'wp2fa_unified_sync_license';

		/**
		 * AJAX action: unified license change (EDD only).
		 *
		 * @var string
		 */
		public const UNIFIED_AJAX_CHANGE = 'wp2fa_unified_change_license';

		/**
		 * Fallback pricing URL (used when no provider is available).
		 *
		 * @var string
		 */
		public const FALLBACK_PRICING_URL = 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=upgrade_pricing_fallback';

		/**
		 * Fallback account URL (used when no provider is available).
		 *
		 * @var string
		 */
		public const FALLBACK_ACCOUNT_URL = 'https://melapress.com/account/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=account_fallback';

		/*
		|----------------------------------------------------------------------
		| Network licensing configuration.
		|----------------------------------------------------------------------
		*/

		/**
		 * Network option for storing per-site activation data.
		 *
		 * @var string
		 */
		public const NETWORK_ACTIVATIONS_OPTION = 'wp2fa_edd_network_activations';

		/**
		 * Transient name for tracking batch activation progress.
		 *
		 * @var string
		 */
		public const NETWORK_PROGRESS_TRANSIENT = 'wp2fa_edd_activation_progress';

		/**
		 * Network option flag for failed subsite activation (insufficient slots).
		 *
		 * @var string
		 */
		public const NETWORK_ACTIVATION_FAILED_FLAG = 'wp2fa_edd_subsite_activation_failed';

		/*
		|----------------------------------------------------------------------
		| Freemius UI messages.
		|----------------------------------------------------------------------
		*/

		/**
		 * Disclaimer text shown in Freemius opt-in messages.
		 *
		 * @var string
		 */
		public const OPTIN_DISCLAIMER = 'NO LOGIN SECURITY DATA IS SENT BACK TO OUR SERVERS.';


		/**
		 * Initialize the licensing factory.
		 *
		 * This should be called early in the plugin bootstrap process.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function init() {
			$has_license_data = self::has_stored_license_data();

			// Only initialize a provider if there's evidence of a prior activation.
			// This prevents Freemius SDK from loading its connect page on fresh installs
			// where our unified license form should be the only entry point.
			if ( $has_license_data ) {
				$provider = self::get_provider();
				if ( $provider ) {
					$provider::init();
				}
			}

			// Hook to allow switching providers via admin.
			add_action( 'admin_init', array( __CLASS__, 'maybe_switch_provider' ) );

			// Determine if we have an active valid license.
			// Only check via the provider if stored data exists (avoids triggering SDK).
			$is_licensed  = $has_license_data && self::has_active_valid_license();
			$is_free_mode = $has_license_data && ! $is_licensed;

			// Register unified license page when no license data exists at all (fresh install).
			// In free mode (expired license), keep normal menus and add license as submenu.
			if ( ! $is_licensed && ! $is_free_mode ) {
				if ( \is_multisite() ) {
					add_action( 'network_admin_menu', array( __CLASS__, 'register_license_page' ), 900 );
				} else {
					add_action( 'admin_menu', array( __CLASS__, 'register_license_page' ), 900 );
				}
			} else {
				// When license is active, register license management submenu.
				// Skip if Freemius is the provider — it handles its own Account page.
				if ( 'freemius' !== self::get_provider_type() ) {
					if ( \is_multisite() ) {
						add_action( 'network_admin_menu', array( __CLASS__, 'register_license_submenu' ), 50 );
					} else {
						add_action( 'admin_menu', array( __CLASS__, 'register_license_submenu' ), 50 );
					}
				}

				// Ensure the license/account submenu is always the last item.
				if ( \is_multisite() ) {
					add_action( 'network_admin_menu', array( __CLASS__, 'reorder_license_submenu_last' ), 99999 );
				} else {
					add_action( 'admin_menu', array( __CLASS__, 'reorder_license_submenu_last' ), 99999 );
				}
			}

			// Unified AJAX handler for license activation.
			add_action( 'wp_ajax_' . self::UNIFIED_AJAX_ACTIVATE, array( __CLASS__, 'ajax_activate_license' ) );
			add_action( 'wp_ajax_' . self::UNIFIED_AJAX_DEACTIVATE, array( __CLASS__, 'ajax_deactivate_license' ) );
			add_action( 'wp_ajax_' . self::UNIFIED_AJAX_SYNC, array( __CLASS__, 'ajax_sync_license' ) );
			add_action( 'wp_ajax_' . self::UNIFIED_AJAX_CHANGE, array( __CLASS__, 'ajax_change_license' ) );
		}

		/**
		 * Check if there's stored license data from a prior activation.
		 *
		 * Used to determine if provider initialization (and SDK loading) should
		 * happen. On a fresh install with no prior activation, this returns false
		 * which prevents the Freemius SDK from loading its connect page.
		 *
		 * @return bool True if license data exists for any provider.
		 * @since 3.3.0
		 */
		public static function has_stored_license_data(): bool {
			// On multisite, license data may be on the main site.
			$is_multisite = \is_multisite();
			$main_site_id = $is_multisite ? \get_main_site_id() : 0;

			// Check EDD license data (read from the main site on multisite).
			$edd_key = EDD_Provider::get_license_option( EDD_Provider::LICENSE_KEY_OPTION, '' );
			if ( ! empty( $edd_key ) ) {
				if ( empty( \get_option( self::PROVIDER_OPTION, '' ) ) ) {
					\update_option( self::PROVIDER_OPTION, 'edd' );
				}
				if ( $is_multisite && empty( \get_blog_option( $main_site_id, self::PROVIDER_OPTION, '' ) ) ) {
					\update_blog_option( $main_site_id, self::PROVIDER_OPTION, 'edd' );
				}
				return true;
			}

			/**
			 * Check Freemius premium option (indicates prior Freemius activation).
			 *
			 * Uses null as the default to distinguish "option doesn't exist" (fresh
			 * install) from "option is 'no'" (Freemius was active but sync or
			 * deactivation set it to 'no'). On multisite, the first admin load
			 * after a plugin update can trigger sync_premium_license() before the
			 * SDK is ready, writing 'no' even though the license is still valid.
			 * Checking for existence rather than 'yes' prevents this from
			 * misidentifying the site as a fresh install.
			 */
			/*
			 * Both Freemius markers below describe a connection, so neither counts for
			 * anything once that connection is gone.
			 *
			 * The markers outlive the install they refer to: a deactivation leaves them
			 * behind, and an install record can be lost while they stay. Treating them as
			 * evidence on their own pins the site to Freemius for good — the SDK is
			 * loaded, it finds no install to manage, and it renders its own connect
			 * screen over the plugin's menu page. That screen cannot accept an EDD key
			 * and offers no route back to the unified form, so the site is stuck on a
			 * form that can never activate anything.
			 *
			 * Requiring an install record keeps the case these markers exist for — a
			 * connected site whose licence has lapsed, which still needs its Freemius
			 * account page — while a preference with nothing behind it falls through to
			 * the unified form, where either provider's key can be entered.
			 */
			$freemius_connected = Freemius_Provider::has_install_record();

			$fs_premium = \get_option( Freemius_Provider::FS_WP2FAP_OPTION, null );
			if ( null === $fs_premium && $is_multisite ) {
				$fs_premium = \get_blog_option( $main_site_id, Freemius_Provider::FS_WP2FAP_OPTION, null );
			}
			if ( null !== $fs_premium && $freemius_connected ) {
				if ( empty( \get_option( self::PROVIDER_OPTION, '' ) ) ) {
					\update_option( self::PROVIDER_OPTION, 'freemius' );
				}
				if ( $is_multisite && empty( \get_blog_option( $main_site_id, self::PROVIDER_OPTION, '' ) ) ) {
					\update_blog_option( $main_site_id, self::PROVIDER_OPTION, 'freemius' );
				}
				return true;
			}

			// Check explicit provider preference (set after first activation).
			$provider = \get_option( self::PROVIDER_OPTION, '' );
			if ( empty( $provider ) && $is_multisite ) {
				$provider = \get_blog_option( $main_site_id, self::PROVIDER_OPTION, '' );
				if ( ! empty( $provider ) ) {
					\update_option( self::PROVIDER_OPTION, $provider );
				}
			}
			if ( ! empty( $provider ) ) {
				if ( 'freemius' === $provider && ! $freemius_connected ) {
					return false;
				}

				return true;
			}

			return false;
		}

		/**
		 * Get the active licensing provider.
		 *
		 * Determines which provider to use based on availability and configuration.
		 * Priority order:
		 * 1. Explicitly configured provider (via option or filter)
		 * 2. Freemius (if available)
		 * 3. EDD (fallback)
		 *
		 * @param bool $force_refresh Force re-detection of provider.
		 * @return Licensing_Provider|null The active provider class name or null.
		 * @since 3.2.0
		 */
		public static function get_provider( bool $force_refresh = false ) {
			if ( null !== self::$provider && ! $force_refresh ) {
				return self::$provider;
			}

			// Check for explicitly configured provider.
			$preferred_provider = get_option( self::PROVIDER_OPTION, '' );

			// Allow filtering the preferred provider.
			// $preferred_provider = apply_filters( self::SLUG_PREFIX . '_licensing_provider', $preferred_provider );

			/*
			 * Resolving to Freemius loads its SDK, and the SDK with no install to manage
			 * puts its connect screen over the plugin's menu page — a screen that cannot
			 * take an EDD key and offers no way back to the unified form.
			 *
			 * Guarding the bootstrap alone is not enough, because resolution happens from
			 * anywhere: any caller of has_active_valid_license(), is_registered() or
			 * provider_call() reaches this method and boots the SDK as a side effect of
			 * asking a question. Refusing to resolve a provider that has nothing behind
			 * it settles that for every call site at once.
			 */
			if ( 'freemius' === $preferred_provider && ! Freemius_Provider::has_install_record() ) {
				$preferred_provider = '';
			}

			// Validate and use preferred provider if specified and available.
			if ( ! empty( $preferred_provider ) ) {
				if ( 'freemius' === $preferred_provider && Freemius_Provider::is_available() ) {
					self::$provider      = Freemius_Provider::class;
					self::$provider_type = 'freemius';
					return self::$provider;
				} elseif ( 'edd' === $preferred_provider && EDD_Provider::is_available() ) {
					self::$provider      = EDD_Provider::class;
					self::$provider_type = 'edd';
					return self::$provider;
				}
			}

			// // Auto-detect based on availability - Freemius takes priority.
			// if ( Freemius_Provider::is_available() ) {
			// self::$provider      = Freemius_Provider::class;
			// self::$provider_type = 'freemius';
			// return self::$provider;
			// }

			// // Fallback to EDD.
			// if ( EDD_Provider::is_available() ) {
			// self::$provider      = EDD_Provider::class;
			// self::$provider_type = 'edd';
			// return self::$provider;
			// }

			// No provider available.
			self::$provider      = null;
			self::$provider_type = null;
			return null;
		}

		/**
		 * Get the provider type name.
		 *
		 * @return string Provider type ('freemius', 'edd', or 'none').
		 * @since 3.2.0
		 */
		public static function get_provider_type(): string {
			if ( null === self::$provider_type ) {
				self::get_provider();
			}

			return self::$provider_type ?? 'none';
		}

		/**
		 * Check if a provider is active.
		 *
		 * @return bool True if a provider is available, false otherwise.
		 * @since 3.2.0
		 */
		public static function has_provider(): bool {
			return null !== self::get_provider();
		}

		/**
		 * Set the preferred licensing provider.
		 *
		 * @param string $provider Provider type ('freemius' or 'edd').
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 */
		public static function set_provider( string $provider ): bool {
			if ( ! in_array( $provider, array( 'freemius', 'edd' ), true ) ) {
				return false;
			}

			// Verify the provider is available.
			if ( 'freemius' === $provider && ! Freemius_Provider::is_available() ) {
				return false;
			}

			if ( 'edd' === $provider && ! EDD_Provider::is_available() ) {
				return false;
			}

			update_option( self::PROVIDER_OPTION, $provider );
			self::get_provider( true ); // Force refresh.

			return true;
		}

		/**
		 * Maybe switch provider based on admin request.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function maybe_switch_provider() {
			if ( ! isset( $_GET[ self::SWITCH_PROVIDER_PARAM ] ) || ! isset( $_GET['_wpnonce'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::SWITCH_PROVIDER_PARAM ) ) {
				return;
			}

			$new_provider = sanitize_text_field( wp_unslash( $_GET[ self::SWITCH_PROVIDER_PARAM ] ) );

			if ( self::set_provider( $new_provider ) ) {
				add_action(
					'admin_notices',
					function () use ( $new_provider ) {
						echo '<div class="notice notice-success is-dismissible"><p>';
						printf(
							/* translators: %s: provider name */
							esc_html__( 'Licensing provider switched to %s successfully.', 'wp-2fa' ),
							esc_html( ucfirst( $new_provider ) )
						);
						echo '</p></div>';
					}
				);
			}
		}

		/**
		 * Proxy method: Check if the license is active and valid.
		 *
		 * @return bool True if license is active and valid, false otherwise.
		 * @since 3.2.0
		 */
		public static function has_active_valid_license(): bool {
			$provider = self::get_provider();
			return $provider ? $provider::has_active_valid_license() : false;
		}

		/**
		 * Proxy method: Check if the premium version is active.
		 *
		 * @return bool True if premium is active, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_premium(): bool {
			$provider = self::get_provider();
			return $provider ? $provider::is_premium() : false;
		}

		/**
		 * Proxy method: Check if the plugin is registered.
		 *
		 * @return bool True if registered, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_registered(): bool {
			$provider = self::get_provider();
			return $provider ? $provider::is_registered() : false;
		}

		/**
		 * Proxy method: Get the license object/data.
		 *
		 * @return mixed License object or data structure, null if not available.
		 * @since 3.2.0
		 */
		public static function get_license() {
			$provider = self::get_provider();
			return $provider ? $provider::get_license() : null;
		}

		/**
		 * Proxy method: Get the license quota.
		 *
		 * @return int Number of allowed users/sites, -1 if unlimited or unavailable.
		 * @since 3.2.0
		 */
		public static function get_license_quota(): int {
			$provider = self::get_provider();
			return $provider ? $provider::get_license_quota() : -1;
		}

		/**
		 * Proxy method: Check if license quota has been exceeded.
		 *
		 * @return bool True if quota exceeded, false otherwise.
		 * @since 3.2.0
		 */
		public static function is_quota_exceeded(): bool {
			$provider = self::get_provider();
			return $provider ? $provider::is_quota_exceeded() : false;
		}

		/**
		 * Proxy method: Get the pricing page URL.
		 *
		 * @return string Pricing page URL.
		 * @since 3.2.0
		 */
		public static function get_pricing_url(): string {
			$provider = self::get_provider();
			return $provider ? $provider::get_pricing_url() : self::FALLBACK_PRICING_URL;
		}

		/**
		 * Proxy method: Get the account/dashboard URL.
		 *
		 * @return string Account/dashboard URL.
		 * @since 3.2.0
		 */
		public static function get_account_url(): string {
			$provider = self::get_provider();
			return $provider ? $provider::get_account_url() : self::FALLBACK_ACCOUNT_URL;
		}

		/**
		 * Proxy method: Sync/refresh the license status.
		 *
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 */
		public static function sync_license(): bool {
			$provider = self::get_provider();
			return $provider ? $provider::sync_license() : false;
		}

		/**
		 * Proxy method: Activate a license key.
		 *
		 * @param string $license_key The license key to activate.
		 * @return bool|array True on success, array with error info on failure.
		 * @since 3.2.0
		 */
		public static function activate_license( string $license_key ) {
			$provider = self::get_provider();
			return $provider ? $provider::activate_license( $license_key ) : false;
		}

		/**
		 * Proxy method: Deactivate the current license.
		 *
		 * @return bool True on success, false on failure.
		 * @since 3.2.0
		 */
		public static function deactivate_license(): bool {
			$provider = self::get_provider();
			return $provider ? $provider::deactivate_license() : false;
		}

		/**
		 * Proxy method: Get the plugin basename.
		 *
		 * @return string Plugin basename.
		 * @since 3.2.0
		 */
		public static function get_plugin_basename(): string {
			$provider = self::get_provider();
			return $provider ? $provider::get_plugin_basename() : plugin_basename( self::PLUGIN_FILE );
		}

		/**
		 * Proxy method: Add an action hook.
		 *
		 * @param string   $tag      The action hook name.
		 * @param callable $callback The callback function.
		 * @param int      $priority Priority.
		 * @param int      $args     Number of arguments.
		 * @return void
		 * @since 3.2.0
		 */
		public static function add_action( string $tag, callable $callback, int $priority = 10, int $args = 1 ) {
			$provider = self::get_provider();
			if ( $provider ) {
				$provider::add_action( $tag, $callback, $priority, $args );
			}
		}

		/**
		 * Proxy method: Add a filter hook.
		 *
		 * @param string   $tag      The filter hook name.
		 * @param callable $callback The callback function.
		 * @param int      $priority Priority.
		 * @param int      $args     Number of arguments.
		 * @return void
		 * @since 3.2.0
		 */
		public static function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ) {
			$provider = self::get_provider();
			if ( $provider ) {
				$provider::add_filter( $tag, $callback, $priority, $args );
			}
		}

		/**
		 * Call a method on the currently selected provider if it exists.
		 *
		 * @param string $method Method name to call on the provider.
		 * @param mixed  ...$args Optional arguments to pass to the provider method.
		 * @return mixed|null Result of the provider method call, or null if not callable.
		 * @since 3.2.0
		 */
		public static function provider_call( string $method, ...$args ) {
			$provider = self::get_provider();
			if ( ! $provider ) {
				return null;
			}

			if ( method_exists( $provider, $method ) && is_callable( array( $provider, $method ) ) ) {
				return forward_static_call_array( array( $provider, $method ), $args );
			} elseif ( $provider::get_provider_instance() && method_exists( $provider::get_provider_instance(), $method ) ) {
				return call_user_func_array( array( $provider::get_provider_instance(), $method ), $args );
			}

			return null;
		}

		/**
		 * Get information about available providers.
		 *
		 * @return array Array of provider information.
		 * @since 3.2.0
		 */
		public static function get_available_providers(): array {
			$providers = array();

			if ( Freemius_Provider::is_available() ) {
				$providers['freemius'] = array(
					'name'      => 'Freemius',
					'available' => true,
					'active'    => 'freemius' === self::get_provider_type(),
				);
			}

			if ( EDD_Provider::is_available() ) {
				$providers['edd'] = array(
					'name'      => 'Easy Digital Downloads',
					'available' => true,
					'active'    => 'edd' === self::get_provider_type(),
				);
			}

			return $providers;
		}

		/*
		|----------------------------------------------------------------------
		| Unified License Page.
		|
		| Provides a single license activation interface that accepts both
		| Freemius (sk_ prefix) and EDD license keys. The key type is
		| detected server-side and routed to the appropriate provider.
		|----------------------------------------------------------------------
		*/

		/**
		 * Register the unified license page as a top-level menu item.
		 *
		 * When no valid license exists, this becomes the only accessible page.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function register_license_page() {
			global $_registered_pages, $_parent_pages, $admin_page_hooks;

			$menu_slug = self::MENU_SLUG;

			// Preserve the admin_page_hooks value before removing/re-adding the menu.
			// Freemius registers submenu pages (e.g. wp-2fa-policies-account) using a
			// hookname derived from admin_page_hooks[menu_slug]. If we change this
			// value by re-adding the menu with a different title, WordPress can no
			// longer resolve the account page's hookname, breaking access checks.
			$saved_page_hook = isset( $admin_page_hooks[ $menu_slug ] ) ? $admin_page_hooks[ $menu_slug ] : null;

			// Remove any previously registered page at this slug (from Freemius SDK or Admin class).
			\remove_menu_page( $menu_slug );

			// Also unregister any page callbacks that were hooked to this slug's hookname.
			$hookname = get_plugin_page_hookname( $menu_slug, '' );
			if ( ! empty( $hookname ) ) {
				\remove_all_actions( $hookname );
			}
			unset( $_registered_pages[ $hookname ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			unset( $_parent_pages[ $menu_slug ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			/*
			 * MENU_TITLE is the product name, so it is not translated: gettext cannot extract
			 * a constant anyway — the string never reached the .pot — and a brand name stays
			 * the same in every locale.
			 */
			$hook = \add_menu_page(
				self::MENU_TITLE,
				self::MENU_TITLE,
				\is_multisite() ? 'manage_network_options' : 'manage_options',
				$menu_slug,
				array( __CLASS__, 'render_license_page' ),
				' ',
				99
			);

			// Restore the original admin_page_hooks value so Freemius submenu pages
			// (like the account page) retain their correct hookname for access checks.
			if ( null !== $saved_page_hook ) {
				$admin_page_hooks[ $menu_slug ] = $saved_page_hook; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}

			// Add menu icon styles if a callback is configured.
			if ( ! empty( self::MENU_ICON_CLASS ) && method_exists( self::MENU_ICON_CLASS, self::MENU_ICON_METHOD ) ) {
				\add_action( 'admin_head', array( self::MENU_ICON_CLASS, self::MENU_ICON_METHOD ) );
			}

			\add_action( "load-{$hook}", array( __CLASS__, 'enqueue_license_scripts' ) );

			// Remove any submenus registered by other modules.
			$menu_hook = \is_multisite() ? 'network_admin_menu' : 'admin_menu';
			\add_action( $menu_hook, array( __CLASS__, 'remove_all_submenus' ), 999 );
		}

		/**
		 * Register the license page as a submenu (when license is active).
		 *
		 * @return void
		 * @since 2.4.0
		 */
		public static function register_license_submenu() {
			$menu_slug = self::MENU_SLUG;

			$hook = \add_submenu_page(
				$menu_slug,
				\__( 'Manage License', 'wp-2fa' ),
				\__( 'Manage License', 'wp-2fa' ),
				\is_multisite() ? 'manage_network_options' : 'manage_options',
				self::UNIFIED_LICENSE_PAGE_SLUG,
				array( __CLASS__, 'render_license_page' ),
				100
			);

			\add_action( "load-{$hook}", array( __CLASS__, 'enqueue_license_scripts' ) );
		}

		/**
		 * Reorder the submenu so the license/account item is always last.
		 *
		 * Handles both our own "Manage License" page (EDD) and the
		 * Freemius SDK "Account" page by moving the matching entry
		 * to the end of the submenu array.
		 *
		 * @return void
		 * @since 2.4.0
		 */
		public static function reorder_license_submenu_last() {
			global $submenu;

			$menu_slug = self::MENU_SLUG;

			if ( empty( $submenu[ $menu_slug ] ) ) {
				return;
			}

			// Slugs that identify the license/account submenu entry.
			$license_slugs = array(
				self::UNIFIED_LICENSE_PAGE_SLUG,        // EDD "Manage License".
				$menu_slug . '-account',               // Freemius "Account".
			);

			$license_index = null;

			foreach ( $submenu[ $menu_slug ] as $index => $item ) {
				if ( isset( $item[2] ) && \in_array( $item[2], $license_slugs, true ) ) {
					$license_index = $index;
					break;
				}
			}

			if ( null === $license_index ) {
				return;
			}

			// Remove the entry and re-append it at the end.
			$license_item = $submenu[ $menu_slug ][ $license_index ];
			unset( $submenu[ $menu_slug ][ $license_index ] );
			$submenu[ $menu_slug ][] = $license_item;
		}

		/**
		 * Remove all submenus under the plugin's top-level menu.
		 *
		 * Ensures only the license activation page is accessible
		 * when no valid license exists. Also removes any Freemius-registered
		 * menu pages that would conflict with the unified license page.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function remove_all_submenus() {
			global $submenu;

			$menu_slug = self::MENU_SLUG;

			if ( isset( $submenu[ $menu_slug ] ) ) {
				// Preserve the Freemius account page if user has an active Freemius
				// connection. Removing it breaks WordPress's admin page access check
				// (get_admin_page_parent cannot resolve the parent), which prevents
				// the disconnect form from being processed.
				$account_slug = $menu_slug . '-account';
				$preserved    = array();

				if ( 'freemius' === self::get_provider_type() || \get_option( Freemius_Provider::FS_WP2FAP_OPTION, '' ) === 'yes' ) {
					foreach ( $submenu[ $menu_slug ] as $key => $item ) {
						if ( isset( $item[2] ) && $account_slug === $item[2] ) {
							$preserved[ $key ] = $item;
						}
					}
				}

				$submenu[ $menu_slug ] = $preserved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		/**
		 * License changes affect the entire network on multisite.
		 *
		 * @return bool
		 */
		public static function can_manage_license(): bool {
			return \current_user_can( \is_multisite() ? 'manage_network_options' : 'manage_options' );
		}

		/**
		 * Enqueue scripts for the unified license page.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function enqueue_license_scripts() {
			if ( ! self::can_manage_license() ) {
				return;
			}

			$plugin_url     = self::PLUGIN_URL;
			$plugin_version = self::PLUGIN_VERSION;
			$menu_slug      = self::MENU_SLUG;

			\wp_enqueue_style(
				'wp2fa-licensing-form',
				$plugin_url . 'includes/classes/Licensing/licensing-form.css',
				array(),
				$plugin_version
			);

			\wp_enqueue_script(
				self::UNIFIED_SCRIPT_HANDLE,
				$plugin_url . 'includes/classes/Licensing/unified-licensing.js',
				array(),
				$plugin_version,
				true
			);

			\wp_localize_script(
				self::UNIFIED_SCRIPT_HANDLE,
				'unifiedLicense',
				array(
					'ajaxUrl'     => \admin_url( 'admin-ajax.php' ),
					'nonce'       => \wp_create_nonce( self::UNIFIED_NONCE_ACTION ),
					'redirectUrl' => \network_admin_url( 'admin.php?page=' . $menu_slug ),
					'isMultisite' => \is_multisite(),
					'prefix'      => self::SLUG_PREFIX,
					'actions'     => array(
						'activate'   => self::UNIFIED_AJAX_ACTIVATE,
						'deactivate' => self::UNIFIED_AJAX_DEACTIVATE,
						'sync'       => self::UNIFIED_AJAX_SYNC,
						'change'     => self::UNIFIED_AJAX_CHANGE,
					),
					'i18n'        => array(
						'activatingText'     => \esc_html__( 'Activating...', 'wp-2fa' ),
						'deactivatingText'   => \esc_html__( 'Deactivating...', 'wp-2fa' ),
						'syncingText'        => \esc_html__( 'Syncing...', 'wp-2fa' ),
						'enterLicenseKey'    => \esc_html__( 'Please enter a license key.', 'wp-2fa' ),
						'activateBtn'        => \esc_html__( 'Activate', 'wp-2fa' ),
						'deactivateBtn'      => \esc_html__( 'Deactivate', 'wp-2fa' ),
						'syncBtn'            => \esc_html__( 'Sync License', 'wp-2fa' ),
						'activateNewBtn'     => \esc_html__( 'Activate New', 'wp-2fa' ),
						'cancelBtn'          => \esc_html__( 'Cancel', 'wp-2fa' ),
						'changingText'       => \esc_html__( 'Changing...', 'wp-2fa' ),
						'changedSuccess'     => \esc_html__( 'License changed successfully.', 'wp-2fa' ),
						'changeFailed'       => \esc_html__( 'License change failed.', 'wp-2fa' ),
						'networkError'       => \esc_html__( 'A network error occurred. Please try again.', 'wp-2fa' ),
						'activatedSuccess'   => \esc_html__( 'License activated successfully.', 'wp-2fa' ),
						'activationFailed'   => \esc_html__( 'Activation failed.', 'wp-2fa' ),
						'deactivatedSuccess' => \esc_html__( 'License deactivated successfully.', 'wp-2fa' ),
						'deactivationFailed' => \esc_html__( 'Deactivation failed.', 'wp-2fa' ),
						'syncedSuccess'      => \esc_html__( 'License synced successfully.', 'wp-2fa' ),
						'syncFailed'         => \esc_html__( 'Sync failed.', 'wp-2fa' ),
					),
				)
			);
		}

		/**
		 * Render the unified license page.
		 *
		 * Shows the same interface regardless of the licensing provider.
		 * Users can enter either Freemius (sk_) or EDD license keys.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function render_license_page() {
			if ( ! self::can_manage_license() ) {
				return;
			}

			$is_active     = self::has_active_valid_license();
			$provider_type = self::get_provider_type();
			$license_key   = '';
			$status        = '';
			$item_name     = '';
			$expires       = '';

			// Get license details from the active provider.
			if ( 'edd' === $provider_type ) {
				$license_key  = EDD_Provider::get_license_option( EDD_Provider::LICENSE_KEY_OPTION, '' );
				$status       = EDD_Provider::get_license_option( EDD_Provider::LICENSE_STATUS_OPTION, '' );
				$license_data = EDD_Provider::get_license_option( EDD_Provider::LICENSE_DATA_OPTION, array() );

				if ( is_array( $license_data ) ) {
					$item_name = isset( $license_data['item_name'] ) ? $license_data['item_name'] : '';
					$expires   = isset( $license_data['expires'] ) ? $license_data['expires'] : '';
				}
			} elseif ( 'freemius' === $provider_type ) {
				$fs = Freemius_Provider::get_provider_instance();
				if ( $fs && $fs->is_registered() ) {
					$license = $fs->_get_license();
					if ( is_object( $license ) ) {
						$license_key = $license->secret_key ?? '';
						$expires     = $license->expiration ?? '';
					}
				}
			}

			// Format expiration date.
			$expiry_display = '';
			$id_prefix      = self::SLUG_PREFIX;

			if ( ! empty( $expires ) && 'lifetime' !== $expires ) {
				$expiry_display = \wp_date( \get_option( 'date_format' ), strtotime( $expires ) );
			} elseif ( 'lifetime' === $expires ) {
				$expiry_display = \__( 'Lifetime', 'wp-2fa' );
			}

			?>
			<div class="wp2fa-license-wrap">
				<h2><?php \esc_html_e( 'Activate your license key', 'wp-2fa' ); ?></h2>

				<div class="wp2fa-license-card">
					<img src="<?php echo \esc_url( self::PLUGIN_URL . 'dist/images/wp-2fa-square.png' ); ?>"
						alt="<?php echo \esc_attr( self::PLUGIN_NAME ); ?>"
						class="wp2fa-license-logo" />

					<div id="<?php echo \esc_attr( $id_prefix ); ?>-license-message" class="wp2fa-license-notice"></div>

					<div id="<?php echo \esc_attr( $id_prefix ); ?>-license-progress" class="wp2fa-license-progress">
						<p><span id="<?php echo \esc_attr( $id_prefix ); ?>-license-progress-text"></span></p>
						<progress id="<?php echo \esc_attr( $id_prefix ); ?>-license-progress-bar" max="100" value="0"></progress>
					</div>

					<?php if ( ! $is_active ) : ?>
						<p class="wp2fa-license-card-title">
							<?php
							printf(
								/* translators: %s: plugin name */
								\esc_html__( 'To get started with %s, please enter your license key below:', 'wp-2fa' ),
								'<strong>' . \esc_html( self::PLUGIN_NAME ) . '</strong>'
							);
							?>
						</p>

						<div class="wp2fa-license-input-row">
							<input type="text"
								id="<?php echo \esc_attr( $id_prefix ); ?>-license-key"
								name="license_key"
								placeholder="<?php \esc_attr_e( 'Paste your license key', 'wp-2fa' ); ?>"
								value=""
								autocomplete="off" />
							<button type="button" id="<?php echo \esc_attr( $id_prefix ); ?>-license-activate" class="wp2fa-license-btn">
								<?php \esc_html_e( 'Activate License', 'wp-2fa' ); ?>
							</button>
						</div>

						<p class="wp2fa-license-help">
							<?php
							printf(
								/* translators: %s: contact link */
								\esc_html__( "Can't find your license key? %s so we can assist you.", 'wp-2fa' ),
								'<a href="mailto:support@melapress.com">' . \esc_html__( 'Contact us', 'wp-2fa' ) . '</a>'
							);
							?>
						</p>
					<?php else : ?>
						<p class="wp2fa-license-card-title">
							<?php
							printf(
								/* translators: %s: plugin name */
								\esc_html__( 'Your %s license is active.', 'wp-2fa' ),
								'<strong>' . \esc_html( self::PLUGIN_NAME ) . '</strong>'
							);
							?>
						</p>

						<div class="wp2fa-license-input-row">
							<input type="password"
								id="<?php echo \esc_attr( $id_prefix ); ?>-license-key"
								name="license_key"
								value="<?php echo \esc_attr( $license_key ); ?>"
								readonly />
							<button type="button" id="<?php echo \esc_attr( $id_prefix ); ?>-license-deactivate" class="wp2fa-license-btn">
								<?php \esc_html_e( 'Deactivate', 'wp-2fa' ); ?>
							</button>
						</div>

						<div class="wp2fa-license-actions">
							<button type="button" id="<?php echo \esc_attr( $id_prefix ); ?>-license-sync" class="wp2fa-license-btn-secondary">
								<?php \esc_html_e( 'Sync License', 'wp-2fa' ); ?>
							</button>
							<?php if ( 'edd' === $provider_type ) : ?>
								<button type="button" id="<?php echo \esc_attr( $id_prefix ); ?>-license-change" class="wp2fa-license-btn-secondary">
									<?php \esc_html_e( 'Change License', 'wp-2fa' ); ?>
								</button>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<hr class="wp2fa-license-divider" />

					<div class="wp2fa-license-status-row">
						<span class="wp2fa-license-status-label"><?php \esc_html_e( 'Status', 'wp-2fa' ); ?></span>
						<span id="<?php echo \esc_attr( $id_prefix ); ?>-license-status" class="wp2fa-license-badge wp2fa-license-badge--<?php echo \esc_attr( $is_active ? 'active' : ( 'expired' === $status ? 'expired' : 'inactive' ) ); ?>">
							<?php
							if ( $is_active ) {
								\esc_html_e( 'Active', 'wp-2fa' );
							} elseif ( 'expired' === $status ) {
								\esc_html_e( 'Expired', 'wp-2fa' );
							} else {
								\esc_html_e( 'Not activated', 'wp-2fa' );
							}
							?>
						</span>
					</div>

					<?php if ( $is_active ) : ?>
						<div class="wp2fa-license-details">
							<dl class="wp2fa-license-details-table">
								<?php if ( ! empty( $item_name ) ) : ?>
									<div class="wp2fa-license-detail-row">
										<dt><?php \esc_html_e( 'Plan', 'wp-2fa' ); ?></dt>
										<dd><?php echo \esc_html( $item_name ); ?></dd>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $expiry_display ) ) : ?>
									<div class="wp2fa-license-detail-row">
										<dt><?php \esc_html_e( 'Expires', 'wp-2fa' ); ?></dt>
										<dd><?php echo \esc_html( $expiry_display ); ?></dd>
									</div>
								<?php endif; ?>
							</dl>
						</div>
					<?php endif; ?>
				</div>

				<p class="wp2fa-license-footer">
					<?php
					printf(
						/* translators: 1: plugin name, 2: bold server name */
						\esc_html__( 'For license management, and to deliver security & feature updates, %1$s connects to the %2$s.', 'wp-2fa' ),
						\esc_html( self::PLUGIN_NAME ),
						'<strong>' . \esc_html__( 'Melapress licensing servers', 'wp-2fa' ) . '</strong>'
					);
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Detect the license key type based on prefix.
		 *
		 * @param string $license_key The license key to check.
		 * @return string 'freemius' if key starts with sk_, 'edd' otherwise.
		 * @since 3.3.0
		 */
		public static function detect_key_type( string $license_key ): string {
			if ( strpos( $license_key, 'sk_' ) === 0 ) {
				return 'freemius';
			}

			return 'edd';
		}

		/**
		 * Unified AJAX handler for license activation.
		 *
		 * Detects key type (Freemius sk_ prefix vs EDD) and routes
		 * to the appropriate provider for activation.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function ajax_activate_license() {
			\check_ajax_referer( self::UNIFIED_NONCE_ACTION, 'nonce' );

			if ( ! self::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$license_key = isset( $_POST['license_key'] ) ? \sanitize_text_field( \wp_unslash( $_POST['license_key'] ) ) : '';

			if ( empty( $license_key ) ) {
				\wp_send_json_error( array( 'message' => \__( 'License key is required.', 'wp-2fa' ) ) );
			}

			$key_type = self::detect_key_type( $license_key );

			// Verify the target provider is available.
			if ( 'freemius' === $key_type && ! Freemius_Provider::is_available() ) {
				\wp_send_json_error( array( 'message' => \__( 'Freemius licensing is not available in this build.', 'wp-2fa' ) ) );
			}

			if ( 'edd' === $key_type && ! EDD_Provider::is_available() ) {
				\wp_send_json_error( array( 'message' => \__( 'EDD licensing is not available in this build.', 'wp-2fa' ) ) );
			}

			/*
			 * Remember the provider preference the site had before this attempt so a
			 * failed activation can be rolled back.
			 *
			 * The provider preference must not be persisted until the key has actually
			 * been accepted. Writing it up front means a single rejected key (a typo, a
			 * key for another product) permanently pins the site to that provider:
			 * has_stored_license_data() then reports stored data, the license screen
			 * renders that provider's own opt-in page instead of the unified form, and
			 * there is no route back to the form to try a different key.
			 */
			$restore_point = self::licence_marker_snapshot();

			// Set the provider preference based on the key type.
			self::set_provider( $key_type );

			// Route activation to the detected provider.
			if ( 'freemius' === $key_type ) {
				$result = Freemius_Provider::activate_license( $license_key );
			} else {
				$result = EDD_Provider::activate_license( $license_key );
			}

			if ( true === $result ) {
				\wp_send_json_success( array( 'message' => \__( 'License activated successfully.', 'wp-2fa' ) ) );
			} else {
				self::restore_licence_markers( $restore_point );

				\wp_send_json_error( array( 'message' => self::activation_error_message( $result ) ) );
			}
		}

		/**
		 * Every option an activation attempt can write, with its current value.
		 *
		 * EDD stores the key and the store's response before it has looked at whether the
		 * store accepted them, so a rejected key leaves those markers behind. Stored EDD
		 * data is read as a prior activation, which puts the site into free mode as though
		 * it had once been licensed and takes the unified form off the licence screen.
		 *
		 * Rolling back only the provider preference is therefore not enough to undo a
		 * failed attempt. Null records "this option did not exist", which is distinct from
		 * an empty value and has to be restored as absence rather than as ''.
		 *
		 * @return array<string,mixed>
		 * @since 4.2.0
		 */
		private static function licence_marker_snapshot(): array {
			$snapshot = array();

			foreach ( self::licence_marker_options() as $name ) {
				// The provider preference is per site; EDD license state lives on the main site on multisite.
				if ( self::PROVIDER_OPTION === $name ) {
					$snapshot[ $name ] = \get_option( $name, null );
				} else {
					$snapshot[ $name ] = EDD_Provider::get_license_option( $name, null );
				}
			}

			return $snapshot;
		}

		/**
		 * Puts the licence markers back exactly as they were before an attempt.
		 *
		 * @param array<string,mixed> $snapshot Values taken by licence_marker_snapshot().
		 *
		 * @return void
		 * @since 4.2.0
		 */
		private static function restore_licence_markers( array $snapshot ): void {
			foreach ( $snapshot as $name => $value ) {
				// The provider preference is per site; EDD license state lives on the main site on multisite.
				if ( self::PROVIDER_OPTION === $name ) {
					if ( null === $value ) {
						\delete_option( $name );
					} else {
						\update_option( $name, $value );
					}
					continue;
				}

				if ( null === $value ) {
					EDD_Provider::delete_license_option( $name );
				} else {
					EDD_Provider::update_license_option( $name, $value );
				}
			}

			self::get_provider( true ); // Force refresh.
		}

		/**
		 * The options that together say whether, and how, this site is licensed.
		 *
		 * @return array<int,string>
		 * @since 4.2.0
		 */
		private static function licence_marker_options(): array {
			return array(
				self::PROVIDER_OPTION,
				EDD_Provider::LICENSE_KEY_OPTION,
				EDD_Provider::LICENSE_STATUS_OPTION,
				EDD_Provider::LICENSE_DATA_OPTION,
				EDD_Provider::PREMIUM_OPTION,
				EDD_Provider::LAST_VALID_OPTION,
			);
		}

		/**
		 * Extract a human readable message from a provider activation failure.
		 *
		 * Providers report failures in more than one shape: an array with a 'message'
		 * key, an object whose 'error' is itself an object with a 'message', or an
		 * object whose 'error' is a plain string. The Freemius API uses the last of
		 * these for key rejections, so without handling it the specific reason the
		 * store gave ("Invalid license key.") is replaced by a generic failure notice.
		 *
		 * @param mixed $result Provider activation result.
		 *
		 * @return string
		 * @since 3.3.0
		 */
		private static function activation_error_message( $result ): string {
			if ( is_array( $result ) && ! empty( $result['message'] ) && \is_string( $result['message'] ) ) {
				return $result['message'];
			}

			if ( is_object( $result ) && isset( $result->error ) ) {
				if ( is_object( $result->error ) && ! empty( $result->error->message ) && \is_string( $result->error->message ) ) {
					return $result->error->message;
				}

				if ( \is_string( $result->error ) && '' !== $result->error ) {
					return $result->error;
				}
			}

			return \__( 'License activation failed.', 'wp-2fa' );
		}

		/**
		 * Unified AJAX handler for license deactivation.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function ajax_deactivate_license() {
			\check_ajax_referer( self::UNIFIED_NONCE_ACTION, 'nonce' );

			if ( ! self::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$result = self::deactivate_license();

			if ( $result ) {
				// Clear provider preference on deactivation.
				\delete_option( self::PROVIDER_OPTION );
				\wp_send_json_success( array( 'message' => \__( 'License deactivated successfully.', 'wp-2fa' ) ) );
			} else {
				\wp_send_json_error( array( 'message' => \__( 'Failed to deactivate license.', 'wp-2fa' ) ) );
			}
		}

		/**
		 * Unified AJAX handler for license sync.
		 *
		 * @return void
		 * @since 3.3.0
		 */
		public static function ajax_sync_license() {
			\check_ajax_referer( self::UNIFIED_NONCE_ACTION, 'nonce' );

			if ( ! self::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			$result = self::sync_license();

			if ( $result ) {
				\wp_send_json_success( array( 'message' => \__( 'License synced successfully.', 'wp-2fa' ) ) );
			} else {
				\wp_send_json_error( array( 'message' => \__( 'Failed to sync license data.', 'wp-2fa' ) ) );
			}
		}

		/**
		 * Unified AJAX handler for changing the license key (EDD only).
		 *
		 * Activates the new license first, then deactivates the old one
		 * on the store. If the new activation fails, the old license
		 * remains untouched.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function ajax_change_license() {
			\check_ajax_referer( self::UNIFIED_NONCE_ACTION, 'nonce' );

			if ( ! self::can_manage_license() ) {
				\wp_send_json_error( array( 'message' => \__( 'Permission denied.', 'wp-2fa' ) ) );
			}

			if ( 'edd' !== self::get_provider_type() ) {
				\wp_send_json_error( array( 'message' => \__( 'License change is not supported for this provider.', 'wp-2fa' ) ) );
			}

			$new_key = isset( $_POST['license_key'] ) ? \sanitize_text_field( \wp_unslash( $_POST['license_key'] ) ) : '';

			if ( empty( $new_key ) ) {
				\wp_send_json_error( array( 'message' => \__( 'License key is required.', 'wp-2fa' ) ) );
			}

			// Capture old license details before activation overwrites them.
			$old_key         = EDD_Provider::get_license_option( EDD_Provider::LICENSE_KEY_OPTION, '' );
			$old_data        = EDD_Provider::get_license_option( EDD_Provider::LICENSE_DATA_OPTION, array() );
			$old_item_id     = is_array( $old_data ) && isset( $old_data['item_id'] ) ? (int) $old_data['item_id'] : 0;
			$old_status      = EDD_Provider::get_license_option( EDD_Provider::LICENSE_STATUS_OPTION, '' );
			$old_premium     = EDD_Provider::get_license_option( EDD_Provider::PREMIUM_OPTION, '' );
			$old_last_valid  = EDD_Provider::get_license_option( EDD_Provider::LAST_VALID_OPTION, 0 );
			$old_sites       = array();
			$old_activations = array();

			if ( \is_multisite() ) {
				$old_activations = EDD_Network_Licensing::get_network_activation_status();
				$old_sites       = array_keys( $old_activations );
			}

			// Prevent deactivating the same key that was just activated.
			if ( $new_key === $old_key ) {
				\wp_send_json_error( array( 'message' => \__( 'The new license key is the same as the current one.', 'wp-2fa' ) ) );
			}

			// Try activating the new license first.
			$result = EDD_Provider::activate_license( $new_key );

			if ( true !== $result ) {
				// Restore old license data — activate_license() may have overwritten it.
				EDD_Provider::update_license_option( EDD_Provider::LICENSE_KEY_OPTION, $old_key );
				EDD_Provider::update_license_option( EDD_Provider::LICENSE_DATA_OPTION, $old_data );
				EDD_Provider::update_license_option( EDD_Provider::LICENSE_STATUS_OPTION, $old_status );
				EDD_Provider::update_license_option( EDD_Provider::PREMIUM_OPTION, $old_premium );
				EDD_Provider::update_license_option( EDD_Provider::LAST_VALID_OPTION, $old_last_valid );

				// Restore network activation data and clear stale transients.
				if ( \is_multisite() ) {
					\update_site_option( EDD_Network_Licensing::NETWORK_ACTIVATIONS_OPTION, $old_activations );
					\delete_site_transient( EDD_Network_Licensing::PROGRESS_TRANSIENT );
				}

				EDD_Provider::delete_license_transient( EDD_Provider::LICENSE_CHECK_TRANSIENT );
				EDD_Provider::delete_license_transient( EDD_Provider::PREMIUM_OPTION );

				if ( '' !== $old_premium ) {
					EDD_Provider::set_license_transient( EDD_Provider::PREMIUM_OPTION, $old_premium, EDD_Provider::LICENSE_CHECK_INTERVAL );
				}

				$error_message = is_array( $result ) && isset( $result['message'] )
					? $result['message']
					: \__( 'License activation failed.', 'wp-2fa' );
				\wp_send_json_error( array( 'message' => $error_message ) );
			}

			// New license activated — deactivate the old one on the store.
			$deactivation_failed = false;

			if ( ! empty( $old_key ) && $old_item_id > 0 ) {
				if ( \is_multisite() && ! empty( $old_sites ) ) {
					foreach ( $old_sites as $site_url ) {
						if ( ! EDD_Provider::try_deactivate_for_url( $old_key, $old_item_id, $site_url ) ) {
							$deactivation_failed = true;
						}
					}
				} else {
					if ( ! EDD_Provider::try_deactivate_for_url( $old_key, $old_item_id, \home_url() ) ) {
						$deactivation_failed = true;
					}
				}
			}

			if ( $deactivation_failed ) {
				\wp_send_json_success(
					array(
						'message' => \__( 'License changed successfully. Note: the previous license could not be fully deactivated. Please contact support if activation slots are not freed.', 'wp-2fa' ),
					)
				);
			}

			\wp_send_json_success( array( 'message' => \__( 'License changed successfully.', 'wp-2fa' ) ) );
		}

		/**
		 * Get the unified license page URL.
		 *
		 * @return string License page admin URL.
		 * @since 3.3.0
		 */
		public static function get_license_page_url(): string {
			if ( self::has_active_valid_license() ) {
				return \network_admin_url( 'admin.php?page=' . self::UNIFIED_LICENSE_PAGE_SLUG );
			}

			$menu_slug = self::MENU_SLUG;
			return \network_admin_url( 'admin.php?page=' . $menu_slug );
		}
	}
}
