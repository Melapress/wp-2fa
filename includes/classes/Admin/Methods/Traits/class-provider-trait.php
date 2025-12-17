<?php
/**
 * Responsible for the plugin login attempts
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\SettingsPages\Settings_Page_Policies;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Methods\Traits\Providers' ) ) {
	/**
	 * Responsible for the providers basic functionality.
	 *
	 * @since 2.9.2
	 */
	trait Providers {

		/**
		 * Stores the globally enabled methods (if it is enables as a global method or in some of the roles)
		 *
		 * @var array
		 *
		 * @since 3.0.0
		 */
		private static $globally_enabled = array();

		/**
		 * Inits all of the common hooks for all of the providers.
		 *
		 * @return void
		 *
		 * @since 2.9.2
		 */
		public static function always_init() {

			\add_filter( WP_2FA_PREFIX . 'providers', array( __CLASS__, 'provider' ) );
		}

		/**
		 * Adds provider to the global providers array
		 *
		 * @param array $providers - Array with all currently supported providers.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function provider( array $providers ) {
			$providers[ static::class ] = static::METHOD_NAME;

			return $providers;
		}

		/**
		 * Extracts the selected value from the global settings (if set), and adds it to the output array
		 *
		 * @param array $output - The array with output values.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function return_default_selection( array $output ) {
			// No method is enabled, fall back to previous selected one - we don't want to break the logic.
			$provider_enabled = Settings_Utils::get_setting_role( null, self::POLICY_SETTINGS_NAME );

			if ( $provider_enabled ) {
				$output[ static::POLICY_SETTINGS_NAME ] = $provider_enabled;
			}

			return $output;
		}

		/**
		 * Returns the status of the mail method (enabled | disabled) for the current user role
		 *
		 * @param string $role - The name of the role to check for.
		 *
		 * @since 3.0.0
		 *
		 * @return boolean
		 */
		public static function is_enabled( ?string $role = null ): bool {
			if ( null === static::$enabled || ! isset( static::$enabled[ $role ] ) ) {
				static::$enabled[ $role ] = empty( Settings_Utils::get_setting_role( $role, static::POLICY_SETTINGS_NAME ) ) ? false : true;
			}

			return static::$enabled[ $role ];
		}

		/**
		 * Checks if given provided is enabled globally (for some of the roles or as a global provider)
		 *
		 * @return boolean
		 *
		 * @since 3.1.0
		 */
		public static function is_globally_enabled() {
			if ( empty( self::$globally_enabled ) || ! isset( self::$globally_enabled[ static::METHOD_NAME ] ) ) {
				$roles = WP_Helper::get_roles();

				self::$globally_enabled[ static::METHOD_NAME ] = false;

				if ( static::is_enabled() ) {
					// Short circuit - if it is globally enabled - no need to check every role.
					self::$globally_enabled[ static::METHOD_NAME ] = true;
				} else {
					foreach ( $roles as $role ) {
						if ( static::is_enabled( $role ) ) {
							self::$globally_enabled[ static::METHOD_NAME ] = true;

							// Once is enough - bounce.
							break;
						}
					}
				}
			}

			return self::$globally_enabled[ static::METHOD_NAME ];
		}

		/**
		 * Checks value from input array ($_POST) and based on that prevents plugin to show "no method enabled", if the user selected given method
		 *
		 * @param boolean $status - Current status of the filter.
		 * @param array   $input - The array with values to check for this method.
		 *
		 * @return bool
		 *
		 * @since 3.1.0
		 */
		public static function method_enabled( bool $status, array $input ) {
			if ( isset( $input[ static::POLICY_SETTINGS_NAME ] ) ) {
				return false;
			}

			return $status;
		}

		/**
		 * Disable method globally (removes it from all roles and global settings)
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		public static function disable_globally() {
			self::$globally_enabled[ static::METHOD_NAME ] = false;

			if ( class_exists( Role_Settings_Controller::class, false ) ) {

				Role_Settings_Controller::remove_roles_setting( 'enable_' . static::METHOD_NAME );

			}

			$policies = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME );

			if ( isset( $policies[ 'enable_' . static::METHOD_NAME ] ) ) {
				$policies[ 'enable_' . static::METHOD_NAME ] = '';

				\remove_filter( 'sanitize_option_wp_2fa_policy', array( Settings_Page_Policies::class, 'validate_and_sanitize' ) );

				Settings_Utils::update_option( WP_2FA_POLICY_SETTINGS_NAME, $policies );
			}
		}
	}
}
