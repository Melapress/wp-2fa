<?php
/**
 * Responsible for WP2FA user's grace periods.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 * @since 3.0.0
 */

namespace WP2FA\Admin;

use WP2FA\Admin\Helpers\User_Helper;

if ( ! class_exists( '\WP2FA\Admin\User_Registered' ) ) {
	/**
	 * User_Profile - Class for handling user things such as profile settings and admin list views.
	 */
	class User_Registered {

		/**
		 * Apply 2FA Grace period
		 *
		 * @param  int $user_id User id.
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function apply_2fa_grace_period( $user_id ) {
			$user_id = intval( $user_id );
			if ( User_Helper::is_user_method_in_role_enabled_methods( $user_id ) ) {
				return;
			} else {
				User_Helper::remove_enabled_method_for_user( $user_id );
				User_Helper::remove_global_settings_hash_for_user( $user_id );
			}
		}

		/**
		 * Checks the user on role change.
		 *
		 * @param integer $user_id - The ID of the user.
		 * @param string  $role - The user role.
		 * @param array   $old_roles - Old roles for the user.
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function check_user_upon_role_change( $user_id, $role, $old_roles ) {
			$user_id = intval( $user_id );
			$role    = sanitize_text_field( $role );

			self::apply_2fa_grace_period( $user_id );
		}
	}
}
