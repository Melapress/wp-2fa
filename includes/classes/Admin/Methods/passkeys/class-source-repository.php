<?php
/**
 * Responsible for the register API endpoints
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Passkeys;

use WP2FA\Methods\Passkeys\Web_Authn;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Public Key Credential Source Repository.
 */
class Source_Repository {

	/**
	 * Meta key prefix.
	 */
	public const PASSKEYS_META = WP_2FA_PREFIX . 'passkey_';

	/**
	 * Find a credential source by its credential ID.
	 *
	 * @param string $public_key_credential_id The credential ID to find.
	 *
	 * @return null|bool The credential source, if found.
	 *
	 * @since 3.0.0
	 */
	public static function find_one_by_credential_id( string $public_key_credential_id ) {
		global $wpdb;

		// Sanitize incoming id and build meta key.
		$public_key_credential_id = sanitize_text_field( $public_key_credential_id );
		$meta_key                = self::PASSKEYS_META . $public_key_credential_id;
		$row                     = $wpdb->get_row( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key ) );

		if ( ! $row || empty( $row->meta_value ) ) {
			return null;
		}

		try {
			$data = json_decode( (string) $row->meta_value, true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return null;
		}

		return $data;
	}

	/**
	 * Find all credential sources for a given user entity.
	 *
	 * @param \WP_User $user - The user entity to find credential sources for.
	 *
	 * @return PublicKeyCredentialSource[] The credential sources, if found.
	 *
	 * @throws \Exception If the user is not found.
	 *
	 * @since 3.0.0
	 */
	public static function find_all_for_user( \WP_User $user ): array {

		global $wpdb;

		// @free:start
			$limit = ' LIMIT 1';
		// @free:end


		// Use esc_like to build a safe LIKE pattern for meta_key search.
		$like_pattern = $wpdb->esc_like( self::PASSKEYS_META ) . '%';
		$public_keys  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE %s AND user_id = %d ",
				$like_pattern,
				$user->ID,
			) . $limit
		);

		if ( ! $public_keys ) {
			return array();
		}

		$public_keys = array_map(
			function ( $public_key ) {
				return json_decode( $public_key->meta_value, true );
			},
			$public_keys
		);

		// Removes null values.
		$public_keys = array_filter( $public_keys );

		return array_map(
			function ( $public_key ) {
				return self::create_from_array( $public_key );
			},
			$public_keys
		);
	}

	/**
	 * Prepares the raw array from DB for displaying.
	 *
	 * @param array $array - The array with raw values, must contain 'extra' key.
	 *
	 * @return array
	 *
	 * @since 3.0.0
	 */
	private static function create_from_array( array $array ): array {
		$ret_arr = array();
		if ( isset( $array['extra'] ) ) {

			$ret_arr['name']          = $array['extra']['name'] ?? '';
			$ret_arr['aaguid']        = $array['extra']['aaguid'] ?? '';
			$ret_arr['created']       = $array['extra']['created'] ?? '';
			$ret_arr['last_used']     = $array['extra']['last_used'] ?? '';
			$ret_arr['enabled']       = $array['extra']['enabled'] ?? '';
			$ret_arr['credential_id'] = $array['extra']['credential_id'] ?? '';

		}

		return $ret_arr;
	}

	/**
	 * Save a new credential source.
	 *
	 * @param \WP_USer $user - The WP user to store the data to.
	 * @param string[] $extra_data  -Extra data to store.
	 *
	 * @return void
	 *
	 * @throws \Exception If the user is not found.
	 *
	 * @since 3.0.0
	 */
	public static function save_credential_source( $user, array $extra_data = array() ): void {

		if ( ! $user instanceof \WP_User ) {
			throw new \Exception( 'User not found.', 400 );
		}

		// Extra data to store. Sanitize known scalar values; allow binary/public_key fields untouched.
		$public_key = array( 'extra' => array() );
		foreach ( $extra_data as $key => $value ) {
			if ( in_array( $key, array( 'public_key', 'credential_id', 'transports', 'aaguid' ), true ) ) {
				$public_key['extra'][ $key ] = $value;
			} else {
				$public_key['extra'][ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value;
			}
		}

		// Store the public key credential source. And need to add extra slashes to escape the slashes in the JSON.
		$public_key_json = addcslashes( \wp_json_encode( $public_key, JSON_UNESCAPED_SLASHES ), '\\' );

		if ( ! class_exists( 'ParagonIE_Sodium_Core_Base64_UrlSafe', false ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Base64/UrlSafe.php';
			require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Util.php';
		}

		// Credential id should exist; sanitize minimally (it's used for encoded meta key).
		$cred_id = isset( $extra_data['credential_id'] ) ? sanitize_text_field( (string) $extra_data['credential_id'] ) : '';
		$meta_key = self::PASSKEYS_META . \ParagonIE_Sodium_Core_Base64_UrlSafe::encodeUnpadded( $cred_id );

		\update_user_meta( $user->ID, $meta_key, $public_key_json );
	}

	/**
	 * Remove not safe characters from a given string.
	 *
	 * @param string $encoded_string - The string to sanitize.
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException - Exception thrown if an argument is not of the expected type.
	 *
	 * @since 3.0.0
	 */
	private static function decode_no_padding( string $encoded_string ): string {
		$src_len = self::safe_str_len( $encoded_string );
		if ( 0 === $src_len ) {
			return '';
		}
		if ( ( $src_len & 3 ) === 0 ) {
			if ( '=' === $encoded_string[ $src_len - 1 ] ) {
				throw new \InvalidArgumentException(
					"decodeNoPadding() doesn't tolerate padding"
				);
			}
			if ( ( $src_len & 3 ) > 1 ) {
				if ( '=' === $encoded_string[ $src_len - 2 ] ) {
					throw new \InvalidArgumentException(
						"decodeNoPadding() doesn't tolerate padding"
					);
				}
			}
		}

		if ( ! class_exists( 'ParagonIE_Sodium_Core_Base64_UrlSafe', false ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Base64/UrlSafe.php';
			require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Util.php';
		}

		return \ParagonIE_Sodium_Core_Base64_UrlSafe::decode(
			$encoded_string,
			true
		);
	}

	/**
	 * Decode from base64 into binary
	 *
	 * Base64 character set "./[A-Z][a-z][0-9]"
	 *
	 * @param string $src - The source string.
	 * @param bool   $strict_padding - Should use strict padding or not?.
	 *
	 * @return string
	 *
	 * @since 3.0.0
	 */
	public static function base64_url_safe( $src, ?bool $strict_padding = true ) {
		return \ParagonIE_Sodium_Core_Base64_UrlSafe::decode(
			$src,
			$strict_padding
		);
	}

	/**
	 * Safe string length
	 *
	 * @ref mbstring.func_overload
	 *
	 * @param string $str - String to be encoded and prepared.
	 *
	 * @return int
	 */
	private static function safe_str_len( string $str ): int {
		if ( \function_exists( 'mb_strlen' ) ) {
			// mb_strlen in PHP 7.x can return false.

			return (int) \mb_strlen( $str, '8bit' );
		} else {
			return \strlen( $str );
		}
	}

	/**
	 * Delete a credential source.
	 *
	 * @param string   $public_key_credential_source - The credential source to delete.
	 * @param \WP_user $user - The user which credential needs to be removed.
	 *
	 * @return void
	 *
	 * @throws \Exception If the user is not found.
	 *
	 * @since 3.0.0
	 */
	public static function delete_credential_source( $public_key_credential_source, $user ) {

		if ( ! $user instanceof \WP_User ) {
			throw new \Exception( 'User not found.', 404 );
		}

		// Allow the user themselves or a caller with capability to edit the user.
		$current = \get_current_user_id();
		if ( $current !== $user->ID && ! current_user_can( 'edit_user', $user->ID ) ) {
			throw new \Exception( 'User not found or insufficient permissions.', 404 );
		}

		$meta_key   = self::PASSKEYS_META . $public_key_credential_source;
		$is_success = \delete_user_meta( $user->ID, $meta_key );

		if ( ! $is_success ) {
			throw new \Exception( 'Unable to delete credential source.', 500 );
		}
	}
}
