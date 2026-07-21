<?php
/**
 * Shared rate limiter for the public (unauthenticated) passkey sign-in endpoints.
 *
 * The passkey sign-in request/response endpoints are intentionally reachable by
 * logged-out users (they are part of the login flow itself), so they cannot be
 * gated behind a nonce or a capability check. This helper provides the throttling
 * and request-size bounds that those endpoints need instead, and is shared by both
 * the REST controller ({@see API_Signin}) and the legacy admin-ajax controller
 * ({@see Ajax_Passkeys}) so the two paths cannot drift apart again.
 *
 * @package    wp-2fa
 * @since      4.1.1
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Passkeys;

use WP2FA\Passkeys\Source_Repository;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Passkeys\Passkeys_Rate_Limiter' ) ) {

	/**
	 * Throttles and size-bounds the public passkey sign-in endpoints.
	 *
	 * @since 4.1.1
	 */
	class Passkeys_Rate_Limiter {

		/**
		 * Default maximum requests per IP + action within the window.
		 * Matches the limit historically applied on the admin-ajax path.
		 *
		 * @var int
		 */
		const DEFAULT_MAX = 30;

		/**
		 * Default rate-limit window, in seconds.
		 *
		 * @var int
		 */
		const DEFAULT_WINDOW = 300;

		/**
		 * Default maximum requests per account within the window.
		 *
		 * A per-account bucket bounds distributed (IP-rotating) abuse aimed at a
		 * single user. It is OFF by default (0) because throttling challenge
		 * issuance for a specific account is a mild denial-of-service vector
		 * against that user; operators who want it can enable it via the
		 * `wp_2fa_passkeys_rl_account_max` filter. The IP bucket is always active.
		 *
		 * @var int
		 */
		const DEFAULT_ACCOUNT_MAX = 0;

		/**
		 * Maximum accepted request body size, in bytes, before expensive parsing.
		 * WebAuthn assertions are a few KB at most; 64 KB is generous headroom.
		 *
		 * @var int
		 */
		const MAX_BODY_BYTES = 65536;

		/**
		 * Maximum accepted length of the supplied username / email identifier.
		 *
		 * @var int
		 */
		const MAX_USER_LEN = 254;

		/**
		 * Best-effort client IP for keying the limiter.
		 *
		 * Prefers REMOTE_ADDR (not spoofable at the application layer) and only
		 * falls back to the first X-Forwarded-For hop when REMOTE_ADDR is missing
		 * or invalid. Used only for throttling, never for an access decision.
		 *
		 * @return string Validated IP, or '' when none could be determined.
		 *
		 * @since 4.1.1
		 */
		public static function get_client_ip(): string {
			$ip = '';
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$candidate = \sanitize_text_field( \wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
				$ip        = filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
			}
			if ( '' === $ip && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$xff       = \sanitize_text_field( \wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				$parts     = explode( ',', $xff );
				$candidate = trim( $parts[0] );
				$ip        = filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
			}
			return (string) $ip;
		}

		/**
		 * Increment a fixed-window counter and report whether it now exceeds $max.
		 *
		 * @param string $token  Opaque bucket identifier (hashed into the key).
		 * @param int    $max    Maximum allowed hits within the window.
		 * @param int    $window Window length in seconds.
		 *
		 * @return bool True when the caller is over the limit.
		 *
		 * @since 4.1.1
		 */
		private static function bucket_exceeded( string $token, int $max, int $window ): bool {
			$key      = Source_Repository::PASSKEYS_META . 'rl_' . md5( $token );
			$attempts = (int) \get_transient( $key );
			++$attempts;
			\set_transient( $key, $attempts, $window );

			return $attempts > $max;
		}

		/**
		 * Enforce the per-IP + action bucket.
		 *
		 * Keyed by IP and action only (never by username), so different usernames
		 * from the same client cannot bypass the bucket by rotating identifiers.
		 *
		 * @param string $action Logical action, e.g. 'signin_request'.
		 *
		 * @return bool True when the request should be rejected.
		 *
		 * @since 4.1.1
		 */
		public static function ip_exceeded( string $action ): bool {
			$max    = (int) \apply_filters( 'wp_2fa_passkeys_rl_max', self::DEFAULT_MAX, $action );
			$window = (int) \apply_filters( 'wp_2fa_passkeys_rl_window', self::DEFAULT_WINDOW, $action );

			if ( $max <= 0 ) {
				return false;
			}

			$ip    = self::get_client_ip();
			$token = 'ip|' . $action . '|' . ( '' !== $ip ? $ip : 'unknown' );

			return self::bucket_exceeded( $token, $max, $window );
		}

		/**
		 * Enforce the optional per-account bucket (off by default; see const).
		 *
		 * @param string $action  Logical action.
		 * @param int    $user_id Resolved user ID (post-normalization).
		 *
		 * @return bool True when the request should be rejected.
		 *
		 * @since 4.1.1
		 */
		public static function account_exceeded( string $action, int $user_id ): bool {
			if ( $user_id <= 0 ) {
				return false;
			}

			$max    = (int) \apply_filters( 'wp_2fa_passkeys_rl_account_max', self::DEFAULT_ACCOUNT_MAX, $action );
			$window = (int) \apply_filters( 'wp_2fa_passkeys_rl_window', self::DEFAULT_WINDOW, $action );

			if ( $max <= 0 ) {
				return false;
			}

			return self::bucket_exceeded( 'acct|' . $action . '|' . $user_id, $max, $window );
		}

		/**
		 * REST guard: returns a 429 WP_Error when the IP bucket is exceeded, else null.
		 *
		 * @param string $action Logical action.
		 *
		 * @return \WP_Error|null
		 *
		 * @since 4.1.1
		 */
		public static function guard_rest( string $action ) {
			if ( self::ip_exceeded( $action ) ) {
				return new \WP_Error(
					'too_many_requests',
					\__( 'Too many requests. Please try again later.', 'wp-2fa' ),
					array( 'status' => 429 )
				);
			}

			return null;
		}

		/**
		 * REST per-account guard: returns a 429 WP_Error when exceeded, else null.
		 *
		 * @param string $action  Logical action.
		 * @param int    $user_id Resolved user ID.
		 *
		 * @return \WP_Error|null
		 *
		 * @since 4.1.1
		 */
		public static function guard_rest_account( string $action, int $user_id ) {
			if ( self::account_exceeded( $action, $user_id ) ) {
				return new \WP_Error(
					'too_many_requests',
					\__( 'Too many requests. Please try again later.', 'wp-2fa' ),
					array( 'status' => 429 )
				);
			}

			return null;
		}

		/**
		 * AJAX guard: emit a JSON 429 and stop when the IP bucket is exceeded.
		 *
		 * @param string $action Logical action.
		 *
		 * @return void
		 *
		 * @since 4.1.1
		 */
		public static function guard_ajax( string $action ): void {
			if ( self::ip_exceeded( $action ) ) {
				\wp_send_json_error( \__( 'Too many requests. Please try again later.', 'wp-2fa' ), 429 );
			}
		}

		/**
		 * Whether a REST request body exceeds the accepted maximum size.
		 *
		 * @param \WP_REST_Request $request The REST request.
		 *
		 * @return bool
		 *
		 * @since 4.1.1
		 */
		public static function body_too_large( \WP_REST_Request $request ): bool {
			$max = (int) \apply_filters( 'wp_2fa_passkeys_rl_max_body_bytes', self::MAX_BODY_BYTES );

			return strlen( (string) $request->get_body() ) > $max;
		}
	}
}
