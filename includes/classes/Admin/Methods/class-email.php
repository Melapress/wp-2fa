<?php
/**
 * Responsible for WP2FA user's email method manipulation.
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
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Methods\Wizards\Email_Wizard_Steps;

/**
 * Class for handling email codes.
 *
 * @since 2.6.0
 *
 * @package WP2FA
 */
if ( ! class_exists( '\WP2FA\Methods\Email' ) ) {
	/**
	 * Email code class, for handling email method code generation and such.
	 *
	 * @since 2.6.0
	 */
	class Email {

		/**
		 * The name of the method.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const METHOD_NAME = 'email';

		/**
		 * The name of the method stored in the policy
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const POLICY_SETTINGS_NAME = 'enable_email';

		/**
		 * Is the mail enabled
		 *
		 * @since 2.6.0
		 *
		 * @var bool
		 */
		private static $email_enabled = null;

		/**
		 * Inits the class and sets the filters.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function init() {

			\add_filter( WP_2FA_PREFIX . 'providers_translated_names', array( __CLASS__, 'email_provider_name_translated' ) );

			\add_filter( WP_2FA_PREFIX . 'providers', array( __CLASS__, 'email_provider' ) );

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

			Email_Wizard_Steps::init();
		}

		/**
		 * Adds email provider translatable name
		 *
		 * @param array $providers - Array with all currently supported providers and their translated names.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function email_provider_name_translated( array $providers ) {
			$providers[ self::METHOD_NAME ] = \esc_html__( 'HOTP (Email)', 'wp-2fa' );

			return $providers;
		}

		/**
		 * Adds email as a provider
		 *
		 * @param array $providers - Array with all currently supported providers.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function email_provider( array $providers ) {
			array_push( $providers, self::METHOD_NAME );

			return $providers;
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
			$default_settings[ self::POLICY_SETTINGS_NAME ] = self::POLICY_SETTINGS_NAME;
			$default_settings['specify-email_hotp']           = 'specify-email_hotp';
			$default_settings['method_help_hotp_intro']       = '<h3>' . __( 'Setting up HOTP (one-time code via email)', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the email address where the one-time code should be sent:', 'wp-2fa' ) . '</p>';
			$default_settings['method_help_hotp_help']        = __( 'To complete the 2FA configuration you will be sent a one-time code over email, therefore you should have access to the mailbox of this email address. If you do not receive the email with the one-time code please check your spam folder and contact your administrator', 'wp-2fa' );
			$default_settings['method_help_hotp_help_email']  = '<b>' . __( 'IMPORTANT', 'wp-2fa' ) . '</b><p>' . __( 'To ensure you always receive the one-time code whitelist the email address from which the codes are sent. This is {from_email}', 'wp-2fa' ) . '</p>';
			$default_settings['method_verification_hotp_pre'] = '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code sent to your email address to finalize the setup', 'wp-2fa' ) . '</p>';
			$default_settings['hotp_reconfigure_intro']       = '<h3>' . __( '{reconfigure_or_configure_capitalized} one-time code over email method', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to {reconfigure_or_configure} the email address where the one-time code should be sent.', 'wp-2fa' ) . '</p>';
			$default_settings['email-option-label']           = __( 'One-time code via email', 'wp-2fa' );

			return $default_settings;
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
			array_push( $loop_settings, 'specify-email_hotp' );


			return $loop_settings;
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
			$email_enabled = WP2FA::get_wp2fa_setting( self::POLICY_SETTINGS_NAME );

			if ( $email_enabled ) {
				$output[ self::POLICY_SETTINGS_NAME ] = $email_enabled;
			}

			return $output;
		}

		/**
		 * Returns the status of the mail method (enabled | disabled)
		 *
		 * @since 2.6.0
		 *
		 * @return boolean
		 */
		public static function is_enabled(): bool {
			if ( null === self::$email_enabled ) {
				self::$email_enabled = empty( Settings::get_role_or_default_setting( 'enable_email', 'current' ) ) ? false : true;
			}

			return self::$email_enabled;
		}
	}
}
