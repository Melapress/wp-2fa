<?php
/**
 * Responsible for the Passkeys extension plugin settings
 *
 * @package    wp2fa
 * @subpackage passkeys
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Methods\Passkeys;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

use WP2FA\Methods\Passkeys\Byte_Buffer;
use WP2FA\Passkeys\Format\Android_Safety_Net;
use WP2FA\Methods\Passkeys\Attestation_Object;
use WP2FA\Admin\Methods\passkeys\Authenticator_Data;
/**
 * Passkeys WebAuthn
 */
if ( ! class_exists( '\WP2FA\Methods\Passkeys\Web_Authn' ) ) {

	/**
	 * Web Authn main class
	 *
	 * @since 3.0.0
	 */
	class Web_Authn {

		public const AUTHENTICATOR_TRANSPORT_USB      = 'usb';
		public const AUTHENTICATOR_TRANSPORT_NFC      = 'nfc';
		public const AUTHENTICATOR_TRANSPORT_BLE      = 'ble';
		public const AUTHENTICATOR_TRANSPORT_HYBRID   = 'hybrid';
		public const AUTHENTICATOR_TRANSPORT_INTERNAL = 'internal';

		public const TRANSPORT_ARRAY = array(
			self::AUTHENTICATOR_TRANSPORT_USB      => self::AUTHENTICATOR_TRANSPORT_USB,
			self::AUTHENTICATOR_TRANSPORT_NFC      => self::AUTHENTICATOR_TRANSPORT_NFC,
			self::AUTHENTICATOR_TRANSPORT_BLE      => self::AUTHENTICATOR_TRANSPORT_BLE,
			self::AUTHENTICATOR_TRANSPORT_HYBRID   => self::AUTHENTICATOR_TRANSPORT_HYBRID,
			self::AUTHENTICATOR_TRANSPORT_INTERNAL => self::AUTHENTICATOR_TRANSPORT_INTERNAL,
		);

		/**
		 * Relying Party parameters
		 *
		 * @var string $rp_name Relying party name.
		 */
		private $rp_name;
		private $rp_id;
		private $rp_id_hash;
		private $challenge;
		private $signature_counter;
		private $ca_files;
		private $formats;
		private $android_key_hashes;

		/**
		 * Default constructor
		 *
		 * @param string     $rp_name                 Relying party name.
		 * @param string     $rp_id                   Relying party ID.
		 * @param array|null $allowed_formats         Allowed formats.
		 * @param bool       $use_base_64_url_encoding Whether to use base64 URL encoding.
		 *
		 * @throws Web_Authn_Exception If OpenSSL is not installed or SHA256 is not supported.
		 *
		 * @since 3.0.0
		 */
		public function __construct( $rp_name, $rp_id, $allowed_formats = null, $use_base_64_url_encoding = false ) {
			$this->rp_name                         = $rp_name;
			$this->rp_id                           = $rp_id;
			$this->rp_id_hash                      = \hash( 'sha256', $rp_id, true );
			Byte_Buffer::$use_base_64_url_encoding = ! ! $use_base_64_url_encoding;
			$supported_formats                     = array( 'android-key', 'android-safetynet', 'apple', 'fido-u2f', 'none', 'packed', 'tpm' );

			if ( ! \function_exists( '\openssl_open' ) ) {
				throw new Web_Authn_Exception( 'OpenSSL-Module not installed' );
			}

			if ( ! \in_array( 'SHA256', \array_map( '\strtoupper', \openssl_get_md_methods() ) ) ) {
				throw new Web_Authn_Exception( 'SHA256 not supported by this openssl installation.' );
			}

			// default: all format.
			if ( ! is_array( $allowed_formats ) ) {
				$allowed_formats = $supported_formats;
			}
			$this->formats = $allowed_formats;

			// validate formats.
			$invalid_formats = \array_diff( $this->formats, $supported_formats );
			if ( ! $this->formats || $invalid_formats ) {
				throw new Web_Authn_Exception( 'invalid formats on construct: ' . implode( ', ', $invalid_formats ) );
			}
		}

		/**
		 * Add root certificates from path
		 *
		 * @param string     $path                 Path to add certificates from.
		 * @param array|null $cert_file_extensions Certificate file extensions to look for.
		 *
		 * @return void
		 */
		public function add_root_certificates( $path, $cert_file_extensions = null ) {
			if ( ! \is_array( $this->ca_files ) ) {
				$this->ca_files = array();
			}
			if ( null === $cert_file_extensions ) {
				$cert_file_extensions = array( 'pem', 'crt', 'cer', 'der' );
			}
			$path = \rtrim( \trim( $path ), '\\/' );
			if ( \is_dir( $path ) ) {
				foreach ( \scandir( $path ) as $ca ) {
					if ( \is_file( $path . DIRECTORY_SEPARATOR . $ca ) && \in_array( \strtolower( \pathinfo( $ca, PATHINFO_EXTENSION ) ), $cert_file_extensions ) ) {
						$this->add_root_certificates( $path . DIRECTORY_SEPARATOR . $ca );
					}
				}
			} elseif ( \is_file( $path ) && ! \in_array( \realpath( $path ), $this->ca_files ) ) {
				$this->ca_files[] = \realpath( $path );
			}
		}

		/**
		 * Add Android key hashes
		 *
		 * @param array $hashes Hashes to add.
		 *
		 * @return void
		 */
		public function add_android_key_hashes( $hashes ) {
			if ( ! \is_array( $this->android_key_hashes ) ) {
				$this->android_key_hashes = array();
			}

			foreach ( $hashes as $hash ) {
				if ( is_string( $hash ) ) {
					$this->android_key_hashes[] = $hash;
				}
			}
		}

		/**
		 * Create a new challenge
		 *
		 * @param int $length Length of the challenge.
		 *
		 * @return Byte_Buffer Challenge.
		 */
		public function get_challenge() {
			return $this->challenge;
		}

		/**
		 * Get create() arguments
		 *
		 * @param string      $user_id                 User ID.
		 * @param string      $user_name               User name.
		 * @param string      $user_display_name       User display name.
		 * @param int         $timeout                 Timeout in seconds.
		 * @param bool|string $require_resident_key    Require resident key.
		 * @param bool|string $require_user_verification Require user verification.
		 * @param bool|null   $cross_platform_attachment Cross-platform attachment.
		 * @param array       $exclude_credential_ids  Exclude credential IDs.
		 *
		 * @return \stdClass Create arguments.
		 */
		public function get_create_args( $user_id, $user_name, $user_display_name, $timeout = 20, $require_resident_key = false, $require_user_verification = false, $cross_platform_attachment = null, $exclude_credential_ids = array() ) {
			$args             = new \stdClass();
			$args->public_key = new \stdClass();

			// relying party.
			$args->public_key->rp       = new \stdClass();
			$args->public_key->rp->name = $this->rp_name;
			$args->public_key->rp->id   = $this->rp_id;

			$args->public_key->authenticatorSelection                   = new \stdClass();
			$args->public_key->authenticatorSelection->userVerification = 'preferred';

			// validate User Verification Requirement.
			if ( \is_bool( $require_user_verification ) ) {
				$args->public_key->authenticatorSelection->userVerification = $require_user_verification ? 'required' : 'preferred';

			} elseif ( \is_string( $require_user_verification ) && \in_array( \strtolower( $require_user_verification ), array( 'required', 'preferred', 'discouraged' ) ) ) {
				$args->public_key->authenticatorSelection->userVerification = \strtolower( $require_user_verification );
			}

			// validate Resident Key Requirement.
			if ( \is_bool( $require_resident_key ) && $require_resident_key ) {
				$args->public_key->authenticatorSelection->require_resident_key = true;
				$args->public_key->authenticatorSelection->residentKey          = 'required';

			} elseif ( \is_string( $require_resident_key ) && \in_array( \strtolower( $require_resident_key ), array( 'required', 'preferred', 'discouraged' ) ) ) {
				$require_resident_key                                  = \strtolower( $require_resident_key );
				$args->public_key->authenticatorSelection->residentKey = $require_resident_key;
				$args->public_key->authenticatorSelection->require_resident_key = 'required' === $require_resident_key;
			}

			// filte authenticators attached with the specified authenticator attachment modality.
			if ( \is_bool( $cross_platform_attachment ) ) {
				$args->public_key->authenticatorSelection->authenticatorAttachment = $cross_platform_attachment ? 'cross-platform' : 'platform';
			}

			// user.
			$args->public_key->user              = new \stdClass();
			$args->public_key->user->id          = new Byte_Buffer( $user_id ); // binary.
			$args->public_key->user->name        = $user_name;
			$args->public_key->user->displayName = $user_display_name;

			// supported algorithms.
			$args->public_key->pubKeyCredParams = array();

			if ( function_exists( 'sodium_crypto_sign_verify_detached' ) || \in_array( 'ed25519', \openssl_get_curve_names(), true ) ) {
				$tmp                                  = new \stdClass();
				$tmp->type                            = 'public-key';
				$tmp->alg                             = -8; // EdDSA.
				$args->public_key->pubKeyCredParams[] = $tmp;
				unset( $tmp );
			}

			if ( \in_array( 'prime256v1', \openssl_get_curve_names(), true ) ) {
				$tmp                                  = new \stdClass();
				$tmp->type                            = 'public-key';
				$tmp->alg                             = -7; // ES256.
				$args->public_key->pubKeyCredParams[] = $tmp;
				unset( $tmp );
			}

			$tmp                                  = new \stdClass();
			$tmp->type                            = 'public-key';
			$tmp->alg                             = -257; // RS256.
			$args->public_key->pubKeyCredParams[] = $tmp;
			unset( $tmp );

			// if there are root certificates added, we need direct attestation to validate
			// against the root certificate. If there are no root-certificates added,
			// anonymization ca are also accepted, because we can't validate the root anyway.
			$attestation = 'indirect';
			if ( \is_array( $this->ca_files ) ) {
				$attestation = 'direct';
			}

			$args->public_key->attestation      = \count( $this->formats ) === 1 && \in_array( 'none', $this->formats ) ? 'none' : $attestation;
			$args->public_key->extensions       = new \stdClass();
			$args->public_key->extensions->exts = true;
			$args->public_key->timeout          = $timeout * 1000; // microseconds.
			$args->public_key->challenge        = $this->_create_challenge(); // binary.

			// Prevent re-registration by specifying existing credentials.
			$args->public_key->excludeCredentials = array();

			if ( is_array( $exclude_credential_ids ) ) {
				foreach ( $exclude_credential_ids as $id ) {
					$tmp                                    = new \stdClass();
					$tmp->id                                = $id instanceof Byte_Buffer ? $id : new Byte_Buffer( $id );  // binary.
					$tmp->type                              = 'public-key';
					$tmp->transports                        = self::AUTHENTICATOR_TRANSPORT_INTERNAL;
					$args->public_key->excludeCredentials[] = $tmp;
					unset( $tmp );
				}
			}

			return $args;
		}

		/**
		 * Get get() arguments
		 *
		 * @param array       $credential_ids               Credential IDs.
		 * @param int         $timeout                      Timeout in seconds.
		 * @param bool        $allow_usb                    Allow USB transport.
		 * @param bool        $allow_nfc                    Allow NFC transport.
		 * @param bool        $allow_ble                    Allow BLE transport.
		 * @param bool        $allow_hybrid                 Allow Hybrid transport.
		 * @param bool        $allow_internal               Allow Internal transport.
		 * @param bool|string $require_user_verification    Require user verification.
		 *
		 * @return \stdClass Get arguments.
		 */
		public function get_get_args( $credential_ids = array(), $timeout = 20, $allow_usb = true, $allow_nfc = true, $allow_ble = true, $allow_hybrid = true, $allow_internal = true, $require_user_verification = false ) {

			// validate User Verification Requirement.
			if ( \is_bool( $require_user_verification ) ) {
				$require_user_verification = $require_user_verification ? 'required' : 'preferred';
			} elseif ( \is_string( $require_user_verification ) && \in_array( \strtolower( $require_user_verification ), array( 'required', 'preferred', 'discouraged' ) ) ) {
				$require_user_verification = \strtolower( $require_user_verification );
			} else {
				$require_user_verification = 'preferred';
			}

			$args                               = new \stdClass();
			$args->public_key                   = new \stdClass();
			$args->public_key->timeout          = $timeout * 1000; // microseconds.
			$args->public_key->challenge        = $this->_create_challenge();  // binary.
			$args->public_key->userVerification = $require_user_verification;
			$args->public_key->rp_id            = $this->rp_id;

			if ( \is_array( $credential_ids ) && \count( $credential_ids ) > 0 ) {
				$args->public_key->allowCredentials = array();

				foreach ( $credential_ids as $id ) {
					$tmp             = new \stdClass();
					$tmp->id         = $id instanceof Byte_Buffer ? $id : new Byte_Buffer( $id );  // binary.
					$tmp->transports = array();

					if ( $allow_usb ) {
						$tmp->transports[] = self::AUTHENTICATOR_TRANSPORT_USB;
					}
					if ( $allow_nfc ) {
						$tmp->transports[] = self::AUTHENTICATOR_TRANSPORT_NFC;
					}
					if ( $allow_ble ) {
						$tmp->transports[] = self::AUTHENTICATOR_TRANSPORT_BLE;
					}
					if ( $allow_hybrid ) {
						$tmp->transports[] = self::AUTHENTICATOR_TRANSPORT_HYBRID;
					}
					if ( $allow_internal ) {
						$tmp->transports[] = self::AUTHENTICATOR_TRANSPORT_INTERNAL;
					}

					$tmp->type                            = 'public-key';
					$args->public_key->allowCredentials[] = $tmp;
					unset( $tmp );
				}
			}

			return $args;
		}

		/**
		 * Get signature counter
		 *
		 * @return int|null Signature counter.
		 */
		public function get_signature_counter() {
			return \is_int( $this->signature_counter ) ? $this->signature_counter : null;
		}

		/**
		 * Process create response
		 *
		 * @param string             $client_data_json           Client data JSON.
		 * @param string             $attestation_object         Attestation object.
		 * @param Byte_Buffer|string $challenge                 Challenge.
		 * @param bool               $require_user_verification  Require user verification.
		 * @param bool               $require_user_present       Require user present.
		 * @param bool               $fail_if_root_mismatch      Fail if root certificate mismatch.
		 * @param bool               $require_cts_profile_match  Require CTS profile match.
		 *
		 * @return array Credential data.
		 *
		 * @throws Web_Authn_Exception If validation fails.
		 *
		 * @since 3.0.0
		 */
		public function process_create( $client_data_json, $attestation_object, $challenge, $require_user_verification = false, $require_user_present = true, $fail_if_root_mismatch = true, $require_cts_profile_match = true ) {
			$client_data_hash = \hash( 'sha256', $client_data_json, true );
			$client_data      = \json_decode( $client_data_json );
			$challenge        = $challenge instanceof Byte_Buffer ? $challenge : new Byte_Buffer( $challenge );

			// security: https://www.w3.org/TR/Web_Authn/#registering-a-new-credential .

			// 2. Let C, the client data claimed as collected during the credential creation,
			// be the result of running an implementation-specific JSON parser on JSONtext.
			if ( ! \is_object( $client_data ) ) {
				throw new Web_Authn_Exception( 'invalid client data', Web_Authn_Exception::INVALID_DATA );
			}

			// 3. Verify that the value of C.type is Web_Authn.create.
			if ( ! \property_exists( $client_data, 'type' ) || 'webauthn.create' !== $client_data->type ) {
				throw new Web_Authn_Exception( 'invalid type', Web_Authn_Exception::INVALID_TYPE );
			}

			// 4. Verify that the value of C.challenge matches the challenge that was sent to the authenticator in the create() call.
			if ( ! \property_exists( $client_data, 'challenge' ) || Byte_Buffer::fromBase64Url( $client_data->challenge )->getBinaryString() !== $challenge->getBinaryString() ) {
				throw new Web_Authn_Exception( 'invalid challenge', Web_Authn_Exception::INVALID_CHALLENGE );
			}

			// 5. Verify that the value of C.origin matches the Relying Party's origin.
			if ( ! \property_exists( $client_data, 'origin' ) || ! $this->_check_origin( $client_data->origin ) ) {
				throw new Web_Authn_Exception( 'invalid origin', Web_Authn_Exception::INVALID_ORIGIN );
			}

			// Attestation.
			$attestation_object = new Attestation_Object( $attestation_object, $this->formats );

			// 9. Verify that the RP ID hash in authData is indeed the SHA-256 hash of the RP ID expected by the RP.
			if ( ! $attestation_object->validate_rp_id_hash( $this->rp_id_hash ) ) {
				throw new Web_Authn_Exception( 'invalid rpId hash', Web_Authn_Exception::INVALID_RELYING_PARTY );
			}

			// 14. Verify that attStmt is a correct attestation statement, conveying a valid attestation signature
			if ( ! $attestation_object->validate_attestation( $client_data_hash ) ) {
				throw new Web_Authn_Exception( 'invalid certificate signature', Web_Authn_Exception::INVALID_SIGNATURE );
			}

			// Android-SafetyNet: if required, check for Compatibility Testing Suite (CTS).
			if ( $require_cts_profile_match && $attestation_object->get_attestation_format() instanceof Android_Safety_Net ) {
				if ( ! $attestation_object->get_attestation_format()->ctsProfileMatch() ) {
					throw new Web_Authn_Exception( 'invalid ctsProfileMatch: device is not approved as a Google-certified Android device.', Web_Authn_Exception::ANDROID_NOT_TRUSTED );
				}
			}

			// 15. If validation is successful, obtain a list of acceptable trust anchors
			$root_valid = is_array( $this->ca_files ) ? $attestation_object->validate_root_certificate( $this->ca_files ) : null;
			if ( $fail_if_root_mismatch && is_array( $this->ca_files ) && ! $root_valid ) {
				throw new Web_Authn_Exception( 'invalid root certificate', Web_Authn_Exception::CERTIFICATE_NOT_TRUSTED );
			}

			// 10. Verify that the User Present bit of the flags in authData is set.
			$user_present = $attestation_object->get_authenticator_data()->get_user_present();
			if ( $require_user_present && ! $user_present ) {
				throw new Web_Authn_Exception( 'user not present during authentication', Web_Authn_Exception::USER_PRESENT );
			}

			// 11. If user verification is required for this registration, verify that the User Verified bit of the flags in authData is set.
			$user_verified = $attestation_object->get_authenticator_data()->get_user_verified();
			if ( $require_user_verification && ! $user_verified ) {
				throw new Web_Authn_Exception( 'user not verified during authentication', Web_Authn_Exception::USER_VERIFICATED );
			}

			$sign_count = $attestation_object->get_authenticator_data()->get_sign_count();
			if ( $sign_count > 0 ) {
				$this->signature_counter = $sign_count;
			}

			// prepare data to store for future logins.
			$data                        = new \stdClass();
			$data->rp_id                 = $this->rp_id;
			$data->attestation_format    = $attestation_object->get_attestation_format_name();
			$data->credential_id         = $attestation_object->get_authenticator_data()->get_credential_id();
			$data->credential_public_key = $attestation_object->get_authenticator_data()->get_public_key_pem();
			$data->certificate_chain     = $attestation_object->get_certificate_chain();
			$data->certificate           = $attestation_object->get_certificate_pem();
			$data->certificate_isuer     = $attestation_object->get_certificate_issuer();
			$data->certificate_subject   = $attestation_object->get_certificate_subject();
			$data->signature_counter     = $this->signature_counter;
			$data->aaguid                = $attestation_object->get_authenticator_data()->get_aaguid();
			$data->root_valid            = $root_valid;
			$data->user_present          = $user_present;
			$data->user_verified         = $user_verified;
			$data->is_backup_eligible    = $attestation_object->get_authenticator_data()->get_is_backup_eligible();
			$data->is_backed_up          = $attestation_object->get_authenticator_data()->get_is_backup();

			return $data;
		}

		/**
		 * Process a get() response.
		 *
		 * @param string      $client_data_json          - The client data JSON.
		 * @param string      $authenticator_data        - The authenticator data.
		 * @param string      $signature                 - The signature.
		 * @param string      $credential_public_key     - The credential public key.
		 * @param Byte_Buffer $challenge                 - The challenge.
		 * @param int|null    $prev_signature_cnt        - The previous signature counter.
		 * @param bool        $require_user_verification - Whether user verification is required.
		 * @param bool        $require_user_present      - Whether user presence is required.
		 *
		 * @return array The result of the get() process.
		 *
		 * @throws Web_Authn_Exception If validation fails.
		 */
		public function process_get( $client_data_json, $authenticator_data, $signature, $credential_public_key, $challenge, $prev_signature_cnt = null, $require_user_verification = false, $require_user_present = true ) {
			$authenticator_obj = new Authenticator_Data( $authenticator_data );
			$client_data_hash  = \hash( 'sha256', $client_data_json, true );
			$client_data       = \json_decode( $client_data_json );
			$challenge         = $challenge instanceof Byte_Buffer ? $challenge : new Byte_Buffer( $challenge );

			// https://www.w3.org/TR/Web_Authn/#verifying-assertion .

			// 1. If the allowCredentials option was given when this authentication ceremony was initiated,
			// verify that credential.id identifies one of the public key credentials that were listed in allowCredentials.
			// -> TO BE VERIFIED BY IMPLEMENTATION

			// 2. If credential.response.userHandle is present, verify that the user identified
			// by this value is the owner of the public key credential identified by credential.id.
			// -> TO BE VERIFIED BY IMPLEMENTATION

			// 3. Using credential’s id attribute (or the corresponding rawId, if base64url encoding is
			// inappropriate for your use case), look up the corresponding credential public key.
			// -> TO BE LOOKED UP BY IMPLEMENTATION

			// 5. Let JSONtext be the result of running UTF-8 decode on the value of cData.
			if ( ! \is_object( $client_data ) ) {
				throw new Web_Authn_Exception( 'invalid client data', Web_Authn_Exception::INVALID_DATA );
			}

			// 7. Verify that the value of C.type is the string Web_Authn.get.
			if ( ! \property_exists( $client_data, 'type' ) || 'webauthn.get' !== $client_data->type ) {
				throw new Web_Authn_Exception( 'invalid type', Web_Authn_Exception::INVALID_TYPE );
			}

			// 8. Verify that the value of C.challenge matches the challenge that was sent to the
			// authenticator in the PublicKeyCredentialRequestOptions passed to the get() call.
			if ( ! \property_exists( $client_data, 'challenge' ) || Byte_Buffer::fromBase64Url( $client_data->challenge )->getBinaryString() !== $challenge->getBinaryString() ) {
				throw new Web_Authn_Exception( 'invalid challenge', Web_Authn_Exception::INVALID_CHALLENGE );
			}

			// 9. Verify that the value of C.origin matches the Relying Party's origin.
			if ( ! \property_exists( $client_data, 'origin' ) || ! $this->_check_origin( $client_data->origin ) ) {
				throw new Web_Authn_Exception( 'invalid origin', Web_Authn_Exception::INVALID_ORIGIN );
			}

			// 11. Verify that the rpIdHash in authData is the SHA-256 hash of the RP ID expected by the Relying Party.
			if ( $authenticator_obj->get_rp_id_hash() !== $this->rp_id_hash ) {
				throw new Web_Authn_Exception( 'invalid rpId hash', Web_Authn_Exception::INVALID_RELYING_PARTY );
			}

			// 12. Verify that the User Present bit of the flags in authData is set
			if ( $require_user_present && ! $authenticator_obj->get_user_present() ) {
				throw new Web_Authn_Exception( 'user not present during authentication', Web_Authn_Exception::USER_PRESENT );
			}

			// 13. If user verification is required for this assertion, verify that the User Verified bit of the flags in authData is set.
			if ( $require_user_verification && ! $authenticator_obj->get_user_verified() ) {
				throw new Web_Authn_Exception( 'user not verificated during authentication', Web_Authn_Exception::USER_VERIFICATED );
			}

			// 14. Verify the values of the client extension outputs
			// (extensions not implemented)

			// 16. Using the credential public key looked up in step 3, verify that sig is a valid signature
			// over the binary concatenation of authData and hash.
			$data_to_verify  = '';
			$data_to_verify .= $authenticator_data;
			$data_to_verify .= $client_data_hash;

			if ( ! $this->_verify_signature( $data_to_verify, $signature, $credential_public_key ) ) {
				throw new Web_Authn_Exception( 'invalid signature', Web_Authn_Exception::INVALID_SIGNATURE );
			}

			$signature_counter = $authenticator_obj->get_sign_count();
			if ( 0 !== $signature_counter ) {
				$this->signature_counter = $signature_counter;
			}

			// 17. If either of the signature counter value authData.signCount or
			// previous signature count is nonzero, and if authData.signCount
			// less than or equal to previous signature count, it's a signal
			// that the authenticator may be cloned
			if ( null !== $prev_signature_cnt ) {
				if ( 0 !== $signature_counter || 0 !== $prev_signature_cnt ) {
					if ( $prev_signature_cnt >= $signature_counter ) {
						throw new Web_Authn_Exception( 'signature counter not valid', Web_Authn_Exception::SIGNATURE_COUNTER );
					}
				}
			}

			return true;
		}

		/**
		 * Check origin
		 *
		 * @param string $origin Origin.
		 *
		 * @return bool True if origin is valid, false otherwise.
		 */
		private function _check_origin( $origin ) {
			if ( str_starts_with( $origin, 'android:apk-key-hash:' ) ) {
				return $this->_check_android_key_hashes( $origin );
			}

			// https://www.w3.org/TR/Web_Authn/#rp-id .

			// The origin's scheme must be https.
			if ( 'localhost' !== $this->rp_id && 'https' !== \wp_parse_url( $origin, PHP_URL_SCHEME ) ) {
				return false;
			}

			// extract host from origin.
			$host = \wp_parse_url( $origin, PHP_URL_HOST );
			$host = \trim( $host, '.' );

			// The RP ID must be equal to the origin's effective domain, or a registrable
			// domain suffix of the origin's effective domain.
			return \preg_match( '/' . \preg_quote( $this->rp_id ) . '$/i', $host ) === 1;
		}

		/**
		 * Check Android key hashes
		 *
		 * @param string $origin Origin.
		 *
		 * @return bool True if key hash is valid, false otherwise.
		 */
		private function _check_android_key_hashes( $origin ) {
			$parts = explode( 'android:apk-key-hash:', $origin );
			if ( count( $parts ) !== 2 ) {
				return false;
			}
			return in_array( $parts[1], $this->android_key_hashes, true );
		}

		/**
		 * Create challenge
		 *
		 * @param int $length Length of the challenge.
		 *
		 * @return Byte_Buffer Challenge.
		 */
		private function _create_challenge( $length = 32 ) {
			if ( ! $this->challenge ) {
				$this->challenge = Byte_Buffer::randomBuffer( $length );
			}
			return $this->challenge;
		}

		/**
		 * Verify signature
		 *
		 * @param string $data_to_verify Data to verify.
		 * @param string $signature Signature.
		 * @param string $credential_public_key Credential public key.
		 *
		 * @return bool True if signature is valid, false otherwise.
		 *
		 * @throws Web_Authn_Exception If public key is invalid.
		 */
		private function _verify_signature( $data_to_verify, $signature, $credential_public_key ) {

			// Use Sodium to verify EdDSA 25519 as its not yet supported by openssl.
			if ( \function_exists( 'sodium_crypto_sign_verify_detached' ) && ! \in_array( 'ed25519', \openssl_get_curve_names(), true ) ) {
				$pk_parts = array();
				if ( \preg_match( '/BEGIN PUBLIC KEY\-+(?:\s|\n|\r)+([^\-]+)(?:\s|\n|\r)*\-+END PUBLIC KEY/i', $credential_public_key, $pk_parts ) ) {
					$raw_pk = \base64_decode( $pk_parts[1] );

					// 30        = der sequence
					// 2a        = length 42 byte
					// 30        = der sequence
					// 05        = lenght 5 byte
					// 06        = der OID
					// 03        = OID length 3 byte
					// 2b 65 70  = OID 1.3.101.112 curveEd25519 (EdDSA 25519 signature algorithm)
					// 03        = der bit string
					// 21        = length 33 byte
					// 00        = null padding
					// [...]     = 32 byte x-curve
					$okp_prefix = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

					if ( $raw_pk && \strlen( $raw_pk ) === 44 && \substr( $raw_pk, 0, \strlen( $okp_prefix ) ) === $okp_prefix ) {
						$public_key_x_curve = \substr( $raw_pk, \strlen( $okp_prefix ) );

						return \sodium_crypto_sign_verify_detached( $signature, $data_to_verify, $public_key_x_curve );
					}
				}
			}

			// Verify with openSSL.
			$public_key = \openssl_pkey_get_public( $credential_public_key );
			if ( false === $public_key ) {
				throw new Web_Authn_Exception( 'public key invalid', Web_Authn_Exception::INVALID_PUBLIC_KEY );
			}

			return \openssl_verify( $data_to_verify, $signature, $public_key, OPENSSL_ALGO_SHA256 ) === 1;
		}

		/**
		 * Get relying party ID
		 *
		 * @return string Relying party ID.
		 */
		public static function get_relying_party_id() {
			$site_url   = \get_option( 'siteurl' );
			$parsed_url = \wp_parse_url( $site_url );
			$domain     = $parsed_url['host'] ?? '';

			$value = $domain;

			return strval( $value );
		}

		/**
		 * Get raw credential ID from base64url encoded string
		 *
		 * @param string $credential_id Base64url encoded credential ID.
		 *
		 * @return string Raw credential ID.
		 */
		public static function get_raw_credential_id( string $credential_id ): string {
			return base64_encode( self::base64url_decode( $credential_id ) );
		}

		/**
		 * Base64url decode
		 *
		 * @param string $string Base64url encoded string.
		 *
		 * @return string Decoded string.
		 */
		public static function base64url_decode( string $string ): string {
			return base64_decode( strtr( $string, '-_', '+/' ) . str_repeat( '=', 3 - ( 3 + strlen( $string ) ) % 4 ) );
		}

		/**
		 * Convert binary AAGUID to hex format
		 *
		 * @param string|null $bin_aaguid Binary AAGUID.
		 *
		 * @return string Formatted hex AAGUID.
		 */
		public static function convert_aaguid_to_hex( ?string $bin_aaguid ) {
			$hex_aaguid = bin2hex( $bin_aaguid );

			return sprintf(
				'%s-%s-%s-%s-%s',
				substr( $hex_aaguid, 0, 8 ),
				substr( $hex_aaguid, 8, 4 ),
				substr( $hex_aaguid, 12, 4 ),
				substr( $hex_aaguid, 16, 4 ),
				substr( $hex_aaguid, 20 )
			);
		}
	}
}
