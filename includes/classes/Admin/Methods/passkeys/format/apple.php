<?php
/**
 * Passkeys formatters
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Passkeys\Format;

use WP2FA\Methods\Passkeys\Byte_Buffer;
use WP2FA\Methods\Passkeys\Web_Authn_Exception;
use WP2FA\Admin\Methods\passkeys\Authenticator_Data;

/**
 * Responsible for apple format
 *
 * @since 3.0.0
 */
class Apple extends Format_Base {

	/**
	 * X5c certificate
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	private $x5c;

	/**
	 * Default constructor
	 *
	 * @param \StdClass                                        $attestion_object - attestation object.
	 * @param \WP2FA\Admin\Methods\passkeys\Authenticator_Data $authenticator_data - Authentication data.
	 *
	 * @throws Web_Authn_Exception - if can not be created.
	 *
	 * @since 3.0.0
	 */
	public function __construct( $attestion_object, Authenticator_Data $authenticator_data ) {
		parent::__construct( $attestion_object, $authenticator_data );

		// check packed data.
		$att_stmt = $this->attestation_object['attStmt'];

		// certificate for validation.
		if ( \array_key_exists( 'x5c', $att_stmt ) && \is_array( $att_stmt['x5c'] ) && \count( $att_stmt['x5c'] ) > 0 ) {

			// The attestation certificate attestn_cert MUST be the first element in the array.
			$attestn_cert = array_shift( $att_stmt['x5c'] );

			if ( ! ( $attestn_cert instanceof Byte_Buffer ) ) {
				throw new Web_Authn_Exception( 'invalid x5c certificate', Web_Authn_Exception::INVALID_DATA );
			}

			$this->x5c = $attestn_cert->getBinaryString();

			// certificate chain.
			foreach ( $att_stmt['x5c'] as $chain ) {
				if ( $chain instanceof Byte_Buffer ) {
					$this->x5c_chain[] = $chain->getBinaryString();
				}
			}
		} else {
			throw new Web_Authn_Exception( 'invalid Apple attestation statement: missing x5c', Web_Authn_Exception::INVALID_DATA );
		}
	}

	/**
	 * Returns the key certificate in PEM format
	 *
	 * @return string|null
	 *
	 * @since 3.0.0
	 */
	public function get_certificate_pem() {
		return $this->_create_certificate_pem( $this->x5c );
	}

	/**
	 * Validates the attestation
	 *
	 * @param string $client_data_hash - Hash collected.
	 *
	 * @throws Web_Authn_Exception - Throws exception if validation fails.
	 *
	 * @since 3.0.0
	 */
	public function validate_attestation( $client_data_hash ) {
		return $this->_validate_ver_x5c( $client_data_hash );
	}

	/**
	 * Validates the certificate against root certificates
	 *
	 * @param array $root_cas - Array with values.
	 *
	 * @return boolean
	 *
	 * @throws Web_Authn_Exception - Throws exception.
	 *
	 * @since 3.0.0
	 */
	public function validate_root_certificate( $root_cas ) {
		$chain_c = $this->_create_x5c_chain_file();
		if ( $chain_c ) {
			$root_cas[] = $chain_c;
		}

		$v = \openssl_x509_checkpurpose( $this->get_certificate_pem(), -1, $root_cas );
		if ( -1 === $v ) {
			throw new Web_Authn_Exception( 'error on validating root certificate: ' . \openssl_error_string(), Web_Authn_Exception::CERTIFICATE_NOT_TRUSTED );
		}
		return $v;
	}

	/**
	 * Validate if x5c is present
	 *
	 * @param string $client_data_hash - Hash collected.
	 *
	 * @return bool
	 *
	 * @throws Web_Authn_Exception - Throws exception.
	 *
	 * @since 3.0.0
	 */
	protected function _validate_ver_x5c( $client_data_hash ) {
		$public_key = \openssl_pkey_get_public( $this->get_certificate_pem() );

		if ( false === $public_key ) {
			throw new Web_Authn_Exception( 'invalid public key: ' . \openssl_error_string(), Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		// Concatenate Authenticator_Data and client_data_hash to form nonce_to_hash.
		$nonce_to_hash  = $this->authenticator_data->get_binary();
		$nonce_to_hash .= $client_data_hash;

		// Perform SHA-256 hash of nonce_to_hash to produce nonce.
		$nonce = hash( 'SHA256', $nonce_to_hash, true );

		$cred_cert = openssl_x509_read( $this->get_certificate_pem() );
		if ( false === $cred_cert ) {
			throw new Web_Authn_Exception( 'invalid x5c certificate: ' . \openssl_error_string(), Web_Authn_Exception::INVALID_DATA );
		}

		$key_data = openssl_pkey_get_details( openssl_pkey_get_public( $cred_cert ) );
		$key      = is_array( $key_data ) && array_key_exists( 'key', $key_data ) ? $key_data['key'] : null;

		// Verify that nonce equals the value of the extension with OID ( 1.2.840.113635.100.8.2 ) in cred_cert.
		$parsed_cred_cert = openssl_x509_parse( $cred_cert );
		$nonce_extension  = $parsed_cred_cert['extensions']['1.2.840.113635.100.8.2'] ?? '';

		// nonce padded by ASN.1 string: 30 24 A1 22 04 20
		// 30     — type tag indicating sequence
		// 24     — 36 byte following
		// A1   — Enumerated [1]
		// 22   — 34 byte following
		// 04 — type tag indicating octet string
		// 20 — 32 byte following.

		$asn1_padding = "\x30\x24\xA1\x22\x04\x20";
		if ( substr( $nonce_extension, 0, strlen( $asn1_padding ) ) === $asn1_padding ) {
			$nonce_extension = substr( $nonce_extension, strlen( $asn1_padding ) );
		}

		if ( $nonce_extension !== $nonce ) {
			throw new Web_Authn_Exception( 'nonce doesn\'t equal the value of the extension with OID 1.2.840.113635.100.8.2', Web_Authn_Exception::INVALID_DATA );
		}

		// Verify that the credential public key equals the Subject Public Key of cred_cert.
		$auth_key_data = openssl_pkey_get_details( openssl_pkey_get_public( $this->authenticator_data->get_public_key_pem() ) );
		$auth_key      = is_array( $auth_key_data ) && array_key_exists( 'key', $auth_key_data ) ? $auth_key_data['key'] : null;

		if ( null === $key || $key !== $auth_key ) {
			throw new Web_Authn_Exception( 'credential public key doesn\'t equal the Subject Public Key of credCert', Web_Authn_Exception::INVALID_DATA );
		}

		return true;
	}

}
