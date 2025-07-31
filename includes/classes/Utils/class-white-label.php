<?php
/**
 * Responsible for white labeling functionality.
 *
 * @package    wp2fa
 * @subpackage white-label
 *
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Utils;

use WP2FA\WP2FA;
use WP2FA\Admin\Controllers\Methods;

if ( ! class_exists( '\WP2FA\Utils\White_Label' ) ) {
	/**
	 * Utility class for white labeling.
	 *
	 * @package WP2FA\Utils
	 *
	 * @since 2.8.0
	 */
	class White_Label {

		/**
		 * Local static cache for plugins settings.
		 *
		 * @var array
		 *
		 * @since 2.8.0
		 */
		private static $plugin_settings = array();

		/**
		 * Class cache to store the default white label settings.
		 *
		 * @var array
		 *
		 * @since 3.0.0
		 */
		private static $default_settings = array();

		/**
		 * Inits the plugin related classes and settings
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function init() {

			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'merge_settings' ) );

			\add_filter( WP_2FA_PREFIX . 'whitelabel_settings', array( __CLASS__, 'fill_settings_array' ) );

			\add_filter( 'login_headerurl', array( __CLASS__, 'set_logo_url' ) );
		}

		/**
		 * Changes the default logo URL to the white label's custom URL.
		 *
		 * @param string $logo_url - The current logo URL.
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public static function set_logo_url( $logo_url ) {
			$custom_url = esc_url( WP2FA::get_wp2fa_white_label_setting( 'logo-code-page-url' ) );

			if ( ! empty( $custom_url ) ) {
				// Change the header title as well.
				\add_filter(
					'login_headertext',
					function() use ( $custom_url ) {
						return esc_html( $custom_url );
					}
				);

				return $custom_url;
			}

			return $logo_url;
		}

		/**
		 * Merges the default settings with the stored settings.
		 *
		 * @param array $settings_array - Collected settings.
		 *
		 * @since 3.0.0
		 */
		public static function fill_settings_array( $settings_array ) {
			$settings_array = array_merge( self::get_default_settings(), $settings_array );

			return $settings_array;
		}

		/**
		 * Util function to grab white label settings or apply defaults if no settings are saved into the db.
		 *
		 * @param  string  $setting_name Settings to grab value of.
		 * @param boolean $get_default_on_empty return default setting value if current one is empty.
		 *
		 * @return string|array               Settings value or default value.
		 *
		 * @since 2.8.0
		 */
		public static function get_setting( $setting_name = '', $get_default_on_empty = false ) {
			$default_settings = self::get_default_settings( array() );

			$white_label_setting = self::$plugin_settings[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ];

			// If we have no setting name, return them all.
			if ( empty( $setting_name ) ) {
				return $white_label_setting;
			}

			// First lets check if any options have been saved.
			if ( empty( $white_label_setting ) || ! isset( $white_label_setting ) ) {
				$apply_defaults = true;
			}

			if ( $apply_defaults ) {
				return isset( $default_settings[ $setting_name ] ) ? $default_settings[ $setting_name ] : '';
			} elseif ( ! isset( $white_label_setting[ $setting_name ] ) ) {
				if ( true === $get_default_on_empty ) {
					if ( isset( $default_settings[ $setting_name ] ) ) {
						return $default_settings[ $setting_name ];
					}
				}

				return '';
			} else {
				return $white_label_setting[ $setting_name ];
			}
		}

		/**
		 * Array with all the plugin default settings.
		 *
		 * @return array
		 *
		 * @since 2.8.0
		 */
		public static function get_default_settings() {

			if ( empty( self::$default_settings ) ) {
				self::$default_settings = array(
					'default-text-code-page'              => '<p>' . esc_html__( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email.', 'wp-2fa' ) . '</p><p><strong>' . esc_html__( 'Note: if you are supposed to receive an email but did not receive any, please click the Resend Code button to request another code.', 'wp-2fa' ) . '</strong></p>',
					'default-text-pw-reset-code-page'     => '<p>' . esc_html__( 'You have been sent a one-time code via email. Please enter the code below and then click Get New Password to proceed with the password reset.', 'wp-2fa' ) . '</p><br><p><strong>' . esc_html__( 'Note: If you have not received the code please click the button Resend Code. If you still do not get the code after pressing the button, please contact the website\'s administrator.', 'wp-2fa' ) . '</strong></p>',
					'default-2fa-required-notice'         => '<p>' . esc_html__( 'This website\'s administrator requires you to enable two-factor authentication (2FA) {grace_period_remaining}.', 'wp-2fa' ) . '</p><br><p>' . esc_html__( 'Failing to configure 2FA within this time period will result in a locked account. For more information, please contact your website administrator.', 'wp-2fa' ) . '</p>',
					'default-2fa-resetup-required-notice' => '<p>' . esc_html__( 'This website\'s administrator requires you to enable two-factor authentication (2FA) {grace_period_remaining}.', 'wp-2fa' ) . '</p><br><p>' . esc_html__( 'Failing to configure 2FA within this time period will result in a locked account. For more information, please contact your website administrator.', 'wp-2fa' ) . '</p>',

					'custom-text-app-code-page'           => '<p>' . esc_html__( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email.', 'wp-2fa' ) . '</p><p><strong>' . esc_html__( 'Note: if you are supposed to receive an email but did not receive any, please click the Resend Code button to request another code.', 'wp-2fa' ) . '</strong></p>',
					'custom-text-email-code-page'         => '<p>' . esc_html__( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email.', 'wp-2fa' ) . '</p><p><strong>' . esc_html__( 'Note: if you are supposed to receive an email but did not receive any, please click the Resend Code button to request another code.', 'wp-2fa' ) . '</strong></p>',

					'default-backup-code-page'            => esc_html__( 'Enter a backup verification code.', 'wp-2fa' ),
					'enable_wizard_styling'               => 'enable_wizard_styling',
					'show_help_text'                      => 'show_help_text',
					'enable_wizard_logo'                  => '',
					'hide_page_generated_by'              => false,
					'enable_welcome'                      => '',
					'welcome'                             => '',
					'method_selection'                    => '<h3>' . esc_html__( 'Choose the 2FA method', 'wp-2fa' ) . '</h3>' . Methods::get_number_of_methods_text(),
					// 'method_selection_single'                  => '<h3>' . esc_html__( 'Choose the 2FA method', 'wp-2fa' ) . '</h3><p>' . esc_html__( 'Only the below 2FA method is allowed on this website:', 'wp-2fa' ) . '</p>',

					'no_further_action'                   => '<h3>' . esc_html__( 'Congratulations! You are all set.', 'wp-2fa' ),
					'2fa_required_intro'                  => '<h3>' . esc_html__( 'You are required to configure 2FA.', 'wp-2fa' ) . '</h3><p>' . esc_html__( 'In order to keep this site - and your details secure, this website’s administrator requires you to enable 2FA authentication to continue.', 'wp-2fa' ) . '</p><p>' . esc_html__( 'Two factor authentication ensures only you have access to your account by creating an added layer of security when logging in -', 'wp-2fa' ) . ' <a href="https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=learn_more" target="_blank" rel="noopener">' . esc_html__( 'Learn more', 'wp-2fa' ) . '</a></p>',

					'custom_css'                          => '',
					'login_custom_css'                    => '',
					'logo-code-page'                      => '',
					'logo-code-page-url'                  => '',
					'disable_login_css'                   => '',
					'login-to-view-area'                  => '<p>' . esc_html__( 'You must be logged in to view this page. {login_url}', 'wp-2fa' ) . '</p>',

					'user-profile-form-preamble-title'    => esc_html__( 'Two-factor authentication settings', 'wp-2fa' ),
					'user-profile-form-preamble-desc'     => esc_html__( 'Add two-factor authentication to strengthen the security of your user account.', 'wp-2fa' ),
					'use_custom_2fa_message'              => 'use-defaults',
				);

				/**
				 * Gives the ability to filter the default settings array of the plugin
				 *
				 * @param array $settings - The array with all the default settings.
				 *
				 * @since 2.0.0
				 */
				self::$default_settings = \apply_filters( WP_2FA_PREFIX . 'white_label_default_settings', self::$default_settings );
			}

			return self::$default_settings;
		}

		/**
		 * Array with all the plugin default settings.
		 *
		 * @param array $settings - Currently collected settings.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function merge_settings( $settings ) {

			$settings[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] = self::get_default_settings();

			return $settings;
		}
	}
}
