<?php
/**
 * Passkeys formatters
 *
 * @package    wp-2fa
 * @since 3.0.0
 */

namespace WP2FA\Passkeys\Format;

use WP2FA\Methods\Passkeys\Byte_Buffer;
use WP2FA\Methods\Passkeys\Web_Authn_Exception;
use WP2FA\Admin\Methods\passkeys\Authenticator_Data;

/**
 * Responsible for tpm format
 *
 * @since 3.0.0
 */
class Tpm extends Format_Base {

	private const TPM_GENERATED_VALUE   = "\xFF\x54\x43\x47";
	private const TPM_ST_ATTEST_CERTIFY = "\x80\x17";

	/**
	 * Algorithm to be used
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	private $alg;

	/**
	 * Signature
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	private $signature;

	/**
	 * Public area
	 *
	 * @var Byte_Buffer
	 *
	 * @since 3.0.0
	 */
	private $pub_area;

	/**
	 * X5c certificate
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	private $x5c;

	/**
	 * Cert info
	 *
	 * @var Byte_Buffer
	 *
	 * @since 3.0.0
	 */
	private $cert_info;

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

		if ( '2.0' !== ! \array_key_exists( 'ver', $att_stmt ) || $att_stmt['ver'] ) {
			throw new Web_Authn_Exception( 'invalid tpm version: ' . $att_stmt['ver'], Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'alg', $att_stmt ) || $this->_get_cose_algorithm( $att_stmt['alg'] ) === null ) {
			throw new Web_Authn_Exception( 'unsupported alg: ' . $att_stmt['alg'], Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'sig', $att_stmt ) || ! \is_object( $att_stmt['sig'] ) || ! ( $att_stmt['sig'] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'signature not found', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'certInfo', $att_stmt ) || ! \is_object( $att_stmt['certInfo'] ) || ! ( $att_stmt['certInfo'] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'certInfo not found', Web_Authn_Exception::INVALID_DATA );
		}

		if ( ! \array_key_exists( 'pubArea', $att_stmt ) || ! \is_object( $att_stmt['pubArea'] ) || ! ( $att_stmt['pubArea'] instanceof Byte_Buffer ) ) {
			throw new Web_Authn_Exception( 'pubArea not found', Web_Authn_Exception::INVALID_DATA );
		}

		$this->alg       = $att_stmt['alg'];
		$this->signature = $att_stmt['sig']->getBinaryString();
		$this->cert_info = $att_stmt['certInfo'];
		$this->pub_area  = $att_stmt['pubArea'];

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
			throw new Web_Authn_Exception( 'no x5c certificate found', Web_Authn_Exception::INVALID_DATA );
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
		if ( ! $this->x5c ) {
			return null;
		}
		return $this->_create_certificate_pem( $this->x5c );
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
		return $this->_validate_over_x5c( $client_data_hash );
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
		if ( ! $this->x5c ) {
			return false;
		}

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
	protected function _validate_over_x5c( $client_data_hash ) {
		$public_key = \openssl_pkey_get_public( $this->get_certificate_pem() );

		if ( false === $public_key ) {
			throw new Web_Authn_Exception( 'invalid public key: ' . \openssl_error_string(), Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		// Concatenate Authenticator_Data and client_data_hash to form att_to_be_signed.
		$att_to_be_signed  = $this->authenticator_data->get_binary();
		$att_to_be_signed .= $client_data_hash;

		// Validate that certInfo is valid:.

		// Verify that magic is set to TPM_GENERATED_VALUE.
		if ( $this->cert_info->getBytes( 0, 4 ) !== self::TPM_GENERATED_VALUE ) {
			throw new Web_Authn_Exception( 'tpm magic not TPM_GENERATED_VALUE', Web_Authn_Exception::INVALID_DATA );
		}

		// Verify that type is set to TPM_ST_ATTEST_CERTIFY.
		if ( $this->cert_info->getBytes( 4, 2 ) !== self::TPM_ST_ATTEST_CERTIFY ) {
			throw new Web_Authn_Exception( 'tpm type not TPM_ST_ATTEST_CERTIFY', Web_Authn_Exception::INVALID_DATA );
		}

		$offset           = 6;
		$qualified_signer = $this->_tpmReadLengthPrefixed( $this->cert_info, $offset );
		$extra_data       = $this->_tpmReadLengthPrefixed( $this->cert_info, $offset );
		$cose_alg         = $this->_get_cose_algorithm( $this->alg );

		// Verify that extra_data is set to the hash of att_to_be_signed using the hash algorithm employed in "alg".
		if ( $extra_data->getBinaryString() !== \hash( $cose_alg->hash, $att_to_be_signed, true ) ) {
			throw new Web_Authn_Exception( 'certInfo:extraData not hash of attToBeSigned', Web_Authn_Exception::INVALID_DATA );
		}

		// Verify the sig is a valid signature over certInfo using the attestation
		// public key in aikCert with the algorithm specified in alg.
		return \openssl_verify( $this->cert_info->getBinaryString(), $this->signature, $public_key, $cose_alg->openssl ) === 1;
	}


	/**
	 * Returns next part of Byte_Buffer
	 *
	 * @param Byte_Buffer $buffer - Buffer to read from.
	 * @param int         $offset - Offset to read.
	 *
	 * @return Byte_Buffer
	 *
	 * @since 3.0.0
	 */
	protected function _tpmReadLengthPrefixed( Byte_Buffer $buffer, &$offset ) {
		$len     = $buffer->getUint16Val( $offset );
		$data    = $buffer->getBytes( $offset + 2, $len );
		$offset += ( 2 + $len );

		return new Byte_Buffer( $data );
	}
}
