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
 * Responsible for u2f format
 *
 * @since 3.0.0
 */
class U2f extends Format_Base {

	/**
	 * Algorithm to be used
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	private $_alg = -7;

	/**
	 * Signature
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	private $_signature;

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

		// check u2f data.
		$att_stmt = $this->attestation_object['attStmt'];

		if ( \array_key_exists( 'alg', $att_stmt ) && $att_stmt['alg'] !== $this->_alg ) {
			throw new Web_Authn_Exception( 'u2f only accepts algorithm -7 ("ES256"), but got ' . $att_stmt['alg'], Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'sig', $att_stmt ) || ! \is_object( $att_stmt['sig'] ) || ! ( $att_stmt['sig'] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'no signature found', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'x5c', $att_stmt ) || ! \is_array( $att_stmt['x5c'] ) || \count( $att_stmt['x5c'] ) !== 1 ) {
			throw new Web_Authn_Exception( 'invalid x5c certificate', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \is_object( $att_stmt['x5c'][0] ) || ! ( $att_stmt['x5c'][0] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'invalid x5c certificate', Web_Authn_Exception::INVALID_DATA );
		}

		$this->_signature = $att_stmt['sig']->getBinaryString();
		$this->x5c        = $att_stmt['x5c'][0]->getBinaryString();
	}


	/**
	 * Returns the key certificate in PEM format
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	public function get_certificate_pem() {
		$pem  = '-----BEGIN CERTIFICATE-----' . "\n";
		$pem .= \chunk_split( \base64_encode( $this->x5c ), 64, "\n" );
		$pem .= '-----END CERTIFICATE-----' . "\n";
		return $pem;
	}

	/**
	 * Validates the attestation
	 *
	 * @param string $client_data_hash - Hash collected.
	 *
	 * @return bool
	 *
	 * @throws Web_Authn_Exception - Throws exception if validation fails.
	 *
	 * @since 3.0.0
	 */
	public function validate_attestation( $client_data_hash ) {
		$public_key = \openssl_pkey_get_public( $this->get_certificate_pem() );

		if ( false === $public_key ) {
			throw new Web_Authn_Exception( 'invalid public key: ' . \openssl_error_string(), Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		// Let verificationData be the concatenation of (0x00 || rpIdHash || client_data_hash || credentialId || publicKeyU2F).
		$data_to_verify  = "\x00";
		$data_to_verify .= $this->authenticator_data->get_rp_id_hash();
		$data_to_verify .= $client_data_hash;
		$data_to_verify .= $this->authenticator_data->get_credential_id();
		$data_to_verify .= $this->authenticator_data->get_public_key_u2f();

		$cose_algorithm = $this->_get_cose_algorithm( $this->_alg );

		// check certificate.
		return \openssl_verify( $data_to_verify, $this->_signature, $public_key, $cose_algorithm->openssl ) === 1;
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
}
