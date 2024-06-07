<?php
/**
 * Responsible for the WP core functionalities.
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

namespace WP2FA\Admin\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/*
 * WP helper class
 */
if ( ! class_exists( '\WP2FA\Admin\Helpers\WP_Helper' ) ) {
	/**
	 * All the WP functionality must go trough this class.
	 *
	 * @since 2.2.0
	 */
	class WP_Helper {
		/**
		 * Hold the user roles as array - Human readable is used for key of the array, and the internal role name is the value.
		 *
		 * @var array
		 *
		 * @since 2.2.0
		 */
		private static $user_roles = array();

		/**
		 * Hold the user roles as array - Internal role name is used for key of the array, and the human readable format is the value.
		 *
		 * @var array
		 *
		 * @since 2.2.0
		 */
		private static $user_roles_wp = array();

		/**
		 * Keeps the value of the multisite install of the WP.
		 *
		 * @var bool
		 *
		 * @since 2.2.0
		 */
		private static $is_multisite = null;

		/**
		 * Holds array with all the sites in multisite WP installation.
		 *
		 * @var array
		 */
		private static $sites = array();

		/**
		 * Inits the class, and fires all the necessarily methods.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function init() {
			if ( self::is_multisite() ) {
				\add_action( 'network_admin_notices', array( __CLASS__, 'show_critical_admin_notice' ) );
			} else {
				\add_action( 'admin_notices', array( __CLASS__, 'show_critical_admin_notice' ) );
			}
		}

		/**
		 * Checks if specific role exists.
		 *
		 * @param string $role - The name of the role to check.
		 *
		 * @since 2.2.0
		 */
		public static function is_role_exists( string $role ): bool {
			self::set_roles();

			if ( in_array( $role, self::$user_roles, true ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns the currently available WP roles - the Human readable format is the key.
		 *
		 * @return array
		 *
		 * @since 2.2.0
		 */
		public static function get_roles() {
			self::set_roles();

			return self::$user_roles;
		}

		/**
		 * Returns the currently available WP roles.
		 *
		 * @return array
		 *
		 * @since 2.2.0
		 */
		public static function get_roles_wp() {
			if ( empty( self::$user_roles_wp ) ) {
				self::set_roles();
				self::$user_roles_wp = array_flip( self::$user_roles );
			}

			return self::$user_roles_wp;
		}

		/**
		 * Shows critical notices to the admin.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function show_critical_admin_notice() {
			if ( User_Helper::is_admin() ) {
				/*
				 * Gives the ability to show notices to the admins
				 */
				\do_action( WP_2FA_PREFIX . 'critical_notice' );
			}
		}

		/**
		 * Check is this is a multisite setup.
		 *
		 * @return bool
		 *
		 * @since 2.2.0
		 */
		public static function is_multisite() {
			if ( null === self::$is_multisite ) {
				self::$is_multisite = function_exists( 'is_multisite' ) && is_multisite();
			}

			return self::$is_multisite;
		}

		/**
		 * Collects all the sites from multisite WP installation.
		 *
		 * @since 2.5.0
		 */
		public static function get_multi_sites(): array {
			if ( self::is_multisite() ) {
				if ( empty( self::$sites ) ) {
					self::$sites = \get_sites();
				}

				return self::$sites;
			}

			return array();
		}

		/**
		 * Calculating the signature.
		 *
		 * @param array $data - Array with data to create a signature for.
		 *
		 * @since 2.2.2
		 */
		public static function calculate_api_signature( array $data ): string {
			$now   = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
			$nonce = $now->getTimestamp();

			$pk_hash               = hash( 'sha512', $data['license_key'] . '|' . $nonce );
			$authentication_string = base64_encode( $pk_hash . '|' . $nonce );

			return $authentication_string;
		}

		/**
		 * Checks if that is the WP login page or not.
		 *
		 * @return bool
		 *
		 * @since 2.4.1
		 */
		public static function is_wp_login() {
			$abs_path = str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, ABSPATH );

			if ( function_exists( 'is_account_page' ) && is_account_page() ) {
				// The user is on the WooCommerce login page.

				return true;
			}

			return ( in_array( $abs_path . 'wp-login.php', get_included_files() ) || in_array( $abs_path . 'wp-register.php', get_included_files() ) ) || ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) || '/wp-login.php' == $_SERVER['PHP_SELF']; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		}

		/**
		 * Check whether we are on an admin and plugin page.
		 *
		 * @since 2.4.1
		 *
		 * @param array|string $slug ID(s) of a plugin page. Possible values: 'general', 'logs', 'about' or array of them.
		 *
		 * @return bool
		 */
		public static function is_admin_page( $slug = array() ) { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded

			$cur_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$check    = WP_2FA_PREFIX_PAGE;

			return \is_admin() && ( false !== strpos( $cur_page, $check ) );
		}

		/**
		 * Remove all non-WP Mail SMTP plugin notices from our plugin pages.
		 *
		 * @since 2.4.1
		 */
		public static function hide_unrelated_notices() {
			// Bail if we're not on our screen or page.
			if ( ! self::is_admin_page() ) {
				return;
			}

			self::remove_unrelated_actions( 'user_admin_notices' );
			self::remove_unrelated_actions( 'admin_notices' );
			self::remove_unrelated_actions( 'all_admin_notices' );
			self::remove_unrelated_actions( 'network_admin_notices' );
		}

		/**
		 * Creates a nonce for HTML field by given name.
		 *
		 * @param string $nonce_name -The name of the nonce to create.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function create_data_nonce( string $nonce_name ): string {
			return ' data-nonce="' . \esc_attr( \wp_create_nonce( $nonce_name ) ) . '"';
		}

		/**
		 * Extracts the domain part of the given string.
		 *
		 * @param string $url_to_check - The URL string to be checked.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function extract_domain( string $url_to_check ): string {
			// get the full domain.
			// $urlparts = parse_url( \site_url() );.

			if ( false !== strpos( $url_to_check, '@' ) ) {
				$domain = \explode( '@', $url_to_check )[1];

				return $domain;
			}
			$urlparts = parse_url( $url_to_check );
			$domain   = $urlparts ['host'];

			// get the TLD and domain.
			$domainparts = explode( '.', $domain );
			$domain      = $domainparts[ count( $domainparts ) - 2 ] . '.' . $domainparts[ count( $domainparts ) - 1 ];

			return $domain;
		}

		/**
		 * Remove all non-WP Mail SMTP notices from the our plugin pages based on the provided action hook.
		 *
		 * @since 2.4.1
		 *
		 * @param string $action The name of the action.
		 */
		private static function remove_unrelated_actions( $action ) {
			global $wp_filter;

			if ( empty( $wp_filter[ $action ]->callbacks ) || ! is_array( $wp_filter[ $action ]->callbacks ) ) {
				return;
			}

			foreach ( $wp_filter[ $action ]->callbacks as $priority => $hooks ) {
				foreach ( $hooks as $name => $arr ) {
					if (
						( // Cover object method callback case.
							is_array( $arr['function'] ) &&
							isset( $arr['function'][0] ) &&
							is_object( $arr['function'][0] ) &&
							false !== strpos( ( get_class( $arr['function'][0] ) ), 'WP2FA' )
						) ||
						( // Cover class static method callback case.
							! empty( $name ) &&
							false !== strpos( ( $name ), 'WP2FA' )
						)
					) {
						continue;
					}

					unset( $wp_filter[ $action ]->callbacks[ $priority ][ $name ] );
				}
			}
		}

		/**
		 * Sets the internal variable with all the existing WP roles.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		private static function set_roles() {
			if ( empty( self::$user_roles ) ) {
				global $wp_roles;

				if ( null === $wp_roles ) {
					wp_roles();
				}

				self::$user_roles = array_flip( $wp_roles->get_names() );
			}
		}
	}
}
