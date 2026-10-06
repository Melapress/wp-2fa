<?php
/**
 * Guards the dispatch of one-time login codes.
 *
 * @package   wp2fa
 * @copyright 2026 Melapress
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://wordpress.org/plugins/wp-2fa/
 * @since     4.2.0
 */

declare(strict_types=1);

namespace WP2FA\Authenticator;

defined( 'ABSPATH' ) || exit;

use WP2FA\WP2FA;
use WP2FA\Utils\Debugging;
use WP2FA\Admin\Helpers\User_Helper;

/**
 * Code_Guard - decides when a fresh one-time code may be sent to a user.
 *
 * @since 4.2.0
 */
if ( ! class_exists( '\WP2FA\Authenticator\Code_Guard' ) ) {

	/**
	 * Single owner of the "has a code already been sent?" decision.
	 *
	 * Every channel that dispatches a one-time code — email, zero-setup email,
	 * Twilio, Clickatell — used to carry its own copy of this logic, and the
	 * copies had drifted apart: two of them raised the marker after sending,
	 * two tore it down again in the same request, and one did both under
	 * opposite conditions. The behaviour a site got therefore depended on which
	 * 2FA method the user happened to be using.
	 *
	 * The rules, in one place:
	 *
	 *   - A code is sent when none has been sent recently for this login attempt.
	 *   - Submitting a code that is then rejected rotates it, so an attacker
	 *     guessing codes is always shooting at a moving target. This is the
	 *     point of the "brute force protection" setting and is preserved.
	 *   - Merely re-rendering the challenge page does NOT rotate the code.
	 *     A duplicate render — a double-clicked login button, a resubmitted
	 *     POST, a second tab, a proxy retry — is not a guess, and treating it
	 *     as one is what caused users to receive the same login's code two to
	 *     five times over, each new one silently invalidating the last.
	 *   - With brute force protection switched off the code is sent once per
	 *     login attempt and never rotated, exactly as before.
	 *
	 * @since 4.2.0
	 */
	class Code_Guard {

		/**
		 * How long a freshly sent code suppresses another send, in seconds.
		 *
		 * Only has to outlast a burst of duplicate requests belonging to one
		 * login attempt, which arrive within seconds of each other. It is
		 * deliberately far shorter than the code's own lifetime, and the
		 * "Resend code" button bypasses it, so a user is never left waiting.
		 */
		private const DEFAULT_DEBOUNCE = 60;

		/**
		 * Values below this are not timestamps.
		 *
		 * Releases before 4.2.0 stored a boolean here. Such a marker reads as 1,
		 * which is treated as "sent long ago" — the safe direction, since the
		 * worst case is one extra code during the upgrade.
		 */
		private const EARLIEST_TIMESTAMP = 946684800;

		/**
		 * Whether the most recent dispatch in this request failed.
		 *
		 * @var bool
		 *
		 * @since 4.2.0
		 */
		private static $dispatch_failed = false;

		/**
		 * Name of the user meta holding the last dispatch time.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function meta_name(): string {
			return WP_2FA_PREFIX . 'code_sent';
		}

		/**
		 * Issue a short-lived SMS enrollment code, separate from login/email codes.
		 *
		 * @param \WP_User $user     Account being enrolled.
		 * @param string   $provider SMS provider.
		 * @param string   $phone    Destination being verified.
		 * @return string
		 */
		public static function issue_sms_setup_code( \WP_User $user, string $provider, string $phone ): string {
			$token = (string) random_int( 100000, 999999 );
			\update_user_meta(
				$user->ID,
				'wp_2fa_sms_setup_' . $provider,
				array(
					'phone'    => preg_replace( '/[\s().+-]+/', '', $phone ),
					'hash'     => \wp_hash( $token ),
					'expires'  => time() + 5 * MINUTE_IN_SECONDS,
					'attempts' => 0,
				)
			);
			return $token;
		}

		/**
		 * Remove a failed send without clearing a newer enrollment challenge.
		 *
		 * @param \WP_User $user     Account being enrolled.
		 * @param string   $provider SMS provider.
		 * @param string   $token    Code whose delivery failed.
		 */
		public static function clear_sms_setup_code( \WP_User $user, string $provider, string $token ): void {
			$key    = 'wp_2fa_sms_setup_' . $provider;
			$record = \get_user_meta( $user->ID, $key, true );
			if ( is_array( $record ) && hash_equals( (string) ( $record['hash'] ?? '' ), \wp_hash( $token ) ) ) {
				\delete_user_meta( $user->ID, $key, $record );
			}
		}

		/**
		 * Consume proof of this destination, with a durable five-attempt limit.
		 *
		 * @param \WP_User $user     Account being enrolled.
		 * @param string   $provider SMS provider.
		 * @param string   $phone    Submitted destination.
		 * @param string   $token    Submitted code.
		 * @return bool
		 */
		public static function consume_sms_setup_code( \WP_User $user, string $provider, string $phone, string $token ): bool {
			$key    = 'wp_2fa_sms_setup_' . $provider;
			$record = \get_user_meta( $user->ID, $key, true );
			if ( ! is_array( $record ) || empty( $record['expires'] ) || $record['expires'] <= time() || $record['attempts'] >= 5 ) {
				return false;
			}
			// Reserve the attempt atomically before comparing the code.
			$attempt = $record;
			++$attempt['attempts'];
			if ( ! \update_user_meta( $user->ID, $key, $attempt, $record ) ) {
				return false;
			}
			if ( $record['phone'] !== preg_replace( '/[\s().+-]+/', '', $phone ) || ! hash_equals( $record['hash'], \wp_hash( $token ) ) ) {
				return false;
			}
			// Only the request that consumes this exact record may enroll the phone.
			return (bool) \delete_user_meta( $user->ID, $key, $attempt );
		}

		/** Cleanup hook for the network-wide SMS setup quotas. */
		public const SMS_SETUP_CLEANUP_HOOK = 'wp_2fa_sms_setup_quota_cleanup';

		private const SMS_SETUP_QUOTA_PREFIX = 'wp_2fa_sms_setup_quota_';

		/**
		 * Reserve quota before generating a setup code or contacting a paid SMS service.
		 *
		 * Both providers share account, destination and network-wide limits. Failed
		 * sends retain their reservation: provider errors must not enable a retry flood.
		 *
		 * @param \WP_User $user  The account setting up SMS.
		 * @param string   $phone Destination phone number.
		 * @return bool Whether a provider request may be made.
		 */
		public static function reserve_setup_sms( \WP_User $user, string $phone ): bool {
			$number = preg_replace( '/[\s().-]+/', '', $phone );
			if ( $user->ID <= 0 || ! preg_match( '/^\+?[1-9][0-9]{6,14}$/D', $number ) ) {
				return false;
			}
			$destination = hash_hmac( 'sha256', ltrim( $number, '+' ), \wp_salt( 'auth' ) );
			if ( ! \wp_next_scheduled( self::SMS_SETUP_CLEANUP_HOOK ) ) {
				\wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::SMS_SETUP_CLEANUP_HOOK );
			}
			/** Filters the maximum attempted SMS setup sends per network per hour. */
			$site_limit = max( 1, (int) \apply_filters( WP_2FA_PREFIX . 'sms_setup_hourly_limit', 100 ) );
			return self::spend_sms_bucket( 'cooldown_user_' . $user->ID, 1, MINUTE_IN_SECONDS )
				&& self::spend_sms_bucket( 'cooldown_number_' . $destination, 1, MINUTE_IN_SECONDS )
				&& self::spend_sms_bucket( 'user_' . $user->ID, 5, 15 * MINUTE_IN_SECONDS )
				&& self::spend_sms_bucket( 'number_' . $destination, 5, 15 * MINUTE_IN_SECONDS )
				&& self::spend_sms_bucket( 'site', $site_limit, HOUR_IN_SECONDS );
		}

		/** The main site's unique option names serialize concurrent sends across a network. */
		private static function sms_options_table(): string {
			global $wpdb;
			return \is_multisite() ? $wpdb->get_blog_prefix( \get_main_site_id() ) . 'options' : $wpdb->options;
		}

		/** Atomically reserve one slot in a rolling quota window, failing closed on DB errors. */
		private static function spend_sms_bucket( string $scope, int $limit, int $window ): bool {
			global $wpdb;
			$table   = self::sms_options_table();
			$key     = self::SMS_SETUP_QUOTA_PREFIX . $scope;
			$now     = time();
			$expires = $now + $window;
			$written = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = IF(CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d, %s, CONCAT(SUBSTRING_INDEX(option_value, ':', 1), ':', LEAST(CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) + 1, %d)))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
					$key,
					$expires . ':1',
					$now,
					$expires . ':1',
					min( PHP_INT_MAX - 1, $limit ) + 1
				)
			);
			if ( false === $written ) {
				return false;
			}
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
			if ( ! is_string( $value ) || ! preg_match( '/^[0-9]+:[0-9]+$/D', $value ) ) {
				return false;
			}
			list( $until, $count ) = explode( ':', $value );
			return (int) $until > $now && (int) $count > 0 && (int) $count <= $limit;
		}

		/** Remove expired quota rows, including hashed destinations that are no longer used. */
		public static function cleanup_setup_sms_quotas(): void {
			global $wpdb;
			$table = self::sms_options_table();
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
					$wpdb->esc_like( self::SMS_SETUP_QUOTA_PREFIX ) . '%',
					time()
				)
			);
			$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM $table WHERE option_name LIKE %s LIMIT 1", $wpdb->esc_like( self::SMS_SETUP_QUOTA_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
			if ( null !== $remaining && ! \wp_next_scheduled( self::SMS_SETUP_CLEANUP_HOOK ) ) {
				\wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::SMS_SETUP_CLEANUP_HOOK );
			}
		}

		/**
		 * Whether a rejected code should be replaced with a new one.
		 *
		 * Mirrors the "Disable one-time code brute force protection" setting:
		 * unchecked (the default) means rotation is on.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_rotation_enabled(): bool {
			return empty( WP2FA::get_wp2fa_general_setting( 'brute_force_disable' ) );
		}

		/**
		 * Seconds for which a sent code suppresses another send.
		 *
		 * @return int
		 *
		 * @since 4.2.0
		 */
		public static function debounce_seconds(): int {
			/**
			 * Filters how long a freshly sent one-time code suppresses another send.
			 *
			 * @param int $seconds - Default debounce window.
			 *
			 * @since 4.2.0
			 */
			$seconds = (int) \apply_filters( WP_2FA_PREFIX . 'code_resend_debounce', self::DEFAULT_DEBOUNCE );

			return max( 0, $seconds );
		}

		/**
		 * Whether a fresh code should be dispatched to the user right now.
		 *
		 * @param int|\WP_User|null $user - The user the code would be sent to.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function should_send( $user = null ): bool {
			// An explicit resend has already dispatched a code in this request.
			if ( isset( $_REQUEST[ Login::INPUT_NAME_RESEND_CODE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return false;
			}

			$last_sent = self::last_sent_at( $user );

			if ( 0 === $last_sent ) {
				return true;
			}

			// Without rotation a login attempt gets exactly one code.
			if ( ! self::is_rotation_enabled() ) {
				return false;
			}

			return ( time() - $last_sent ) >= self::debounce_seconds();
		}

		/**
		 * Whether an explicit resend may dispatch a code now.
		 *
		 * Unlike should_send(), this holds whether or not codes rotate: it is
		 * only about how often the user's inbox can be written to.
		 *
		 * @param int|\WP_User|null $user - The user the code would be sent to.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function may_resend( $user = null ): bool {
			$last_sent = self::last_sent_at( $user );

			return 0 === $last_sent || ( time() - $last_sent ) >= self::debounce_seconds();
		}

		/**
		 * Record that a code has just been dispatched.
		 *
		 * @param int|\WP_User|null $user - The user the code was sent to.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function mark_sent( $user = null ) {
			User_Helper::set_meta( self::meta_name(), time(), $user );
		}

		/**
		 * Send a code through the given channel unless one was just sent.
		 *
		 * @param callable          $sender     - Dispatches the code; its return value is passed back.
		 * @param int|\WP_User|null $user       - The user the code is for.
		 * @param mixed             $if_skipped - Returned when no code is sent.
		 *
		 * @return mixed The sender's return value, or $if_skipped.
		 *
		 * @since 4.2.0
		 */
		public static function send_once( callable $sender, $user = null, $if_skipped = true ) {
			if ( ! self::should_send( $user ) ) {
				return $if_skipped;
			}

			return self::dispatch( $sender, $user );
		}

		/**
		 * Send a code now, and record it as sent only if it really went out.
		 *
		 * Every channel used to raise the "sent" marker whatever the sender
		 * returned. A failed email or SMS therefore looked like a delivered one:
		 * the challenge asked for a code that never arrived, and the marker then
		 * held back the next send - for the debounce window, or with rotation off
		 * for the rest of the login attempt.
		 *
		 * Success is a truthy result that is not a WP_Error. False, a WP_Error
		 * and an exception are failures: nothing is marked, the failure is
		 * logged without the code itself, and dispatch_failed() reports it for
		 * the rest of the request so the screen can say so.
		 *
		 * @param callable          $sender - Dispatches the code.
		 * @param int|\WP_User|null $user   - The user the code is for.
		 *
		 * @return mixed The sender's result on success; false or a WP_Error on failure.
		 *
		 * @since 4.2.0
		 */
		public static function dispatch( callable $sender, $user = null ) {
			try {
				$result = $sender();
			} catch ( \Throwable $e ) {
				$result = new \WP_Error( 'wp2fa_code_dispatch_exception', $e->getMessage() );
			}

			if ( self::succeeded( $result ) ) {
				self::$dispatch_failed = false;
				self::mark_sent( $user );

				return $result;
			}

			self::$dispatch_failed = true;

			// Not User_Helper::get_user_object(): that would move the helper's current user.
			$user_id = $user instanceof \WP_User ? (int) $user->ID : ( \is_numeric( $user ) ? (int) $user : 0 );
			Debugging::log(
				sprintf(
					'One-time code dispatch failed for user %d%s',
					$user_id,
					\is_wp_error( $result ) ? ': ' . $result->get_error_message() : '.'
				)
			);

			return \is_wp_error( $result ) ? $result : false;
		}

		/**
		 * Whether a sender's result means the code went out.
		 *
		 * @param mixed $result - What the sender returned.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function succeeded( $result ): bool {
			return ! \is_wp_error( $result ) && (bool) $result;
		}

		/**
		 * Whether the last dispatch in this request failed.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function dispatch_failed(): bool {
			return self::$dispatch_failed;
		}

		/**
		 * Retire the current code so the next render issues a new one.
		 *
		 * Call this when a submitted code has been rejected. Does nothing when
		 * brute force protection is switched off, because that setting exists
		 * precisely to keep one code alive for the whole attempt.
		 *
		 * @param int|\WP_User|null $user - The user whose code was rejected.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function rotate( $user = null ) {
			if ( ! self::is_rotation_enabled() ) {
				return;
			}

			self::clear( $user );
		}

		/**
		 * Forget that a code was sent, unconditionally.
		 *
		 * Used once the login attempt is over, so the next one starts clean.
		 *
		 * @param int|\WP_User|null $user - The user to clear.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function clear( $user = null ) {
			User_Helper::remove_meta( self::meta_name(), $user );
		}

		/**
		 * When the last code was dispatched, as a Unix timestamp.
		 *
		 * @param int|\WP_User|null $user - The user to read.
		 *
		 * @return int Zero when no usable marker is stored.
		 *
		 * @since 4.2.0
		 */
		private static function last_sent_at( $user = null ): int {
			$stored = (int) User_Helper::get_meta( self::meta_name(), $user );

			if ( $stored <= 0 ) {
				return 0;
			}

			// A pre-4.2.0 boolean marker: a code was sent, but we cannot say when.
			if ( $stored < self::EARLIEST_TIMESTAMP ) {
				return 1;
			}

			return $stored;
		}
	}
}
