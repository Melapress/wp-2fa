<?php
namespace WP2FA\Utils;

use WP2FA\WP2FA as WP2FA;

/**
 * Utility class hanlding settings CRUD.
 *
 * @package WP2FA\Utils
 * @since 1.7.0
 */
class SettingsUtils {

	/**
	 * Creates a hash based on the passed settings array.
	 *
	 * @param array $settings - Settings array.
	 * @return string
	 */
	public static function create_settings_hash( $settings ) {
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
	 * Created a prefixed setting name from suppplied string.
	 *
	 * @param  string $setting_name
	 * @return string
	 */
	private static function setting_prefixer( $setting_name ) {
		// Ensure we have not already been passed a prefixed setting name.
		return ( strpos( $setting_name, 'wp_2fa_' ) === 0 ) ? $setting_name : WP_2FA_PREFIX . $setting_name;
	}
}
