<?php
/**
 * Responsible for the plugin login attempts
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

use WP2FA\Admin\Helpers\User_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
/**
 * Responsible for the login attempts
 *
 * @since 2.4.1
 */
trait Login_Attempts {

	/**
	 * Holds the number of allowed attempts to login
	 *
	 * @var integer
	 *
	 * @since 2.4.1
	 */
	private static $number_of_allowed_attempts = 3;

	/**
	 * Increasing login attempts for User
	 *
	 * @since 2.4.1
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return void
	 */
	public static function increase_login_attempts( \WP_User $user ) {
		$attempts = self::get_login_attempts( $user );
		if ( '' === $attempts ) {
			$attempts = 0;
		}
		User_Helper::set_meta( self::$logging_attempts_meta_key, ++$attempts, $user );
	}

	/**
	 * Returns the number of unsuccessful attempts for the User
	 *
	 * @since 2.4.1
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return integer
	 */
	public static function get_login_attempts( \WP_User $user ): int {
		return (int) User_Helper::get_meta( self::$logging_attempts_meta_key, $user );
	}

	/**
	 * Clearing login attempts for User
	 *
	 * @since 2.4.1
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return void
	 */
	public static function clear_login_attempts( \WP_User $user ) {
		User_Helper::remove_meta( self::$logging_attempts_meta_key, $user );
	}

	/**
	 * Returns the number of allowed login attempts
	 *
	 * @return integer
	 *
	 * @since 2.4.1
	 */
	public static function get_allowed_login_attempts(): int {
		return self::$number_of_allowed_attempts;
	}

	/**
	 * Sets the number of allowed attempts
	 *
	 * @param integer $number - The number of the allowed attempts.
	 *
	 * @return integer
	 *
	 * @since 2.4.1
	 */
	public static function set_number_of_login_attempts( int $number ): int {
		self::$number_of_allowed_attempts = $number;

		return self::$number_of_allowed_attempts;
	}

	/**
	 * Returns the name of the meta key holding the login attempts for the user
	 *
	 * @return string
	 *
	 * @since 2.4.1
	 */
	public static function get_meta_key(): string {

		return self::$logging_attempts_meta_key;
	}

	/**
	 * Sets the login attempts meta key
	 *
	 * @param string $logging_attempts_meta_key - The name of the meta.
	 *
	 * @return string
	 *
	 * @since 2.4.1
	 */
	public static function set_meta_key( string $logging_attempts_meta_key ): string {
		self::$logging_attempts_meta_key = $logging_attempts_meta_key;

		return self::$logging_attempts_meta_key;
	}

	/**
	 * Checks the number of login attempts
	 *
	 * @param \WP_User $user - The user we have to check for.
	 *
	 * @return boolean
	 *
	 * @since 2.4.1
	 */
	public static function check_number_of_attempts( \WP_User $user ): bool {
		if ( self::get_allowed_login_attempts() < self::get_login_attempts( $user ) ) {
			return false;
		}

		return true;
	}
}
