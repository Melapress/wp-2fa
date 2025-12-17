<?php

namespace WP2FA\Admin\Methods\passkeys;

use WP2FA\Methods\Passkeys\Byte_Buffer;
use WP2FA\Methods\Passkeys\Cbor_Decoder;
use WP2FA\Methods\Passkeys\Web_Authn_Exception;

/**
 * Undocumented class
 *
 * @since 3.0.0
 */
class Authenticator_Data {

	/** @var string */
	protected $_binary;
	/** @var \stdClass */
	protected $rp_id_hash;
	/** @var \stdClass */
	protected $_flags;
	/** @var int */
	protected $sign_count;
	/** @var \stdClass */
	protected $attested_credential_data;
	/** @var array */
	protected $_extensionData;
	// Cose encoded keys.
	private const COSE_KTY = 1;
	private const COSE_ALG = 3;
	// Cose curve.
	private const COSE_CRV = -1;
	private const COSE_X   = -2;
	private const COSE_Y   = -3;
	// Cose RSA PS256.
	private const COSE_N = -1;
	private const COSE_E = -2;
	// EC2 key type.
	private const EC2_TYPE  = 2;
	private const EC2_ES256 = -7;
	private const EC2_P256  = 1;
	// RSA key type.
	private const RSA_TYPE  = 3;
	private const RSA_RS256 = -257;
	// OKP key type.
	private const OKP_TYPE    = 1;
	private const OKP_ED25519 = 6;
	private const OKP_EDDSA   = -8;

	/**
	 * Parsing the Authenticator_Data binary.
	 *
	 * @param string $binary - Default comment.
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	public function __construct( $binary ) {
		if ( ! \is_string( $binary ) || \strlen( $binary ) < 37 ) {
			throw new Web_Authn_Exception( 'Invalid Authenticator_Data input', Web_Authn_Exception::INVALID_DATA );
		}
		$this->_binary = $binary;

		// Read infos from binary.
		// https://www.w3.org/TR/Web_Authn/#sec-authenticator-data .

		// RP ID.
		$this->rp_id_hash = \substr( $binary, 0, 32 );

		// flags (1 byte).
		$flags        = \unpack( 'Cflags', \substr( $binary, 32, 1 ) )['flags'];
		$this->_flags = $this->_read_flags( $flags );

		// signature counter: 32-bit unsigned big-endian integer.
		$this->sign_count = \unpack( 'Nsigncount', \substr( $binary, 33, 4 ) )['signcount'];

		$offset = 37;
		// https://www.w3.org/TR/Web_Authn/#sec-attested-credential-data .
		if ( $this->_flags->attested_data_included ) {
			$this->attested_credential_data = $this->_read_attest_data( $binary, $offset );
		}

		if ( $this->_flags->extension_data_included ) {
			$this->_read_extension_data( \substr( $binary, $offset ) );
		}
	}

	/**
	 * Authenticator Attestation Globally Unique Identifier, a unique number
	 * that identifies the model of the authenticator (not the specific instance
	 * of the authenticator)
	 * The aaguid may be 0 if the user is using a old u2f device and/or if
	 * the browser is using the fido-u2f format.
	 *
	 * @return string
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	public function get_aaguid() {
		if ( ! ( $this->attested_credential_data instanceof \stdClass ) ) {
			throw new Web_Authn_Exception( 'credential data not included in authenticator data', Web_Authn_Exception::INVALID_DATA );
		}
		return $this->attested_credential_data->aaguid;
	}

	/**
	 * Returns the Authenticator_Data as binary
	 *
	 * @return string
	 */
	public function get_binary() {
		return $this->_binary;
	}

	/**
	 * Returns the credential_id
	 *
	 * @return string
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	public function get_credential_id() {
		if ( ! ( $this->attested_credential_data instanceof \stdClass ) ) {
			throw new Web_Authn_Exception( 'credential id not included in authenticator data', Web_Authn_Exception::INVALID_DATA );
		}
		return $this->attested_credential_data->credential_id;
	}

	/**
	 * Returns the public key in PEM format
	 *
	 * @return string
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	public function get_public_key_pem() {
		if ( ! ( $this->attested_credential_data instanceof \stdClass ) || ! isset( $this->attested_credential_data->credential_public_key ) ) {
			throw new Web_Authn_Exception( 'credential data not included in authenticator data', Web_Authn_Exception::INVALID_DATA );
		}

		$der = null;
		switch ( $this->attested_credential_data->credential_public_key->kty ?? null ) {
			case self::EC2_TYPE:
				$der = $this->_get_ec2_der();
				break;
			case self::RSA_TYPE:
				$der = $this->_get_rsa_der();
				break;
			case self::OKP_TYPE:
				$der = $this->_get_okp_der();
				break;
			default:
				throw new Web_Authn_Exception( 'invalid key type', Web_Authn_Exception::INVALID_DATA );
		}

		$pem  = '-----BEGIN PUBLIC KEY-----' . "\n";
		$pem .= \chunk_split( \base64_encode( $der ), 64, "\n" );
		$pem .= '-----END PUBLIC KEY-----' . "\n";
		return $pem;
	}

	/**
	 * Returns the public key in U2F format
	 *
	 * @return string
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	public function get_public_key_u2f() {
		if ( ! ( $this->attested_credential_data instanceof \stdClass ) || ! isset( $this->attested_credential_data->credential_public_key ) ) {
			throw new Web_Authn_Exception( 'credential data not included in authenticator data', Web_Authn_Exception::INVALID_DATA );
		}
		if ( ( $this->attested_credential_data->credential_public_key->kty ?? null ) !== self::EC2_TYPE ) {
			throw new Web_Authn_Exception( 'signature algorithm not ES256', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}
		return "\x04" . // ECC uncompressed.
				$this->attested_credential_data->credential_public_key->x .
				$this->attested_credential_data->credential_public_key->y;
	}

	/**
	 * Returns the SHA256 hash of the relying party id (=hostname)
	 *
	 * @return string
	 */
	public function get_rp_id_hash() {
		return $this->rp_id_hash;
	}

	/**
	 * Returns the sign counter
	 *
	 * @return int
	 */
	public function get_sign_count() {
		return $this->sign_count;
	}

	/**
	 * Returns true if the user is present
	 *
	 * @return boolean
	 */
	public function get_user_present() {
		return $this->_flags->user_present;
	}

	/**
	 * Returns true if the user is verified
	 *
	 * @return boolean
	 */
	public function get_user_verified() {
		return $this->_flags->user_verified;
	}

	/**
	 * Returns true if the backup is eligible
	 *
	 * @return boolean
	 */
	public function get_is_backup_eligible() {
		return $this->_flags->is_backup_eligible;
	}

	/**
	 * Returns true if the current credential is backed up
	 *
	 * @return boolean
	 */
	public function get_is_backup() {
		return $this->_flags->is_backup;
	}

	// -----------------------------------------------
	// PRIVATE
	// -----------------------------------------------

	/**
	 * Returns DER encoded EC2 key
	 *
	 * @return string
	 */
	private function _get_ec2_der() {
		return $this->_der_sequence(
			$this->_der_sequence(
				$this->_der_oid( "\x2A\x86\x48\xCE\x3D\x02\x01" ) . // OID 1.2.840.10045.2.1 ecPublicKey.
				$this->_der_oid( "\x2A\x86\x48\xCE\x3D\x03\x01\x07" )  // 1.2.840.10045.3.1.7 prime256v1.
			) .
			$this->_der_bit_string( $this->get_public_key_u2f() )
		);
	}

	/**
	 * Returns DER encoded EdDSA key
	 *
	 * @return string
	 */
	private function _get_okp_der() {
		return $this->_der_sequence(
			$this->_der_sequence(
				$this->_der_oid( "\x2B\x65\x70" ) // OID 1.3.101.112 curveEd25519 (EdDSA 25519 signature algorithm).
			) .
			$this->_der_bit_string( $this->attested_credential_data->credential_public_key->x )
		);
	}

	/**
	 * Returns DER encoded RSA key
	 *
	 * @return string
	 */
	private function _get_rsa_der() {
		return $this->_der_sequence(
			$this->_der_sequence(
				$this->_der_oid( "\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01" ) . // OID 1.2.840.113549.1.1.1 rsaEncryption.
				$this->_der_null_value()
			) .
			$this->_der_bit_string(
				$this->_der_sequence(
					$this->_der_unsigned_integer( $this->attested_credential_data->credential_public_key->n ) .
					$this->_der_unsigned_integer( $this->attested_credential_data->credential_public_key->e )
				)
			)
		);
	}

	/**
	 * Reads the flags from flag byte
	 *
	 * @param string $bin_flag - Default comment.
	 *
	 * @return \stdClass
	 */
	private function _read_flags( $bin_flag ) {
		$flags = new \stdClass();

		$flags->bit_0 = ! ! ( $bin_flag & 1 );
		$flags->bit_1 = ! ! ( $bin_flag & 2 );
		$flags->bit_2 = ! ! ( $bin_flag & 4 );
		$flags->bit_3 = ! ! ( $bin_flag & 8 );
		$flags->bit_4 = ! ! ( $bin_flag & 16 );
		$flags->bit_5 = ! ! ( $bin_flag & 32 );
		$flags->bit_6 = ! ! ( $bin_flag & 64 );
		$flags->bit_7 = ! ! ( $bin_flag & 128 );

		// named flags.
		$flags->user_present            = $flags->bit_0;
		$flags->user_verified           = $flags->bit_2;
		$flags->is_backup_eligible      = $flags->bit_3;
		$flags->is_backup               = $flags->bit_4;
		$flags->attested_data_included  = $flags->bit_6;
		$flags->extension_data_included = $flags->bit_7;
		return $flags;
	}

	/**
	 * Read attested data
	 *
	 * @param string $binary - Default comment.
	 * @param int    $end_offset - Default comment.
	 *
	 * @return \stdClass
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	private function _read_attest_data( $binary, &$end_offset ) {
		$attested_c_data = new \stdClass();
		if ( \strlen( $binary ) <= 55 ) {
			throw new Web_Authn_Exception( 'Attested data should be present but is missing', Web_Authn_Exception::INVALID_DATA );
		}

		// The AAGUID of the authenticator.
		$attested_c_data->aaguid = \substr( $binary, 37, 16 );

		// Byte length L of Credential ID, 16-bit unsigned big-endian integer.
		$length                         = \unpack( 'nlength', \substr( $binary, 53, 2 ) )['length'];
		$attested_c_data->credential_id = \substr( $binary, 55, $length );

		// set end offset.
		$end_offset = 55 + $length;

		// extract public key.
		$attested_c_data->credential_public_key = $this->_read_credential_public_key( $binary, 55 + $length, $end_offset );

		return $attested_c_data;
	}

	/**
	 * Reads COSE key-encoded elliptic curve public key in EC2 format
	 *
	 * @param string $binary - Default comment.
	 * @param int    $offset - Default comment.
	 * @param int    $end_offset - Default comment.
	 *
	 * @return \stdClass
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	private function _read_credential_public_key( $binary, $offset, &$end_offset ) {
		$enc = Cbor_Decoder::decode_in_place( $binary, $offset, $end_offset );

		// COSE key-encoded elliptic curve public key in EC2 format.
		$cred_p_key      = new \stdClass();
		$cred_p_key->kty = $enc[ self::COSE_KTY ];
		$cred_p_key->alg = $enc[ self::COSE_ALG ];

		switch ( $cred_p_key->alg ) {
			case self::EC2_ES256:
				$this->_read_credential_public_key_es256( $cred_p_key, $enc );
				break;
			case self::RSA_RS256:
				$this->_read_credential_public_key_rs256( $cred_p_key, $enc );
				break;
			case self::OKP_EDDSA:
				$this->_read_credential_public_key_eddsa( $cred_p_key, $enc );
				break;
		}

		return $cred_p_key;
	}

	/**
	 * Extract EDDSA informations from cose
	 *
	 * @param \stdClass $cred_p_key - Default comment.
	 * @param \stdClass $enc - Default comment.
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	private function _read_credential_public_key_eddsa( &$cred_p_key, $enc ) {
		$cred_p_key->crv = $enc[ self::COSE_CRV ];
		$cred_p_key->x   = $enc[ self::COSE_X ] instanceof Byte_Buffer ? $enc[ self::COSE_X ]->getBinaryString() : null;
		unset( $enc );

		// Validation.
		if ( self::OKP_TYPE !== $cred_p_key->kty ) {
			throw new Web_Authn_Exception( 'public key not in OKP format', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( self::OKP_EDDSA !== $cred_p_key->alg ) {
			throw new Web_Authn_Exception( 'signature algorithm not EdDSA', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( self::OKP_ED25519 !== $cred_p_key->crv ) {
			throw new Web_Authn_Exception( 'curve not Ed25519', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( \strlen( $cred_p_key->x ) !== 32 ) {
			throw new Web_Authn_Exception( 'Invalid X-coordinate', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}
	}

	/**
	 * Extract ES256 information from cose
	 *
	 * @param \stdClass $cred_p_key - Default comment.
	 * @param \stdClass $enc - Default comment.
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	private function _read_credential_public_key_es256( &$cred_p_key, $enc ) {
		$cred_p_key->crv = $enc[ self::COSE_CRV ];
		$cred_p_key->x   = $enc[ self::COSE_X ] instanceof Byte_Buffer ? $enc[ self::COSE_X ]->getBinaryString() : null;
		$cred_p_key->y   = $enc[ self::COSE_Y ] instanceof Byte_Buffer ? $enc[ self::COSE_Y ]->getBinaryString() : null;
		unset( $enc );

		// Validation.
		if ( self::EC2_TYPE !== $cred_p_key->kty ) {
			throw new Web_Authn_Exception( 'public key not in EC2 format', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( self::EC2_ES256 !== $cred_p_key->alg ) {
			throw new Web_Authn_Exception( 'signature algorithm not ES256', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( self::EC2_P256 !== $cred_p_key->crv ) {
			throw new Web_Authn_Exception( 'curve not P-256', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( \strlen( $cred_p_key->x ) !== 32 ) {
			throw new Web_Authn_Exception( 'Invalid X-coordinate', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( \strlen( $cred_p_key->y ) !== 32 ) {
			throw new Web_Authn_Exception( 'Invalid Y-coordinate', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}
	}

	/**
	 * Extract RS256 informations from COSE
	 *
	 * @param \stdClass $cred_p_key - Default comment.
	 * @param \stdClass $enc - Default comment.
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	private function _read_credential_public_key_rs256( &$cred_p_key, $enc ) {
		$cred_p_key->n = $enc[ self::COSE_N ] instanceof Byte_Buffer ? $enc[ self::COSE_N ]->getBinaryString() : null;
		$cred_p_key->e = $enc[ self::COSE_E ] instanceof Byte_Buffer ? $enc[ self::COSE_E ]->getBinaryString() : null;
		unset( $enc );

		// Validation.
		if ( self::RSA_TYPE !== $cred_p_key->kty ) {
			throw new Web_Authn_Exception( 'public key not in RSA format', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( self::RSA_RS256 !== $cred_p_key->alg ) {
			throw new Web_Authn_Exception( 'signature algorithm not ES256', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( \strlen( $cred_p_key->n ) !== 256 ) {
			throw new Web_Authn_Exception( 'Invalid RSA modulus', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}

		if ( \strlen( $cred_p_key->e ) !== 3 ) {
			throw new Web_Authn_Exception( 'Invalid RSA public exponent', Web_Authn_Exception::INVALID_PUBLIC_KEY );
		}
	}

	/**
	 * Reads cbor encoded extension data.
	 *
	 * @param string $binary - Default comment.
	 *
	 * @return array
	 *
	 * @throws Web_Authn_Exception - Default comment.
	 */
	private function _read_extension_data( $binary ) {
		$ext = Cbor_Decoder::decode( $binary );
		if ( ! \is_array( $ext ) ) {
			throw new Web_Authn_Exception( 'invalid extension data', Web_Authn_Exception::INVALID_DATA );
		}

		return $ext;
	}


	// ---------------
	// DER functions
	// ---------------

	/**
	 * Undocumented function
	 *
	 * @param [type] $len - Default comment.
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	private function _der_length( $len ) {
		if ( $len < 128 ) {
			return \chr( $len );
		}
		$len_bytes = '';
		while ( $len > 0 ) {
			$len_bytes = \chr( $len % 256 ) . $len_bytes;
			$len       = \intdiv( $len, 256 );
		}
		return \chr( 0x80 | \strlen( $len_bytes ) ) . $len_bytes;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $contents - Default comment.
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	private function _der_sequence( $contents ) {
		return "\x30" . $this->_der_length( \strlen( $contents ) ) . $contents;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $encoded - Default comment.
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	private function _der_oid( $encoded ) {
		return "\x06" . $this->_der_length( \strlen( $encoded ) ) . $encoded;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $bytes - Default comment.
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	private function _der_bit_string( $bytes ) {
		return "\x03" . $this->_der_length( \strlen( $bytes ) + 1 ) . "\x00" . $bytes;
	}

	/**
	 * Undocumented function
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	private function _der_null_value() {
		return "\x05\x00";
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $bytes - Default comment.
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	private function _der_unsigned_integer( $bytes ) {
		$len = \strlen( $bytes );

		// Remove leading zero bytes.
		for ( $i = 0; $i < ( $len - 1 ); $i++ ) {
			if ( \ord( $bytes[ $i ] ) !== 0 ) {
				break;
			}
		}
		if ( 0 !== $i ) {
			$bytes = \substr( $bytes, $i );
		}

		// If most significant bit is set, prefix with another zero to prevent it being seen as negative number.
		if ( ( \ord( $bytes[0] ) & 0x80 ) !== 0 ) {
			$bytes = "\x00" . $bytes;
		}

		return "\x02" . $this->_der_length( \strlen( $bytes ) ) . $bytes;
	}
}
