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

use WP2FA\Methods\Passkeys\Byte_Buffer;
use WP2FA\Methods\Passkeys\Web_Authn_Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Passkeys Byte_Buffer
 */
if ( ! class_exists( '\WP2FA\Methods\Passkeys\Cbor_Decoder' ) ) {

	/**
	 * Modified version of https://github.com/madwizard-thomas/Web_Authn-server/blob/master/src/Format/Cbor_Decoder.php
	 */
	class Cbor_Decoder {

		public const CBOR_MAJOR_UNSIGNED_INT = 0;
		public const CBOR_MAJOR_TEXT_STRING  = 3;
		public const CBOR_MAJOR_FLOAT_SIMPLE = 7;
		public const CBOR_MAJOR_NEGATIVE_INT = 1;
		public const CBOR_MAJOR_ARRAY        = 4;
		public const CBOR_MAJOR_TAG          = 6;
		public const CBOR_MAJOR_MAP          = 5;
		public const CBOR_MAJOR_BYTE_STRING  = 2;

		/**
		 * Decodes the given string
		 *
		 * @param Byte_Buffer|string $buf_or_bin - Default comment.
		 *
		 * @return mixed
		 *
		 * @throws Web_Authn_Exception - Default comment.
		 *
		 * @since 3.1.0
		 */
		public static function decode( $buf_or_bin ) {
			$buf = $buf_or_bin instanceof Byte_Buffer ? $buf_or_bin : new Byte_Buffer( $buf_or_bin );

			$offset = 0;
			$result = self::_parse_item( $buf, $offset );
			if ( $offset !== $buf->getLength() ) {
				throw new Web_Authn_Exception( 'Unused bytes after data item.', Web_Authn_Exception::CBOR );
			}
			return $result;
		}

		/**
		 * Decodes the given string in place
		 *
		 * @param Byte_Buffer|string $buf_or_bin - Default comment.
		 * @param int                $start_offset - Default comment.
		 * @param int|null           $end_offset - Default comment.
		 *
		 * @return mixed
		 *
		 * @since 3.1.0
		 */
		public static function decode_in_place( $buf_or_bin, $start_offset, &$end_offset = null ) {
			$buf = $buf_or_bin instanceof Byte_Buffer ? $buf_or_bin : new Byte_Buffer( $buf_or_bin );

			$offset     = $start_offset;
			$data       = self::_parse_item( $buf, $offset );
			$end_offset = $offset;
			return $data;
		}

		// ---------------------
		// protected
		// ---------------------

		/**
		 * Pasrses a single item
		 *
		 * @param Byte_Buffer $buf - Default comment.
		 * @param int         $offset - Default comment.
		 *
		 * @return mixed
		 */
		protected static function _parse_item( Byte_Buffer $buf, &$offset ) {
			$first = $buf->getByteVal( $offset++ );
			$type  = $first >> 5;
			$val   = $first & 0b11111;

			if ( self::CBOR_MAJOR_FLOAT_SIMPLE === $type ) {
				return self::_parse_float_simple( $val, $buf, $offset );
			}

			$val = self::_parse_extra_length( $val, $buf, $offset );

			return self::_parse_item_data( $type, $val, $buf, $offset );
		}

		/**
		 * Parses a single item
		 *
		 * @param [type]                              $val - Default comment.
		 * @param \WP2FA\Methods\Passkeys\Byte_Buffer $buf - Default comment.
		 * @param [type]                              $offset - Default comment.
		 *
		 * @return mixed
		 *
		 * @since 3.0.0
		 *
		 * @throws Web_Authn_Exception - Default comment.
		 */
		protected static function _parse_float_simple( $val, Byte_Buffer $buf, &$offset ) {
			switch ( $val ) {
				case 24:
					$val = $buf->getByteVal( $offset );
					++$offset;
					return self::_parse_simple( $val );

				case 25:
					$float_value = $buf->getHalfFloatVal( $offset );
					$offset     += 2;
					return $float_value;

				case 26:
					$float_value = $buf->getFloatVal( $offset );
					$offset     += 4;
					return $float_value;

				case 27:
					$float_value = $buf->getDoubleVal( $offset );
					$offset     += 8;
					return $float_value;

				case 28:
				case 29:
				case 30:
					throw new Web_Authn_Exception( 'Reserved value used.', Web_Authn_Exception::CBOR );

				case 31:
					throw new Web_Authn_Exception( 'Indefinite length is not supported.', Web_Authn_Exception::CBOR );
			}

			return self::_parse_simple( $val );
		}

		/**
		 * Parses a simple value
		 *
		 * @param int $val - Default comment.
		 *
		 * @return mixed
		 *
		 * @throws Web_Authn_Exception - Default comment.
		 */
		protected static function _parse_simple( $val ) {
			if ( 20 === $val ) {
				return false;
			}
			if ( 21 === $val ) {
				return true;
			}
			if ( 22 === $val ) {
				return null;
			}
			throw new Web_Authn_Exception( sprintf( 'Unsupported simple value %d.', $val ), Web_Authn_Exception::CBOR );
		}

		/**
		 * Parses the extra length information
		 *
		 * @param [type]                              $val - Default comment.
		 * @param \WP2FA\Methods\Passkeys\Byte_Buffer $buf - Default comment.
		 * @param [type]                              $offset - Default comment.
		 *
		 * @return mixed
		 *
		 * @since 3.0.0
		 *
		 * @throws Web_Authn_Exception - Default comment.
		 */
		protected static function _parse_extra_length( $val, Byte_Buffer $buf, &$offset ) {
			switch ( $val ) {
				case 24:
					$val = $buf->getByteVal( $offset );
					++$offset;
					break;

				case 25:
					$val     = $buf->getUint16Val( $offset );
					$offset += 2;
					break;

				case 26:
					$val     = $buf->getUint32Val( $offset );
					$offset += 4;
					break;

				case 27:
					$val     = $buf->getUint64Val( $offset );
					$offset += 8;
					break;

				case 28:
				case 29:
				case 30:
					throw new Web_Authn_Exception( 'Reserved value used.', Web_Authn_Exception::CBOR );

				case 31:
					throw new Web_Authn_Exception( 'Indefinite length is not supported.', Web_Authn_Exception::CBOR );
			}

			return $val;
		}

		/**
		 * Paarses the item data
		 *
		 * @param [type]                              $type - Default comment.
		 * @param [type]                              $val - Default comment.
		 * @param \WP2FA\Methods\Passkeys\Byte_Buffer $buf - Default comment.
		 * @param [type]                              $offset - Default comment.
		 *
		 * @return mixed
		 *
		 * @since 3.0.0
		 *
		 * @throws Web_Authn_Exception - Default comment.
		 */
		protected static function _parse_item_data( $type, $val, Byte_Buffer $buf, &$offset ) {
			switch ( $type ) {
				case self::CBOR_MAJOR_UNSIGNED_INT: // uint.
					return $val;

				case self::CBOR_MAJOR_NEGATIVE_INT:
					return -1 - $val;

				case self::CBOR_MAJOR_BYTE_STRING:
					$data    = $buf->getBytes( $offset, $val );
					$offset += $val;
					return new Byte_Buffer( $data ); // bytes.

				case self::CBOR_MAJOR_TEXT_STRING:
					$data    = $buf->getBytes( $offset, $val );
					$offset += $val;
					return $data; // UTF-8.

				case self::CBOR_MAJOR_ARRAY:
					return self::_parse_array( $buf, $offset, $val );

				case self::CBOR_MAJOR_MAP:
					return self::_parse_map( $buf, $offset, $val );

				case self::CBOR_MAJOR_TAG:
					return self::_parse_item( $buf, $offset ); // 1 embedded data item.
			}

			// This should never be reached.
			throw new Web_Authn_Exception( sprintf( 'Unknown major type %d.', $type ), Web_Authn_Exception::CBOR );
		}

		/**
		 * Parses a single item
		 *
		 * @param \WP2FA\Methods\Passkeys\Byte_Buffer $buf - Default comment.
		 * @param [type]                              $offset - Default comment.
		 * @param [type]                              $count - Default comment.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 *
		 * @throws Web_Authn_Exception - Default comment.
		 */
		protected static function _parse_map( Byte_Buffer $buf, &$offset, $count ) {
			$map = array();

			for ( $i = 0; $i < $count; $i++ ) {
				$map_key = self::_parse_item( $buf, $offset );
				$map_val = self::_parse_item( $buf, $offset );

				if ( ! \is_int( $map_key ) && ! \is_string( $map_key ) ) {
					throw new Web_Authn_Exception( 'Can only use strings or integers as map keys', Web_Authn_Exception::CBOR );
				}

				$map[ $map_key ] = $map_val; // todo dup.
			}
			return $map;
		}

		/**
		 * Parses a single item
		 *
		 * @param \WP2FA\Methods\Passkeys\Byte_Buffer $buf - Default comment.
		 * @param [type]                              $offset - Default comment.
		 * @param [type]                              $count - Default comment.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 *
		 * @throws Web_Authn_Exception - Default comment.
		 */
		protected static function _parse_array( Byte_Buffer $buf, &$offset, $count ) {
			$arr = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$arr[] = self::_parse_item( $buf, $offset );
			}

			return $arr;
		}
	}
}
