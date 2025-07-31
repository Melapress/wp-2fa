<?php
/**
 * Responsible for the plugin methods
 *
 * @package    wp2fa
 * @subpackage admin_controllers
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Controllers;

use WP2FA\WP2FA;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Helpers\Methods_Helper;
use WP2FA\Extensions\OutOfBand\Out_Of_Band;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Methods class
 */
if ( ! class_exists( '\WP2FA\Admin\Controllers\Methods' ) ) {

	/**
	 * All the methods related functionality must be extracted from this class. Responsible only for global methods data, not the user method related stuff.
	 *
	 * @since 2.2.0
	 */
	class Methods {

		/**
		 * Holds all the enabled methods in the plugin
		 *
		 * @var array
		 *
		 * @since 2.2.0
		 */
		private static $enabled_methods = null;

		/**
		 * Works our a list of available 2FA methods. It doesn't include the disabled ones.
		 *
		 * @param string $role - The role to get the methods for. Default is 'global'.
		 *
		 * @return string[]
		 *
		 * @since 2.0.0
		 * @since 2.9.0 - Added role parameter so the methods can be filtered base on the user role.
		 */
		public static function get_available_2fa_methods( string $role = 'global' ): array {
			$available_methods = array();

			/**
			 * Add an option for external providers to implement their own 2fa methods and set them as available.
			 *
			 * @param array $available_methods - The array with all the available methods.
			 *
			 * @since 2.0.0
			 */
			return \apply_filters( WP_2FA_PREFIX . 'available_2fa_methods', $available_methods, $role );
		}

		/**
		 * Returns array with all the enabled methods in the plugin for the current role
		 *
		 * @param string $role - Role to extract data for.
		 *
		 * @return array
		 *
		 * @since 2.2.0
		 */
		public static function get_enabled_methods( $role = 'global' ): array {
			$role = \sanitize_text_field( $role );

			if ( null === self::$enabled_methods || ! isset( self::$enabled_methods[ $role ] ) ) {
				self::$enabled_methods[ $role ] = array();
				$providers                      = Settings::get_providers();

				foreach ( $providers as $provider ) {
					if ( Settings::is_provider_enabled_for_role( $role, $provider ) ) {
						$method = Methods_Helper::get_method_by_provider_name( $provider );
						if ( $method && \method_exists( $method, 'is_secondary' ) && $method::is_secondary() ) {
							continue;
						} elseif ( class_exists( '\WP2FA\Extensions\OutOfBand\Out_Of_Band', false ) && Out_Of_Band::METHOD_NAME === $provider ) {
							self::$enabled_methods[ $role ][ $provider ] = WP2FA::get_wp2fa_setting( 'enable_' . \sanitize_key( $provider ) . '_email', false, false, $role );
						} else {
							self::$enabled_methods[ $role ][ $provider ] = WP2FA::get_wp2fa_setting( 'enable_' . \sanitize_key( $provider ), false, false, $role );
						}
					}
				}

				self::$enabled_methods[ $role ] = array_filter( self::$enabled_methods[ $role ] );
			}

			return self::$enabled_methods;
		}

		/**
		 * Checks if given methog is enabled for given role and returns true or false.
		 *
		 * @param string $provider_name - The name of the method.
		 * @param string $role - The name of the role to check for. Default is 'global.
		 *
		 * @return boolean
		 *
		 * @since 2.9.0
		 */
		public static function is_method_enabled_for_role( string $provider_name, string $role = 'global' ): bool {
			$role = \sanitize_text_field( $role );

			if ( ! isset( self::$enabled_methods[ $role ] ) ) {
				self::get_enabled_methods( $role );
			}

			if ( isset( self::$enabled_methods[ $role ][ $provider_name ] ) && self::$enabled_methods[ $role ][ $provider_name ] ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns text with the number of methods supported for the given role
		 *
		 * @since 2.2.0
		 *
		 * @return string
		 */
		public static function get_number_of_methods_text() {
			return esc_html__(
				'There are {available_methods_count} methods available to choose from for 2FA:',
				'wp-2fa'
			);
		}
	}
}
