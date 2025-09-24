<?php
/**
 * Responsible for various settings manipulations.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 *
 * @since 1.7.0
 */

declare(strict_types=1);

namespace WP2FA\Utils;

use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

if ( ! class_exists( '\WP2FA\Utils\Settings_Utils' ) ) {

	/**
	 * Utility class handling settings CRUD.
	 *
	 * @package WP2FA\Utils
	 *
	 * @since 1.7.0
	 */
	class Settings_Utils {

		/**
		 * Creates a hash based on the passed settings array.
		 *
		 * @param array $settings - Settings array.
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public static function create_settings_hash( array $settings ): string {
			return md5( json_encode( $settings ) );
		}

		/**
		 * Returns an option by given name
		 *
		 * @param string $setting_name - The name of the option.
		 * @param mixed  $default_value - The default value if there is no one stored.
		 *
		 * @return mixed
		 *
		 * @since 2.0.0
		 */
		public static function get_option( $setting_name, $default_value = false ) {
			$setting_name          = sanitize_key( $setting_name );
			$prefixed_setting_name = self::setting_prefixer( $setting_name );

			return ( WP_Helper::is_multisite() ) ? get_network_option( null, $prefixed_setting_name, $default_value ) : get_option( $prefixed_setting_name, $default_value );
		}

		/**
		 * Updates an option by a given name with a given value
		 *
		 * @param string $setting_name - The name of the setting to update.
		 * @param mixed  $new_value - The value to be stored.
		 *
		 * @return mixed
		 *
		 * @since 2.0.0
		 */
		public static function update_option( $setting_name, $new_value ) {
			$setting_name          = \sanitize_key( $setting_name );
			$new_value             = $new_value;
			$prefixed_setting_name = self::setting_prefixer( $setting_name );

			return ( WP_Helper::is_multisite() ) ? \update_network_option( null, $prefixed_setting_name, $new_value ) : \update_option( $prefixed_setting_name, $new_value, false );
		}

		/**
		 * Deletes an option by a given name
		 *
		 * @param string $setting_name - The name of the option to delete.
		 *
		 * @return mixed
		 *
		 * @since 2.0.0
		 */
		public static function delete_option( $setting_name ) {
			$setting_name          = sanitize_key( $setting_name );
			$prefixed_setting_name = self::setting_prefixer( $setting_name );

			return ( WP_Helper::is_multisite() ) ? \delete_network_option( null, $prefixed_setting_name ) : \delete_option( $prefixed_setting_name );
		}

		/**
		 * Created a prefixed setting name from supplied string.
		 *
		 * @param  string $setting_name - The name of the setting.
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		private static function setting_prefixer( $setting_name ) {
			// Ensure we have not already been passed a prefixed setting name.
			return ( strpos( $setting_name, 'wp_2fa_' ) === 0 ) ? $setting_name : WP_2FA_PREFIX . $setting_name;
		}

		/**
		 * Converts a string (e.g. 'yes' or 'no') to a bool.
		 *
		 * @param string $string String to convert.
		 *
		 * @return bool
		 *
		 * @since 2.0.0
		 */
		public static function string_to_bool( $string ) {
			$string = sanitize_text_field( $string );
			return is_bool( $string ) ? $string : ( 'yes' === $string || 1 === $string || 'true' === $string || '1' === $string || 'on' === $string || 'enable' === $string );
		}

		/**
		 * Converts a bool to a 'yes' or 'no'.
		 *
		 * @param bool $bool String to convert.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function bool_to_string( $bool ) {
			if ( ! is_bool( $bool ) ) {
				$bool = self::string_to_bool( $bool );
			}
			return true === $bool ? 'yes' : 'no';
		}

		/**
		 * Gets a setting for a specific role. If no role is specified it will fall back to the default.
		 *
		 * @param string|null $role - The name of the role.
		 * @param string      $setting_name - The name of the setting.
		 * @param bool        $default - Set the default on empty.
		 *
		 * @return mixed
		 *
		 * @since 3.0.0
		 */
		public static function get_setting_role( ?string $role, string $setting_name, bool $default = false ) {
			$role         = \sanitize_key( $role );
			$setting_name = \sanitize_key( $setting_name );

			if ( class_exists( Role_Settings_Controller::class, false ) ) {
				return Role_Settings_Controller::get_setting( $role, $setting_name, $default );
			}

			return Settings::get_role_or_default_setting( $setting_name, null, $role, $default );
		}
	}
}
