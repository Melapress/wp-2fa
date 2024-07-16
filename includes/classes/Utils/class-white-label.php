<?php
/**
 * Responsible for white labeling functionality.
 *
 * @package    wp2fa
 * @subpackage white-label
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Utils;

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
		 * Inits the plugin related classes and settings
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function init() {

			self::$plugin_settings[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] = Settings_Utils::get_option( WP_2FA_WHITE_LABEL_SETTINGS_NAME, array() );
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
			$default_settings = self::get_default_settings();

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
			$default_settings = array(
				'default-text-code-page'                   => '<p>' . __( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email.', 'wp-2fa' ) . '</p><p><strong>' . __( 'Note: if you are supposed to receive an email but did not receive any, please click the Resend Code button to request another code.', 'wp-2fa' ) . '</strong></p>',
				'default-text-pw-reset-code-page'          => '<p>' . __( 'You have been sent a one-time code via email. Please enter the code below and then click Get New Password to proceed with the password reset.', 'wp-2fa' ) . '</p><br><p><strong>' . __( 'Note: If you have not received the code please click the button Resend Code. If you still do not get the code after pressing the button, please contact the website\'s administrator.', 'wp-2fa' ) . '</strong></p>',
				'default-2fa-required-notice'              => '<p>' . __( 'This website\'s administrator requires you to enable two-factor authentication (2FA) {grace_period_remaining}.', 'wp-2fa' ) . '</p><br><p>' . __( 'Failing to configure 2FA within this time period will result in a locked account. For more information, please contact your website administrator.', 'wp-2fa' ) . '</p>',
				'default-2fa-resetup-required-notice'      => '<p>' . __( 'This website\'s administrator requires you to enable two-factor authentication (2FA) {grace_period_remaining}.', 'wp-2fa' ) . '</p><br><p>' . __( 'Failing to configure 2FA within this time period will result in a locked account. For more information, please contact your website administrator.', 'wp-2fa' ) . '</p>',
				'custom-text-authy-code-page-intro'        => __( 'If you are using the Authy app approve the OneTouch request to log in.', 'wp-2fa' ),
				'custom-text-authy-code-page-awaiting'     => __( 'Waiting for approval from application...', 'wp-2fa' ),
				'custom-text-authy-code-page'              => __( 'Manually enter the code from the mobile app.', 'wp-2fa' ),
				'custom-text-twilio-code-page'             => __( 'Enter the 2FA code you have received over SMS.', 'wp-2fa' ),
				'custom-text-clickatell-code-page'         => __( 'Enter the 2FA code you have received over SMS.', 'wp-2fa' ),
				'custom-text-yubico-code-page'             => __( 'Please insert the YubiKey in a USB port and touch / click the button on the YubiKey to generate the OTP required to log in.', 'wp-2fa' ),
				'custom-text-app-code-page'                => '<p>' . __( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email.', 'wp-2fa' ) . '</p><p><strong>' . __( 'Note: if you are supposed to receive an email but did not receive any, please click the Resend Code button to request another code.', 'wp-2fa' ) . '</strong></p>',
				'custom-text-email-code-page'              => '<p>' . __( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email.', 'wp-2fa' ) . '</p><p><strong>' . __( 'Note: if you are supposed to receive an email but did not receive any, please click the Resend Code button to request another code.', 'wp-2fa' ) . '</strong></p>',

				'default-backup-code-page'                 => __( 'Enter a backup verification code.', 'wp-2fa' ),
				'method_invalid_setting'                   => 'login_block',
				'enable_wizard_styling'                    => 'enable_wizard_styling',
				'show_help_text'                           => 'show_help_text',
				'enable_wizard_logo'                       => '',
				'enable_welcome'                           => '',
				'welcome'                                  => '',
				'method_selection'                         => '<h3>' . __( 'Choose the 2FA method', 'wp-2fa' ) . '</h3>' . esc_html__(
					'There are {available_methods_count} methods available to choose from for 2FA:',
					'wp-2fa'
				),
				'method_selection_single'                  => '<h3>' . __( 'Choose the 2FA method', 'wp-2fa' ) . '</h3><p>' . __( 'Only the below 2FA method is allowed on this website:', 'wp-2fa' ) . '</p>',
				'method_help_authy_intro'                  => '<h3>' . __( 'Setting up Push notifications', 'wp-2fa' ) . '</h3><p>' . __( 'To enable push notifications enter the country and cellphone number in order to use it with this account.', 'wp-2fa' ) . '</p>',
				'method_help_twilio_intro'                 => '<h3>' . __( 'Setting up 2FA over SMS', 'wp-2fa' ) . '</h3><p>' . __( 'When you use 2FA over SMS to log in to this website you will receive your one-time code via an SMS on your cellphone. Therefore please enter the cellphone number of where you would like to receive the SMS below.', 'wp-2fa' ) . '</p>',
				'method_help_clickatell_intro'             => '<h3>' . __( 'Setting up 2FA over SMS', 'wp-2fa' ) . '</h3><p>' . __( 'When you use 2FA over SMS to log in to this website you will receive your one-time code via an SMS on your cellphone. Therefore please enter the cellphone number of where you would like to receive the SMS below.', 'wp-2fa' ) . '</p>',
				'method_help_oob_intro'                    => '<h3>' . __( 'Setting up Link over email 2FA', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the email address to where the out-of-band link should be sent:', 'wp-2fa' ) . '</p>',
				'method_help_yubico_intro'                 => '<h3>' . __( 'Setting up 2FA with YubiKey', 'wp-2fa' ) . '</h3><p>' . __( '1 - Insert your YubiKey into the computer\'s / mobile\'s USB port', 'wp-2fa' ) . '</p><p>' . __( '2 - Touch / press the button on your YubiKey to generate the OTP code, which is automatically populated below', 'wp-2fa' ) . '</p>',
				'method_verification_oob_pre'              => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code sent to your email address to finalize the setup. Once the code is confirmed and 2FA is set up, you only have to verify a login by clicking on a link sent to you via email.', 'wp-2fa' ) . '</p>',
				'method_verification_authy_pre'            => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the code from your Authy application with name {authy_name}', 'wp-2fa' ) . '</p>',
				'method_verification_twilio_pre'           => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code sent via SMS to your phone to confirm your phone number.', 'wp-2fa' ) . '</p>',
				'method_verification_clickatell_pre'       => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code sent via SMS to your phone to confirm your phone number.', 'wp-2fa' ) . '</p>',
				'method_verification_yubico_pre'           => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Touch the YubiKey again to generate the OTP code to confirm the setup. Once the code is populated below, it should be automatically saved and verified. If that does not happen by any reason, once the secret key was pasted, click "Validate & save" button below to manually save and complete the configuration.', 'wp-2fa' ) . '</p>',
				'backup_codes_intro_multi'                 => '<h3>' . __( 'Your login just got more secure', 'wp-2fa' ) . '</h3><p>' . __( 'It is recommended to configure a backup 2FA method in case you do not have access to the primary 2FA method to generate a code to log in. You can configure any of the below. You can always configure any or both from your user profile page later.', 'wp-2fa' ) . '</p>',
				'backup_codes_intro'                       => '<h3>' . __( 'Your login just got more secure', 'wp-2fa' ) . '</h3><p>' . __( 'Congratulations! You have enabled two-factor authentication for your user. You’ve just helped towards making this website more secure!', 'wp-2fa' ) . '</p>',
				'backup_codes_intro_continue'              => '<h3>' . __( 'Your login just got more secure', 'wp-2fa' ) . '</h3><p>' . __( 'Congratulations! You have enabled two-factor authentication for your user. You’ve just helped towards making this website more secure!', 'wp-2fa' ) . '</p><p>' . __( 'You should now generate the list of backup method. Although this is optional, it is highly recommended to have a secondary 2FA method. This can be used as a backup should the primary 2FA method fail. This can happen if, for example, you forget your smartphone, the smartphone runs out of battery, or there are email deliverability problems.', 'wp-2fa' ) . '</p>',
				'backup_codes_generate_intro'              => '<h3>' . __( 'Generate list of backup codes', 'wp-2fa' ) . '</h3><p>' . __( 'It is recommended to generate and print some backup codes in case you lose access to your primary 2FA method.', 'wp-2fa' ) . '</p>',
				'backup_codes_generated'                   => '<h3>' . __( 'Backup codes generated', 'wp-2fa' ) . '</h3><p>' . __( 'Here are your backup codes:', 'wp-2fa' ) . '</p>',
				'no_further_action'                        => '<h3>' . __( 'Congratulations! You are all set.', 'wp-2fa' ),
				'2fa_required_intro'                       => '<h3>' . __( 'You are required to configure 2FA.', 'wp-2fa' ) . '</h3><p>' . __( 'In order to keep this site - and your details secure, this website’s administrator requires you to enable 2FA authentication to continue.', 'wp-2fa' ) . '</p><p>' . __( 'Two factor authentication ensures only you have access to your account by creating an added layer of security when logging in -', 'wp-2fa' ) . ' <a href="https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank" rel="noopener">' . __( 'Learn more', 'wp-2fa' ) . '</a></p>',
				'authy_reconfigure_intro'                  => '<h3>' . __( '{reconfigure_or_configure_capitalized} push notification method', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to {reconfigure_or_configure} the push notifications configuration.', 'wp-2fa' ) . '</p>',
				'authy_reconfigure_intro_unavailable'      => '<h3>' . __( '{reconfigure_or_configure_capitalized} push notification method', 'wp-2fa' ) . '</h3><p>' . __( 'The 2FA service you want to use is currently unavailable. Please try again later or restart the wizard to choose another method.', 'wp-2fa' ) . '</p>',
				'twilio_reconfigure_intro'                 => '<h3>' . __( '{reconfigure_or_configure_capitalized} SMS method (Twilio)', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to {reconfigure_or_configure} the mobile phone number where the one-time code should be sent.', 'wp-2fa' ) . '</p>',
				'clickatell_reconfigure_intro'             => '<h3>' . __( '{reconfigure_or_configure_capitalized} SMS method (Clickatell)', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the phone where code should be send:', 'wp-2fa' ) . '</p>',
				'yubico_reconfigure_intro'                 => '<h3>' . __( '{reconfigure_or_configure_capitalized} 2FA over YubiKey', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to {reconfigure_or_configure} the YubiKey associated with your user.', 'wp-2fa' ) . '</p>',
				'twilio_reconfigure_intro_unavailable'     => '<h3>' . __( '{reconfigure_or_configure_capitalized} SMS method', 'wp-2fa' ) . '</h3><p>' . __( 'The 2FA over SMS service you want to use is currently unavailable. Please try again later or restart the wizard to choose another method.', 'wp-2fa' ) . '</p>',
				'clickatell_reconfigure_intro_unavailable' => '<h3>' . __( '{reconfigure_or_configure_capitalized} SMS method', 'wp-2fa' ) . '</h3><p>' . __( 'The 2FA over SMS service you want to use is currently unavailable. Please try again later or restart the wizard to choose another method.', 'wp-2fa' ) . '</p>',
				'yubico_reconfigure_intro_unavailable'     => '<h3>' . __( ' {reconfigure_or_configure_capitalized} 2FA over YubiKey', 'wp-2fa' ) . '</h3><p>' . __( 'The Yubico service you want to use is currently unavailable. Please try again later or restart the wizard to choose another method.', 'wp-2fa' ) . '</p>',
				'oob_reconfigure_intro'                    => '<h3>' . __( '{reconfigure_or_configure_capitalized} link over email method', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to {reconfigure_or_configure} the email address where the link should be sent.', 'wp-2fa' ) . '</p>',
				'custom_css'                               => '',
				'login_custom_css'                         => '',
				'logo-code-page'                           => '',
				'disable_login_css'                        => '',
				'login-to-view-area'                       => '<p>' . __( 'You must be logged in to view this page. {login_url}', 'wp-2fa' ) . '</p>',
				'backup_email_intro'                       => '<h3>' . __( 'Your login just got more secure', 'wp-2fa' ) . '</h3><p>' . __( 'Well done on configuring 2FA, your login has just got more secure. To make sure you never get locked out you are required to confirm your email address and use email as an alternative and backup 2FA method in case your primary method is unavailable. Please confirm your email address below', 'wp-2fa' ) . '</p>',
				'user-profile-form-preamble-title'         => __( 'Two-factor authentication settings', 'wp-2fa' ),
				'user-profile-form-preamble-desc'          => __( 'Add two-factor authentication to strengthen the security of your user account.', 'wp-2fa' ),
				'use_custom_2fa_message'                   => 'use-defaults',
			);

			/**
			 * Gives the ability to filter the default settings array of the plugin
			 *
			 * @param array $settings - The array with all the default settings.
			 *
			 * @since 2.0.0
			 */
			$default_settings = \apply_filters( WP_2FA_PREFIX . 'white_label_default_settings', $default_settings );

			return $default_settings;
		}
	}
}
