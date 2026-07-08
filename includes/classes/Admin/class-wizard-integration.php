<?php
/**
 * WP 2FA - New Wizard Integration
 *
 * Enqueues the new JS/CSS wizard assets on user profile pages and
 * provides the localization data (enabled methods, TOTP data, etc.)
 * via wp_localize_script.
 *
 * Premium / extension methods should NOT be referenced here directly.
 * Instead they hook into the following WP filters and actions:
 *
 *   Actions:
 *     wp_2fa_wizard_enqueue_assets  - Fired during asset enqueue so extensions
 *                                     can register their own JS/CSS files.
 *                                     Args: (string $plugin_url, string $version, string $core_handle)
 *
 *   Filters:
 *     wp_2fa_wizard_enabled_methods      - Register method as enabled. Args: (array, WP_User, string $role)
 *     wp_2fa_wizard_method_labels        - Add/override method labels. Args: (array $labels, WP_User, string $role)
 *     wp_2fa_wizard_method_descriptions  - Add/override method descriptions. Args: (array $descs, WP_User, string $role)
 *     wp_2fa_wizard_localized_data       - Final filter on the full localized data array.
 *                                          Args: (array $data, WP_User, string $role)
 *     wp_2fa_wizard_i18n_strings         - Add/override translatable strings. Args: (array $strings)
 *
 * @package    wp2fa
 * @subpackage wizard
 * @since      4.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

declare( strict_types=1 );

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Helpers\Email_Templates;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Admin\Helpers\Methods_Helper;
use WP2FA\Admin\Views\Re_Login_2FA;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP2FA\Admin\Wizard_Integration' ) ) {

	/**
	 * Handles the new JS-driven 2FA setup wizard for user profiles.
	 *
	 * @since 4.0.0
	 */
	class Wizard_Integration {

		/**
		 * Script handle for the wizard core.
		 *
		 * @var string
		 */
		const SCRIPT_HANDLE = 'wp2fa-setup-wizard';

		/**
		 * Registers hooks.
		 *
		 * @return void
		 */
		public static function init(): void {
			\add_action( 'show_user_profile', array( __CLASS__, 'render_profile_section' ), 25 );
			\add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_section' ), 25 );
			\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			\add_action( 'admin_footer', array( __CLASS__, 'print_wizard_templates' ) );
			\add_action( 'wp_ajax_wp2fa_dismiss_required_intro', array( __CLASS__, 'ajax_dismiss_required_intro' ) );
		}

		/**
		 * Decide whether to load assets on the current screen.
		 *
		 * @return bool
		 */
		private static function is_profile_page(): bool {
			$screen = \function_exists( 'get_current_screen' ) ? \get_current_screen() : null;
			if ( ! $screen ) {
				return false;
			}

			return in_array( $screen->id, array( 'profile', 'user-edit', 'profile-network', 'user-edit-network' ), true );
		}

		/**
		 * Enqueue CSS and JS on user profile pages.
		 *
		 * @return void
		 */
		public static function enqueue_assets(): void {
			if ( ! self::is_profile_page() ) {
				return;
			}

			// Profile section assets (cards, buttons, etc.).
			Profile_Section_Renderer::enqueue_assets();

			$plugin_url = WP_2FA_URL;
			$version    = WP_2FA_VERSION;

			\wp_enqueue_style(
				'wp2fa-setup-wizard',
				$plugin_url . 'css/wp2fa-wizard.css',
				array(),
				$version
			);

			// Core wizard JS (depends on wp-hooks and wp-util for wp.template).
			\wp_enqueue_script(
				self::SCRIPT_HANDLE,
				$plugin_url . 'js/wp2fa-wizard.js',
				array( 'wp-hooks', 'wp-util' ),
				$version,
				true
			);

			// Core method modules (always present).
			\wp_enqueue_script(
				'wp2fa-wizard-totp',
				$plugin_url . 'js/wp2fa-wizard-totp.js',
				array( self::SCRIPT_HANDLE, 'wp-hooks' ),
				$version,
				true
			);

			\wp_enqueue_script(
				'wp2fa-wizard-email',
				$plugin_url . 'js/wp2fa-wizard-email.js',
				array( self::SCRIPT_HANDLE, 'wp-hooks' ),
				$version,
				true
			);

			// Backup codes module.
			\wp_enqueue_script(
				'wp2fa-wizard-backup-codes',
				$plugin_url . 'js/wp2fa-wizard-backup-codes.js',
				array( self::SCRIPT_HANDLE, 'wp-hooks' ),
				$version,
				true
			);

			/**
			 * Action: wp_2fa_wizard_enqueue_assets
			 *
			 * Allows premium / extension methods to enqueue their own wizard
			 * JS and CSS files conditionally.
			 *
			 * @param string $plugin_url  The plugin root URL (with trailing slash).
			 * @param string $version     The plugin version string.
			 * @param string $core_handle The script handle of the wizard core JS.
			 *
			 * @since 4.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'wizard_enqueue_assets', $plugin_url, $version, self::SCRIPT_HANDLE );

			// Localized data – attached to the core handle.
			\wp_localize_script( self::SCRIPT_HANDLE, 'wp2faWizardData', self::get_localized_data() );
		}

		/**
		 * Build the localization payload for the wizard scripts.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_localized_data(): array {
			// $user = self::get_target_user();
			User_Helper::set_user( \wp_get_current_user() );
			$role = User_Helper::get_user_role();

			$user = User_Helper::get_user();

			// Determine which methods are enabled for this user's role.
			$enabled_methods = array();
			$method_labels   = array();
			$method_descs    = array();

			$available_methods = Methods::get_enabled_methods( User_Helper::get_user_role() );

			// Use methods for the user's role if available, otherwise fall back to global.
			$role_methods = array();
			if ( is_array( $available_methods ) && ! empty( $available_methods ) ) {
				if ( \array_key_exists( $role, $available_methods ) && ! empty( $available_methods[ $role ] ) ) {
					$role_methods = $available_methods[ $role ];
				} elseif ( \array_key_exists( 'global', $available_methods ) ) {
					$role_methods = $available_methods['global'];
				}
			}

			foreach ( $role_methods as $method_id => $method_data ) {
				$enabled_methods[ $method_id ] = true;
			}

			/**
			 * Filter: wp_2fa_wizard_enabled_methods
			 *
			 * Allows external classes to register themselves as enabled for the given user (should never be used).
			 * Each method should add its key (e.g. 'totp', 'email') with a truthy value.
			 *
			 * @param array    $enabled_methods Associative array of method_id => bool.
			 * @param \WP_User $user            The target user.
			 * @param string   $role            The user's role.
			 *
			 * @since 4.0.0
			 */
			$enabled_methods = \apply_filters( WP_2FA_PREFIX . 'wizard_enabled_methods', $enabled_methods, $user, $role );

			// White label / translatable labels.
			$method_labels['totp']  = WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true ) ?: __( 'One-time code via 2FA App (TOTP)', 'wp-2fa' );
			$method_labels['email'] = WP2FA::get_wp2fa_white_label_setting( 'email-option-label', true ) ?: __( 'One-time code via email (HOTP)', 'wp-2fa' );

			/**
			 * Filter: wp_2fa_wizard_method_labels
			 *
			 * Allows methods to add or override their display labels.
			 *
			 * @param array    $method_labels Associative array of method_id => label string.
			 * @param \WP_User $user          The target user.
			 * @param string   $role          The user's role.
			 *
			 * @since 4.0.0
			 */
			$method_labels = \apply_filters( WP_2FA_PREFIX . 'wizard_method_labels', $method_labels, $user, $role );

			$method_descs = array(
				'totp'  => __( 'Refer to the guide on how to set up 2FA apps for more information on how to setup these apps and which apps are supported.', 'wp-2fa' ),
				'email' => '',
			);

			/**
			 * Filter: wp_2fa_wizard_method_descriptions
			 *
			 * Allows methods to add or override their descriptions.
			 *
			 * @param array    $method_descs Associative array of method_id => description string.
			 * @param \WP_User $user         The target user.
			 * @param string   $role         The user's role.
			 *
			 * @since 4.0.0
			 */
			$method_descs = \apply_filters( WP_2FA_PREFIX . 'wizard_method_descriptions', $method_descs, $user, $role );
			$method_descs = \array_map( 'wp_kses_post', $method_descs );

			// -------------------------------------------------------
			// Method hints (info icon tooltip text).
			// Only populated when the "show_help_text" white-label
			// setting is enabled. Keys match method IDs.
			// -------------------------------------------------------
			$method_hints   = array();
			$show_help_text = ( 'show_help_text' === WP2FA::get_wp2fa_white_label_setting( 'show_help_text' ) );

			if ( $show_help_text ) {
				foreach ( \array_keys( $enabled_methods ) as $method_id ) {
					$hint = WP2FA::get_wp2fa_white_label_setting( $method_id . '-option-label-hint', true );
					if ( ! empty( $hint ) ) {
						$method_hints[ $method_id ] = \wp_kses_post( $hint );
					}
				}

				/**
				 * Filter: wp_2fa_wizard_method_hints
				 *
				 * Allows methods to add or override their hint texts shown via the info icon.
				 *
				 * @param array    $method_hints Associative array of method_id => hint HTML string.
				 * @param \WP_User $user         The target user.
				 * @param string   $role         The user's role.
				 *
				 * @since 4.0.0
				 */
				$method_hints = \apply_filters( WP_2FA_PREFIX . 'wizard_method_hints', $method_hints, $user, $role );
				$method_hints = \array_map( 'wp_kses_post', $method_hints );
			}

			// -------------------------------------------------------
			// TOTP-specific data.
			// -------------------------------------------------------
			$totp_data = array();
			if ( ! empty( $enabled_methods['totp'] ) && class_exists( '\WP2FA\Methods\TOTP' ) ) {
				User_Helper::set_user( \wp_get_current_user() );
				$totp_data = array(
					'qrCodeUrl' => TOTP::get_qr_code(),
					'totpKey'   => TOTP::get_totp_decrypted(),
				);
			}

			// -------------------------------------------------------
			// Email-specific data.
			// -------------------------------------------------------
			$email_data = array();
			if ( ! empty( $enabled_methods['email'] ) ) {
				$from_email  = \get_option( 'admin_email' );
				$custom_mail = Email_Templates::get_wp2fa_email_templates( 'custom_from_email_address' );

				if ( ! empty( $custom_mail ) ) {
					$from_email = $custom_mail;
				} else {
					$from_email = Settings_Page::get_default_email_address();
				}

				$nominated_email = User_Helper::get_nominated_email_for_user( $user );
				$email_data      = array(
					'userEmail'        => $user->user_email,
					'allowCustomEmail' => ! empty( Settings_Utils::get_setting_role( $role, 'specify-email_hotp' ) ),
					'fromEmail'        => $from_email,
					'nominatedEmail'   => $nominated_email !== $user->user_email ? $nominated_email : '',
				);
			}

			// -------------------------------------------------------
			// Backup methods.
			// -------------------------------------------------------
			$backup_methods       = array();
			$backup_method_labels = array();
			$backup_codes_data    = array();

			if ( class_exists( '\WP2FA\Methods\Backup_Codes' ) && Backup_Codes::are_backup_codes_enabled_for_role( $role ) ) {
				$backup_methods['backup_codes']       = true;
				$backup_method_labels['backup_codes'] = Backup_Codes::get_select_method_label();
				$backup_codes_data                    = array(
					'nonce'      => \wp_create_nonce( 'wp-2fa-backup-codes-generate-json-' . $user->ID ),
					'emailNonce' => \wp_create_nonce( 'wp-2fa-send-backup-codes-email-nonce' ),
				);
			}

			$role_backup_methods = Settings::get_enabled_backup_methods_for_user_role( $user );
			if ( ! empty( $role_backup_methods ) ) {
				foreach ( $role_backup_methods as $method_id => $method_data ) {
					if ( ! isset( $backup_methods[ $method_id ] ) ) {
						$backup_methods[ $method_id ] = true;
						if ( is_array( $method_data ) && ! empty( $method_data['button_name'] ) ) {
							$backup_method_labels[ $method_id ] = $method_data['button_name'];
						}
					}
				}
			}
			$backup_method_labels = \array_map( 'wp_kses_post', $backup_method_labels );

			// -------------------------------------------------------
			// Method ordering.
			// -------------------------------------------------------
			$method_orders         = array();
			$methods_order_setting = Settings_Utils::get_setting_role( $role, Methods_Helper::POLICY_SETTINGS_NAME, true );
			if ( \is_array( $methods_order_setting ) ) {
				$flipped = \array_flip( $methods_order_setting );
				foreach ( $flipped as $method_name => $order ) {
					$method_orders[ $method_name ] = (int) $order;
				}
			}

			// -------------------------------------------------------
			// Welcome screen (optional white-label setting).
			// -------------------------------------------------------
			$welcome_html   = '';
			$enable_welcome = WP2FA::get_wp2fa_white_label_setting( 'enable_welcome', false );
			if ( 'enable_welcome' === $enable_welcome ) {
				$welcome_content = WP2FA::get_wp2fa_white_label_setting( 'welcome', false );
				if ( ! empty( trim( (string) $welcome_content ) ) ) {
					$welcome_html = \wp_kses_post( $welcome_content );
				}
			}

			// -------------------------------------------------------
			// Required intro screen (shown before everything for enforced users).
			// -------------------------------------------------------
			$required_intro_html = '';
			$is_user_enforced    = User_Helper::is_enforced( $user->ID );
			if ( $is_user_enforced ) {
				$intro_content = WP2FA::get_wp2fa_white_label_setting( 'wp-2fa_required_intro', true );
				$intro_seen    = \get_user_meta( $user->ID, WP_2FA_PREFIX . 'required_intro_seen', true );
				if ( ! empty( trim( (string) $intro_content ) ) && empty( $intro_seen ) ) {
					$required_intro_html = \wp_kses_post( $intro_content );
				}
			}

			// -------------------------------------------------------
			// Redirect URL after successful 2FA setup.
			// Priority: redirect-user-custom-page > redirect-user-custom-page-global > none (reload).
			// -------------------------------------------------------
			$redirect_url    = '';
			$role_redirect   = \sanitize_text_field( (string) Settings_Utils::get_setting_role( $role, 'redirect-user-custom-page' ) );
			$global_redirect = \sanitize_text_field( (string) Settings_Utils::get_setting_role( null, 'redirect-user-custom-page' ) );

			if ( '' !== trim( $role_redirect ) ) {
				$redirect_url = \trailingslashit( \get_site_url() ) . $role_redirect;
			} elseif ( '' !== trim( $global_redirect ) ) {
				$redirect_url = \trailingslashit( \get_site_url() ) . $global_redirect;
			} else {
				// Fallback: check redirect-user-custom-page-global.
				$global_page_redirect = \sanitize_text_field( (string) Settings_Utils::get_setting_role( $role, 'redirect-user-custom-page-global' ) );
				if ( '' !== trim( $global_page_redirect ) ) {
					$redirect_url = \trailingslashit( \get_site_url() ) . $global_page_redirect;
				}
			}

			// -------------------------------------------------------
			// Reconfigure intro texts (shown when user already has 2FA).
			// -------------------------------------------------------
			$current_method                 = User_Helper::get_enabled_method_for_user( $user );
			$is_reconfiguration             = ! empty( $current_method );
			$reconfigure_intros             = array();
			$reconfigure_intros_unavailable = array();
			$unavailable_methods            = array();

			if ( $is_reconfiguration ) {
				$totp_reconfigure = WP2FA::get_wp2fa_white_label_setting( 'totp_reconfigure_intro', true );
				if ( ! empty( $totp_reconfigure ) ) {
					$reconfigure_intros['totp'] = \wp_kses_post( $totp_reconfigure );
				}

				$hotp_reconfigure = WP2FA::get_wp2fa_white_label_setting( 'hotp_reconfigure_intro', true );
				if ( ! empty( $hotp_reconfigure ) ) {
					$reconfigure_intros['email'] = \wp_kses_post( $hotp_reconfigure );
				}

				/**
				 * Filter: wp_2fa_wizard_reconfigure_intros
				 *
				 * Allows extensions to add their reconfigure intro texts.
				 *
				 * @param array    $reconfigure_intros Associative array of method_id => raw HTML text with placeholders.
				 * @param \WP_User $user               The target user.
				 * @param string   $role               The user's role.
				 *
				 * @since 4.0.0
				 */
				$reconfigure_intros = \apply_filters( WP_2FA_PREFIX . 'wizard_reconfigure_intros', $reconfigure_intros, $user, $role );

				/**
				 * Filter: wp_2fa_wizard_reconfigure_intros_unavailable
				 *
				 * Allows extensions to add their unavailable reconfigure intro texts.
				 *
				 * @param array    $reconfigure_intros_unavailable Associative array of method_id => raw HTML text.
				 * @param \WP_User $user                          The target user.
				 * @param string   $role                          The user's role.
				 *
				 * @since 4.0.0
				 */
				$reconfigure_intros_unavailable = \apply_filters( WP_2FA_PREFIX . 'wizard_reconfigure_intros_unavailable', $reconfigure_intros_unavailable, $user, $role );

				/**
				 * Filter: wp_2fa_wizard_unavailable_methods
				 *
				 * Allows extensions to mark methods as unavailable (service down, API key invalid, etc).
				 *
				 * @param array    $unavailable_methods Array of method IDs that are unavailable.
				 * @param \WP_User $user                The target user.
				 * @param string   $role                The user's role.
				 *
				 * @since 4.0.0
				 */
				$unavailable_methods = \apply_filters( WP_2FA_PREFIX . 'wizard_unavailable_methods', $unavailable_methods, $user, $role );
			}

			// -------------------------------------------------------
			// Assemble the data array.
			// -------------------------------------------------------
			$data = array(
				'ajaxUrl'                      => \admin_url( 'admin-ajax.php' ),
				'nonce'                        => \wp_create_nonce( 'wp-2fa-validate-authcode' ),
				'sendEmailNonce'               => \wp_create_nonce( 'wp-2fa-send-setup-email' ),
				'userId'                       => $user->ID,
				'userRole'                     => $role,
				'currentMethod'                => $current_method,
				'isReconfiguration'            => $is_reconfiguration,
				'reconfigureIntros'            => $reconfigure_intros,
				'reconfigureIntrosUnavailable' => $reconfigure_intros_unavailable,
				'unavailableMethods'           => $unavailable_methods,
				'enabledMethods'               => $enabled_methods,
				'methodLabels'                 => $method_labels,
				'methodDescriptions'           => $method_descs,
				'methodHints'                  => $method_hints,
				'totpData'                     => $totp_data,
				'emailData'                    => $email_data,
				'backupMethods'                => $backup_methods,
				'backupMethodLabels'           => $backup_method_labels,
				'backupCodesData'              => $backup_codes_data,
				'methodOrders'                 => $method_orders,
				'reloadOnSuccess'              => true,
				'redirectToUrl'                => $redirect_url,
				'reLogin'                      => Settings_Utils::get_setting_role( $role, Re_Login_2FA::RE_LOGIN_SETTINGS_NAME ),
				'reLoginEnabled'               => Re_Login_2FA::ENABLED_SETTING_VALUE,
				'loginUrl'                     => \wp_login_url(),
				'autoOpenWizard'               => self::should_auto_open_wizard( $user ),
				'welcomeHtml'                  => $welcome_html,
				'requiredIntroHtml'            => $required_intro_html,
				'dismissIntroNonce'            => \wp_create_nonce( 'wp-2fa-dismiss-required-intro' ),
				'stylingClass'                 => ( empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ) ? 'default_styling' : 'enable_styling',
				'i18n'                         => self::get_i18n_strings(),
			);

			/**
			 * Filter: wp_2fa_wizard_localized_data
			 *
			 * Final filter on the complete localized data array passed to JS.
			 * Extensions can add their own method-specific data keys here.
			 *
			 * @param array    $data The complete wp2faWizardData array.
			 * @param \WP_User $user The target user.
			 * @param string   $role The user's role.
			 *
			 * @since 4.0.0
			 */
			return \apply_filters( WP_2FA_PREFIX . 'wizard_localized_data', $data, $user, $role );
		}

		/**
		 * Translatable strings for the JS wizard.
		 *
		 * @return array<string,string>
		 */
		private static function get_i18n_strings(): array {
			$strings = array(
				'chooseMethod'             => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_selection', true ) ),
				'goBack'                   => \__( 'Go back', 'wp-2fa' ),
				'continueBtn'              => \__( 'Continue', 'wp-2fa' ),
				'close'                    => \__( 'Close', 'wp-2fa' ),
				'cancel'                   => \__( 'Cancel', 'wp-2fa' ),
				'imReady'                  => \__( "I'm Ready", 'wp-2fa' ),
				'validateSave'             => \__( 'Validate & Save', 'wp-2fa' ),
				'emailVerifyIntro'         => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_verification_hotp_pre', true ) ) ?: \__( 'Please type in the one-time code sent to your email address to finalize the setup', 'wp-2fa' ),
				'verificationCode'         => \__( 'Verification Code:', 'wp-2fa' ),
				'enterCode'                => \__( 'Please enter the verification code.', 'wp-2fa' ),
				'invalidCode'              => \__( 'Invalid or expired OTP code. Please try again.', 'wp-2fa' ),
				'invalidCodeFormat'        => \__( 'Invalid code. Please enter the correct OTP code generated by your Authenticator app.', 'wp-2fa' ),
				'networkError'             => \__( 'Network error. Please try again.', 'wp-2fa' ),
				'verifyConfig'             => \__( 'Verify configuration', 'wp-2fa' ),

				// TOTP.
				'settingUpTotp'            => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_intro', true ) ) ?: \__( 'Setting up TOTP (one-time code via app)', 'wp-2fa' ),
				'totpStep1'                => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_1', true ) ) ?: \__( 'Download and start the application of your choice', 'wp-2fa' ),
				'totpStep2'                => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_2', true ) ) ?: \__( 'From within the application scan the QR code provided on the left. Otherwise, enter the following code manually in the application:', 'wp-2fa' ),
				'totpStep3'                => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_3', true ) ) ?: \__( 'Click the "I\'m ready" button below when you complete the application setup process to proceed with the wizard.', 'wp-2fa' ),
				'totpVerifyIntro'          => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_verification_totp_pre', true ) ) ?: \__( 'Please type in the one-time code from your authenticator app to finalize setup.', 'wp-2fa' ),
				'totpSuccess'              => \__( 'TOTP configured successfully!', 'wp-2fa' ),
				'copyKey'                  => \__( 'Copy key', 'wp-2fa' ),

				// Email.
				'settingUpEmail'           => \__( 'Setting up HOTP (one-time code via email)', 'wp-2fa' ),
				'emailSetupIntro'          => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_hotp_intro', true ) ) ?: \__( 'To complete the 2FA configuration you will be sent a one-time code over email, therefore you should have access to the mailbox of this email address. If you do not receive the email with the one-time code please check your spam folder and contact your administrator', 'wp-2fa' ),
				'useMyEmail'               => \__( 'Use my user email', 'wp-2fa' ),
				'useAnotherEmail'          => \__( 'Use another email address', 'wp-2fa' ),
				'emailPlaceholder'         => \__( 'email@example.com', 'wp-2fa' ),
				'emailHelpText'            => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_hotp_help', true ) ),
				'emailWhitelistNotice'     => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_hotp_help_email', true ) ),
				'emailSuccess'             => \__( 'Email 2FA configured successfully!', 'wp-2fa' ),
				'emailSendError'           => \__( 'Failed to send setup email.', 'wp-2fa' ),
				'codeSent'                 => \__( 'A new code has been sent.', 'wp-2fa' ),
				'resendCode'               => \__( 'Send me another code', 'wp-2fa' ),

				// Backup codes.
				'backupCodesTitle'         => '', //\__( 'Backup Codes', 'wp-2fa' ),
				'backupCodesIntro'         => Backup_Codes::user_needs_to_setup_backup_codes( \wp_get_current_user() )
					? \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro', true ) )
					: \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro_continue', true ) ),
				'generateBackupCodes'      => \__( 'Generate list of backup codes', 'wp-2fa' ),
				'backupCodesError'         => \__( 'Failed to generate backup codes.', 'wp-2fa' ),
				'yourBackupCodes'          => '', // \__( 'Your backup codes', 'wp-2fa' ),
				'backupCodesGenerated'     => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_generated', true ) ) ?: \esc_html__( 'Store these codes somewhere safe. Each code can only be used once.', 'wp-2fa' ),
				'copyBackupCodes'          => \__( 'Copy', 'wp-2fa' ),
				'copied'                   => \__( 'Copied!', 'wp-2fa' ),
				'downloadBackupCodes'      => \__( 'Download', 'wp-2fa' ),
				'printBackupCodes'         => \__( 'Print', 'wp-2fa' ),
				'imReadyClose'             => \__( 'I\'m ready, close the wizard', 'wp-2fa' ),
				'skipBackupMethod'         => \__( 'I\'ll set this up later', 'wp-2fa' ),

				// Backup method selection step (shown when >1 backup method is available).
				'backupSelectTitle'        => '', // WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro_multi_title', true ) ?: \__( 'Your login just got more secure', 'wp-2fa' ),
				'backupSelectDescription'  => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro_multi', true ) ) ?: \__( 'Congratulations! You have enabled two-factor authentication for your user. You\'ve just helped towards making this website more secure!', 'wp-2fa' ),
				'backupSelectSectionTitle' => \__( 'Select backup method', 'wp-2fa' ),
				'closeLater'               => \__( 'Close wizard', 'wp-2fa' ),
				'configureBackup'          => \__( 'Continue', 'wp-2fa' ),

				// Completion step (no backup methods available).
				'noFurtherAction'          => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'no_further_action', true ) ),
				'closeWizard'              => \__( 'Close wizard', 'wp-2fa' ),

				// Close confirmation dialog.
				'wizardCancelBody'         => \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'wp-2fa_wizard_cancel', true ) ) ?: ( '<h3>' . \esc_html__( 'Are you sure?', 'wp-2fa' ) . '</h3><p>' . \esc_html__( 'Any unsaved changes will be lost!', 'wp-2fa' ) . '</p>' ),
				'confirmYes'               => \__( 'Yes', 'wp-2fa' ),
				'confirmNo'                => \__( 'No', 'wp-2fa' ),

				// Reconfiguration placeholder replacements.
				'reconfigureCapitalized'   => \__( 'Reconfigure', 'wp-2fa' ),
				'configureCapitalized'     => \__( 'Configure', 'wp-2fa' ),
				'reconfigureLower'         => \__( 'reconfigure', 'wp-2fa' ),
				'configureLower'           => \__( 'configure', 'wp-2fa' ),
			);

			/**
			 * Filter: wp_2fa_wizard_i18n_strings
			 *
			 * Allows extensions to add their own translatable strings.
			 *
			 * @param array $strings Associative array of key => translated string.
			 *
			 * @since 4.0.0
			 */
			return \apply_filters( WP_2FA_PREFIX . 'wizard_i18n_strings', $strings );
		}

		/**
		 * Determine if the wizard should auto-open for the given user.
		 *
		 * @param \WP_User $user The target user.
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		private static function should_auto_open_wizard( \WP_User $user ): bool {
			// Admin profile page with show=wp-2fa-setup parameter.
			if ( isset( $_GET['show'] ) && 'wp-2fa-setup' === $_GET['show'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}

			// User is enforced instantly (grace period expired, must configure now).
			if ( User_Helper::get_user_enforced_instantly( $user ) ) {
				return true;
			}

			return false;
		}

		/**
		 * AJAX handler: mark the required intro as seen for the current user.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function ajax_dismiss_required_intro(): void {
			\check_ajax_referer( 'wp-2fa-dismiss-required-intro', 'nonce' );

			$user = \wp_get_current_user();
			if ( ! $user || ! $user->ID ) {
				\wp_send_json_error( 'not_logged_in', 403 );
			}

			\update_user_meta( $user->ID, WP_2FA_PREFIX . 'required_intro_seen', 1 );
			\wp_send_json_success();
		}

		/**
		 * Get the user being viewed/edited.
		 *
		 * @return \WP_User
		 */
		private static function get_target_user(): \WP_User {
			if ( isset( $_GET['user_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$user_id = (int) $_GET['user_id']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$user    = \get_user_by( 'id', $user_id );
				if ( $user ) {
					return $user;
				}
			}

			return \wp_get_current_user();
		}

		/**
		 * Print wizard underscore.js templates in the admin footer.
		 *
		 * Templates are rendered as <script type="text/html" id="tmpl-…"> blocks
		 * and consumed by JS via wp.template().
		 *
		 * To override a template, use the {@see 'wp_2fa_wizard_templates'} filter
		 * and replace the file path for any template key. Extensions can add their
		 * own templates via the {@see 'wp_2fa_wizard_print_templates'} action.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function print_wizard_templates(): void {
			if ( ! self::is_profile_page() ) {
				return;
			}

			$template_dir = WP_2FA_PATH . 'templates' . DIRECTORY_SEPARATOR . 'wizard' . DIRECTORY_SEPARATOR;

			$templates = array(
				'modal'                   => $template_dir . 'modal.php',
				'confirm-close'           => $template_dir . 'confirm-close.php',
				'required-intro'          => $template_dir . 'required-intro.php',
				'welcome'                 => $template_dir . 'welcome.php',
				'method-selection'        => $template_dir . 'method-selection.php',
				'backup-method-footer'    => $template_dir . 'backup-method-footer.php',
				'backup-method-selection' => $template_dir . 'backup-method-selection.php',
				'totp-setup'              => $template_dir . 'totp-setup.php',
				'totp-verify'             => $template_dir . 'totp-verify.php',
				'email-setup'             => $template_dir . 'email-setup.php',
				'email-verify'            => $template_dir . 'email-verify.php',
				'backup-codes-generate'   => $template_dir . 'backup-codes-generate.php',
				'backup-codes-display'    => $template_dir . 'backup-codes-display.php',
				'completion'              => $template_dir . 'completion.php',
			);

			/**
			 * Filter: wp_2fa_wizard_templates
			 *
			 * Allows overriding wizard template file paths. Each key is a template
			 * name, each value is the absolute file path. Replace a path to use a
			 * custom template file instead of the bundled one.
			 *
			 * @param array<string,string> $templates Template name => absolute path.
			 *
			 * @since 4.0.0
			 */
			$templates = \apply_filters( WP_2FA_PREFIX . 'wizard_templates', $templates );

			foreach ( $templates as $name => $path ) {
				if ( \file_exists( $path ) ) {
					include $path;
				}
			}

			/**
			 * Action: wp_2fa_wizard_print_templates
			 *
			 * Allows extensions to print their own wizard underscore templates.
			 * Extensions should include their template PHP files inside this hook.
			 *
			 * @since 4.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'wizard_print_templates' );

			self::print_wizard_styling_script();
		}

		/**
		 * Render the full profile 2FA section using the new template-based renderer.
		 *
		 * Replaces the old render_wizard_button method. This renders
		 * the complete profile section with summary, cards, and actions.
		 *
		 * @param \WP_User $user The user whose profile is being displayed.
		 *
		 * @return void
		 */
		public static function render_profile_section( $user ): void {
			$role            = User_Helper::get_user_role( $user );
			$enabled_methods = Methods::get_enabled_methods( $role );

			// Only show the section if there are enabled methods for this role.
			if ( empty( $enabled_methods ) || ( isset( $enabled_methods[ $role ] ) && empty( $enabled_methods[ $role ] ) ) ) {
				return;
			}

			Profile_Section_Renderer::render( $user );
		}

		/**
		 * Render the profile section for shortcode / frontend context.
		 *
		 * Called from the shortcode handler or custom pages.
		 *
		 * @param \WP_User $user            The target user.
		 * @param array    $additional_args  Extra arguments (show_preamble, options, etc.).
		 *
		 * @return void
		 */
		public static function render_profile_section_frontend( \WP_User $user, array $additional_args = array() ): void {
			// Enqueue frontend-specific assets.
			Profile_Section_Renderer::enqueue_assets( true );

			// Enqueue the wizard assets for the frontend context.
			self::enqueue_frontend_wizard_assets();

			Profile_Section_Renderer::render( $user, $additional_args );
		}

		/**
		 * Enqueue wizard JS/CSS and print templates for the frontend context.
		 *
		 * This mirrors what enqueue_assets() does for the admin profile page,
		 * adapted for frontend shortcode / WooCommerce pages.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function enqueue_frontend_wizard_assets(): void {
			static $enqueued = false;
			if ( $enqueued ) {
				return;
			}
			$enqueued = true;

			$plugin_url = WP_2FA_URL;
			$version    = WP_2FA_VERSION;

			\wp_enqueue_style(
				'wp2fa-setup-wizard',
				$plugin_url . 'css/wp2fa-wizard.css',
				array(),
				$version
			);

			// Core wizard JS (depends on wp-hooks and wp-util for wp.template).
			\wp_enqueue_script(
				self::SCRIPT_HANDLE,
				$plugin_url . 'js/wp2fa-wizard.js',
				array( 'wp-hooks', 'wp-util' ),
				$version,
				true
			);

			// Core method modules.
			\wp_enqueue_script(
				'wp2fa-wizard-totp',
				$plugin_url . 'js/wp2fa-wizard-totp.js',
				array( self::SCRIPT_HANDLE, 'wp-hooks' ),
				$version,
				true
			);

			\wp_enqueue_script(
				'wp2fa-wizard-email',
				$plugin_url . 'js/wp2fa-wizard-email.js',
				array( self::SCRIPT_HANDLE, 'wp-hooks' ),
				$version,
				true
			);

			// Backup codes module.
			\wp_enqueue_script(
				'wp2fa-wizard-backup-codes',
				$plugin_url . 'js/wp2fa-wizard-backup-codes.js',
				array( self::SCRIPT_HANDLE, 'wp-hooks' ),
				$version,
				true
			);

			/**
			 * Action: wp_2fa_wizard_enqueue_assets
			 *
			 * Allows premium / extension methods to enqueue their own wizard
			 * JS and CSS files conditionally.
			 *
			 * @param string $plugin_url  The plugin root URL (with trailing slash).
			 * @param string $version     The plugin version string.
			 * @param string $core_handle The script handle of the wizard core JS.
			 *
			 * @since 4.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'wizard_enqueue_assets', $plugin_url, $version, self::SCRIPT_HANDLE );

			// Localized data – attached to the core handle.
			\wp_localize_script( self::SCRIPT_HANDLE, 'wp2faWizardData', self::get_localized_data() );

			// Print wizard templates in the footer.
			\add_action( 'wp_footer', array( __CLASS__, 'print_wizard_templates_frontend' ) );
		}

		/**
		 * Print wizard underscore.js templates in the frontend footer.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function print_wizard_templates_frontend(): void {
			$template_dir = WP_2FA_PATH . 'templates' . DIRECTORY_SEPARATOR . 'wizard' . DIRECTORY_SEPARATOR;

			$templates = array(
				'modal'                   => $template_dir . 'modal.php',
				'confirm-close'           => $template_dir . 'confirm-close.php',
				'required-intro'          => $template_dir . 'required-intro.php',
				'welcome'                 => $template_dir . 'welcome.php',
				'method-selection'        => $template_dir . 'method-selection.php',
				'backup-method-footer'    => $template_dir . 'backup-method-footer.php',
				'backup-method-selection' => $template_dir . 'backup-method-selection.php',
				'totp-setup'              => $template_dir . 'totp-setup.php',
				'totp-verify'             => $template_dir . 'totp-verify.php',
				'email-setup'             => $template_dir . 'email-setup.php',
				'email-verify'            => $template_dir . 'email-verify.php',
				'backup-codes-generate'   => $template_dir . 'backup-codes-generate.php',
				'backup-codes-display'    => $template_dir . 'backup-codes-display.php',
				'completion'              => $template_dir . 'completion.php',
			);

			/** This filter is documented in self::print_wizard_templates() */
			$templates = \apply_filters( WP_2FA_PREFIX . 'wizard_templates', $templates );

			foreach ( $templates as $name => $path ) {
				if ( \file_exists( $path ) ) {
					include $path;
				}
			}

			/** This action is documented in self::print_wizard_templates() */
			\do_action( WP_2FA_PREFIX . 'wizard_print_templates' );

			self::print_wizard_styling_script();
		}

		/**
		 * Output inline script and custom CSS for backward-compatible wizard styling.
		 *
		 * Adds the enable_styling/default_styling class to the wizard modal via the
		 * wp2fa_wizard_modal_classes JS filter, and outputs any user-defined custom CSS.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function print_wizard_styling_script(): void {
			// When enable_wizard_styling is checked, use the plugin's built-in styling (no custom CSS).
			// When unchecked (empty), apply the custom CSS from white-label settings.
			// if ( ! empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ) {
			// return;
			// }

			// Always output custom CSS if present — it applies regardless of the
			// enable_wizard_styling toggle (matching legacy v3 behaviour where
			// custom_wizard_css fired unconditionally via the methods_wizards action).
			$custom_css = WP2FA::get_wp2fa_white_label_setting( 'custom_css', true );
			if ( ! empty( $custom_css ) ) {
				$modal_css = <<<'CSS'
.wp2fa-modal input[type=radio]:checked:before {
	display:none;
}
.wp2fa-modal.enable_styling h4,.wp2fa-modal.enable_styling h3 {
	font-family:helvetica !important;
}
.wp2fa-modal fieldset {
	padding:0;
	border:0;
}
.wp2fa-modal ol {
	margin:0 0 15px;
	padding-inline-start:17px;
}
.wp2fa-modal ol li {
	position:relative;
	padding:6px;
}
.wp2fa-modal .modal__content code.app-key {
	font-size:16px;
	padding:7px 12px;
	word-break:break-all;
	background:#efefef;
	color:#222;
}
.wp2fa-modal.enable_styling .modal__content .wp2fa-setup-actions .button:focus,
.wp2fa-modal.enable_styling .modal__content .modal__btn:focus,
.wp2fa-modal.enable_styling .modal__content .modal__btn.button-confirm:focus,
.wp2fa-modal.enable_styling .modal__content .modal__btn.button-decline:focus {
	box-shadow:none;
}
.wp2fa-modal.enable_styling .button+.button {
	margin-inline-start:10px;
}
@media screen and (max-width:1299px) {
	.wp2fa-modal .modal__container {
		max-width:96vw;
	}
	.wp2fa-modal .modal__content code br {
		display:block !important;
	}
	.wp2fa-modal .modal__content code.app-key {
		width:100%;
		display:block;
		text-align:center;
		font-size:16px;
		margin-bottom:30px;
		background:#efefef;
		padding:5px;
		word-break:break-all;
	}
	.wp2fa-modal .modal__content .apps-wrapper {
		margin:10px 0;
	}
	.wp2fa-modal .modal__content a.app-logo {
		padding:0 5px;
	}
	.wp2fa-modal .modal__content .wizard-tooltip {
		background:#3e6bff;
		color:white;
		height:18px;
		display:inline-block;
		width:18px;
		border-radius:50%;
		position:absolute;
		overflow:hidden;
		text-align:center;
		line-height:18px;
		font-weight:700;
		margin-inline-start:4px;
		margin-top:3px;
		bottom:10px;
		inset-inline-end:17px;
	}
	.wp2fa-modal .modal__content .inline-helper {
		background:#eee;
		padding:10px;
		border-radius:10px;
		display:block;
		margin-top:10px;
		font-size:12px;
		line-height:24px;
		display:none;
	}
	.wp2fa-modal .modal__content .click-to-copy {
		border:2px solid #3e6bff;
		border-radius:14px;
		display:inline-block;
		font-size:10px;
		padding:0 9px;
		font-weight:700;
		line-height:16px;
	}
	.wp2fa-modal .modal__content .click-to-copy.done {
		background-color:green !important;
		border:2px solid green;
		color:white;
	}
	.wp2fa-modal .modal__content .click-to-copy:hover {
		background-color:#3e6bff;
		color:white;
		cursor:pointer;
		opacity:0.5;
	}
	.wp2fa-modal .modal__content .app-key-wrapper {
		background:#eee;
		border-radius:4px;
		margin:10px 0 5px;
		overflow:hidden;
		padding:3px 7px;
	}
	.wp2fa-modal .modal__content .app-key-wrapper input {
		background:transparent;
		border:none;
		display:inline-block;
		font-size:9pt;
		margin:0 10px 0 0;
		max-width:15pc;
		min-height:0;
		min-width:200px;
		padding:0;
		color:#888;
	}
	.wp2fa-modal .modal__content .app-key-wrapper input:focus-visible,
	.wp2fa-modal .modal__content .app-key-wrapper input:focus {
		border:none;
		background-color:transparent;
		outline:none;
	}
	.wp2fa-modal .modal__content .wp2fa-setup-actions .button,
	.wp2fa-modal .modal__content .modal__btn {
		width:auto;
		display:inline-block;
		margin-bottom:10px;
		margin-inline-start:0px;
		margin-inline-end:0px;
		text-align:center;
	}
	.wp2fa-modal h4,.wp2fa-modal h3 {
		font-size:20px;
	}
	.wp2fa-modal h4 {
		clear:both;
	}
}
@media screen and (min-width:1300px) {
	.wp2fa-modal .clear-both {
		clear:both;
		display:block;
		overflow:hidden;
	}
	.wp2fa-modal .modal-50 {
		width:50%;
		display:inline-block;
		float:left;
	}
	.wp2fa-modal .modal-60 {
		width:70%;
		display:inline-block;
		float:left;
	}
	.wp2fa-modal .modal-60 .radio-cells {
		width:100%;
		padding-inline-start:10px;
	}
	.wp2fa-modal .modal-40 {
		width:30%;
		float:left;
		display:flex;
		justify-content:center;
	}
	.wp2fa-modal .mb-20 {
		margin-bottom:20px;
	}
	.wp2fa-modal.enable_styling p+.radio-cells,
	.wp2fa-modal.enable_styling p+fieldset {
		margin:20px 0;
		display:flex;
		flex-wrap:wrap;
	}
	.wp2fa-modal.enable_styling .radio-cells {
		flex-wrap:wrap;
		margin:0px 0 20px;
		width:calc(100% + 10px);
		position:relative;
		inset-inline-start:-5px;
	}
	.wp2fa-modal.enable_styling .radio-cells input:not([type="radio"]) {
		margin-top:5px;
	}
	.wp2fa-modal.enable_styling .radio-cells .option-pill {
		flex-basis:0;
		flex-grow:1;
		border:3px solid #eee;
		border-radius:10px;
		padding:10px;
		margin:5px;
		font-size:11px;
		position:relative;
	}
	.wp2fa-modal.enable_styling .radio-cells .option-pill p {
		height:100%;
	}
	.wp2fa-modal.enable_styling .radio-cells.max-3 .option-pill {
		flex:33.333%;
		display:flex;
		flex-direction:column;
	}
	.wp2fa-modal.enable_styling .radio-cells.max-3 .option-pill p {
		flex:1;
	}
	.wp2fa-modal.enable_styling .radio-cells .option-pill.isSelected {
		border:3px solid #007cba;
	}
	.wp2fa-modal.enable_styling .radio-cells .option-pill label {
		display:block;
		font-size:13px;
		line-height:24px;
		font-weight:500;
		margin-bottom:2px;
		height:100%;
		max-width:calc(100% - 20px);
	}
	.wp2fa-modal.enable_styling .radio-cells .option-pill p {
		font-size:12px;
		line-height:23px;
		opacity:0.9;
		height:auto;
	}
	.wp2fa-modal .wizard-tooltip {
		background:#3e6bff;
		color:white;
		height:18px;
		display:inline-block;
		width:18px;
		border-radius:50%;
		position:absolute;
		overflow:hidden;
		text-align:center;
		line-height:18px;
		font-weight:700;
		margin-inline-start:4px;
		margin-top:3px;
		bottom:10px;
		inset-inline-end:17px;
	}
	.wp2fa-modal .inline-helper {
		background:#eee;
		padding:10px;
		border-radius:10px;
		display:block;
		margin-top:10px;
		font-size:12px;
		line-height:24px;
		display:none;
	}
	.wp2fa-modal.enable_styling .tooltip-content-wrapper {
		display:none;
	}
	.wp2fa-modal .click-to-copy {
		border:2px solid #3e6bff;
		border-radius:14px;
		display:inline-block;
		font-size:10px;
		padding:0 9px;
		font-weight:700;
		line-height:16px;
	}
	.wp2fa-modal .click-to-copy.done {
		background-color:green !important;
		border:2px solid green;
		color:white;
	}
	.wp2fa-modal .click-to-copy:hover {
		background-color:#3e6bff;
		color:white;
		cursor:pointer;
		opacity:0.5;
	}
	.wp2fa-modal .app-key-wrapper {
		background:#eee;
		border-radius:4px;
		margin:10px 0 5px;
		overflow:hidden;
		padding:3px 7px;
	}
	.wp2fa-modal .app-key-wrapper input {
		background:transparent;
		border:none;
		display:inline-block;
		font-size:9pt;
		margin:0 10px 0 0;
		max-width:15pc;
		min-height:0;
		min-width:200px;
		padding:0;
		color:#888;
	}
	.wp2fa-modal .app-key-wrapper input:focus-visible,
	.wp2fa-modal .app-key-wrapper input:focus {
		border:none;
		background-color:transparent;
		outline:none;
	}
	.wp2fa-modal .option-pill {
		margin-bottom:15px;
	}
	.wp2fa-modal .qr-code {
		float:left;
		width:100%;
		position:relative;
		inset-inline-start:-2%;
	}
	.wp2fa-modal .mb-30 {
		margin-bottom:30px;
	}
}
@media (max-width:500px) {
	.wp2fa-modal .modal__content .apps-wrapper {
		display:block;
	}
	.wp2fa-modal .modal__content a.app-logo {
		width:calc(33.33333% - 13px);
		display:inline-block;
		margin-bottom:5px;
	}
	.wp2fa-modal h4,.wp2fa-modal h3 {
		font-size:18px;
		margin-bottom:9px;
	}
	.wp2fa-modal .modal__content .modal__btn,
	.wp2fa-modal .modal__content .wp2fa-setup-actions .button {
		display:block;
		margin-bottom:10px;
		margin-inline-start:0;
		margin-inline-end:0;
		text-align:center;
		width:auto;
		padding:10px 15px;
		font-size:12px;
	}
	.wp2fa-modal .modal__content .radio-cells .option-pill {
		flex-basis:unset;
		flex-grow:1;
	}
	.wp2fa-modal .modal__content p+.radio-cells {
		margin-top:20px;
	}
}
@media screen and (min-width:801px) and (max-width:1299px) {
	.wp2fa-modal .modal__content p+.radio-cells {
		margin-top:20px;
	}
}
@media screen and (min-width:1024px) {
	.wp2fa-modal .modal__container {
		min-width:768px;
	}
}
@media screen and (max-width:800px) {
	.wp2fa-modal .modal__container {
		max-width:95vw;
		min-width:80vw;
	}
	.wp2fa-modal .modal__content .wp2fa-setup-actions .button,
	.wp2fa-modal .modal__content .modal__btn {
		width:auto;
		display:inline-block;
		margin-bottom:10px;
		margin-inline-start:0px;
		margin-inline-end:0px;
		text-align:center;
	}
	.wp2fa-modal .modal__close {
		inset-inline-end:15px;
		top:15px;
	}
}
@media screen and (max-width:500px) {
	.wp2fa-modal .modal__content .app-key-wrapper input {
		max-width:200px;
	}
	.wp2fa-modal .modal__content .wizard-tooltip {
		inset-inline-end:5px;
		bottom:5px;
	}
}
CSS;
				echo '<style id="wp2fa-custom-wizard-css">' . $modal_css . \wp_strip_all_tags( $custom_css ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<script>
			( function() {
				function wp2faRegisterStylingFilter() {
					if ( typeof wp !== 'undefined' && wp.hooks && typeof wp2faWizardData !== 'undefined' ) {
						wp.hooks.addFilter( 'wp2fa_wizard_modal_classes', 'wp2fa/white-label-styling', function( classes ) {
							var stylingClass = wp2faWizardData.stylingClass || 'default_styling';
							return ( classes ? classes + ' ' : '' ) + stylingClass + ' wp2fa-modal';
						} );

						// When styling is disabled, swap our custom button/element classes
						// for WP-native ones so the theme's styles apply directly.
						if ( ( wp2faWizardData.stylingClass || 'default_styling' ) === 'default_styling' ) {

							// Selectors covering wizard buttons and profile section buttons.
							var WIZARD_BTN_SEL = '.wp2fa-wizard-btn, .wp2fa-wizard-btn-primary, .wp2fa-wizard-btn-secondary, .wp-2fa-button-primary, .wp-2fa-button-secondary';
							var PROFILE_BTN_SEL = '.wp2fa-profile__btn';

							function tagWizardButtons( root ) {
								root.querySelectorAll( WIZARD_BTN_SEL ).forEach( function( el ) {
									var isPrimary = el.classList.contains( 'wp2fa-wizard-btn-primary' ) || el.classList.contains( 'wp-2fa-button-primary' );
									el.classList.remove(
										'wp2fa-wizard-btn',
										'wp2fa-wizard-btn-primary',
										'wp2fa-wizard-btn-secondary',
										'wp2fa-wizard-btn-close-later',
										'wp-2fa-button-primary',
										'wp-2fa-button-secondary'
									);
									el.classList.add( 'button' );
									if ( isPrimary ) {
										el.classList.add( 'button-primary' );
									}
								} );
							}

							function tagProfileButtons( root ) {
								root.querySelectorAll( PROFILE_BTN_SEL ).forEach( function( el ) {
									var isPrimary = el.classList.contains( 'wp2fa-profile__btn--primary' );
									var isDanger = el.classList.contains( 'wp2fa-profile__btn--danger' );
									el.classList.remove(
										'wp2fa-profile__btn',
										'wp2fa-profile__btn--primary',
										'wp2fa-profile__btn--secondary',
										'wp2fa-profile__btn--danger',
										'wp2fa-profile__btn--disabled'
									);
									el.classList.add( 'button' );
									if ( isPrimary || isDanger ) {
										el.classList.add( 'button-primary' );
									}
								} );
							}

							// Tag profile buttons on the page immediately.
							function initProfileButtons() {
								tagProfileButtons( document );
							}

							if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
								initProfileButtons();
							} else {
								document.addEventListener( 'DOMContentLoaded', initProfileButtons );
							}

							// Tag wizard buttons each time a step is shown.
							wp.hooks.addAction( 'wp2fa_wizard_step_shown', 'wp2fa/default-button-classes', function() {
								var modal = document.getElementById( 'wp2fa-setup-wizard-modal' );
								if ( modal ) {
									tagWizardButtons( modal );
								}
							} );

							// Observe for modal insertion and dynamic content inside it.
							var modalObserver = null;
							var bodyObserver = new MutationObserver( function( mutations ) {
								for ( var i = 0; i < mutations.length; i++ ) {
									for ( var j = 0; j < mutations[i].addedNodes.length; j++ ) {
										var node = mutations[i].addedNodes[j];
										if ( node.nodeType === 1 && ( node.id === 'wp2fa-setup-wizard-overlay' || ( node.querySelector && node.querySelector( '#wp2fa-setup-wizard-modal' ) ) ) ) {
											var modal = document.getElementById( 'wp2fa-setup-wizard-modal' ) || node.querySelector( '#wp2fa-setup-wizard-modal' ) || node;
											tagWizardButtons( modal );
											bodyObserver.disconnect();

											// Watch inside the modal for dynamically added buttons.
											modalObserver = new MutationObserver( function() {
												tagWizardButtons( modal );
											} );
											modalObserver.observe( modal, { childList: true, subtree: true } );
											return;
										}
									}
								}
							} );
							bodyObserver.observe( document.body || document.documentElement, { childList: true, subtree: true } );
						}
					}
				}
				if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
					wp2faRegisterStylingFilter();
				} else {
					document.addEventListener( 'DOMContentLoaded', wp2faRegisterStylingFilter );
				}
			} )();
			</script>
			<?php
		}
	}
}

