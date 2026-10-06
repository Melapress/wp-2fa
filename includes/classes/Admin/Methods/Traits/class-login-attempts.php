<?php
/**
 * Responsible for the plugin login attempts
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2026 Melapress
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
	 * Shared across all login providers and independent of a password login.
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return bool
	 *
	 * @since 4.2.0
	 */
	public static function second_factor_locked( \WP_User $user ): bool {
		$legacy = \get_user_meta( $user->ID, WP_2FA_PREFIX . 'second_factor_limit', true );
		if ( \is_array( $legacy ) && (int) ( $legacy['locked_until'] ?? 0 ) > \time() ) {
			return true;
		}
		$state = self::second_factor_attempt_state( $user );
		return null !== $state && $state['count'] >= 5 && $state['expires'] > \time();
	}

	/**
	 * Seconds until a locked second factor can be tried again; 0 if not locked.
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return int
	 *
	 * @since 4.2.0
	 */
	public static function second_factor_lock_remaining( \WP_User $user ): int {
		if ( ! self::second_factor_locked( $user ) ) {
			return 0;
		}

		$until  = 0;
		$legacy = \get_user_meta( $user->ID, WP_2FA_PREFIX . 'second_factor_limit', true );
		if ( \is_array( $legacy ) ) {
			$until = (int) ( $legacy['locked_until'] ?? 0 );
		}
		$state = self::second_factor_attempt_state( $user );
		if ( null !== $state ) {
			$until = max( $until, $state['expires'] );
		}

		return max( 1, $until - \time() );
	}

	/**
	 * What a locked-out user is told: that they are locked out, and for how long.
	 *
	 * "Too many attempts" alone left them retrying, each time refused, with no
	 * idea whether to wait a minute or call someone.
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return string Plain text; escape for the context it is printed in.
	 *
	 * @since 4.2.0
	 */
	public static function second_factor_lock_message( \WP_User $user ): string {
		$remaining = self::second_factor_lock_remaining( $user );

		if ( $remaining <= 0 ) {
			return __( 'Too many verification attempts. Please try again later.', 'wp-2fa' );
		}

		return sprintf(
			/* translators: %s: how long until the user can try again, e.g. "12 mins". */
			__( 'Too many verification attempts. Please try again in %s, or ask the site administrator to unlock your account.', 'wp-2fa' ),
			\human_time_diff( \time(), \time() + $remaining )
		);
	}

	/**
	 * Reserve one attempt before validating a code. The unique option_name index
	 * serializes competing inserts and updates across PHP workers.
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return bool Whether this request may validate its code.
	 *
	 * @since 4.2.0
	 */
	public static function reserve_second_factor_attempt( \WP_User $user ): bool {
		global $wpdb;
		if ( self::second_factor_locked( $user ) ) {
			return false;
		}
		$table = self::second_factor_options_table();
		$key   = self::second_factor_option_key( $user );
		$now   = \time();
		$old   = \get_user_meta( $user->ID, WP_2FA_PREFIX . 'second_factor_limit', true );
		if ( \is_array( $old ) && (int) ( $old['window_start'] ?? 0 ) + 15 * MINUTE_IN_SECONDS > $now ) {
			$expires = max( (int) ( $old['locked_until'] ?? 0 ), (int) $old['window_start'] + 15 * MINUTE_IN_SECONDS );
			$count   = min( 5, max( 0, (int) ( $old['count'] ?? 0 ) ) );
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $expires . ':' . $count ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
		}

		$expires = $now + 15 * MINUTE_IN_SECONDS;
		$seeded  = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $expires . ':0' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
		if ( false === $seeded ) {
			return false;
		}
		$written = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE $table SET option_value = IF(CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d, CONCAT(%d, ':', LAST_INSERT_ID(1)), IF(CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) = 4, CONCAT(%d, ':', LAST_INSERT_ID(5)), CONCAT(SUBSTRING_INDEX(option_value, ':', 1), ':', LAST_INSERT_ID(LEAST(CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) + 1, 6))))) WHERE option_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
				$now,
				$expires,
				$expires,
				$key
			)
		);
		if ( false === $written ) {
			return false;
		}
		// The connection's LAST_INSERT_ID carries this request's assigned slot,
		// even if another worker has already incremented the stored row.
		$slot = $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Constant SQL.
		return null !== $slot && (int) $slot >= 1 && (int) $slot <= 5;
	}

	/** Retained for extensions calling the old public method. */
	public static function record_second_factor_failure( \WP_User $user ): void {
		self::reserve_second_factor_attempt( $user );
	}

	/** Read the uncached, network-wide attempt state. */
	private static function second_factor_attempt_state( \WP_User $user ): ?array {
		global $wpdb;
		$table = self::second_factor_options_table();
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table WHERE option_name = %s", self::second_factor_option_key( $user ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
		if ( ! \is_string( $value ) || ! \preg_match( '/^[0-9]+:[0-9]+$/', $value ) ) {
			return null;
		}
		list( $expires, $count ) = \explode( ':', $value, 2 );
		return array(
			'expires' => (int) $expires,
			'count'   => (int) $count,
		);
	}

	private static function second_factor_options_table(): string {
		global $wpdb;
		return \is_multisite() ? $wpdb->get_blog_prefix( \get_main_site_id() ) . 'options' : $wpdb->options;
	}

	private static function second_factor_option_key( \WP_User $user ): string {
		return WP_2FA_PREFIX . 'second_factor_attempts_' . $user->ID;
	}

	/**
	 * Clear the shared counter only after the full second factor succeeds.
	 *
	 * @param \WP_User $user - the WP User.
	 *
	 * @return void
	 *
	 * @since 4.2.0
	 */
	public static function clear_second_factor_failures( \WP_User $user ): void {
		\delete_user_meta( $user->ID, WP_2FA_PREFIX . 'second_factor_limit' );
		global $wpdb;
		$table = self::second_factor_options_table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE option_name = %s", self::second_factor_option_key( $user ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
	}

	/**
	 * Holds the number of allowed attempts to login for the user.
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
		self::$number_of_allowed_attempts = absint( $number );

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
		return sanitize_key( self::$logging_attempts_meta_key );
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
		self::$logging_attempts_meta_key = sanitize_key( $logging_attempts_meta_key );

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
		// Callers increment on a failure and then ask, so with three allowed the
		// third failure has to be the last one. This compared with <, which let
		// a fourth through.
		if ( self::get_login_attempts( $user ) >= self::get_allowed_login_attempts() ) {
			return false;
		}

		return true;
	}
}
