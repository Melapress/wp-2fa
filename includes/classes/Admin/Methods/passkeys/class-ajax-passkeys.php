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
		 * Get client IP address best-effort for rate limiting/logging.
		 *
		 * @return string
		 *
		 * @since 3.1.0
		 */
		private static function get_client_ip(): string {
			$ip = '';
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$candidate = \sanitize_text_field( \wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
				$ip        = filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
			}
			// If REMOTE_ADDR is empty or invalid, consider X-Forwarded-For (best-effort).
			if ( empty( $ip ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$xff       = \sanitize_text_field( \wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				$parts     = explode( ',', $xff );
				$candidate = trim( $parts[0] );
				$ip        = filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
			}
			return (string) $ip;
		}

		/**
		 * Basic transient-based rate limiting.
		 * Limiter key is derived from action and client IP.
		 *
		 * @param string $action Unique action key.
		 * @param int    $max    Max requests per window.
		 * @param int    $window Window in seconds.
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		private static function maybe_rate_limit( string $action, int $max = 30, int $window = 300 ): void {
			$ip       = self::get_client_ip();
			$key      = sprintf( '%s_rl_%s', Source_Repository::PASSKEYS_META, md5( $action . '|' . $ip ) );
			$attempts = (int) get_transient( $key );
			++$attempts;
			set_transient( $key, $attempts, $window );
			if ( $attempts > $max ) {
				wp_send_json_error( __( 'Too many requests. Please try again later.', 'wp-2fa' ), 429 );
				wp_die();
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
			\add_action( 'wp_ajax_wp2fa_profile_revoke_key', array( __CLASS__, 'revoke_profile_key' ) );
			\add_action( 'wp_ajax_wp2fa_profile_enable_key', array( __CLASS__, 'wp2fa_profile_enable_key' ) );
			\add_action( 'wp_ajax_wp2fa_profile_register', array( __CLASS__, 'register_request' ) );
			\add_action( 'wp_ajax_wp2fa_profile_response', array( __CLASS__, 'register_response' ) );
			\add_action( 'wp_ajax_nopriv_wp2fa_signin_request', array( __CLASS__, 'signin_request' ) );
			\add_action( 'wp_ajax_nopriv_wp2fa_signin_response', array( __CLASS__, 'signin_response' ) );
			\add_action( 'wp_ajax_wp2fa_signin_request', array( __CLASS__, 'signin_request' ) );
			\add_action( 'wp_ajax_wp2fa_signin_response', array( __CLASS__, 'signin_response' ) );
			\add_action( 'wp_ajax_wp2fa_user_passkey_rename', array( __CLASS__, 'passkey_rename' ) );
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @since 3.0.0
		 */
		public static function signin_response() {

			// Apply light rate limiting for unauthenticated login attempts.
			self::maybe_rate_limit( 'signin_response' );

			if ( ! empty( $_POST['user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public sign-in endpoint cannot require nonce
				$data['user'] = \sanitize_user( \wp_unslash( $_POST['user'] ) );
				$user_login   = \get_user_by( 'login', $data['user'] );
				$user         = $user_login ? $user_login : \get_user_by( 'email', $data['user'] );
				if ( ! $user ) {
					// Generic failure to reduce user enumeration.
					return \wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
				}
			}

			$data = $_POST['data'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

			if ( ! $data || ! \is_array( $data ) || empty( $data ) ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$request_id = \sanitize_text_field( \wp_unslash( ( $_POST['request_id'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( ! $request_id ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			// Get challenge from cache.
			$challenge = \get_transient( Source_Repository::PASSKEYS_META . $request_id );

			// If $challenge not exists, return WP_Error.
			if ( ! $challenge ) {
				return new \WP_Error( 'invalid_challenge', __( 'Authentication failed.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$asse_rep = \map_deep( \wp_unslash( $data ?? array() ), 'sanitize_text_field' );

			if ( empty( $asse_rep ) ) {
				return new \WP_Error( 'invalid_challenge', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$uid = $user ? (string) $user->ID : '';

			// Delete challenge from cache.
			\delete_transient( Source_Repository::PASSKEYS_META . $request_id );

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

			$data = $webauthn->process_get(
				Web_Authn::base64url_decode( $asse_rep['response']['clientDataJSON'] ),
				Web_Authn::base64url_decode( $asse_rep['response']['authenticatorData'] ),
				Web_Authn::base64url_decode( $asse_rep['response']['signature'] ),
				$user_data['extra']['public_key'],
				Web_Authn::base64url_decode( $challenge )
			);

			try {

				if ( Passkeys::is_enabled( User_Helper::get_user_role( (int) $uid ) ) ) {
					if ( ! $user_data['extra']['enabled'] ) {
						return \wp_send_json_error( __( 'Authentication failed.', 'wp-2fa' ), 400 );
					}

					// If user found and authorized, set the login cookie.
					\wp_set_auth_cookie( $uid, true, is_ssl() );

					// Mark 2FA as pending using helper so other components can enforce a challenge.
					if ( ! class_exists( Pending_2FA_Helper::class, false ) ) {
						require_once __DIR__ . '/class-pending-2fa-helper.php';
					}
					Pending_2FA_Helper::mark_pending( (int) $uid, array( 'source' => 'passkey' ) );

					// Update the meta value.
					$user_data['extra']['last_used'] = time();
					$public_key_json                 = addcslashes( \wp_json_encode( $user_data, JSON_UNESCAPED_SLASHES ), '\\' );
					\update_user_meta( $uid, $meta_key, $public_key_json );
				} else {
					return \wp_send_json_error(
						__( 'User is not eligible for this method.', 'wp-2fa' )
					);
				}
			} catch ( \Exception $error ) {
				// Log the detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] signin_response error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'public_key_validation_failed', __( 'Authentication failed.', 'wp-2fa' ), array( 'status' => 400 ) );
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
			$redirect_to = apply_filters( 'login_redirect', $redirect_to, '', $user );

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

			\wp_send_json_success(
				array(
					'status'      => 'verified',
					'message'     => __( 'Successfully signin with Passkey.', 'wp-2fa' ),
					'redirect_to' => $redirect_to ?? '',
				)
			);
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @since 3.0.0
		 */
		public static function signin_request() {

			// Apply light rate limiting for unauthenticated login attempts.
			self::maybe_rate_limit( 'signin_request' );

			$user = null;

			if ( ! empty( $_POST['user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public sign-in endpoint cannot require nonce
				$data['user'] = \sanitize_user( \wp_unslash( $_POST['user'] ) );
				$user_login   = \get_user_by( 'login', $data['user'] );
				$user         = $user_login ? $user_login : \get_user_by( 'email', $data['user'] );
			}

			if ( ! $user ) {
				// Generic message to reduce user enumeration.
				return \wp_send_json_error( __( 'Passkey authentication not available.', 'wp-2fa' ), 400 );
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
				'uid'              => $user ? (string) $user->ID : '',
			);

			// Store the challenge in transient for 60 seconds.
			// For some hosting transient set to persistent object cache like Redis/Memcache. By default it stored in options table.
			\set_transient( Source_Repository::PASSKEYS_META . $request_id, $challenge, 60 );

			$response = array(
				'options'    => $options,
				'request_id' => $request_id,
			);

			return \wp_send_json_success( $response, 200 );
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return void|\WP_Error
		 *
		 * @throws \Throwable - When unable to parse the JSON data.
		 *
		 * @since 3.0.0
		 */
		public static function register_response() {
			self::validate_nonce( 'wp2fa_profile_register' );

			$data = $_POST['data'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

			if ( ! $data || ! \is_array( $data ) || empty( $data ) ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			try {
				$user = \wp_get_current_user();

				// Get expected challenge from user meta.
				$challenge = \get_user_meta( $user->ID, WP_2FA_PREFIX . 'passkey_challenge', true );

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

				\delete_user_meta( $user->ID, WP_2FA_PREFIX . 'passkey_challenge' );

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
					'name'          => $name,
					'created'       => time(),
					'last_used'     => false,
					'enabled'       => true,
					'ip_address'    => Authentication_Server::get_ip_address(),
					'platform'      => $platform,
					'user_agent'    => $user_agent,
					'aaguid'        => $data['aaguid'],
					'public_key'    => $data['public_key'],
					'credential_id' => $credential_id,
					'transports'    => ( isset( $params['response']['transports'] ) ) ? \wp_json_encode( $params['response']['transports'] ) : \wp_json_encode( array() ),
				);

				// Finally store the credential source to database.
				Source_Repository::save_credential_source( $user, $extra_data );

			} catch ( \Exception $error ) {
				// Log detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_response error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'public_key_validation_failed', __( 'Verification failed.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			Passkeys::set_user_method( $user );

			\wp_send_json_success( 'verified' );
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @return void|\WP_Error
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
			} catch ( \Exception $error ) {
				// Log detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_request error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			\wp_send_json_success( $public_key_credential_creation_options, 200 );
		}

		/**
		 * Revokes the stored key from the user profile.
		 *
		 * @return void|\WP_Error
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
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			try {
				$meta_key = Source_Repository::PASSKEYS_META . $fingerprint;

				$user = \get_user_by( 'ID', $user_id );

				$user_data = \json_decode( (string) \get_user_meta( $user->ID, $meta_key, true ), true, 512, JSON_THROW_ON_ERROR );

				// Update the meta value.
				if ( isset( $user_data['extra']['enabled'] ) ) {
					$user_data['extra']['enabled'] = ! (bool) $user_data['extra']['enabled'];
				} else {
					$user_data['extra']['enabled'] = false;
				}
				$public_key_json = \wp_json_encode( $user_data, JSON_UNESCAPED_SLASHES );
				\update_user_meta( $user->ID, $meta_key, $public_key_json );
			} catch ( \Exception $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] enable_key error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			\wp_send_json_success( 2, 200 );
		}

		/**
		 * Revokes the stored key from the user profile.
		 *
		 * @return void|\WP_Error
		 *
		 * @since 3.0.0
		 */
		public static function revoke_profile_key() {

			self::validate_nonce( 'wp2fa-user-passkey-revoke' );

			$user_id     = (int) \sanitize_text_field( \wp_unslash( ( $_POST['user_id'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$fingerprint = (string) \sanitize_text_field( \wp_unslash( ( $_POST['fingerprint'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$current = \get_current_user_id();
			if ( $current !== $user_id && ! \current_user_can( 'edit_user', $user_id ) ) {
				\wp_send_json_error( __( 'Insufficient permissions.', 'wp-2fa' ), 403 );

				\wp_die();
			}

			if ( ! $fingerprint ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			$credential = Source_Repository::find_one_by_credential_id( $fingerprint );

			if ( ! $credential ) {
				return new \WP_Error( 'not_found', __( 'Not found.', 'wp-2fa' ), array( 'status' => 404 ) );
			}

			try {
				$user = \wp_get_current_user();
				Source_Repository::delete_credential_source( $fingerprint, $user );
			} catch ( \Exception $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] revoke_key error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
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

		/**
		 * Renames given passkey.
		 *
		 * @return \WP_Error|void
		 *
		 * @since 3.1.0
		 */
		public static function passkey_rename() {
			self::validate_nonce( 'wp2fa-user-passkey-rename', 'nonce' );

			$id    = (string) \sanitize_text_field( \wp_unslash( ( $_POST['id'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = (string) \sanitize_text_field( \wp_unslash( ( $_POST['value'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( ! $id ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			if ( ! $value ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			try {
				$meta_key = Source_Repository::PASSKEYS_META . $id;

				$user = \wp_get_current_user();

				$user_data = \json_decode( (string) \get_user_meta( $user->ID, $meta_key, true ), true, 512, JSON_THROW_ON_ERROR );

				// Update the meta value.
				$user_data['extra']['name'] = $value;
				$public_key_json            = \wp_json_encode( $user_data, JSON_UNESCAPED_SLASHES );
				\update_user_meta( $user->ID, $meta_key, $public_key_json );
			} catch ( \Exception $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] passkey_rename error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			\wp_send_json_success( 2, 200 );
		}
	}
}
