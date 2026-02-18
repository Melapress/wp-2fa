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

use WP2FA\Admin\Methods\passkeys\Authenticator_Data;

/**
 * Base format class
 *
 * @since 3.0.0
 */
abstract class Format_Base {

	/**
	 * Attestation object
	 *
	 * @var Array
	 *
	 * @since 3.1.0
	 */
	protected $attestation_object = null;

	/**
	 * Authenticator data
	 *
	 * @var Authenticator_Data
	 *
	 * @since 3.1.0
	 */
	protected $authenticator_data = null;

	/**
	 * X5c certificate chain
	 *
	 * @var array
	 *
	 * @since 3.0.0
	 */
	protected $x5c_chain = array();

	/**
	 * Temporary file for certificate chain
	 *
	 * @var string
	 *
	 * @since 3.0.0
	 */
	protected $x5c_temp_file = null;

	/**
	 * Default constructor
	 *
	 * @param Array              $attestation_object - Default comment.
	 * @param Authenticator_Data $authenticator_data - Default comment.
	 *
	 * @since 3.1.0
	 */
	public function __construct( $attestation_object, Authenticator_Data $authenticator_data ) {
		$this->attestation_object = $attestation_object;
		$this->authenticator_data = $authenticator_data;
	}

	/**
	 * Default destructor
	 *
	 * @since 3.0.0
	 */
	public function __destruct() {
		// delete X.509 chain certificate file after use.
		if ( $this->x5c_temp_file && \is_file( $this->x5c_temp_file ) ) {
			\wp_delete_file( $this->x5c_temp_file );
		}
	}

	/**
	 * Returns the certificate chain in PEM format.
	 *
	 * @return string|null
	 *
	 * @since 3.0.0
	 */
	public function get_certificate_chain() {
		if ( $this->x5c_temp_file && \is_file( $this->x5c_temp_file ) ) {
			return \file_get_contents( $this->x5c_temp_file );
		}
		return null;
	}

	/**
	 * Returns the key X.509 certificate in PEM format
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	public function get_certificate_pem() {
		// need to be overwritten.
		return null;
	}

	/**
	 * Checks validity of the signature
	 *
	 * @param string $client_data_hash - Default comment.
	 *
	 * @return bool
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 *
	 * @since 3.0.0
	 */
	public function validate_attestation( $client_data_hash ) {
		// need to be overwritten.
		return false;
	}

	/**
	 * Validates the certificate against root certificates
	 *
	 * @param array $root_cas - Default comment.
	 *
	 * @return boolean
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 *
	 * @since 3.0.0
	 */
	public function validate_root_certificate( $root_cas ) {
		// need to be overwritten.
		return false;
	}

	/**
	 * Create a PEM encoded certificate with X.509 binary data
	 *
	 * @param string $x5c - Default comment.
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	protected function _create_certificate_pem( $x5c ) {
		$pem  = '-----BEGIN CERTIFICATE-----' . "\n";
		$pem .= \chunk_split( \base64_encode( $x5c ), 64, "\n" );
		$pem .= '-----END CERTIFICATE-----' . "\n";
		return $pem;
	}

	/**
	 * Creates a PEM encoded chain file
	 *
	 * @return string|null
	 *
	 * @since 3.0.0
	 */
	protected function _create_x5c_chain_file() {
		$content = '';
		if ( \is_array( $this->x5c_chain ) && \count( $this->x5c_chain ) > 0 ) {
			foreach ( $this->x5c_chain as $x5c ) {
				$cert_info = \openssl_x509_parse( $this->_create_certificate_pem( $x5c ) );

				// check if certificate is self signed.
				if ( \is_array( $cert_info ) && \is_array( $cert_info['issuer'] ) && \is_array( $cert_info['subject'] ) ) {
					$self_signed = false;

					$subject_key_identifier   = $cert_info['extensions']['subjectKeyIdentifier'] ?? null;
					$authority_key_identifier = $cert_info['extensions']['authorityKeyIdentifier'] ?? null;

					if ( $authority_key_identifier && substr( $authority_key_identifier, 0, 6 ) === 'keyid:' ) {
						$authority_key_identifier = substr( $authority_key_identifier, 6 );
					}
					if ( $subject_key_identifier && substr( $subject_key_identifier, 0, 6 ) === 'keyid:' ) {
						$subject_key_identifier = substr( $subject_key_identifier, 6 );
					}

					if ( ( $subject_key_identifier && ! $authority_key_identifier ) || ( $authority_key_identifier && $authority_key_identifier === $subject_key_identifier ) ) {
						$self_signed = true;
					}

					if ( ! $self_signed ) {
						$content .= "\n" . $this->_create_certificate_pem( $x5c ) . "\n";
					}
				}
			}
		}

		if ( $content ) {
			$this->x5c_temp_file = \tempnam( \sys_get_temp_dir(), 'x5c_' );
			if ( \file_put_contents( $this->x5c_temp_file, $content ) !== false ) {
				return $this->x5c_temp_file;
			}
		}

		return null;
	}

	/**
	 * Returns the name and openssl key for provided cose number.
	 *
	 * @param int $cose_number - Default comment.
	 *
	 * @return \stdClass|null
	 *
	 * @since 3.0.0
	 */
	protected function _get_cose_algorithm( $cose_number ) {
		// https://www.iana.org/assignments/cose/cose.xhtml#algorithms .
		$cose_algorithms = array(
			array(
				'hash'    => 'SHA1',
				'openssl' => OPENSSL_ALGO_SHA1,
				'cose'    => array(
					-65535,  // RS1.
				),
			),

			array(
				'hash'    => 'SHA256',
				'openssl' => OPENSSL_ALGO_SHA256,
				'cose'    => array(
					-257, // RS256.
					-37,  // PS256.
					-7,   // ES256.
					5,     // HMAC256.
				),
			),

			array(
				'hash'    => 'SHA384',
				'openssl' => OPENSSL_ALGO_SHA384,
				'cose'    => array(
					-258, // RS384.
					-38,  // PS384.
					-35,  // ES384.
					6,     // HMAC384.
				),
			),

			array(
				'hash'    => 'SHA512',
				'openssl' => OPENSSL_ALGO_SHA512,
				'cose'    => array(
					-259, // RS512.
					-39,  // PS512.
					-36,  // ES512.
					7,     // HMAC512.
				),
			),
		);

		foreach ( $cose_algorithms as $cose_algorithm ) {
			if ( \in_array( $cose_number, $cose_algorithm['cose'], true ) ) {
				$return          = new \stdClass();
				$return->hash    = $cose_algorithm['hash'];
				$return->openssl = $cose_algorithm['openssl'];
				return $return;
			}
		}

		return null;
	}
}
