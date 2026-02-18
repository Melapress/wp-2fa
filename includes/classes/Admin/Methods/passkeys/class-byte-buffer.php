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

/**
 * Passkeys Byte_Buffer
 */
if ( ! class_exists( '\WP2FA\Methods\Passkeys\Byte_Buffer' ) ) {

	/**
	 * Modified version of https://github.com/madwizard-thomas/Web_Authn-server/blob/master/src/Format/Byte_Buffer.php
	 * Copyright © 2018 Thomas Bleeker - MIT licensed
	 * Modified by Lukas Buchs
	 * Thanks Thomas for your work!
	 */
	class Byte_Buffer implements \JsonSerializable, \Serializable {

		/**
		 * Undocumented variable
		 *
		 * @var boolean
		 *
		 * @since 3.0.0
		 */
		public static $use_base_64_url_encoding = false;

		/**
		 * Undocumented variable
		 *
		 * @var string
		 *
		 * @since 3.0.0
		 */
		private $data;

		/**
		 * Undocumented variable
		 *
		 * @var int
		 *
		 * @since 3.0.0
		 */
		private $length;

		/**
		 * Default constructor
		 *
		 * @param string $binary_data - String with binary data.
		 *
		 * @since 3.0.0
		 */
		public function __construct( $binary_data ) {
			$this->data   = (string) $binary_data;
			$this->length = \strlen( $binary_data );
		}

		// -----------------------
		// PUBLIC STATIC
		// -----------------------

		/**
		 * Create a Byte_Buffer from a base64 url encoded string
		 *
		 * @param string $base64url - The Url Encode.
		 *
		 * @return Byte_Buffer
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public static function fromBase64Url( $base64url ): Byte_Buffer {
			$bin = self::_base64url_decode( $base64url );
			if ( false === $bin ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid base64 url string', Web_Authn_Exception::BYTE_BUFFER );
			}
			return new Byte_Buffer( $bin );
		}

		/**
		 * Create a Byte_Buffer from a base64 url encoded string
		 *
		 * @param string $hex - Hex string.
		 *
		 * @return Byte_Buffer
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public static function fromHex( $hex ): Byte_Buffer {
			$bin = \hex2bin( $hex );
			if ( false === $bin ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid hex string', Web_Authn_Exception::BYTE_BUFFER );
			}
			return new Byte_Buffer( $bin );
		}

		/**
		 * Create a random Byte_Buffer
		 *
		 * @param int $length - The size.
		 *
		 * @return Byte_Buffer
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public static function randomBuffer( $length ): Byte_Buffer {
			if ( \function_exists( 'random_bytes' ) ) { // >PHP 7.0
				return new Byte_Buffer( \random_bytes( $length ) );

			} elseif ( \function_exists( 'openssl_random_pseudo_bytes' ) ) {
				return new Byte_Buffer( \openssl_random_pseudo_bytes( $length ) );

			} else {
				throw new Web_Authn_Exception( 'Byte_Buffer: cannot generate random bytes', Web_Authn_Exception::BYTE_BUFFER );
			}
		}

		// -----------------------
		// PUBLIC
		// -----------------------

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - Offset.
		 * @param int $length - Length.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getBytes( $offset, $length ): string {
			if ( $offset < 0 || $length < 0 || ( $offset + $length > $this->length ) ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid offset or length', Web_Authn_Exception::BYTE_BUFFER );
			}
			return \substr( $this->data, $offset, $length );
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - Offset.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getByteVal( $offset ): int {
			if ( $offset < 0 || $offset >= $this->length ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid offset', Web_Authn_Exception::BYTE_BUFFER );
			}
			return \ord( \substr( $this->data, $offset, 1 ) );
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $json_flags - Json flags.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getJson( $json_flags = 0 ) {
			$data = \json_decode( $this->getBinaryString(), null, 512, $json_flags );
			if ( \json_last_error() !== JSON_ERROR_NONE ) {
				throw new Web_Authn_Exception( \json_last_error_msg(), Web_Authn_Exception::BYTE_BUFFER );
			}
			return $data;
		}

		/**
		 * Returns the length
		 *
		 * @return int
		 *
		 * @since 3.0.0
		 */
		public function getLength(): int {
			return $this->length;
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - The offsets.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getUint16Val( $offset ) {
			if ( $offset < 0 || ( $offset + 2 ) > $this->length ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid offset', Web_Authn_Exception::BYTE_BUFFER );
			}
			return unpack( 'n', $this->data, $offset )[1];
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - The offsets.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getUint32Val( $offset ) {
			if ( $offset < 0 || ( $offset + 4 ) > $this->length ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid offset', Web_Authn_Exception::BYTE_BUFFER );
			}
			$val = unpack( 'N', $this->data, $offset )[1];

			// Signed integer overflow causes signed negative numbers.
			if ( $val < 0 ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Value out of integer range.', Web_Authn_Exception::BYTE_BUFFER );
			}
			return $val;
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - The offsets.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getUint64Val( $offset ) {
			if ( PHP_INT_SIZE < 8 ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: 64-bit values not supported by this system', Web_Authn_Exception::BYTE_BUFFER );
			}
			if ( $offset < 0 || ( $offset + 8 ) > $this->length ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid offset', Web_Authn_Exception::BYTE_BUFFER );
			}
			$val = unpack( 'J', $this->data, $offset )[1];

			// Signed integer overflow causes signed negative numbers.
			if ( $val < 0 ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Value out of integer range.', Web_Authn_Exception::BYTE_BUFFER );
			}

			return $val;
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - The offsets.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getHalfFloatVal( $offset ) {
			// FROM spec pseudo decode_half(unsigned char *halfp).
			$half = $this->getUint16Val( $offset );

			$exp  = ( $half >> 10 ) & 0x1f;
			$mant = $half & 0x3ff;

			if ( 0 === $exp ) {
				$val = $mant * ( 2 ** -24 );
			} elseif ( 31 !== $exp ) {
				$val = ( $mant + 1024 ) * ( 2 ** ( $exp - 25 ) );
			} else {
				$val = ( 0 === $mant ) ? INF : NAN;
			}

			return ( $half & 0x8000 ) ? -$val : $val;
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - The offsets.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getFloatVal( $offset ) {
			if ( $offset < 0 || ( $offset + 4 ) > $this->length ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid offset', Web_Authn_Exception::BYTE_BUFFER );
			}
			return unpack( 'G', $this->data, $offset )[1];
		}

		/**
		 * Returns the bytes
		 *
		 * @param int $offset - The offsets.
		 *
		 * @return string
		 *
		 * @throws Web_Authn_Exception - When url string is invalid.
		 *
		 * @since 3.0.0
		 */
		public function getDoubleVal( $offset ) {
			if ( $offset < 0 || ( $offset + 8 ) > $this->length ) {
				throw new Web_Authn_Exception( 'Byte_Buffer: Invalid offset', Web_Authn_Exception::BYTE_BUFFER );
			}
			return unpack( 'E', $this->data, $offset )[1];
		}

		/**
		 * Returns the bytes
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function getBinaryString(): string {
			return $this->data;
		}

		/**
		 * Returns the bytes
		 *
		 * @param string|Byte_Buffer $buffer - The buffer.
		 *
		 * @return bool
		 *
		 * @since 3.0.0
		 */
		public function equals( $buffer ): bool {
			if ( is_object( $buffer ) && $buffer instanceof Byte_Buffer ) {
				return $buffer->getBinaryString() === $this->getBinaryString();

			} elseif ( is_string( $buffer ) ) {
				return $buffer === $this->getBinaryString();
			}

			return false;
		}

		/**
		 * Returns the bytes
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function getHex(): string {
			return \bin2hex( $this->data );
		}

		/**
		 * Returns the bytes
		 *
		 * @return bool
		 *
		 * @since 3.0.0
		 */
		public function isEmpty(): bool {
			return 0 === $this->length;
		}


		/**
		 * JsonSerialize interface
		 * return binary data in RFC 1342-Like serialized string
		 *
		 * @return string
		 */
		public function jsonSerialize(): string {
			if ( self::$use_base_64_url_encoding ) {
				return self::_base64url_encode( $this->data );

			} else {
				return '=?BINARY?B?' . \base64_encode( $this->data ) . '?=';
			}
		}

		/**
		 * Serializable-Interface
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function serialize(): string {
			return \serialize( $this->data );
		}

		/**
		 * Serializable-Interface
		 *
		 * @param string $serialized - Serialized string.
		 *
		 * @since 3.0.0
		 */
		public function unserialize( $serialized ) {
			$this->data   = \unserialize( $serialized );
			$this->length = \strlen( $this->data );
		}

		/**
		 * (PHP 8 deprecates Serializable-Interface)
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public function __serialize(): array {
			return array(
				'data' => \serialize( $this->data ),
			);
		}

		/**
		 * Object to string
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public function __toString(): string {
			return $this->getHex();
		}

		/**
		 * (PHP 8 deprecates Serializable-Interface)
		 *
		 * @param array $data - The data.
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public function __unserialize( $data ) {
			if ( $data && isset( $data['data'] ) ) {
				$this->data   = \unserialize( $data['data'] );
				$this->length = \strlen( $this->data );
			}
		}

		// -----------------------
		// PROTECTED STATIC
		// -----------------------

		/**
		 * Base64 url decoding
		 *
		 * @param string $data - The data.
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		protected static function _base64url_decode( $data ): string {
			return \base64_decode( \strtr( $data, '-_', '+/' ) . \str_repeat( '=', 3 - ( 3 + \strlen( $data ) ) % 4 ) );
		}

		/**
		 * Base64 url encoding
		 *
		 * @param string $data - The data.
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		protected static function _base64url_encode( $data ): string {
			return \rtrim( \strtr( \base64_encode( $data ), '+/', '-_' ), '=' );
		}
	}
}
