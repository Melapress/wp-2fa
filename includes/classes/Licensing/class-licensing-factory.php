<?php
/**
 * Licensing Factory for WP2FA plugin.
 *
 * Central entry point for licensing operations. This factory determines which
 * licensing provider to use (Freemius or EDD) and routes all licensing calls
 * through the appropriate provider implementation.
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

use WP2FA\Extensions_Loader;
use WP2FA\Licensing\EDD_Provider;
use WP2FA\Licensing\Freemius_Provider;

if ( ! class_exists( '\WP2FA\Licensing\Licensing_Factory' ) ) {

	/**
	 * Factory class for licensing providers.
	 *
	 * Implements a singleton pattern and provides a unified interface
	 * to whichever licensing provider is active.
	 *
	 * @since 3.2.0
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

		/**
		 * Option name for storing the preferred provider.
		 *
		 * @var string
		 */
		const PROVIDER_OPTION = 'wp2fa_licensing_provider';

		/**
		 * Initialize the licensing factory.
		 *
		 * This should be called early in the plugin bootstrap process.
		 *
		 * @return void
		 * @since 3.2.0
		 */
		public static function init() {
			$provider = self::get_provider();
			if ( $provider ) {
				$provider::init();
			}

			// Hook to allow switching providers via admin.
			add_action( 'admin_init', array( __CLASS__, 'maybe_switch_provider' ) );

			if ( null !== $provider && class_exists( '\WP2FA\Extensions_Loader' ) ) {
				Extensions_Loader::init();
			}
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
			$preferred_provider = apply_filters( 'wp2fa_licensing_provider', $preferred_provider );

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

			// Auto-detect based on availability - Freemius takes priority.
			if ( Freemius_Provider::is_available() ) {
				self::$provider      = Freemius_Provider::class;
				self::$provider_type = 'freemius';
				return self::$provider;
			}

			// Fallback to EDD.
			if ( EDD_Provider::is_available() ) {
				self::$provider      = EDD_Provider::class;
				self::$provider_type = 'edd';
				return self::$provider;
			}

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
			if ( ! isset( $_GET['wp2fa_switch_provider'] ) || ! isset( $_GET['_wpnonce'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp2fa_switch_provider' ) ) {
				return;
			}

			$new_provider = sanitize_text_field( wp_unslash( $_GET['wp2fa_switch_provider'] ) );

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
			return $provider ? $provider::get_pricing_url() : 'https://melapress.com/wordpress-2fa/pricing/';
		}

		/**
		 * Proxy method: Get the account/dashboard URL.
		 *
		 * @return string Account/dashboard URL.
		 * @since 3.2.0
		 */
		public static function get_account_url(): string {
			$provider = self::get_provider();
			return $provider ? $provider::get_account_url() : 'https://melapress.com/account/';
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
			return $provider ? $provider::get_plugin_basename() : plugin_basename( WP_2FA_FILE );
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
	}
}
