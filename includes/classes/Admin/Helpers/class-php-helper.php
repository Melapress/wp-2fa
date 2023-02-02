<?php
/**
 * Responsible for the User's operations
 *
 * @package    wp2fa
 * @subpackage helpers
 * @since      latest
 * @copyright  2023 WP White Security
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * User's settings class
 */
if ( ! class_exists( '\WP2FA\Admin\Helpers\PHP_Helper' ) ) {

	/**
	 * All the user related settings must go trough this class.
	 *
	 * @since 2.4.0
	 */
	class PHP_Helper {

		/**
		 * Checks if given function is callable (exists) or not
		 *
		 * @param string $function - The name of the function to check.
		 *
		 * @return boolean
		 *
		 * @since 2.4.0
		 */
		public static function is_callable( string $function ): bool {
			if ( ! is_callable( $function ) ) {
				return false;
			}

			return true;
		}
	}
}
