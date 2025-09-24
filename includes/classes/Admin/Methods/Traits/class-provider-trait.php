<?php
/**
 * Responsible for the plugin login attempts
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Responsible for the providers basic functionality.
 *
 * @since 2.9.2
 */
trait Providers {

	/**
	 * Inits all of the common hooks for all of the providers.
	 *
	 * @return void
	 *
	 * @since 2.9.2
	 */
	public static function always_init() {

		\add_filter( WP_2FA_PREFIX . 'providers', array( __CLASS__, 'provider' ) );
	}

	/**
	 * Adds email as a provider
	 *
	 * @param array $providers - Array with all currently supported providers.
	 *
	 * @return array
	 *
	 * @since 2.6.0
	 */
	public static function provider( array $providers ) {
		$providers[ static::class ] = static::METHOD_NAME;

		return $providers;
	}
}
