<?php
/**
 * Responsible for the plugin wizard ordering
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Helpers\Methods_Helper;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
/**
 * Responsible for the login attempts
 *
 * @since 2.6.0
 */
trait Methods_Wizards_Trait {

	/**
	 * Returns the order in the wizard
	 *
	 * @param string $role - The name of the role - could be empty.
	 * @param array  $methods - The array with currently collected methods.
	 *
	 * @return integer
	 *
	 * @since 2.6.0
	 */
	public static function get_order( string $role = null, array $methods = array() ): int {
		if ( null !== $role && ! empty( $role ) && class_exists( '\WP2FA\Extensions\RoleSettings\Role_Settings_Controller' ) ) {
			$methods_order = Role_Settings_Controller::get_setting( $role, Methods_Helper::POLICY_SETTINGS_NAME );

			if ( \is_array( $methods_order ) ) {
				$methods_order = \array_flip( $methods_order );

				if ( isset( $methods_order[ self::get_main_class()::METHOD_NAME ] ) ) {
					static::$order = (int) $methods_order[ self::get_main_class()::METHOD_NAME ];
				}
			}
		} else {
			$use_role_setting = null;
			if ( null === $role || '' === trim( (string) $role ) ) {
				$use_role_setting = \WP_2FA_PREFIX . 'no-user';
			}

			$methods_order = Settings::get_role_or_default_setting( Methods_Helper::POLICY_SETTINGS_NAME, $use_role_setting, $role, true );

			if ( \is_array( $methods_order ) ) {
				$methods_order = \array_flip( $methods_order );

				if ( isset( $methods_order[ self::get_main_class()::METHOD_NAME ] ) ) {
					static::$order = (int) $methods_order[ self::get_main_class()::METHOD_NAME ];
				}
			}
		}

		if ( isset( $methods[ static::$order ] ) ) {
			// Obviously we have a problem here - such order already exists in the methods array, so grab the biggest order and increase it.
			// TODO: maybe we need to update the settings for that method as well ?

			static::$order = max( array_keys( $methods ) );

			++static::$order;
		}

		return static::$order;
	}

	/**
	 * Returns the main class of the given wizard steps class.
	 *
	 * @return string
	 *
	 * @since 2.6.0
	 */
	public static function get_main_class(): string {
		return static::$main_class;
	}

	/**
	 * Creates hidden field for the method order
	 *
	 * @param string $role - The name of the role (if present).
	 *
	 * @return string
	 *
	 * @since 2.6.0
	 */
	public static function hidden_order_setting( string $role = null ): string {
		$name_prefix = WP_2FA_POLICY_SETTINGS_NAME;
		if ( null !== $role && '' !== trim( (string) $role ) ) {
			$name_prefix .= "[{$role}]";
		}
		$hidden_field = '<input type="hidden" name="' . \esc_attr( $name_prefix ) . '[methods_order][]" value="' . \esc_attr( self::get_main_class()::METHOD_NAME ) . '">';

		return $hidden_field;
	}
}
