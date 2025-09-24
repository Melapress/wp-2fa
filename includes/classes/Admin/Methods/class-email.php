<?php
/**
 * Responsible for WP2FA user's email method manipulation.
 *
 * @package    wp2fa
 * @subpackage methods
 *
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 *
 * @since 2.6.0
 */

declare(strict_types=1);

namespace WP2FA\Methods;

use WP2FA\WP2FA;
use WP2FA\Admin\Setup_Wizard;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use WP2FA\Admin\Controllers\API\API_Login;
use WP2FA\Admin\Methods\Traits\Providers;
use WP2FA\Methods\Wizards\Email_Wizard_Steps;
use WP2FA\Admin\SettingsPages\Settings_Page_White_Label;

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

		use Providers;

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
		public const POLICY_SETTINGS_NAME = 'enable_' . self::METHOD_NAME;

		/**
		 * Is the mail enabled
		 *
		 * @since 2.6.0
		 *
		 * @var bool
		 */
		private static $enabled = null;

		/**
		 * Is the mail enforced
		 *
		 * @since 3.0.0
		 *
		 * @var bool
		 */
		private static $email_enforced = null;

		/**
		 * Inits the class and sets the filters.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function init() {

			self::always_init();

			\add_filter( WP_2FA_PREFIX . 'providers_translated_names', array( __CLASS__, 'provider_name_translated' ) );

			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );

			\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'settings_loop' ), 10, 1 );

			\add_filter( WP_2FA_PREFIX . 'no_method_enabled', array( __CLASS__, 'return_default_selection' ), 10, 1 );

			// add the HOTP methods to the list of available methods if enabled.
			\add_filter(
				WP_2FA_PREFIX . 'available_2fa_methods',
				function ( $available_methods, $role ) {
					if ( ! empty( Settings_Utils::get_setting_role( $role, self::POLICY_SETTINGS_NAME ) ) ) {
						array_push( $available_methods, self::METHOD_NAME );
					}

					return $available_methods;
				},
				10,
				2
			);

			// \add_action( WP_2FA_PREFIX . 'before_settings_save_roles', array( __CLASS__, 'set_email_settings_when_enforced' ) );

			// \add_action( WP_2FA_PREFIX . 'before_settings_save', array( __CLASS__, 'set_email_settings_when_enforced' ) );

			\add_filter( WP_2FA_PREFIX . 'white_label_default_settings', array( __CLASS__, 'add_whitelabel_settings' ) );

			\add_action( WP_2FA_PREFIX . 'validate_login_api', array( __CLASS__, 'api_login_validate' ), 10, 3 );

			\add_action( WP_2FA_PREFIX . 'white_label_wizard_options', array( __CLASS__, 'white_label_option_labels' ) );

			Email_Wizard_Steps::init();
		}

		/**
		 * Checks the provided user and token and validates them. Returns true if valid, false otherwise.
		 *
		 * @param array       $valid - The current validation value.
		 * @param integer     $user_id - The user ID to check for.
		 * @param string|null $token - The token to validate against user provided.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function api_login_validate( array $valid, int $user_id, ?string $token ): array {

			if ( ! Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user_id ), self::METHOD_NAME ) ) {
				return $valid;
			}

			if ( self::METHOD_NAME !== User_Helper::get_enabled_method_for_user( $user_id ) ) {
				return $valid;
			}

			if ( ! is_array( $valid ) || ! isset( $valid['valid'] ) ) {
				$valid['valid'] = false;
			}

			// If the login is valid, return it as it is.
			if ( true === $valid['valid'] ) {
				return $valid;
			}

			if ( ! isset( $token ) || empty( $token ) ) {
				return $valid;
			}

			// Sanitize the token to ensure it is safe to use.
			$sanitized_token = \sanitize_text_field( $token );

			$is_valid = Authentication::validate_token( User_Helper::get_user( $user_id ), $sanitized_token );

			if ( ! $is_valid ) {
				$valid[ self::METHOD_NAME ]['error'] = \esc_html__( 'ERROR: Invalid verification code.', 'wp-2fa' );
				if ( API_Login::check_number_of_attempts( User_Helper::get_user( $user_id ) ) ) {

					if ( empty( WP2FA::get_wp2fa_general_setting( 'brute_force_disable' ) ) ) {
						Setup_Wizard::send_authentication_setup_email( $user_id, 'nominated_email_address' );
						if ( ! empty( WP2FA::get_wp2fa_general_setting( 'brute_force_disable' ) ) ) {
							User_Helper::set_meta( WP_2FA_PREFIX . 'code_sent', true );
						}

						$valid[ self::METHOD_NAME ]['error'] .= \esc_html__( ' For security reasons you have been sent a new code via email. Please use this new code to log in.', 'wp-2fa' );
					}
				}
			}

			$valid['valid'] = $is_valid;

			return $valid;
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
		public static function provider_name_translated( array $providers ) {
			$providers[ self::METHOD_NAME ] = \esc_html__( 'HOTP (Email)', 'wp-2fa' );

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
			$default_settings['specify-email_hotp'] = false;

			// $default_settings['enforce-email_hotp'] = false;

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
			// array_push( $loop_settings, 'enforce-email_hotp' );


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
		 * Returns the status of the mail method (enabled | disabled) for the current user role
		 *
		 * @param string $role - The name of the role to check for.
		 *
		 * @since 2.6.0
		 * @since 2.9.0 - Added role parameter to check if the Twilio is enabled for the given role.
		 *
		 * @return boolean
		 */
		public static function is_enabled( ?string $role = null ): bool {
			if ( null === self::$enabled || ! isset( self::$enabled[ $role ] ) ) {
				self::$enabled[ $role ] = empty( Settings_Utils::get_setting_role( $role, self::POLICY_SETTINGS_NAME ) ) ? false : true;
			}

			return self::$enabled[ $role ];
		}

		/**
		 * Fills up the White Label settings array with the method defaults.
		 *
		 * @param array $default_settings - The array with the collected white label settings.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function add_whitelabel_settings( array $default_settings ): array {

			$default_settings['method_help_hotp_intro']       = '<h3>' . __( 'Setting up HOTP (one-time code via email)', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the email address where the one-time code should be sent:', 'wp-2fa' ) . '</p>';
			$default_settings['method_help_hotp_help']        = __( 'To complete the 2FA configuration you will be sent a one-time code over email, therefore you should have access to the mailbox of this email address. If you do not receive the email with the one-time code please check your spam folder and contact your administrator', 'wp-2fa' );
			$default_settings['method_help_hotp_help_email']  = '<b>' . __( 'IMPORTANT', 'wp-2fa' ) . '</b><p>' . __( 'To ensure you always receive the one-time code whitelist the email address from which the codes are sent. This is {from_email}', 'wp-2fa' ) . '</p>';
			$default_settings['method_verification_hotp_pre'] = '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code sent to your email address to finalize the setup', 'wp-2fa' ) . '</p>';
			$default_settings['hotp_reconfigure_intro']       = '<h3>' . __( '{reconfigure_or_configure_capitalized} one-time code over email method', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to {reconfigure_or_configure} the email address where the one-time code should be sent.', 'wp-2fa' ) . '</p>';
			$default_settings['email-option-label']           = __( 'One-time code via email', 'wp-2fa' );
			$default_settings['email-option-label-hint']      = '';

			return $default_settings;
		}

		/**
		 * Shows the Method option label and hint in the White Label settings.
		 *
		 * @return void
		 *
		 * @since 2.9.0
		 */
		public static function white_label_option_labels() {
			?>
			<strong class="description"><?php esc_html_e( 'Email option label', 'wp-2fa' ); ?></strong>
			<br><br>
			<fieldset>
				<input type="text" id="email-option-label" name="wp_2fa_white_label[email-option-label]" class="large-text" value="<?php echo \esc_attr( WP2FA::get_wp2fa_white_label_setting( 'email-option-label', true ) ); ?>">
			</fieldset>
			<br>
			<strong class="description"><?php esc_html_e( 'Email option hint', 'wp-2fa' ); ?></strong>
			<br>
			<fieldset>
				<?php
					echo Settings_Page_White_Label::create_standard_editor( 'email-option-label-hint' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</fieldset>
			<br>
			<?php
		}
	}
}
