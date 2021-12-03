<?php
namespace WP2FA\Utils;

use WP2FA\WP2FA as WP2FA;

/**
 * Utility class handling settings CRUD.
 *
 * @package WP2FA\Utils
 * @since 1.7.0
 */
class SettingsUtils {

	/**
	 * Creates a hash based on the passed settings array.
	 *
	 * @param array $settings - Settings array.
	 *
	 * @return string
	 */
	public static function create_settings_hash( array $settings ): string {
		return md5( json_encode( $settings ) );
	}

	public static function get_option( $setting_name, $default_value = false ) {
		$prefixed_setting_name = self::setting_prefixer( $setting_name );
		return ( WP2FA::is_this_multisite() ) ? get_network_option( null, $prefixed_setting_name, $default_value ) : get_option( $prefixed_setting_name, $default_value );
	}

	public static function update_option( $setting_name, $new_value ) {
		$prefixed_setting_name = self::setting_prefixer( $setting_name );
		return ( WP2FA::is_this_multisite() ) ? update_network_option( null, $prefixed_setting_name, $new_value ) : update_option( $prefixed_setting_name, $new_value, true );
	}

	public static function delete_option( $setting_name ) {
		$prefixed_setting_name = self::setting_prefixer( $setting_name );
		return ( WP2FA::is_this_multisite() ) ? delete_network_option( null, $prefixed_setting_name ) : delete_option( $prefixed_setting_name );
	}

	/**
	 * Created a prefixed setting name from supplied string.
	 *
	 * @param  string $setting_name
	 * @return string
	 */
	private static function setting_prefixer( $setting_name ) {
		// Ensure we have not already been passed a prefixed setting name.
		return ( strpos( $setting_name, 'wp_2fa_' ) === 0 ) ? $setting_name : WP_2FA_PREFIX . $setting_name;
	}

	/**
	 * Converts a string (e.g. 'yes' or 'no') to a bool.
	 *
	 * @since 2.0.0
	 * @param string $string String to convert.
	 * @return bool
	 */
	public static function string_to_bool( $string ) {
		return is_bool( $string ) ? $string : ( 'yes' === $string || 1 === $string || 'true' === $string || '1' === $string || 'on' === $string || 'enable' === $string);
	}

	/**
	 * Converts a bool to a 'yes' or 'no'.
	 *
	 * @since 2.0.0
	 * @param bool $bool String to convert.
	 * @return string
	 */
	public static function bool_to_string( $bool ) {
		if ( ! is_bool( $bool ) ) {
			$bool = self::string_to_bool( $bool );
		}
		return true === $bool ? 'yes' : 'no';
	}
}
