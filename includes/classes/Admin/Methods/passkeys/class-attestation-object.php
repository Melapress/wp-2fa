<?php
/**
 * Responsible for the proper attestation object extracting
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Methods\Passkeys;

use WP2FA\Passkeys\Format\Tpm;
use WP2FA\Passkeys\Format\U2f;
use WP2FA\Passkeys\Format\None;
use WP2FA\Passkeys\Format\Apple;
use WP2FA\Passkeys\Format\Packed;
use WP2FA\Passkeys\Format\Android_Key;
use WP2FA\Methods\Passkeys\Cbor_Decoder;
use WP2FA\Passkeys\Format\Android_Safety_Net;
use WP2FA\Admin\Methods\passkeys\Authenticator_Data;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Methods\Passkeys\Attestation_Object' ) ) {

	/**
	 * Attestation object class
	 *
	 * @since 3.0.0
	 */
	class Attestation_Object {

		/**
		 * Data passed from the Authenticator.
		 *
		 * @var Authenticator_Data
		 *
		 * @since 3.0.0
		 */
		private $authenticator_data;

		/**
		 * Format of the authenticator
		 *
		 * @var Format_Base
		 *
		 * @since 3.0.0
		 */
		private $attestation_format;

		/**
		 * Format name
		 *
		 * @var string
		 *
		 * @since 3.0.0
		 */
		private $attestation_format_name;

		/**
		 * Default constructor
		 *
		 * @param string $binary - The binary string.
		 * @param array  $allowed_formats - Allowed formats.
		 *
		 * @since 3.0.0
		 *
		 * @throws Web_Authn_Exception - when format is invalid.
		 */
		public function __construct( $binary, $allowed_formats ) {
			$enc = Cbor_Decoder::decode( $binary );
			// validation.
			if ( ! \is_array( $enc ) || ! \array_key_exists( 'fmt', $enc ) || ! is_string( $enc['fmt'] ) ) {
				throw new Web_Authn_Exception( 'invalid attestation format', Web_Authn_Exception::INVALID_DATA );
			}

			if ( ! \array_key_exists( 'attStmt', $enc ) || ! \is_array( $enc['attStmt'] ) ) {
				throw new Web_Authn_Exception( 'invalid attestation format (attStmt not available)', Web_Authn_Exception::INVALID_DATA );
			}

			if ( ! \array_key_exists( 'authData', $enc ) || ! \is_object( $enc['authData'] ) || ! ( $enc['authData'] instanceof Byte_Buffer ) ) {
				throw new Web_Authn_Exception( 'invalid attestation format (authData not available)', Web_Authn_Exception::INVALID_DATA );
			}

			$this->authenticator_data      = new Authenticator_Data( $enc['authData']->getBinaryString() );
			$this->attestation_format_name = $enc['fmt'];

			// Format ok?
			if ( ! in_array( $this->attestation_format_name, $allowed_formats ) ) {
				throw new Web_Authn_Exception( 'invalid atttestation format: ' . $this->attestation_format_name, Web_Authn_Exception::INVALID_DATA );
			}

			switch ( $this->attestation_format_name ) {
				case 'android-key':
					$this->attestation_format = new Android_Key( $enc, $this->authenticator_data );
					break;
				case 'android-safetynet':
					$this->attestation_format = new Android_Safety_Net( $enc, $this->authenticator_data );
					break;
				case 'apple':
					$this->attestation_format = new Apple( $enc, $this->authenticator_data );
					break;
				case 'fido-u2f':
					$this->attestation_format = new U2f( $enc, $this->authenticator_data );
					break;
				case 'none':
					$this->attestation_format = new None( $enc, $this->authenticator_data );
					break;
				case 'packed':
					$this->attestation_format = new Packed( $enc, $this->authenticator_data );
					break;
				case 'tpm':
					$this->attestation_format = new Tpm( $enc, $this->authenticator_data );
					break;
				default:
					throw new Web_Authn_Exception( 'invalid attestation format: ' . $enc['fmt'], Web_Authn_Exception::INVALID_DATA );
			}
		}

		/**
		 * Returns the attestation format name
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function get_attestation_format_name() {
			return $this->attestation_format_name;
		}

		/**
		 * Returns the attestation format class
		 *
		 * @return Format\Format_Base
		 *
		 * @since 3.0.0
		 */
		public function get_attestation_format() {
			return $this->attestation_format;
		}

		/**
		 * Returns the attestation public key in PEM format
		 *
		 * @return Authenticator_Data
		 *
		 * @since 3.0.0
		 */
		public function get_authenticator_data() {
			return $this->authenticator_data;
		}

		/**
		 * Returns the certificate chain as PEM
		 *
		 * @return string|null
		 *
		 * @since 3.0.0
		 */
		public function get_certificate_chain() {
			return $this->attestation_format->get_certificate_chain();
		}

		/**
		 * Returns the certificate issuer as string
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function get_certificate_issuer() {
			$pem    = $this->get_certificate_pem();
			$issuer = '';
			if ( $pem ) {
				$cert_info = \openssl_x509_parse( $pem );
				if ( \is_array( $cert_info ) && \array_key_exists( 'issuer', $cert_info ) && \is_array( $cert_info['issuer'] ) ) {

					$cn = $cert_info['issuer']['CN'] ?? '';
					$o  = $cert_info['issuer']['O'] ?? '';
					$ou = $cert_info['issuer']['OU'] ?? '';

					if ( $cn ) {
						$issuer .= $cn;
					}
					if ( $issuer && ( $o || $ou ) ) {
						$issuer .= ' (' . trim( $o . ' ' . $ou ) . ')';
					} else {
						$issuer .= trim( $o . ' ' . $ou );
					}
				}
			}

			return $issuer;
		}

		/**
		 * Returns the certificate subject as string
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function get_certificate_subject() {
			$pem     = $this->get_certificate_pem();
			$subject = '';
			if ( $pem ) {
				$cert_info = \openssl_x509_parse( $pem );
				if ( \is_array( $cert_info ) && \array_key_exists( 'subject', $cert_info ) && \is_array( $cert_info['subject'] ) ) {

					$cn = $cert_info['subject']['CN'] ?? '';
					$o  = $cert_info['subject']['O'] ?? '';
					$ou = $cert_info['subject']['OU'] ?? '';

					if ( $cn ) {
						$subject .= $cn;
					}
					if ( $subject && ( $o || $ou ) ) {
						$subject .= ' (' . trim( $o . ' ' . $ou ) . ')';
					} else {
						$subject .= trim( $o . ' ' . $ou );
					}
				}
			}

			return $subject;
		}

		/**
		 * Returns the key certificate in PEM format
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function get_certificate_pem() {
			return $this->attestation_format->get_certificate_pem();
		}

		/**
		 * Checks validity of the signature
		 *
		 * @param string $client_data_hash - The data from the client.
		 *
		 * @return bool
		 *
		 * @since 3.0.0
		 */
		public function validate_attestation( $client_data_hash ) {
			return $this->attestation_format->validate_attestation( $client_data_hash );
		}

		/**
		 * Validates the certificate against root certificates
		 *
		 * @param array $root_cas - Root certificate.
		 *
		 * @return boolean
		 *
		 * @since 3.0.0
		 */
		public function validate_root_certificate( $root_cas ) {
			return $this->attestation_format->validate_root_certificate( $root_cas );
		}

		/**
		 * Checks if the RpId-Hash is valid
		 *
		 * @param string $rp_id_hash - Hash string.
		 *
		 * @return bool
		 *
		 * @since 3.0.0
		 */
		public function validate_rp_id_hash( $rp_id_hash ) {
			return $rp_id_hash === $this->authenticator_data->get_rp_id_hash();
		}
	}
}
