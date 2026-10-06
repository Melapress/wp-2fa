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
use WP2FA\Admin\Helpers\WP_Helper;

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
				$sites = \get_sites(
					array(
						'fields' => 'ids',
						'number' => 0,
					)
				);
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
			$existing_policy  = self::get_settings( self::$plugin_policy_name );
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
		 * Migration for version up to 4.1.0
		 *
		 * Backfills 'custom-user-page-id' for installs that have a custom FE page URL
		 * but are missing the stored page ID (bug fixed in 4.1.0).
		 *
		 * Handles:
		 * - Global policy settings.
		 * - Per-role settings (premium).
		 * - Multisite: iterates all sub-sites when the page is created per-site.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		protected static function migrate_up_to_410() {

			self::delete_file_hash();

			self::migrate_sms_templates_from_white_label();

			$policy = self::get_settings( self::$plugin_policy_name );

			if ( \is_array( $policy ) ) {
				$updated = false;

				// Global policy: backfill page ID if URL is set but ID is missing.
				if ( ! empty( $policy['create-custom-user-page'] ) && 'yes' === $policy['create-custom-user-page']
					&& ! empty( $policy['custom-user-page-url'] )
					&& empty( $policy['custom-user-page-id'] )
				) {
					/*
					 * Not with a page per site: each site's page is found by its slug on
					 * that site, so there is no single ID to store - and the policy is
					 * one network setting. This used to write each site's local ID into
					 * it in turn, leaving the last site's, which links then resolved on
					 * the main site: an unrelated page, or none.
					 */
					if ( ! WP_Helper::is_multisite() || empty( $policy['separate-multisite-page-url'] ) ) {
						$page_id = self::resolve_page_id_from_slug( $policy['custom-user-page-url'] );
						if ( $page_id ) {
							$policy['custom-user-page-id'] = $page_id;
							$updated                       = true;
						}
					}
				}

				if ( $updated ) {
					self::set_settings( self::$plugin_policy_name, $policy );
				}
			}

		}

		/**
		 * Resolves a page ID from its slug.
		 *
		 * @param string $slug The page slug.
		 *
		 * @return int The page ID, or 0 if not found.
		 *
		 * @since 4.1.0
		 */
		private static function resolve_page_id_from_slug( string $slug ): int {
			/*
			 * On a network the one shared page lives on the main site, and that is
			 * where the stored ID is resolved (Settings::get_custom_page_link()). The
			 * upgrade can run on any site's request, so it looks there too.
			 */
			$switched = WP_Helper::is_multisite() && \get_current_blog_id() !== \get_main_site_id();
			if ( $switched ) {
				\switch_to_blog( \get_main_site_id() );
			}

			$page = \get_page_by_path( $slug, OBJECT, 'page' );

			if ( $switched ) {
				\restore_current_blog();
			}

			return $page ? (int) $page->ID : 0;
		}

		/**
		 * Moves SMS templates from white label settings to email settings.
		 *
		 * The v4.0.0 migration only converted the format of SMS templates already
		 * present in email settings (string → array) but did not move templates
		 * that were still stored in white label settings. This method corrects that
		 * by checking whether each SMS template key exists in email settings and,
		 * if not, copies it from white label settings in the correct array format.
		 *
		 * If a template is not found in either location the plugin falls back to
		 * hardcoded defaults, so no action is needed.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		private static function migrate_sms_templates_from_white_label() {
			$sms_keys = array(
				'default-twilio-registration-text',
				'default-twilio-code-text',
			);

			$email_settings = self::get_settings( self::$plugin_email_settings_name );

			if ( ! \is_array( $email_settings ) ) {
				$email_settings = array();
			}

			$white_label_settings = self::get_settings( self::$plugin_white_label_name );

			if ( ! \is_array( $white_label_settings ) ) {
				return;
			}

			$email_updated       = false;
			$white_label_updated = false;

			foreach ( $sms_keys as $key ) {
				// Already present in email settings with a valid body — nothing to do.
				if ( isset( $email_settings[ $key ] ) && \is_array( $email_settings[ $key ] ) && ! empty( $email_settings[ $key ]['body'] ) ) {
					continue;
				}

				// Check white label settings for the template.
				if ( ! isset( $white_label_settings[ $key ] ) ) {
					continue;
				}

				$value = $white_label_settings[ $key ];

				if ( \is_string( $value ) && '' !== $value ) {
					$email_settings[ $key ] = array( 'body' => $value );
					$email_updated          = true;
				} elseif ( \is_array( $value ) && ! empty( $value['body'] ) ) {
					$email_settings[ $key ] = $value;
					$email_updated          = true;
				}

				// Remove from white label settings to avoid stale duplicates.
				unset( $white_label_settings[ $key ] );
				$white_label_updated = true;
			}

			if ( $email_updated ) {
				self::set_settings( self::$plugin_email_settings_name, $email_settings );
			}

			if ( $white_label_updated ) {
				self::set_settings( self::$plugin_white_label_name, $white_label_settings );
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

		/**
		 * Deletes the config file hash transient.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		private static function delete_file_hash() {

			\delete_transient( 'wp_2fa_config_file_hash' );

			// On multisite, delete the config hash transient from ALL subsites
			// to prevent stale checksums from blocking extensions after upgrade.
			if ( \is_multisite() ) {
				$sites = \get_sites(
					array(
						'fields' => 'ids',
						'number' => 0,
					)
				);
				foreach ( $sites as $blog_id ) {
					\switch_to_blog( $blog_id );
					\delete_transient( 'wp_2fa_config_file_hash' );
					\restore_current_blog();
				}
			}
		}
		/**
		 * Migration for version 4.2.0 — seed the licensing provider.
		 *
		 * The licensing layer was realigned with the shared Melapress
		 * implementation in this release. Provider selection is now driven purely
		 * by the wp2fa_licensing_provider option: Licensing_Factory::get_provider()
		 * returns null when it is unset, and a null provider means every one of the
		 * plugin's licence checks answers "unlicensed".
		 *
		 * Nothing wrote that option before 4.2.0, so on upgrade it is empty on
		 * every existing site. Licensing_Factory::has_stored_license_data() can
		 * seed it at runtime, but its Freemius branch keys off the fs_wp2fap
		 * option, and that option is only a cache written by
		 * Freemius_Provider::sync_premium_license() — which runs from an admin_init
		 * hook that is itself registered inside Freemius_Provider::init(), and init()
		 * only runs once has_stored_license_data() has already returned true.
		 *
		 * On a site where that cache was never written, or was left at 'no' by a
		 * failed sync, those two conditions deadlock and the site would silently
		 * lose its premium features with no way to recover through the UI.
		 *
		 * Seeding the option here, once, from evidence that survives independently
		 * of that cache breaks the cycle before any of it runs.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		protected static function migrate_up_to_420() {
			/*
			 * Roles added or removed through WP_User::add_role() / remove_role() did
			 * not refresh a user's 2FA policy state, so some users may be carrying a
			 * status worked out for roles they no longer have alone. On networks,
			 * roles held on other sites were read from the wrong meta keys, so
			 * users enforced through them may have been left optional. Every
			 * user's state is worked out again on their next request.
			 */
			self::recompute_user_policy_state();


			// Undo what the 4.1 page-ID backfill left on networks with a page per site.
			self::drop_network_page_ids_for_per_site_pages();

			// Login code emails: send users to the site's admin address, not to the
			// plugin's sending address. Only untouched default lines change.
			\WP2FA\Admin\Helpers\Email_Templates::point_default_contact_lines_at_site_admin();

			// The default "account unlocked" email lost the site name and its last line.
			\WP2FA\Admin\Helpers\Email_Templates::repair_default_unlocked_email();

			$provider_option = '\WP2FA\Licensing\Licensing_Factory';

			if ( ! \class_exists( $provider_option ) ) {
				return;
			}

			$option_name = \WP2FA\Licensing\Licensing_Factory::PROVIDER_OPTION;

			// Respect an explicit choice that is already recorded.
			if ( ! empty( \get_option( $option_name, '' ) ) ) {
				return;
			}

			$is_multisite = \is_multisite();
			$main_site_id = $is_multisite ? \get_main_site_id() : 0;

			// An EDD licence key is the strongest signal, and takes precedence.
			$edd_key = \get_option( \WP2FA\Licensing\EDD_Provider::LICENSE_KEY_OPTION, '' );
			if ( empty( $edd_key ) && $is_multisite ) {
				$edd_key = \get_blog_option( $main_site_id, \WP2FA\Licensing\EDD_Provider::LICENSE_KEY_OPTION, '' );
			}

			if ( ! empty( $edd_key ) ) {
				self::record_licensing_provider( $option_name, 'edd', $is_multisite, $main_site_id );

				return;
			}

			if ( self::has_freemius_activation_evidence( $is_multisite, $main_site_id ) ) {
				self::record_licensing_provider( $option_name, 'freemius', $is_multisite, $main_site_id );
			}

			// Neither: leave the option unset so a genuinely fresh install still
			// lands on the unified licence form, which is the intended entry point.
		}


		/**
		 * Makes every user's 2FA policy state be worked out again.
		 *
		 * Drops each user's record of the policy version their state was computed
		 * for. Nothing else changes now: the next request a user makes recomputes
		 * their status from their current roles, as a policy save would.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function recompute_user_policy_state(): void {
			global $wpdb;

			$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => \WP2FA\Admin\Helpers\User_Helper::USER_SETTINGS_HASH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			// Cached user meta would still hold the old records.
			if ( \function_exists( 'wp_cache_supports' ) && \wp_cache_supports( 'flush_group' ) ) {
				\wp_cache_flush_group( 'user_meta' );
			} else {
				\wp_cache_flush();
			}
		}

		/**
		 * Clears the setup-page ID where each site has a page of its own.
		 *
		 * With a page per site the page is found by its slug on the site in
		 * question, and the ID - one number in a network-wide setting - cannot
		 * name all of them. The 4.1 upgrade nevertheless filled it in, with the
		 * last site's local ID, and links resolved that number on the main site:
		 * an unrelated page, or none. Without it, every link falls back to the
		 * slug on the user's own site, which is what that mode is meant to do.
		 *
		 * Only the ID goes, only on a network, only for a policy (global or per
		 * role) that is in per-site mode. Running it again changes nothing.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function drop_network_page_ids_for_per_site_pages(): void {
			if ( ! WP_Helper::is_multisite() ) {
				return;
			}

			$policy          = self::get_settings( self::$plugin_policy_name );
			$global_per_site = \is_array( $policy ) && ! empty( $policy['separate-multisite-page-url'] );

			if ( $global_per_site && ! empty( $policy['custom-user-page-id'] ) ) {
				unset( $policy['custom-user-page-id'] );
				self::set_settings( self::$plugin_policy_name, $policy );
			}

		}

		/**
		 * Was this site ever activated through Freemius?
		 *
		 * Deliberately broader than the fs_wp2fap === 'yes' test used at runtime.
		 * That option is a cache, so it is absent on a licensed site whose sync has
		 * not run yet and stale on one whose last sync failed. Freemius's own
		 * account storage is not a cache — if it names this plugin, the SDK was
		 * registered here at some point, which is exactly the question being asked.
		 *
		 * TODO(review): this branch is a deliberate addition over the shared
		 * implementation, which checks only the cached option. It exists because
		 * WP 2FA is Freemius-first and gates 58 call sites on the answer, whereas
		 * the shared version is EDD-first and gates 6. Drop it only if the extra
		 * tolerance is judged unnecessary.
		 *
		 * @param bool $is_multisite - Whether this is a network install.
		 * @param int  $main_site_id - Main site ID on a network, 0 otherwise.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function has_freemius_activation_evidence( bool $is_multisite, int $main_site_id ): bool {
			$premium_option = \WP2FA\Licensing\Licensing_Factory::FREEMIUS_PREMIUM_OPTION;

			// The cached flag, in either of its two truthful forms: 'yes' means
			// licensed, and any recorded value at all means a sync has run here.
			foreach ( self::licensing_option_values( $premium_option, $is_multisite, $main_site_id ) as $value ) {
				if ( '' !== $value && null !== $value && false !== $value ) {
					return true;
				}
			}

			// Freemius's own account storage. Present once the SDK has registered
			// this product, and never written by our own sync.
			/*
			 * Through the provider, which reads the rows directly. Asking for this option the
			 * ordinary way unserialises it before the SDK has declared its entity classes, and
			 * WordPress keeps the broken objects in its option cache for the rest of the
			 * request — the SDK is then handed those and fills the screen with
			 * "property on an incomplete object" warnings.
			 */
			foreach ( \WP2FA\Licensing\Freemius_Provider::account_records() as $accounts ) {
				if ( ! \is_array( $accounts ) ) {
					continue;
				}

				$slug = \WP2FA\Licensing\Licensing_Factory::FREEMIUS_SLUG;

				foreach ( array( 'sites', 'plugins', 'licenses', 'plans' ) as $bucket ) {
					if ( isset( $accounts[ $bucket ] ) && \is_array( $accounts[ $bucket ] ) && isset( $accounts[ $bucket ][ $slug ] ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Read an option from this site and, on a network, the main site too.
		 *
		 * @param string $option_name  - Option to read.
		 * @param bool   $is_multisite - Whether this is a network install.
		 * @param int    $main_site_id - Main site ID on a network, 0 otherwise.
		 *
		 * @return array
		 *
		 * @since 4.2.0
		 */
		private static function licensing_option_values( string $option_name, bool $is_multisite, int $main_site_id ): array {
			$values = array( \get_option( $option_name, '' ) );

			if ( $is_multisite && $main_site_id > 0 ) {
				$values[] = \get_blog_option( $main_site_id, $option_name, '' );
			}

			return $values;
		}

		/**
		 * Record the resolved provider, on the network main site as well.
		 *
		 * @param string $option_name  - Provider option name.
		 * @param string $provider     - 'edd' or 'freemius'.
		 * @param bool   $is_multisite - Whether this is a network install.
		 * @param int    $main_site_id - Main site ID on a network, 0 otherwise.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function record_licensing_provider( string $option_name, string $provider, bool $is_multisite, int $main_site_id ) {
			\update_option( $option_name, $provider );

			if ( $is_multisite && $main_site_id > 0 && empty( \get_blog_option( $main_site_id, $option_name, '' ) ) ) {
				\update_blog_option( $main_site_id, $option_name, $provider );
			}
		}
	}
}
