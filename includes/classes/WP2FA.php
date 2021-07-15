<?php // phpcs:ignore

namespace WP2FA;

use WP2FA\Admin\User;
use WP2FA\Admin\UserListing;
use WP2FA\Admin\SettingsPage;
use WP2FA\Utils\DateTimeUtils;
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
	 * Options variables.
	 *
	 * @var array
	 */
	protected static $wp_2fa_options;
	protected static $wp_2fa_email_templates;

	/**
	 * Count of the available methods
	 *
	 * @var integer
	 */
	public static $methodsCount = 2;

	/**
	 * Instance wrapper.
	 *
	 * @var object
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
	 * Contructor.
	 */
	private function __construct() {
		self::$wp_2fa_options         = SettingsUtils::get_option( WP_2FA_SETTINGS_NAME );
		self::$wp_2fa_email_templates = SettingsUtils::get_option( WP_2FA_EMAIL_SETTINGS_NAME );

		/** We need to exclude all the possible ways, that logic to be executed by some WP request which could come from cron job or AJAX call, which will break the wizard (by storing the settings for the plugin) before it is completed by the user. We also have to check if the user is still processing first time wizard ($_GET parameter), and if the wizard has been finished already (wp_2fa_wizard_not_finished)  */
		if ( SettingsUtils::get_option( 'wizard_not_finished' ) && ! isset( $_GET['is_initial_setup'] ) && ! wp_doing_ajax() && ! defined( 'DOING_CRON' ) ) {

			if ( ! SettingsUtils::get_option( WP_2FA_SETTINGS_NAME ) ) {
				SettingsUtils::update_option( WP_2FA_SETTINGS_NAME, self::getDefaultSettings() );
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
			'excluded_users'                      => [],
			'excluded_roles'                      => [],
			'enforced_users'                      => [],
			'enforced_roles'                      => [],
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
			'included_sites'                      => [],
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
		);

		return $default_settings;
	}

	/**
	 * Fire up classes.
	 */
	public function init() {
		// Bootstrap.
		Core\setup();

		$this->settings        = new Admin\SettingsPage();
		$this->wizard          = new Admin\SetupWizard();
		$this->authentication  = new Authenticator\Authentication();
		$this->backupcodes     = new Authenticator\BackupCodes();
		$this->login           = new Authenticator\Login();
		$this->user_notices    = new Admin\UserNotices();
		$this->crontasks       = new Cron\CronTasks();
		$this->user_registered = new Admin\UserRegistered();
		$this->shortcodes      = new Shortcodes\Shortcodes();

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
		if ( empty( self::$wp_2fa_options ) || ! isset( self::$wp_2fa_options ) ) {
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
		add_action( 'wp_ajax_wp_2fa_cancel_bg_processes', array( $this->settings, 'cancel_bg_processes' ) );

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

		if ( SettingsUtils::get_option( 'redirect_on_activate', false ) ) {
			// Delete redirect option.
			SettingsUtils::delete_option( 'redirect_on_activate' );

			SettingsUtils::update_option( 'wizard_not_finished', true );

			$page = ( self::is_this_multisite() && is_super_admin() ) ?  network_admin_url( 'index.php' ) : admin_url( 'options-general.php' );
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
		$roles = $wp_roles->role_names;
		return $roles;
	}

	/**
	 * Check to see if the user or user role is excluded.
	 *
	 * @param  int $user_id User id.
	 * @return boolean Is user excluded or not.
	 */
	public static function is_user_excluded( $user_id, $excluded_users = '', $excluded_roles = '', $excluded_sites = '', $included_sites = [] ) {
		$user       = false;
		$user_roles = false;

		// If we have been passed an object, handle accordingly.
		if ( is_a( $user_id, '\WP_User' ) ) {
			$user       = $user_id;
			$user_roles = $user->roles;
		}

		// If we have an int instead, lets get the user data for that ID.
		if ( is_numeric( $user_id ) || isset( $_GET['user_id'] ) && is_numeric( $user_id ) ) {
			$user       = get_user_by( 'id', $user_id );
			$user_roles = $user->roles;
		}

		// Finally, we could have an array consisting of ID or user_login.
		if ( is_array( $user_id ) && isset( $user_id['ID'] ) ) {
			$user       = get_user_by( 'id', $user_id['ID'] );
			$user_roles = $user->roles;
		}

		// Finally, if we reach this point with no $user or $user_roles lets get the current user.
		if ( ! $user || ! $user_roles ) {
			$user       = wp_get_current_user();
			$user_roles = $user->roles;
		}

		$user_excluded = false;

		if ( isset( $excluded_users ) && ! empty( $excluded_users ) ) {
			$excluded_users = $excluded_users;
		} else {
			$excluded_users = WP2FA::get_wp2fa_setting( 'excluded_users' );
		}

		if ( ! empty( $excluded_users ) ) {
			// Turn it into an array.
			$excluded_users_array = $excluded_users;
			// Compare our roles with the users and see if we get a match.
			$result = in_array( $user->user_login, $excluded_users_array, true );
			if ( $result ) {
				$user_excluded = true;
				return true;
			}
		}

		if ( isset( $excluded_roles ) && ! empty( $excluded_roles )  ) {
			$excluded_roles = $excluded_roles;
		} else {
			$excluded_roles = WP2FA::get_wp2fa_setting( 'excluded_roles' );
		}

		if ( ! empty( $excluded_roles ) ) {
			// Turn it into an array.
			$excluded_roles_array = array_map('strtolower', $excluded_roles  );
			// Compare our roles with the users and see if we get a match.
			$result = array_intersect( $excluded_roles_array, $user->roles );
			if ( $result ) {
				$user_excluded = true;
				return true;
			}
		}

		if ( self::is_this_multisite() ) {
			if ( isset( $excluded_sites )  && ! empty( $excluded_sites ) ) {
				$excluded_sites = $excluded_sites;
			} else {
				$excluded_sites = WP2FA::get_wp2fa_setting( 'excluded_sites' );
			}

			if ( ! empty( $excluded_sites ) && is_array( $excluded_sites  ) ) {
				
				foreach ( $excluded_sites as $site_id ) {
					if ( is_user_member_of_blog( $user->ID, $site_id ) ) {
						// User is a member of the a blog we are excluding from 2FA.
						$user_excluded = true;
						return true;
					} else {
						// User is NOT a member of the a blog we are excluding.
						$user_excluded = false;
					}
				}
			}

			if ( ! isset( $included_sites ) || empty( $included_sites ) ) {
				$included_sites = WP2FA::get_wp2fa_setting( 'included_sites' );
			}

			foreach ( $included_sites as $siteId ) {
				if ( is_user_member_of_blog( $user->ID, $siteId ) ) {
					$user_excluded = false;
				}
			}
		}

		if ( true === $user_excluded ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if user is enforced
	 *
	 * @since 1.6
	 *
	 * @param [type] $user_id
	 * @param string $current_policy
	 * @param string $excluded_users
	 * @param string $excluded_roles
	 * @param string $enforced_users
	 * @param string $enforced_roles
	 *
	 * @return boolean
	 */
	public static function isUserEnforced( $user_id, $current_policy = '', $excluded_users = '', $excluded_roles = '', $enforced_users = '', $enforced_roles = '' ) {
		if ( isset( $_GET['user_id'] ) ) {
			$user_id    = (int) $_GET['user_id'];
			$user       = get_user_by( 'id', $user_id );
			$user_roles = $user->roles;
		} elseif ( isset( $user_id ) ) {
			$user       = get_user_by( 'id', $user_id );
			$user_roles = $user->roles;
		} else {
			$user       = wp_get_current_user();
			$user_roles = $user->roles;
		}

		if ( $current_policy ) {
			$current_policy = $current_policy;
		} else {
			$current_policy = self::get_wp2fa_setting( 'enforcement-policy' );
		}

		$enabled_method = get_user_meta( $user->ID, WP_2FA_PREFIX . 'enabled_methods', true );
		$user_eligable  = false;

		// Lets check the policy settings and if the user has setup totp/email by checking for the usermeta.
		if ( empty( $enabled_method ) && self::is_this_multisite() && 'superadmins-only' === $current_policy ) {
			return is_super_admin( $user->ID );
		} elseif ( empty( $enabled_method ) && self::is_this_multisite() && 'superadmins-siteadmins-only' === $current_policy ) {
			return is_super_admin( $user->ID ) || User::isAdminUser( $user->ID );
		} else if ( 'all-users' === $current_policy && empty( $enabled_method ) ) {

			if ( 'yes' === WP2FA::get_wp2fa_setting( 'superadmins-role-exclude' ) && is_super_admin( $user->ID ) ) {
				return false;
			}

			if ( isset( $excluded_users ) ) {
				$excluded_users = $excluded_users;
			} else {
				$excluded_users = self::get_wp2fa_setting( 'excluded_users' );
			}

			if ( ! empty( $excluded_users ) ) {
				// Turn it into an array.
				$excluded_users_array = explode( ',', $excluded_users );
				// Compare our roles with the users and see if we get a match.
				$result = in_array( $user->user_login, $excluded_users_array, true );
				if ( ! $result ) {
					$user_eligable = true;
				}
			}

			if ( isset( $excluded_roles ) ) {
				$excluded_roles = $excluded_roles;
			} else {
				$excluded_roles = self::get_wp2fa_setting( 'excluded_roles' );
			}

			if ( ! empty( $excluded_roles ) ) {
				// Turn it into an array.
				$excluded_roles_array = explode( ',', strtolower( $excluded_roles ) );
				// Compare our roles with the users and see if we get a match.
				$result = array_intersect( $excluded_roles_array, $user->roles );

				if ( ! empty( $result ) ) {
					$user_eligable = true;
				}

				if ( self::is_this_multisite() ) {
					$users_caps = array();
					$subsites   = get_sites();
					// Check each site and add to our array so we know each users actual roles.
					foreach ( $subsites as $subsite ) {
						$subsite_id   = get_object_vars( $subsite )['blog_id'];
						$users_caps[] = get_user_meta( $user->ID, 'wp_' .$subsite_id .'_capabilities', true  );
					}
					// Strip the top layer ready.
					$users_caps = $users_caps;
					foreach ( $users_caps as $key => $value ) {
						if ( ! empty( $value ) ) {
							foreach ( $value as $key => $value ) {
								$result = in_array( $key, $excluded_roles_array, true );
							}
						}
					}
					if ( ! empty( $result ) ) {
						return false;
					}
				}
			}

			if ( true === $user_eligable || empty( $enabled_method ) ) {
				return true;
			}
		} elseif ( 'certain-roles-only' === $current_policy && empty( $enabled_method ) ) {

			if ( isset( $enforced_users ) && ! empty( $enforced_users ) ) {
				$enforced_users = $enforced_users;
			} else {
				$enforced_users = self::get_wp2fa_setting( 'enforced_users' );
			}

			if ( ! empty( $enforced_users )) {
				// Turn it into an array.
				$enforced_users_array = $enforced_users;
				// Compare our roles with the users and see if we get a match.
				$result = in_array( $user->user_login, $enforced_users_array, true );
				// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
				if ( ! empty( $result ) ) {
					return true;
				}
			}

			if ( isset( $enforced_roles ) && ! empty( $enforced_roles ) ) {
				$enforced_roles = $enforced_roles;
			} else {
				$enforced_roles = self::get_wp2fa_setting( 'enforced_roles' );
			}

			if ( ! empty( $enforced_roles ) ) {
				// Turn it into an array.
				$enforced_roles_array = SettingsPage::extract_roles_from_input( $enforced_roles );
				// Compare our roles with the users and see if we get a match.
				$result = array_intersect( $enforced_roles_array, $user->roles );
				// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
				if ( ! empty( $result ) ) {
					return true;
				}

				if ( self::is_this_multisite() ) {
					$users_caps = array();
					$subsites   = get_sites();
					// Check each site and add to our array so we know each users actual roles.
					foreach ( $subsites as $subsite ) {
						$subsite_id   = get_object_vars( $subsite )['blog_id'];
						$users_caps[] = get_user_meta( $user->ID, 'wp_' .$subsite_id .'_capabilities', true  );
					}
					// Strip the top layer ready.
					$users_caps = $users_caps;
					foreach ( $users_caps as $key => $value ) {
						if ( ! empty( $value ) ) {
							foreach ( $value as $key => $value ) {
								$result = in_array( $key, $enforced_roles_array, true );
							}
						}
					}
					if ( ! empty( $result ) ) {
						return true;
					}
				}
			}

			if ( WP2FA::get_wp2fa_setting( 'superadmins-role-add' ) ) {
				return is_super_admin( $user->ID );
			}

		} elseif ( 'certain-users-only' === $current_policy && empty( $enabled_method ) ) {

			if ( isset( $enforced_users ) && ! empty( $enforced_users ) ) {
				$enforced_users = $enforced_users;
			} else {
				$enforced_users = self::get_wp2fa_setting( 'enforced_users' );
			}

			if ( ! empty( $enforced_users ) ) {
				// Turn it into an array.
				$enforced_users_array = explode( ',', $enforced_users );
				// Compare our roles with the users and see if we get a match.
				$result = in_array( $user->user_login, $enforced_users_array, true );
				// The user is one of the chosen roles we are forcing 2FA onto, so lets show the nag.
				if ( ! empty( $result ) ) {
					return true;
				}
			}
		} elseif ( 'enforce-on-multisite' === $current_policy ) {
			$includedSites = self::get_wp2fa_setting( 'included_sites' );

			foreach ( $includedSites as $site_id ) {
				if ( is_user_member_of_blog( $user_id, $site_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Util function to grab settings or apply defaults if no settings are saved into the db.
	 *
	 * @param  string $setting_name Settings to grab value of.
 	 * @param boolean $getDefaultOnEmpty return default setting value if current one is empty
	 * @param boolean $getDefaultValue return default value setting (ignore the stored ones)
	 * @return string               Settings value or default value.
	 */
	public static function get_wp2fa_setting( $setting_name = '', $getDefaultOnEmpty = false, $getDefaultValue = false ) {
		$default_settings = self::getDefaultSettings();

		if ( true === $getDefaultValue ) {
			if ( isset( $default_settings[ $setting_name ] ) ) {
				return $default_settings[ $setting_name ];
			}

			return false;
		}

		$apply_defaults = false;

		// If we have no setting name, return them all.
		if ( empty( $setting_name ) ) {
			return self::$wp_2fa_options;
		}

		// First lets check if any options have been saved.
		if ( empty( self::$wp_2fa_options ) || ! isset( self::$wp_2fa_options ) ) {
			$apply_defaults = true;
		}

		if ( $apply_defaults ) {
			return $default_settings[ $setting_name ];
		} elseif ( ! isset( self::$wp_2fa_options[ $setting_name ] ) ) {
			if ( true === $getDefaultOnEmpty ) {
				if ( isset( $default_settings[ $setting_name ] ) ) {
					return $default_settings[ $setting_name ];
				}
			}
			return false;
		} else {
			return self::$wp_2fa_options[ $setting_name ];
		}
	}

	/**
	 * Util function to grab EMAIL settings or apply defaults if no settings are saved into the db.
	 *
	 * @param  string $setting_name Settings to grab value of.
	 * @return string               Settings value or default value.
	 */
	public static function get_wp2fa_email_templates( $setting_name = '', $grab_default = '' ) {

		$apply_defaults = false;

		// First lets check if any options have been saved.
		if ( empty( self::$wp_2fa_email_templates ) || ! isset( self::$wp_2fa_email_templates ) ) {
			$apply_defaults = true;
		}

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

		if ( $apply_defaults || ! empty( $grab_default ) ) {
			return $default_settings[ $setting_name ];
		}
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

		$new_page_id = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
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
			'{login_code}'            => sanitize_text_field( $login_code ),
			'{2fa_settings_page_url}' => esc_url( $new_page_permalink ),
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

		if ( 'use-grace-period' !== WP2FA::get_wp2fa_setting( 'grace-policy' ) ) {
			$user                       = wp_get_current_user();
			$is_user_instantly_enforced = get_user_meta( $user->ID, WP_2FA_PREFIX . 'user_enforced_instantly', true );
			$grace_period_expiry_time   = get_user_meta( $user->ID, WP_2FA_PREFIX . 'grace_period_expiry', true );
			$time_now                   = time();
			if ( $is_user_instantly_enforced && ! empty( $grace_period_expiry_time ) && $grace_period_expiry_time < $time_now && ! WP2FA::is_user_excluded( $user->ID ) ) {

				/*
				 * We should only allow:
				 * - 2FA setup wizard in the administration
				 * - custom 2FA page if enabled and created
				 * - AJAX requests originating from these 2FA setup UIs
				 */
				if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], [ 'send_authentication_setup_email', 'validate_authcode_via_ajax', 'heartbeat' ] )) {
					return;
				}

				if ( is_admin() || is_network_admin() ) {
					$allowed_admin_page = 'profile.php';
					if ( $pagenow === $allowed_admin_page && ( isset( $_GET['show'] ) && $_GET['show'] === 'wp-2fa-setup' ) ) {
						return;
					}
				}

				if ( is_page() ) {
					$custom_user_page_id = \WP2FA\WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
					if ( !empty( $custom_user_page_id ) && get_the_ID() == (int) $custom_user_page_id ) {
						return;
					}
				}

				//  force a redirect to the 2FA set-up page if it exists
				$custom_user_page_id = \WP2FA\WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
				if ( !empty( $custom_user_page_id ) ) {
					wp_redirect( Settings::getCustomPageLink() );
					exit;
				}

				//  custom 2FA page is not set-up, force redirect to the wizard in administration
				wp_redirect( Settings::getSetupPageLink() );
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
	public function update_usermeta_if_required() {

		if ( wp_doing_ajax() || ! is_user_logged_in()) {
			return;
		}

		$user                  = wp_get_current_user();
		$users_settings_hash   = get_user_meta( $user->ID, WP_2FA_PREFIX . 'global_settings_hash', true );
		$current_settings_hash = SettingsUtils::get_option( 'settings_hash' );
		if ( $users_settings_hash !== $current_settings_hash ) {
			// Doing this envokes setUserPoliciesAndGrace and setGlobalSettingsHash in the User class.
			new \WP2FA\Admin\User();
		}
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
		$email_templates = $this->settings->get_email_notification_definitions();
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
	public static function getAllSettings() {
		return self::$wp_2fa_options;
	}
}
