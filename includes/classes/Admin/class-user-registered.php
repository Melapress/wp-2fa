<?php
/**
 * Responsible for WP2FA user's grace periods.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2026 Melapress
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
		public static function check_user_upon_role_change( $user_id, $role, $old_roles = array() ) {
			$user_id = intval( $user_id );

			self::apply_2fa_grace_period( $user_id );

			/*
			 * Whatever the method check above decided, the user's policy state was
			 * worked out for the roles they had. Dropping the hash makes the next
			 * request work it out again for the roles they have now - whether
			 * they are enforced, excluded, and by when they must set up 2FA.
			 */
			User_Helper::remove_global_settings_hash_for_user( $user_id );
		}

		/**
		 * A role added to or taken from a user, beside the one they had.
		 *
		 * WP_User::add_role() and remove_role() fire add_user_role and
		 * remove_user_role, not set_user_role. Only set_user_role was handled, so
		 * an optional user given an enforced role by a plugin or an integration
		 * kept their optional status - and signed in with a password alone.
		 *
		 * @param int    $user_id - The ID of the user.
		 * @param string $role    - The role added or removed.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function check_user_upon_role_added_or_removed( $user_id, $role = '' ) {
			self::check_user_upon_role_change( $user_id, (string) $role );
		}
	}
}
