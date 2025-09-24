<?php
/**
 * Main plugin class.
 *
 * @package    wp2fa
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA;

use WP2FA\Methods\TOTP;
use WP2FA\Utils\White_Label;
use WP2FA\Admin\Setup_Wizard;
use WP2FA\Admin\User_Listing;
use WP2FA\Admin\User_Notices;
use WP2FA\Admin\User_Profile;
use WP2FA\Admin\FlyOut\FlyOut;
use WP2FA\Admin\Settings_Page;
use WP2FA\Authenticator\Login;
use WP2FA\Utils\Request_Utils;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\User_Registered;
use WP2FA\Shortcodes\Shortcodes;
use WP2FA\Utils\Date_Time_Utils;
use WP2FA\Admin\Premium_Features;
use WP2FA\Authenticator\Open_SSL;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Freemius\User_Licensing;
use WP2FA\Admin\Views\Re_Login_2FA;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\Ajax_Helper;
use WP2FA\Admin\Helpers\File_Writer;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Controllers\Endpoints;
use WP2FA\Admin\Plugin_Updated_Notice;
use WP2FA\Admin\Helpers\Classes_Helper;
use WP2FA\Admin\Helpers\Methods_Helper;
use WP2FA\Authenticator\Reset_Password;
use WP2FA\Admin\Views\Password_Reset_2FA;
use WP2FA\Admin\Views\Grace_Period_Notifications;

if ( ! class_exists( '\WP2FA\WP2FA' ) ) {
	/**
	 * Main WP2FA Class.
	 */
	class WP2FA {

		/**
		 * Holds the global plugin secret key
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
		 * Local static cache for plugins type.
		 *
		 * @var string
		 *
		 * @since 3.0.0
		 */
		private static $plugin_type = null;

		/**
		 * Local static cache for plugins settings.
		 *
		 * @var array
		 *
		 * @since 2.8.0
		 */
		private static $default_settings = array();

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
			if ( empty( self::$default_settings ) ) {
				self::$default_settings = array(
					'enforcement-policy'               => 'do-not-enforce',
					'excluded_users'                   => array(),
					'excluded_roles'                   => array(),
					'enforced_users'                   => array(),
					'enforced_roles'                   => array(),
					'grace-period'                     => 3,
					'grace-period-denominator'         => 'days',
					'enable_destroy_session'           => '',
					'limit_access'                     => '',
					'enable_rest'                      => false,
					'brute_force_disable'              => '',
					'2fa_settings_last_updated_by'     => '',
					'2fa_main_user'                    => '',
					'grace-period-expiry-time'         => '',
					'plugin_version'                   => WP_2FA_VERSION,
					'delete_data_upon_uninstall'       => '',
					'excluded_sites'                   => array(),
					'included_sites'                   => array(),
					'create-custom-user-page'          => 'no',
					'redirect-user-custom-page'        => '',
					'redirect-user-custom-page-global' => '',
					'custom-user-page-url'             => '',
					'custom-user-page-id'              => '',
					'hide_remove_button'               => '',
					'separate-multisite-page-url'      => '',
					'grace-policy'                     => 'use-grace-period',
					'superadmins-role-add'             => 'no',
					'superadmins-role-exclude'         => 'no',
					'method_invalid_setting'           => 'login_block',
				);
				/**
				 * Gives the ability to filter the default settings array of the plugin
				 *
				 * @param array $settings - The array with all the default settings.
				 *
				 * @since 2.0.0
				 */
				self::$default_settings = \apply_filters( WP_2FA_PREFIX . 'default_settings', self::$default_settings );
			}

			return self::$default_settings;
		}

		/**
		 * Inits the plugin related classes and settings
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function init() {

			\add_filter(
				'aadvana_trigger_error',
				function ( $trigger_error, $function_name, $errstr ) {
					if ( '_load_textdomain_just_in_time' === $function_name && strpos( $errstr, '<code>' . WP_2FA_TEXTDOMAIN ) !== false ) {
						$trigger_error = false;
					}

					return $trigger_error;
				},
				10,
				3
			);

			Methods_Helper::init();

			self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ]      = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );
			self::$plugin_settings[ WP_2FA_SETTINGS_NAME ]             = Settings_Utils::get_option( WP_2FA_SETTINGS_NAME, array() );
			self::$plugin_settings[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] = ( ! empty( Settings_Utils::get_option( WP_2FA_WHITE_LABEL_SETTINGS_NAME, array() ) ) ) ? Settings_Utils::get_option( WP_2FA_WHITE_LABEL_SETTINGS_NAME, array() ) : White_Label::get_default_settings();

			self::$wp_2fa_email_templates = Settings_Utils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );

			White_Label::init();

			/** We need to exclude all the possible ways, that logic to be executed by some WP request which could come from cron job or AJAX call, which will break the wizard (by storing the settings for the plugin) before it is completed by the user. We also have to check if the user is still processing first time wizard ($_GET parameter), and if the wizard has been finished already (wp_2fa_wizard_not_finished)  */
			if ( Settings_Utils::get_option( 'wizard_not_finished' ) && ! isset( $_GET['is_initial_setup'] ) && ! wp_doing_ajax() && ! defined( 'DOING_CRON' ) ) {

				if ( ! Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME ) ) {
					self::update_plugin_settings( self::get_default_settings() );
				}

				// Set a flag so we know we have default values present, not custom.
				Settings_Utils::update_option( 'default_settings_applied', true );
				Settings_Utils::delete_option( 'wizard_not_finished' );
			}


			WP_Helper::init();

			// Bootstrap.
			Core\setup();

			if ( \is_admin() && ! \wp_doing_ajax() ) {
				User_Listing::init();
				// Hide all unrelated to the plugin notices on the plugin admin pages.
				\add_action( 'admin_print_scripts', array( WP_Helper::class, 'hide_unrelated_notices' ) );
			}

			Grace_Period_Notifications::init();
			Password_Reset_2FA::init();
			Re_Login_2FA::init();

			Shortcodes::init();
			\add_action( 'after_setup_theme', array( User_Notices::class, 'init' ), 10 );
			\add_action( 'after_setup_theme', array( FlyOut::class, 'init' ), 10 );
			Plugin_Updated_Notice::init();

			self::add_actions();

			if ( false === Settings_Utils::string_to_bool( self::get_wp2fa_general_setting( 'disable_rest' ) ) ) {
				Endpoints::init();
			}

			// Inits all the additional free app extensions.
			$free_extensions = Classes_Helper::get_classes_by_namespace( 'WP2FA\\App\\' );

			foreach ( $free_extensions as $extension ) {
				if ( method_exists( $extension, 'init' ) ) {
					call_user_func_array( array( $extension, 'init' ), array() );
				}
			}
		}

		/**
		 * Inits all the plugin hooks
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function add_actions() {
			// Plugin redirect on activation, only if we have no settings currently saved.
			if ( ( ! isset( self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] ) || empty( self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] ) ) && Settings_Utils::get_option( 'redirect_on_activate', false ) ) {
				\add_action( 'admin_init', array( __CLASS__, 'setup_redirect' ), 10 );
			} elseif ( ! \is_array( Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME ) ) ) {
					Settings_Utils::delete_option( WP_2FA_POLICY_SETTINGS_NAME );
					self::update_plugin_settings( self::get_default_settings() );
			}
			if ( Settings_Utils::get_option( 'wizard_not_finished' ) && function_exists( 'wp_get_current_user' ) && \current_user_can( 'manage_options' ) ) {
				\add_action( 'admin_init', array( Setup_Wizard::class, 'setup_page' ), 10 );
			}
			if ( Settings_Utils::get_option( 'wizard_not_finished' ) && function_exists( 'wp_get_current_user' ) && \current_user_can( 'manage_options' ) ) {
				\add_action( 'admin_init', array( Setup_Wizard::class, 'setup_page' ), 10 );
			}

			// SettingsPage.
			if ( WP_Helper::is_multisite() ) {
				\add_action( 'network_admin_menu', array( Settings_Page::class, 'create_settings_admin_menu_multisite' ) );
				\add_action( 'network_admin_edit_update_wp2fa_network_options', array( Settings_Page::class, 'update_wp2fa_network_options' ) );
				\add_action( 'network_admin_edit_update_wp2fa_network_email_options', array( Settings_Page::class, 'update_wp2fa_network_email_options' ) );
				\add_action( 'network_admin_notices', array( Settings_Page::class, 'settings_saved_network_admin_notice' ) );
				\add_action( 'network_admin_notices', array( __CLASS__, 'wp_not_writable' ) );
			} else {
				\add_action( 'admin_menu', array( Settings_Page::class, 'create_settings_admin_menu' ) );
				\add_action( 'admin_notices', array( Settings_Page::class, 'settings_saved_admin_notice' ) );
				\add_action( 'admin_notices', array( __CLASS__, 'wp_not_writable' ) );
			}
			\add_action( 'wp_ajax_wp2fa_dismiss_notice_mail_domain', array( Settings_Page::class, 'dismiss_notice_mail_domain' ) );

			\add_action( 'wp_ajax_set_salt_key', array( Ajax_Helper::class, 'set_salt_key' ) );
			\add_action( 'wp_ajax_unset_salt_key', array( Ajax_Helper::class, 'unset_salt_key' ) );

			\add_action( 'wp_ajax_wp_2fa_get_all_users', array( Ajax_Helper::class, 'get_all_users' ) );
			\add_action( 'wp_ajax_wp_2fa_get_all_roles', array( Ajax_Helper::class, 'get_ajax_user_roles' ) );
			\add_action( 'wp_ajax_wp_2fa_get_all_network_sites', array( Ajax_Helper::class, 'get_all_network_sites' ) );
			\add_action( 'wp_ajax_unlock_account', array( Ajax_Helper::class, 'unlock_account' ), 10, 1 );
			\add_action( 'admin_action_unlock_account', array( Ajax_Helper::class, 'unlock_account' ), 10, 1 );
			\add_action( 'admin_action_remove_user_2fa', array( Ajax_Helper::class, 'remove_user_2fa' ), 10, 1 );
			\add_action( 'wp_ajax_remove_user_2fa', array( Ajax_Helper::class, 'remove_user_2fa' ), 10, 1 );
			\add_action( 'admin_menu', array( Settings_Page::class, 'hide_settings' ), 999 );
			\add_action( 'plugin_action_links_' . WP_2FA_BASE, array( Settings_Page::class, 'add_plugin_action_links' ) );
			\add_filter( 'display_post_states', array( Settings_Page::class, 'add_display_post_states' ), 10, 2 );
			\add_action( 'wp_ajax_send_authentication_setup_email', array( Setup_Wizard::class, 'send_authentication_setup_email' ) );
			\add_action( 'wp_ajax_send_backup_codes_email', array( Backup_Codes::class, 'send_backup_codes_email' ) );
			\add_action( 'wp_ajax_regenerate_authentication_key', array( TOTP::class, 'regenerate_authentication_key' ) );

			// User_Notices.
			\add_action( 'wp_ajax_dismiss_nag', array( User_Notices::class, 'dismiss_nag' ) );
			\add_action( 'wp_ajax_wp2fa_dismiss_reconfigure_nag', array( User_Notices::class, 'dismiss_nag' ) );
			\add_action( 'wp_logout', array( User_Notices::class, 'reset_nag' ), 10, 1 );

			// User_Profile.
			global $pagenow;
			if ( 'profile.php' !== $pagenow || 'user-edit.php' !== $pagenow ) {
				\add_action( 'show_user_profile', array( User_Profile::class, 'inline_2fa_profile_form' ) );
				\add_action( 'edit_user_profile', array( User_Profile::class, 'inline_2fa_profile_form' ) );
				if ( WP_Helper::is_multisite() ) {
					\add_action( 'personal_options_update', array( User_Profile::class, 'save_user_2fa_options' ) );
				}
			}
			\add_filter( 'user_row_actions', array( User_Profile::class, 'user_2fa_row_actions' ), 10, 2 );
			if ( WP_Helper::is_multisite() ) {
				\add_filter( 'ms_user_row_actions', array( User_Profile::class, 'user_2fa_row_actions' ), 10, 2 );
			}
			\add_action( 'wp_ajax_validate_authcode_via_ajax', array( User_Profile::class, 'validate_authcode_via_ajax' ) );
			\add_action( 'wp_ajax_wp2fa_test_email', array( Ajax_Helper::class, 'handle_send_test_email_ajax' ) );

			// Login.
			\add_action( 'wp_login', array( Login::class, 'wp_login' ), 20, 2 );
			\add_action( 'wp_loaded', array( Login::class, 'login_form_validate_2fa' ) );
			\add_action( 'login_form_validate_2fa', array( Login::class, 'login_form_validate_2fa' ) );
			\add_action( 'login_form_backup_2fa', array( Login::class, 'backup_2fa' ) );
			\add_action( 'login_enqueue_scripts', array( Login::class, 'dequeue_style' ), PHP_INT_MAX );

			// Reset password.
			\add_action( 'lostpassword_post', array( Reset_Password::class, 'lostpassword_post' ), 20, 2 );
			\add_action( 'login_form_lostpassword', array( Reset_Password::class, 'login_form_validate_2fa' ), 20 );
			// \add_action( 'wp_loaded', array( Reset_Password::class, 'login_form_validate_2fa' ) );.

			/**
			 * Keep track of all the user sessions for which we need to invalidate the
			 * authentication cookies set during the initial password check.
			 */
			\add_action( 'set_auth_cookie', array( Login::class, 'collect_auth_cookie_tokens' ) );
			\add_action( 'set_logged_in_cookie', array( Login::class, 'collect_auth_cookie_tokens' ) );

			// Run only after the core wp_authenticate_username_password() check.
			\add_filter( 'authenticate', array( Login::class, 'filter_authenticate' ), 50 );
			\add_filter( 'wp_authenticate_user', array( Login::class, 'run_authentication_check' ), 10, 2 );

			// User Register.
			\add_action( 'set_user_role', array( User_Registered::class, 'check_user_upon_role_change' ), 10, 3 );

			// User is removed from multisite.
			\add_action( 'remove_user_from_blog', array( User_Helper::class, 'remove_global_settings_hash_for_user' ) );

			// Block users from admin if needed.
			$user_block_hook = is_admin() || is_network_admin() ? 'init' : 'wp';
			\add_action( $user_block_hook, array( __CLASS__, 'block_unconfigured_users_from_admin' ), 10 );

			// Help & Contact Us.
			\add_action( WP_2FA_PREFIX . 'after_admin_menu_created', array( '\WP2FA\Admin\Help_Contact_Us', 'add_extra_menu_item' ) );

			// @free:start
			// Premium Features.
			\add_action( WP_2FA_PREFIX . 'after_admin_menu_created', array( Premium_Features::class, 'add_extra_menu_item' ) );
			\add_action( WP_2FA_PREFIX . 'before_plugin_settings', array( Premium_Features::class, 'add_settings_banner' ) );
			\add_action( 'admin_footer', array( Premium_Features::class, 'pricing_new_tab_js' ) );
			// @free:end

			\add_action( 'admin_footer', array( User_Profile::class, 'dismiss_nag_notice' ) );

			\add_action( WP_2FA_PREFIX . 'user_authenticated', array( __CLASS__, 'clear_user_after_login' ), 10, 1 );

			\add_filter( 'mepr-auto-login', array( Login::class, 'mepr_login' ) );
		}

		/**
		 * Add actions specific to the wizard.
		 *
		 * @since 2.0.0
		 */
		public static function add_wizard_actions() {
			if ( function_exists( 'wp_get_current_user' ) && \current_user_can( 'read' ) ) {
				\add_action( 'admin_init', array( '\WP2FA\Admin\Setup_Wizard', 'setup_page' ), 10 );
			}
		}

		/**
		 * Redirect user to 1st time setup.
		 *
		 * @since 2.0.0
		 */
		public static function setup_redirect() {

			// Bail early before the redirect if the user can't manage options.
			if ( wp_doing_ajax() && ! \current_user_can( 'manage_options' ) ) {
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

				$redirect = \add_query_arg(
					array(
						'page'             => 'wp-2fa-setup',
						'is_initial_setup' => 'true',
					),
					\network_admin_url( 'user-edit.php' )
				);

				\wp_safe_redirect( $redirect );
				exit();
			}
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
		 *
		 * @since 2.0.0
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
		 *
		 * @since 3.0.0
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
		 *
		 * @since 2.0.0
		 */
		public static function get_wp2fa_white_label_setting( $setting_name = '', $get_default_on_empty = false, $get_default_value = false ) {

			return (string) self::get_wp2fa_setting_generic( WP_2FA_WHITE_LABEL_SETTINGS_NAME, $setting_name, $get_default_on_empty, $get_default_value );
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
		 *
		 * @since 2.0.0
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
				return isset( $default_settings[ $setting_name ] ) ? $default_settings[ $setting_name ] : false;
			} elseif ( ! isset( $wp2fa_setting[ $setting_name ] ) ) {
				if ( true === $get_default_on_empty ) {
					if ( isset( $default_settings[ $setting_name ] ) ) {
						return $default_settings[ $setting_name ];
					} elseif ( isset( $default_settings[ $wp_2fa_setting ][ $setting_name ] ) ) {
						return $default_settings[ $wp_2fa_setting ][ $setting_name ];
					}
				}
				return false;
			} elseif ( WP_2FA_POLICY_SETTINGS_NAME === $wp_2fa_setting ) {

				/**
				 * Extensions could change the extracted value, based on custom / different / specific for role settings.
				 *
				 * @param mixed - Value of the setting.
				 * @param string - The name of the setting.
				 * @param string - The role name.
				 *
				 * @since 2.0.0
				 */
				return \apply_filters( WP_2FA_PREFIX . 'setting_generic', $wp2fa_setting[ $setting_name ], $setting_name, $role );
			} else {
				return $wp2fa_setting[ $setting_name ];
			}
		}

		/**
		 * Util function to grab EMAIL settings or apply defaults if no settings are saved into the db.
		 *
		 * @param  string $setting_name Settings to grab value of.
		 *
		 * @since 2.0.0
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

			$login_code_body  = '<p>' . \esc_html__( 'Hello {user_display_name},', 'wp-2fa' ) . '</p>';
			$login_code_body .= '<p>' . \esc_html__( 'You are trying to log in to {site_name} using the username {user_login_name}. To complete your login, please enter the following one-time 2FA code:', 'wp-2fa' ) . '</p>';
			$login_code_body .= '<p>' . \esc_html__( '{login_code}', 'wp-2fa' ) . '</p>';
			$login_code_body .= '<p>' . \esc_html__( 'Enter this code on the login page to finish the authentication process and access your account.', 'wp-2fa' ) . '</p>';
			$login_code_body .= '<p>' . \esc_html__( 'This request was made from IP address {user_ip_address}. If you did not request this, please contact the site administrator at {admin_email}.', 'wp-2fa' ) . '</p>';
			$login_code_body .= '<p>' . \esc_html__( 'If you encounter any other issues logging in, feel free to contact us at {admin_email}.', 'wp-2fa' ) . '</p>';
			$login_code_body .= '<p>' . \esc_html__(
				'Kind regards,
				The {site_name} Team',
				'wp-2fa'
			) . '</p>';


			$login_code_setup_subject = __( 'Your 2FA Setup Verification Code for {site_name}', 'wp-2fa' );

			$login_code_setup_body  = '<p>' . \esc_html__( 'Hello {user_display_name},', 'wp-2fa' ) . '</p>';
			$login_code_setup_body .= '<p>' . \esc_html__( 'You have requested to set up two-factor authentication for your user {user_login_name} on the website {site_name} ({site_url}).', 'wp-2fa' ) . '</p>';

			$login_code_setup_body .= '<p>' . sprintf(
			// translators: The login code provided from the plugin.
				\esc_html__( 'Please enter the following code to complete your setup: %1$1s', 'wp-2fa' ),
				'<strong>{login_code}</strong>'
			);
			$login_code_setup_body .= '</p>';
			$login_code_setup_body .= '<p>' . \esc_html__( 'This request was made from IP address {user_ip_address}. If you did not request this, please contact the site administrator at {admin_email}.', 'wp-2fa' ) . '</p>';
			$login_code_setup_body .= '<p>' . \esc_html__( 'Thank you.', 'wp-2fa' ) . '</p>';
			$login_code_setup_body .= '<p>' . \esc_html__( 'The {site_name} Team', 'wp-2fa' );
			$login_code_setup_body .= '</p>';

			// Create User Locked Message.
			$user_locked_subject = __( 'Your user on {site_name} has been locked', 'wp-2fa' );

			$user_locked_body  = '<p>' . \esc_html__( 'Hello.', 'wp-2fa' ) . '</p>';
			$user_locked_body .= '<p>' . sprintf(
			// translators: %1s - the name of the user
			// translators: %2s - the name of the site.
				\esc_html__( 'Since you have not enabled two-factor authentication for the user %1$1s on the website %2$2s within the grace period, your account has been locked.', 'wp-2fa' ),
				'{user_login_name}',
				'{site_name}'
			);
			$user_locked_body .= '</p>';
			$user_locked_body .= '<p>' . \esc_html__( 'Contact your website administrator to unlock your account.', 'wp-2fa' ) . '</p>';
			$user_locked_body .= '<p>' . \esc_html__( 'Thank you.', 'wp-2fa' ) . '</p>';

			// Create User unlocked Message.
			$user_unlocked_subject = __( 'Your user on {site_name} has been unlocked', 'wp-2fa' );
			$user_unlocked_body    = '';

			$user_unlocked_body .= '<p>' . __( 'Hello,', 'wp-2fa' ) . '</p><p>' . \esc_html__( 'Your user', 'wp-2fa' ) . ' <strong>{user_login_name}</strong> ' . \esc_html__( 'on the website', 'wp-2fa' ) . ' {site_url} ' . __( 'has been unlocked. Please configure two-factor authentication within the grace period, otherwise your account will be locked again.', 'wp-2fa' ) . '</p>';

			if ( ! empty( self::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
				$user_unlocked_body .= '<p>' . __( 'You can configure 2FA from this page:', 'wp-2fa' ) . ' <a href="{2fa_settings_page_url}" target="_blank">{2fa_settings_page_url}.</a></p>';
			}

			$user_unlocked_body .= '<p>' . __( 'Thank you.', 'wp-2fa' ) . '</p>';

			// Create User backup codes Message.
			$user_backup_codes_subject = __( '2FA backup codes for user {user_login_name} on {site_name}', 'wp-2fa' );
			$user_backup_codes_body    = '';

			$user_backup_codes_body .= '<p>' . __( 'Hello,', 'wp-2fa' ) . '</p><p>' . \esc_html__( 'Below please find the 2FA backup codes for your user', 'wp-2fa' ) . ' <strong>{user_login_name}</strong> ' . \esc_html__( 'on the website', 'wp-2fa' ) . ' <strong>{site_name}</strong>. ' . __( 'The website\'s URL is', 'wp-2fa' ) . ' {site_url} </p>';

			$user_backup_codes_body .= '{backup_codes}';

			$user_backup_codes_body .= '<p>' . __( 'Thank you for enabling 2FA on your account and helping us keeping the website secure.', 'wp-2fa' ) . '</p>';

			// Array of defaults, now we have things setup above.
			$default_settings = array(
				'email_from_setting'                  => 'use-defaults',
				'custom_from_email_address'           => '',
				'custom_from_display_name'            => '',
				'login_code_email_subject'            => $login_code_subject,
				'login_code_email_body'               => $login_code_body,
				'login_code_setup_email_subject'      => $login_code_setup_subject,
				'login_code_setup_email_body'         => $login_code_setup_body,
				'user_account_locked_email_subject'   => $user_locked_subject,
				'user_account_locked_email_body'      => $user_locked_body,
				'user_account_unlocked_email_subject' => $user_unlocked_subject,
				'user_account_unlocked_email_body'    => $user_unlocked_body,
				'user_backup_codes_email_subject'     => $user_backup_codes_subject,
				'user_backup_codes_email_body'        => $user_backup_codes_body,
				'send_account_locked_email'           => 'enable_account_locked_email',
				'send_account_unlocked_email'         => 'enable_account_unlocked_email',
				'send_login_code_email'               => 'enable_send_login_code_email',
				'send_reset_password_code_email'      => 'enable_send_reset_password_code_email',
			);

			/**
			 * Allows 3rd party providers to their own settings for the mail templates.
			 *
			 * @param array $default_settings - Array with the default settings.
			 *
			 * @since 2.0.0
			 */
			$default_settings = \apply_filters( WP_2FA_PREFIX . 'mail_default_settings', $default_settings );

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
		 *
		 * @since 2.0.0
		 */
		public static function replace_email_strings( $input = '', $user_id = '', $token = '', $override_grace_period = '' ) {

			if ( empty( $GLOBALS['wp_rewrite'] ) ) {
				$GLOBALS['wp_rewrite'] = new \WP_Rewrite(); // phpcs:ignore -- WordPress.WP.GlobalVariablesOverride.Prohibited
			}

			$token = trim( (string) $token );

			// Gather grace period.
			$grace_period_string = '';
			if ( isset( $override_grace_period ) && ! empty( $override_grace_period ) ) {
				$grace_period_string = sanitize_text_field( $override_grace_period );
			} else {
				$grace_policy        = self::get_wp2fa_setting( 'grace-policy' );
				$grace_period_string = Date_Time_Utils::format_grace_period_expiration_string( $grace_policy );
			}

			// Setup user data.
			if ( isset( $user_id ) && ! empty( $user_id ) ) {
				$user = get_userdata( intval( $user_id ) );
			} else {
				$user = wp_get_current_user();
			}

			// Setup token.
			if ( isset( $token ) && ! empty( $token ) ) {
				$login_code = $token;
			} else {
				$login_code = '';
			}

			$new_page_id = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'custom-user-page-id' );
			if ( ! empty( $new_page_id ) ) {
				$new_page_permalink = esc_url( \get_permalink( intval( $new_page_id ) ) );
			} else {
				$new_page_id = Settings::get_custom_settings_page_id( '', $user );
				if ( ! empty( $new_page_id ) ) {
					$new_page_permalink = esc_url( \get_permalink( intval( $new_page_id ) ) );
				} else {
					$new_page_permalink = '';
				}
			}

			$admin_email = null;
			if ( 'use-custom-email' === self::get_wp2fa_email_templates( 'email_from_setting' ) ) {
				$admin_email = sanitize_email( self::get_wp2fa_email_templates( 'custom_from_email_address' ) );
			} else {
				$admin_email = Settings_Page::get_default_email_address();
			}

			// These are the strings we are going to search for, as well as their respective replacements.
			$replacements = array(
				'{site_url}'              => \esc_url( \get_bloginfo( 'url' ) ),
				'{site_name}'             => \sanitize_text_field( \get_bloginfo( 'name' ) ),
				'{grace_period}'          => \sanitize_text_field( $grace_period_string ),
				'{user_login_name}'       => \sanitize_text_field( $user->user_login ),
				'{user_first_name}'       => \sanitize_text_field( $user->user_firstname ),
				'{user_last_name}'        => \sanitize_text_field( $user->user_lastname ),
				'{user_display_name}'     => \sanitize_text_field( $user->display_name ),
				'{login_code}'            => $login_code,
				'{2fa_settings_page_url}' => $new_page_permalink,
				'{user_ip_address}'       => esc_attr( Request_Utils::get_ip() ),
				'{admin_email}'           => $admin_email,
			);

			/**
			 * 3rd party plugins could change the mail strings, or provide their own.
			 *
			 * @param array $replacements - The array with all the currently supported strings.
			 */
			$replacements = \apply_filters(
				WP_2FA_PREFIX . 'replacement_email_strings',
				$replacements
			);

			$final_output = str_replace( array_keys( $replacements ), array_values( $replacements ), $input );
			return $final_output;
		}

		/**
		 * Util which contextualizes the wording 'reconfigure'/'configure' as needed.
		 *
		 * @param string     $input   - Text we are working on.
		 * @param int|string $user_id - User id, if its needed.
		 * @param string     $method_to_check - Name of the method to check for.
		 *
		 * @return string The output, with all the {strings} swapped out.
		 *
		 * @since 2.5.0
		 */
		public static function contextual_reconfigure_text( $input = '', $user_id = '', $method_to_check = '' ) {

			if ( empty( trim( (string) $input ) ) ) {
				return $input;
			}

			$enabled_method = User_Helper::get_enabled_method_for_user( intval( $user_id ) );

			$text = ( $enabled_method === $method_to_check ) ? \esc_html__( 'Reconfigure', 'wp-2fa' ) : \esc_html__( 'Configure', 'wp-2fa' );

			$replacements = array(
				'{reconfigure_or_configure_capitalized}' => $text,
				'{reconfigure_or_configure}'             => strtolower( $text ),
			);

			/**
			 * 3rd party plugins could change this to their own.
			 *
			 * @param array $replacements - The array with all the currently supported strings.
			 *
			 * @since 2.5.0
			 */
			$replacements = \apply_filters(
				WP_2FA_PREFIX . 'replacement_reconfigure_strings',
				$replacements
			);

			return str_replace( array_keys( $replacements ), array_values( $replacements ), \wp_kses_post( $input ) );
		}

		/**
		 * Util replace replace a placeholder with the actual remaining grace period for a user..
		 *
		 * @param string $input  -  Text we are working on.
		 * @param int    $grace_expiry  -  Expiration time.
		 *
		 * @return string The output, with all the {strings} swapped out.
		 *
		 * @since 2.5.0
		 */
		public static function replace_remaining_grace_period( $input = '', $grace_expiry = -1 ) {
			if ( empty( trim( (string) $input ) ) || empty( trim( (string) $grace_expiry ) ) ) {
				return sanitize_text_field( $input );
			}

			$replacements = array(
				'{grace_period_remaining}' => esc_attr( Date_Time_Utils::format_grace_period_expiration_string( null, intval( $grace_expiry ) ) ),
			);

			return str_replace( array_keys( $replacements ), array_values( $replacements ), ( $input ) );
		}

		/**
		 * Util which we use to replace our {strings} with actual, useful stuff.
		 *
		 * @param string  $input   Text we are working on.
		 * @param WP_User $user   The WP User.
		 *
		 * @return string          The output, with all the {strings} swapped out.
		 *
		 * @since 2.0.0
		 */
		public static function replace_wizard_strings( $input = '', $user = false ) {

			if ( ! $user ) {
				return sanitize_text_field( $input );
			}

			$available_methods = Methods::get_enabled_methods( User_Helper::get_user_role( $user ) );

			// These are the strings we are going to search for, as well as their respective replacements.
			$replacements = array(
				'{available_methods_count}' => intval( count( $available_methods[ User_Helper::get_user_role( $user ) ] ) ),
			);

			/**
			 * 3rd party plugins could change the mail strings, or provide their own.
			 *
			 * @param array $replacements - The array with all the currently supported strings.
			 */
			$replacements = \apply_filters(
				WP_2FA_PREFIX . 'replacement_wizard_strings',
				$replacements
			);

			$final_output = str_replace( array_keys( $replacements ), array_values( $replacements ), ( $input ) );
			return $final_output;
		}

		/**
		 * If a user is trying to access anywhere other than the 2FA config area, this blocks them.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function block_unconfigured_users_from_admin() {
			global $pagenow;

			$user = User_Helper::get_user();
			if ( 0 === $user->ID ) {
				return;
			}

			$redirect = true;

			if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) ) {
				if ( Extensions_Loader::use_proxytron() ) {
					$redirect = User_Licensing::enable_2fa_user_setting( true );
				}
			}


			if ( $redirect ) {
				$is_user_instantly_enforced = User_Helper::get_user_enforced_instantly();
				$grace_period_expiry_time   = (int) User_Helper::get_user_expiry_date();
				$time_now                   = time();
				if ( $is_user_instantly_enforced && ! empty( $grace_period_expiry_time ) && $grace_period_expiry_time < $time_now && ! User_Helper::is_excluded( $user->ID ) ) {

					$has_cap = true;
					if ( class_exists( 'WooCommerce', false ) ) {

						// Lets check if the user has the required capabilities to view the 2FA settings page (or profile page in the Admin section - dashboard).
						$has_cap = false;

						$access_caps = array( 'edit_posts', 'manage_woocommerce', 'view_admin_dashboard' );

						foreach ( $access_caps as $access_cap ) {
							if ( \current_user_can( $access_cap ) ) {
								$has_cap = true;
								break;
							}
						}
					}

					/**
					 * We should only allow:
					 * - 2FA setup wizard in the administration
					 * - custom 2FA page if enabled and created
					 * - AJAX requests originating from these 2FA setup UIs
					 */
					if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && self::action_check() ) { // phpcs:ignore
						return;
					}

					if ( \is_admin() || \is_network_admin() ) {
						$allowed_admin_page = 'profile.php';
						if ( $pagenow === $allowed_admin_page && ( isset( $_GET['show'] ) && 'wp-2fa-setup' === $_GET['show'] ) ) { // phpcs:ignore
							return;
						}
					}

					if ( is_page() ) {
						$custom_user_page_id = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'custom-user-page-id' );
						if ( ! empty( $custom_user_page_id ) && \get_the_ID() === (int) $custom_user_page_id ) {
							return;
						} else {
							$custom_user_page_id = Settings::get_custom_settings_page_id( '', $user );
							if ( ! empty( $custom_user_page_id ) && \get_the_ID() === (int) $custom_user_page_id ) {
								return;
							}
						}
					}

					// force a redirect to the 2FA set-up page if it exists.
					$custom_user_page_id = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'custom-user-page-id' );
					if ( ! empty( $custom_user_page_id ) ) {
						\wp_redirect( Settings::get_custom_page_link( $user ) );
						exit;
					} else {
						$custom_user_page_id = Settings::get_custom_settings_page_id( '', $user );
						if ( ! empty( $custom_user_page_id ) && \get_the_ID() === (int) $custom_user_page_id ) {
							\wp_redirect( \get_permalink( $custom_user_page_id ) );
							exit;
						}
					}

					// There is nowhere to redirect, so we have to fall back to the default which is the dashboard. If the user does not have the required capabilities to view the dashboard - lets stop the redirection.
					if ( ! $has_cap ) {

						// Is there WOO installed? If so, then lets try to extract the redirection rules from there.
						if ( class_exists( 'WooCommerce', false ) ) {

							// Lets check if there is a 2FA implemented within the WOOCommerce myaccount page.
							$items = \wc_get_account_menu_items();

							if ( isset( $items['wp-2fa'] ) ) {

								if ( ! isset( $_GET['wp-2fa'] ) ) {
									$url = \add_query_arg(
										array(
											'wp-2fa' => '',
										),
										\get_permalink( \get_option( 'woocommerce_myaccount_page_id' ) )
									);

									\wp_redirect( $url );

									exit;
								}
							}
						}


							// Nothing suitable found - notify the admin and bail.
							$transient_name = WP_2FA_PREFIX . '_notified_admin_mail_nowhere_to_redirect_' . $user->ID;
							if ( false === \get_transient( $transient_name ) ) {
								$subject = sprintf(
								// translators: The username.
									\esc_html__(
										'2FA not configured on enforced user – action required',
										'wp-2fa'
									),
									// $user->user_login,
								);

								$text = sprintf(
								// translators: The username.
								// translators: the site name.
									\esc_html__(
										'Two-factor authentication (2FA) is currently enforced for the user %1$s on your site %2$s, but the user was able to log in without completing the 2FA process.',
										'wp-2fa'
									),
									$user->user_login,
									\get_bloginfo( 'name' )
								);

								$text .= sprintf(
								// translators: The username.
								// translators: the site name.
									\esc_html__(
										'This can happen in cases such as:',
										'wp-2fa'
									)
								);

								$text .= '<p><ul>';
								$text .= '<li>' . \esc_html__( 'Automatic login after registration via WooCommerce or membership plugins', 'wp-2fa' ) . '</li>';
								$text .= '<li>' . \esc_html__( 'Users being logged in automatically after placing an order', 'wp-2fa' ) . '</li>';
								$text .= '<li>' . \esc_html__( '2FA not yet being configured for front-end login flows', 'wp-2fa' ) . '</li>';
								$text .= '</ul></p>';

								$text .= '<p>' . sprintf(
								// translators: the settings page.
								// translators: the support e-mail.
									\esc_html__(
										'To address this, please enable and configure the Front-end 2FA Page:%1$s',
										'wp-2fa'
									),
									'<br><a href="https://woo-wp2fa-test.instawp.xyz/wp-admin/admin.php?page=wp-2fa-settings&tab=integrations">' . \esc_html__( 'Configure Front-end 2FA Page', 'wp-2fa' ) . '</a>'
								) . '</p>';

								$text .= sprintf(
								// translators: The username.
								// translators: the site name.
									\esc_html__(
										'If you\'re using the Premium edition, you can take advantage of:',
										'wp-2fa'
									)
								);

								$text .= '<p><ul>';
								$text .= '<li>' . \esc_html__( 'One-click WooCommerce integration (adds 2FA setup to the “My Account” area)', 'wp-2fa' ) . '</li>';
								$text .= '<li>' . \esc_html__( 'Option to disable this type of notification email', 'wp-2fa' ) . '</li>';
								$text .= '<li>' . \esc_html__( 'Additional 2FA methods (e.g. SMS via Twilio/Clickatell, one-time email links, YubiKey, etc.)', 'wp-2fa' ) . '</li>';
								$text .= '<li>' . \esc_html__( 'A Trusted Devices (“Remember Me”) functionality to reduce 2FA prompts', 'wp-2fa' ) . '</li>';
								$text .= '</ul></p>';

								$text .= '<p>' . sprintf(
								// translators: the settings page.
								// translators: the support e-mail.
									\esc_html__(
										'If you need assistance with any of the setup, feel free to reach out to us at %1$s.',
										'wp-2fa'
									),
									'<a href="mailto:support@melapress.com">support@melapress.com</a>'
								) . '</p>';

								$text .= '<p>' . \esc_html__( 'Best Regards,', 'wp-2fa' ) . '<br>' . \esc_html__( 'The Melapress Team', 'wp-2fa' ) . '</p>';

								Settings_Page::send_email(
									\get_option( 'admin_email' ),
									$subject,
									$text
								);

								\set_transient( $transient_name, 'sent', DAY_IN_SECONDS * 2 );
							}


						return;
					}

					// custom 2FA page is not set-up, force redirect to the wizard in administration.
					\wp_redirect( Settings::get_setup_page_link() );
					exit;
				}
			}
		}

		/**
		 * Returns currently stored settings
		 *
		 * @return array
		 *
		 * @since 2.0.0
		 */
		public static function get_policy_settings() {
			/**
			 * Extensions could change the stored settings value, based on custom / different / specific for role settings.
			 *
			 * @param array - Value of the settings.
			 *
			 * @since 2.0.0
			 */
			$settings = \apply_filters( WP_2FA_PREFIX . 'policy_settings', self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] );

			return $settings;
		}

		/**
		 * Returns currently stored White Labeling settings
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function get_white_labeling_settings() {
			/**
			 * Extensions could change the stored settings value, based on custom / different / specific for role settings.
			 *
			 * @param array - Value of the settings.
			 *
			 * @since 3.0.0
			 */
			$settings = \apply_filters( WP_2FA_PREFIX . 'whitelabel_settings', self::$plugin_settings[ \WP_2FA_WHITE_LABEL_SETTINGS_NAME ] );

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
			if ( ! isset( $_REQUEST['action'] ) ) {
				return false;
			}
			$actions_array = array(
				'send_authentication_setup_email',
				'validate_authcode_via_ajax',
				'heartbeat',
				'regenerate_authentication_key',
				'send_backup_codes_email',
				'register_user_twilio',
				'register_user_clickatell',
			);

			/**
			 * Allows 3rd party providers to add their own actions to the check.
			 *
			 * @param array $actions_array - Array with the default actions.
			 *
			 * @since 2.0.0
			 */
			$actions_array = \apply_filters( WP_2FA_PREFIX . 'actions_check', $actions_array );

			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );

			return in_array( $action, $actions_array, true );
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
				// Create a hash for comparison when we interact with a user.
				$settings_hash = Settings_Utils::create_settings_hash( self::get_policy_settings() );
				Settings_Utils::update_option( WP_2FA_PREFIX . 'settings_hash', $settings_hash );
			}
		}

		/**
		 * Getter for the secret key of the plugin for the current instance
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

			$dismissed = (bool) Settings_Utils::get_option( 'remove_store_salt_in_wp_config_message', false );

			if ( ! File_Writer::can_write_to_file( File_Writer::get_wp_config_file_path() ) && ! $dismissed ) {
				$whitelist_admin_pages = array(
					'wp-2fa_page_wp-2fa-settings',
					'wp-2fa_page_wp-2fa-settings-network',
					'toplevel_page_wp-2fa-policies',
					'toplevel_page_wp-2fa-policies-network',
					'wp-2fa_page_wp-2fa-help-contact-us',
					'wp-2fa_page_wp-2fa-help-contact-us-network',
					'wp-2fa_page_wp-2fa-policies-account',
					'wp-2fa_page_wp-2fa-policies-account-network',
					'wp-2fa_page_wp-2fa-reports',
					'wp-2fa_page_wp-2fa-reports-network',
				);
				$admin_page            = \get_current_screen();
				if ( in_array( $admin_page->base, $whitelist_admin_pages, true ) ) {
					?>
					<div class="notice notice-warning" id="config-update-notice">
						<?php
						$message = sprintf(
							'<p>%1$s <a href="https://melapress.com/support/kb/wp-2fa-add-2fa-plugin-encryption-key-wp-config/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=encryption_key_in_wp_config" noopener target="_blank">%2$s</a><br>%3$s</p>',
							\esc_html__( 'For security reasons WP 2FA needs to store the private key in the wp-config.php file. However, it is unable to. This can happen because of restrictive permissions, or the file is not in the default location. To fix this you can:', 'wp-2fa' ) . '<br><br>' .
							\esc_html__( 'Option A) allow the plugin to write to the wp-config.php file temporarily by changing the wp-config.php permissions to 755. Once ready, click the button to proceed.', 'wp-2fa' ) . '<br>' .
							\esc_html__( 'Option B) Add the encryption key to the wp-config.php file yourself by ', 'wp-2fa' ),
							\esc_html__( 'following these instructions.', 'wp-2fa' ) . '<br>',
							\esc_html__( 'Once you complete any of the above, please click the button below.', 'wp-2fa' )
						);
						echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<p><button id="salt-update" type="button">
							<span><?php \esc_html_e( 'Write key to file now / Check for the key in file', 'wp-2fa' ); ?></span>
						</button><button style="margin-left: 15px;" id="dismiss-salt-update" type="button">
							<span><?php \esc_html_e( 'I am aware of the risks. Please do not alert me again about this.', 'wp-2fa' ); ?></span>
						</button></p>
					</div>
					<script>
					jQuery(document).ready(function($) {
						jQuery(document).on('click', '#salt-update', function(event) {
							event.preventDefault();
							const ajaxURL = (typeof wp2faWizardData !== "undefined") ? wp2faWizardData.ajaxURL : ajaxurl;
							const nonceValue = '<?php echo \esc_attr( \wp_create_nonce( 'wp-2fa-set-salt-nonce' ) ); ?>';
							jQuery.ajax({
								url: ajaxURL,
								method: 'POST',
								data: {
									action: 'set_salt_key',
									_wpnonce: nonceValue
								},
								success: function(data) {
									if (data.success) {
										jQuery('#config-update-notice').remove();
									} else {
										alert(data.data);
									}
								},
								error: function(data) {
									alert(data.responseJSON.data[0].message);
								}
							});
						});

						jQuery(document).on('click', '#dismiss-salt-update', function(event) {
							event.preventDefault();
							const ajaxURL = (typeof wp2faWizardData !== "undefined") ? wp2faWizardData.ajaxURL : ajaxurl;
							const nonceValue = '<?php echo \esc_attr( \wp_create_nonce( 'wp-2fa-unset-salt-nonce' ) ); ?>';
							jQuery.ajax({
								url: ajaxURL,
								method: 'POST',
								data: {
									action: 'unset_salt_key',
									_wpnonce: nonceValue
								},
								success: function(data) {
									if (data.success) {
										jQuery('#config-update-notice').remove();
									} else {
										alert(data.data);
									}
								},
								error: function(data) {
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
		 * Remove the user meta related with the code has been sent to the user.
		 * That is so we can lower the security by giving the option not to resend codes, so eventual brute force could succeed.
		 * The setting name - brute_force_disable
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		public static function clear_user_after_login() {
			User_Helper::remove_meta( WP_2FA_PREFIX . 'code_sent' );
		}

		/**
		 * Determine the plugin type.
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public static function determine_plugin_type(): string {

			if ( null === self::$plugin_type ) {

				self::$plugin_type = 'free';
				// Check if the plugin is a premium version.
				if ( class_exists( '\WP2FA\Extensions_Loader', false ) ) {
					self::$plugin_type = 'premium';
				}
			}

			return self::$plugin_type;
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
}
