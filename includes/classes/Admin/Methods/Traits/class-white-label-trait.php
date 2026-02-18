<?php
/**
 * Responsible for the plugin white label
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Methods\Traits\WhiteLabel' ) ) {
	/**
	 * Responsible for the white label
	 *
	 * @since 2.9.2
	 */
	trait WhiteLabel {

		/**
		 * Adds white label settings.
		 *
		 * @param array $default_settings - The array with default settings.
		 *
		 * @return array
		 */
		public static function add_whitelabel_settings( array $default_settings ): array {
			// To be implemented in the using class.
			return $default_settings;
		}

		/**
		 * Returns white label option labels.
		 *
		 * @return array
		 */
		public static function white_label_option_labels(): array {
			// To be implemented in the using class.
			return array();
		}
	}
}
