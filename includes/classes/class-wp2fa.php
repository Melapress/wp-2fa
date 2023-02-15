<?php
/**
 * Main plugin class.
 *
 * @package    wp2fa
 * @copyright  2023 WP White Security
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA;

use WP2FA\Admin\User;
use WP2FA\Admin\User_Listing;
use WP2FA\Admin\User_Notices;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Request_Utils;
use WP2FA\Shortcodes\Shortcodes;
use WP2FA\Utils\Date_Time_Utils;
use WP2FA\Authenticator\Open_SSL;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Freemius\User_Licensing;
use WP2FA\Freemius\Freemius_Helper;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\File_Writer;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Helpers\Classes_Helper;
use WP2FA\Authenticator\Backup_Codes;
use WP2FA\Utils\Settings_Utils as Settings_Utils;
use WP2FA\Admin\SettingsPages\Settings_Page_Email;

/**
 * Main WP2FA Class.
 */
class WP2FA {

	/**
	 * Holds the global plugin secret key for storing the TOTP
	 *
	 * @var string
	 *
	 * @since 2.0.0
	 */
	private static $secret_key = null;

	/**
	 * Local static cache for plugins settings.
	 *
	 * @var array
	 *
	 * @since 2.0.0
	 */
	private static $plugin_settings = array();

	/**
	 * Local static cache for email template settings.
	 *
	 * @var array
	 */
	protected static $wp_2fa_email_templates;

	/**
	 * Array with all the plugin default settings.
	 *
	 * @return array
	 *
	 * @since 2.2.0
	 */
	public static function get_default_settings() {
		$default_settings = array(
			'enable_totp'                          => 'enable_totp',
			'enable_email'                         => 'enable_email',
			'backup_codes_enabled'                 => 'yes',
			'enforcement-policy'                   => 'do-not-enforce',
			'excluded_users'                       => array(),
			'excluded_roles'                       => array(),
			'enforced_users'                       => array(),
			'enforced_roles'                       => array(),
			'grace-period'                         => 3,
			'grace-period-denominator'             => 'days',
			'enable_destroy_session'               => '',
			'limit_access'                         => '',
			'2fa_settings_last_updated_by'         => '',
			'2fa_main_user'                        => '',
			'grace-period-expiry-time'             => '',
			'plugin_version'                       => WP_2FA_VERSION,
			'delete_data_upon_uninstall'           => '',
			'excluded_sites'                       => '',
			'included_sites'                       => array(),
			'create-custom-user-page'              => 'no',
			'redirect-user-custom-page'            => '',
			'redirect-user-custom-page-global'     => '',
			'custom-user-page-url'                 => '',
			'custom-user-page-id'                  => '',
			'hide_remove_button'                   => '',
			'grace-policy'                         => 'use-grace-period',
			'superadmins-role-add'                 => 'no',
			'superadmins-role-exclude'             => 'no',
			'default-text-code-page'               => __( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email. Note: if you are supposed to receive an email but did not receive any, please click the Resend Code button to request another code.', 'wp-2fa' ),
			'specify-email_hotp'                   => '',
			'default-backup-code-page'             => __( 'Enter a backup verification code.', 'wp-2fa' ),
			'method_invalid_setting'               => 'login_block',
			'enable_wizard_styling'                => 'enable_wizard_styling',
			'show_help_text'                       => 'show_help_text',
			'enable_wizard_logo'                   => '',
			'enable_welcome'                       => '',
			'welcome'                              => '',
			'method_selection'                     => '<h3>' . __( 'Choose the 2FA method', 'wp-2fa' ) . '</h3>' . Methods::get_number_of_methods_text(),
			'method_selection_single'              => '<h3>' . __( 'Choose the 2FA method', 'wp-2fa' ) . '</h3><p>' . __( 'Only the below 2FA method is allowed on this website:', 'wp-2fa' ) . '</p>',
			'method_help_totp_intro'               => '<h3>' . __( 'Setting up TOTP', 'wp-2fa' ) . '</h3>',
			'method_help_totp_step_1'              => __( 'Download and start the application of your choice', 'wp-2fa' ),
			'method_help_totp_step_2'              => __( 'From within the application scan the QR code provided on the left. Otherwise, enter the following code manually in the application:', 'wp-2fa' ),
			'method_help_totp_step_3'              => __( 'Click the "I\'m ready" button below when you complete the application setup process to proceed with the wizard.', 'wp-2fa' ),
			'method_help_hotp_intro'               => '<h3>' . __( 'Setting up HOTP', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the email address where the one-time code should be sent:', 'wp-2fa' ) . '</p>',
			'method_help_authy_intro'              => '<h3>' . __( 'Setting up Authy', 'wp-2fa' ) . '</h3><p>' . __( 'To enable Authy enter the country and cellphone number in order to use it with this account.', 'wp-2fa' ) . '</p>',
			'method_help_twilio_intro'             => '<h3>' . __( 'Setting up Twilio', 'wp-2fa' ) . '</h3><p>' . __( 'To enable Twiliio enter the country and cellphone number in order to use it with this account.', 'wp-2fa' ) . '</p>',
			'method_help_oob_intro'                => '<h3>' . __( 'Setting up Link over email 2FA', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the email address to where the out-of-band link should be sent:', 'wp-2fa' ) . '</p>',
			'method_verification_totp_pre'         => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code from your chosen authentication app to finalize the setup.', 'wp-2fa' ) . '</p>',
			'method_verification_hotp_pre'         => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code sent to your email address to finalize the setup', 'wp-2fa' ) . '</p>',
			'method_verification_oob_pre'          => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the one-time code sent to your email address to finalize the setup. Once the code is confirmed and 2FA is set up, you only have to verify a login by clicking on a link sent to you via email.', 'wp-2fa' ) . '</p>',
			'method_verification_authy_pre'        => '<h3>' . __( 'Almost there…', 'wp-2fa' ) . '</h3><p>' . __( 'Please type in the code from your Authy application with name {authy_name}', 'wp-2fa' ) . '</p>',
			'backup_codes_intro_multi'             => '<h3>' . __( 'Your login just got more secure', 'wp-2fa' ) . '</h3><p>' . __( 'It is recommended to have a backup 2FA method in case you cannot generate a code from your 2FA app and you need to log in. You can configure any of the below. You can always configure any or both from your user profile page later.', 'wp-2fa' ) . '</p>',
			'backup_codes_intro'                   => '<h3>' . __( 'Your login just got more secure', 'wp-2fa' ) . '</h3><p>' . __( 'Congratulations! You have enabled two-factor authentication for your user. You’ve just helped towards making this website more secure!', 'wp-2fa' ) . '</p>',
			'backup_codes_intro_continue'          => '<h3>' . __( 'Your login just got more secure', 'wp-2fa' ) . '</h3><p>' . __( 'Congratulations! You have enabled two-factor authentication for your user. You’ve just helped towards making this website more secure!', 'wp-2fa' ) . '</p><p>' . __( 'You should now generate the list of backup method. Although this is optional, it is highly recommended to have a secondary 2FA method. This can be used as a backup should the primary 2FA method fail. This can happen if, for example, you forget your smartphone, the smartphone runs out of battery, or there are email deliverability problems.', 'wp-2fa' ) . '</p>',
			'backup_codes_generate_intro'          => '<h3>' . __( 'Generate list of backup codes', 'wp-2fa' ) . '</h3><p>' . __( 'It is recommended to generate and print some backup codes in case you lose access to your primary 2FA method.', 'wp-2fa' ) . '</p>',
			'backup_codes_generated'               => '<h3>' . __( 'Backup codes generated', 'wp-2fa' ) . '</h3><p>' . __( 'Here are your backup codes:', 'wp-2fa' ) . '</p>',
			'no_further_action'                    => '<h3>' . __( 'Congratulations! You are all set.', 'wp-2fa' ),
			'2fa_required_intro'                   => '<h3>' . __( 'You are required to configure 2FA.', 'wp-2fa' ) . '</h3><p>' . __( 'In order to keep this site - and your details secure, this website’s administrator requires you to enable 2FA authentication to continue.', 'wp-2fa' ) . '</p><p>' . __( 'Two factor authentication ensures only you have access to your account by creating an added layer of security when logging in -', 'wp-2fa' ) . ' <a href="https://www.wpwhitesecurity.com/two-factor-authentication-wordpress/" target="_blank" rel="noopener">' . __( 'Learn more', 'wp-2fa' ) . '</a></p>',
			'totp_reconfigure_intro'               => '<h3>' . __( 'Reconfigure the 2FA App', 'wp-2fa' ) . '</h3><p>' . __( 'Click the below button to reconfigure the current 2FA method. Note that once reset you will have to re-scan the QR code on all devices you want this to work on because the previous codes will stop working.', 'wp-2fa' ) . '</p>',
			'hotp_reconfigure_intro'               => '<h3>' . __( 'Reconfigure one-time code over email method', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the email address where the one-time code should be sent:', 'wp-2fa' ) . '</p>',
			'authy_reconfigure_intro'              => '<h3>' . __( 'Reconfigure Authy method', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the phone where link should be send:', 'wp-2fa' ) . '</p>',
			'authy_reconfigure_intro_unavailable'  => '<h3>' . __( 'Reconfigure Authy method', 'wp-2fa' ) . '</h3><p>' . __( 'The 2FA service you want to use is currently unavailable. Please try again later or restart the wizard to choose another method.', 'wp-2fa' ) . '</p>',
			'twilio_reconfigure_intro'             => '<h3>' . __( 'Reconfigure Twilio method', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the phone where code should be send:', 'wp-2fa' ) . '</p>',
			'twilio_reconfigure_intro_unavailable' => '<h3>' . __( 'Reconfigure Twilio method', 'wp-2fa' ) . '</h3><p>' . __( 'The 2FA service you want to use is currently unavailable. Please try again later or restart the wizard to choose another method.', 'wp-2fa' ) . '</p>',
			'oob_reconfigure_intro'                => '<h3>' . __( 'Reconfigure link over email method', 'wp-2fa' ) . '</h3><p>' . __( 'Please select the email address where the OOB code should be sent:', 'wp-2fa' ) . '</p>',
			'custom_css'                           => '',
			'logo-code-page'                       => '',
		);
		/**
		 * Gives the ability to filter the default settings array of the plugin
		 *
		 * @param array $settings - The array with all the default settings.
		 *
		 * @since 2.0.0
		 */
		$default_settings = apply_filters( WP_2FA_PREFIX . 'default_settings', $default_settings );

		return $default_settings;
	}

	/**
	 * Fire up classes.
	 */
	public static function init() {

		self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ]      = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );
		self::$plugin_settings[ WP_2FA_SETTINGS_NAME ]             = Settings_Utils::get_option( WP_2FA_SETTINGS_NAME, array() );
		self::$plugin_settings[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] = Settings_Utils::get_option( WP_2FA_WHITE_LABEL_SETTINGS_NAME, array() );

		self::$wp_2fa_email_templates = Settings_Utils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );

		/** We need to exclude all the possible ways, that logic to be executed by some WP request which could come from cron job or AJAX call, which will break the wizard (by storing the settings for the plugin) before it is completed by the user. We also have to check if the user is still processing first time wizard ($_GET parameter), and if the wizard has been finished already (wp_2fa_wizard_not_finished)  */
		if ( Settings_Utils::get_option( 'wizard_not_finished' ) && ! isset( $_GET['is_initial_setup'] ) && ! wp_doing_ajax() && ! defined( 'DOING_CRON' ) ) {

			if ( ! Settings_Utils::get_option( WP_2FA_SETTINGS_NAME ) ) {
				self::update_plugin_settings( self::get_default_settings() );
			}

			// Set a flag so we know we have default values present, not custom.
			Settings_Utils::update_option( 'default_settings_applied', true );
			Settings_Utils::delete_option( 'wizard_not_finished' );
		}

		// Activation/Deactivation.
		register_activation_hook( WP_2FA_FILE, '\WP2FA\Core\activate' );
		register_deactivation_hook( WP_2FA_FILE, '\WP2FA\Core\deactivate' );
		// Register our uninstallation hook.
		register_uninstall_hook( WP_2FA_FILE, '\WP2FA\Core\uninstall' );


		WP_Helper::init();

		// Bootstrap.
		Core\setup();
		Backup_Codes::init();

		if ( is_admin() ) {
			User_Listing::init();
		}

		Shortcodes::init();
		User_Notices::init();

		self::add_actions();

		// Inits all the additional free app extensions.
		$free_extensions = Classes_Helper::get_classes_by_namespace( 'WP2FA\\App\\' );

		foreach ( $free_extensions as $extension ) {
			if ( method_exists( $extension, 'init' ) ) {
				call_user_func_array( array( $extension, 'init' ), array() );
			}
		}
	}

	/**
	 * Add our plugins actions.
	 */
	public static function add_actions() {
		// Plugin redirect on activation, only if we have no settings currently saved.
		if ( ! isset( self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] ) || empty( self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] ) ) {
			add_action( 'admin_init', array( __CLASS__, 'setup_redirect' ), 10 );
		}

		// SettingsPage.
		if ( WP_Helper::is_multisite() ) {
			add_action( 'network_admin_menu', array( '\WP2FA\Admin\Settings_Page', 'create_settings_admin_menu_multisite' ) );
			add_action( 'network_admin_edit_update_wp2fa_network_options', array( '\WP2FA\Admin\Settings_Page', 'update_wp2fa_network_options' ) );
			add_action( 'network_admin_edit_update_wp2fa_network_email_options', array( '\WP2FA\Admin\Settings_Page', 'update_wp2fa_network_email_options' ) );
			add_action( 'network_admin_notices', array( '\WP2FA\Admin\Settings_Page', 'settings_saved_network_admin_notice' ) );
		} else {
			add_action( 'admin_menu', array( '\WP2FA\Admin\Settings_Page', 'create_settings_admin_menu' ) );
			add_action( 'admin_notices', array( '\WP2FA\Admin\Settings_Page', 'settings_saved_admin_notice' ) );
			add_action( 'admin_notices', array( __CLASS__, 'wp_not_writable' ) );
		}
		\add_action( 'wp_ajax_nopriv_set_salt_key', array( __CLASS__, 'set_salt_key' ) );
		\add_action( 'wp_ajax_set_salt_key', array( __CLASS__, 'set_salt_key' ) );

		add_action( 'wp_ajax_get_all_users', array( '\WP2FA\Admin\Settings_Page', 'get_all_users' ) );
		add_action( 'wp_ajax_get_all_network_sites', array( '\WP2FA\Admin\Settings_Page', 'get_all_network_sites' ) );
		add_action( 'wp_ajax_unlock_account', array( '\WP2FA\Admin\Settings_Page', 'unlock_account' ), 10, 1 );
		add_action( 'admin_action_unlock_account', array( '\WP2FA\Admin\Settings_Page', 'unlock_account' ), 10, 1 );
		add_action( 'admin_action_remove_user_2fa', array( '\WP2FA\Admin\Settings_Page', 'remove_user_2fa' ), 10, 1 );
		add_action( 'wp_ajax_remove_user_2fa', array( '\WP2FA\Admin\Settings_Page', 'remove_user_2fa' ), 10, 1 );
		add_action( 'admin_menu', array( '\WP2FA\Admin\Settings_Page', 'hide_settings' ), 999 );
		add_action( 'plugin_action_links_' . WP_2FA_BASE, array( '\WP2FA\Admin\Settings_Page', 'add_plugin_action_links' ) );
		add_filter( 'display_post_states', array( '\WP2FA\Admin\Settings_Page', 'add_display_post_states' ), 10, 2 );

		// Setup_Wizard.
		if ( WP_Helper::is_multisite() ) {
			add_action( 'network_admin_menu', array( '\WP2FA\Admin\Setup_Wizard', 'network_admin_menus' ), 10 );
			add_action( 'admin_menu', array( '\WP2FA\Admin\Setup_Wizard', 'admin_menus' ), 10 );
		} else {
			add_action( 'admin_menu', array( '\WP2FA\Admin\Setup_Wizard', 'admin_menus' ), 10 );
		}
		add_action( 'plugins_loaded', array( __CLASS__, 'add_wizard_actions' ), 10 );
		add_action( 'wp_ajax_send_authentication_setup_email', array( '\WP2FA\Admin\Setup_Wizard', 'send_authentication_setup_email' ) );
		add_action( 'wp_ajax_send_backup_codes_email', array( '\WP2FA\Admin\Setup_Wizard', 'send_backup_codes_email' ) );
		add_action( 'wp_ajax_regenerate_authentication_key', array( '\WP2FA\Admin\Setup_Wizard', 'regenerate_authentication_key' ) );

		// User_Notices.
		add_action( 'wp_ajax_dismiss_nag', array( '\WP2FA\Admin\User_Notices', 'dismiss_nag' ) );
		add_action( 'wp_ajax_wp2fa_dismiss_reconfigure_nag', array( '\WP2FA\Admin\User_Notices', 'dismiss_nag' ) );
		add_action( 'wp_logout', array( '\WP2FA\Admin\User_Notices', 'reset_nag' ), 10, 1 );

		// User_Profile.
		global $pagenow;
		if ( 'profile.php' !== $pagenow || 'user-edit.php' !== $pagenow ) {
			add_action( 'show_user_profile', array( '\WP2FA\Admin\User_Profile', 'inline_2fa_profile_form' ) );
			add_action( 'edit_user_profile', array( '\WP2FA\Admin\User_Profile', 'inline_2fa_profile_form' ) );
			if ( WP_Helper::is_multisite() ) {
				add_action( 'personal_options_update', array( '\WP2FA\Admin\User_Profile', 'save_user_2fa_options' ) );
			}
		}
		add_filter( 'user_row_actions', array( '\WP2FA\Admin\User_Profile', 'user_2fa_row_actions' ), 10, 2 );
		if ( WP_Helper::is_multisite() ) {
			add_filter( 'ms_user_row_actions', array( '\WP2FA\Admin\User_Profile', 'user_2fa_row_actions' ), 10, 2 );
		}
		add_action( 'wp_ajax_validate_authcode_via_ajax', array( '\WP2FA\Admin\User_Profile', 'validate_authcode_via_ajax' ) );
		add_action( 'wp_ajax_wp2fa_test_email', array( __CLASS__, 'handle_send_test_email_ajax' ) );

		// Login.
		add_action( 'wp_login', array( '\WP2FA\Authenticator\Login', 'wp_login' ), 20, 2 );
		add_action( 'wp_loaded', array( '\WP2FA\Authenticator\Login', 'login_form_validate_2fa' ) );
		add_action( 'login_form_validate_2fa', array( '\WP2FA\Authenticator\Login', 'login_form_validate_2fa' ) );
		add_action( 'login_form_backup_2fa', array( '\WP2FA\Authenticator\Login', 'backup_2fa' ) );
		add_action( 'login_enqueue_scripts', array( '\WP2FA\Authenticator\Login', 'dequeue_style' ), PHP_INT_MAX );

		/**
		 * Keep track of all the user sessions for which we need to invalidate the
		 * authentication cookies set during the initial password check.
		 */
		add_action( 'set_auth_cookie', array( '\WP2FA\Authenticator\Login', 'collect_auth_cookie_tokens' ) );
		add_action( 'set_logged_in_cookie', array( '\WP2FA\Authenticator\Login', 'collect_auth_cookie_tokens' ) );

		// Run only after the core wp_authenticate_username_password() check.
		add_filter( 'authenticate', array( '\WP2FA\Authenticator\Login', 'filter_authenticate' ), 50 );
		add_filter( 'wp_authenticate_user', array( '\WP2FA\Authenticator\Login', 'run_authentication_check' ), 10, 2 );

		// User Register.
		add_action( 'set_user_role', array( '\WP2FA\Admin\User_Registered', 'check_user_upon_role_change' ), 10, 3 );

		// Block users from admin if needed.
		$user_block_hook = is_admin() || is_network_admin() ? 'init' : 'wp';
		add_action( $user_block_hook, array( __CLASS__, 'block_unconfigured_users_from_admin' ), 10 );
		// Check if usermeta is out of sync with settings.
		add_action( $user_block_hook, array( __CLASS__, 'update_usermeta_if_required' ), 5 );

		// Help & Contact Us.
		add_action( WP_2FA_PREFIX . 'after_admin_menu_created', array( '\WP2FA\Admin\Help_Contact_Us', 'add_extra_menu_item' ) );

		// phpcs:ignore
		/* @free:start */
		// Premium Features.
		add_action( WP_2FA_PREFIX . 'after_admin_menu_created', array( 'WP2FA\Admin\Premium_Features', 'add_extra_menu_item' ) );
		add_action( WP_2FA_PREFIX . 'before_plugin_settings', array( 'WP2FA\Admin\Premium_Features', 'add_settings_banner' ) );
		add_action( 'admin_footer', array( 'WP2FA\Admin\Premium_Features', 'pricing_new_tab_js' ) );
		/* @free:end */

		add_action( 'admin_footer', array( '\WP2FA\Admin\User_Profile', 'dismiss_nag_notice' ) );
	}

	/**
	 * Add actions specific to the wizard.
	 */
	public static function add_wizard_actions() {
		if ( function_exists( 'wp_get_current_user' ) && current_user_can( 'read' ) ) {
			add_action( 'admin_init', array( '\WP2FA\Admin\Setup_Wizard', 'setup_page' ), 10 );
		}
	}

	/**
	 * Redirect user to 1st time setup.
	 *
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 */
	public static function setup_redirect() {

		// Bail early before the redirect if the user can't manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$registered_and_active = 'yes';
		if ( function_exists( 'wp2fa_freemius' ) ) {
			$registered_and_active = wp2fa_freemius()->is_registered() && wp2fa_freemius()->has_active_valid_license() ? 'yes' : 'no';
		}

		if ( Settings_Utils::get_option( 'redirect_on_activate', false ) && 'yes' === $registered_and_active ) {
			// Delete redirect option.
			Settings_Utils::delete_option( 'redirect_on_activate' );

			Settings_Utils::update_option( 'wizard_not_finished', true );

			$redirect = add_query_arg(
				array(
					'page'             => 'wp-2fa-setup',
					'is_initial_setup' => 'true',
				),
				admin_url( 'user-edit.php' )
			);

			wp_safe_redirect( $redirect );
			exit();
		}
	}

	/**
	 * Return user roles.
	 *
	 * @return array User roles.
	 */
	public static function wp_2fa_get_roles() {
		return WP_Helper::get_roles_wp();
	}

	/**
	 * Util function to grab settings or apply defaults if no settings are saved into the db.
	 *
	 * @param string  $setting_name Settings to grab value of.
 	 * @param boolean $get_default_on_empty return default setting value if current one is empty.
	 * @param boolean $get_default_value return default value setting (ignore the stored ones).
	 * @param string  $role - The name of the user role.
	 *
	 * @return mixed               Settings value or default value.
	 */
	public static function get_wp2fa_setting( $setting_name = '', $get_default_on_empty = false, $get_default_value = false, $role = 'global' ) {
		$role = ( is_null( $role ) || empty( $role ) ) ? 'global' : $role;
		return self::get_wp2fa_setting_generic( WP_2FA_POLICY_SETTINGS_NAME, $setting_name, $get_default_on_empty, $get_default_value, $role );
	}

	/**
	 * Util function to grab settings or apply defaults if no settings are saved into the db.
	 *
	 * @param string  $setting_name Settings to grab value of.
 	 * @param boolean $get_default_on_empty return default setting value if current one is empty.
	 * @param boolean $get_default_value return default value setting (ignore the stored ones).
	 *
	 * @return mixed               Settings value or default value.
	 */
	public static function get_wp2fa_general_setting( $setting_name = '', $get_default_on_empty = false, $get_default_value = false ) {

		return self::get_wp2fa_setting_generic( WP_2FA_SETTINGS_NAME, $setting_name, $get_default_on_empty, $get_default_value );
	}

	/**
	 * Util function to grab white label settings or apply defaults if no settings are saved into the db.
	 *
	 * @param  string  $setting_name Settings to grab value of.
 	 * @param boolean $get_default_on_empty return default setting value if current one is empty.
	 * @param boolean $get_default_value return default value setting (ignore the stored ones).
	 *
	 * @return string               Settings value or default value.
	 */
	public static function get_wp2fa_white_label_setting( $setting_name = '', $get_default_on_empty = false, $get_default_value = false ) {

		return self::get_wp2fa_setting_generic( WP_2FA_WHITE_LABEL_SETTINGS_NAME, $setting_name, $get_default_on_empty, $get_default_value );
	}

	/**
	 * Generic method for extracting settings from the plugin
	 *
	 * @param string  $wp_2fa_setting - The name of the settings type.
	 * @param string  $setting_name - The name of the setting to extract.
	 * @param boolean $get_default_on_empty - Should we use default value on empty.
	 * @param boolean $get_default_value - Extract default value.
	 * @param string  $role - The name of the user role.
	 *
	 * @return mixed
	 */
	private static function get_wp2fa_setting_generic( $wp_2fa_setting = WP_2FA_POLICY_SETTINGS_NAME, $setting_name = '', $get_default_on_empty = false, $get_default_value = false, $role = 'global' ) {
		$default_settings = self::get_default_settings();
		$role             = ( is_null( $role ) || empty( $role ) ) ? 'global' : $role;

		if ( true === $get_default_value ) {
			if ( isset( $default_settings[ $setting_name ] ) ) {
				return $default_settings[ $setting_name ];
			}

			return false;
		}

		$apply_defaults = false;

		$wp2fa_setting = self::$plugin_settings[ $wp_2fa_setting ];

		// If we have no setting name, return them all.
		if ( empty( $setting_name ) ) {
			return $wp2fa_setting;
		}

		// First lets check if any options have been saved.
		if ( empty( $wp2fa_setting ) || ! isset( $wp2fa_setting ) ) {
			$apply_defaults = true;
		}

		if ( $apply_defaults ) {
			return $default_settings[ $setting_name ];
		} elseif ( ! isset( $wp2fa_setting[ $setting_name ] ) ) {
			if ( true === $get_default_on_empty ) {
				if ( isset( $default_settings[ $setting_name ] ) ) {
					return $default_settings[ $setting_name ];
				}
			}
			return false;
		} else {

			if ( WP_2FA_POLICY_SETTINGS_NAME === $wp_2fa_setting ) {
				/**
				 * Extensions could change the extracted value, based on custom / different / specific for role settings.
				 *
				 * @param mixed - Value of the setting.
				 * @param string - The name of the setting.
				 * @param string - The role name.
				 *
				 * @since 2.0.0
				 */
				return apply_filters( WP_2FA_PREFIX . 'setting_generic', $wp2fa_setting[ $setting_name ], $setting_name, $role );
			} else {
				return $wp2fa_setting[ $setting_name ];
			}
		}
	}

	/**
	 * Util function to grab EMAIL settings or apply defaults if no settings are saved into the db.
	 *
	 * @param  string $setting_name Settings to grab value of.
	 */
	public static function get_wp2fa_email_templates( $setting_name = '' ) {

		// If we have no setting name, return what ever is saved.
		if ( empty( $setting_name ) ) {
			return self::$wp_2fa_email_templates;
		}

		// If we have a saved setting, return it.
		if ( $setting_name && isset( self::$wp_2fa_email_templates[ $setting_name ] ) ) {
			return self::$wp_2fa_email_templates[ $setting_name ];
		}

		// Create Login Code Message.
		$login_code_subject = __( 'Your login confirmation code for {site_name}', 'wp-2fa' );

		$login_code_body = '<p>' . sprintf(
			// translators: The login code provided from the plugin.
			esc_html__( 'Enter %1$1s to log in.', 'wp-2fa' ),
			'<strong>{login_code}</strong>'
		);
		$login_code_body .= '</p>';
		$login_code_body .= '<p>' . esc_html__( 'Thank you.', 'wp-2fa' ) . '</p>';
		$login_code_body .= '<p>' . esc_html__( 'Email sent by', 'wp-2fa' );
		$login_code_body .= ' <a href="https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/" target="_blank">' . esc_html__( 'WP 2FA plugin.', 'wp-2fa' ) . '</a>';
		$login_code_body .= '</p>';

		// Create User Locked Message.
		$user_locked_subject = __( 'Your user on {site_name} has been locked', 'wp-2fa' );

		$user_locked_body  = '<p>' . esc_html__( 'Hello.', 'wp-2fa' ) . '</p>';
		$user_locked_body .= '<p>' . sprintf(
			// translators: %1s - the name of the user
			// translators: %2s - the name of the site.
			esc_html__( 'Since you have not enabled two-factor authentication for the user %1$1s on the website %2$2s within the grace period, your account has been locked.', 'wp-2fa' ),
			'{user_login_name}',
			'{site_name}'
		);
		$user_locked_body .= '</p>';
		$user_locked_body .= '<p>' . esc_html__( 'Contact your website administrator to unlock your account.', 'wp-2fa' ) . '</p>';
		$user_locked_body .= '<p>' . esc_html__( 'Thank you.', 'wp-2fa' ) . '</p>';
		$user_locked_body .= '<p>' . esc_html__( 'Email sent by', 'wp-2fa' );
		$user_locked_body .= ' <a href="https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/" target="_blank">' . esc_html__( 'WP 2FA plugin.', 'wp-2fa' ) . '</a>';
		$user_locked_body .= '</p>';

		// Create User unlocked Message.
		$user_unlocked_subject = __( 'Your user on {site_name} has been unlocked', 'wp-2fa' );
		$user_unlocked_body    = '';

		$user_unlocked_body .= '<p>' . __( 'Hello,', 'wp-2fa' ) . '</p><p>' . esc_html__( 'Your user', 'wp-2fa' ) . ' <strong>{user_login_name}</strong> ' . esc_html__( 'on the website', 'wp-2fa' ) . ' {site_url} ' . __( 'has been unlocked. Please configure two-factor authentication within the grace period, otherwise your account will be locked again.', 'wp-2fa' ) . '</p>';

		if ( ! empty( self::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
			$user_unlocked_body .= '<p>' . __( 'You can configure 2FA from this page:', 'wp-2fa' ) . ' <a href="{2fa_settings_page_url}" target="_blank">{2fa_settings_page_url}.</a></p>';
		}

		$user_unlocked_body .= '<p>' . __( 'Thank you.', 'wp-2fa' ) . '</p><p>' . __( 'Email sent by', 'wp-2fa' ) . ' <a href="https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/" target="_blank">' . __( 'WP 2FA plugin', 'wp-2fa' ) . '</a></p>';

		// Create User backup codes Message.
		$user_backup_codes_subject = __( '2FA backup codes for user {user_login_name} on {site_name}', 'wp-2fa' );
		$user_backup_codes_body    = '';

		$user_backup_codes_body .= '<p>' . __( 'Hello,', 'wp-2fa' ) . '</p><p>' . esc_html__( 'Below please find the 2FA backup codes for your user', 'wp-2fa' ) . ' <strong>{user_login_name}</strong> ' . esc_html__( 'on the website', 'wp-2fa' ) . ' <strong>{site_name}</strong>. ' . __( 'The website\'s URL is', 'wp-2fa' ) . ' {site_url} </p>';

		$user_backup_codes_body .= '{backup_codes}';

		$user_backup_codes_body .= '<p>' . __( 'Thank you for enabling 2FA on your account and helping us keeping the website secure.', 'wp-2fa' ) . '</p><p>' . __( 'Email sent by', 'wp-2fa' ) . ' <a href="https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/" target="_blank">' . __( 'WP 2FA plugin', 'wp-2fa' ) . '</a></p>';

		// Array of defaults, now we have things setup above.
		$default_settings = array(
			'email_from_setting'                  => 'use-defaults',
			'custom_from_email_address'           => '',
			'custom_from_display_name'            => '',
			'login_code_email_subject'            => $login_code_subject,
			'login_code_email_body'               => $login_code_body,
			'user_account_locked_email_subject'   => $user_locked_subject,
			'user_account_locked_email_body'      => $user_locked_body,
			'user_account_unlocked_email_subject' => $user_unlocked_subject,
			'user_account_unlocked_email_body'    => $user_unlocked_body,
			'user_backup_codes_email_subject'     => $user_backup_codes_subject,
			'user_backup_codes_email_body'        => $user_backup_codes_body,
			'send_account_locked_email'           => 'enable_account_locked_email',
			'send_account_unlocked_email'         => 'enable_account_unlocked_email',
		);

		/**
		 * Allows 3rd party providers to their own settings for the mail templates.
		 *
		 * @param array $default_settings - Array with the default settings.
		 *
		 * @since 2.0.0
		 */
		$default_settings = apply_filters( WP_2FA_PREFIX . 'mail_default_settings', $default_settings );

		return $default_settings[ $setting_name ];
	}

	/**
	 * Util which we use to replace our {strings} with actual, useful stuff.
	 *
	 * @param string     $input   Text we are working on.
	 * @param int|string $user_id User id, if its needed.
	 * @param string     $token   Login code, if its needed..
	 * @param string     $override_grace_period - Value to override grace period with.
	 *
	 * @return string          The output, with all the {strings} swapped out.
	 */
	public static function replace_email_strings( $input = '', $user_id = '', $token = '', $override_grace_period = '' ) {

		// Gather grace period.
		$grace_period_string = '';
		if ( isset( $override_grace_period ) && ! empty( $override_grace_period ) ) {
			$grace_period_string = $override_grace_period;
		} else {
			$grace_policy        = self::get_wp2fa_setting( 'grace-policy' );
			$grace_period_string = Date_Time_Utils::format_grace_period_expiration_string( $grace_policy );
		}

		// Setup user data.
		if ( isset( $user_id ) && ! empty( $user_id ) ) {
			$user = get_userdata( $user_id );
		} else {
			$user = wp_get_current_user();
		}

		// Setup token.
		if ( isset( $token ) && ! empty( $token ) ) {
			$login_code = $token;
		} else {
			$login_code = '';
		}

		$new_page_id = Settings::get_role_or_default_setting( 'custom-user-page-id', $user );
		if ( ! empty( $new_page_id ) ) {
			$new_page_permalink = get_permalink( $new_page_id );
		} else {
			$new_page_permalink = '';
		}

		// These are the strings we are going to search for, as well as there respective replacements.
		$replacements = array(
			'{site_url}'              => esc_url( get_bloginfo( 'url' ) ),
			'{site_name}'             => sanitize_text_field( get_bloginfo( 'name' ) ),
			'{grace_period}'          => sanitize_text_field( $grace_period_string ),
			'{user_login_name}'       => sanitize_text_field( $user->user_login ),
			'{user_first_name}'       => sanitize_text_field( $user->user_firstname ),
			'{user_last_name}'        => sanitize_text_field( $user->user_lastname ),
			'{user_display_name}'     => sanitize_text_field( $user->display_name ),
			'{login_code}'            => $login_code,
			'{2fa_settings_page_url}' => esc_url( $new_page_permalink ),
			'{user_ip_address}'       => Request_Utils::get_ip(),
		);

		/**
		 * 3rd party plugins could change the mail strings, or provide their own.
		 *
		 * @param array $replacements - The array with all the currently supported strings.
		 */
		$replacements = apply_filters(
			WP_2FA_PREFIX . 'replacement_email_strings',
			$replacements
		);

		$final_output = str_replace( array_keys( $replacements ), array_values( $replacements ), $input );
		return $final_output;
	}

	/**
	 * Util which we use to replace our {strings} with actual, useful stuff.
	 *
	 * @param string  $input   Text we are working on.
	 * @param WP_User $user   The WP User.
	 *
	 * @return string          The output, with all the {strings} swapped out.
	 */
	public static function replace_wizard_strings( $input = '', $user = false ) {

		if ( ! $user ) {
			return $input;
		}

		$available_methods = Methods::get_enabled_methods( User_Helper::get_user_role( $user ) );

		// These are the strings we are going to search for, as well as there respective replacements.
		$replacements = array(
			'{available_methods_count}' => count( $available_methods[ User_Helper::get_user_role( $user ) ] ),
		);

		/**
		 * 3rd party plugins could change the mail strings, or provide their own.
		 *
		 * @param array $replacements - The array with all the currently supported strings.
		 */
		$replacements = apply_filters(
			WP_2FA_PREFIX . 'replacement_wizard_strings',
			$replacements
		);

		$final_output = str_replace( array_keys( $replacements ), array_values( $replacements ), $input );
		return $final_output;
	}

	/**
	 * If a user is trying to access anywhere other than the 2FA config area, this blocks them.
	 *
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 */
	public static function block_unconfigured_users_from_admin() {
		global $pagenow;

		$user = User_Helper::get_user();
		if ( 0 === $user->ID ) {
			return;
		}

		$redirect = true;

		if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) ) {
			$redirect = User_Licensing::enable_2fa_user_setting( true );
		}

		if ( $redirect ) {
			$is_user_instantly_enforced = User_Helper::get_user_enforced_instantly();
			$grace_period_expiry_time   = (int) User_Helper::get_user_expiry_date();
			$time_now                   = time();
			if ( $is_user_instantly_enforced && ! empty( $grace_period_expiry_time ) && $grace_period_expiry_time < $time_now && ! User_Helper::is_excluded( $user->ID ) ) {

				/**
				 * We should only allow:
				 * - 2FA setup wizard in the administration
				 * - custom 2FA page if enabled and created
				 * - AJAX requests originating from these 2FA setup UIs
				 */
				if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && self::action_check() ) { // phpcs:ignore
					return;
				}

				if ( is_admin() || is_network_admin() ) {
					$allowed_admin_page = 'profile.php';
					if ( $pagenow === $allowed_admin_page && ( isset( $_GET['show'] ) && 'wp-2fa-setup' === $_GET['show'] ) ) { // phpcs:ignore
						return;
					}
				}

				if ( is_page() ) {
					$custom_user_page_id = Settings::get_role_or_default_setting( 'custom-user-page-id', $user );
					if ( ! empty( $custom_user_page_id ) && get_the_ID() === (int) $custom_user_page_id ) {
						return;
					}
				}

				// force a redirect to the 2FA set-up page if it exists.
				$custom_user_page_id = Settings::get_role_or_default_setting( 'custom-user-page-id', $user );
				if ( ! empty( $custom_user_page_id ) ) {
					wp_redirect( Settings::get_custom_page_link( $user ) );
					exit;
				}

				// custom 2FA page is not set-up, force redirect to the wizard in administration.
				wp_redirect( Settings::get_setup_page_link() );
				exit;
			}
		}
	}

	/**
	 * Checks if user's settings hash matches the current one, and if not, updates it.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public static function update_usermeta_if_required() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		// doing this invokes update of necessary user metadata in the User class.
		User::get_instance();
	}

	/**
	 * Handles AJAX calls for sending test emails.
	 */
	public static function handle_send_test_email_ajax() {

		// check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		// check email id.
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( \wp_unslash( $_POST['email_id'] ) ) : null;
		if ( null === $email_id || false === $email_id ) {
			wp_send_json_error();
		}

		// check nonce.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ) : null;
		if ( null === $nonce || false === $nonce || ! wp_verify_nonce( $nonce, 'wp-2fa-email-test-' . $email_id ) ) {
			wp_send_json_error();
		}

		$user_id = get_current_user_id();
		// Grab user data.
		$user = get_userdata( $user_id );
		// Grab user email.
		$email = $user->user_email;

		if ( 'config_test' === $email_id ) {
			$email_sent = Settings_Page::send_email(
				$email,
				esc_html__( 'Test email from WP 2FA', 'wp-2fa' ),
				esc_html__( 'This email was sent by the WP 2FA plugin to test the email delivery.', 'wp-2fa' )
			);
			if ( $email_sent ) {
				wp_send_json_success( 'Test email was successfully sent to <strong>' . $email . '</strong>' );
			}

			wp_send_json_error();
		}

		/**
		 * All email templates
		 *
		 *  @var Email_Template[] $email_templates
		 */
		$email_templates = Settings_Page_Email::get_email_notification_definitions();
		foreach ( $email_templates as $email_template ) {
			if ( $email_id === $email_template->get_email_content_id() ) {
				// send the test email.

				// Setup the email contents.
				$subject = wp_strip_all_tags( self::get_wp2fa_email_templates( $email_id . '_email_subject' ) );
				$message = wpautop( self::get_wp2fa_email_templates( $email_id . '_email_body' ), $user_id );

				$email_sent = Settings_Page::send_email( $email, $subject, $message );
				if ( $email_sent ) {
					wp_send_json_success( 'Test email <strong>' . $email_template->get_title() . '</strong> was successfully sent to <strong>' . $email . '</strong>' );
				}

				wp_send_json_error();
			}
		}
	}

	/**
	 * Returns currently stored settings
	 *
	 * @return array
	 */
	public static function get_policy_settings() {
		/**
		 * Extensions could change the stored settings value, based on custom / different / specific for role settings.
		 *
		 * @param array - Value of the settings.
		 *
		 * @since 2.0.0
		 */
		$settings = apply_filters( WP_2FA_PREFIX . 'policy_settings', self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] );

		return $settings;
	}

	/**
	 * Checks the action parameter against given list of actions
	 *
	 * @return bool
	 *
	 * @since 2.0.0
	 */
	private static function action_check() {
		if ( ! isset( $_REQUEST['action'] ) ) { //phpcs:ignore -- No nonce - that is not needed here
			return false;
		}
		$actions_array = array(
			'send_authentication_setup_email',
			'validate_authcode_via_ajax',
			'heartbeat',
			'regenerate_authentication_key',
			'send_backup_codes_email',
		);

		/**
		 * Allows 3rd party providers to their own settings for the mail templates.
		 *
		 * @param array $actions_array - Array with the default settings.
		 *
		 * @since 2.0.0
		 */
		$actions_array = apply_filters( WP_2FA_PREFIX . 'actions_check', $actions_array );

		return in_array( $_REQUEST['action'], $actions_array, true );
	}

	/**
	 * Updates the plugin settings, the settings hash in the database as well as a local (cached) copy of the settings.
	 *
	 * @param array  $settings - The settings values.
	 * @param bool   $skip_option_save If true, the settings themselves are not saved. This is needed when saving settings from settings page as WordPress options API takes care of that.
	 * @param string $settings_name - The name of the settings to extract.
	 *
	 * @since 2.0.0
	 */
	public static function update_plugin_settings( $settings, $skip_option_save = false, $settings_name = WP_2FA_POLICY_SETTINGS_NAME ) {
		// update local copy of settings.
		self::$plugin_settings[ $settings_name ] = $settings;

		if ( ! $skip_option_save ) {
			// update the database option itself.
			Settings_Utils::update_option( $settings_name, $settings );
		}

		if ( WP_2FA_POLICY_SETTINGS_NAME === $settings_name ) {
			// Create a hash for comparison when we interact with a use.
			$settings_hash = Settings_Utils::create_settings_hash( self::get_policy_settings() );
			Settings_Utils::update_option( WP_2FA_PREFIX . 'settings_hash', $settings_hash );
		}
	}

	/**
	 * Getter for the TOTP secret key of the plugin for the current instance
	 *
	 * Note: that is legacy code and will be removed.
	 *
	 * @return string
	 *
	 * @since 2.0.0
	 */
	public static function get_secret_key() {
		if ( null === self::$secret_key ) {
			if ( ! defined( File_Writer::SECRET_NAME ) ) {
				self::check_for_key();
			} else {
				self::$secret_key = constant( File_Writer::SECRET_NAME );
			}
		}

		return self::$secret_key;
	}

	/**
	 * Sets the salt key into the wp-config.php file via AJAX request.
	 *
	 * @return void
	 *
	 * @since 2.4.0
	 */
	public static function set_salt_key() {
		if ( \wp_doing_ajax() ) {
			if ( isset( $_REQUEST['_wpnonce'] ) ) {
				$nonce_check = \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wp-2fa-set-salt-nonce' );
				if ( ! $nonce_check ) {
					\wp_send_json_error( new \WP_Error( 500, \esc_html__( 'Nonce checking failed', 'wp-2fa' ) ), 400 );
				} else {
					if ( \current_user_can( 'manage_options' ) ) {
						if ( ! File_Writer::can_write_to_file( File_Writer::get_wp_config_file_path() ) ) {
							\wp_send_json_error(
								new \WP_Error(
									500,
									\esc_html__(
										'Unable to write to wp-config.php',
										'wp-2fa'
									)
								),
								400
							);
						} else {
							$secret_key = Settings_Utils::get_option( 'secret_key' );
							if ( ! empty( $secret_key ) ) {
								File_Writer::save_secret_key( $secret_key );
								Settings_Utils::delete_option( 'secret_key' );
								\wp_send_json_success(
									\esc_html__(
										'wp-config.php successfully update, global setting deleted',
										'wp-2fa'
									)
								);
							} else {
								\wp_send_json_error(
									new \WP_Error(
										500,
										\esc_html__(
											'Unable to find global secret key',
											'wp-2fa'
										)
									),
									400
								);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Checks if the wp-config.php file is writable, show notice to the admin if it is not
	 *
	 * @return void
	 *
	 * @since 2.4.0
	 */
	public static function wp_not_writable() {

		if ( ! \defined( 'WP2FA_SECRET_IS_IN_DB' ) || true !== WP2FA_SECRET_IS_IN_DB ) {
			return;
		}

		if ( ! File_Writer::can_write_to_file( File_Writer::get_wp_config_file_path() ) ) {
			$whitelist_admin_pages = array(
				'wp-2fa_page_wp-2fa-settings',
				'toplevel_page_wp-2fa-policies',
				'wp-2fa_page_wp-2fa-help-contact-us',
				'wp-2fa_page_wp-2fa-policies-account',
				'wp-2fa_page_wp-2fa-reports',
			);
			$admin_page            = get_current_screen();
			if ( in_array( $admin_page->base, $whitelist_admin_pages ) ) {
				?>
			<div class="notice notice-warning" id="config-update-notice">
				<?php
				$message = sprintf(
					'<p>%1$s <a href="https://wp2fa.io/support/kb/add-2fa-plugin-encryption-key-wp-config" noopener target="_blank">%2$s</a><br>%3$s</p>',
					esc_html__( 'For security reasons WP 2FA needs to store the private key in the wp-config.php file. However, it is unable to. This can happen because of restrictive permissions, or the file is not in the default location. To fix this you can:', 'wp-2fa' ) . '<br><br>' .

					esc_html__( 'Option A) allow the plugin to write to the wp-config.php file temporarily by changing the wp-config.php permissions to 755. Once ready, click the button to proceed.', 'wp-2fa' ) . '<br>' .

					esc_html__( 'Option B) Add the encryption key to the wp-config.php file yourself by ', 'wp-2fa' ),
					esc_html__( 'following these instructions.', 'wp-2fa' ) . '<br>',
					esc_html__(
						'Once you complete any of the above, please click the button below.
					',
						'wp-2fa'
					),
				)
				?>
				<?php echo $message; ?>
				<p><button id="salt-update" type="button">
					<span><?php esc_html_e( 'Write key to file now / Check for the key in file', 'wp-2fa' ); ?></span>
				</button></p>
			</div>
				<script>
				jQuery(document).ready(function($) {
					$(document).on('click', '#salt-update', function( event ) {
						const ajaxURL = (typeof wp2faWizardData != "undefined") ? wp2faWizardData.ajaxURL : ajaxurl;
						const nonceValue = '<?php echo \esc_attr( \wp_create_nonce( 'wp-2fa-set-salt-nonce' ) ); ?>';
						jQuery.ajax({
							url: ajaxURL,
							data: {
								action: 'set_salt_key',
								_wpnonce: nonceValue
							},
							success: function (data) {
								if (data.success) {
									jQuery('#config-update-notice .notice-dismiss').click();
								} else {
									alert(data.data);
								}
							},
							error: function (data) {
								alert(data.responseJSON.data[0].message);
							}
						});
					});
				});
				</script>
				<?php
			}
		}
	}

	/**
	 * Checks and sets the global wp2fa salt
	 *
	 * @return void
	 *
	 * @since 2.4.0
	 */
	private static function check_for_key() {
		self::$secret_key = Settings_Utils::get_option( 'secret_key' );
		if ( empty( self::$secret_key ) ) {
			self::$secret_key = base64_encode( Open_SSL::secure_random() ); // phpcs:ignore
			if ( ! File_Writer::save_secret_key( self::$secret_key ) ) {
				Settings_Utils::update_option( 'secret_key', self::$secret_key );
			}
		}
	}
}
