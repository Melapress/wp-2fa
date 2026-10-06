<?php
/**
 * Responsible for the register API endpoints
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
use WP2FA\Methods\Passkeys\Byte_Buffer;
use WP2FA\Passkeys\Pending_2FA_Helper;
use WP2FA\Authenticator\Login;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Endpoints registering
 */
if ( ! class_exists( '\WP2FA\Passkeys\Ajax_Passkeys' ) ) {

	/**
	 * Register API controller
	 *
	 * @since 3.0.0
	 */
	class Ajax_Passkeys {

		/**
		 * Encode data using base64url (RFC 4648 §5) without padding.
		 *
		 * @param string $data Binary data to encode.
		 *
		 * @return string
		 *
		 * @since 3.1.0
		 */
		private static function base64url_encode( string $data ): string {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for WebAuthn challenge encoding.
			return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
		}

		/**
		 * Basic transient-based rate limiting.
		 * Limiter key is derived from action and client IP.
		 *
		 * @param string $action Unique action key.
		 * @param int    $max    Max requests per window (unused; retained for signature compatibility).
		 * @param int    $window Window in seconds (unused; retained for signature compatibility).
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		private static function maybe_rate_limit( string $action, int $max = 30, int $window = 300 ): void {
			// Delegate to the shared limiter so the REST and admin-ajax sign-in paths
			// stay in lockstep (see Passkeys_Rate_Limiter). guard_ajax() emits a JSON
			// 429 and stops the request when the per-IP bucket is exceeded.
			if ( ! class_exists( '\WP2FA\Passkeys\Passkeys_Rate_Limiter', false ) ) {
				require_once __DIR__ . '/class-passkeys-rate-limiter.php';
			}
			Passkeys_Rate_Limiter::guard_ajax( $action );
		}

		/**
		 * Reject oversized public AJAX requests before WebAuthn parsing or crypto.
		 *
		 * @return void
		 * 
		 * @since 4.2.0
		 */
		private static function reject_oversized_request(): void {
			$max = (int) \apply_filters(
				'wp_2fa_passkeys_rl_max_body_bytes',
				Passkeys_Rate_Limiter::MAX_BODY_BYTES
			);
			$content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

			if ( $max > 0 && $content_length > $max ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 413 );
			}
		}

		/**
		 * Resolve a public sign-in identifier using WordPress login semantics.
		 *
		 * The login exactly as entered first, then - for anything with an @ - the
		 * email. Both passkey transports, and both steps of each, use this one, so
		 * an account resolves the same whichever way the browser signs in.
		 *
		 * @param mixed $identifier Username or email supplied by the client.
		 * @param bool  $slashed    Whether the value is magic-quoted, as $_POST is.
		 *
		 * @return \WP_User|null
		 * 
		 * @since 4.2.0
		 */
		public static function resolve_signin_user( $identifier, bool $slashed = true ): ?\WP_User {
			if ( ! is_string( $identifier ) ) {
				return null;
			}

			// $_POST arrives slashed; a REST request's JSON body does not.
			$identifier = trim( $slashed ? \wp_unslash( $identifier ) : $identifier );
			if ( '' === $identifier || strlen( $identifier ) > Passkeys_Rate_Limiter::MAX_USER_LEN ) {
				return null;
			}

			// Try the login exactly as entered first. Sanitizing it before lookup can
			// rewrite legitimate legacy usernames, and filters on sanitize_user can
			// make the AJAX transport behave differently from wp-login.php.
			$user = \get_user_by( 'login', $identifier );
			if ( $user instanceof \WP_User ) {
				return $user;
			}

			if ( false !== strpos( $identifier, '@' ) ) {
				$email = \sanitize_email( $identifier );
				if ( ! $email || ! \is_email( $email ) ) {
					return null;
				}

				$user = \get_user_by( 'email', $email );

				return $user instanceof \WP_User ? $user : null;
			}

			return null;
		}

		/**
		 * Record why a public passkey request was unavailable without disclosing it
		 * to the unauthenticated caller.
		 *
		 * @param string $reason  Internal diagnostic reason.
		 * @param int    $user_id Resolved user ID, when available.
		 *
		 * @return void
		 * 
		 * @since 4.2.0
		 */
		private static function debug_unavailable( string $reason, int $user_id = 0 ): void {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only authentication diagnostic.
				\error_log( sprintf( '[WP-2FA] AJAX passkey sign-in unavailable (%s, user ID %d).', $reason, $user_id ) );
			}
		}

		/**
		 * Inits the class hooks
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function init() {
			/*
			 * Every handler below answers with wp_send_json_*() - its errors too.
			 * WordPress ignores what an AJAX action returns, so the WP_Error objects
			 * some of them used to return reached the browser as a bare "0".
			 */
			\add_action( 'wp_ajax_wp2fa_profile_revoke_key', array( __CLASS__, 'revoke_profile_key' ) );
			\add_action( 'wp_ajax_wp2fa_profile_enable_key', array( __CLASS__, 'wp2fa_profile_enable_key' ) );
			\add_action( 'wp_ajax_wp2fa_profile_register', array( __CLASS__, 'register_request' ) );
			\add_action( 'wp_ajax_wp2fa_profile_response', array( __CLASS__, 'register_response' ) );
			\add_action( 'wp_ajax_nopriv_wp2fa_signin_request', array( __CLASS__, 'signin_request' ) );
			\add_action( 'wp_ajax_nopriv_wp2fa_signin_response', array( __CLASS__, 'signin_response' ) );
			\add_action( 'wp_ajax_wp2fa_signin_request', array( __CLASS__, 'signin_request' ) );
			\add_action( 'wp_ajax_wp2fa_signin_response', array( __CLASS__, 'signin_response' ) );
		}
		/**
		 * Whether this sign-in asked to be remembered.
		 *
		 * Put through the same filter every other sign-in path uses, so a site overriding
		 * the choice gets the same answer whichever way its people sign in.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function remember_requested(): bool {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The assertion itself is what authenticates this request; read only as a preference.
			$raw = isset( $_POST['rememberme'] ) ? \sanitize_text_field( \wp_unslash( (string) $_POST['rememberme'] ) ) : '';

			return (bool) \apply_filters(
				WP_2FA_PREFIX . 'rememberme',
				\filter_var( $raw, FILTER_VALIDATE_BOOLEAN )
			);
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return void - Answers with JSON and ends the request.
		 *
		 * @since 3.0.0
		 */
		public static function signin_response() {

			// Apply light rate limiting for unauthenticated login attempts.
			self::maybe_rate_limit( 'signin_response' );
			self::reject_oversized_request();

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public sign-in endpoint cannot require nonce.
			$user = self::resolve_signin_user( $_POST['user'] ?? null );
			if ( ! $user ) {
				// Generic failure to reduce user enumeration.
				return \wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
			}

			if ( Passkeys_Rate_Limiter::account_exceeded( 'signin_response', (int) $user->ID ) ) {
				return \wp_send_json_error( __( 'Too many requests. Please try again later.', 'wp-2fa' ), 429 );
			}

			$data = $_POST['data'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

			if ( ! $data || ! \is_array( $data ) || empty( $data ) ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			$request_id = \sanitize_text_field( \wp_unslash( ( $_POST['request_id'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( ! $request_id ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			// Get the user-bound challenge from cache.
			$stored = \get_transient( Source_Repository::PASSKEYS_META . $request_id );

			if ( ! is_array( $stored ) || empty( $stored['challenge'] ) || (string) ( $stored['uid'] ?? '' ) !== (string) $user->ID ) {
				\wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
			}
			$challenge = (string) $stored['challenge'];

			$asse_rep = \map_deep( \wp_unslash( $data ), 'sanitize_text_field' );
			if ( empty( $asse_rep['rawId'] ) || ! is_string( $asse_rep['rawId'] ) || empty( $asse_rep['response'] ) || ! is_array( $asse_rep['response'] ) ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			$b64url_re = '/^[A-Za-z0-9\\-_]+=*$/';
			if ( ! preg_match( $b64url_re, $asse_rep['rawId'] ) ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}
			foreach ( array( 'clientDataJSON', 'authenticatorData', 'signature' ) as $required_key ) {
				if (
					empty( $asse_rep['response'][ $required_key ] )
					|| ! is_string( $asse_rep['response'][ $required_key ] )
					|| ! preg_match( $b64url_re, $asse_rep['response'][ $required_key ] )
				) {
					\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
				}
			}

			$uid = $user ? (string) $user->ID : '';

			// Atomically claim the challenge. A replay racing this request must lose.
			if ( ! \delete_transient( Source_Repository::PASSKEYS_META . $request_id ) ) {
				\wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
			}

			$webauthn = new Web_Authn(
				Web_Authn::get_relying_party_id(),
				Web_Authn::get_relying_party_id()
			);

			$credential_id = Web_Authn::get_raw_credential_id( $asse_rep['rawId'] );

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
				return \wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
			}

			try {
				$previous_signature_counter = isset( $user_data['extra']['signature_counter'] ) ? (int) $user_data['extra']['signature_counter'] : null;
				$data                       = $webauthn->process_get(
					Web_Authn::base64url_decode( $asse_rep['response']['clientDataJSON'] ),
					Web_Authn::base64url_decode( $asse_rep['response']['authenticatorData'] ),
					Web_Authn::base64url_decode( $asse_rep['response']['signature'] ),
					$user_data['extra']['public_key'],
					Web_Authn::base64url_decode( $challenge ),
					$previous_signature_counter,
					true
				);

				if ( Passkeys::is_enabled( User_Helper::get_user_role( (int) $uid ) ) ) {
					if ( ! $user_data['extra']['enabled'] ) {
						return \wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
					}

					if ( ! class_exists( Pending_2FA_Helper::class, false ) ) {
						require_once __DIR__ . '/class-pending-2fa-helper.php';
					}
					if ( ! Pending_2FA_Helper::can_complete_signin( $user ) ) {
						return \wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 403 );
					}

					/*
					 * A verified passkey is not always a completed sign-in. Where a
					 * second factor is also required, no session is issued here: the
					 * caller is handed a login nonce and sent to the 2FA challenge,
					 * and WordPress only learns about the user once it is answered.
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
						/*
						 * Deliberately a 200 carrying success:false, not a 403. jQuery
						 * treats a non-2xx as a transport failure and rejects before the
						 * client can read the body, so a status code here would swallow
						 * the very message the user needs to see. Nothing is admitted
						 * either way — no cookie is issued on this path.
						 */
						return \wp_send_json_error(
							array(
								'status'  => 'second_factor_required',
								'message' => __( 'This account needs a second authentication factor as well as a passkey. Sign in with your password to set one up.', 'wp-2fa' ),
							)
						);
					}

					if ( Pending_2FA_Helper::OUTCOME_ADMIT === $outcome ) {
						// Passkey alone completes the sign-in.
						/*
						 * Honour the user's choice rather than always granting the longer
						 * session. This used to pass true unconditionally, so a passkey
						 * sign-in ran for a fortnight even where the person had deliberately
						 * left "Remember Me" alone on a shared machine — failing quietly, and
						 * in the direction that costs them rather than inconveniences them.
						 */
						\wp_set_auth_cookie( $uid, self::remember_requested(), is_ssl() );

						// After the cookie, not before: the pending record is bound to
						// the session token issued here.
						Pending_2FA_Helper::mark_pending( (int) $uid, array( 'source' => 'passkey' ) );
						\do_action( WP_2FA_PREFIX . 'passkey_login', $user );
					}

				} else {
					return \wp_send_json_error(
						__( 'User is not eligible for this method.', 'wp-2fa' )
					);
				}
			} catch ( \Throwable $error ) {
				// Log the detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] signin_response error: %s', $error->getMessage() ) );
				}
				\wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
			}

			$redirect_to = isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : '';

			/**
			 * Filters the login redirect URL.
			 *
			 * @since 3.0.0
			 *
			 * @param string           $redirect_to           The redirect destination URL.
			 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
			 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
			 */
			$redirect_to = \wp_validate_redirect( apply_filters( 'login_redirect', $redirect_to, '', $user ), \admin_url() );

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || \admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
					$redirect_to = user_admin_url();
				} elseif ( is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? \admin_url( 'profile.php' ) : \home_url();
				}
			}

			// Return only the path component to avoid host mismatches in proxied environments.
			$redirect_path = ! empty( $redirect_to ) ? \wp_parse_url( $redirect_to, PHP_URL_PATH ) : '/wp-admin/';
			$redirect_query = \wp_parse_url( $redirect_to, PHP_URL_QUERY );
			$redirect_to = $redirect_path . ( $redirect_query ? '?' . $redirect_query : '' );

			if ( Pending_2FA_Helper::OUTCOME_CHALLENGE === $outcome ) {
				// No session exists yet; the nonce carries the user across to the
				// challenge, exactly as the password flow does.
				$login_nonce = Login::create_login_nonce( $user->ID );

				if ( ! $login_nonce ) {
					return \wp_send_json_error(
						array( 'message' => __( 'Could not start the second authentication step.', 'wp-2fa' ) ),
						500
					);
				}

				\wp_send_json_success(
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

			\wp_send_json_success(
				array(
					'status'      => 'verified',
					'message'     => __( 'Successfully signin with Passkey.', 'wp-2fa' ),
					'redirect_to' => $redirect_to,
				)
			);
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return void - Answers with JSON and ends the request.
		 *
		 * @since 3.0.0
		 */
		public static function signin_request() {

			// Apply light rate limiting for unauthenticated login attempts.
			self::maybe_rate_limit( 'signin_request' );
			self::reject_oversized_request();

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public sign-in endpoint cannot require nonce.
			$user = self::resolve_signin_user( $_POST['user'] ?? null );

			if ( ! $user ) {
				self::debug_unavailable( 'unknown identifier' );
				// Generic message to reduce user enumeration.
				return \wp_send_json_error( __( 'Passkey authentication not available.', 'wp-2fa' ), 400 );
			}

			if ( Passkeys_Rate_Limiter::account_exceeded( 'signin_request', (int) $user->ID ) ) {
				return \wp_send_json_error( __( 'Too many requests. Please try again later.', 'wp-2fa' ), 429 );
			}

			// if ( User_Helper::is_excluded( $user->ID ) ) {
			// Generic message to reduce user enumeration.
			// return \wp_send_json_error( __( 'Passkey authentication not available.', 'wp-2fa' ), 400 );
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
				self::debug_unavailable( 'no stored credentials', (int) $user->ID );
				// Generic message to reduce enumeration of passkey availability.
				return \wp_send_json_error( __( 'Passkey authentication not available.', 'wp-2fa' ), 400 );
			}

			$request_id = \wp_generate_uuid4();

			// Use base64url encoding for WebAuthn challenge consistency.
			$challenge = self::base64url_encode( random_bytes( 32 ) );

			$options = array(
				'challenge'        => $challenge,
				'rpId'             => Web_Authn::get_relying_party_id(),
				'allowCredentials' => $allow_credentials ?? array(),
				'userVerification' => 'required',
				'timeout'          => 5 * 60 * 1000,
				// No 'uid': the client never needs it, and returning it only when the
				// account has a passkey told anyone asking which accounts exist.
				// The user stays bound to the challenge through the stored copy below.
			);

			// Store the challenge for 60 seconds and bind it to the resolved account.
			// For some hosting transient set to persistent object cache like Redis/Memcache. By default it stored in options table.
			\set_transient(
				Source_Repository::PASSKEYS_META . $request_id,
				array(
					'challenge' => $challenge,
					'uid'       => (string) $user->ID,
					'iat'       => time(),
				),
				60
			);

			$response = array(
				'options'    => $options,
				'request_id' => $request_id,
			);

			return \wp_send_json_success( $response, 200 );
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return void - Answers with JSON and ends the request.
		 *
		 * @throws \Throwable - When unable to parse the JSON data.
		 *
		 * @since 3.0.0
		 */
		public static function register_response() {
			self::validate_nonce( 'wp2fa_profile_register' );

			$data = $_POST['data'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

			// The AJAX JS sends data as a JSON string via URLSearchParams; decode it.
			if ( is_string( $data ) ) {
				$data = json_decode( wp_unslash( $data ), true );
			}

			if ( ! $data || ! \is_array( $data ) || empty( $data ) ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
				return;
			}

			try {
				$user = \wp_get_current_user();
				if ( ! Passkeys::is_enabled( User_Helper::get_user_role( $user ) ) ) {
					\wp_send_json_error( __( 'Passkeys are disabled for this account.', 'wp-2fa' ), 403 );
				}

				$challenge = \get_transient( Source_Repository::REGISTRATION_CHALLENGE_PREFIX . $user->ID );
				if ( ! is_string( $challenge ) || '' === $challenge ) {
					\wp_send_json_error( __( 'Registration challenge expired.', 'wp-2fa' ), 400 );
					return;
				}

				$params  = array(
					'rawId'    => \sanitize_text_field( \wp_unslash( $data['rawId'] ?? '' ) ),
					'response' => \map_deep( \wp_unslash( $data['response'] ?? array() ), 'sanitize_text_field' ),
				);
				$user_id = $user->ID;

				$web_authn = new Web_Authn(
					Web_Authn::get_relying_party_id(),
					Web_Authn::get_relying_party_id()
				);

				$credential_id      = Web_Authn::get_raw_credential_id( $params['rawId'] );
				$client_data_json   = Web_Authn::base64url_decode( $params['response']['clientDataJSON'] );
				$attestation_object = Web_Authn::base64url_decode( $params['response']['attestationObject'] );
				$challenge          = Web_Authn::base64url_decode( $challenge );

				$attestation = $web_authn->process_create(
					$client_data_json,
					new Byte_Buffer( $attestation_object ),
					$challenge,
					false, // User verification not required for current flow.
				);

				$data = array(
					'user_id'       => $user_id,
					'credential_id' => $credential_id,
					'public_key'    => $attestation->credential_public_key,
					'aaguid'        => Web_Authn::convert_aaguid_to_hex( $attestation->aaguid ),
					'last_used_at'  => null,
				);

				\delete_transient( Source_Repository::REGISTRATION_CHALLENGE_PREFIX . $user->ID );

				// Get platform from user agent and sanitize it before use/storage.
				$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? \sanitize_text_field( \wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

				switch ( true ) {
					case preg_match( '/android/i', $user_agent ):
						$platform = 'Android';
						break;
					case preg_match( '/iphone/i', $user_agent ):
						$platform = 'iPhone / iOS';
						break;
					case preg_match( '/linux/i', $user_agent ):
						$platform = 'Linux';
						break;
					case preg_match( '/macintosh|mac os x/i', $user_agent ):
						$platform = 'Mac OS';
						break;
					case preg_match( '/windows|win32/i', $user_agent ):
						$platform = 'Windows';
						break;
					default:
						$platform = 'unknown';
						break;
				}

				if ( isset( $_POST['passkey_name'] ) && ! empty( $_POST['passkey_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already validated earlier in method.
					$name = \sanitize_text_field( \wp_unslash( $_POST['passkey_name'] ) );
				} else {
					$name = "Generated on $platform";
				}

				$extra_data = array(
					'name'              => $name,
					'created'           => time(),
					'last_used'         => false,
					'enabled'           => true,
					'ip_address'        => Authentication_Server::get_ip_address(),
					'platform'          => $platform,
					'user_agent'        => $user_agent,
					'aaguid'            => $data['aaguid'],
					'public_key'        => $data['public_key'],
					'credential_id'     => $credential_id,
					'transports'        => ( isset( $params['response']['transports'] ) ) ? \wp_json_encode( $params['response']['transports'] ) : \wp_json_encode( array() ),
					'signature_counter' => $web_authn->get_signature_counter(),
				);

				// Finally store the credential source to database.
				Source_Repository::save_credential_source( $user, $extra_data );

			} catch ( \Throwable $error ) {
				// Log detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_response error: %s', $error->getMessage() ) );
				}
				\wp_send_json_error( __( 'Verification failed.', 'wp-2fa' ), 400 );
			}

			Passkeys::set_user_method( $user );

			\wp_send_json_success( 'verified' );
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return void - Answers with JSON and ends the request.
		 *
		 * @since 3.0.0
		 */
		public static function register_request() {

			self::validate_nonce( 'wp2fa_profile_register' );

			$user = \wp_get_current_user();

			if ( ! $user || 0 === $user->ID ) {
				// Generic message to reduce user enumeration.
				return \wp_send_json_error( __( 'Passkey authentication not available.', 'wp-2fa' ), 400 );
			}
			if ( ! Passkeys::is_enabled( User_Helper::get_user_role( $user ) ) ) {
				\wp_send_json_error( __( 'Passkeys are disabled for this account.', 'wp-2fa' ), 403 );
			}

			// if ( User_Helper::is_excluded( $user->ID ) ) {
			// Generic message to reduce user enumeration.
			// return \wp_send_json_error( __( 'Passkey authentication not available.', 'wp-2fa' ), 400 );
			// }

			// Strict boolean parsing for is_usb.
			$is_usb_raw = isset( $_POST['is_usb'] ) ? \sanitize_text_field( \wp_unslash( (string) $_POST['is_usb'] ) ) : 'false'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$is_usb     = filter_var( $is_usb_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			$is_usb     = ( null === $is_usb ) ? false : (bool) $is_usb;

			try {
				$public_key_credential_creation_options = Authentication_Server::create_attestation_request( $user, \null, $is_usb );
			} catch ( \Throwable $error ) {
				// Log detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_request error: %s', $error->getMessage() ) );
				}
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			\wp_send_json_success( $public_key_credential_creation_options, 200 );
		}

		/**
		 * Revokes the stored key from the user profile.
		 *
		 * @return void - Answers with JSON and ends the request.
		 *
		 * @since 3.0.0
		 */
		public static function wp2fa_profile_enable_key() {

			// Accept both legacy and new action names for compatibility.
			self::validate_nonce( 'wp2fa-user-passkey-enable' );
			// Fallback to legacy action if the first failed (validate_nonce will die on failure),
			// so we only call it if the request still proceeds.

			$user_id     = (int) \sanitize_text_field( \wp_unslash( ( $_POST['user_id'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$fingerprint = (string) \sanitize_text_field( \wp_unslash( ( $_POST['fingerprint'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$current = \get_current_user_id();
			if ( $current !== $user_id && ! \current_user_can( 'edit_user', $user_id ) ) {
				\wp_send_json_error( __( 'Insufficient permissions.', 'wp-2fa' ), 403 );

				\wp_die();
			}

			if ( ! $fingerprint ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			try {
				$meta_key = Source_Repository::PASSKEYS_META . $fingerprint;

				$user = \get_user_by( 'ID', $user_id );

				$user_data = \json_decode( (string) \get_user_meta( $user->ID, $meta_key, true ), true, 512, JSON_THROW_ON_ERROR );
				if ( empty( $user_data['extra']['enabled'] ) && ! Passkeys::is_enabled( User_Helper::get_user_role( $user ) ) ) {
					\wp_send_json_error( __( 'Passkeys are disabled for this account.', 'wp-2fa' ), 403 );
				}

				// Update the meta value.
				if ( isset( $user_data['extra']['enabled'] ) ) {
					$user_data['extra']['enabled'] = ! (bool) $user_data['extra']['enabled'];
				} else {
					$user_data['extra']['enabled'] = false;
				}
				// Slashed like every other writer of this meta: update_metadata()
				// unslashes, which turned the PEM's \n into n and broke the key for good.
				$public_key_json = addcslashes( \wp_json_encode( $user_data, JSON_UNESCAPED_SLASHES ), '\\' );
				\update_user_meta( $user->ID, $meta_key, $public_key_json );
			} catch ( \Throwable $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] enable_key error: %s', $error->getMessage() ) );
				}
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			\wp_send_json_success( 2, 200 );
		}

		/**
		 * Revokes the stored key from the user profile.
		 *
		 * @return void - Answers with JSON and ends the request.
		 *
		 * @since 3.0.0
		 */
		public static function revoke_profile_key() {

			self::validate_nonce( 'wp2fa-user-passkey-revoke' );

			$user_id     = (int) \sanitize_text_field( \wp_unslash( ( $_POST['user_id'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$fingerprint = (string) \sanitize_text_field( \wp_unslash( ( $_POST['fingerprint'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$current = \get_current_user_id();
			if ( $current !== $user_id ) {
				\wp_send_json_error( __( 'Insufficient permissions.', 'wp-2fa' ), 403 );

				\wp_die();
			}

			if ( ! $fingerprint ) {
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			$credential = Source_Repository::find_one_by_credential_id( $fingerprint );

			if ( ! $credential ) {
				\wp_send_json_error( __( 'Not found.', 'wp-2fa' ), 404 );
			}

			try {
				$user = \wp_get_current_user();
				Source_Repository::delete_credential_source( $fingerprint, $user );
				User_Helper::update_user_state( $user );
				User_Helper::set_user_status( $user );
			} catch ( \Throwable $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] revoke_key error: %s', $error->getMessage() ) );
				}
				\wp_send_json_error( __( 'Invalid request.', 'wp-2fa' ), 400 );
			}

			\wp_send_json_success( 2, 200 );
		}

		/**
		 * Verifies the nonce and user capability.
		 *
		 * @param string $action - Name of the nonce action.
		 * @param string $nonce_name Name of the nonce.
		 *
		 * @return bool|void
		 *
		 * @since 3.0.0
		 */
		public static function validate_nonce( string $action, string $nonce_name = '_wpnonce' ) {
			if ( ! \wp_doing_ajax() || ! \check_ajax_referer( $action, $nonce_name, false ) ) {
				\wp_send_json_error( 'Insufficient permissions or invalid nonce.', 403 );

				\wp_die();
			}

			return \true;
		}
	}
}
