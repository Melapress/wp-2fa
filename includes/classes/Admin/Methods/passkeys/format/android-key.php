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
 * Responsible for android format
 *
 * @since 3.0.0
 */
class Android_Key extends Format_Base {

	/**
	 * Algorithm to be used
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	private $_alg;

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
	private $_x5c;

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
		$att_stmt = $this->attestation_object['att_stmt'];

		if ( ! \array_key_exists( 'alg', $att_stmt ) || $this->_get_cose_algorithm( $att_stmt['alg'] ) === null ) {
			throw new Web_Authn_Exception( 'unsupported alg: ' . $att_stmt['alg'], Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'sig', $att_stmt ) || ! \is_object( $att_stmt['sig'] ) || ! ( $att_stmt['sig'] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'no signature found', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'x5c', $att_stmt ) || ! \is_array( $att_stmt['x5c'] ) || \count( $att_stmt['x5c'] ) < 1 ) {
			throw new Web_Authn_Exception( 'invalid x5c certificate', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \is_object( $att_stmt['x5c'][0] ) || ! ( $att_stmt['x5c'][0] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'invalid x5c certificate', Web_Authn_Exception::INVALID_DATA );
		}

		$this->_alg       = $att_stmt['alg'];
		$this->_signature = $att_stmt['sig']->getBinaryString();
		$this->_x5c       = $att_stmt['x5c'][0]->getBinaryString();

		if ( count( $att_stmt['x5c'] ) > 1 ) {
			for ( $i = 1; $i < count( $att_stmt['x5c'] ); $i++ ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found
				$this->x5c_chain[] = $att_stmt['x5c'][ $i ]->getBinaryString();
			}
			unset( $i );
		}
	}

	/**
	 * Returns the key certificate in PEM format
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	public function get_certificate_pem() {
		return $this->_create_certificate_pem( $this->_x5c );
	}

	/**
	 * Validator
	 *
	 * @param string $client_data_hash - Hash collected.
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

		// Verify that sig is a valid signature over the concatenation of authenticator_data and client_data_hash
		// using the attestation public key in attestn_cert with the algorithm specified in alg.
		$data_to_verify  = $this->authenticator_data->get_binary();
		$data_to_verify .= $client_data_hash;

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
	public function validateRootCertificate( $root_cas ) {
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
