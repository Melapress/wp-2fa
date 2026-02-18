<?php
/**
 * Responsible for the plugin settings
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

use WP2FA\Utils\Settings_Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Methods\Traits\Settings_Trait' ) ) {
	/**
	 * Responsible for the settings
	 *
	 * @since 2.9.2
	 */
	trait Settings_Trait {

		/**
		 * Adds method-specific settings to the loop.
		 *
		 * @param array $settings - The array with settings.
		 *
		 * @return array
		 *
		 * @since 3.1.1
		 */
		public static function settings_loop( array $settings ): array {
			$settings[ static::POLICY_SETTINGS_NAME ] = Settings_Utils::get_option( static::POLICY_SETTINGS_NAME, static::get_settings_default_value() );

			return $settings;
		}

		/**
		 * Adds default settings for the method.
		 *
		 * @param array $default_settings - The array with default settings.
		 *
		 * @return array
		 *
		 * @since 3.1.1
		 */
		public static function add_default_settings( array $default_settings ): array {

			if ( \method_exists( static::class, 'get_settings_default_value' ) ) {
				$default_settings[ static::POLICY_SETTINGS_NAME ] = static::get_settings_default_value();
			} else {
				$default_settings[ static::POLICY_SETTINGS_NAME ] = static::POLICY_SETTINGS_NAME;
			}

			return $default_settings;
		}

		/**
		 * Adds translated provider name.
		 *
		 * @param array $providers - Array with all currently supported providers and their translated names.
		 *
		 * @return array
		 *
		 * @since 3.1.1
		 */
		public static function provider_name_translated( array $providers ): array {
			$providers[ static::METHOD_NAME ] = static::get_translated_name();

			return $providers;
		}
	}
}
