<?php
/**
 * Responsible for the User's operations
 *
 * @package    wp2fa
 * @subpackage helpers
 * @since      2.6.0
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Helpers;

use WP2FA\Admin\Helpers\Classes_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * User's settings class
 */
if ( ! class_exists( '\WP2FA\Admin\Helpers\Methods_Helper' ) ) {

	/**
	 * All the user related settings must go trough this class.
	 *
	 * @since 2.6.0
	 */
	class Methods_Helper {
		const METHODS_NAMESPACE = '\WP2FA\Methods';

		const POLICY_SETTINGS_NAME = 'methods_order';

		/**
		 * Cached methods array
		 *
		 * @var array
		 *
		 * @since 2.7.0
		 */
		public static $methods = array();

		/**
		 * Inits the class and initializes all the methods
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function init() {

			foreach ( self::get_methods() as $method ) {
				if ( method_exists( $method, 'init' ) ) {
					call_user_func_array( array( $method, 'init' ), array() );
				}
			}

			\add_action( WP_2FA_PREFIX . 'methods_setup', array( __CLASS__, 'methods_settings' ), 10, 3 );
			\add_filter( WP_2FA_PREFIX . 'filter_output_content', array( __CLASS__, 'settings_store' ), 10, 2 );
			\add_action( WP_2FA_PREFIX . 'methods_options', array( __CLASS__, 'methods_options' ) );
			\add_action( WP_2FA_PREFIX . 'methods_reconfigure_options', array( __CLASS__, 'methods_re_configure' ) );
		}

		/**
		 * Sets the methods in correct order for re-configuring. Checks the selected method for the user and puts it on top of the list
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function methods_re_configure() {
			$role = User_Helper::get_user_role();

			/**
			 * Option to re-configure the methods - all the methods are called and their order and code is collected. Then the currently selected method is positioned on top and methods are shown in order. That is called in the user profile page.
			 *
			 * @param array - All the collected methods and their order.
			 * @param string $role - The role of the current user
			 *
			 * @since 2.6.0
			 */
			$methods = \apply_filters( WP_2FA_PREFIX . 'methods_re_configure', array(), $role );

			$enabled_method = User_Helper::get_enabled_method_for_user();

			foreach ( $methods as $order => $method ) {
				if ( $enabled_method === $method['name'] ) {
					$methods[-1] = $method;
					unset( $methods[ $order ] );

					break;
				}
			}

			ksort( $methods );

			foreach ( $methods as $method ) {
				echo $method['output']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Collects the methods options and shows them in order
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function methods_options() {
			$role = User_Helper::get_user_role();

			/**
			 * Shows methods in order. Every method is called and its code and order is collected. That is used when there are no methods selected from the user.
			 *
			 * @param array - All the collected methods and their order.
			 * @param string $role - The role of the current user
			 *
			 * @since 2.6.0
			 */
			$methods = \apply_filters( WP_2FA_PREFIX . 'methods_modal_options', array(), $role );

			ksort( $methods );

			foreach ( $methods as $method ) {
				echo $method; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Settings page and first time wizard settings render
		 *
		 * @param boolean $setup_wizard - Is that the first time setup wizard.
		 * @param string  $data_role - Additional HTML data attribute.
		 * @param mixed   $role - Name of the role.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function methods_settings( bool $setup_wizard, string $data_role, $role = null ) {

			/**
			 * Shows methods in order. Every method is called and its code and order is collected. Used in the wizards.
			 *
			 * @param array - All the collected methods and their order.
			 * @param bool - Is that a setup wizard call or not?
			 * @param string - Additional HTML data attribute.
			 * @param string $role - The role, that is when global settings of the plugin are selected.
			 *
			 * @since 2.6.0
			 */
			$methods = \apply_filters( WP_2FA_PREFIX . 'methods_settings', array(), $setup_wizard, $data_role, $role );

			ksort( $methods );

			foreach ( $methods as $method ) {
				echo $method; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Adds and filters extension values in the settings store array ($output).
		 *
		 * @param array $output - Array with the currently stored settings.
		 * @param array $input  - Array with the input ($_POST) values.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function settings_store( array $output, array $input ) {
			if ( isset( $input[ self::POLICY_SETTINGS_NAME ] ) && \is_array( $input[ self::POLICY_SETTINGS_NAME ] ) ) {
				foreach ( $input[ self::POLICY_SETTINGS_NAME ] as $order => $method ) {
					$output[ self::POLICY_SETTINGS_NAME ][ $order ] = $method;
				}
			}

			return $output;
		}

		/**
		 * Returns the method by its slug.
		 *
		 * @param string $provider_name - The slug to search for.
		 *
		 * @return bool|\WP2FA\Methods
		 *
		 * @since 2.7.0
		 */
		public static function get_method_by_provider_name( string $provider_name ) {
			foreach ( self::get_methods() as $method ) {
				if ( $provider_name === $method::METHOD_NAME ) {
					return $method;
				}
			}

			return \false;
		}

		/**
		 * Returns all of the registered methods.
		 *
		 * @return array
		 *
		 * @since 2.7.0
		 */
		private static function get_methods(): array {
			if ( empty( self::$methods ) ) {
				/**
				 * Gives the ability to add classes to the Class_Helper array.
				 *
				 * @since 2.7.0
				 */
				\do_action( WP_2FA_PREFIX . 'add_to_class_map' );

				self::$methods = Classes_Helper::get_classes_by_namespace( self::METHODS_NAMESPACE );
			}

			return self::$methods;
		}
	}
}
