<?php
/**
 * Passkeys formatters
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Passkeys\Format;

use WP2FA\Methods\Passkeys\Byte_Buffer;
use WP2FA\Methods\Passkeys\Web_Authn_Exception;
use WP2FA\Admin\Methods\passkeys\Authenticator_Data;

/**
 * Responsible for android format
 *
 * @since 3.0.0
 */
class Android_Safety_Net extends Format_Base {

	private $signature;
	private $signed_value;
	private $x5c;
	private $payload;

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
	public function __construct( $attestation_object, Authenticator_Data $authenticator_data ) {
		parent::__construct( $attestation_object, $authenticator_data );

		// check data.
		$att_stmt = $this->attestation_object['attStmt'];

		if ( ! \array_key_exists( 'ver', $att_stmt ) || ! $att_stmt['ver'] ) {
			throw new Web_Authn_Exception( 'invalid Android Safety Net Format', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'response', $att_stmt ) || ! ( $att_stmt['response'] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'invalid Android Safety Net Format', Web_Authn_Exception::INVALID_DATA );
		}

		$response = $att_stmt['response']->getBinaryString();

		// Response is a JWS [RFC7515] object in Compact Serialization.
		// JWSs have three segments separated by two period ('.') characters.
		$parts = \explode( '.', $response );
		unset( $response );
		if ( \count( $parts ) !== 3 ) {
			throw new Web_Authn_Exception( 'invalid JWS data', Web_Authn_Exception::INVALID_DATA );
		}

		$header             = $this->_base64url_decode( $parts[0] );
		$payload            = $this->_base64url_decode( $parts[1] );
		$this->signature    = $this->_base64url_decode( $parts[2] );
		$this->signed_value = $parts[0] . '.' . $parts[1];
		unset( $parts );

		$header  = \json_decode( $header );
		$payload = \json_decode( $payload );

		if ( ! ( $header instanceof \stdClass ) ) {
			throw new Web_Authn_Exception( 'invalid JWS header', Web_Authn_Exception::INVALID_DATA );
		}
		if ( ! ( $payload instanceof \stdClass ) ) {
			throw new Web_Authn_Exception( 'invalid JWS payload', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! isset( $header->x5c ) || ! is_array( $header->x5c ) || count( $header->x5c ) === 0 ) {
			throw new Web_Authn_Exception( 'No X.509 signature in JWS Header', Web_Authn_Exception::INVALID_DATA );
		}

		// algorithm.
		if ( ! \in_array( $header->alg, array( 'RS256', 'ES256' ) ) ) {
			throw new Web_Authn_Exception( 'invalid JWS algorithm ' . $header->alg, Web_Authn_Exception::INVALID_DATA );
		}

		$this->x5c     = \base64_decode( $header->x5c[0] );
		$this->payload = $payload;

		if ( count( $header->x5c ) > 1 ) {
			for ( $i = 1; $i < count( $header->x5c ); $i++ ) {
				$this->x5c_chain[] = \base64_decode( $header->x5c[ $i ] );
			}
			unset( $i );
		}
	}

	/**
	 * ctsProfileMatch: A stricter verdict of device integrity.
	 * If the value of ctsProfileMatch is true, then the profile of the device running your app matches
	 * the profile of a device that has passed Android compatibility testing and
	 * has been approved as a Google-certified Android device.
	 *
	 * @return bool
	 */
	public function ctsProfileMatch() {
		return isset( $this->payload->ctsProfileMatch ) ? ! ! $this->payload->ctsProfileMatch : false;
	}


	/*
	 * returns the key certificate in PEM format
	 * @return string
	 */
	public function getCertificatePem() {
		return $this->_create_certificate_pem( $this->x5c );
	}

	/**
	 * @param string $client_data_hash
	 */
	public function validate_attestation( $client_data_hash ) {
		$public_key = \openssl_pkey_get_public( $this->getCertificatePem() );

		// Verify that the nonce in the response is identical to the Base64 encoding
		// of the SHA-256 hash of the concatenation of Authenticator_Data and client_data_hash.
		if ( empty( $this->payload->nonce ) || \base64_encode( \hash( 'SHA256', $this->authenticator_data->get_binary() . $client_data_hash, true ) ) !== $this->payload->nonce ) {
			throw new Web_Authn_Exception( 'invalid nonce in JWS payload', Web_Authn_Exception::INVALID_DATA );
		}

		// Verify that attestationCert is issued to the hostname "attest.android.com".
		$cert_info = \openssl_x509_parse( $this->getCertificatePem() );
		if ( ! \is_array( $cert_info ) || ( $cert_info['subject']['CN'] ?? '' ) !== 'attest.android.com' ) {
			throw new Web_Authn_Exception( 'invalid certificate CN in JWS (' . ( $cert_info['subject']['CN'] ?? '-' ) . ')', Web_Authn_Exception::INVALID_DATA );
		}

		// Verify that the basicIntegrity attribute in the payload of response is true.
		if ( empty( $this->payload->basicIntegrity ) ) {
			throw new Web_Authn_Exception( 'invalid basicIntegrity in payload', Web_Authn_Exception::INVALID_DATA );
		}

		// check certificate.
		return \openssl_verify( $this->signed_value, $this->signature, $public_key, OPENSSL_ALGO_SHA256 ) === 1;
	}


	/**
	 * Validates the certificate against root certificates
	 *
	 * @param array $root_cas
	 * @return boolean
	 * @throws Web_Authn_Exception
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
	 * Decode base64 url
	 *
	 * @param string $data
	 * @return string
	 */
	private function _base64url_decode( $data ) {
		return \base64_decode( \strtr( $data, '-_', '+/' ) . \str_repeat( '=', 3 - ( 3 + \strlen( $data ) ) % 4 ) );
	}
}
