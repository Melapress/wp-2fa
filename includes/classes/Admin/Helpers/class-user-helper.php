<?php
/**
 * Responsible for the User's operations.
 *
 * @package    wp2fa
 * @subpackage helpers
 *
 * @since      2.2.0
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

use wpdb;
use WP2FA\WP2FA;
use WP2FA\Utils\User_Utils;
use WP2FA\Extensions_Loader;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Freemius\User_Licensing;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Controllers\Settings;

/*
 * User's settings class
 */
if ( ! class_exists( '\WP2FA\Admin\Helpers\User_Helper' ) ) {
	/**
	 * All the user related settings must go trough this class.
	 *
	 * @since 2.2.0
	 */
	class User_Helper {
		/**
		 * Enabled 2fa method for user meta name.
		 */
		public const ENABLED_METHODS_META_KEY = WP_2FA_PREFIX . 'enabled_methods';
		/**
		 * Email token for user meta name.
		 */
		public const TOKEN_META_KEY = WP_2FA_PREFIX . 'email_token';
		/**
		 * Global settings hash for user meta name
		 * That is used to check if user needs to be re-checked / re-configured, if the settings of the plugin are changed, probably the user settings also need to be changed - that meta holds the key to check against.
		 */
		public const USER_SETTINGS_HASH = WP_2FA_PREFIX . 'global_settings_hash';
		/**
		 * The meta name for the user 2FA status in the plugin.
		 */
		public const USER_2FA_STATUS = WP_2FA_PREFIX . '2fa_status';
		/**
		 * The user grace period expired meta key.
		 */
		public const USER_GRACE_KEY = WP_2FA_PREFIX . 'user_grace_period_expired';
		/**
		 * The user grace period expiry date meta key.
		 */
		public const USER_GRACE_EXPIRY_KEY = WP_2FA_PREFIX . 'grace_period_expiry';
		/**
		 * The user enforcement status.
		 */
		public const USER_ENFORCED_INSTANTLY = WP_2FA_PREFIX . 'user_enforced_instantly';
		/**
		 * The user reconfigure 2fa status.
		 */
		public const USER_NEEDS_TO_RECONFIGURE_2FA = WP_2FA_PREFIX . 'user_needs_to_reconfigure_2fa';
		/**
		 * The user enforcement state.
		 */
		public const USER_ENFORCEMENT_STATE = WP_2FA_PREFIX . 'enforcement_state';
		/**
		 * The user nag dismissed flag.
		 */
		public const USER_NAG_DISMISSED = WP_2FA_PREFIX . 'update_nag_dismissed';
		/**
		 * The default status of the user which has no status set yet.
		 */
		public const USER_UNDETERMINED_STATUS = 'no_determined_yet';
		/**
		 * The last login date for the user.
		 */
		public const USER_LOGIN_DATE = 'login_date';
		/**
		 * The reset password for the user is valid.
		 */
		public const USER_RESET_PASSWORD_VALID = 'reset_password_valid';
		/**
		 * The nominated global email address for the user.
		 */
		public const USER_NOMINATED_EMAIL = WP_2FA_PREFIX . 'nominated_email_address';
		/**
		 * The backup email address for the user - for backup methods when app is in use.
		 */
		public const USER_BACKUP_EMAIL = WP_2FA_PREFIX . 'backup_email_address';
		/**
		 * The default user statuses.
		 */
		public const USER_STATE_STATUSES = array(
			'optional',
			'excluded',
			'enforced',
		);

		/**
		 * The class user variable.
		 *
		 * @var \WP_User
		 *
		 * @since 2.2.0
		 */
		private static $user = null;

		/**
		 * All global excluded roles
		 *
		 * @var array
		 *
		 * @since 2.5.0
		 */
		private static $excluded_roles = null;

		/**
		 * All global excluded sites
		 *
		 * @var array
		 *
		 * @since 2.5.0
		 */
		private static $excluded_sites = null;

		/**
		 * All global excluded users
		 *
		 * @var array
		 *
		 * @since 2.5.0
		 */
		private static $excluded_users = null;

		/**
		 * All global included sites
		 *
		 * @var array
		 *
		 * @since 2.5.0
		 */
		private static $included_sites = null;

		/**
		 * All global enforced users
		 *
		 * @var array
		 *
		 * @since 2.5.0
		 */
		private static $enforced_users = null;

		/**
		 * All global enforced roles
		 *
		 * @var array
		 *
		 * @since 2.5.0
		 */
		private static $enforced_roles = null;

		/**
		 * Marks the status of the updating process
		 *
		 * @var boolean
		 *
		 * @since 2.4.1
		 */
		private static $update_started = false;

		/**
		 * Returns the enable 2fa backup methods for the given user
		 *
		 * @param \WP_User] $user - The user which has to be checked.
		 *
		 * @return mixed
		 *
		 * @since 2.6.0
		 */
		public static function get_enabled_backup_methods_for_user( $user = null ) {
			self::set_proper_user( $user );

			/*
			 * Checks the enabled methods for the user.
			 *
			 * @param mixed - Value of the method.
			 * @param array - Array of enabled methods for the user.
			 * @param \WP_User - The user which must be checked.
			 *
			 * @since 2.6.0
			 */
			return apply_filters( WP_2FA_PREFIX . 'user_enabled_backup_methods', array(), $user );
		}

		/**
		 * Returns the enabled 2FA method for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_enabled_method_for_user( $user = null ) {
			self::set_proper_user( $user );

			/*
			 * Checks the enabled methods for the user.
			 *
			 * @param mixed - Value of the method.
			 * @param string|null $user - Currently enabled method.
			 * @param \WP_User - The user which must be checked.
			 *
			 * @since 2.0.0
			 */
			return apply_filters( WP_2FA_PREFIX . 'user_enabled_methods', self::get_meta( self::ENABLED_METHODS_META_KEY ), $user );
		}

		/**
		 * Sets the enabled 2FA method for the user.
		 *
		 * @param string            $method - The name of the method to set.
		 * @param int|\WP_User|null $user   - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_enabled_method_for_user( string $method, $user = null ) {
			self::set_proper_user( $user );

			/*
			 * Fires before the user method is set.
			 *
			 * @param string - Current user method.
			 * @param \WP_User $user - The user for which the method has been set.
			 *
			 * @since 2.6.0
			 */
			\do_action( WP_2FA_PREFIX . 'before_method_been_set', self::get_enabled_method_for_user( self::get_user() ), self::get_user() );

			$set_method = self::set_meta( self::ENABLED_METHODS_META_KEY, $method );

			/*
			 * Fires when the user method is set.
			 *
			 * @param string - The method set for the user.
			 * @param \WP_User $user - The user for which the method has been set.
			 *
			 * @since 2.2.2
			 */
			\do_action( WP_2FA_PREFIX . 'method_has_been_set', $method, self::get_user() );

			return $set_method;
		}

		/**
		 * Removes the 2FA method for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_enabled_method_for_user( $user = null ) {
			self::set_proper_user( $user );

			/*
			 * Fires before the user method is removed.
			 *
			 * @param string - Current user method.
			 * @param \WP_User $user - The user for which the method has been set.
			 *
			 * @since 2.6.0
			 */
			\do_action( WP_2FA_PREFIX . 'before_method_is_removed', self::get_enabled_method_for_user( self::get_user() ), self::get_user() );

			self::remove_meta( self::ENABLED_METHODS_META_KEY, self::$user );

			/*
			 * Fires after the user method is removed.
			 *
			 * @param \WP_User $user - The user for which the method has been set.
			 *
			 * @since 2.6.0
			 */
			\do_action( WP_2FA_PREFIX . 'after_method_is_removed', self::get_user() );

			if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) ) {
				if ( Extensions_Loader::use_proxytron() ) {
					$user_blog_id = 1;
					if ( WP_Helper::is_multisite() ) {
						$user_blog_id = \get_active_blog_for_user( self::$user->ID )->blog_id;
					}
					if ( ( $current_blog = \get_current_blog_id() ) !== $user_blog_id ) { // phpcs:ignore
						if ( WP_Helper::is_multisite() ) {
							\switch_to_blog( $user_blog_id );
						}
						User_Licensing::method_has_been_set();
						if ( WP_Helper::is_multisite() ) {
							\switch_to_blog( $current_blog );
						}
					}
				}
			}
		}

		/**
		 * Returns the email token for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_email_token_for_user( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::TOKEN_META_KEY );
		}

		/**
		 * Sets the email token for the user.
		 *
		 * @param string            $token - The token to set for the user.
		 * @param int|\WP_User|null $user  - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_email_token_for_user( string $token, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::TOKEN_META_KEY, $token );
		}

		/**
		 * Removes the email token for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_email_token_for_user( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::TOKEN_META_KEY, self::$user );
		}

		/**
		 * Returns the last login date for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.5.0
		 */
		public static function get_login_date_for_user( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_RESET_PASSWORD_VALID );
		}

		/**
		 * Sets  last login date for the user.
		 *
		 * @param bool              $valid - The reset password is valid user.
		 * @param int|\WP_User|null $user  - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.5.0
		 */
		public static function set_reset_password_valid_for_user( bool $valid, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_RESET_PASSWORD_VALID, $valid );
		}

		/**
		 * Removes  last login date  for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		public static function remove_reset_password_valid_for_user( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_RESET_PASSWORD_VALID, self::$user );
		}

		/**
		 * Returns the last login date for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.5.0
		 */
		public static function get_reset_password_valid_for_user( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_RESET_PASSWORD_VALID );
		}

		/**
		 * Sets last login date for the user.
		 *
		 * @param int               $login_date - The login date to set for the user.
		 * @param int|\WP_User|null $user  - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.5.0
		 */
		public static function set_login_date_for_user( int $login_date, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_LOGIN_DATE, $login_date );
		}

		/**
		 * Removes  last login date  for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		public static function remove_login_date_for_user( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_LOGIN_DATE, self::$user );
		}

		/**
		 * Returns the global settings hash for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_global_settings_hash_for_user( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_SETTINGS_HASH );
		}

		/**
		 * Sets the global settings hash for the user.
		 *
		 * @param string            $hash - The global settings hash to set for the user.
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_global_settings_hash_for_user( string $hash, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_SETTINGS_HASH, $hash );
		}

		/**
		 * Removes the global settings hash for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_global_settings_hash_for_user( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_SETTINGS_HASH, self::$user );
		}

		/**
		 * Returns the current 2FA status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_2fa_status( $user = null ) {
			self::set_proper_user( $user );

			$status = (string) self::get_meta( self::USER_2FA_STATUS );

			if ( '' === trim( $status ) ) {
				$status = self::USER_UNDETERMINED_STATUS;
				self::set_2fa_status( self::USER_UNDETERMINED_STATUS );
			}

			return $status;
		}

		/**
		 * Sets the 2FA status for the user.
		 *
		 * @param string            $status - The name of the status to set.
		 * @param int|\WP_User|null $user   - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_2fa_status( string $status, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_2FA_STATUS, $status );
		}

		/**
		 * Removes the 2FA status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_2fa_status( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_2FA_STATUS, self::$user );
		}

		/**
		 * Returns the current nag status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.3
		 */
		public static function get_nag_status( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_NAG_DISMISSED );
		}

		/**
		 * Sets the nag status for the user.
		 *
		 * @param bool              $status - The name of the status to set.
		 * @param int|\WP_User|null $user   - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.3
		 */
		public static function set_nag_status( bool $status, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_NAG_DISMISSED, $status );
		}

		/**
		 * Removes the nag status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.3
		 */
		public static function remove_nag_status( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_NAG_DISMISSED, self::$user );
		}

		/**
		 * Returns the current 2FA status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_user_expiry_date( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_GRACE_EXPIRY_KEY );
		}

		/**
		 * Sets the 2FA status for the user.
		 *
		 * @param string            $date - The period to set.
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_user_expiry_date( string $date, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_GRACE_EXPIRY_KEY, $date );
		}

		/**
		 * Removes the 2FA status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_user_expiry_date( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_GRACE_EXPIRY_KEY, self::$user );
		}

		/**
		 * Returns the current 2FA enforcement status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_user_enforced_instantly( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_ENFORCED_INSTANTLY );
		}

		/**
		 * Sets the 2FA enforcement status for the user.
		 *
		 * @param bool              $status - The status for user enforcement.
		 * @param int|\WP_User|null $user   - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_user_enforced_instantly( bool $status, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_ENFORCED_INSTANTLY, $status );
		}

		/**
		 * Removes the 2FA enforcement status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_user_enforced_instantly( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_ENFORCED_INSTANTLY, self::$user );
		}

		/**
		 * Returns the current 2FA needs to reconfigure status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_user_needs_to_reconfigure_2fa( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_NEEDS_TO_RECONFIGURE_2FA );
		}

		/**
		 * Sets the 2FA needs to reconfigure status for the user.
		 *
		 * @param bool              $status - The status for user enforcement.
		 * @param int|\WP_User|null $user   - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_user_needs_to_reconfigure_2fa( bool $status, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_NEEDS_TO_RECONFIGURE_2FA, $status );
		}

		/**
		 * Removes the 2FA needs to reconfigure status for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_user_needs_to_reconfigure_2fa( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_NEEDS_TO_RECONFIGURE_2FA, self::$user );
		}

		/**
		 * Every meta call for the user must go through this method, so we can unify the code.
		 *
		 * @param string            $meta - The meta name that we should check.
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 * @param mixed             $default_value - The default value to be returned if meta is not presented.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_meta( string $meta, $user = null, $default_value = true ) {
			self::set_proper_user( $user );

			return \get_user_meta( self::$user->ID, $meta, $default_value );
		}

		/**
		 * Every meta storing call for the user must go through this method.
		 *
		 * @param string            $meta  - The meta name that we should check.
		 * @param mixed             $value - The value which should be stored.
		 * @param int|\WP_User|null $user  - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_meta( string $meta, $value, $user = null ) {
			self::set_proper_user( $user );

			return \update_user_meta( self::$user->ID, $meta, $value );
		}

		/**
		 * Removes meta for the given user.
		 *
		 * @param string            $meta - The name of the meta.
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function remove_meta( string $meta, $user = null ) {
			self::set_proper_user( $user );

			return \delete_user_meta( self::$user->ID, $meta );
		}

		/**
		 * Returns the currently set user.
		 *
		 * @return \WP_User
		 *
		 * @since 2.2.0
		 */
		public static function get_user() {
			if ( null === self::$user ) {
				self::set_user();
			}

			return self::$user;
		}

		/**
		 * Returns WP User object.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return \WP_User
		 *
		 * @since 2.2.0
		 */
		public static function get_user_object( $user = null ) {
			if ( null === $user && null !== self::$user ) {
				return self::$user;
			}

			self::set_user( $user );

			return self::$user;
		}

		/**
		 * Sets the user.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function set_user( $user = null ) {
			if ( $user instanceof \WP_User ) {
				if ( isset( self::$user ) && $user === self::$user ) {
					return;
				}
				self::$user = $user;
			} elseif ( false !== ( filter_var( $user, FILTER_VALIDATE_INT ) ) ) {
				if ( isset( self::$user ) && $user instanceof \WP_User && $user === self::$user->ID ) {
					return;
				}
				if ( ! function_exists( 'get_user_by' ) ) {
					require ABSPATH . WPINC . '/pluggable.php';
				}
				self::$user = \get_user_by( 'id', $user );
				if ( \is_bool( self::$user ) ) {
					self::$user = \wp_get_current_user();
				}
			} elseif ( is_string( $user ) && ! empty( trim( (string) $user ) ) ) {
				if ( isset( self::$user ) && $user instanceof \WP_User && $user === self::$user->ID ) {
					return;
				}
				if ( ! function_exists( 'get_user_by' ) ) {
					require ABSPATH . WPINC . '/pluggable.php';
				}
				self::$user = \get_user_by( 'login', $user );
			} else {
				if ( ! function_exists( 'wp_get_current_user' ) ) {
					require ABSPATH . WPINC . '/pluggable.php';
					wp_cookie_constants();
				}
				self::$user = \wp_get_current_user();
			}
		}

		/**
		 * Returns the default role for the given user.
		 *
		 * @param int|\WP_User|null $user - The WP user.
		 *
		 * @since 2.2.0
		 */
		public static function get_user_role( $user = null ): string {
			self::set_proper_user( $user );

			if ( 0 === self::$user->ID || \is_bool( self::$user ) ) {
				return '';
			}

			if ( \is_multisite() ) {
				$blog_id = \get_current_blog_id();

				if ( ! is_user_member_of_blog( self::$user->ID, $blog_id ) ) {
					$user_blog_id = \get_active_blog_for_user( self::$user->ID );

					if ( null !== $user_blog_id ) {
						self::$user = new \WP_User(
							// $user_id
							self::$user->ID,
							// $name | login, ignored if $user_id is set
							'',
							// $blog_id
							$user_blog_id->blog_id
						);
					}
				}
			}

			$role = reset( self::$user->roles );

			/*
			 * The code looks like this for clearness only
			 */
			if ( \is_multisite() ) {
				/*
				 * On multi site we can have user which has no assigned role, but it is superadmin.
				 * If the check confirms that - assign the role of the administrator to the user in order not to break our code.
				 *
				 * Unfortunately we could never be sure what is the name of the administrator role (someone could change this default value),
				 * in order to continue working we will use the presumption that if given role has 'manage_options' capability, then it is
				 * most probably administrator - so we will assign that role to the user.
				 */
				if ( false === $role && is_super_admin( self::$user->ID ) ) {
					$wp_roles = WP_Helper::get_roles_wp();
					foreach ( $wp_roles as $role_name => $wp_role ) {
						$admin_role_set = get_role( $role_name )->capabilities;
						if ( $admin_role_set['manage_options'] ) {
							$role = $role_name;

							break;
						}
					}
				}
			}

			return (string) $role;
		}

		/**
		 * Returns the default blog_id for the given user.
		 *
		 * @param int|\WP_User|null $user - The WP user.
		 *
		 * @since 2.5.0
		 */
		public static function get_user_default_blog( $user = null ): int {
			self::set_proper_user( $user );

			if ( 0 === self::$user->ID ) {
				return 0;
			}

			if ( \is_multisite() ) {
				$blog_id = \get_current_blog_id();

				if ( ! is_user_member_of_blog( self::$user->ID, $blog_id ) ) {
					$blog_id = \get_active_blog_for_user( self::$user->ID );

					if ( $blog_id instanceof \WP_Site ) {
						return (int) $blog_id->blog_id;
					} else {
						return 1;
					}
				}
			} else {
				return 1;
			}

			return (int) $blog_id;
		}

		/**
		 * Checks if the user method is within the selected methods for the given role.
		 *
		 * @param int|\WP_User|null $user - The WP user.
		 *
		 * @since 2.2.0
		 */
		public static function is_user_method_in_role_enabled_methods( $user = null ): bool {
			$enabled_method = self::get_enabled_method_for_user( $user );
			if ( empty( $enabled_method ) ) {
				return false;
			}
			$is_method_available = Settings::is_provider_enabled_for_role( self::get_user_role( $user ), $enabled_method );

			return $is_method_available;
		}

		/**
		 * Removes all the meta keys associated with the given user.
		 *
		 * @param int|\WP_User|null $user - The WP user for which we have to remove the meta data.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_all_2fa_meta_for_user( $user = null ) {
			self::set_proper_user( $user );

			$user_meta_values = array_filter(
				\get_user_meta( self::$user->ID ),
				function ( $key ) {
					return 0 === strpos( $key, WP_2FA_PREFIX );
				},
				ARRAY_FILTER_USE_KEY
			);

			foreach ( array_keys( $user_meta_values ) as $meta_name ) {
				self::remove_meta( $meta_name, $user );
			}

			if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) ) {
				if ( Extensions_Loader::use_proxytron() ) {
					$user_blog_id = 1;
					if ( WP_Helper::is_multisite() ) {
						$user_blog_id = \get_active_blog_for_user( self::$user->ID )->blog_id;
					}
					if ( ( $current_blog = \get_current_blog_id() ) !== $user_blog_id ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
						if ( WP_Helper::is_multisite() ) {
							wp2fa_freemius()->switch_to_blog( $user_blog_id );
						}
						User_Licensing::method_has_been_set();
						if ( WP_Helper::is_multisite() ) {
							wp2fa_freemius()->switch_to_blog( $current_blog );
						}
					}
				}
			}

			/*
			 * Fires when the user method is removed.
			 *
			 * @param \WP_User $user - The user for which the method has been removed.
			 *
			 * @since 2.2.2
			 */
			\do_action( WP_2FA_PREFIX . 'method_has_been_removed', self::get_user() );
		}

		/**
		 * Quick boolean check for whether a given user is using two-step.
		 *
		 * @since 2.2.0
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return bool
		 */
		public static function is_user_using_two_factor( $user = null ) {
			self::set_proper_user( $user );

			return ! empty( self::get_enabled_method_for_user() );
		}

		/**
		 * Gets the user grace period from meta.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function get_grace_period( $user = null ) {
			self::set_proper_user( $user );

			return self::get_meta( self::USER_GRACE_KEY, self::$user );
		}

		/**
		 * Sets the user grace period from meta.
		 *
		 * @param string            $value - The value of the meta key.
		 * @param int|\WP_User|null $user  - The WP user that must be used.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function set_grace_period( $value, $user = null ) {
			self::set_proper_user( $user );

			return self::set_meta( self::USER_GRACE_KEY, $value, self::$user );
		}

		/**
		 * Sets the user grace period from meta.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function remove_grace_period( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_GRACE_KEY, $user );
		}

		/**
		 * Checks if the user is locked. It only checks a single user meta field to keep this as fast as possible. The
		 * value of the field is updated elsewhere.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return bool True if the user account is locked. False otherwise.
		 *
		 * @since 2.2.0
		 */
		public static function is_user_locked( $user = null ): bool {
			return (bool) self::get_grace_period( $user );
		}

		/**
		 * Checks if the given user has administrator or super administrator privileges.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @since 2.2.0
		 */
		public static function is_admin( $user = null ): bool {
			self::set_proper_user( $user );

			$is_admin = in_array( 'administrator', self::$user->roles, true ) || ( function_exists( 'is_super_admin' ) && is_super_admin( self::$user->ID ) );

			if ( ! $is_admin ) {
				return false;
			}

			return true;
		}

		/**
		 * Checks if user is excluded.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function is_excluded( $user = null ) {
			$state = self::get_user_state( $user );

			if ( 'excluded' !== $state ) {
				$user_role = self::get_user_role( $user );

				if ( Settings_Utils::string_to_bool( WP2FA::get_wp2fa_setting( 'superadmins-role-exclude' ) ) && is_super_admin( self::$user->ID ) ) {
					$state = 'excluded';
					self::set_user_state( $state, $user );
					self::remove_enabled_method_for_user( $user );
				}

				// User does not have role assigned, exclude them.
				if ( '' === $user_role ) {
					$state = 'excluded';
					self::set_user_state( $state, $user );
				}
			}

			return 'excluded' === $state;
		}

		/**
		 * Updates the user state based on the current plugin settings.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return string
		 *
		 * @since 2.3
		 */
		public static function update_user_state( $user = null ) {
			self::set_proper_user( $user );

			$enforcement_state = 'optional';
			if ( self::run_user_exclusion_check( self::get_user() ) ) {
				$enforcement_state = 'excluded';
			} elseif ( self::run_user_enforcement_check( self::get_user() ) ) {
				$enforcement_state = 'enforced';
			}

			self::set_user_state( $enforcement_state );

			// Clear enabled methods if excluded.
			if ( 'excluded' === $enforcement_state ) {
				self::remove_enabled_method_for_user();
			}

			return $enforcement_state;
		}

		/**
		 * Checks if user is enforced.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.2.0
		 */
		public static function is_enforced( $user = null ) {
			$state = self::get_user_state( $user );

			return 'enforced' === $state;
		}

		/**
		 * Returns the current user state stored.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return string
		 *
		 * @since 2.2.0
		 */
		public static function get_user_state( $user = null ) {
			self::set_proper_user( $user );

			$state = self::get_meta( self::USER_ENFORCEMENT_STATE );

			if ( empty( $state ) ) {
				$state = self::update_user_state();
			}

			return $state;
		}

		/**
		 * Returns the current user state stored.
		 *
		 * @param string            $state - The 2FA user state.
		 * @param int|\WP_User|null $user  - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function set_user_state( $state, $user = null ) {
			self::set_proper_user( $user );

			if ( ! in_array( $state, self::USER_STATE_STATUSES, true ) ) {
				$state = self::USER_STATE_STATUSES[0];
			}

			self::set_meta( self::USER_ENFORCEMENT_STATE, $state );
		}

		/**
		 * Removes 2FA meta for the given user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.2
		 */
		public static function remove_2fa_for_user( $user = null ) {
			self::set_proper_user( $user );

			self::remove_all_2fa_meta_for_user( $user );
		}

		/**
		 * Figures out the correct 2FA status of a user and stores it against the user in DB. The method is static
		 * because it is temporarily used in user listing to update user accounts created prior to version 1.7.0.
		 *
		 * @param \WP_User $user - The user which status should be set.
		 *
		 * @return string
		 *
		 * @see \WP2FA\Admin\User_Listing
		 * @since 1.7.0
		 */
		public static function set_user_status( \WP_User $user ) {
			$status      = User_Utils::determine_user_2fa_status( $user );
			$status_data = User_Utils::extract_statuses( $status );
			if ( ! empty( $status_data ) ) {
				self::set_2fa_status( $status_data['id'], $user );

				return $status_data['label'];
			}

			return '';
		}

		/**
		 * Send email to setup authentication.
		 *
		 * @param [type] $user_id - The ID of the user.
		 *
		 * @return bool
		 */
		public static function send_expired_grace_email( $user_id ) {
			// Bail if the user has not enabled this email.
			if ( 'enable_account_locked_email' !== WP2FA::get_wp2fa_email_templates( 'send_account_locked_email' ) ) {
				return false;
			}

			// Grab user data.
			$user = get_userdata( $user_id );
			// Grab user email.
			$email = $user->user_email;

			$subject = wp_strip_all_tags( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_locked_email_subject' ), $user_id ) );
			$message = wpautop( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_locked_email_body' ), $user_id ) );

			return Settings_Page::send_email( $email, $subject, $message );
		}

		/**
		 * Checks if user needs to reconfigure the method
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return boolean
		 */
		public static function needs_to_reconfigure_method( $user = null ): bool {

			self::set_proper_user( $user );

			return ( ! empty( self::get_user_needs_to_reconfigure_2fa( self::get_user() ) ) && ! self::get_nag_status() && empty( self::get_enabled_method_for_user( self::get_user() ) ) );
		}

		/**
		 * Locks the user account if the grace period setting is configured and the user is currently out of their grace
		 * period. It also takes care of sending the "account locked" email to the user if not already sent before.
		 *
		 * @return bool True if the user account is locked. False otherwise.
		 */
		private static function lock_user_account_if_needed() {
			$settings = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME );
			if ( ! is_array( $settings ) || ( isset( $settings['enforcement-policy'] ) && 'do-not-enforce' === $settings['enforcement-policy'] ) ) {
				// 2FA is not enforced, make sure to clear any related user meta previously created
				self::remove_meta( WP_2FA_PREFIX . 'is_locked' );
				self::remove_user_expiry_date();
				self::remove_meta( WP_2FA_PREFIX . 'locked_account_notification' );

				return false;
			}

			if ( self::is_excluded() ) {
				return false;
			}

			$is_user_instantly_enforced = self::get_user_enforced_instantly();
			if ( $is_user_instantly_enforced ) {
				// no need to lock the account if the user is enforced to set 2FA up instantly.
				return false;
			}

			// Do not lock if user has 2FA configured.
			$has_enabled_method = self::get_2fa_status();
			if ( 'has_enabled_methods' === $has_enabled_method ) {
				return false;
			}

			$grace_period_expiry_time = self::get_user_expiry_date();
			$grace_period_expired     = ( ! empty( $grace_period_expiry_time ) && $grace_period_expiry_time < time() );
			if ( $grace_period_expired ) {

				/**
				 * Filter can be used to prevent locking of the user account when the grace period expires.
				 *
				 * @param boolean $should_be_locked Should account be locked? True by default.
				 * @param \WP_User $user WP_User object.
				 *
				 * @return boolean True if the user account should be locked.
				 * @since 2.0.0
				 */
				$should_be_locked = apply_filters( WP_2FA_PREFIX . 'should_account_be_locked_on_grace_period_expiration', true, self::get_user() );
				if ( ! $should_be_locked ) {
					return false;
				}

				// set "grace period expired" flag.
				self::set_grace_period( true );

				/**
				 * Allow 3rd party developers to execute additional code when grace period expires (account is locked)
				 *
				 * @param \WP_User $user WP_User object.
				 *
				 * @since 2.0.0
				 */
				do_action( WP_2FA_PREFIX . 'after_grace_period_expired', self::get_user() );

				/**
				 * Filter can be used to disable the email notification about locked user account.
				 *
				 * @param boolean $can_send Can the email notification be sent? True by default.
				 * @param \WP_User $user WP_User object.
				 *
				 * @return boolean True if the email notification can be sent.
				 * @since 2.0.0
				 */
				$notify_user = apply_filters( WP_2FA_PREFIX . 'send_account_locked_notification', true, self::get_user() );
				if ( $notify_user ) {
					// Send the email to alert the user, only if we have not done so before.
					$account_notification = get_user_meta( self::get_user()->ID, WP_2FA_PREFIX . 'locked_account_notification', true );
					if ( ! $account_notification ) {
						self::send_expired_grace_email( self::get_user()->ID );
						self::set_meta( WP_2FA_PREFIX . 'locked_account_notification', true );
					}
				}

				// Grab user session and kill it, preferably with fire.
				$manager = \WP_Session_Tokens::get_instance( self::get_user()->ID );
				$manager->destroy_all();

				return true;
			}

			return false;
		}

		/**
		 * Caches and returns the globally set excluded roles
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		private static function get_excluded_roles() {
			if ( null === self::$excluded_roles ) {
				self::$excluded_roles = WP2FA::get_wp2fa_setting( 'excluded_roles' );
			}

			return self::$excluded_roles;
		}

		/**
		 * Caches and returns the globally set enforced users
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		private static function get_enforced_users() {
			if ( null === self::$enforced_users ) {
				self::$enforced_users = WP2FA::get_wp2fa_setting( 'enforced_users' );
			}

			return self::$enforced_users;
		}

		/**
		 * Caches and returns the globally set excluded sites
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		private static function get_excluded_sites() {
			if ( null === self::$excluded_sites ) {
				self::$excluded_sites = WP2FA::get_wp2fa_setting( 'excluded_sites' );
			}

			return self::$excluded_sites;
		}

		/**
		 * Caches and returns the globally set excluded users
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		private static function get_excluded_users() {
			if ( null === self::$excluded_users ) {
				self::$excluded_users = WP2FA::get_wp2fa_setting( 'excluded_users' );
			}

			return self::$excluded_users;
		}

		/**
		 * Caches and returns the globally set included sites
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		private static function get_included_sites() {
			if ( null === self::$included_sites ) {
				self::$included_sites = WP2FA::get_wp2fa_setting( 'included_sites' );
			}

			return self::$included_sites;
		}

		/**
		 * Caches and returns the globally set enforced roles
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		private static function get_enforced_roles() {
			if ( null === self::$enforced_roles ) {
				self::$enforced_roles = WP2FA::get_wp2fa_setting( 'enforced_roles' );
			}

			return self::$enforced_roles;
		}

		/**
		 * Runs the necessary checks to figure out if the user is excluded based on current plugin settings.
		 *
		 * @param \WP_User $user User to evaluate.
		 * @param array    $roles - Array with user roles.
		 * @param string   $user_login - User login name.
		 * @param int      $user_id - The id of the user.
		 *
		 * @return bool True if the user is excluded based on current plugin settings.
		 * @since 2.0.0
		 *
		 * @since 2.5.0 added params $roles, $user_login, $user_id . $user is with highest priority
		 */
		public static function run_user_exclusion_check( $user = null, $roles = null, $user_login = null, $user_id = null ) {
			if ( null !== $user ) {
				$user_roles = $user->roles;
				$user_login = $user->user_login;
				$user_id    = $user->ID;
			} else {
				/**
				 * Setting that inner class flag because if we are here that means reports are generated, and we dont need to update users meta but just to check what is currently there.
				 */
				self::$update_started = true;
				$user_roles           = $roles;
			}
			$user_excluded  = false;
			$excluded_users = self::get_excluded_users();
			if ( ! empty( $excluded_users ) ) {

				// Compare our roles with the users and see if we get a match.
				$result = in_array( $user_login, $excluded_users, true );
				if ( $result ) {
					return true;
				}
			}

			$excluded_roles = self::get_excluded_roles();
			if ( ! empty( $excluded_roles ) ) {
				$excluded_roles = array_map( 'strtolower', $excluded_roles );
				// Compare our roles with the users and see if we get a match.
				$result = array_intersect( $excluded_roles, $user_roles );
				if ( ! empty( $result ) ) {
					return true;
				}
			}

			if ( WP_Helper::is_multisite() ) {
				$excluded_sites = self::get_excluded_sites();
				if ( ! empty( $excluded_sites ) && is_array( $excluded_sites ) ) {

					foreach ( $excluded_sites as $site_id ) {
						if ( is_user_member_of_blog( $user_id, $site_id ) ) {
							// User is a member of the blog we are excluding from 2FA.
							return true;
						} else {
							// User is NOT a member of the blog we are excluding.
							$user_excluded = false;
						}
					}
				}

				$included_sites = self::get_included_sites();
				if ( $included_sites && is_array( $included_sites ) ) {
					foreach ( $included_sites as $site_id ) {
						if ( is_user_member_of_blog( $user_id, $site_id ) ) {
							$user_excluded = false;
						}
					}
				}
			}

			return $user_excluded;
		}

		/**
		 * Runs the necessary checks to figure out if the user is enforced based on current plugin settings.
		 *
		 * @param \WP_User $user User to evaluate.
		 * @param array    $roles - Array with user roles.
		 * @param string   $user_login - User login name.
		 * @param int      $user_id - The id of the user.
		 *
		 * @return bool True if the user is enforced based on current plugin settings.
		 *
		 * @since 2.0.0
		 *
		 * @since 2.5.0 added params $roles, $user_login, $user_id . $user is with highest priority
		 */
		public static function run_user_enforcement_check( $user = null, $roles = null, $user_login = null, $user_id = null ) {
			if ( null !== $user ) {
				$user_roles = $user->roles;
				$user_login = $user->user_login;
				$user_id    = $user->ID;
			} else {
				/**
				 * Setting that inner class flag because if we are here that means reports are generated, and we dont need to update users meta but just to check what is currently there.
				 */
				self::$update_started = true;
				$user_roles           = $roles;
			}

			$current_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
			$enabled_method = self::get_enabled_method_for_user( $user_id );
			$user_eligible  = false;

			if ( Settings_Utils::string_to_bool( WP2FA::get_wp2fa_setting( 'superadmins-role-exclude' ) ) && is_super_admin( $user_id ) ) {
				return false;
			}

			// Let's check the policy settings and if the user has setup totp/email by checking for the usermeta.
			if ( empty( $enabled_method ) && WP_Helper::is_multisite() && 'superadmins-only' === $current_policy ) {
				return is_super_admin( $user_id );
			} elseif ( empty( $enabled_method ) && WP_Helper::is_multisite() && 'superadmins-siteadmins-only' === $current_policy ) {
				return self::is_admin( $user_id );
			} elseif ( 'all-users' === $current_policy && empty( $enabled_method ) ) {

				$excluded_users = self::get_excluded_users();
				if ( ! empty( $excluded_users ) ) {
					// Compare our roles with the users and see if we get a match.
					$result = in_array( $user_login, $excluded_users, true );
					if ( $result ) {
						return false;
					}

					$user_eligible = true;
				}

				$excluded_roles = self::get_excluded_roles();
				if ( ! empty( $excluded_roles ) ) {

					if ( ! WP_Helper::is_multisite() ) {
						// Compare our roles with the users and see if we get a match.
						$result = array_intersect( $excluded_roles, $user_roles );

						if ( ! empty( $result ) ) {
							return false;
						}
					} else {
						$users_caps = array();
						$subsites   = get_sites();
						// Check each site and add to our array so we know each users actual roles.
						foreach ( $subsites as $subsite ) {
							$subsite_id = get_object_vars( $subsite )['blog_id'];
							global $wpdb;

							if ( 1 === (int) $subsite_id ) {
								$users_caps[] = get_user_meta( $user_id, $wpdb->base_prefix . 'capabilities', true );
							} else {
								$users_caps[] = get_user_meta( $user_id, $wpdb->base_prefix . $subsite_id . '_capabilities', true );
							}
						}

						foreach ( $users_caps as $key => $value ) {
							if ( ! empty( $value ) ) {
								foreach ( $value as $key => $value ) {
									$result = in_array( $key, $excluded_roles, true );
								}
							}
						}
						if ( ! empty( $result ) ) {
							return false;
						}
					}
				}

				if ( true === $user_eligible || empty( $enabled_method ) ) {
					return true;
				}
			} elseif ( ( 'certain-roles-only' === $current_policy || 'certain-users-only' === $current_policy ) && empty( $enabled_method ) ) {
				$enforced_users = self::get_enforced_users();
				if ( ! empty( $enforced_users ) ) {

					// Compare our roles with the users and see if we get a match.
					$result = in_array( $user_login, $enforced_users, true );
					// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
					if ( ! empty( $result ) ) {
						return true;
					}
				}

				$enforced_roles = self::get_enforced_roles();
				if ( ! empty( $enforced_roles ) ) {
					// Turn it into an array.
					$enforced_roles_array = Settings_Page::extract_roles_from_input( $enforced_roles );

					if ( ! WP_Helper::is_multisite() ) {
						// Compare our roles with the users and see if we get a match.
						$result = array_intersect( $enforced_roles_array, $user_roles );

						// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
						if ( ! empty( $result ) ) {
							return true;
						}
					} else {
						$users_caps = array();
						$subsites   = get_sites();
						// Check each site and add to our array so we know each users actual roles.
						foreach ( $subsites as $subsite ) {
							$subsite_id = get_object_vars( $subsite )['blog_id'];

							global $wpdb;

							if ( 1 === (int) $subsite_id ) {
								$users_caps[] = get_user_meta( $user_id, $wpdb->prefix . 'capabilities', true );
							} else {
								$users_caps[] = get_user_meta( $user_id, $wpdb->prefix . $subsite_id . '_capabilities', true );
							}
						}

						foreach ( $users_caps as $role_in_site ) {
							if ( ! empty( $role_in_site ) ) {
								foreach ( array_keys( $role_in_site ) as $role ) {
									if ( in_array( $role, $enforced_roles_array, true ) ) {
										// User is enforced somewhere.
										return true;
									}
								}
							}
						}
						return false;
					}
				}

				if ( Settings_Utils::string_to_bool( WP2FA::get_wp2fa_setting( 'superadmins-role-add' ) ) ) {
					return is_super_admin( $user_id );
				}
			} elseif ( 'enforce-on-multisite' === $current_policy ) {
				$included_sites = self::get_included_sites();

				foreach ( $included_sites as $site_id ) {
					if ( is_user_member_of_blog( $user_id, $site_id ) ) {
						return true;
					}
				}
			} elseif ( 'all-users' === $current_policy && ! empty( $enabled_method ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Runs the necessary checks to figure out if the user is enforced based on current plugin settings.
		 *
		 * @param \WP_User $user User to evaluate.
		 * @param array    $roles - Array with user roles.
		 * @param string   $user_login - User login name.
		 * @param int      $user_id - The id of the user.
		 *
		 * @return bool True if the user is enforced based on current plugin settings.
		 *
		 * @since 2.0.0
		 *
		 * @since 2.5.0 added params $roles, $user_login, $user_id . $user is with highest priority
		 */
		public static function is_user_enforced( $user = null, $roles = null, $user_login = null, $user_id = null ) {
			if ( null !== $user ) {
				$user_roles = $user->roles;
				$user_login = $user->user_login;
				$user_id    = $user->ID;
			} else {
				/**
				 * Setting that inner class flag because if we are here that means reports are generated, and we dont need to update users meta but just to check what is currently there.
				 */
				self::$update_started = true;
				$user_roles           = $roles;
			}

			$current_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
			$user_eligible  = false;

			if ( Settings_Utils::string_to_bool( WP2FA::get_wp2fa_setting( 'superadmins-role-exclude' ) ) && is_super_admin( $user_id ) ) {
				return false;
			}

			// Let's check the policy settings and if the user has setup totp/email by checking for the usermeta.
			if ( WP_Helper::is_multisite() && 'superadmins-only' === $current_policy ) {
				return is_super_admin( $user_id );
			} elseif ( WP_Helper::is_multisite() && 'superadmins-siteadmins-only' === $current_policy ) {
				return self::is_admin( $user_id );
			} elseif ( 'all-users' === $current_policy ) {

				$excluded_users = self::get_excluded_users();
				if ( ! empty( $excluded_users ) ) {
					// Compare our roles with the users and see if we get a match.
					$result = in_array( $user_login, $excluded_users, true );
					if ( $result ) {
						return false;
					}

					$user_eligible = true;
				}

				$excluded_roles = self::get_excluded_roles();
				if ( ! empty( $excluded_roles ) ) {

					if ( ! WP_Helper::is_multisite() ) {
						// Compare our roles with the users and see if we get a match.
						$result = array_intersect( $excluded_roles, $user_roles );

						if ( ! empty( $result ) ) {
							return false;
						}
					} else {
						$users_caps = array();
						$subsites   = get_sites();
						// Check each site and add to our array so we know each users actual roles.
						foreach ( $subsites as $subsite ) {
							$subsite_id = get_object_vars( $subsite )['blog_id'];
							global $wpdb;

							if ( 1 === (int) $subsite_id ) {
								$users_caps[] = get_user_meta( $user_id, $wpdb->base_prefix . 'capabilities', true );
							} else {
								$users_caps[] = get_user_meta( $user_id, $wpdb->base_prefix . $subsite_id . '_capabilities', true );
							}
						}

						foreach ( $users_caps as $key => $value ) {
							if ( ! empty( $value ) ) {
								foreach ( $value as $key => $value ) {
									$result = in_array( $key, $excluded_roles, true );
								}
							}
						}
						if ( ! empty( $result ) ) {
							return false;
						}
					}
				}

				if ( true === $user_eligible ) {
					return true;
				}
			} elseif ( ( 'certain-roles-only' === $current_policy || 'certain-users-only' === $current_policy ) ) {
				$enforced_users = self::get_enforced_users();
				if ( ! empty( $enforced_users ) ) {

					// Compare our roles with the users and see if we get a match.
					$result = in_array( $user_login, $enforced_users, true );
					// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
					if ( ! empty( $result ) ) {
						return true;
					}
				}

				$enforced_roles = self::get_enforced_roles();
				if ( ! empty( $enforced_roles ) ) {
					// Turn it into an array.
					$enforced_roles_array = Settings_Page::extract_roles_from_input( $enforced_roles );

					if ( ! WP_Helper::is_multisite() ) {
						// Compare our roles with the users and see if we get a match.
						$result = array_intersect( $enforced_roles_array, $user_roles );

						// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
						if ( ! empty( $result ) ) {
							return true;
						}
					} else {
						$users_caps = array();
						$subsites   = get_sites();
						// Check each site and add to our array so we know each users actual roles.
						foreach ( $subsites as $subsite ) {
							$subsite_id = get_object_vars( $subsite )['blog_id'];

							global $wpdb;

							if ( 1 === (int) $subsite_id ) {
								$users_caps[] = get_user_meta( $user_id, $wpdb->prefix . 'capabilities', true );
							} else {
								$users_caps[] = get_user_meta( $user_id, $wpdb->prefix . $subsite_id . '_capabilities', true );
							}
						}

						foreach ( $users_caps as $key => $value ) {
							if ( ! empty( $value ) ) {
								foreach ( $value as $key => $value ) {
									$result = in_array( $key, $enforced_roles_array, true );
								}
							}
						}
						if ( ! empty( $result ) ) {
							return true;
						}
					}
				}

				if ( Settings_Utils::string_to_bool( WP2FA::get_wp2fa_setting( 'superadmins-role-add' ) ) ) {
					return is_super_admin( $user_id );
				}
			} elseif ( 'enforce-on-multisite' === $current_policy ) {
				$included_sites = self::get_included_sites();

				foreach ( $included_sites as $site_id ) {
					if ( is_user_member_of_blog( $user_id, $site_id ) ) {
						return true;
					}
				}
			} elseif ( 'all-users' === $current_policy ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns the nominated email for user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.6.0
		 */
		public static function get_nominated_email_for_user( $user = null ) {
			self::set_proper_user( $user );

			$email = self::get_meta( self::USER_NOMINATED_EMAIL );

			if ( empty( $email ) || ! isset( $email ) ) {
				$email = self::get_user()->user_email;
			}

			return $email;
		}

		/**
		 * Sets the nominated email for the user. If the email is the same as the current user email from the WP - the meta is not populated.
		 *
		 * @param string            $email - The token to set for the user.
		 * @param int|\WP_User|null $user  - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.6.0
		 */
		public static function set_nominated_email_for_user( string $email, $user = null ) {
			self::set_proper_user( $user );

			$email = \sanitize_email( \wp_unslash( $email ) );

			if ( ! empty( $email ) ) {
				if ( self::get_user()->user_email !== $email ) {
					return self::set_meta( self::USER_NOMINATED_EMAIL, $email );
				} else {
					self::remove_nominated_email_for_user( $user );
				}
			}

			return false;
		}

		/**
		 * Removes the nominated email for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function remove_nominated_email_for_user( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_NOMINATED_EMAIL, self::$user );
		}


		/**
		 * Returns the backup email for user. If the data stored in meta is = 'wp_mail' that means that the user email should be extracted from the WP BE.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.6.0
		 */
		public static function get_backup_email_for_user( $user = null ) {
			self::set_proper_user( $user );

			$email = self::get_meta( self::USER_BACKUP_EMAIL );

			if ( empty( $email ) || ! isset( $email ) ) {
				$email = self::get_user()->user_email;
			}

			if ( isset( $email ) && 'wp_mail' === $email ) {
				$email = self::get_user()->user_email;
			}

			return $email;
		}

		/**
		 * Sets the backup email for the user. If the email is the same as the current user email from the WP - the meta is not populated.
		 *
		 * @param string            $email - The token to set for the user.
		 * @param int|\WP_User|null $user  - The WP user we should extract the meta data for.
		 *
		 * @return mixed
		 *
		 * @since 2.6.0
		 */
		public static function set_backup_email_for_user( string $email, $user = null ) {
			self::set_proper_user( $user );

			$email = \sanitize_email( \wp_unslash( $email ) );

			if ( ! empty( $email ) ) {
				if ( self::get_user()->user_email !== $email ) {
					return self::set_meta( self::USER_BACKUP_EMAIL, $email );
				} elseif ( self::get_user()->user_email === $email ) {
					return self::set_meta( self::USER_BACKUP_EMAIL, 'wp_mail' );
				} else {
					self::remove_backup_email_for_user( $user );
				}
			}

			return false;
		}

		/**
		 * Removes the backup email for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function remove_backup_email_for_user( $user = null ) {
			self::set_proper_user( $user );

			self::remove_meta( self::USER_BACKUP_EMAIL, self::$user );
		}

		/**
		 * Updates teh user metadata. Checks for changes in the global settings, and if it finds some, checks these against the given user metadata settings hash and updates the user metadata if necessary.
		 *
		 * @return void
		 *
		 * @since 2.4.1
		 */
		private static function update_meta_if_necessary() {
			$global_settings_hash = Settings_Utils::get_option( WP_2FA_PREFIX . 'settings_hash' );
			if ( ! empty( $global_settings_hash ) ) {
				$stored_hash = self::get_global_settings_hash_for_user( self::get_user() );
				if ( $global_settings_hash !== $stored_hash ) {
					self::set_global_settings_hash_for_user( $global_settings_hash, self::get_user() );
					// update necessary user attributes (user meta) based on changed settings; the enforcement check
					// needs to run first as function "set_user_policies_and_grace" relies on having the correct values.
					self::check_methods_and_set_user();
					self::update_user_state( self::get_user() );
					self::set_user_policies_and_grace();
					self::remove_backup_methods( self::get_user() );
				}
				self::lock_user_account_if_needed();
			}
		}

		/**
		 * Sets the proper user policies and grace.
		 *
		 * @return void
		 *
		 * @since 2.4.1
		 */
		private static function set_user_policies_and_grace() {
			$enabled_methods_for_the_user = self::get_enabled_method_for_user( self::get_user() );
			if ( ! empty( $enabled_methods_for_the_user ) ) {
				self::remove_user_enforced_instantly( self::get_user() );
				self::remove_user_expiry_date( self::get_user() );
				self::remove_user_needs_to_reconfigure_2fa( self::get_user() );
				self::set_user_status( self::get_user() );

				return;
			}

			if ( self::is_enforced( self::get_user()->ID ) ) {
				$grace_policy = Settings::get_role_or_default_setting( 'grace-policy', self::get_user() );

				// Check if want to apply the custom period, or instant expiry.
				if ( 'use-grace-period' === $grace_policy ) {
					$custom_grace_period_duration =
					Settings::get_role_or_default_setting( 'grace-period', self::get_user() ) . ' ' . Settings::get_role_or_default_setting( 'grace-period-denominator', self::get_user() );
					$grace_expiry                 = strtotime( $custom_grace_period_duration );
					self::remove_user_enforced_instantly( self::get_user() );
				} else {
					$grace_expiry = time();
				}

				self::set_user_expiry_date( (string) $grace_expiry, self::get_user() );
				if ( 'no-grace-period' === $grace_policy ) {
					self::set_user_enforced_instantly( true, self::get_user() );
				}
			} else {
				self::remove_user_enforced_instantly( self::get_user() );
				self::remove_user_expiry_date( self::get_user() );
				self::remove_user_needs_to_reconfigure_2fa( self::get_user() );
			}

			// update the 2FA status meta field.
			self::set_user_status( self::get_user() );
		}

		/**
		 * Checks the user methods and sets the user status.
		 *
		 * @return void
		 *
		 * @since 2.4.1
		 */
		private static function check_methods_and_set_user() {
			if ( ! self::get_user_needs_to_reconfigure_2fa( self::get_user() ) ) {
				$enabled_methods_for_the_user = self::get_enabled_method_for_user( self::get_user() );

				if ( empty( $enabled_methods_for_the_user ) ) {
					return;
				}

				$global_methods = Methods::get_available_2fa_methods();
				if ( empty( \array_intersect( array( $enabled_methods_for_the_user ), $global_methods ) ) ) {
					self::remove_enabled_method_for_user( self::get_user() );
					if ( self::is_enforced( self::get_user()->ID ) ) {
						self::set_user_needs_to_reconfigure_2fa( true, self::get_user() );
					}
				}
			}
		}

		/**
		 * Calls all the backup methods and gives them and option to remove their stored values.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		private static function remove_backup_methods( $user = null ) {
			self::set_proper_user( $user );
			\do_action( WP_2FA_PREFIX . 'remove_backup_methods_for_user', self::get_user() );
		}

		/**
		 * Sets the local variable class based on the given parameter.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		private static function set_proper_user( $user = null ) {
			if ( null !== $user ) {
				self::set_user( $user );
			} else {
				self::get_user();
			}

			if ( false !== self::$user && 0 !== self::$user->ID && false === self::$update_started ) {
				self::$update_started = true;

				self::update_meta_if_necessary();
			}
		}
	}
}
