<?php
/**
 * Responsible for the register API endpoints
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2025 Melapress
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

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Endpoints registering
 */
if ( ! class_exists( '\WP2FA\Passkeys\API_Register' ) ) {

	/**
	 * Register API controller
	 *
	 * @since 3.0.0
	 */
	class API_Register {

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @param \WP_REST_Request $request The request object.
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @since 3.0.0
		 */
		public static function register_request_action( \WP_REST_Request $request ) {

			// Ensure the caller is authenticated (defense-in-depth; routes should also set permission_callback).
			$current_user = \wp_get_current_user();
			if ( ! $current_user || 0 === (int) $current_user->ID ) {
				return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'wp-2fa' ), array( 'status' => 401 ) );
			}

			// if ( User_Helper::is_excluded( $current_user->ID ) ) {
			// 	return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'wp-2fa' ), array( 'status' => 401 ) );
			// }

			// Sanitize and normalize inputs.
			$is_usb = (bool) $request->get_param( 'is_usb' );
			if ( ! $is_usb ) {
				$is_usb = false;
			}

			try {
				$public_key_credential_creation_options = Authentication_Server::create_attestation_request( $current_user, null, $is_usb );
			} catch ( \Exception $error ) {
				// Log detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_request_action error: %s', $error->getMessage() ) );
				}
				// Avoid leaking internal error details in responses.
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			return \rest_ensure_response( $public_key_credential_creation_options );
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @param \WP_REST_Request $request The request object.
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @throws \Throwable - When unable to parse the JSON data.
		 *
		 * @since 3.0.0
		 */
		public static function register_response_action( \WP_REST_Request $request ) {
			$data = $request->get_body();

			if ( ! $data ) {
				return new \WP_Error( 'invalid_request', 'Invalid request.', array( 'status' => 400 ) );
			}

			try {
				$user = \wp_get_current_user();
				if ( ! $user || 0 === (int) $user->ID ) {
					return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'wp-2fa' ), array( 'status' => 401 ) );
				}

				// Get expected challenge from user meta.
				$challenge = \get_user_meta( $user->ID, WP_2FA_PREFIX . 'passkey_challenge', true );

				try {
					// Decode JSON and throw on error to catch malformed payloads.
					$data = json_decode( $data, true, 512, JSON_THROW_ON_ERROR );
				} catch ( \JsonException $json_exception ) {
					return new \WP_Error( 'invalid_request', __( 'Invalid JSON payload.', 'wp-2fa' ), array( 'status' => 400 ) );
				}

				// Extract attestation response safely without altering base64url payloads.
				$att_resp = $data['attResp'] ?? array();

				$raw_id = isset( $att_resp['rawId'] ) ? wp_unslash( $att_resp['rawId'] ) : '';

				// Response may contain binary/base64url values. Do not run sanitize_text_field on them.
				$resp = $att_resp['response'] ?? array();

				$client_data_json_b64 = isset( $resp['clientDataJSON'] ) ? wp_unslash( $resp['clientDataJSON'] ) : '';
				$attestation_b64      = isset( $resp['attestationObject'] ) ? wp_unslash( $resp['attestationObject'] ) : '';

				// Basic base64url validation: allow A-Z a-z 0-9 - _ and optional padding '='.
				$b64url_re = '/^[A-Za-z0-9\-_]+=*$/';

				if ( '' === $raw_id || '' === $client_data_json_b64 || '' === $attestation_b64 ) {
					return new \WP_Error( 'invalid_request', __( 'Incomplete attestation payload.', 'wp-2fa' ), array( 'status' => 400 ) );
				}

				if ( ! preg_match( $b64url_re, $client_data_json_b64 ) || ! preg_match( $b64url_re, $attestation_b64 ) || ! preg_match( $b64url_re, $raw_id ) ) {
					return new \WP_Error( 'invalid_request', __( 'Invalid attestation payload encoding.', 'wp-2fa' ), array( 'status' => 400 ) );
				}

				$params  = array(
					'rawId'    => $raw_id,
					'response' => array(
						'clientDataJSON'    => $client_data_json_b64,
						'attestationObject' => $attestation_b64,
						'transports'        => isset( $resp['transports'] ) ? $resp['transports'] : array(),
					),
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
					false, // $this->is_user_verification_required(),
				);

				$data = array(
					'user_id'       => $user_id,
					'credential_id' => $credential_id,
					'public_key'    => $attestation->credential_public_key,
					'aaguid'        => Web_Authn::convert_aaguid_to_hex( $attestation->aaguid ),
					'last_used_at'  => null,
					'passkey_name'  => $data['passkey_name'] ?? null,
				);

				\delete_user_meta( $user->ID, WP_2FA_PREFIX . 'passkey_challenge' );

				// Get platform from user agent; trim and sanitize to avoid storing excessively long or unsafe strings.
				$user_agent = (string) $request->get_header( 'User-Agent' );
				$user_agent = substr( \sanitize_text_field( $user_agent ), 0, 512 );

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

				if ( isset( $data['passkey_name'] ) && ! empty( $data['passkey_name'] ) ) {
					$name = \sanitize_text_field( \wp_unslash( $data['passkey_name'] ) );
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
					'transports'    => ( isset( $params['response']['transports'] ) ) ? \wp_json_encode( $params['response']['transports'] ) : wp_json_encode( array() ),
				);

				// Finally store the credential source to database.
				Source_Repository::save_credential_source( $user, $extra_data );

			} catch ( \Exception $error ) {
				// Log detailed error server-side only when WP_DEBUG is enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_response_action error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'public_key_validation_failed', __( 'Public key validation failed.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			Passkeys::set_user_method( $user );

			return rest_ensure_response(
				array(
					'status'  => 'verified',
					'message' => 'Successfully registered.',
				)
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
		public static function register_revoke_action( \WP_REST_Request $request ) {
			$data = $request->get_json_params();

			if ( ! $data ) {
				return new \WP_Error( 'invalid_request', 'Invalid request.', array( 'status' => 400 ) );
			}

			// Require authentication (defense-in-depth).
			$current_user = \wp_get_current_user();
			if ( ! $current_user || 0 === (int) $current_user->ID ) {
				return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'wp-2fa' ), array( 'status' => 401 ) );
			}

			// Sanitize and validate fingerprint.
			$fingerprint = isset( $data['fingerprint'] ) ? sanitize_text_field( wp_unslash( $data['fingerprint'] ) ) : '';
			if ( '' === $fingerprint || ! preg_match( '/^[A-Za-z0-9\-_]{8,512}$/', $fingerprint ) ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid fingerprint.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			if ( ! $fingerprint ) {
				return new \WP_Error( 'invalid_request', 'Fingerprint param not exist.', array( 'status' => 400 ) );
			}

			$credential = Source_Repository::find_one_by_credential_id( $fingerprint );

			if ( ! $credential ) {
				return new \WP_Error( 'not_found', 'Fingerprint not found.', array( 'status' => 404 ) );
			}

			try {
				Source_Repository::delete_credential_source( $fingerprint, $current_user );
			} catch ( \Exception $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_revoke_action error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			return rest_ensure_response(
				array(
					'status'  => 'success',
					'message' => __( 'Successfully revoked.', 'wp-2fa' ),
				)
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
		public static function register_enable_action( \WP_REST_Request $request ) {
			$data = $request->get_json_params();

			if ( ! $data ) {
				return new \WP_Error( 'invalid_request', 'Invalid request.', array( 'status' => 400 ) );
			}

			// Require authentication (defense-in-depth).
			$current_user = \wp_get_current_user();
			if ( ! $current_user || 0 === (int) $current_user->ID ) {
				return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'wp-2fa' ), array( 'status' => 401 ) );
			}

			// Sanitize and validate fingerprint.
			$fingerprint = isset( $data['info']['fingerprint'] ) ? sanitize_text_field( wp_unslash( $data['info']['fingerprint'] ) ) : '';
			if ( '' === $fingerprint || ! preg_match( '/^[A-Za-z0-9\-_]{8,512}$/', $fingerprint ) ) {
				return new \WP_Error( 'invalid_request', __( 'Invalid fingerprint.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			if ( ! $fingerprint ) {
				return new \WP_Error( 'invalid_request', 'Fingerprint param not exist.', array( 'status' => 400 ) );
			}

			$credential = Source_Repository::find_one_by_credential_id( $fingerprint );

			if ( ! $credential ) {
				return new \WP_Error( 'not_found', 'Fingerprint not found.', array( 'status' => 404 ) );
			}

			try {
				$user_id = isset( $data['info']['user_id'] ) ? absint( $data['info']['user_id'] ) : 0;

				$user = \get_user_by( 'ID', $user_id );

				if ( ! $user ) {
					return new \WP_Error( 'invalid_request', 'Wrong user.', array( 'status' => 400 ) );
				}

				// Allow the user themselves or an administrator with edit_user capability.
				$current = \wp_get_current_user();
				if ( $current->ID !== $user->ID && ! \current_user_can( 'edit_user', $user->ID ) ) {
					return new \WP_Error( 'forbidden', __( 'Insufficient permissions.', 'wp-2fa' ), array( 'status' => 403 ) );
				}

				$meta_key = Source_Repository::PASSKEYS_META . $fingerprint;

				$stored_meta = (string) \get_user_meta( $user->ID, $meta_key, true );
				if ( '' === $stored_meta ) {
					return new \WP_Error( 'not_found', 'Fingerprint not found for current user.', array( 'status' => 404 ) );
				}

				$user_data = \json_decode( $stored_meta, true, 512, JSON_THROW_ON_ERROR );

				// Update the meta value.
				if ( isset( $user_data['extra']['enabled'] ) ) {
					$user_data['extra']['enabled'] = ! (bool) $user_data['extra']['enabled'];
				} else {
					$user_data['extra']['enabled'] = false;
				}
				$public_key_json = addcslashes( \wp_json_encode( $user_data, JSON_UNESCAPED_SLASHES ), '\\' );
				\update_user_meta( $user->ID, $meta_key, $public_key_json );
			} catch ( \Exception $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side security logging only.
					\error_log( sprintf( '[WP-2FA] register_enable_action error: %s', $error->getMessage() ) );
				}
				return new \WP_Error( 'invalid_request', __( 'Invalid request.', 'wp-2fa' ), array( 'status' => 400 ) );
			}

			return rest_ensure_response(
				array(
					'status'  => 'success',
					'message' => __( 'Successfully enabled/disabled.', 'wp-2fa' ),
				)
			);
		}
	}
}
