<?php
/**
 * Responsible for plugin updates.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Utils;

use WP2FA\Utils\Abstract_Migration;
use WP2FA\Utils\User_Utils;
use WP2FA\Utils\Settings_Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Migration class
 */
if ( ! class_exists( '\WP2FA\Utils\Migration' ) ) {

	/**
	 * Put all you migration methods here
	 *
	 * @package WP2FA\Utils
	 * @since 1.6
	 */
	class Migration extends Abstract_Migration {

		/**
		 * The name of the option from which we should extract version
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since 1.6.0
		 */
		protected static $version_option_name = WP_2FA_PREFIX . 'plugin_version';

		/**
		 * The constant name where the plugin version is stored
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since 1.6.0
		 */
		protected static $const_name_of_plugin_version = 'WP_2FA_VERSION';

		/**
		 * The name of the plugin settings
		 *
		 * @var string
		 *
		 * @since 1.6.0
		 */
		private static $plugin_settings_name = WP_2FA_SETTINGS_NAME;

		/**
		 * The name of the plugin policy settings
		 *
		 * @var string
		 *
		 * @since 1.6.0
		 */
		private static $plugin_policy_name = WP_2FA_POLICY_SETTINGS_NAME;

		/**
		 * The name of the plugin white label settings
		 *
		 * @var string
		 *
		 * @since 1.6.0
		 */
		private static $plugin_white_label_name = WP_2FA_WHITE_LABEL_SETTINGS_NAME;

		/**
		 * The name of the plugin email settings
		 *
		 * @var string
		 *
		 * @since 1.6.0
		 */
		private static $plugin_email_settings_name = WP_2FA_EMAIL_SETTINGS_NAME;

		/**
		 * Migration for version upto 1.6.0
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_160() {
			$settings = self::get_settings( self::$plugin_settings_name );
			if ( ! is_array( $settings ) ) {
				return;
			}

			$needs_update = false;

			$settings_to_convert = array( 'enforced_roles', 'enforced_users', 'excluded_users', 'excluded_roles' );
			foreach ( $settings_to_convert as $setting_name ) {
				if ( array_key_exists( $setting_name, $settings ) && ! is_array( $settings[ $setting_name ] ) ) {
					$settings[ $setting_name ] = array_filter(
						array_map( 'sanitize_text_field', explode( ',', $settings[ $setting_name ] ) )
					);
					$needs_update              = true;
				}
			}

			if ( ! isset( $settings['backup_codes_enabled'] ) ) {
				$settings['backup_codes_enabled'] = 'yes';
				$needs_update                     = true;
			}

			if ( $needs_update ) {
				// Update settings.
				self::set_settings( self::$plugin_settings_name, $settings );
			}
		}

		/**
		 * Migration for version upto 1.6.2
		 *
		 * @return void
		 *
		 * @since 1.6.2
		 */
		protected static function migrate_up_to_162() {
			$settings = self::get_settings( self::$plugin_settings_name );
			if ( ! is_array( $settings ) ) {
				return;
			}

			$needs_update = false;

			$settings_to_convert = array( 'excluded_sites' );
			foreach ( $settings_to_convert as $setting_name ) {
				if ( array_key_exists( $setting_name, $settings ) && ! is_array( $settings[ $setting_name ] ) ) {
					$original_settings_split   = array_filter(
						array_map( 'sanitize_text_field', explode( ',', $settings[ $setting_name ] ) )
					);
					$settings[ $setting_name ] = array();
					foreach ( $original_settings_split as $value ) {
						$settings[ $setting_name ][] = sanitize_text_field( mb_substr( $value, mb_strrpos( $value, ':' ) + 1 ) );
					}
					$needs_update = true;
				}
			}

			self::migrate_up_to_160();

			if ( $needs_update ) {
				// Update settings.
				self::set_settings( self::$plugin_settings_name, $settings );
			}
		}

		/**
		 * Migration for version upto 1.5.0
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_150() {
			$settings = self::get_settings( self::$plugin_settings_name );

			if ( is_array( $settings ) && array_key_exists( 'enforcment-policy', $settings ) ) {
				// Correct setting name.
				$settings['enforcement-policy'] = sanitize_text_field( $settings['enforcment-policy'] );
				// Remove old setting.
				unset( $settings['enforcment-policy'] );
				// Update settings.
				self::set_settings( self::$plugin_settings_name, $settings );
			}
		}

		/**
		 * Migration for version upto 1.7.0
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_170() {
			$settings = self::get_settings( self::$plugin_settings_name );

			if ( is_array( $settings ) && array_key_exists( 'notify_users', $settings ) ) {
				// Remove old setting.
				unset( $settings['notify_users'] );
				// Update settings.
				self::set_settings( self::$plugin_settings_name, $settings );
			}

			$email_settings  = self::get_settings( self::$plugin_email_settings_name );
			$items_to_remove = array( 'send_enforced_email', 'enforced_email_subject', 'enforced_email_body' );

			if ( is_array( $email_settings ) && User_Utils::in_array_all( $items_to_remove, $email_settings ) ) {
				foreach ( $items_to_remove as $item ) {
					if ( isset( $email_settings[ $item ] ) ) {
						unset( $email_settings[ $item ] );
					}
				}
				// Update settings.
				self::set_settings( self::$plugin_email_settings_name, $email_settings );
			}
		}

		/**
		 * Migration for version upto 2.0.0
		 * Separates the current settings into 3 different types of settings:
		 *  - Policy
		 *  - General
		 *  - White label
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_200() {
			$settings = self::get_settings( self::$plugin_settings_name );

			if ( is_array( $settings ) ) {

				$new_settings_array = array_flip(
					array(
						'enable_grace_cron',
						'limit_access',
						'delete_data_upon_uninstall',
						'enable_destroy_session',
					)
				);

				$new_white_label_array = array_flip(
					array(
						'default-text-code-page',
					)
				);

				$settings_array = array_intersect_key(
					$settings,
					$new_settings_array
				);

				$settings = array_diff_key( $settings, $new_settings_array );

				self::set_settings( self::$plugin_settings_name, $settings_array );

				$white_label_settings = array_intersect_key(
					$settings,
					$new_white_label_array
				);

				$settings = array_diff_key( $settings, $new_white_label_array );

				self::set_settings( self::$plugin_white_label_name, $white_label_settings );

				self::set_settings( self::$plugin_policy_name, $settings );
			}
		}

		/**
		 * Migration for version upto 2.2.0
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_220() {
			global $wpdb;

			$new_prefix = 'wp_2fa_trusted_device_';
			$old_prefix = 'wp2fa_trusted_device_';

			\delete_transient( 'wp_2fa_config_file_hash' );

			$wpdb->query(
				$wpdb->prepare(
					"
				 UPDATE $wpdb->usermeta
				 SET meta_key = REPLACE( meta_key, %s, %s )
				 WHERE meta_key LIKE %s
				 ",
					array(
						\sanitize_key( $old_prefix ),
						\sanitize_key( $new_prefix ),
						\sanitize_key( $old_prefix . '%' ),
					)
				)
			);
		}

		/**
		 * Migration for version upto 2.3.0
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_230() {

			$version = self::get_settings( self::$version_option_name );

			if ( $version && version_compare( $version, '2.2.1', '<=' ) ) {
				$settings = self::get_settings( self::$plugin_white_label_name );

				if ( isset( $settings['enable_wizard_styling'] ) ) {
					$settings['enable_wizard_styling'] = false;
				} else {
					$settings                          = array();
					$settings['enable_wizard_styling'] = false;
				}

				self::set_settings( self::$plugin_white_label_name, $settings );
			}
		}

		/**
		 * Migration for version upto 2.4.0
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_240() {

			\delete_transient( 'wp_2fa_config_file_hash' );

			if ( \wp_next_scheduled( 'wp_2fa_check_grace_period_status' ) ) {
				\wp_clear_scheduled_hook( 'wp_2fa_check_grace_period_status' );
			}
		}

		/**
		 * Migration for version upto 2.6.2
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_262() {

			self::migrate_up_to_240();
		}

		/**
		 * Migration for version upto 2.6.3
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		protected static function migrate_up_to_263() {

			self::migrate_up_to_240();
		}

		/**
		 * Migration for version upto 2.8.0
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		protected static function migrate_up_to_280() {

			self::migrate_up_to_240();
		}

		/**
		 * Migration for version upto 3.0.0
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		protected static function migrate_up_to_300() {

			Settings_Utils::delete_option( 'update_redirection_needed' );
			Settings_Utils::delete_option( 'update_notice_needed' );
		}

		/**
		 * Migration for version upto 2.9.2
		 *
		 * @return void
		 *
		 * @since 2.9.2
		 */
		protected static function migrate_up_to_292() {

			Settings_Utils::delete_option( 'method_selection_single' );

			$settings = self::get_settings( self::$plugin_settings_name );

			if ( \is_array( $settings ) && isset( $settings['enable_rest'] ) ) {

				$settings['enable_rest'] = false;

				self::set_settings( self::$plugin_settings_name, $settings );
			}
		}

		/**
		 * Migration for version upto 4.0.0
		 *
		 * Disable the new interface for existing (upgrading) users.
		 * Fresh installs will default to enabled since the option won't exist.
		 *
		 * Also renames settings keys that previously started with '2fa_' to 'wp-2fa_'
		 * to comply with CSS naming conventions (no class/ID names starting with a digit).
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		protected static function migrate_up_to_400() {

			\delete_transient( 'wp_2fa_config_file_hash' );

			// On multisite, delete the config hash transient from ALL subsites
			// to prevent stale checksums from blocking extensions after upgrade.
			if ( \is_multisite() ) {
				$sites = \get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
				foreach ( $sites as $blog_id ) {
					\switch_to_blog( $blog_id );
					\delete_transient( 'wp_2fa_config_file_hash' );
					\restore_current_blog();
				}
			}

			// Detect fresh install: if no policy settings exist, this is a new install
			// (migration runs before the activation hook can set the version).
			// In that case, skip interface/dialog changes so the new interface is enabled
			// by default and the first-time wizard can run unimpeded.
			$existing_policy = self::get_settings( self::$plugin_policy_name );
			$is_fresh_install = empty( $existing_policy );

			$settings = self::get_settings( self::$plugin_settings_name );

			if ( ! \is_array( $settings ) ) {
				$settings = array();
			}

			if ( ! $is_fresh_install ) {
				$settings['use_new_interface'] = false;

				self::set_settings( self::$plugin_settings_name, $settings );
			}

			self::rename_white_label_keys();

			self::migrate_sms_templates();

			// Show the new interface announcement dialog only for upgrades.
			// Skip for fresh installs, WP-CLI and bulk updates.
			if ( ! $is_fresh_install ) {
				$show_dialog = true;

				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					$show_dialog = false;
				} else {
					$manual_update = \get_transient( 'wp_2fa_manual_update' );
					if ( '0' === $manual_update ) {
						$show_dialog = false;
					}
					\delete_transient( 'wp_2fa_manual_update' );
				}

				if ( $show_dialog ) {
					Settings_Utils::update_option( \WP2FA\Admin\New_Interface_Notice::SHOW_DIALOG_OPTION, 1 );
				}
			}
		}

		/**
		 * Migrates SMS template settings from plain string format to array format.
		 *
		 * Prior to version 4.0.0, SMS templates were stored as plain strings:
		 *   "default-twilio-registration-text" => "custom text"
		 *
		 * From version 4.0.0 onwards, they must be stored as arrays with a 'body' key:
		 *   "default-twilio-registration-text" => array( "body" => "custom text" )
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function migrate_sms_templates() {
			$email_settings = self::get_settings( self::$plugin_email_settings_name );

			if ( ! \is_array( $email_settings ) ) {
				return;
			}

			$sms_keys = array(
				'default-twilio-registration-text',
				'default-twilio-code-text',
			);

			$updated = false;

			foreach ( $sms_keys as $key ) {
				if ( isset( $email_settings[ $key ] ) && \is_string( $email_settings[ $key ] ) ) {
					$email_settings[ $key ] = array( 'body' => $email_settings[ $key ] );
					$updated                = true;
				}
			}

			if ( $updated ) {
				self::set_settings( self::$plugin_email_settings_name, $email_settings );
			}
		}

		/**
		 * Renames white label settings keys that are also used as wp_editor HTML
		 * element IDs, from '2fa_*' to 'wp-2fa_*' so they no longer start with a digit.
		 *
		 * Only keys that directly become HTML id attributes are renamed.
		 * Internal-only settings keys (e.g. '2fa_settings_last_updated_by') and
		 * user meta keys (e.g. 'wp_2fa_2fa_status') are left unchanged because
		 * their final resolved values never start with a digit.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function rename_white_label_keys() {

			$white_label_key_map = array(
				'2fa_required_intro' => 'wp-2fa_required_intro',
				'2fa_wizard_cancel'  => 'wp-2fa_wizard_cancel',
			);

			$white_label_settings = self::get_settings( self::$plugin_white_label_name );

			if ( \is_array( $white_label_settings ) ) {
				$updated = false;

				foreach ( $white_label_key_map as $old_key => $new_key ) {
					if ( \array_key_exists( $old_key, $white_label_settings ) && ! \array_key_exists( $new_key, $white_label_settings ) ) {
						$white_label_settings[ $new_key ] = $white_label_settings[ $old_key ];
						unset( $white_label_settings[ $old_key ] );
						$updated = true;
					}
				}

				if ( $updated ) {
					self::set_settings( self::$plugin_white_label_name, $white_label_settings );
				}
			}
		}

		/**
		 * Returns the plugin settings by a given setting type
		 *
		 * @param mixed $setting_name - The setting which needs to be extracted.
		 *
		 * @return mixed
		 *
		 * @since 1.6.0
		 */
		private static function get_settings( $setting_name ) {
			return Settings_Utils::get_option( sanitize_key( $setting_name ) );
		}

		/**
		 * Updates the plugin settings
		 *
		 * @param mixed $setting_name - The setting which needs to be updated.
		 * @param mixed $settings - The settings values.
		 *
		 * @return void
		 *
		 * @since 1.6.0
		 */
		private static function set_settings( $setting_name, $settings ) {
			Settings_Utils::update_option( sanitize_key( $setting_name ), $settings );
		}
	}
}
