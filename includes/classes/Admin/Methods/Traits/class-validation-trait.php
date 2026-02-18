<?php
/**
 * Responsible for the plugin validation
 *
 * @package    wp2fa
 * @subpackage traits
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Methods\Traits;

use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Methods\Traits\Validation' ) ) {
	/**
	 * Responsible for the validation
	 *
	 * @since 2.9.2
	 */
	trait Validation {

		/**
		 * Validates the login via API.
		 *
		 * @param array       $valid - The validation array.
		 * @param int         $user_id - The user ID.
		 * @param string|null $token - The token to validate.
		 *
		 * @return array
		 */
		public static function api_login_validate( array $valid, int $user_id, ?string $token ): array {
			if ( ! Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user_id ), static::METHOD_NAME ) ) {
				return $valid;
			}

			if ( static::METHOD_NAME !== User_Helper::get_enabled_method_for_user( $user_id ) ) {
				return $valid;
			}

			if ( ! is_array( $valid ) || ! isset( $valid['valid'] ) ) {
				$valid['valid'] = false;
			}

			// If the login is valid, return it as it is.
			if ( true === $valid['valid'] ) {
				return $valid;
			}

			if ( ! isset( $token ) || empty( $token ) ) {
				return $valid;
			}

			// Sanitize the token to ensure it is safe to use.
			$sanitized_token = \sanitize_text_field( $token );

			$is_valid = static::validate_token( User_Helper::get_user( $user_id ), $sanitized_token );

			if ( ! $is_valid ) {
				$valid[ static::METHOD_NAME ]['error'] = \esc_html__( 'ERROR: Invalid verification code.', 'wp-2fa' );
			}

			$valid['valid'] = $is_valid;

			return $valid;
		}

		/**
		 * Validates the token for the method.
		 *
		 * @param \WP_User $user - The user.
		 * @param string   $token - The token.
		 *
		 * @return bool
		 */
		abstract protected static function validate_token( \WP_User $user, string $token ): bool;
	}
}
