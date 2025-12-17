<?php
/**
 * Responsible for the Passkeys extension plugin settings
 *
 * @package    wp2fa
 * @subpackage passkeys
 * @since 3.0.0
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Methods;

use WP2FA\WP2FA;
use WP2FA\Admin\User_Profile;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Passkeys\Ajax_Passkeys;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Passkeys\PassKeys_Endpoints;
use WP2FA\Admin\Methods\Traits\Providers;
use WP2FA\Passkeys\Passkeys_User_Profile;
use WP2FA\Extensions\EmailBackup\Email_Backup;
use WP2FA\Methods\Wizards\PassKeys_Wizard_Steps;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Passkeys settings class
 */
if ( ! class_exists( '\WP2FA\Methods\Passkeys' ) ) {

	/**
	 * Responsible for setting different 2FA Passkeys settings
	 *
	 * @since 3.0.0
	 */
	class Passkeys {

		use Providers;

		public const PASSKEY_DIR = 'includes' . \DIRECTORY_SEPARATOR . 'classes' . \DIRECTORY_SEPARATOR . 'Admin' . \DIRECTORY_SEPARATOR . 'Methods' . \DIRECTORY_SEPARATOR . 'passkeys';

		public const METHOD_NAME          = 'passkeys';
		public const POLICY_SETTINGS_NAME = 'enable_passkeys';

		public const USER_PROFILE_JS_MODULE = 'wp_2fa_passkeys_user_profile';
		public const USER_LOGIN_JS_MODULE   = 'wp_2fa_passkeys_user_login';

		/**
		 * Is the passkeys method enabled
		 *
		 * @since 3.0.0
		 *
		 * @var bool
		 */
		private static $enabled = null;

		/**
		 * Inits the main Passkeys class and sets the methods.
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function init() {

			self::always_init();

			\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
			\add_action( WP_2FA_PREFIX . 'shortcode_scripts', array( __CLASS__, 'enqueue_scripts' ), 10, 1 );

			\add_filter( 'script_loader_tag', array( __CLASS__, 'set_script_type_attribute' ), 10, 3 );

			\add_filter( WP_2FA_PREFIX . 'providers_translated_names', array( __CLASS__, 'passkeys_provider_name_translated' ) );

			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );

			\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'settings_loop' ), 10, 1 );

			\add_filter( WP_2FA_PREFIX . 'no_method_enabled', array( __CLASS__, 'return_default_selection' ), 10, 1 );

			\add_filter( WP_2FA_PREFIX . 'save_additional_enabled_methods', array( __CLASS__, 'method_enabled' ), 10, 2 );

			\add_filter( WP_2FA_PREFIX . 'enable_2fa_user_setting', array( __CLASS__, 'enable_2fa_user_setting' ), 10, 3 );

			// Login forms.

			\add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login_scripts' ) );

			\add_action( 'woocommerce_login_form_end', array( __CLASS__, 'woocommerce_login_form' ) );

			\add_action( 'login_form', array( __CLASS__, 'add_to_admin_login_page' ) );

			// \add_action( WP_2FA_PREFIX . 'white_label_wizard_options', array( __CLASS__, 'white_label_option_labels' ) );

			\add_filter( WP_2FA_PREFIX . 'white_label_default_settings', array( __CLASS__, 'add_whitelabel_settings' ) );

			\add_filter( WP_2FA_PREFIX . 'method_settings_disabled', array( __CLASS__, 'disable_email_backup' ), 10, 3 );

			\add_filter( WP_2FA_PREFIX . 'before_settings_save_roles', array( __CLASS__, 'set_this_method' ), 10, 1 );

			\add_filter( WP_2FA_PREFIX . 'before_settings_save', array( __CLASS__, 'before_settings_save' ), 10, 1 );

			\add_filter( WP_2FA_PREFIX . 'before_remove_role', array( __CLASS__, 'set_this_method' ), 10, 1 );

			// Login forms end.

			// add the Passkeys methods to the list of available methods if enabled.
			\add_filter(
				WP_2FA_PREFIX . 'available_2fa_methods',
				function ( $available_methods ) {
					if ( ! empty( Settings_Utils::get_setting_role( null, self::POLICY_SETTINGS_NAME ) ) ) {
						array_push( $available_methods, self::METHOD_NAME );
					}

					return $available_methods;
				}
			);

			PassKeys_Wizard_Steps::init();

			PassKeys_Endpoints::init();

			Passkeys_User_Profile::init();

			Ajax_Passkeys::init();
		}

		/**
		 * Responsible for providing proper assets on the user profile page.
		 *
		 * @param bool $shortcodes - Whether the scripts are being loaded for shortcodes.
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function enqueue_scripts( $shortcodes = false ) {
			if ( ! \is_admin() && \function_exists( 'is_user_logged_in' ) && ! \is_user_logged_in() ) {
				return;
			}
			global $current_screen;

			$woo = '';
			if ( function_exists( 'wc_get_page_id' ) ) {
				$woo = (string) \get_permalink( \wc_get_page_id( 'myaccount' ) );
			}

			if ( ! isset( $current_screen->id ) || ( isset( $current_screen->id ) && ! in_array(
				$current_screen->id,
				array(
					'wp-2fa_page_wp-2fa-settings',
					'toplevel_page_wp-2fa-policies',
				),
				true
			) ) ) {
				// if ( ! self::is_set() ) {
				// return;
				// }
			}

			if ( ( ( isset( $current_screen->id ) && in_array(
				$current_screen->id,
				array(
					'wp-2fa_page_wp-2fa-settings',
					'toplevel_page_wp-2fa-policies',
					'profile',
					'profile-network',
				),
				true
			) ) || true === $shortcodes )
			|| ( isset( $_GET['is_initial_setup'] ) && 'true' === $_GET['is_initial_setup'] ) // phpcs:ignore
			|| ( isset( $_SERVER['SCRIPT_NAME'] ) && false !== stripos( \wp_login_url(), \sanitize_text_field( \wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) )
			|| ( isset( $_SERVER['REQUEST_URI'] ) && false !== stripos( \wp_login_url(), \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) )
			|| ( isset( $_SERVER['REQUEST_URI'] ) && false !== stripos( $woo, \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) )
			) {
				if ( false === Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'disable_rest' ) ) ) {
					\wp_enqueue_script(
						self::USER_PROFILE_JS_MODULE,
						\trailingslashit( WP_2FA_URL ) . \trailingslashit( self::PASSKEY_DIR ) . 'assets/js/user-profile.js',
						array( 'wp-api-fetch', 'wp-dom-ready', 'wp-i18n' ),
						WP_2FA_VERSION,
						array( 'in_footer' => true )
					);
				} else {
					\wp_enqueue_script(
						self::USER_PROFILE_JS_MODULE,
						\trailingslashit( WP_2FA_URL ) . \trailingslashit( self::PASSKEY_DIR ) . 'assets/js/user-profile-ajax.js',
						array( 'jquery', 'wp-dom-ready', 'wp-i18n' ),
						WP_2FA_VERSION,
						array( 'in_footer' => true )
					);
				}
			}
		}

		/**
		 * Adds login scripts to the login page
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function enqueue_login_scripts() {
			if ( \is_user_logged_in() || ! self::is_globally_enabled() ) {
				return;
			}
			if ( false === Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'disable_rest' ) ) ) {
				\wp_enqueue_script(
					self::USER_LOGIN_JS_MODULE,
					\trailingslashit( WP_2FA_URL ) . \trailingslashit( self::PASSKEY_DIR ) . 'assets/js/user-login.js',
					array( 'wp-api-fetch', 'wp-dom-ready' ),
					WP_2FA_VERSION,
					array( 'in_footer' => true )
				);
			} else {
				\wp_enqueue_script(
					self::USER_LOGIN_JS_MODULE,
					\trailingslashit( WP_2FA_URL ) . \trailingslashit( self::PASSKEY_DIR ) . 'assets/js/user-login-ajax.js',
					array( 'jquery', 'wp-dom-ready' ),
					WP_2FA_VERSION,
					array( 'in_footer' => true )
				);

				$variables = array(
					'ajaxurl' => \admin_url( 'admin-ajax.php' ),
				);
				\wp_localize_script( self::USER_LOGIN_JS_MODULE, 'login', $variables );
			}
		}

		/**
		 * Add `type="module"` to a script tag
		 *
		 * @param string $tag           - Original script tag.
		 * @param string $handle        - Handle of the script that's currently being filtered.
		 * @param string $src  - Script src attribut.
		 *
		 * @return string Script tag with attribute `type="module"` added.
		 *
		 * @since 3.0.0
		 */
		public static function set_script_type_attribute( string $tag, string $handle, string $src ): string {
			if ( self::USER_PROFILE_JS_MODULE !== $handle && self::USER_LOGIN_JS_MODULE !== $handle ) {
				return $tag;
			}

			$tag = '<script type="module" src="' . esc_url( $src ) . '" id="' . self::USER_PROFILE_JS_MODULE . '"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

			return $tag;
		}

		/**
		 * Adds passkeys provider translatable name
		 *
		 * @param array $providers - Array with all currently supported providers and their translated names.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function passkeys_provider_name_translated( array $providers ) {
			$providers[ self::METHOD_NAME ] = WP2FA::get_wp2fa_white_label_setting( 'passkeys-option-label', true );

			return $providers;
		}

		/**
		 * Adds the extension default settings to the main plugin settings
		 *
		 * @param array $default_settings - array with plugin default settings.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function add_default_settings( array $default_settings ) {
			$default_settings[ self::POLICY_SETTINGS_NAME ] = false; // self::POLICY_SETTINGS_NAME;

			return $default_settings;
		}

		/**
		 * Add extension settings to the loop array
		 *
		 * @param array $loop_settings - Currently available settings array.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function settings_loop( array $loop_settings ) {
			array_push( $loop_settings, self::POLICY_SETTINGS_NAME );
			array_push( $loop_settings, self::POLICY_SETTINGS_NAME . '_certain_roles' );

			return $loop_settings;
		}

		/**
		 * Shows the passkeys use button to the main WP login form
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function add_to_admin_login_page() {
			if ( \is_user_logged_in() || ! self::is_globally_enabled() ) {
				return;
			}

			echo self::load_style(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			
			<div class="wp-2fa-login-wrapper" id="wp-2fa-login-wrapper">
				<div id="errorMessage" class="notice notice-error" style="display: none;"></div>
				<div id="successMessage" class="notice notice-success" style="display: none;"></div>
				<button type="button" id="wp-2fa-login-via-passkey" class="button button-large wp-2fa-login-via-passkey">
					<span id="buttonText"><?php esc_html_e( 'Log in with a passkey', 'wp-2fa' ); ?></span>
				</button>
				<div style="display: none;margin-top: 10px;" id="wp-2fa-standard-login-wrapper">
					<button type="button" id="wp-2fa-login-standard" class="button button-large wp-2fa-login-standard">
						<span id="buttonText"><?php esc_html_e( 'Log in with your username and password', 'wp-2fa' ); ?></span>
					</button>
				</div>
			</div>
			<?php
		}

		/**
		 * Loads the login form stylesheet
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public static function load_style(): string {
			\ob_start();
			?>
			<style>
				/** Default */
				.wp-2fa-login-wrapper {
					text-align: center;
					display: block;
					border-top: 1px solid #ccc;
					padding: 20px 0;
				}

				.wp-2fa-login-wrapper .notice-error {
					border-left-color: #d63638;
					border-right-color: #d63638;
				}

				.wp-2fa-login-wrapper .notice-success {
					border-left-color: #358f49;
					border-right-color: #358f49;
				}

				/** Short Code */
				.wp-2fa-login-short-code-wrapper .wp-2fa-login-via-passkey {
					display: inline-block;
					font-weight: 400;
					color: #1e1e1e;
					text-align: center;
					vertical-align: middle;
					user-select: none;
					background-color: #fff;
					border: 1px solid #e9e9e9;
					padding: 0.375rem 0.75rem;
					font-size: 1rem;
					line-height: 1.5;
					transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
				}

				.wp-2fa-login-wrapper .notice {
					text-align: initial;
				}

				.wp-2fa-login-short-code-wrapper .notice-success {
					color: #358f49;
					background-color: #f3fff6;
					border: 1px solid #d6f3dd;
					padding: 10px;
					border-radius: 5px;
					margin: 15px 0;
					font-size: 13px;
				}

				.wp-2fa-login-short-code-wrapper .notice-error {
					color: #721c24;
					background-color: #fdf1f2;
					border: 1px solid #ffdbdf;
					padding: 10px;
					border-radius: 5px;
					margin: 15px 0;
					font-size: 13px;
				}

				.wp-2fa-login-short-code-wrapper .wp-2fa-login-via-passkey:hover {
					background-color: #e2e2e2;
					border-color: #e2e2e2;
				}

				.wp-2fa-login-short-code-wrapper .wp-2fa-login-via-passkey:disabled {
					opacity: 0.65;
					cursor: not-allowed;
				}

				/** WooCommerce */
				.wp-2fa-login-woocommerce-wrapper {
					text-align: center;
					margin-top: 50px;
					display: block;
					border-top: 1px solid #ccc;
					padding: 20px 0;
					margin-block-start: 1em;
					margin-block-end: 1em;
				}

				.wp-2fa-login-woocommerce-wrapper .wp-2fa-login-via-passkey {
					float: unset !important;
				}

				.wp-2fa-login-woocommerce-wrapper .wp-2fa-login-via-passkey:disabled {
					opacity: 0.65;
					cursor: not-allowed;
				}

				.wp-2fa-login-woocommerce-wrapper .wc-block-components-notice-banner {
					padding: 10px !important;
				}

				/* Ultimate Member */
				.wp-2fa-login-ultimate-member-wrapper .is-error {
					margin-top: 10px;
					margin-bottom: 10px;
				}

				.wp-2fa-login-ultimate-member-wrapper .is-success {
					margin-top: 10px !important;
					margin-bottom: 10px !important;
				}
			</style>
			<?php

			return \ob_get_clean();
		}

		/**
		 * Shows passkey on the woocommerce login form
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function woocommerce_login_form() {
			if ( \is_user_logged_in() || ! self::is_globally_enabled() ) {
				return;
			}

			self::enqueue_login_scripts();

			echo self::load_style(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			?>
			
			<div class="wp-2fa-login-woocommerce-wrapper" id="wp-2fa-login-woocommerce-wrapper">
				<div id="errorMessage" class="notice notice-error" style="display: none;"></div>
				<div id="successMessage" class="notice notice-success" style="display: none;"></div>
				<button type="button" id="wp-2fa-login-via-passkey" class="woocommerce-button button woocommerce-form-login__submit wp-element-button wp-2fa-login-via-passkey">
					<span id="buttonText"><?php esc_html_e( 'Log in with a passkey', 'wp-2fa' ); ?></span>
				</button>
				<div style="display: none;margin-top: 10px;" id="wp-2fa-standard-login-wrapper">
					<button type="button" id="wp-2fa-login-standard" class="woocommerce-form-login__submit wp-element-button wp-2fa-login-standard" style="float: unset !important;">
						<span id="buttonText"><?php esc_html_e( 'Log in with your username and password', 'wp-2fa' ); ?></span>
					</button>
				</div>
			</div>
			<?php
		}

		/**
		 * Shows dates in proper format
		 *
		 * @param string|null $datetime - The unix timestamp.
		 *
		 * @return string|null
		 *
		 * @since 3.1.0
		 */
		public static function get_datetime_from_now( ?string $datetime ) {
			if ( ! $datetime ) {
				return null;
			}

			$human_time_diff = human_time_diff( $datetime );

			// translators: %s represents a human-readable time difference (e.g., "5 minutes").
			return sprintf( __( '%s ago', 'wp-2fa' ), $human_time_diff );
		}

		/**
		 * Shows / hides the "Change 2FA settings" button in user profile page.
		 *
		 * @param bool $status - The currently set value.
		 * @param int  $user_id - The ID of the user to check for.
		 *
		 * @since 3.1.0
		 */
		public static function enable_2fa_user_setting( bool $status, $user_id ): bool {
			if ( true === $status ) {

				$user_role         = User_Helper::get_user_role( $user_id );
				$available_methods = Methods::get_enabled_methods( $user_role );

				if ( $available_methods && isset( $available_methods[ $user_role ] ) && count( $available_methods[ $user_role ] ) === 1 && isset( $available_methods[ $user_role ][ self::METHOD_NAME ] ) ) {
					return false;
				}
			}

			return $status;
		}

		/**
		 * Shows the Method option label and hint in the White Label settings.
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		public static function white_label_option_labels() {
			?>
			<strong class="description">
			<?php
			echo \esc_html(
				\wp_sprintf(
					// translators: Method option label.
					__( '%s option label', 'wp-2fa' ),
					WP2FA::get_wp2fa_white_label_setting( 'passkeys-option-label', true )
				)
			);
			?>
			</strong>
			<br>
			<fieldset>
				<input type="text" id="passkeys-option-label" name="wp_2fa_white_label[passkeys-option-label]" class="large-text" value="<?php echo \esc_attr( WP2FA::get_wp2fa_white_label_setting( 'passkeys-option-label', true ) ); ?>">
			</fieldset>
			<br>
			<?php
		}

		/**
		 * Fills up the White Label settings array with the method defaults.
		 *
		 * @param array $default_settings - The array with the collected white label settings.
		 *
		 * @return array
		 *
		 * @since 3.1.0
		 */
		public static function add_whitelabel_settings( array $default_settings ): array {

			$default_settings['passkeys-option-label'] = __( 'Passkeys', 'wp-2fa' );

			return $default_settings;
		}

		/**
		 * Disables email backup method if only Passkeys is enabled
		 *
		 * @param boolean     $disabled - The current disabled status.
		 * @param string      $method - The method to check if the logic needs to fire.
		 * @param string|null $role - The role to check for.
		 *
		 * @return boolean
		 *
		 * @since 3.1.0
		 */
		public static function disable_email_backup( bool $disabled, string $method, $role = null ): bool {
			if ( 'WP2FA\Extensions\EmailBackup\Email_Backup' === $method ) {
				$available_methods = Settings::get_enabled_providers_for_role( (string) $role );
				if ( $available_methods && count( $available_methods ) === 2 && isset( $available_methods[ self::METHOD_NAME ] ) && isset( $available_methods[ Email_Backup::METHOD_NAME ] ) ) {
					return true;
				} elseif ( $available_methods && count( $available_methods ) === 1 && isset( $available_methods[ self::METHOD_NAME ] ) ) {
					return true;
				}
			}

			return $disabled;
		}

		/**
		 * Sets the Passkeys as a method for the given user.
		 *
		 * @param \WP_User $user - The user for which the method has to be set, if null, it uses the current user.
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		public static function set_user_method( $user = null ) {
			if ( null === $user ) {
				$user = \wp_get_current_user();
			}

			// User_Helper::set_enabled_method_for_user( self::METHOD_NAME, $user ); .
			User_Profile::delete_expire_and_enforced_keys( $user->ID );
			User_Helper::set_user_status( $user );
		}

		/**
		 * Sets this method in the role settings options before saving.
		 *
		 * @param array $roles_options - The currently set roles options.
		 *
		 * @return array
		 *
		 * @since 3.1.0
		 */
		public static function set_this_method( array $roles_options ): array {
			$roles_options_stored = Settings_Utils::get_option( Role_Settings_Controller::SETTINGS_NAME );
			if ( ! $roles_options_stored || ! is_array( $roles_options_stored ) ) {
				return $roles_options;
			}

			$stored_policy_settings = WP2FA::get_policy_settings();

			if ( isset( $stored_policy_settings[ self::POLICY_SETTINGS_NAME ] ) && isset( $stored_policy_settings[ self::POLICY_SETTINGS_NAME . '_certain_roles' ] ) && ! empty( $stored_policy_settings[ self::POLICY_SETTINGS_NAME . '_certain_roles' ] ) ) {

				foreach ( $roles_options_stored as $role => $settings ) {
					if ( isset( $settings[ self::POLICY_SETTINGS_NAME ] ) && ! empty( $settings[ self::POLICY_SETTINGS_NAME ] ) ) {
						$roles_options[ $role ][ self::POLICY_SETTINGS_NAME ] = self::POLICY_SETTINGS_NAME;
					}
				}
			} else {
				foreach ( $roles_options_stored as $role => $settings ) {
					unset( $roles_options_stored[ $role ][ self::POLICY_SETTINGS_NAME . '_certain_roles' ] );
					unset( $roles_options_stored[ $role ][ self::POLICY_SETTINGS_NAME ] );
				}
			
				foreach ( $roles_options as $role => $settings ) {
					unset( $roles_options[ $role ][ self::POLICY_SETTINGS_NAME . '_certain_roles' ] );
					unset( $roles_options[ $role ][ self::POLICY_SETTINGS_NAME ] );
				}
			}

			return $roles_options;
		}

		/**
		 * Before saving the settings, it checks if the Passkeys policy settings are set and saves them.
		 *
		 * @param array $settings - The current settings array.
		 *
		 * @return array
		 *
		 * @since 3.1.0
		 */
		public static function before_settings_save( array $settings ): array {
			$stored_policy_settings = WP2FA::get_policy_settings();

			if ( ! $settings[ self::POLICY_SETTINGS_NAME . '_certain_roles' ] && isset( $stored_policy_settings[ self::POLICY_SETTINGS_NAME . '_certain_roles' ] ) ) {
				$settings[ self::POLICY_SETTINGS_NAME . '_certain_roles' ] = $stored_policy_settings[ self::POLICY_SETTINGS_NAME . '_certain_roles' ];
			}
			if ( ! $settings[ self::POLICY_SETTINGS_NAME ] && isset( $stored_policy_settings[ self::POLICY_SETTINGS_NAME ] ) ) {
				$settings[ self::POLICY_SETTINGS_NAME ] = $stored_policy_settings[ self::POLICY_SETTINGS_NAME ];
			}

			if ( isset( $settings[ self::POLICY_SETTINGS_NAME ] ) && ! empty( $settings[ self::POLICY_SETTINGS_NAME ] ) ) {
				$settings[ self::POLICY_SETTINGS_NAME ] = true;
			} else {
				$settings[ self::POLICY_SETTINGS_NAME ] = false;
			}

			return $settings;
		}
	}
}
