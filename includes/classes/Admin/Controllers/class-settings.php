<?php
/**
 * Responsible for the plugin settings iterations
 *
 * @package    wp2fa
 * @subpackage admin_controllers
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 * @since      2.2.0
 */

declare(strict_types=1);

namespace WP2FA\Admin\Controllers;

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Page;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Helpers\Methods_Helper;
use WP2FA\Extensions\OutOfBand\Out_Of_Band;
use WP2FA\Admin\SettingsPages\Settings_Page_Policies;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Controllers\Settings' ) ) {
	/**
	 * WP2FA Settings controller
	 *
	 * @since 2.2.0
	 */
	class Settings {

		/**
		 * The link to the WP admin settings page
		 *
		 * @var string
		 *
		 * @since 2.2.0
		 */
		private static $settings_page_link = '';

		/**
		 * The name of the WP2FA WP admin setup page
		 *
		 * @var string
		 *
		 * @since 2.2.0
		 */
		private static $setup_page_name = 'wp-2fa-setup';

		/**
		 * The link to the WP admin setup page
		 *
		 * @var string
		 *
		 * @since 2.2.0
		 */
		private static $setup_page_link = '';

		/**
		 * The link to the custom settings page (if one is presented)
		 *
		 * @var string
		 *
		 * @since 2.2.0
		 */
		private static $custom_setup_page_link = null;

		/**
		 * Array with all the backup methods available.
		 *
		 * Array must contain the following:
		 * [backup_method_slug] - [
		 *          'wizard-step' - The name (HTML friendly as it will be used in the tags) of the plugin wizard step.
		 *          'button_name' - The button name shown in the wizard - language translated.
		 * ]
		 *
		 * @var array
		 *
		 * @since 2.0.0
		 */
		private static $backup_methods = null;

		/**
		 * All available providers for the plugin
		 * For the specific role @see get_all_providers_for_role()
		 *
		 * @var array
		 *
		 * @since 2.2.0
		 */
		private static $all_providers = array();

		/**
		 * All available providers for the plugin with their translated names.
		 *
		 * @var array
		 *
		 * @since 2.5.0
		 */
		private static $all_providers_names_translated = array();

		/**
		 * All the available providers by user roles
		 *
		 * @var array
		 *
		 * @since 2.2.0
		 */
		private static $all_providers_for_roles = array();

		/**
		 * Returns the link to the WP admin settings page, based on the current WP install
		 *
		 * @return string
		 *
		 * @since 2.2.0
		 */
		public static function get_settings_page_link() {
			if ( '' === self::$settings_page_link ) {
				self::$settings_page_link = add_query_arg( 'page', Settings_Page::TOP_MENU_SLUG, network_admin_url( 'admin.php' ) );
			}

			return self::$settings_page_link;
		}

		/**
		 * Returns the link to the WP admin settings page, based on the current WP install
		 *
		 * @param bool $force_show - Do we need to add show param regardless.
		 *
		 * @return string
		 *
		 * @since 2.2.0
		 * @since 3.1.0 - added flag for show param.
		 */
		public static function get_setup_page_link( $force_show = false ) {
			if ( '' === self::$setup_page_link ) {
				self::$setup_page_link = self::get_custom_page_link();

				if ( empty( self::$setup_page_link ) ) {
					if ( WP_Helper::is_multisite() ) {
						self::$setup_page_link = \add_query_arg( 'show', self::$setup_page_name, \get_admin_url( \get_current_blog_id(), 'profile.php' ) );
					} else {
						self::$setup_page_link = \add_query_arg( 'show', self::$setup_page_name, \network_admin_url( 'profile.php' ) );
					}
				} elseif ( $force_show ) {
					return \add_query_arg( 'show', self::$setup_page_name, self::$setup_page_link );
				}
			}

			return \apply_filters( WP_2FA_PREFIX . 'setup_page_link', self::$setup_page_link );
		}

		/**
		 * Extracts the custom settings page URL
		 *
		 * @param mixed $user - User for which to extract the setting, null, \WP_User or user id - @see get_role_or_default_setting method of this class.
		 *
		 * @return string
		 *
		 * @since 2.2.0
		 */
		public static function get_custom_page_link( $user = null ): string {
			if ( null === self::$custom_setup_page_link ) {
				self::$custom_setup_page_link = self::get_role_or_default_setting( 'custom-user-page-id', $user );

				if ( ! empty( self::$custom_setup_page_link ) ) {
					if ( WP_Helper::is_multisite() ) {
						\switch_to_blog( get_main_site_id() );

						$new_page_permalink           = \esc_url( \get_permalink( \get_post( self::$custom_setup_page_link ) ) );
						self::$custom_setup_page_link = $new_page_permalink;

						\restore_current_blog();
					} else {
						$new_page_permalink           = \esc_url( \get_permalink( \get_post( self::$custom_setup_page_link ) ) );
						self::$custom_setup_page_link = $new_page_permalink;
					}
				} else {
					$custom_user_page_id = (int) self::get_custom_settings_page_id( '', $user );
					if ( ! empty( $custom_user_page_id ) ) {
						self::$custom_setup_page_link = \esc_url( \get_permalink( $custom_user_page_id ) );
					}
				}
			}

			return (string) apply_filters( WP_2FA_PREFIX . 'custom_setup_page_link', self::$custom_setup_page_link, $user );
		}

		/**
		 * Check all the roles for given setting
		 *
		 * @param string $setting_name - The name of the setting to check for.
		 *
		 * @return boolean
		 *
		 * @since 2.0.0
		 */
		public static function check_setting_in_all_roles( string $setting_name ): bool {
			$roles = WP_Helper::get_roles();

			foreach ( $roles as $role ) {
				$setting_value = WP2FA::get_wp2fa_setting( $setting_name, false, false, $role );
				if ( ! empty( $setting_value ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Return setting specific for the given role or default setting (based on user)
		 *
		 * @param string  $setting_name - The name of the setting.
		 * @param mixed   $user - \WP_User or any string or null - if string the current user will be used, if null global plugin setting will be used.
		 * @param mixed   $role - The name of the role (or null).
		 * @param boolean $get_default_on_empty - Get default setting on empty setting value.
		 * @param boolean $get_default_value - Extracts default value.
		 *
		 * @return mixed
		 *
		 * @since 2.0.0
		 */
		public static function get_role_or_default_setting( string $setting_name, $user = null, $role = null, $get_default_on_empty = false, $get_default_value = false ) {
			if ( null === $role ) {
				/**
				 * No user specified - get the default settings
				 */
				if ( null === $user || \WP_2FA_PREFIX . 'no-user' === $user ) {
					return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value );
				}

				/**
				 * There is an User - extract the role
				 */
				if ( $user instanceof \WP_User || is_int( $user ) ) {
					if ( null === $role ) {
						$role = User_Helper::get_user_role( $user );
					}
					return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value, $role );
				}

				/**
				 * No logged in current user, ergo no roles - fall back to defaults
				 */
				if ( 0 === User_Helper::get_user_object()->ID ) {
					return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value );
				}

				$role = User_Helper::get_user_role();
			}

			return WP2FA::get_wp2fa_setting( $setting_name, $get_default_on_empty, $get_default_value, $role );
		}

		/**
		 * Returns all the backup methods currently supported
		 *
		 * @return array
		 *
		 * @since 2.0.0
		 */
		public static function get_backup_methods(): array {

			if ( null === self::$backup_methods ) {

				/**
				 * Gives the ability to add additional backup methods
				 *
				 * @param array The array with all the backup methods currently supported.
				 *
				 * @since 2.0.0
				 */
				$methods = \apply_filters( WP_2FA_PREFIX . 'backup_methods_list', array() );

				if ( ! \is_array( $methods ) ) {
					$methods = array();
				}

				self::$backup_methods = array();
				foreach ( $methods as $slug => $definition ) {
					$slug = \is_string( $slug ) ? trim( $slug ) : '';
					if ( '' === $slug || ! self::is_valid_slug( $slug ) || ! \is_array( $definition ) ) {
						continue;
					}
					self::$backup_methods[ $slug ] = $definition;
				}
			}

			return self::$backup_methods;
		}

		/**
		 * Get backup methods enabled for user based on its role
		 *
		 * @param \WP_User $user - The WP user which we must check.
		 *
		 * @return array
		 *
		 * @since 2.0.0
		 */
		public static function get_enabled_backup_methods_for_user_role( \WP_User $user ): array {
			$backup_methods = self::get_backup_methods();

			/**
			 * Extensions could change the enabled backup methods array.
			 *
			 * @param array - Backup methods array.
			 * @param \WP_User - The user to check for.
			 *
			 * @since 2.0.0
			 */
			$backup_methods = apply_filters( WP_2FA_PREFIX . 'backup_methods_enabled', $backup_methods, $user );

			// Sanitize the backup methods array.
			return $backup_methods;
		}

		/**
		 * Returns all enabled providers for specific role
		 *
		 * @param string $role - The name of the role to check for.
		 *
		 * @return array
		 *
		 * @throws \Exception - if the role is wrong - throws an exception.
		 *
		 * @since 2.2.0
		 */
		public static function get_enabled_providers_for_role( string $role ) {

			if ( WP_Helper::is_role_exists( $role ) ) {
				self::get_all_roles_providers();

				return self::$all_providers_for_roles[ $role ];
			} elseif ( '' === $role ) {

				$providers = self::get_providers();

				$enabled = array();

				foreach ( $providers as $provider ) {
					$method = Methods_Helper::get_method_by_provider_name( $provider );
					if ( class_exists( '\WP2FA\Extensions\OutOfBand\Out_Of_Band', false ) && Out_Of_Band::METHOD_NAME === $provider ) {
						$method = Out_Of_Band::class;
					}
					if ( $method && $method::is_enabled() ) {
						$enabled[ $provider ] = true;
					}
				}

				return $enabled;
			} else {
				throw new \Exception( __( 'Role provided does not exist.', 'wp-2fa' ) );
			}
		}

		/**
		 * Checks if given provider is enabled for the given role.
		 *
		 * @param string $role - The name of the role.
		 * @param string $provider - The name of the provider.
		 *
		 * @return boolean
		 *
		 * @throws \Exception - If the provider is not registered in the plugin.
		 *
		 * @since 2.2.0
		 */
		public static function is_provider_enabled_for_role( string $role, string $provider ): bool {
			self::get_providers();

			if ( in_array( $provider, self::$all_providers, true ) ) {
				self::get_enabled_providers_for_role( $role );
				if ( isset( self::$all_providers_for_roles[ $role ][ $provider ] ) ) {
					return true;
				}

				return false;
			}

			throw new \Exception( __( 'Requested provider is not registered.', 'wp-2fa' ) );
		}

		/**
		 * Returns all providers by roles.
		 * If given role does not have specified settings set - falls back to the default settings.
		 *
		 * @return array
		 *
		 * @since 2.2.0
		 */
		public static function get_all_roles_providers() {
			if ( empty( self::$all_providers_for_roles ) ) {
				$roles     = WP_Helper::get_roles();
				$providers = self::get_providers();

				foreach ( $roles as $role ) {
					self::$all_providers_for_roles[ $role ] = array();
					foreach ( $providers as $provider ) {
						if ( Backup_Codes::METHOD_NAME === $provider ) {
							self::$all_providers_for_roles[ $role ][ $provider ] = self::normalize_setting_flag( WP2FA::get_wp2fa_setting( $provider . '_enabled', false, false, $role ) );
						} elseif ( 'backup_email' === $provider ) {
							self::$all_providers_for_roles[ $role ][ $provider ] = self::normalize_setting_flag( WP2FA::get_wp2fa_setting( 'enable-email-backup', false, false, $role ) );
						} elseif ( class_exists( '\WP2FA\Extensions\OutOfBand\Out_Of_Band', false ) && Out_Of_Band::METHOD_NAME === $provider ) {
							self::$all_providers_for_roles[ $role ][ $provider ] = self::normalize_setting_flag( WP2FA::get_wp2fa_setting( 'enable_' . $provider . '_email', false, false, $role ) );
						} else {
							self::$all_providers_for_roles[ $role ][ $provider ] = self::normalize_setting_flag( WP2FA::get_wp2fa_setting( 'enable_' . $provider, false, false, $role ) );
						}
					}
					self::$all_providers_for_roles[ $role ] = array_filter( self::$all_providers_for_roles[ $role ], 'boolval' );
				}
			}

			return self::$all_providers_for_roles;
		}

		/**
		 * Returns an array with all providers and their translated name. Key is the method slug and value is the translated method name.
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		public static function get_providers_translate_names(): array {
			if ( empty( self::$all_providers_names_translated ) ) {
				/**
				 * Filter the supplied providers.
				 *
				 * This lets third-parties either remove providers (such as Email), or
				 * add their own providers (such as text message or Clef).
				 *
				 * @param array $provider array if available options.
				 */
				$translated = apply_filters( WP_2FA_PREFIX . 'providers_translated_names', self::$all_providers_names_translated );
				if ( ! \is_array( $translated ) ) {
					$translated = array();
				}
				self::$all_providers_names_translated = array();
				foreach ( $translated as $slug => $label ) {
					$slug = \is_string( $slug ) ? trim( $slug ) : '';
					if ( '' === $slug || ! self::is_valid_slug( $slug ) ) {
						continue;
					}
					self::$all_providers_names_translated[ $slug ] = \wp_strip_all_tags( (string) $label );
				}
			}

			return array_map( 'esc_html', self::$all_providers_names_translated );
		}

		/**
		 * Grab list of all register providers in the plugin.
		 *
		 * @return array
		 *
		 * @since 2.2.0
		 */
		public static function get_providers() {
			if ( empty( self::$all_providers ) ) {
				/**
				 * Filter the supplied providers.
				 *
				 * This lets third-parties either remove providers (such as Email), or
				 * add their own providers (such as text message or Clef).
				 *
				 * @param array $provider array if available options.
				 */
				$providers = apply_filters( WP_2FA_PREFIX . 'providers', self::$all_providers );

				if ( ! \is_array( $providers ) ) {
					$providers = array();
				}

				self::$all_providers = self::sanitize_slug_list( $providers );
			}

			return self::$all_providers;
		}

		/**
		 * Returns the page ID stored in the given role or user, based on the multisite and page URL only.
		 *
		 * @param string       $role - The role name if any. Default fallback if not role no user is provided.
		 * @param \WP_User|int $user - The user object or user id if any.
		 *
		 * @return int
		 *
		 * @since 2.5.0
		 */
		public static function get_custom_settings_page_id( $role = '', $user = '' ) {
			if ( ! empty( $role ) ) {
				$page_slug = self::sanitize_page_slug( (string) self::get_role_or_default_setting( 'custom-user-page-url', '', $role ) );
			} elseif ( ! empty( $user ) ) {
				$page_slug = self::sanitize_page_slug( (string) self::get_role_or_default_setting( 'custom-user-page-url', $user ) );
			} else {
				$page_slug = self::sanitize_page_slug( (string) self::get_role_or_default_setting( 'custom-user-page-url', '', '' ) );
			}

			if ( ! empty( $role ) ) {
				$separate_page = self::sanitize_page_slug( (string) self::get_role_or_default_setting( 'separate-multisite-page-url', '', $role ) );
			} elseif ( ! empty( $user ) ) {
				$separate_page = self::sanitize_page_slug( (string) self::get_role_or_default_setting( 'separate-multisite-page-url', $user ) );
			} else {
				$separate_page = self::sanitize_page_slug( (string) self::get_role_or_default_setting( 'separate-multisite-page-url', '', '' ) );
			}

			$new_page_id = '';

			// Lets check for multisite first and if that is the case - lets search for that page on the user's default blog.
			if ( WP_Helper::is_multisite() && '' !== $separate_page ) {
				if ( ! empty( $user ) ) {
					$blog_id = (int) User_Helper::get_user_default_blog( $user );
				} else {
					$blog_id = (int) \get_current_blog_id();
				}

				if ( $blog_id <= 0 || ! \get_blog_details( $blog_id ) ) {
					$new_page_id = '';
				} else {
					// Switch to the blog context.
					\switch_to_blog( $blog_id );

					$page_exists = Settings_Page_Policies::get_post_by_post_name( $page_slug, 'page' );
					// Restore global context.
					\restore_current_blog();

					if ( false !== $page_exists ) {
						$new_page_id = $page_exists->ID;
					}
				}
			} else {
				$page_exists = Settings_Page_Policies::get_post_by_post_name( $page_slug, 'page' );
				if ( false !== $page_exists ) {
					$new_page_id = $page_exists->ID;
				}
			}

			return (int) $new_page_id;
		}

		/**
		 * Returns the setup page name
		 *
		 * @return string
		 *
		 * @since 2.2.0
		 */
		public static function get_setup_page_name() {
			return self::$setup_page_name;
		}

		/**
		 * Clears cached static settings allowing runtime refresh.
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		public static function reset_cached_settings(): void {
			// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			self::$settings_page_link = '';
			self::$setup_page_link = '';
			self::$custom_setup_page_link = null;
			self::$backup_methods = null;
			self::$all_providers = array();
			self::$all_providers_names_translated = array();
			self::$all_providers_for_roles = array();
			// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		}

		/**
		 * Determines whether the given slug follows the permitted format.
		 *
		 * @param string $slug Potential slug.
		 *
		 * @return bool
		 *
		 * @since 3.1.0
		 */
		private static function is_valid_slug( string $slug ): bool {
			$slug = trim( $slug );
			if ( '' === $slug ) {
				return false;
			}

			return (bool) preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $slug );
		}

		/**
		 * Sanitizes a list of slug strings returned from filters.
		 *
		 * @param array $items Raw provider list.
		 *
		 * @return array
		 *
		 * @since 3.1.0
		 */
		private static function sanitize_slug_list( array $items ): array {
			$sanitized = array();

			foreach ( $items as $class_name => $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}

				$item = trim( $item );
				if ( self::is_valid_slug( $item ) ) {
					$sanitized[ $class_name ] = $item;
				}
			}

			return array_unique( $sanitized );
		}

		/**
		 * Normalizes settings flags to booleans regardless of stored type.
		 *
		 * @param mixed $value Raw flag value.
		 *
		 * @return bool
		 *
		 * @since 3.1.0
		 */
		private static function normalize_setting_flag( $value ): bool {
			if ( is_bool( $value ) ) {
				return $value;
			}

			if ( is_numeric( $value ) ) {
				return (bool) intval( $value );
			}

			$providers                = self::get_providers();
			$enabled_provider_setting = array();
			foreach ( $providers as $provider ) {
				$enabled_provider_setting[] = 'enable_' . $provider;
			}

			if ( is_string( $value ) ) {
				$value = strtolower( trim( $value ) );
				return in_array( $value, array_merge( array( '1', 'true', 'yes', 'on', 'enable_oob_email', 'enable-email-backup', 'backup_codes_enabled' ), $enabled_provider_setting ), true );
			}

			return false;
		}

		/**
		 * Produces a safe slug for page lookups using WordPress sanitizer.
		 *
		 * @param string $slug Raw slug value.
		 *
		 * @return string
		 *
		 * @since 3.1.0
		 */
		private static function sanitize_page_slug( string $slug ): string {
			$slug = \sanitize_title( $slug );

			return (string) $slug;
		}
	}
}
