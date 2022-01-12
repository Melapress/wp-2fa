<?php
namespace WP2FA\Utils;

use WP2FA\Admin\User;
use WP2FA\WP2FA as WP2FA;
use WP2FA\Admin\Controllers\Settings;
use \WP2FA\Authenticator\BackupCodes as BackupCodes;

/**
 * Utility class for creating modal popup markup.
 *
 * @package WP2FA\Utils
 * @since 1.4.2
 */
class UserUtils {

	/**
	 * Holds map with human readable 2FA statuses
	 *
	 * @var array
	 */
	private static $statuses;

	public static function determine_user_2fa_status( $user ) {

		// Get current user, we going to need this regardless.
		$current_user = wp_get_current_user();

		// Bail if we still dont have an object.
		if ( ! is_a( $user, '\WP_User' ) || ! is_a( $current_user, '\WP_User' ) ) {
			return [];
		}

		$roles = (array) $user->roles;

		// Grab grace period UNIX time.
		$grace_period_expired = get_user_meta( $user->ID, WP_2FA_PREFIX . 'user_grace_period_expired', true );
		$is_user_excluded     = User::is_excluded( $user->ID );
		$isUserEnforced       = User::is_enforced( $user->ID );
		$isUserLocked         = User::isUserLocked( $user->ID );
		$user_last_login      = get_user_meta( $user->ID, WP_2FA_PREFIX . 'login_date', true );

		// First lets see if the user already has a token.
		$enabled_methods = get_user_meta( $user->ID, WP_2FA_PREFIX . 'enabled_methods', true );

		$noEnforcedMethods = false;
		if ( 'do-not-enforce' === WP2FA::get_wp2fa_setting( 'enforcement-policy' ) ) {
			$noEnforcedMethods = true;
		}

		$user_type = array();

		if ( empty( $roles ) ) {
			$user_type[] = 'orphan_user'; // User has no role.
		}

		if ( current_user_can( 'manage_options' ) ) {
			$user_type[] = 'can_manage_options';
		}

		if ( current_user_can( 'read' ) ) {
			$user_type[] = 'can_read';
		}

		if ( $grace_period_expired ) {
			$user_type[] = 'grace_has_expired';
		}

		if ( $current_user->ID === $user->ID ) {
			$user_type[] = 'viewing_own_profile';
		}

		if ( ! empty( $enabled_methods ) ) {
			$user_type[] = 'has_enabled_methods';
		}

		if ( $noEnforcedMethods && ! empty( $enabled_methods ) ) {
			$user_type[] = 'no_required_has_enabled';
		}

		if ( $noEnforcedMethods && empty( $enabled_methods ) && ! $is_user_excluded ) {
			if ( empty( $user_last_login ) ) {
				$user_type[] = 'no_determined_yet';
			} else {
				$user_type[] = 'no_required_not_enabled';
			}
		}

		if ( ! $noEnforcedMethods && empty( $enabled_methods ) && ! $is_user_excluded && $isUserEnforced ) {
			$user_type[] = 'user_needs_to_setup_2fa';
		}

		if ( ! $noEnforcedMethods && empty( $enabled_methods ) && ! $is_user_excluded && ! $isUserEnforced ) {
			if ( empty( $user_last_login ) ) {
				$user_type[] = 'no_determined_yet';
			} else {
				$user_type[] = 'no_required_not_enabled';
			}
		}

		if ( $is_user_excluded ) {
			$user_type[] = 'user_is_excluded';
		}

		if ( $isUserLocked ) {
			$user_type[] = 'user_is_locked';
		}

		$codes_remaining = BackupCodes::codes_remaining_for_user( $user );
		if ( 0 === $codes_remaining ) {
			$user_type[] = 'user_needs_to_setup_backup_codes';
		}

		return apply_filters( 'wp_2fa_additional_user_types', $user_type, $user );
	}

	public static function in_array_all( $needles, $haystack ) {
		return empty( array_diff( $needles, $haystack ) );
	}

	/**
	 * Check if role is not in given array of roles
	 *
	 * @param array $roles
	 * @param array $userRoles
	 *
	 * @return bool
	 */
	public static function roleIsNot( $roles, $userRoles ) {
		if (
			empty(
				array_intersect(
					$roles,
					$userRoles
				)
			)
		) {
			return true;
		}

		return false;
	}

	/**
	 * Works our a list of available 2FA methods. It doesn't include the disabled ones.
	 *
	 * @return string[]
	 * @since 2.0.0
	 */
	public static function get_available_2fa_methods(): array {
		$available_methods = array();

		if ( ! empty( Settings::get_role_or_default_setting( 'enable_email', 'current' ) ) ) {
			$available_methods[] = 'email';
		}

		if ( ! empty( Settings::get_role_or_default_setting( 'enable_totp', 'current' ) ) ) {
			$available_methods[] = 'totp';
		}

		/**
		 * Add an option for external providers to implement their own 2fa methods and set them as available.
		 *
		 * @param array $available_methods - The array with all the available methods.
		 *
		 * @since 2.0.0
		 */
		return apply_filters( 'wp_2fa_available_2fa_methods', $available_methods );
	}

	/**
	 * Return all users, either by using a direct query or get_users.
	 *
	 * @param string $method Method to use.
	 * @param array $users_args Query arguments.
	 *
	 * @return mixed              Array of IDs/Object of Users.
	 */
	public static function get_all_users_data( $method, $users_args ) {

		if ( 'get_users' === $method ) {
			return get_users( $users_args );
		}

		//  method is "query", let's build the SQL query ourselves
		global $wpdb;

		$batch_size = isset( $users_args['batch_size'] ) ? $users_args['batch_size'] : false;
		$offset     = isset( $users_args['count'] ) ? $users_args['count'] * $batch_size : false;

		// Default.
		$select = 'SELECT ID, user_login FROM ' . $wpdb->users . '';

		// If we want to grab users with a specific role.
		if ( isset( $users_args['role__in'] ) && ! empty( $users_args['role__in'] ) ) {
			$roles  = $users_args['role__in'];
			$select = '
					SELECT  ID, user_login
					FROM    ' . $wpdb->users . ' u INNER JOIN ' . $wpdb->usermeta . ' um
					ON      u.ID = um.user_id
					WHERE   um.meta_key LIKE \'' . $wpdb->base_prefix . '%capabilities' . '\'
					AND     (
			';
			$i      = 1;
			foreach ( $roles as $role ) {
				$select .= ' um.meta_value    LIKE    \'%"' . $role . '"%\' ';
				if ( $i < count( $roles ) ) {
					$select .= ' OR ';
				}
				$i ++;
			}
			$select .= ' ) ';

			$excluded_users = ( ! empty( $users_args['excluded_users'] ) ) ? $users_args['excluded_users'] : [];

			$excluded_users = array_map( function ( $excluded_user ) {
				return '"' . $excluded_user . '"';
			}, $excluded_users );

			if ( ! empty( $excluded_users ) ) {
				$select .= '
						AND user_login NOT IN ( ' . implode( ',', $excluded_users ) . ' )
				';
			}

			$skip_existing_2fa_users = ( ! empty( $users_args['skip_existing_2fa_users'] ) ) ? $users_args['skip_existing_2fa_users'] : false;

			if ( $skip_existing_2fa_users ) {
				$select .= '
				AND u.ID NOT IN (
				  SELECT DISTINCT user_id FROM  ' . $wpdb->usermeta . ' WHERE meta_key = \'wp_2fa_enabled_methods\'
				)
				';
			}

		}

		if ( $batch_size ) {
			$select .= ' LIMIT ' . $batch_size . ' OFFSET ' . $offset . '';
		}

		return $wpdb->get_results( $select );
	}

	/**
	 * Get list of IDs only if they have a specific 2FA method enabled.
	 *
	 * @param string $removing Method to search for.
	 * @param $users_args
	 *
	 * @return array            User details.
	 */
	public static function get_all_user_ids_based_on_enabled_2fa_method( $removing, $users_args ) {

		global $wpdb;

		$batch_size = isset( $users_args['batch_size'] ) ? $users_args['batch_size'] : false;
		$offset     = isset( $users_args['count'] ) ? $users_args['count'] * $batch_size : false;

		$select = '
			SELECT ID FROM ' . $wpdb->users . '
			INNER JOIN ' . $wpdb->usermeta . ' ON ' . $wpdb->users . '.ID = ' . $wpdb->usermeta . '.user_id
			WHERE ' . $wpdb->usermeta . '.meta_key = \'wp_2fa_enabled_methods\'
			AND ' . $wpdb->usermeta . '.meta_value = \'' . $removing . '\'
		';

		if ( $batch_size ) {
			$select .= '
				LIMIT ' . $batch_size . ' OFFSET ' . $offset . '
			';
		}

		$users = $wpdb->get_results( $select );

		$users = array_map( function ( $user ) {
			return (int) $user->ID;
		}, $users );

		$users = implode( ',', $users );

		return $users;
	}

	public static function get_all_user_ids_who_have_wp_2fa_metadata_present( $users_args ) {

		global $wpdb;

		$batch_size = isset( $users_args['batch_size'] ) ? $users_args['batch_size'] : false;
		$offset     = isset( $users_args['count'] ) ? $users_args['count'] * $batch_size : false;

		$select = '
			SELECT ID FROM ' . $wpdb->users . '
			INNER JOIN ' . $wpdb->usermeta . ' ON ' . $wpdb->users . '.ID = ' . $wpdb->usermeta . '.user_id
			WHERE ' . $wpdb->usermeta . '.meta_key LIKE \'wp_2fa_%\'
		';

		if ( $batch_size ) {
			$select .= '
				LIMIT ' . $batch_size . ' OFFSET ' . $offset . '
			';
		}

		$users = $wpdb->get_results( $select );

		$users = array_map( function ( $user ) {
			return (int) $user->ID;
		}, $users );

		$users = implode( ',', $users );

		return $users;
	}

	/**
	 * Retrieve string of comma seperated IDs.
	 *
	 * @param string $method Method to use.
	 * @param array $users_args Query arguments.
	 *
	 * @return string             List of IDs.
	 */
	public static function get_all_user_ids( $method, $users_args ) {
		$user_data = UserUtils::get_all_users_data( $method, $users_args );

		$users = array_map( function ( $user ) {
			return (int) $user->ID;
		}, $user_data );

		return implode( ',', $users );
	}

	/**
	 * Retrieve array if user IDs and login names.
	 *
	 * @param string $method Method to use.
	 * @param array $users_args Query arguments.
	 *
	 * @return array              User details.
	 */
	public static function get_all_user_ids_and_login_names( $method, $users_args ) {
		$user_data = UserUtils::get_all_users_data( $method, $users_args );
		$user_item = [];

		$users = array_map( function ( $user ) {
			$user_item['ID']         = (int) $user->ID;
			$user_item['user_login'] = $user->user_login;

			return $user_item;
		}, $user_data );

		return $users;
	}

	/**
	 * Returns the array with human readable statuses of the WP 2FA
	 *
	 * @since 1.6
	 *
	 * @return array
	 */
	public static function getHumanReadableUserStatuses() {
		if ( null === self::$statuses ) {
			self::$statuses =
			[
				'has_enabled_methods'              => __( 'Configured', 'wp-2fa' ),
				'user_needs_to_setup_2fa'          => __( 'Required but not configured', 'wp-2fa' ),
				'no_required_has_enabled'          => __( 'Configured (but not required)', 'wp-2fa' ),
				'no_required_not_enabled'          => __( 'Not required & not configured', 'wp-2fa' ),
				'user_is_excluded'                 => __( 'Not allowed', 'wp-2fa' ),
				'user_is_locked'                   => __( 'Locked', 'wp-2fa' ),
				'no_determined_yet'                => __( 'User has not logged in yet, 2FA status is unknown', 'wp-2fa' ),
			];
		}

		return self::$statuses;
	}

	/**
	 * Gets the user types extracted with @see UserUtils::determine_user_2fa_status,
	 * checks values and generates human readable 2FA status text
	 *
	 * @param array $userTypes
	 *
	 * @return array An array with the id and label elements of user 2FA status. Empty in case there is not match.
	 *
	 * @since 1.7.0 Changed the function to return the id and label of the first match it finds instead of concatenated labels of all matched statuses.
	 */
	public static function extractStatuses( $userTypes ) {
		if ( null === self::$statuses ) {
			self::getHumanReadableUserStatuses();
		}

		foreach ( self::$statuses as $key => $value ) {
			if ( in_array( $key, $userTypes ) ) {
				return [
					'id'=> $key,
					'label' => $value
					];
			}
		}

		return [];
	}
}
