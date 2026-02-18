<?php
/**
 * Licensing Provider Interface for WP2FA plugin.
 *
 * Defines the common interface that all licensing providers must implement.
 * This allows the plugin to support multiple licensing systems (Freemius, EDD, etc.)
 * through a unified API.
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

/**
 * Interface for licensing providers.
 *
 * @since 3.2.0
 */
interface Licensing_Provider {

	/**
	 * Initialize the licensing provider.
	 *
	 * Sets up hooks, filters, and any necessary configurations.
	 *
	 * @return void
	 * @since 3.2.0
	 */
	public static function init();

	/**
	 * Check if the license is active and valid.
	 *
	 * @return bool True if license is active and valid, false otherwise.
	 * @since 3.2.0
	 */
	public static function has_active_valid_license(): bool;

	/**
	 * Check if the premium version is active.
	 *
	 * Resource cautious function intended for quick check during initial stages
	 * of plugin bootstrap, especially on front-end.
	 *
	 * @return bool True if premium is active, false otherwise.
	 * @since 3.2.0
	 */
	public static function is_premium(): bool;

	/**
	 * Check if the plugin is registered with the licensing provider.
	 *
	 * @return bool True if registered, false otherwise.
	 * @since 3.2.0
	 */
	public static function is_registered(): bool;

	/**
	 * Get the license object/data.
	 *
	 * @return mixed License object or data structure, null if not available.
	 * @since 3.2.0
	 */
	public static function get_license();

	/**
	 * Get the license quota (number of allowed users/sites).
	 *
	 * @return int Number of allowed users/sites, -1 if unlimited or unavailable.
	 * @since 3.2.0
	 */
	public static function get_license_quota(): int;

	/**
	 * Check if license quota has been exceeded.
	 *
	 * @return bool True if quota exceeded, false otherwise.
	 * @since 3.2.0
	 */
	public static function is_quota_exceeded(): bool;

	/**
	 * Get the pricing page URL.
	 *
	 * @return string Pricing page URL.
	 * @since 3.2.0
	 */
	public static function get_pricing_url(): string;

	/**
	 * Get the account/dashboard URL.
	 *
	 * @return string Account/dashboard URL.
	 * @since 3.2.0
	 */
	public static function get_account_url(): string;

	/**
	 * Sync/refresh the license status.
	 *
	 * Forces a check with the licensing server to update local license status.
	 *
	 * @return bool True on success, false on failure.
	 * @since 3.2.0
	 */
	public static function sync_license(): bool;

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key The license key to activate.
	 * @return bool|array True on success, array with error info on failure.
	 * @since 3.2.0
	 */
	public static function activate_license( string $license_key );

	/**
	 * Deactivate the current license.
	 *
	 * @return bool True on success, false on failure.
	 * @since 3.2.0
	 */
	public static function deactivate_license(): bool;

	/**
	 * Get the provider name.
	 *
	 * @return string Provider name (e.g., 'freemius', 'edd').
	 * @since 3.2.0
	 */
	public static function get_provider_name(): string;

	/**
	 * Check if this provider is available/configured.
	 *
	 * @return bool True if provider is available, false otherwise.
	 * @since 3.2.0
	 */
	public static function is_available(): bool;

	/**
	 * Get the plugin basename.
	 *
	 * @return string Plugin basename (e.g., 'wp-2fa/wp-2fa.php').
	 * @since 3.2.0
	 */
	public static function get_plugin_basename(): string;

	/**
	 * Add an action hook specific to this provider.
	 *
	 * @param string   $tag      The action hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Priority (default 10).
	 * @param int      $args     Number of arguments (default 1).
	 * @return void
	 * @since 3.2.0
	 */
	public static function add_action( string $tag, callable $callback, int $priority = 10, int $args = 1 );

	/**
	 * Add a filter hook specific to this provider.
	 *
	 * @param string   $tag      The filter hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Priority (default 10).
	 * @param int      $args     Number of arguments (default 1).
	 * @return void
	 * @since 3.2.0
	 */
	public static function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 );
}
