<?php
/**
 * Responsible for the signin API endpoints
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Passkeys;

use WP2FA\Methods\Passkeys;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Methods\Passkeys\Web_Authn;
use WP2FA\Passkeys\Source_Repository;
use WP2FA\Passkeys\Pending_2FA_Helper;
use WP2FA\Authenticator\Login;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Endpoints registering
 */
if ( ! class_exists( '\WP2FA\Passkeys\API_Signin' ) ) {

	/**
	 * Register API controller
	 *
	 * @since 3.0.0
	 */
	class API_Signin {

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @param \WP_REST_Request $request The request object.
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @since 3.0.0
		 * @since 3.1.0 - Added request parameter.
		 */
		public static function signin_request_action( \WP_REST_Request $request ) {
			// Public endpoint: throttle by IP and bound the body size before any parsing.
			if ( ! class_exists( '\WP2FA\Passkeys\Passkeys_Rate_Limiter', false ) ) {
				require_once __DIR__ . '/class-passkeys-rate-limiter.php';
			}
			$rate_limited = Passkeys_Rate_Limiter::guard_rest( 'signin_request' );
			if ( null !== $rate_limited ) {
				return \rest_ensure_response( $rate_limited );
			}
			if ( Passkeys_Rate_Limiter::body_too_large( $request ) ) {
				return \rest_ensure_response( new \WP_Error( 'request_too_large', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 413 ) ) );
			}

			$data = $request->get_json_params();

			$user = null;

			/*
			 * The same lookup as the AJAX transport: the login exactly as entered,
			 * then the email. This took anything containing @ for an email, and
			 * WordPress allows @ in a username - qa@local, with a different email,
			 * could sign in with a passkey over AJAX and never over REST. The
			 * strict sanitising of other logins turned some legacy usernames into
			 * names nobody has, too.
			 */
			if ( ! empty( $data['user'] ) ) {
				$user = Ajax_Passkeys::resolve_signin_user( $data['user'], false );
			}

			if ( ! $user ) {
				// Avoid user enumeration by returning a generic error.
				return \rest_ensure_response( new \WP_Error( 'invalid_credentials', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) ) );
			}

			// Optional secondary per-account bucket (off by default) — bounds IP-rotating abuse of one account.
			$account_limited = Passkeys_Rate_Limiter::guard_rest_account( 'signin_request', (int) $user->ID );
			if ( null !== $account_limited ) {
				return \rest_ensure_response( $account_limited );
			}

			// leave if the user is not required to have 2FA enabled due to and exclusion rule.
			// if ( User_Helper::is_excluded( $user->ID ) ) {
			// Avoid user enumeration by returning a generic error.
			// return \rest_ensure_response( new \WP_Error( 'invalid_credentials', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) ) );
			// }

			$public_key_credentials = Source_Repository::find_all_for_user( $user );
			if ( ! empty( $public_key_credentials ) ) {
				$allow_credentials = array();

				foreach ( $public_key_credentials as $public_key_credential ) {
					$allow_credentials[] = array(
						'type' => 'public-key',
						'id'   => $public_key_credential['credential_id'],
					);
				}
			} else {
				// Avoid disclosing whether a user exists or has passkeys.
				return \rest_ensure_response( new \WP_Error( 'invalid_credentials', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) ) );
			}

			$request_id = \wp_generate_uuid4();

			// Generate a base64url-encoded challenge (no padding) to match WebAuthn expectations.
			$challenge = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for WebAuthn base64url challenge

			$options = array(
				'challenge'        => $challenge,
				'rpId'             => Web_Authn::get_relying_party_id(),
				'allowCredentials' => $allow_credentials,
				'userVerification' => 'preferred',
				'timeout'          => 5 * 60 * 1000,
				// No 'uid': the client never needs it, and returning it only when the
				// account has a passkey told anyone asking which accounts exist.
				// The user stays bound to the challenge through the stored copy below.
			);

			// Store the challenge in transient for 60 seconds, bound to the (optional) user id.
			// For some hosting transient set to persistent object cache like Redis/Memcache. By default it stored in options table.
			$challenge_payload = array(
				'challenge' => $challenge,
				'uid'       => $user ? (string) $user->ID : null,
				'iat'       => time(),
			);
			\set_transient( Source_Repository::PASSKEYS_META . $request_id, $challenge_payload, 60 );

			$response = array(
				'options'    => $options,
				'request_id' => $request_id,
			);

			return \rest_ensure_response( $response );
		}

		/**
		 * Whether this sign-in asked to be remembered.
		 *
		 * Put through the same filter every other sign-in path uses, so a site overriding
		 * the choice gets the same answer whichever way its people sign in.
		 *
		 * @param \WP_REST_Request $request - The sign-in request.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function remember_requested( \WP_REST_Request $request ): bool {
			return (bool) \apply_filters(
				WP_2FA_PREFIX . 'rememberme',
				\rest_sanitize_boolean( $request->get_param( 'rememberme' ) )
			);
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @param \WP_REST_Request $request The request object.
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @since 3.0.0
		 */
		public static function signin_response_action( \WP_REST_Request $request ) {
			// Public endpoint: throttle by IP and bound the body size before any parsing/crypto.
			if ( ! class_exists( '\WP2FA\Passkeys\Passkeys_Rate_Limiter', false ) ) {
				require_once __DIR__ . '/class-passkeys-rate-limiter.php';
			}
			$rate_limited = Passkeys_Rate_Limiter::guard_rest( 'signin_response' );
			if ( null !== $rate_limited ) {
				return \rest_ensure_response( $rate_limited );
			}
			if ( Passkeys_Rate_Limiter::body_too_large( $request ) ) {
				return \rest_ensure_response( new \WP_Error( 'request_too_large', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 413 ) ) );
			}

			$data = $request->get_json_params();

			if ( ! $data ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$user = null;

			// The same lookup as the request step, and as the AJAX transport.
			if ( ! empty( $data['user'] ) ) {
				$user = Ajax_Passkeys::resolve_signin_user( $data['user'], false );

				if ( ! $user ) {
					// Avoid user enumeration by returning a generic error.
					return \rest_ensure_response( new \WP_Error( 'invalid_credentials', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) ) );
				}
			}

			$uid = $user ? (string) $user->ID : '';

			// Optional secondary per-account bucket (off by default).
			$account_limited = Passkeys_Rate_Limiter::guard_rest_account( 'signin_response', (int) $uid );
			if ( null !== $account_limited ) {
				return \rest_ensure_response( $account_limited );
			}

			$request_id = isset( $data['request_id'] ) ? (string) $data['request_id'] : '';
			if ( '' === $request_id ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			// Get challenge from cache.
			$stored = \get_transient( Source_Repository::PASSKEYS_META . $request_id );

			// If not found or invalid, return a generic error.
			if ( ! is_array( $stored ) || empty( $stored['challenge'] ) ) {
				return new \WP_Error( 'invalid_challenge', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			// If a uid was present at request time and differs now, fail generically.
			if ( $uid && isset( $stored['uid'] ) && (string) $stored['uid'] !== (string) $uid ) {
				return new \WP_Error( 'invalid_challenge', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$challenge = (string) $stored['challenge'];

			$asse_rep = $data['asseResp'] ?? array();
			// rawId a string, as the AJAX transport requires: a JSON array cast to the
			// string "Array", passed the format check below, and reached a decoder
			// that only takes strings - a TypeError, after the challenge was spent.
			if ( ! is_array( $asse_rep ) || empty( $asse_rep['rawId'] ) || ! is_string( $asse_rep['rawId'] ) || empty( $asse_rep['response'] ) || ! is_array( $asse_rep['response'] ) ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}
			foreach ( array( 'clientDataJSON', 'authenticatorData', 'signature' ) as $required_key ) {
				if ( empty( $asse_rep['response'][ $required_key ] ) || ! is_string( $asse_rep['response'][ $required_key ] ) ) {
					return new \WP_Error( 'invalid_request', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
				}
			}

			// Validate base64url encoding for WebAuthn fields to avoid malformed input reaching decoder.
			$b64url_re       = '/^[A-Za-z0-9\\-_]+=*$/';
			$raw_id_b64      = $asse_rep['rawId'];
			$client_data_b64 = (string) ( $asse_rep['response']['clientDataJSON'] ?? '' );
			$auth_data_b64   = (string) ( $asse_rep['response']['authenticatorData'] ?? '' );
			$signature_b64   = (string) ( $asse_rep['response']['signature'] ?? '' );

			if ( ! preg_match( $b64url_re, $raw_id_b64 ) || ! preg_match( $b64url_re, $client_data_b64 ) || ! preg_match( $b64url_re, $auth_data_b64 ) || ! preg_match( $b64url_re, $signature_b64 ) ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			// Decoded before the challenge is claimed: input that cannot even be read
			// is refused without spending the user's challenge.
			try {
				$credential_id = Web_Authn::get_raw_credential_id( $raw_id_b64 );
			} catch ( \Throwable $e ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			// Atomically claim the challenge. A replay racing this request must lose.
			if ( ! \delete_transient( Source_Repository::PASSKEYS_META . $request_id ) ) {
				return new \WP_Error( 'invalid_challenge', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$webauthn = new Web_Authn(
				Web_Authn::get_relying_party_id(),
				Web_Authn::get_relying_party_id()
			);

			if ( ! class_exists( 'ParagonIE_Sodium_Core_Base64_UrlSafe', false ) ) {
				require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Base64/UrlSafe.php';
				require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Util.php';
			}

			$meta_key = Source_Repository::PASSKEYS_META . \ParagonIE_Sodium_Core_Base64_UrlSafe::encodeUnpadded( $credential_id );

			try {
				$user_data = \json_decode( (string) \get_user_meta( $uid, $meta_key, true ), true, 512, JSON_THROW_ON_ERROR );
			} catch ( \JsonException $exception ) {
				$user_data = null;
			}

			if ( null === $user_data || empty( $user_data ) ) {
				// Do not disclose whether a passkey exists.
				return \rest_ensure_response(
					array(
						'status'  => 'unverified',
						'message' => __( 'Invalid credentials.', 'wp-2fa' ),
					)
				);
			}

			try {
				$previous_signature_counter = isset( $user_data['extra']['signature_counter'] ) ? (int) $user_data['extra']['signature_counter'] : null;
				$verification_result         = $webauthn->process_get(
					Web_Authn::base64url_decode( $asse_rep['response']['clientDataJSON'] ),
					Web_Authn::base64url_decode( $asse_rep['response']['authenticatorData'] ),
					Web_Authn::base64url_decode( $asse_rep['response']['signature'] ),
					$user_data['extra']['public_key'],
					Web_Authn::base64url_decode( $challenge ),
					$previous_signature_counter,
					true
				);
			} catch ( \Throwable $e ) {
				// Log details server-side only when WP_DEBUG is enabled, return generic error to client.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging verification details server-side for diagnostics.
					\error_log( 'Passkey verification failed: ' . $e->getMessage() );
				}
				return new \WP_Error( 'verification_failed', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			try {
				if ( Passkeys::is_enabled( User_Helper::get_user_role( (int) $uid ) ) ) {
					if ( empty( $user_data['extra']['enabled'] ) ) {
						return \rest_ensure_response(
							array(
								'status'  => 'unverified',
								'message' => __( 'Invalid credentials.', 'wp-2fa' ),
							)
						);
					}
					if ( ! Pending_2FA_Helper::can_complete_signin( $user ) ) {
						return new \WP_Error( 'invalid_credentials', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 403 ) );
					}

					/*
					 * A verified passkey is not always a completed sign-in.
					 *
					 * Where an administrator has asked for a second factor as well,
					 * no session is issued here at all: the caller is handed a login
					 * nonce and sent to the 2FA challenge, and WordPress only learns
					 * about the user once that is answered. Issuing the cookie first
					 * and enforcing afterwards, as this used to, meant a usable
					 * session existed for a factor that had not been given.
					 */
					$outcome = Pending_2FA_Helper::outcome_for( $user );

					$new_signature_counter = $webauthn->get_signature_counter();
					if ( null !== $new_signature_counter ) {
						$user_data['extra']['signature_counter'] = $new_signature_counter;
					}

					// Persist every verified assertion, even when this login outcome is refused.
					$user_data['extra']['last_used'] = time();
					$public_key_json                 = addcslashes( \wp_json_encode( $user_data, JSON_UNESCAPED_SLASHES ), '\\' );
					\update_user_meta( $uid, $meta_key, $public_key_json );

					if ( Pending_2FA_Helper::OUTCOME_REFUSE === $outcome ) {
						return \rest_ensure_response(
							array(
								'status'  => 'second_factor_required',
								'message' => __( 'This account needs a second authentication factor as well as a passkey. Sign in with your password to set one up.', 'wp-2fa' ),
							)
						);
					}

					if ( Pending_2FA_Helper::OUTCOME_ADMIT === $outcome ) {
						// Passkey alone completes the sign-in.
						\wp_set_current_user( $uid, User_Helper::get_user( $uid )->user_login );

						/*
						 * Honour the user's choice rather than always granting the longer
						 * session. This used to pass true unconditionally, so a passkey
						 * sign-in ran for a fortnight even where the person had deliberately
						 * left "Remember Me" alone on a shared machine — failing quietly, and
						 * in the direction that costs them rather than inconveniences them.
						 */
						\wp_set_auth_cookie( $uid, self::remember_requested( $request ), is_ssl() );

						// After the cookie, not before: the record is bound to the
						// session token WordPress issues here, and that token does not
						// exist until wp_set_auth_cookie() has run.
						Pending_2FA_Helper::mark_pending( (int) $uid, array( 'source' => 'passkey' ) );
						\do_action( WP_2FA_PREFIX . 'passkey_login', $user );
					}

				} else {
					return \rest_ensure_response(
						array(
							'status'  => 'unverified',
							'message' => __( 'Invalid credentials.', 'wp-2fa' ),
						)
					);
				}
			} catch ( \Throwable $error ) {
				// Log detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging validation details server-side for diagnostics.
					\error_log( 'Public key validation failed: ' . $error->getMessage() );
				}
				return new \WP_Error( 'public_key_validation_failed', __( 'Invalid credentials.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$redirect_to = isset( $data['redirect_to'] ) && is_string( $data['redirect_to'] ) ? $data['redirect_to'] : '';

			/**
			 * Filters the login redirect URL.
			 *
			 * @since 3.0.0
			 *
			 * @param string           $redirect_to           The redirect destination URL.
			 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
			 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
			 */
			$redirect_to = apply_filters( 'login_redirect', $redirect_to, '', $user );

			// Validate the redirect URL to prevent open redirects and ensure same-origin.
			$redirect_to = \wp_validate_redirect( $redirect_to, \admin_url() );

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || \admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( \is_multisite() && ! \get_active_blog_for_user( $user->ID ) && ! \is_super_admin( $user->ID ) ) {
					$redirect_to = \user_admin_url();
				} elseif ( \is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = \get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? \admin_url( 'profile.php' ) : \home_url();
				}
			}

			// Return only the path component to avoid host mismatches in proxied environments.
			$redirect_path = ! empty( $redirect_to ) ? \wp_parse_url( $redirect_to, PHP_URL_PATH ) : '/wp-admin/';
			$redirect_query = \wp_parse_url( $redirect_to, PHP_URL_QUERY );
			$redirect_to = $redirect_path . ( $redirect_query ? '?' . $redirect_query : '' );

			if ( Pending_2FA_Helper::OUTCOME_CHALLENGE === $outcome ) {
				// No session exists yet. The nonce is what carries the user across to
				// the challenge, exactly as the password flow does it.
				$login_nonce = Login::create_login_nonce( $user->ID );

				if ( ! $login_nonce ) {
					return new \WP_Error( 'login_nonce_failed', __( 'Could not start the second authentication step.', 'wp-2fa' ), array( 'status' => 500 ) );
				}

				return \rest_ensure_response(
					array(
						'status'      => 'pending_2fa',
						'message'     => __( 'Passkey verified. A second authentication factor is required.', 'wp-2fa' ),
						'user_id'     => (int) $user->ID,
						'login_nonce' => $login_nonce['key'],
						'provider'    => User_Helper::get_enabled_method_for_user( $user ),
						'redirect_to' => $redirect_to,
						'login_url'   => \wp_login_url(),
					)
				);
			}

			return \rest_ensure_response(
				array(
					'status'      => 'verified',
					'message'     => __( 'Successfully signin with Passkey.', 'wp-2fa' ),
					'redirect_to' => $redirect_to,
				)
			);
		}
	}
}
