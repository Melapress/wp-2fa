<?php
/**
 * Helper for managing pending 2FA state after primary auth.
 *
 * @package    wp-2fa
 * @since      3.1.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

declare(strict_types=1);

namespace WP2FA\Passkeys;

use WP2FA\WP2FA;
use WP2FA\Authenticator\Login;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WP2FA\\Passkeys\\Pending_2FA_Helper' ) ) {
	/**
	 * Pending 2FA state helper.
	 *
	 * Provides utilities to mark, query, clear, and optionally enforce
	 * a pending second factor step for a logged-in user.
	 *
	 * @since 3.1.0
	 */
	class Pending_2FA_Helper {
		/**
		 * Transient key prefix used to store pending 2FA state.
		 */
		public const TRANSIENT_PREFIX = 'wp_2fa_signin_pending_';

		/**
		 * Fingerprint of the session token issued during this request, if any.
		 *
		 * wp_get_session_token() reads the request's own cookie, so it is empty in
		 * the request that *sets* one — which is exactly the request that marks a
		 * user pending. The token is therefore taken from the set_logged_in_cookie
		 * action instead, which fires from inside wp_set_auth_cookie().
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		private static $issued_fingerprint = '';

		/**
		 * User ID for which the captured token was issued.
		 *
		 * @var int
		 */
		private static $issued_user_id = 0;

		/**
		 * Remember the session token WordPress has just issued.
		 *
		 * @param string $cookie     - The logged-in cookie value.
		 * @param int    $expire     - Unused.
		 * @param int    $expiration - Unused.
		 * @param int    $user_id    - Unused.
		 * @param string $scheme     - Unused.
		 * @param string $token      - Session token; only passed by WordPress 6.2 and later.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function capture_session_token( $cookie, $expire = 0, $expiration = 0, $user_id = 0, $scheme = '', $token = '' ): void {
			if ( '' === (string) $token && \is_string( $cookie ) && '' !== $cookie ) {
				// WordPress before 6.2 does not pass the token, but the cookie holds it.
				$parsed = \wp_parse_auth_cookie( $cookie, 'logged_in' );
				$token  = ( \is_array( $parsed ) && isset( $parsed['token'] ) ) ? $parsed['token'] : '';
			}

			self::$issued_user_id     = (int) $user_id;
			self::$issued_fingerprint = self::fingerprint( (string) $token );
		}

		/**
		 * Reduce a session token to the value stored alongside a pending record.
		 *
		 * Hashed rather than stored verbatim: the record lives in an option row, and
		 * a session token is a credential in its own right.
		 *
		 * @param string $token - Session token, possibly empty.
		 *
		 * @return string Empty when there is no token to fingerprint.
		 *
		 * @since 4.2.0
		 */
		private static function fingerprint( string $token ): string {
			return ( '' === $token ) ? '' : \wp_hash( $token );
		}

		/**
		 * Fingerprint of the session making the current request.
		 *
		 * Prefers the token issued during this request, since a request that has
		 * just signed the user in has no cookie of its own to read yet.
		 *
		 * @param int $user_id User whose issued token may be used.
		 *
		 * @return string Empty when the session cannot be identified.
		 *
		 * @since 4.2.0
		 */
		private static function current_fingerprint( int $user_id ): string {
			if ( $user_id === self::$issued_user_id && '' !== self::$issued_fingerprint ) {
				return self::$issued_fingerprint;
			}

			return self::fingerprint( (string) \wp_get_session_token() );
		}

		/**
		 * Whether a stored record belongs to the session making this request.
		 *
		 * A record with no fingerprint is recognized here for upgrade compatibility,
		 * but enforce_on_request() treats it as unsafe and invalidates the account's
		 * sessions. It must never silently become an authenticated session.
		 *
		 * @param array $payload - Stored pending record.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function belongs_to_this_session( array $payload ): bool {
			$stored = isset( $payload['token'] ) ? (string) $payload['token'] : '';

			if ( '' === $stored ) {
				return true;
			}

			$payload_user_id = isset( $payload['uid'] ) ? (int) $payload['uid'] : 0;
			if ( $payload_user_id <= 0 ) {
				return false;
			}

			$current = self::current_fingerprint( $payload_user_id );

			return '' !== $current && \hash_equals( $stored, $current );
		}

		/** A passkey on its own completes authentication. */
		public const OUTCOME_ADMIT = 'admit';

		/** A second factor is owed, and the user has one to answer with. */
		public const OUTCOME_CHALLENGE = 'challenge';

		/** A second factor is owed and the user has none, so there is nothing to admit them on. */
		public const OUTCOME_REFUSE = 'refuse';

		/**
		 * Whether a passkey assertion on its own completes authentication.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function passkey_alone_is_sufficient(): bool {
			// @free:start
			return true;
			// @free:end

		}

		/**
		 * What a successful passkey assertion should lead to for this user.
		 *
		 * The three answers exist because "passkey verified" and "authentication
		 * complete" are not the same thing once an administrator has asked for a
		 * second factor as well. Deciding it here keeps the REST and admin-ajax
		 * sign-in endpoints from drifting apart, which they already had over the
		 * order of two lines.
		 *
		 * @param \WP_User $user - The user whose passkey was just verified.
		 *
		 * @return string One of the OUTCOME_* constants.
		 *
		 * @since 4.2.0
		 */
		public static function outcome_for( \WP_User $user ): string {
			if ( self::passkey_alone_is_sufficient() ) {
				return self::OUTCOME_ADMIT;
			}

			// A passkey does not register itself as the user's 2FA method, so this
			// answers "has a second factor as well", not "has a passkey".
			$provider = User_Helper::get_enabled_method_for_user( $user );
			if (
				is_string( $provider )
				&& '' !== $provider
				&& Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user ), $provider )
			) {
				return self::OUTCOME_CHALLENGE;
			}

			return self::OUTCOME_REFUSE;
		}

		/**
		 * Apply the normal account checks before a passkey can issue a session.
		 *
		 * @param \WP_User $user - The user to check.
		 *
		 * @return bool True if the user can complete sign-in, false otherwise.
		 *
		 * @since 4.2.0
		 */
		public static function can_complete_signin( \WP_User $user ): bool {
			if ( User_Helper::is_user_locked( $user ) && ! User_Helper::is_excluded( $user ) ) {
				return false;
			}

			$checked = \apply_filters( 'wp_authenticate_user', $user, '' );
			return $checked instanceof \WP_User && $checked->ID === $user->ID;
		}

		/**
		 * Get the transient key for a user.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return string Transient key.
		 *
		 * @since 3.1.0
		 */
		public static function key_for( int $user_id, string $fingerprint = '' ): string {
			$key = self::TRANSIENT_PREFIX . (string) $user_id;

			return ( '' === $fingerprint ) ? $key : $key . '_' . substr( $fingerprint, 0, 32 );
		}

		/**
		 * The record key for the session making this request.
		 *
		 * One record per user is not enough. The obligation belongs to a session, and two
		 * sessions of the same account can owe one each: a user who starts a second passkey
		 * sign-in elsewhere before answering the first challenge would otherwise have the
		 * first record overwritten, leaving that session holding an auth cookie with nothing
		 * left to answer — the very bypass the binding exists to prevent.
		 *
		 * Falls back to the unscoped key when the session cannot be identified, so such a
		 * record is still written somewhere enforce_on_request() will find it and fail closed.
		 *
		 * @param int $user_id - User the record belongs to.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		private static function session_key_for( int $user_id ): string {
			return self::key_for( $user_id, self::current_fingerprint( $user_id ) );
		}

		/**
		 * Mark a user as pending 2FA.
		 *
		 * @param int        $user_id User ID.
		 * @param array|null $payload Optional payload to persist (merged with defaults).
		 * @param int|null   $ttl     Optional TTL (seconds). Defaults to filtered value.
		 *
		 * @return bool True on success.
		 *
		 * @since 3.1.0
		 */
		public static function mark_pending( int $user_id, ?array $payload = null, ?int $ttl = null ): bool {
			if ( $user_id <= 0 ) {
				return false;
			}

			$ttl = (int) ( $ttl ?? \apply_filters( 'wp_2fa_signin_pending_ttl', 600, $user_id ) ); // default 10 minutes.
			$ttl = max( 60, $ttl );

			$client_ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( \sanitize_text_field( \wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';

			$defaults = array(
				'uid'    => $user_id,
				'iat'    => time(),
				'source' => 'unknown',
				'ip'     => $client_ip,
				'ua'     => $user_agent,
				// Binds the record to the session that started the ceremony, so a
				// second session signed in to the same account cannot answer, or
				// quietly consume, a challenge that was not meant for it.
				'token'  => self::current_fingerprint( $user_id ),
			);
			$data = is_array( $payload ) ? array_merge( $defaults, $payload ) : $defaults;

			// Callers may add context, but must not replace the identity or binding
			// fields that make this an authentication control.
			$data['uid']   = $user_id;
			$data['iat']   = $defaults['iat'];
			$data['ip']    = $defaults['ip'];
			$data['ua']    = $defaults['ua'];
			$data['token'] = self::current_fingerprint( $user_id );

			return (bool) \set_site_transient( self::session_key_for( $user_id ), $data, $ttl );
		}

		/**
		 * Get pending payload for a user.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return array|null Pending payload array or null if none.
		 *
		 * @since 3.1.0
		 */
		public static function get_pending( int $user_id ): ?array {
			if ( $user_id <= 0 ) {
				return null;
			}
			$value = \get_site_transient( self::session_key_for( $user_id ) );

			if ( ! is_array( $value ) ) {
				/*
				 * Records written before the key was scoped to a session, and records written
				 * when no session could be identified, live under the unscoped key. They are
				 * still read so enforce_on_request() can see them and fail closed.
				 */
				$value = \get_site_transient( self::key_for( $user_id ) );
			}

			if ( ! is_array( $value ) ) {
				return null;
			}

			// Another session for the same account must not see this record.
			if ( ! self::belongs_to_this_session( $value ) ) {
				return null;
			}

			return $value;
		}

		/**
		 * Check if a user has pending 2FA.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return bool True if pending 2FA exists.
		 *
		 * @since 3.1.0
		 */
		public static function has_pending( int $user_id ): bool {
			return null !== self::get_pending( $user_id );
		}

		/**
		 * Clear pending 2FA for a user.
		 *
		 * @param int $user_id User ID.
		 *
		 * @since 3.1.0
		 */
		public static function clear_pending( int $user_id, bool $force = false ): void {
			if ( $user_id <= 0 ) {
				return;
			}

			$session_key = self::session_key_for( $user_id );
			$legacy_key  = self::key_for( $user_id );

			/*
			 * A forced clear accompanies revoking the account's sessions, so the unscoped
			 * record goes too. Other sessions' records are left to expire: their keys are not
			 * derivable from here, and with their sessions destroyed they cannot be used.
			 */
			if ( $force ) {
				\delete_site_transient( $session_key );
				\delete_site_transient( $legacy_key );

				return;
			}

			$value       = \get_site_transient( $session_key );
			$from_legacy = false;

			if ( ! is_array( $value ) ) {
				$value       = \get_site_transient( $legacy_key );
				$from_legacy = is_array( $value );
			}

			// Only the session that is actually being challenged may retire the
			// record; otherwise a second session could clear the challenge and
			// leave the first one signed in without ever answering it.
			if ( is_array( $value ) && ! self::belongs_to_this_session( $value ) ) {
				return;
			}

			\delete_site_transient( $session_key );

			/*
			 * The unscoped record is only removed when it is the one that was just answered.
			 * Removing it otherwise would retire an obligation belonging to whichever session
			 * wrote it, which is the same fail-open this binding exists to prevent.
			 */
			if ( $from_legacy ) {
				\delete_site_transient( $legacy_key );
			}
		}

		/**
		 * Enforce pending 2FA on normal page requests.
		 *
		 * - Blocks AJAX and REST requests (except 2FA challenge endpoints) when additional 2FA is required.
		 * - Skips if a filter reports current request is the challenge view.
		 * - Fires an action for custom enforcement or optionally redirects to a URL provided by filter.
		 *
		 * Filters:
		 * - `wp_2fa_is_challenge_request( bool $default )` -> bool
		 * - `wp_2fa_pending_redirect_url( string $default, int $user_id, array $payload )` -> string URL or empty for no redirect.
		 *
		 * @since 3.1.0
		 */
		public static function enforce_on_request(): void {
			// Skip during Cron (not relevant to user interaction).
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				return;
			}

			$user_id = (int) \get_current_user_id();
			if ( $user_id <= 0 ) {
				return;
			}

			$payload = self::get_pending( $user_id );
			if ( null === $payload ) {
				return;
			}

			// Determine if this is an AJAX or REST request.
			$is_ajax     = function_exists( 'wp_doing_ajax' ) && \wp_doing_ajax();
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
			// Strip query string from URI to prevent route-string injection via query parameters.
			$request_path = (string) strtok( $request_uri, '?' );
			/*
			 * This runs on wp_loaded, before a REST request is dispatched and marked,
			 * so the route has to be read from the request. That only means REST on
			 * the front controller, which is where WordPress serves it. On an admin
			 * screen, wp-login.php or anything else it is a query argument and no
			 * more - and naming an allowed route in it, /wp-admin/?rest_route=/wp-2fa-
			 * methods/v1/login/validate, used to let an ordinary page through.
			 */
			$script           = isset( $_SERVER['SCRIPT_NAME'] ) ? \basename( \sanitize_text_field( \wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) ) ) : '';
			$front_controller = ! \is_admin() && 'index.php' === $script;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Not processing form data; only routing context check.
			$rest_route  = $front_controller && isset( $_GET['rest_route'] ) ? \sanitize_text_field( \wp_unslash( (string) $_GET['rest_route'] ) ) : '';
			$is_rest     = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
						|| ( $front_controller && $request_path && 0 === strpos( $request_path, '/wp-json/' ) )
						|| ( $rest_route && 0 === strpos( $rest_route, '/' ) );

			/*
			 * Records created before session binding was introduced cannot be safely
			 * attributed to one of the user's sessions. Fail closed on that short
			 * upgrade window: revoke every session for the account and require a fresh
			 * login instead of allowing another session to consume the challenge.
			 */
			if ( empty( $payload['token'] ) ) {
				self::clear_pending( $user_id, true );
				\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
				\wp_clear_auth_cookie();

				if ( $is_ajax || $is_rest ) {
					\wp_send_json_error(
						array( 'message' => \__( 'Your sign-in session could not be verified. Please sign in again.', 'wp-2fa' ) ),
						403
					);
				}

				\wp_safe_redirect( \wp_login_url() );
				exit;
			}

			if ( $is_ajax || $is_rest ) {
				// @free:start
				$skip_for_passkeys = 1;
				// @free:end


				// If passkey alone is sufficient (skip_2fa is true), allow through — pending will be
				// cleared on the next normal page load.
				if ( $skip_for_passkeys ) {
					return;
				}

				// Allow the 2FA validation REST endpoint (authenticates via login_nonce, not session).
				if ( $is_rest ) {
					// Derive the actual REST route from a single authoritative source to prevent
					// injection via query parameters on pretty-permalink URLs.
					$actual_route = '';
					if ( $rest_route ) {
						// Plain permalinks: route comes from ?rest_route= (this IS the route).
						$actual_route = $rest_route;
					} elseif ( 0 === strpos( $request_path, '/wp-json/' ) ) {
						// Pretty permalinks: extract route from the path after the REST prefix.
						$actual_route = substr( $request_path, strlen( '/wp-json' ) );
					}

					$actual_route = '/' . ltrim( $actual_route, '/' );
					$actual_route = untrailingslashit( $actual_route );

					if ( '/wp-2fa-methods/v1/login/validate' === $actual_route ) {
						return;
					}
					// Allow only passkey sign-in endpoints (not management routes like register/revoke/enable).
					if ( in_array( $actual_route, array( '/wp-2fa-passkeys/v1/singin/request', '/wp-2fa-passkeys/v1/singin/response' ), true ) ) {
						return;
					}
				}

				// Allow AJAX actions that the 2FA challenge UI itself uses.
				if ( $is_ajax ) {
					$action = isset( $_REQUEST['action'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$allowed_actions = array( 'custom_ajax_logout', 'wp2fa_signin_request', 'wp2fa_signin_response' );
					if ( in_array( $action, $allowed_actions, true ) ) {
						return;
					}
				}

				// Allow challenge-related requests indicated by filter.
				$is_challenge = (bool) \apply_filters( 'wp_2fa_is_challenge_request', false );
				if ( $is_challenge ) {
					return;
				}

				// Block all other AJAX/REST while additional 2FA is pending.
				\wp_send_json_error(
					array( 'message' => \__( 'Two-factor authentication is required to complete sign-in.', 'wp-2fa' ) ),
					403
				);
			}

			$is_challenge = (bool) \apply_filters( 'wp_2fa_is_challenge_request', false );
			if ( $is_challenge ) {
				return;
			}

			$user = \get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return;
			}

			// Allow implementers to handle enforcement (e.g., show challenge UI).
			// Prefer redirect if a URL is provided; otherwise simulate login event and fire a custom action.
			$redirect = (string) \apply_filters( 'wp_2fa_pending_redirect_url', '', $user_id, $payload );
			if ( ! empty( $redirect ) ) {
				/*
				 * Keep the record until the destination has actually completed its
				 * challenge. Clearing it here creates an authenticated interval in
				 * which no enforcement state exists if the redirect is interrupted,
				 * rejected, or handled by the wrong session.
				 */
				\wp_safe_redirect( $redirect );
				exit;
			}

			// @free:start
			$skip_for_passkeys = 1;
			// @free:end


			// If not skipping, call the plugin login handler to trigger the 2FA flow.
			if ( ! $skip_for_passkeys ) {
				$_REQUEST['redirect_to'] = $_SERVER['REQUEST_URI'] ?? '';
				$_REQUEST['redirect_to'] = \esc_url( $_REQUEST['redirect_to'] );
				Login::wp_login( $user->user_login, $user );

				/*
				 * A defensive backstop: wp_login() normally terminates after removing
				 * the first-factor session. If an explicit integration exemption lets
				 * it return, retaining the marker is safer than silently authenticating
				 * a request whose second factor was never completed.
				 */
				return;
			}

			// This setting explicitly makes the verified passkey the final factor.
			self::clear_pending( $user_id );

			// Also fire a custom action for themes/plugins that prefer not to rely on wp_login here.
			\do_action( 'wp_2fa_enforce_pending', $user_id, $payload );
			// Do not call core login hooks here; they are meant for actual login events
			// and can interfere with REST and other flows when fired out of context.
		}

		/**
		 * Add necessary hooks.
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		public static function add_hooks(): void {
			\add_action( 'wp_loaded', array( self::class, 'enforce_on_request' ), 9 );
			\add_action( 'set_logged_in_cookie', array( self::class, 'capture_session_token' ), 10, 6 );
		}
	}
}
