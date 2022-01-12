<?php // phpcs:ignore

namespace WP2FA;

use WP2FA\Admin\User;
use WP2FA\Admin\UserListing;
use WP2FA\Admin\SettingsPage;
use WP2FA\Admin\HelpContactUs;
use WP2FA\Utils\RequestUtils;
use WP2FA\Utils\DateTimeUtils;
use WP2FA\Authenticator\Open_SSL;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Utils\SettingsUtils as SettingsUtils;

/**
 * Main WP2FA Class.
 */
class WP2FA {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = WP_2FA_VERSION;

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
	 * Count of the available methods
	 *
	 * @var integer
	 */
	public static $methodsCount = 3;

	/**
	 * Instance wrapper.
	 *
	 * @var WP2FA
	 */
	private static $instance = null;

	/**
	 * Holds array with all the sites in multisite WP installation
	 *
	 * @var array
	 */
	private static $sites = [];

	/**
	 * Return plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {

		self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] = SettingsUtils::get_option( WP_2FA_POLICY_SETTINGS_NAME );
		self::$plugin_settings[ WP_2FA_SETTINGS_NAME ] = SettingsUtils::get_option( WP_2FA_SETTINGS_NAME );
		self::$plugin_settings[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] = SettingsUtils::get_option( WP_2FA_WHITE_LABEL_SETTINGS_NAME );

		self::$wp_2fa_email_templates     = SettingsUtils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );

		/** We need to exclude all the possible ways, that logic to be executed by some WP request which could come from cron job or AJAX call, which will break the wizard (by storing the settings for the plugin) before it is completed by the user. We also have to check if the user is still processing first time wizard ($_GET parameter), and if the wizard has been finished already (wp_2fa_wizard_not_finished)  */
		if ( SettingsUtils::get_option( 'wizard_not_finished' ) && ! isset( $_GET['is_initial_setup'] ) && ! wp_doing_ajax() && ! defined( 'DOING_CRON' ) ) {

			if ( ! SettingsUtils::get_option( WP_2FA_SETTINGS_NAME ) ) {
				self::updatePluginSettings( self::getDefaultSettings() );
			}

			// Set a flag so we know we have default values present, not custom.
			SettingsUtils::update_option( 'default_settings_applied', true );
			SettingsUtils::delete_option( 'wizard_not_finished' );
		}

		// Activation/Deactivation.
		register_activation_hook( WP_2FA_FILE, '\WP2FA\Core\activate' );
		register_deactivation_hook( WP_2FA_FILE, '\WP2FA\Core\deactivate' );
		// Register our uninstallation hook.
		register_uninstall_hook( WP_2FA_FILE, '\WP2FA\Core\uninstall' );
	}

	public static function getDefaultSettings() {
		$default_settings = array(
			'enable_totp'                         => 'enable_totp',
			'enable_email'                        => 'enable_email',
			'backup_codes_enabled'                => 'yes',
			'enforcement-policy'                  => 'do-not-enforce',
			'excluded_users'                      => array(),
			'excluded_roles'                      => array(),
			'enforced_users'                      => array(),
			'enforced_roles'                      => array(),
			'grace-period'                        => 3,
			'grace-period-denominator'            => 'days',
			'enable_grace_cron'                   => '',
			'enable_destroy_session'              => '',
			'limit_access'                        => '',
			'2fa_settings_last_updated_by'        => '',
			'2fa_main_user'                       => '',
			'grace-period-expiry-time'            => '',
			'plugin_version'                      => WP_2FA_VERSION,
			'delete_data_upon_uninstall'          => '',
			'excluded_sites'                      => '',
			'included_sites'                      => array(),
			'create-custom-user-page'             => 'no',
			'redirect-user-custom-page'           => '',
			'redirect-user-custom-page-global'    => '',
			'custom-user-page-url'                => '',
			'custom-user-page-id'                 => '',
			'hide_remove_button'                  => '',
			'grace-policy'                        => 'use-grace-period',
			'superadmins-role-add'                => 'no',
			'superadmins-role-exclude'            => 'no',
			'default-text-code-page'              => __( 'Please enter the two-factor authentication (2FA) verification code below to login. Depending on your 2FA setup, you can get the code from the 2FA app or it was sent to you by email.', 'wp-2fa' ),
			'email-code-period'                   => 5,
			'specify-email_hotp'                  => '',
			'default-backup-code-page'            => __( 'Enter a backup verification code.', 'wp-2fa' ),
		);

		$default_settings = apply_filters( 'wp_2fa_default_settings', $default_settings );

		return $default_settings;
	}

	/**
	 * Fire up classes.
	 */
	public function init() {
		// Bootstrap.
		Core\setup();

		$this->settings        = new Admin\SettingsPage();
		$this->settings_email  = new Admin\SettingsPages\Settings_Page_Email();
		$this->wizard          = new Admin\SetupWizard();
		$this->authentication  = new Authenticator\Authentication();
		$this->backupcodes     = new Authenticator\BackupCodes();
		$this->login           = new Authenticator\Login();
		$this->user_notices    = new Admin\UserNotices();
		$this->crontasks       = new Cron\CronTasks();
		$this->user_registered = new Admin\UserRegistered();
		$this->shortcodes      = new Shortcodes\Shortcodes();
		$this->helpcontactus   = new Admin\HelpContactUs();
		$this->premiumfeatures = new Admin\PremiumFeatures();

		global $pagenow;
		if ( 'profile.php' !== $pagenow || 'user-edit.php' !== $pagenow ) {
			$this->user_profiles = new Admin\UserProfile();
		}

		if ( is_admin() ) {
			UserListing::init();

		}

		$this->add_actions();

	}

	/**
	 * Add our plugins actions.
	 */
	public function add_actions() {
		// Plugin redirect on activation, only if we have no settings currently saved.
		if ( ! isset( self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] ) || empty( self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] ) ) {
			add_action( 'admin_init', array( $this, 'setup_redirect' ), 10 );
		}

		// SettingsPage.
		if ( self::is_this_multisite() ) {
			add_action( 'network_admin_menu', array( $this->settings, 'create_settings_admin_menu_multisite' ) );
			add_action( 'network_admin_edit_update_wp2fa_network_options', array( $this->settings, 'update_wp2fa_network_options' ) );
			add_action( 'network_admin_edit_update_wp2fa_network_email_options', array( $this->settings, 'update_wp2fa_network_email_options' ) );
			add_action( 'network_admin_notices', array( $this->settings, 'settings_saved_network_admin_notice' ) );
		} else {
			add_action( 'admin_menu', array( $this->settings, 'create_settings_admin_menu' ) );
			add_action( 'admin_notices', array( $this->settings, 'settings_saved_admin_notice' ) );
		}
		add_action( 'wp_ajax_get_all_users', array( $this->settings, 'get_all_users' ) );
		add_action( 'wp_ajax_get_all_network_sites', array( $this->settings, 'get_all_network_sites' ) );
		add_action( 'wp_ajax_unlock_account', array( $this->settings, 'unlock_account' ), 10, 1 );
		add_action( 'admin_action_unlock_account', array( $this->settings, 'unlock_account' ), 10, 1 );
		add_action( 'admin_action_remove_user_2fa', array( $this->settings, 'remove_user_2fa' ), 10, 1 );
		add_action( 'wp_ajax_remove_user_2fa', array( $this->settings, 'remove_user_2fa' ), 10, 1 );
		add_action( 'admin_menu', array( $this->settings, 'hide_settings' ), 999 );
		add_action( 'plugin_action_links_' . WP_2FA_BASE, array( $this->settings, 'add_plugin_action_links' ) );
		add_filter( 'display_post_states',  array( $this->settings, 'add_display_post_states' ), 10, 2 );

		// SetupWizard.
		if ( self::is_this_multisite() ) {
			add_action( 'network_admin_menu', array( $this->wizard, 'network_admin_menus' ), 10 );
			add_action( 'admin_menu', array( $this->wizard, 'admin_menus' ), 10 );
		} else {
			add_action( 'admin_menu', array( $this->wizard, 'admin_menus' ), 10 );
		}
		add_action( 'plugins_loaded', array( $this, 'add_wizard_actions' ), 10 );
		add_action( 'wp_ajax_send_authentication_setup_email', array( $this->wizard, 'send_authentication_setup_email' ) );
		add_action( 'wp_ajax_regenerate_authentication_key', array( $this->wizard, 'regenerate_authentication_key' ) );

		// UserNotices.
		add_action( 'wp_ajax_dismiss_nag', array( $this->user_notices, 'dismiss_nag' ) );
		add_action( 'wp_ajax_wp2fa_dismiss_reconfigure_nag', array( $this->user_notices, 'dismiss_nag' ) );
		add_action( 'wp_logout', array( $this->user_notices, 'reset_nag' ), 10, 1 );

		// UserProfile.
		global $pagenow;
		if ( 'profile.php' !== $pagenow || 'user-edit.php' !== $pagenow ) {
			add_action( 'show_user_profile', array( $this->user_profiles, 'inline_2fa_profile_form' ) );
			add_action( 'edit_user_profile', array( $this->user_profiles, 'inline_2fa_profile_form' ) );
			if ( self::is_this_multisite() ) {
				add_action( 'personal_options_update', array( $this->user_profiles, 'save_user_2fa_options' ) );
			}
		}
		add_filter( 'user_row_actions', array( $this->user_profiles, 'user_2fa_row_actions' ), 10, 2 );
		if ( self::is_this_multisite() ) {
			add_filter( 'ms_user_row_actions', array( $this->user_profiles, 'user_2fa_row_actions' ), 10, 2 );
		}
		add_action( 'wp_ajax_validate_authcode_via_ajax', array( $this->user_profiles, 'validate_authcode_via_ajax' ) );
		add_action( 'wp_ajax_wp2fa_test_email', array( $this, 'handle_send_test_email_ajax' ) );

		// Login.
		add_action( 'init', array( $this->login, 'get_providers' ) );
		add_action( 'wp_login', array( $this->login, 'wp_login' ), 20, 2 );
		add_action( 'login_form_validate_2fa', array( $this->login, 'login_form_validate_2fa' ) );
		add_action( 'login_form_backup_2fa', array( $this->login, 'backup_2fa' ) );

		/**
		 * Keep track of all the user sessions for which we need to invalidate the
		 * authentication cookies set during the initial password check.
		 */
		add_action( 'set_auth_cookie', array( $this->login, 'collect_auth_cookie_tokens' ) );
		add_action( 'set_logged_in_cookie', array( $this->login, 'collect_auth_cookie_tokens' ) );

		// Run only after the core wp_authenticate_username_password() check.
		add_filter( 'authenticate', array( $this->login, 'filter_authenticate' ), 50 );
		add_filter( 'wp_authenticate_user', array( $this->login, 'run_authentication_check' ), 10, 2 );

		// User Register.
		add_action( 'set_user_role', array( $this->user_registered, 'check_user_upon_role_change' ), 10, 3 );

		// Block users from admin if needed.
		$user_block_hook = is_admin() || is_network_admin() ? 'init' : 'wp';
		add_action( $user_block_hook, array( $this,  'block_unconfigured_users_from_admin' ), 10 );
		// Check if usermeta is out of sync with settings.
		add_action( $user_block_hook, array( $this,  'update_usermeta_if_required' ), 5 );

		// Help & Contact Us.
		add_action( 'wp_2fa_after_admin_menu_created', array( $this->helpcontactus, 'add_extra_menu_item' ) );
		// Premium Features.
		add_action( 'wp_2fa_after_admin_menu_created', array( $this->premiumfeatures, 'add_extra_menu_item' ) );
	}

	/**
	 * Add actions specific to the wizard.
	 */
	public function add_wizard_actions() {
		if ( function_exists( 'wp_get_current_user' ) && current_user_can( 'read' ) ) {
			add_action( 'admin_init', array( $this->wizard, 'setup_page' ), 10 );
		}
	}

	/**
	 * Redirect user to 1st time setup.
	 */
	public function setup_redirect() {

		// Bail early before the redirect if the user can't manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$registered_and_active = 'yes';
		if ( function_exists( 'wp2fa_freemius' ) ) {
			$registered_and_active = wp2fa_freemius()->is_registered() && wp2fa_freemius()->has_active_valid_license() ? 'yes' : 'no';
		}

		if ( SettingsUtils::get_option( 'redirect_on_activate', false ) && 'yes' === $registered_and_active ) {
			// Delete redirect option.
			SettingsUtils::delete_option( 'redirect_on_activate' );

			SettingsUtils::update_option( 'wizard_not_finished', true );

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
	 * Check is this is a multisite setup.
	 */
	public static function is_this_multisite() {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * Return user roles.
	 *
	 * @return array User roles.
	 */
	public static function wp_2fa_get_roles() {
		global $wp_roles;
		return $wp_roles->role_names;
	}

	/**
	 * Util function to grab settings or apply defaults if no settings are saved into the db.
	 *
	 * @param  string $setting_name Settings to grab value of.
 	 * @param boolean $getDefaultOnEmpty return default setting value if current one is empty
	 * @param boolean $getDefaultValue return default value setting (ignore the stored ones)
	 * @return mixed               Settings value or default value.
	 */
	public static function get_wp2fa_setting( $setting_name = '', $getDefaultOnEmpty = false, $getDefaultValue = false, $role = 'global' ) {
		$role = is_null( $role ) ? 'global' : $role;
		return self::get_wp2fa_setting_generic( WP_2FA_POLICY_SETTINGS_NAME, $setting_name, $getDefaultOnEmpty, $getDefaultValue, $role );
	}

	/**
	 * Util function to grab settings or apply defaults if no settings are saved into the db.
	 *
	 * @param  string $setting_name Settings to grab value of.
 	 * @param boolean $getDefaultOnEmpty return default setting value if current one is empty
	 * @param boolean $getDefaultValue return default value setting (ignore the stored ones)
	 * @return mixed               Settings value or default value.
	 */
	public static function get_wp2fa_general_setting( $setting_name = '', $getDefaultOnEmpty = false, $getDefaultValue = false ) {

		return self::get_wp2fa_setting_generic( WP_2FA_SETTINGS_NAME, $setting_name, $getDefaultOnEmpty, $getDefaultValue );
	}

	/**
	 * Util function to grab white label settings or apply defaults if no settings are saved into the db.
	 *
	 * @param  string $setting_name Settings to grab value of.
 	 * @param boolean $getDefaultOnEmpty return default setting value if current one is empty
	 * @param boolean $getDefaultValue return default value setting (ignore the stored ones)
	 * @return string               Settings value or default value.
	 */
	public static function get_wp2fa_white_label_setting( $setting_name = '', $getDefaultOnEmpty = false, $getDefaultValue = false ) {

		return self::get_wp2fa_setting_generic( WP_2FA_WHITE_LABEL_SETTINGS_NAME, $setting_name, $getDefaultOnEmpty, $getDefaultValue );
	}

	private static function get_wp2fa_setting_generic( $wp_2fa_setting = WP_2FA_POLICY_SETTINGS_NAME, $setting_name = '', $get_default_on_empty = false, $get_default_value = false, $role = 'global' ) {
		$default_settings = self::getDefaultSettings();
		$role = is_null( $role ) ? 'global' : $role;

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
				return apply_filters( 'wp_2fa_setting_generic', $wp2fa_setting[ $setting_name ], $setting_name, $role );
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
				esc_html__( 'Enter %1$s to log in.', 'wp-2fa' ),
				'<strong>{login_code}</strong>'
			);
		$login_code_body .= '</p>';
		$login_code_body .= '<p>' . esc_html__( 'Thank you.', 'wp-2fa' ) . '</p>';
		$login_code_body .= '<p>' . esc_html__( 'Email sent by', 'wp-2fa' );
		$login_code_body .= ' <a href="https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/" target="_blank">' . esc_html__( 'WP 2FA plugin.', 'wp-2fa' ) . '</a>';
		$login_code_body .= '</p>';

		// Create User Locked Message.
		$user_locked_subject = __( 'Your user on {site_name} has been locked', 'wp-2fa' );

		$user_locked_body = '<p>' . esc_html__( 'Hello.', 'wp-2fa' ) . '</p>';
		$user_locked_body .= '<p>' . sprintf(
				esc_html__( 'Since you have not enabled two-factor authentication for the user %1$s on the website %2$s within the grace period, your account has been locked.', 'wp-2fa' ),
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

		$user_unlocked_body .= '<p>'. __( 'Hello,', 'wp-2fa' ) .'</p><p>'. esc_html__( 'Your user', 'wp-2fa' ) .' <strong>{user_login_name}</strong> '. esc_html__( 'on the website', 'wp-2fa' ) .' {site_url} '. __( 'has been unlocked. Please configure two-factor authentication within the grace period, otherwise your account will be locked again.', 'wp-2fa' ) .'</p>';

		if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
			$user_unlocked_body .= '<p>'. __( 'You can configure 2FA from this page:', 'wp-2fa' ) .' <a href="{2fa_settings_page_url}" target="_blank">{2fa_settings_page_url}.</a></p>';
		}

		$user_unlocked_body .='<p>'. __( 'Thank you.', 'wp-2fa' ) .'</p><p>'. __( 'Email sent by', 'wp-2fa' ) .' <a href="https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/" target="_blank">'. __( 'WP 2FA plugin', 'wp-2fa' ) .'</a></p>';

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
		$default_settings = apply_filters( 'wp_2fa_mail_default_settings', $default_settings );

		return $default_settings[ $setting_name ];
	}

	/**
	 * Util which we use to replace our {strings} with actual, useful stuff.
	 *
	 * @param  string $input   Text we are working on.
	 * @param  int|string    $user_id User id, if its needed.
	 * @param  string $token   Login code, if its needed..
	 * @return string          The output, with all the {strings} swapped out.
	 */
	public static function replace_email_strings( $input = '', $user_id = '', $token = '', $override_grace_period = '' ) {

		// Gather grace period.
		$grace_period_string = '';
		if ( isset( $override_grace_period ) && ! empty( $override_grace_period ) ) {
			$grace_period_string = $override_grace_period;
		} else {
			$grace_policy = self::get_wp2fa_setting( 'grace-policy' );
			$grace_period_string = DateTimeUtils::format_grace_period_expiration_string( $grace_policy );
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
			'{user_first_name}'       => sanitize_text_field( $user->firstname ),
			'{user_last_name}'        => sanitize_text_field( $user->lastname ),
			'{user_display_name}'     => sanitize_text_field( $user->display_name ),
			'{login_code}'            => $login_code,
			'{2fa_settings_page_url}' => esc_url( $new_page_permalink ),
			'{user_ip_address}'       => RequestUtils::get_ip(),
		);

		$replacements = apply_filters(
			'wp_2fa_replacement_email_strings',
			$replacements
		);

		$final_output = str_replace( array_keys( $replacements ), array_values( $replacements ), $input );
		return $final_output;
	}

	/**
	 * If a user is trying to access anywhere other than the 2FA config area, this blocks them.
	 */
	public function block_unconfigured_users_from_admin() {
		global $pagenow;

		$user = User::get_instance();
		$is_user_instantly_enforced = $user->getEnforcedInstantly();
		$grace_period_expiry_time   = $user->getGracePeriodExpiration();
		$time_now                   = time();
		if ( $is_user_instantly_enforced && ! empty( $grace_period_expiry_time ) && $grace_period_expiry_time < $time_now && ! User::is_excluded( $user->getUser()->ID ) ) {

			/**
			 * We should only allow:
			 * - 2FA setup wizard in the administration
			 * - custom 2FA page if enabled and created
			 * - AJAX requests originating from these 2FA setup UIs
			 */
			if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && self::action_check() ) {
				return;
			}

			if ( is_admin() || is_network_admin() ) {
				$allowed_admin_page = 'profile.php';
				if ( $pagenow === $allowed_admin_page && ( isset( $_GET['show'] ) && $_GET['show'] === 'wp-2fa-setup' ) ) {
					return;
				}
			}

			if ( is_page() ) {
				$custom_user_page_id = Settings::get_role_or_default_setting( 'custom-user-page-id', $user->getUser() );
				if ( ! empty( $custom_user_page_id ) && get_the_ID() == (int) $custom_user_page_id ) {
					return;
				}
			}

			// force a redirect to the 2FA set-up page if it exists.
			$custom_user_page_id = Settings::get_role_or_default_setting( 'custom-user-page-id', $user->getUser() );
			if ( ! empty( $custom_user_page_id ) ) {
				wp_redirect( Settings::get_custom_page_link( $user->getUser() ) );
				exit;
			}

			// custom 2FA page is not set-up, force redirect to the wizard in administration.
			wp_redirect( Settings::get_setup_page_link() );
			exit;
		}
	}

	/**
	 * Checks if user's settings hash matches the current one, and if not, updates it.
	 *
	 * @return void
	 * @since 1.7.0
	 */
	public function update_usermeta_if_required() {
		if ( wp_doing_ajax() || ! is_user_logged_in()) {
			return;
		}

		//  doing this invokes update of necessary user metadata in the User class.
		User::get_instance();
	}

	/**
	 * Handles AJAX calls for sending test emails.
	 */
	public function handle_send_test_email_ajax() {

		//  check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		//  check email id
		$email_id = filter_input(INPUT_POST, 'email_id', FILTER_SANITIZE_STRING);
		if ($email_id === null || $email_id === false) {
			wp_send_json_error();
		}

		//  check nonce
		$nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
		if ($nonce === null || $nonce === false || ! wp_verify_nonce($nonce, 'wp-2fa-email-test-' . $email_id)) {
			wp_send_json_error();
		}

		$user_id = get_current_user_id();
		// Grab user data.
		$user = get_userdata( $user_id );
		// Grab user email.
		$email = $user->user_email;

		if ('config_test' === $email_id) {
			$email_sent = SettingsPage::send_email(
				$email,
				esc_html__('Test email from WP 2FA', 'wp-2fa'),
				esc_html__('This email was sent by the WP 2FA plugin to test the email delivery.', 'wp-2fa')
			);
			if ( $email_sent ) {
				wp_send_json_success('Test email was successfully sent to <strong>' . $email . '</strong>' );
			}

			wp_send_json_error();
		}

		/** @var EmailTemplate[] $email_templates */
		$email_templates = $this->settings_email->get_email_notification_definitions();
		foreach ($email_templates as $email_template) {
			if ($email_id === $email_template->getEmailContentId()) {
				//  send the test email



				// Setup the email contents.
				$subject = wp_strip_all_tags( WP2FA::get_wp2fa_email_templates( $email_id . '_email_subject' ) );
				$message = wpautop( WP2FA::get_wp2fa_email_templates( $email_id . '_email_body' ), $user_id );

				$email_sent = SettingsPage::send_email( $email, $subject, $message );
				if ( $email_sent ) {
					wp_send_json_success('Test email <strong>' . $email_template->getTitle() . '</strong> was successfully sent to <strong>' . $email . '</strong>' );
				}

				wp_send_json_error();
			}
		}
	}

	/**
	 * Collects all the sites from multisite WP installation
	 *
	 * @return array
	 */
	public static function getMultiSites() {
		if ( self::is_this_multisite() ) {
			if ( empty( self::$sites ) ) {

				self::$sites = \get_sites();
			}

			return self::$sites;
		}

		return [];
	}

	/**
	 * Returns text with the number of plugins supported
	 *
	 * @since 1.6
	 *
	 * @return string
	 */
	public static function getNumberOfPluginsText() {
		$methodsCount = self::$methodsCount;

		if ( \class_exists('NumberFormatter') ) {
			$number_formatter = new \NumberFormatter( get_locale(), \NumberFormatter::SPELLOUT );
			$methodsCount = $number_formatter->format( self::$methodsCount );
		}

		return
			sprintf(
				\_n(
					'There is %s method available from which you can choose for 2FA:',
					'There are %s methods available from which you can choose for 2FA:',
					self::$methodsCount,
					'wp-2fa'
				),
				$methodsCount
			);
	}

	/**
	 * Returns currently stored settings
	 *
	 * @return void
	 */
	public static function getPolicySettings() {
		/**
		 * Extensions could change the stored settings value, based on custom / different / specific for role settings.
		 *
		 * @param array - Value of the settings.
		 *
		 * @since 2.0.0
		 */
		$settings = apply_filters( 'wp_2fa_policy_settings', self::$plugin_settings[ WP_2FA_POLICY_SETTINGS_NAME ] );

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
		$actions_array = array(
			'send_authentication_setup_email',
			'validate_authcode_via_ajax',
			'heartbeat',
		);

		/**
		 * Allows 3rd party providers to their own settings for the mail templates.
		 *
		 * @param array $actions_array - Array with the default settings.
		 *
		 * @since 2.0.0
		 */
		$actions_array = apply_filters( 'wp_2fa_actions_check', $actions_array );

		return in_array( $_REQUEST['action'], $actions_array );
	}

	/**
	 * Updates the plugin settings, the settings hash in the database as well as a local (cached) copy of the settings.
	 *
	 * @param array $settings
	 * @param bool $skip_option_save If true, the settings themselves are not saved. This is needed when saving settings from settings page as WordPress options API takes care of that.
	 *
	 * @since 2.0.0
	 */
	public static function updatePluginSettings( $settings, $skip_option_save = false, $settings_name=WP_2FA_POLICY_SETTINGS_NAME ) {
		// update local copy of settings.
		self::$plugin_settings[ $settings_name ] = $settings;

		if ( ! $skip_option_save ) {
			// update the database option itself.
			SettingsUtils::update_option( $settings_name, $settings );
		}

		if ( WP_2FA_POLICY_SETTINGS_NAME === $settings_name ) {
			// Create a hash for comparison when we interact with a use.
			$settings_hash = SettingsUtils::create_settings_hash( self::getPolicySettings() );
			SettingsUtils::update_option( WP_2FA_PREFIX . 'settings_hash', $settings_hash );
		}
	}

	/**
	 * Getter for the TOTP secret key of the plugin for the current instance
	 *
	 * @return string
	 *
	 * @since 2.0.0
	 */
	public static function get_secret_key() {
		if ( null === self::$secret_key ) {
			self::$secret_key = SettingsUtils::get_option('secret_key');
			if ( empty( self::$secret_key ) ) {
				self::$secret_key = base64_encode( Open_SSL::secure_random() );
				SettingsUtils::update_option( 'secret_key', self::$secret_key );
			}
		}

		return self::$secret_key;
	}

	/**
	 * Returns message for the WP Mail SMTP plugin usage suggestion
	 *
	 * @return string
	 *
	 * @since 2.0.0
	 */
	public static function print_email_deliverability_message() {

		return sprintf( '%1$s <a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">%2$s</a>.', esc_html__( 'To ensure emails are delivered so users do not have problems logging in, we recommend using the free plugin', 'wp-2fa' ), esc_html__( 'WP Mail SMTP', 'wp-2fa' ) );
	}
}
