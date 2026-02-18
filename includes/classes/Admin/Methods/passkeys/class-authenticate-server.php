<?php
/**
 * Responsible for the API endpoints
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Passkeys;

use WP2FA\Methods\Passkeys\Web_Authn;

/**
 * Authentication Server.
 */
if ( ! class_exists( '\WP2FA\Passkeys\Authentication_Server' ) ) {

	/**
	 * Web Authentication Server
	 *
	 * @since 3.0.0
	 */
	class Authentication_Server {

		/**
		 * Create Attestation Request for registration.
		 *
		 * @param \WP_User    $user - Current User.
		 * @param string|null $challenge - Challenge string.
		 * @param bool        $is_usb - Is USB authenticator.
		 *
		 * @return PublicKeyCredentialCreationOptions
		 *
		 * @since 3.0.0
		 */
		public static function create_attestation_request( \WP_User $user, ?string $challenge = null, bool $is_usb = false ) {

			// $fingerprint = base64_encode( self::generate_fingerprint() );

			// $minutes = intval( 5 );

			// $date        = ( new \DateTime() )->add( new \DateInterval( 'PT' . $minutes . 'M' ) );
			// $expire_date = $date->format( 'Y-m-d H:i:s' );

			$challenge = base64_encode( random_bytes( 32 ) );

			// Store challenge in User meta.
			\update_user_meta( $user->ID, WP_2FA_PREFIX . 'passkey_challenge', $challenge );

			$user_id = (string) \get_current_user_id();

			$first_name = get_user_meta( $user_id, 'first_name', true );
			$last_name  = get_user_meta( $user_id, 'last_name', true );

			$full_name = trim( $first_name . ' ' . $last_name );

			if ( empty( $full_name ) ) {
				$full_name = get_the_author_meta( 'user_email', $user_id );
			}

			$username = trim( $full_name );

			$options = array(
				'challenge'        => $challenge,
				'rp'               => array(
					'id'   => Web_Authn::get_relying_party_id(),
					'name' => Web_Authn::get_relying_party_id(),
				),
				'user'             => array(
					'id'          => base64_encode( $user_id ),
					'name'        => $username,
					'displayName' => $username,
				),
				'pubKeyCredParams' => array(
					array(
						'type' => 'public-key',
						'alg'  => -7,
					),
					array(
						'type' => 'public-key',
						'alg'  => -257,
					),
				),
				'attestation'      => 'none',
				'extensions'       => array(
					'credProps' => true,
				),
				'timeout'          => intval( 5 ) * 60 * 1000,

			// 'authenticatorSelection' => array(
			// 'residentKey'      => 'discouraged',
			// 'requiredResidentKey' => false,
			// 'userVerification' => 'required',
			// 'authenticatorAttachment' => 'cross-platform',

			// ),
			);

			if ( $is_usb ) {
				$options['authenticatorSelection'] = array(
					'authenticatorAttachment' => 'cross-platform',
					'residentKey'             => 'discouraged',
					'userVerification'        => 'required',
				);
			} else {
				$options['authenticatorSelection'] = array(
					'authenticatorAttachment' => 'platform',
					'residentKey'             => 'required',
					'userVerification'        => 'required',
				);
			}
			// $options['authenticatorSelection'] = array(

			// 'residentKey'      => 'required',
			// 'residentKey'      => 'discouraged',
			// 'userVerification' => 'required',
			// 'authenticatorAttachment' => 'cross-platform',
			// );

			$public_key_credentials = Source_Repository::find_all_for_user( $user );

			if ( ! empty( $public_key_credentials ) ) {
				$exclude_credentials = array();

				foreach ( $public_key_credentials as $public_key_credential ) {
					$exclude_credentials[] = array(
						'type' => 'public-key',
						'id'   => $public_key_credential['credential_id'],
					);
				}

				$options['excludeCredentials'] = $exclude_credentials;
			}

			return $options;
		}

		/**
		 * Generates the finger print for authentication
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		private static function generate_fingerprint() {

			$user_agent      = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' ) );
			$ip_address      = self::get_ip_address() ?? 'unknown';
			$accept_language = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown' ) );
			$accept_encoding = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'unknown' ) );

			$fingerprint_data = $user_agent . '|' . $ip_address . '|' . $accept_language . '|' . $accept_encoding;

			$fingerprint_hash = hash( 'sha256', $fingerprint_data );

			return $fingerprint_hash;
		}

		/**
		 * Collects the ip address of the user
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public static function get_ip_address() {
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = \rest_is_ip_address( trim( current( preg_split( '/,/', \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) ) );
			} else {
				$ip = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			}

			return (string) $ip;
		}
	}
}
