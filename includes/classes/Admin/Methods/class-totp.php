<?php
/**
 * Responsible for WP2FA user's TOTP manipulation.
 *
 * @package    wp2fa
 * @subpackage methods
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 *
 * @since 2.6.0
 */

declare(strict_types=1);

namespace WP2FA\Methods;

use WP2FA\WP2FA;
use WP2FA\Admin\User_Profile;
use WP2FA\Authenticator\Open_SSL;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use WP2FA\Methods\Wizards\TOTP_Wizard_Steps;

/**
 * Class for handling totp codes.
 *
 * @since 2.6.0
 *
 * @package WP2FA
 */
if ( ! class_exists( '\WP2FA\Methods\TOTP' ) ) {
	/**
	 * TOTP code class, for handling totp (app) code generation and such.
	 *
	 * @since 2.6.0
	 */
	class TOTP {

		/**
		 * The name of the method.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const METHOD_NAME = 'totp';

		/**
		 * Secret TOTP key meta name.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const TOTP_META_KEY = WP_2FA_PREFIX . 'totp_key';

		/**
		 * The name of the method stored in the policy
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const POLICY_SETTINGS_NAME = 'enable_totp';

		/**
		 * Is the totp method enabled
		 *
		 * @since 1.7
		 *
		 * @var bool
		 */
		private static $totp_enabled = null;

		/**
		 * Totp key assigned to user
		 *
		 * @var string
		 */
		private static $totp_key = '';

		/**
		 * Inits the class and sets the filters.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function init() {

			\add_filter( WP_2FA_PREFIX . 'providers_translated_names', array( __CLASS__, 'totp_provider_name_translated' ) );

			\add_filter( WP_2FA_PREFIX . 'providers', array( __CLASS__, 'totp_provider' ) );

			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );

			\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'settings_loop' ), 10, 1 );

			\add_filter( WP_2FA_PREFIX . 'no_method_enabled', array( __CLASS__, 'return_default_selection' ), 10, 1 );

			// add the TOTP methods to the list of available methods if enabled.
			\add_filter(
				WP_2FA_PREFIX . 'available_2fa_methods',
				function ( $available_methods ) {
					if ( ! empty( Settings::get_role_or_default_setting( self::POLICY_SETTINGS_NAME, 'current' ) ) ) {
						array_push( $available_methods, self::METHOD_NAME );
					}

					return $available_methods;
				}
			);

			TOTP_Wizard_Steps::init();
		}

		/**
		 * Adds TOTP translated name
		 *
		 * @param array $providers - Array with all currently supported providers and their translated names.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function totp_provider_name_translated( array $providers ) {
			$providers[ self::METHOD_NAME ] = esc_html__( 'TOTP (one-time code via app)', 'wp-2fa' );

			return $providers;
		}

		/**
		 * Extracts the selected value from the global settings (if set), and adds it to the output array
		 *
		 * @param array $output - The array with output values.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function return_default_selection( array $output ) {
			// No method is enabled, fall back to previous selected one - we don't want to break the logic.
			$totp_enabled = WP2FA::get_wp2fa_setting( self::POLICY_SETTINGS_NAME );

			if ( $totp_enabled ) {
				$output[ self::POLICY_SETTINGS_NAME ] = $totp_enabled;
			}

			return $output;
		}

		/**
		 * Sets the TOTP as a method for the given user
		 *
		 * @param \WP_User $user - The user for which the method has to be set, if null, it uses the current user.
		 * @param string   $totp_key - The totp key for the user to be set.
		 *
		 * @return void
		 *
		 * @throws \LogicException - If the method is called without $totp_key.
		 *
		 * @since 2.6.0
		 */
		public static function set_user_method( $user = null, string $totp_key = '' ) {
			if ( null === $user ) {
				$user = wp_get_current_user();
			}

			if ( '' === \trim( $totp_key ) ) {
				throw new \LogicException( 'TOTP key must not be empty' );
			}

			User_Helper::set_enabled_method_for_user( self::METHOD_NAME, $user );
			self::set_user_totp_key( $totp_key, $user );
			User_Profile::delete_expire_and_enforced_keys( $user->ID );
			User_Helper::set_user_status( $user );
		}

		/**
		 * Adds TOTP as a provider
		 *
		 * @param array $providers - Array with all currently supported providers.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function totp_provider( array $providers ) {
			array_push( $providers, self::METHOD_NAME );

			return $providers;
		}

		/**
		 * Retrieves the QR code
		 *
		 * @since 2.6.0
		 *
		 * @return string
		 */
		public static function get_qr_code(): string {

			// Setup site information, used when generating our QR code.
			$site_name = site_url();
			$site_name = trim( str_replace( array( 'http://', 'https://' ), '', (string) $site_name ), '/' );
			/**
			 * Changing the title of the login screen for the TOTP method.
			 *
			 * @param string $title - The default title.
			 * @param \WP_User $user - The WP user.
			 *
			 * @since 2.0.0
			 */
			$totp_title = apply_filters(
				WP_2FA_PREFIX . 'totp_title',
				$site_name . ':' . User_Helper::get_user_object()->user_login,
				User_Helper::get_user_object()
			);

			return Authentication::get_google_qr_code( $totp_title, self::get_totp_key(), $site_name );
		}

		/**
		 * Validates authentication.
		 *
		 * @param \WP_User $user - The WP user, if presented.
		 *
		 * @return bool Whether the user gave a valid code
		 *
		 * @since 2.6.0
		 */
		public static function validate_totp_authentication( \WP_User $user = null ) {
			if ( ! empty( $_REQUEST['authcode'] ) ) {  //phpcs:ignore
				$valid = Authentication::is_valid_authcode(
					self::get_totp_key( $user ),
					\sanitize_text_field( \wp_unslash( $_REQUEST['authcode'] ) )
				);
				if ( $valid ) {
					Authentication::clear_login_attempts( $user );
				} else {
					Authentication::increase_login_attempts( $user );
				}
				return $valid;
			}

			return false;
		}

		/**
		 * Add extension settings to the loop array
		 *
		 * @param array $loop_settings - Currently available settings array.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function settings_loop( array $loop_settings ) {
			array_push( $loop_settings, self::POLICY_SETTINGS_NAME );

			return $loop_settings;
		}

		/**
		 * Returns the status of the totp method (enabled | disabled)
		 *
		 * @since 2.6.0
		 *
		 * @return boolean
		 */
		public static function is_enabled(): bool {
			if ( null === self::$totp_enabled ) {
				self::$totp_enabled = empty( Settings::get_role_or_default_setting( self::POLICY_SETTINGS_NAME, 'current' ) ) ? false : true;
			}

			return self::$totp_enabled;
		}

		/**
		 * Regenerates the TOTP key for the user
		 *
		 * @return void - JSON - object with "key" - stores the new key and "qr" - stores the new QR code.
		 *
		 * @since 2.5.0
		 */
		public static function regenerate_authentication_key() {
			// Grab current user.
			$user = wp_get_current_user();

			$key = Authentication::generate_key();

			$site_name = site_url();
			$site_name = trim( str_replace( array( 'http://', 'https://' ), '', (string) $site_name ), '/' );

			/**
			 * Changing the title of the login screen for the TOTP method.
			 *
			 * @param string $title - The default title.
			 * @param \WP_User $user - The WP user.
			 *
			 * @since 2.0.0
			 */
			$totp_title = apply_filters( WP_2FA_PREFIX . 'totp_title', $site_name . ':' . $user->user_login, $user );
			$new_qr     = Authentication::get_google_qr_code( $totp_title, $key, $site_name );

			wp_send_json_success(
				array(
					'key' => Authentication::decrypt_key_if_needed( $key ),
					'qr'  => $new_qr,
				)
			);
		}

		/**
		 * Adds the extension default settings to the main plugin settings
		 *
		 * @param array $default_settings - array with plugin default settings.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function add_default_settings( array $default_settings ) {
			$default_settings[ self::POLICY_SETTINGS_NAME ]   = self::POLICY_SETTINGS_NAME;
			$default_settings['method_help_totp_intro']       = '<h3>' . __( 'Setting up TOTP (one-time code via app)', 'wp-2fa' ) . '</h3>';
			$default_settings['method_help_totp_step_1']      = __( 'Download and start the application of your choice', 'wp-2fa' );
			$default_settings['method_help_totp_step_2']      = __( 'From within the application scan the QR code provided on the left. Otherwise, enter the following code manually in the application:', 'wp-2fa' );
			$default_settings['method_help_totp_step_3']      = __( 'Click the "I\'m ready" button below when you complete the application setup process to proceed with the wizard.', 'wp-2fa' );
			$default_settings['method_verification_totp_pre'] = '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code from your chosen authentication app to finalize the setup.', 'wp-2fa' ) . '</p>';
			$default_settings['totp_reconfigure_intro']       = '<h3>' . __( '{reconfigure_or_configure_capitalized} the 2FA App', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to {reconfigure_or_configure} the current 2FA method. Note that once reset you will have to re-scan the QR code on all devices you want this to work on because the previous codes will stop working.', 'wp-2fa' ) . '</p>';
			$default_settings['totp-option-label']            = __( 'One-time code via 2FA app', 'wp-2fa' );
			$default_settings['totp-option-label-hint']       = sprintf(
				/* translators: link to the knowledge base website */
				\esc_html__( 'Refer to the %s for more information on how to setup these apps and which apps are supported.', 'wp-2fa' ),
			'<a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank">' . \esc_html__( 'guide on how to set up 2FA apps', 'wp-2fa' ) . '</a>'
			);

			return $default_settings;
		}

		/**
		 * User totp key getter
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_totp_key( $user = null ): string {
			if ( '' === trim( (string) self::$totp_key ) ) {
				self::$totp_key = self::get_user_totp_key_auth( User_Helper::get_user( $user )->ID );
				if ( empty( self::$totp_key ) ) {
					self::$totp_key = Authentication::generate_key();

					self::set_user_totp_key( self::$totp_key, $user );
				} elseif ( Open_SSL::is_ssl_available() && false === \strpos( self::$totp_key, Open_SSL::SECRET_KEY_PREFIX ) ) {
						self::$totp_key = Open_SSL::SECRET_KEY_PREFIX . Open_SSL::encrypt( self::$totp_key );
						self::set_user_totp_key( self::$totp_key, $user );
				}
			}

			return self::$totp_key;
		}

		/**
		 * Returns the encoded TOTP when we need to show the actual code to the user
		 * If for some reason the code is invalid it recreates it
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_totp_decrypted( $user = null ): string {
			$key = self::get_totp_key( $user );
			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, 'ssl_' ) ) {

				/**
				 * Old key detected - convert.
				 */
				$key = Open_SSL::decrypt_legacy( substr( $key, 4 ) );

				self::remove_user_totp_key( $user );
				self::$totp_key = '';

				$key = self::get_totp_key( $user );
			}

			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, 'wps_' ) ) {

				/**
				 * Old key detected - convert.
				 */
				$key = Open_SSL::decrypt_wps( substr( $key, 4 ) );

				self::remove_user_totp_key( $user );

				$secret = Open_SSL::encrypt( $key );

				if ( Open_SSL::is_ssl_available() ) {
					$secret = Open_SSL::SECRET_KEY_PREFIX . $secret;
				}

				self::set_user_totp_key( $secret, $user );

				self::$totp_key = $secret;
			}

			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, Open_SSL::SECRET_KEY_PREFIX ) ) {
				$key = Open_SSL::decrypt( substr( $key, 4 ) );

				/**
				 * If for some reason the key is not valid, that means that we have to clear the stored TOTP for the user, and create new on
				 * That could happen if the global stored secret (plugin level) is deleted.
				 *
				 * Lets check and if that is the case - create new one
				 */
				if ( ! Authentication::validate_base32_string( $key ) ) {
					self::$totp_key = '';
					self::remove_user_totp_key( $user );
					$key = self::get_totp_key( $user );
					$key = Open_SSL::decrypt( substr( $key, 4 ) );
				}
			}

			return $key;
		}

		/**
		 * Deletes the TOTP secret key for a user.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return void
		 */
		public static function remove_user_totp_key( $user = null ) {
			User_Helper::remove_meta( self::TOTP_META_KEY, $user );

			self::$totp_key = '';
		}

		/**
		 * Returns the TOTP secret key for a user.
		 *
		 * @param int|\WP_User|null $user - The WP user that must be used.
		 *
		 * @return string
		 */
		public static function get_user_totp_key( $user = null ) {
			return User_Helper::get_meta( self::TOTP_META_KEY, $user );
		}

		/**
		 * Updates the TOTP secret key for a user.
		 *
		 * @param string            $value - The value of the TOTP key.
		 * @param int|\WP_User|null $user  - The WP user that must be used.
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function set_user_totp_key( string $value, $user = null ) {
			User_Helper::set_meta( self::TOTP_META_KEY, $value, $user );
		}

		/**
		 * Get the TOTP secret key for a user.
		 *
		 * @param  int $user_id User ID.
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_user_totp_key_auth( $user_id ) {

			$key = (string) self::get_user_totp_key( $user_id );

			$test = $key;

			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, 'ssl_' ) ) {

				/**
				 * Old key detected - convert.
				 */
				$key = Open_SSL::decrypt_legacy( substr( $key, 4 ) );

				self::remove_user_totp_key();

				$secret = Open_SSL::encrypt( $key );

				if ( Open_SSL::is_ssl_available() ) {
					$secret = Open_SSL::SECRET_KEY_PREFIX . $secret;
				}

				self::set_user_totp_key( $key, $user_id );

				$test = $key = (string) self::get_user_totp_key( $user_id ); // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			}

			// We've tried tried to use WP core functionality, but that doesn't work - lets update.
			if ( Open_SSL::is_ssl_available() && false !== \strpos( $key, 'wps_' ) ) {

				/**
				 * Old key detected - convert.
				 */
				$key = Open_SSL::decrypt_wps( substr( $key, 4 ) );

				self::remove_user_totp_key();

				$secret = Open_SSL::encrypt( $key );

				if ( Open_SSL::is_ssl_available() ) {
					$secret = Open_SSL::SECRET_KEY_PREFIX . $secret;
				}

				self::set_user_totp_key( $key, $user_id );

				$test = $key = (string) self::get_user_totp_key( $user_id );  // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			}

			Authentication::decrypt_key_if_needed( $test );

			if ( ! Authentication::is_valid_key( $test ) ) {
				$key = Authentication::generate_key();
				self::set_user_totp_key( $key, $user_id );
				Authentication::clear_decrypted_key();
			}

			return $key;
		}
	}
}
