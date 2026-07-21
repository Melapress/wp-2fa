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
		 * Get the transient key for a user.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return string Transient key.
		 *
		 * @since 3.1.0
		 */
		public static function key_for( int $user_id ): string {
			return self::TRANSIENT_PREFIX . (string) $user_id;
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
			);
			$data     = is_array( $payload ) ? array_merge( $defaults, $payload ) : $defaults;

			return (bool) \set_site_transient( self::key_for( $user_id ), $data, $ttl );
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
			$value = \get_site_transient( self::key_for( $user_id ) );
			return is_array( $value ) ? $value : null;
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
		public static function clear_pending( int $user_id ): void {
			if ( $user_id > 0 ) {
				\delete_site_transient( self::key_for( $user_id ) );
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
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Not processing form data; only routing context check.
			$rest_route  = isset( $_GET['rest_route'] ) ? \sanitize_text_field( \wp_unslash( (string) $_GET['rest_route'] ) ) : '';
			$is_rest     = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
						|| ( $request_path && 0 === strpos( $request_path, '/wp-json/' ) )
						|| ( $rest_route && 0 === strpos( $rest_route, '/' ) );

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

					if ( false !== strpos( $actual_route, 'wp-2fa-methods/v1/login/validate' ) ) {
						return;
					}
					// Allow only passkey sign-in endpoints (not management routes like register/revoke/enable).
					if ( false !== strpos( $actual_route, 'wp-2fa-passkeys/v1/singin/' ) ) {
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
				// Clear the pending flag before redirecting to avoid loops.
				self::clear_pending( $user_id );
				\wp_safe_redirect( $redirect );
				exit;
			}

			// @free:start
			$skip_for_passkeys = 1;
			// @free:end


			// Clear the pending flag to prevent repeated triggers.
			self::clear_pending( $user_id );

			// If not skipping, call the plugin login handler to trigger the 2FA flow.
			if ( ! $skip_for_passkeys ) {
				$_REQUEST['redirect_to'] = $_SERVER['REQUEST_URI'] ?? '';
				$_REQUEST['redirect_to'] = \esc_url( $_REQUEST['redirect_to'] );
				Login::wp_login( $user->user_login, $user );
			}

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
		}
	}
}
