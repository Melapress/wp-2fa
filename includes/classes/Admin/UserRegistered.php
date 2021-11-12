<?php // phpcs:ignore

namespace WP2FA\Admin;

use \WP2FA\WP2FA as WP2FA;
use \WP2FA\Authenticator\Authentication as Authentication;
use \WP2FA\Admin\SettingsPage as SettingsPage;

/**
 * UserProfile - Class for handling user things such as profile settings and admin list views.
 */
class UserRegistered {

	/**
	 * Classs constructor
	 */
	public function __construct() {

	}

	/**
	 * Apply 2FA Grace period
	 *
	 * @param  int $user_id User id.
	 */
	public function apply_2fa_grace_period( $user_id ) {
		// Get user object.
		$user = get_user_by( 'id', $user_id );
		// Check if this user is actually eligible.
		$is_needed        = User::is_enforced( $user->ID );
		$is_user_excluded = User::is_excluded( $user->ID );
		// If they are, add grace_period.
		if ( $is_needed && ! $is_user_excluded ) {
			$grace_policy     = WP2FA::get_wp2fa_setting( 'grace-policy' );
			// Grab grace period.
			$create_a_string = WP2FA::get_wp2fa_setting( 'grace-period' ) . ' ' . WP2FA::get_wp2fa_setting( 'grace-period-denominator' );

			// Check if want to apply the custom period, or instant expiry.
			if ( 'use-grace-period' === $grace_policy ) {
				$grace_expiry = strtotime( $create_a_string );
			} else {
				$grace_expiry = time();
			}

			update_user_meta( $user->ID, WP_2FA_PREFIX . 'grace_period_expiry', $grace_expiry );
			if ( 'no-grace-period' === $grace_policy ) {
				update_user_meta( $user->ID, WP_2FA_PREFIX . 'user_enforced_instantly', true );
			}
		}
	}

	public function check_user_upon_role_change( $user_id, $role, $old_roles ) {
		$this->apply_2fa_grace_period( $user_id );
	}
}
