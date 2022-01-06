<?php
/**
 * Responsible for the plugin settings iterations
 *
 * @package wp2fa
 * @subpackage trusted-devices
 */

namespace WP2FA\Admin\Controllers;

use WP2FA\WP2FA;
use WP2FA\Admin\User;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * WP2FA Settings controller
 */
class Settings {

	/**
	 * The name of the WP2FA WP admin settings page
	 *
	 * @var string
	 */
	private static $settings_page_name = 'wp-2fa-policies';

	/**
	 * The link to the WP admin settings page
	 *
	 * @var string
	 */
	private static $settings_page_link = '';

	/**
	 * The name of the WP2FA WP admin setup page
	 *
	 * @var string
	 */
	private static $setup_page_name = 'wp-2fa-setup';

	/**
	 * The link to the WP admin setup page
	 *
	 * @var string
	 */
	private static $setup_page_link = '';

	/**
	 * The link to the custom settings page (if one is presented)
	 *
	 * @var string
	 */
	private static $custom_setup_page_link = null;

	/**
	 * Array with all the backup methods available
	 *
	 * @var array
	 *
	 * @since 2.0.0
	 */
	private static $backup_methods = null;

	/**
	 * Returns the link to the WP admin settings page, based on the current WP install
	 *
	 * @return string
	 */
	public static function get_settings_page_link() {
		if ( '' === self::$settings_page_link ) {
			if ( WP2FA::is_this_multisite() ) {
				self::$settings_page_link = add_query_arg( 'page', self::$settings_page_name, network_admin_url( 'admin.php' ) );
			} else {
				self::$settings_page_link = add_query_arg( 'page', self::$settings_page_name, admin_url( 'admin.php' ) );
			}
		}

		return self::$settings_page_link;
	}

	/**
	 * Returns the link to the WP admin settings page, based on the current WP install
	 *
	 * @return string
	 */
	public static function get_setup_page_link() {
		if ( '' === self::$setup_page_link ) {
			if ( WP2FA::is_this_multisite() ) {
				self::$setup_page_link = add_query_arg( 'show', self::$setup_page_name, network_admin_url( 'profile.php' ) );
			} else {
				self::$setup_page_link = add_query_arg( 'show', self::$setup_page_name, admin_url( 'profile.php' ) );
			}
		}

		return self::$setup_page_link;
	}

	/**
	 * Extracts the custom settings page URL
	 *
	 * @param mixed $user - User for which to extract the setting, null, WP_User or user id - @see get_role_or_default_setting method of this class.
	 *
	 * @return string
	 */
	public static function get_custom_page_link( $user = null ): string {
		if ( null === self::$custom_setup_page_link ) {
			self::$custom_setup_page_link = self::get_role_or_default_setting( 'custom-user-page-id', $user );

			if ( ! empty( self::$custom_setup_page_link ) ) {
				$custom_slug = '';
				if ( WP2FA::is_this_multisite() ) {
					switch_to_blog( get_main_site_id() );

					$custom_slug                  = get_post_field( 'post_name', get_post( self::$custom_setup_page_link ) );
					self::$custom_setup_page_link = trailingslashit( get_site_url() ) . $custom_slug;

					restore_current_blog();
				} else {
					$custom_slug                  = get_post_field( 'post_name', get_post( self::$custom_setup_page_link ) );
					self::$custom_setup_page_link = trailingslashit( get_site_url() ) . $custom_slug;
				}
			}
		}

		return self::$custom_setup_page_link;
	}

	/**
	 * Check all the roles for given setting
	 *
	 * @param string $setting_name - The name of the setting to check for.
	 *
	 * @return boolean
	 *
	 * @since 2.0.0
	 */
	public static function check_setting_in_all_roles( string $setting_name ): bool {
		global $wp_roles;

		$roles = $wp_roles->get_names();

		foreach ( $roles as $role => $value ) {
			if ( ! empty( WP2FA::get_wp2fa_setting( $setting_name, null, null, $role ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return setting specific for the given role or default setting (based on user)
	 *
	 * @param string  $setting_name - The name of the setting.
	 * @param mixed   $user - \WP_User or any string or null - if string the current user will be used, if null global plugin setting will be used.
	 * @param mixed   $role - The name of the role (or null).
	 * @param boolean $get_default_on_empty - Get default setting on empty setting value.
	 * @param boolean $get_default_value - Extracts default value.
	 *
	 * @return mixed
	 *
	 * @since 2.0.0
	 */
	public static function get_role_or_default_setting( string $setting_name, $user = null, $role = null, $get_default_on_empty = false, $get_default_value = false ) {
		/**
		 * No user specified - get the default settings
		 */
		if ( null === $user ) {
			return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value );
		}
		/**
		 * There is an User - extract the role
		 */
		if ( $user instanceof \WP_User ) {
			if ( null === $role ) {
				$role = reset( $user->roles );
			}
			return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value, $role );
		}

		// Extract user by an ID.
		if ( is_int( $user ) ) {
			if ( null === $role ) {
				$role = reset( ( new \WP_User( $user ) )->roles );
			}
			return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value, $role );
		}
		/**
		 * Current user - lets extract the role
		 */
		if ( null === $role ) {
			/**
			 * No logged in current user, ergo no roles - fall back to defaults
			 */
			if ( 0 === User::get_instance()->getUser()->ID ) {
				return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value );
			}

			$role = reset( User::get_instance()->getUser()->roles );
		}
		return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value, $role );
	}

	/**
	 * Returns all the backup methods currently supported
	 *
	 * @return array
	 *
	 * @since 2.0.0
	 */
	public static function get_backup_methods(): array {

		if ( null === self::$backup_methods ) {

			/**
			 * Gives the ability to add additional backup methods
			 *
			 * @param array The array with all the backup methods currently supported.
			 *
			 * @since 2.0.0
			 */
			self::$backup_methods = apply_filters( WP_2FA_PREFIX . 'backup_methods_list', array() );
		}

		return self::$backup_methods;
	}

	/**
	 * Get backup methods enabled for user based on its role
	 *
	 * @param \WP_User $user - The WP user which we must check.
	 *
	 * @return array
	 *
	 * @since 2.0.0
	 */
	public static function get_enabled_backup_methods_for_user_role( \WP_User $user ): array {
		$backup_methods = self::get_backup_methods();

		/**
		 * Extensions could change the enabled backup methods array.
		 *
		 * @param array - Backup methods array.
		 * @param \WP_User - The user to check for.
		 *
		 * @since 2.0.0
		 */
		return apply_filters( 'wp_2fa_backup_methods_enabled', $backup_methods, $user );
	}
}
