<?php
/**
 * Responsible for WP2FA user's backup codes manipulation.
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
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use WP2FA\Admin\Methods\Traits\Login_Attempts;

/**
 * Class for handling backup codes.
 *
 * @since 0.1-dev
 *
 * @package WP2FA
 */
if ( ! class_exists( '\WP2FA\Methods\Backup_Codes' ) ) {
	/**
	 * Backup code class, for handling backup code generation and such.
	 *
	 * @since 2.6.0
	 */
	class Backup_Codes {

		use Login_Attempts;

		/**
		 * Holds the name of the meta key for the allowed login attempts.
		 *
		 * @var string
		 *
		 * @since 2.0.0
		 */
		private static $logging_attempts_meta_key = WP_2FA_PREFIX . 'backup-login-attempts';

		/**
		 * Key used for backup codes.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		public const BACKUP_CODES_META_KEY = 'wp_2fa_backup_codes';

		/**
		 * The number backup codes.
		 *
		 * @var int
		 *
		 * @since 2.6.0
		 */
		public const NUMBER_OF_CODES = 10;

		/**
		 * The name of the method.
		 *
		 * @var string
		 *
		 * @since 2.0.0
		 */
		public const METHOD_NAME = 'backup_codes';

		/**
		 * The login attempts class.
		 *
		 * @var \WP2FA\Admin\Controllers\Login_Attempts
		 *
		 * @since 2.0.0
		 */
		private static $login_attempts = null;

		/**
		 * Holds the status of the backup codes functionality
		 *
		 * @var bool[]
		 *
		 * @since 2.6.0
		 */
		private static $backup_codes_enabled = array();

		/**
		 * Default extension settings.
		 *
		 * @var array
		 *
		 * @since 2.6.0
		 */
		private static $settings = array(
			'backup_codes_enabled' => 'yes',
		);

		/**
		 * Inits the backup codes class hooks
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function init() {
			\add_filter( WP_2FA_PREFIX . 'backup_methods_list', array( __CLASS__, 'add_backup_method' ), 10, 2 );
			\add_filter( WP_2FA_PREFIX . 'backup_methods_enabled', array( __CLASS__, 'check_backup_method_for_role' ), 10, 2 );
			\add_action( 'wp_ajax_wp2fa_run_ajax_generate_json', array( __CLASS__, 'run_ajax_generate_json' ) );

			\add_action( WP_2FA_PREFIX . 'remove_backup_methods_for_user', array( __CLASS__, 'remove_backup_methods_for_user' ) );

			\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'settings_loop' ), 10, 2 );

			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );

			\add_filter( WP_2FA_PREFIX . 'providers', array( __CLASS__, 'backup_codes' ) );

			\add_filter( WP_2FA_PREFIX . 'providers_translated_names', array( __CLASS__, 'fill_providers_array_with_method_name_translated' ) );

			\add_filter( WP_2FA_PREFIX . 'user_enabled_backup_methods', array( __CLASS__, 'method_enabled_for_user' ), 10, 2 );
		}

		/**
		 * Generate backup codes.
		 *
		 * @param object $user User data.
		 * @param string $args possible args.
		 *
		 * @since 2.6.0
		 */
		public static function generate_codes( $user, $args = '' ) {
			$codes        = array();
			$codes_hashed = array();

			// Check for arguments.
			if ( isset( $args['number'] ) ) {
				$num_codes = (int) $args['number'];
			} else {
				$num_codes = self::NUMBER_OF_CODES;
			}

			// Append or replace (default).
			if ( isset( $args['method'] ) && 'append' === $args['method'] ) {
				$codes_hashed = (array) \get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );
			}

			for ( $i = 0; $i < $num_codes; ++$i ) {
				$code           = Authentication::get_code();
				$codes_hashed[] = \wp_hash_password( $code );
				$codes[]        = $code;
				unset( $code );
			}

			\update_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, $codes_hashed );

			// Unhashed.
			return $codes;
		}

		/**
		 * Fills the array of the enabled backup methods is it is provided for the given user
		 *
		 * @param array    $array_methods - Array to fill if the method is enabled for user.
		 * @param \WP_User $user - The user to check for.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function method_enabled_for_user( array $array_methods, $user ): array {
			if ( self::is_enabled_for_user( $user ) ) {
				$array_methods[ self::METHOD_NAME ] = self::get_translated_name();
			}

			return $array_methods;
		}

		/**
		 * Adds Backup codes as a provider.
		 *
		 * @param array $providers - Array with all currently supported providers.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function backup_codes( array $providers ) {
			array_push( $providers, self::METHOD_NAME );

			return $providers;
		}

		/**
		 * Adds Backup code as a provider.
		 *
		 * @param array $providers - Array with all currently supported providers and their translated names.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function fill_providers_array_with_method_name_translated( array $providers ) {
			$providers[ self::METHOD_NAME ] = self::get_translated_name();

			return $providers;
		}

		/**
		 * Returns the name of the provider
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_translated_name(): string {
			return esc_html__( 'Backup codes', 'wp-2fa' );
		}

		/**
		 * Removes the backup method (user meta key) from the database.
		 *
		 * @param \WP_User,int,null $user - The user to remove method for.
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		public static function remove_backup_methods_for_user( $user ) {
			if ( ! Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user ), self::get_method_name() ) ) {
				\delete_user_meta( $user->ID, self::BACKUP_CODES_META_KEY );
			}
		}

		/**
		 * Generate codes and check remaining amount for user.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function run_ajax_generate_json() {
			$user = wp_get_current_user();

			check_ajax_referer( 'wp-2fa-backup-codes-generate-json-' . $user->ID, 'nonce' );

			// Setup the return data.
			$codes = self::generate_codes( $user );

			$count = self::codes_remaining_for_user( $user );
			$i18n  = array(
				'count' => esc_html(
					sprintf(
						/* translators: %s: count */
						_n( '%s unused code remaining.', '%s unused codes remaining.', $count, 'wp-2fa' ),
						$count
					)
				),
				/* translators: %s: the site's domain */
				'title' => esc_html__( 'Two-Factor Backup Codes for %s', 'wp-2fa' ),
			);

			// Send the response.
			wp_send_json_success(
				array(
					'codes' => $codes,
					'i18n'  => $i18n,
				)
			);
		}

		/**
		 * Grab number of unused backup codes within the users position.
		 *
		 * @param object $user - User data.
		 *
		 * @return int Count of codes.
		 *
		 * @since 2.6.0
		 */
		public static function codes_remaining_for_user( $user ) {
			$backup_codes = \get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );
			if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
				return count( $backup_codes );
			}

			return 0;
		}

		/**
		 * Validate backup codes.
		 *
		 * @param object $user User data.
		 * @param string $code The code we are checking.
		 *
		 * @return bool Is is valid or not.
		 *
		 * @since 2.6.0
		 */
		public static function validate_code( $user, $code ) {
			$backup_codes = \get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );
			if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
				foreach ( $backup_codes as $code_hashed ) {
					if ( \wp_check_password( $code, $code_hashed, $user->ID ) ) {
						self::delete_code( $user, $code_hashed );
						self::clear_login_attempts( $user );

						return true;
					}
				}
			}
			self::increase_login_attempts( $user );

			return false;
		}

		/**
		 * Delete code once its used.
		 *
		 * @param object $user        User data.
		 * @param string $code_hashed Code to delete.
		 *
		 * @since 2.6.0
		 */
		public static function delete_code( $user, $code_hashed ) {
			$backup_codes = get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );

			// Delete the current code from the list since it's been used.
			$backup_codes = array_flip( $backup_codes );
			unset( $backup_codes[ $code_hashed ] );
			$backup_codes = array_values( array_flip( $backup_codes ) );

			// Update the backup code master list.
			\update_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, $backup_codes );
		}

		/**
		 * Add the method to the existing backup methods array.
		 *
		 * @param array $backup_methods - Array with the currently supported backup methods.
		 *
		 * @since 2.0.0
		 */
		public static function add_backup_method( array $backup_methods ): array {
			return array_merge(
				$backup_methods,
				array(
					self::METHOD_NAME => array(
						'wizard-step' => '2fa-wizard-config-backup-codes',
						'button_name' => sprintf(
							/* translators: URL with more information about the backup codes */
							esc_html__( 'Login with a backup code: you will get 10 backup codes and you can use one of them when you need to login and you cannot generate a code from the app. %s', 'wp-2fa' ),
							'<a href="https://melapress.com/2fa-backup-codes/" target="_blank">' . esc_html__( 'More information.', 'wp-2fa' ) . '</a>'
						),
					),
				)
			);
		}

		/**
		 * Changes the global backup methods array - removes the method if it is not enabled.
		 *
		 * @param array    $backup_methods - Array with all global backup methods.
		 * @param \WP_User $user           - User to check for is that method enabled.
		 *
		 * @since 2.0.0
		 */
		public static function check_backup_method_for_role( array $backup_methods, \WP_User $user ): array {
			$enabled = self::are_backup_codes_enabled_for_role( User_Helper::get_user_role( $user ) );

			if ( ! $enabled ) {
				unset( $backup_methods[ self::METHOD_NAME ] );
			}

			return $backup_methods;
		}

		/**
		 * Returns the name of the method.
		 *
		 * @since 2.0.0
		 */
		public static function get_method_name(): string {
			return self::METHOD_NAME;
		}

		/**
		 * Checks if the backup codes option is enabled for the role
		 *
		 * @param string $role - The role name.
		 *
		 * @return bool
		 *
		 * @since 2.6.0
		 */
		public static function are_backup_codes_enabled_for_role( $role = 'global' ) {

			$role = ( is_null( $role ) || empty( $role ) ) ? 'global' : $role;

			if ( ! isset( self::$backup_codes_enabled[ $role ] ) ) {
				self::$backup_codes_enabled[ $role ] = false;

				if ( 'global' === $role ) {
					$setting_value = Settings::get_role_or_default_setting( self::get_settings_name() );
				} else {
					$setting_value = Settings::get_role_or_default_setting( self::get_settings_name(), 'current', $role );
				}
				self::$backup_codes_enabled[ $role ] = Settings_Utils::string_to_bool( $setting_value );
			}

			return self::$backup_codes_enabled[ $role ];
		}

		/**
		 * Checks if the backup codes are enabled for the user.
		 *
		 * @param int|\WP_User|null $user - The WP user we should extract the meta data for.
		 *
		 * @return bool
		 *
		 * @since 2.6.0
		 *
		 * @throws \LogicException - can not extract user from the given parameters.
		 */
		public static function is_enabled_for_user( $user ): bool {
			$user = User_Helper::get_user_object( $user );

			if ( ! \is_a( $user, '\WP_User' ) ) {
				throw new \LogicException( 'Not a proper user object provided!' );
			}

			$codes_remaining = self::codes_remaining_for_user( $user );

			return (bool) $codes_remaining;
		}

		/**
		 * Adds settings names to the extraction array - grabs the values and stores them based on names.
		 *
		 * @param array $settings - Array with all the settings.
		 *
		 * @since 2.6.0
		 */
		public static function settings_loop( array $settings ): array {
			return array_merge( $settings, array_keys( self::$settings ) );
		}

		/**
		 * Adds the extension default settings to the main plugin settings.
		 *
		 * @param array $default_settings - Array with plugin default settings.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function add_default_settings( array $default_settings ) {
			return array_merge( $default_settings, self::$settings );
		}

		/**
		 * Returns the method settings name
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_settings_name(): string {
			return \array_key_first( self::$settings );
		}

		/**
		 * Returns the method settings default value
		 *
		 * @return mixed
		 *
		 * @since 2.6.0
		 */
		public static function get_settings_default_value() {
			return \reset( self::$settings );
		}

		/**
		 * Validates a backup code.
		 *
		 * Backup Codes are single use and are deleted upon a successful validation.
		 *
		 * @since 2.6.0
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 *
		 * @return boolean
		 */
		public static function validate_backup_codes( $user ) {
			if ( ! isset( $user->ID ) || ! isset( $_REQUEST['wp-2fa-backup-code'] ) ) { //phpcs:ignore
				return false;
			}

			return self::validate_code( $user, \sanitize_text_field( \wp_unslash( $_REQUEST['wp-2fa-backup-code'] ) ) );
		}

		/**
		 * Returns the backup codes for the user.
		 *
		 * @param \WP_User $user \WP_User - object of the logged-in user.
		 *
		 * @return array
		 *
		 * @since 2.6.0
		 */
		public static function get_backup_codes_for_user( $user ): array {
			$backup_codes = get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );

			if ( ! \is_array( $backup_codes ) ) {
				return array();
			}

			return $backup_codes;
		}

		/**
		 * Send email with fresh code, or to setup email 2fa.
		 *
		 * @param int    $user_id User id we want to send the message to.
		 * @param string $nominated_email_address - The user custom address to use (name of the meta key to check for).
		 *
		 * @return bool
		 *
		 * @since 2.6.0
		 */
		public static function send_backup_codes_email( $user_id, $nominated_email_address = 'nominated_email_address' ) {

			// If we have a nonce posted, check it.
			if ( \wp_doing_ajax() && isset( $_POST['_wpnonce'] ) ) {
				$nonce_check = \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ), 'wp-2fa-send-backup-codes-email-nonce' );
				if ( ! $nonce_check ) {
					return false;
				}
			} else {
				\wp_die();
			}

			$user = User_Helper::get_user_object();

			$enabled_email_address = '';
			if ( ! empty( $nominated_email_address ) ) {
				if ( 'nominated_email_address' === $nominated_email_address ) {
					$enabled_email_address = User_Helper::get_nominated_email_for_user( $user );
				} else {
					$enabled_email_address = get_user_meta( $user->ID, WP_2FA_PREFIX . $nominated_email_address, true );
				}
			}

			if ( isset( $_POST['codes'] ) ) {
				$codes = substr( str_replace( '\\n', '<br>', \sanitize_text_field( \wp_unslash( $_POST['codes'] ) ) ), 1, -1 );

				$posted_codes = array_filter( \explode( '<br>', $codes ) );

				$stored_codes = self::get_backup_codes_for_user( $user );

				foreach ( $posted_codes as $key => $check_code ) {
					$check_code = trim( \explode( ':', $check_code )[1] );
					if ( ! \wp_check_password( $check_code, $stored_codes[ $key ], $user->ID ) ) {

						\wp_die();
					}
				}
			} else {
				\wp_die();
			}

			$subject = wp_strip_all_tags( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_backup_codes_email_subject' ), $user->ID ) );
			$message = wpautop( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_backup_codes_email_body' ), $user->ID ) );

			$final_output = str_replace( '{backup_codes}', $codes, $message );

			if ( ! empty( $enabled_email_address ) ) {
				$email_address = $enabled_email_address;
			} else {
				$email_address = $user->user_email;
			}

			return Settings_Page::send_email( $email_address, $subject, $final_output );
		}

		/**
		 * Marks methods as secondary.
		 *
		 * @return boolean
		 *
		 * @since 2.7.0
		 */
		public static function is_secondary() {
			return true;
		}
	}
}
