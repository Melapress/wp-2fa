<?php
/**
 * WP 2FA - Profile Section Renderer
 *
 * Assembles all data needed for the profile templates and renders them.
 * Replaces the old inline HTML approach with a template-based system.
 *
 * Works in both admin (profile page) and frontend (shortcode) contexts.
 *
 * @package    wp2fa
 * @subpackage profile
 * @since      4.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 */

declare( strict_types=1 );

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Utils\User_Utils;
use WP2FA\Extensions_Loader;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Freemius\User_Licensing;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Extensions\Zero_Setup_Email\Zero_Setup_Email;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP2FA\Admin\Profile_Section_Renderer' ) ) {

	/**
	 * Renders the new card-based profile 2FA section.
	 *
	 * @since 4.0.0
	 */
	class Profile_Section_Renderer {

		/**
		 * Script handle for the profile section JS.
		 */
		const SCRIPT_HANDLE = 'wp2fa-profile-section';

		/**
		 * Style handle for the profile section CSS.
		 */
		const STYLE_HANDLE = 'wp2fa-profile-section';

		/**
		 * Template directory path (with trailing separator).
		 *
		 * @var string
		 */
		private static $template_dir = '';

		/**
		 * Get the template directory path.
		 *
		 * @return string
		 */
		private static function get_template_dir(): string {
			if ( empty( self::$template_dir ) ) {
				self::$template_dir = WP_2FA_PATH . 'templates' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
			}

			return self::$template_dir;
		}

		/**
		 * Enqueue CSS and JS for the profile section.
		 *
		 * Can be called from admin hooks or frontend shortcode context.
		 *
		 * @param bool $is_frontend Whether this is a frontend (shortcode) context.
		 *
		 * @return void
		 */
		public static function enqueue_assets( bool $is_frontend = false ): void {
			$plugin_url = WP_2FA_URL;
			$version    = WP_2FA_VERSION;

			// CSS.
			\wp_enqueue_style(
				self::STYLE_HANDLE,
				$plugin_url . 'css/wp2fa-profile.css',
				array(),
				$version
			);

			// JS.
			\wp_enqueue_script(
				self::SCRIPT_HANDLE,
				$plugin_url . 'js/profile/wp2fa-profile.js',
				array(),
				$version,
				true
			);

			// Backup codes profile JS.
			\wp_enqueue_script(
				'wp2fa-profile-backup-codes',
				$plugin_url . 'js/profile/wp2fa-profile-backup-codes.js',
				array( self::SCRIPT_HANDLE ),
				$version,
				true
			);

			// Localized data for the profile JS.
			$user = \wp_get_current_user();

			$localized = array(
				'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'showQrCode'            => \esc_html__( 'Show QR code', 'wp-2fa' ),
					'hideQrCode'            => \esc_html__( 'Hide QR code', 'wp-2fa' ),
					'copied'                => \esc_html__( 'Copied!', 'wp-2fa' ),
					'yourBackupCodes'       => '', //\esc_html__( 'Your backup codes', 'wp-2fa' ),
					'backupCodesGenerated'  => WP2FA::get_wp2fa_white_label_setting( 'backup_codes_generated', true ) ?: \esc_html__( 'Store these codes somewhere safe. Each code can only be used once.', 'wp-2fa' ),
					'backupCodesEmailSent'  => \esc_html__( 'Codes sent to your email.', 'wp-2fa' ),
					'backupCodesEmailError' => \esc_html__( 'Failed to send email.', 'wp-2fa' ),
					'networkError'          => \esc_html__( 'Network error. Please try again.', 'wp-2fa' ),
				),
			);

			// Auto-open the wizard if the user needs to configure 2FA instantly.
			if ( $user && $user->ID ) {
				$show_setup = false;
				if ( isset( $_GET['show'] ) && 'wp-2fa-setup' === $_GET['show'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$show_setup = true;
				} elseif ( User_Helper::get_user_enforced_instantly( $user ) ) {
					$show_setup = true;
				}

				$show_enable2fa = \apply_filters( WP_2FA_PREFIX . 'enable_2fa_user_setting', true, $user->ID );
				if ( $show_setup && $show_enable2fa ) {
					$localized['autoOpenWizard'] = true;
				}
			}

			/**
			 * Filter: wp_2fa_profile_localized_data
			 *
			 * Allows extensions to add data to the profile section JS.
			 *
			 * @param array $localized The localized data array.
			 *
			 * @since 4.0.0
			 */
			$localized = \apply_filters( WP_2FA_PREFIX . 'profile_localized_data', $localized );

			\wp_localize_script( self::SCRIPT_HANDLE, 'wp2faProfileData', $localized );

			/**
			 * Action: wp_2fa_profile_enqueue_assets
			 *
			 * Allows extensions to enqueue their own profile section assets.
			 *
			 * @param string $plugin_url  The plugin root URL.
			 * @param string $version     The plugin version.
			 * @param bool   $is_frontend Whether this is a frontend context.
			 *
			 * @since 4.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'profile_enqueue_assets', $plugin_url, $version, $is_frontend );
		}

		/**
		 * Render the profile section.
		 *
		 * This is the main entry point. It assembles the template data and includes
		 * the main template file.
		 *
		 * @param \WP_User $user            The user whose profile is being displayed.
		 * @param array    $additional_args  Extra arguments (is_shortcode, show_preamble, options).
		 *
		 * @return void
		 */
		public static function render( \WP_User $user, array $additional_args = array() ): void {

			// Ensure we have something in the settings — but only bail if the plugin
			// was never configured (no settings hash). If settings got wiped after
			// initial configuration, the init() fallback restores them.
			if ( empty( Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME ) ) && ! Settings_Utils::get_option( WP_2FA_PREFIX . 'settings_hash' ) ) {
				return;
			}

			$show_preamble = true;
			if ( isset( $additional_args['show_preamble'] ) ) {
				$show_preamble = \filter_var( $additional_args['show_preamble'], FILTER_VALIDATE_BOOLEAN );
			}

			$user_type = User_Utils::determine_user_2fa_status( $user );

			// Orphan user override.
			if ( in_array( 'orphan_user', $user_type, true ) ) {
				$additional_args['is_shortcode'] = true;
			}

			$data = self::build_template_data( $user, $user_type, $additional_args, $show_preamble );

			/**
			 * Filter: wp_2fa_profile_template_data
			 *
			 * Allows extensions to modify the template data before rendering.
			 *
			 * @param array    $data       The template data array.
			 * @param \WP_User $user       The target user.
			 * @param array    $user_type  The user's 2FA status array.
			 *
			 * @since 4.0.0
			 */
			$data = \apply_filters( WP_2FA_PREFIX . 'profile_template_data', $data, $user, $user_type );

			$template_file = self::get_template_dir() . 'main.php';

			/**
			 * Filter: wp_2fa_profile_template_file
			 *
			 * Allows overriding the main profile template file path.
			 *
			 * @param string $template_file Absolute path to the main template.
			 *
			 * @since 4.0.0
			 */
			$template_file = \apply_filters( WP_2FA_PREFIX . 'profile_template_file', $template_file );

			if ( \file_exists( $template_file ) ) {
				include $template_file;
			}
		}

		/**
		 * Build the complete template data array.
		 *
		 * Consolidates all the logic from the old user_2fa_options method.
		 *
		 * @param \WP_User $user            The target user.
		 * @param array    $user_type       User 2FA status flags.
		 * @param array    $additional_args Extra arguments.
		 * @param bool     $show_preamble   Whether to show heading/description.
		 *
		 * @return array<string,mixed>
		 */
		private static function build_template_data( \WP_User $user, array $user_type, array $additional_args, bool $show_preamble ): array {

			$description  = WP2FA::get_wp2fa_white_label_setting( 'user-profile-form-preamble-desc', true );
			$is_shortcode = isset( $additional_args['is_shortcode'] ) && $additional_args['is_shortcode'];
			$viewing_own  = in_array( 'viewing_own_profile', $user_type, true );
			$is_admin     = in_array( 'can_manage_options', $user_type, true );
			$is_excluded  = in_array( 'user_is_excluded', $user_type, true );

			// Orphan user messages.
			if ( in_array( 'orphan_user', $user_type, true ) ) {
				if ( User_Utils::in_array_all( array( 'user_needs_to_setup_2fa', 'can_manage_options' ), $user_type ) ) {
					$description = \esc_html__( 'This user is required to setup 2FA but has not yet done so.', 'wp-2fa' );
				}
				if ( User_Utils::in_array_all( array( 'user_is_excluded', 'can_manage_options' ), $user_type ) ) {
					$description = \esc_html__( 'This user is excluded from configuring 2FA.', 'wp-2fa' );
				}
			}

			// Excluded user: filter for extra content.
			$excluded_content = '';
			if ( $is_excluded || \current_user_can( 'manage_options' ) ) {
				/** This filter is documented in class-user-profile.php */
				$excluded_content = \esc_html__( 'This user is excluded from configuring 2FA.', 'wp-2fa' );
			}

			// Determine enabled methods and labels.
			$enabled_method     = User_Helper::get_enabled_method_for_user( $user );
			$has_enabled_method = ! empty( $enabled_method );
			$primary_label      = \esc_html__( 'No enabled primary method', 'wp-2fa' );

			if ( $has_enabled_method ) {
				$translated_methods = Settings::get_providers_translate_names();
				$primary_label      = isset( $translated_methods[ $enabled_method ] ) ? $translated_methods[ $enabled_method ] : $primary_label;
			}

			// Backup methods.
			$enabled_backup_methods = User_Helper::get_enabled_backup_methods_for_user( $user );
			$backup_methods_label   = \esc_html__( 'No enabled backup methods', 'wp-2fa' );
			if ( ! empty( $enabled_backup_methods ) ) {
				$backup_methods_label = \implode( ', ', $enabled_backup_methods );
			}

			// Show "Currently configured" summary?
			$show_enabled = true;
			if ( isset( $additional_args['options']['do_not_show_enabled'] ) && false !== (bool) $additional_args['options']['do_not_show_enabled'] ) {
				$show_enabled = false;
			}

			// Build specific card data.
			$setup_card_data  = self::build_setup_card_data( $user, $user_type, $additional_args );
			$backup_card_data = self::build_backup_card_data( $user, $user_type );
			$admin_data       = self::build_admin_actions_data( $user, $user_type );
			$totp_data        = self::build_totp_data( $user, $user_type, $enabled_method );

			// Extra content from filters.
			$form_content = '';
			/** This filter is documented in class-user-profile.php */
			$extra_content = \apply_filters( WP_2FA_PREFIX . 'append_to_profile_form_content', $form_content, $user );

			// Show cards?
			$show_setup_card  = $setup_card_data['show_change_btn'] || $setup_card_data['show_configure_btn'] || $setup_card_data['show_remove_btn'];
			$show_backup_card = $viewing_own && $has_enabled_method && ( $backup_card_data['show_generate_codes'] || ! empty( $backup_card_data['extra_buttons'] ) );

			// Premium enforcement check.
			if ( ! $has_enabled_method && class_exists( '\WP2FA\Extensions\Zero_Setup_Email\Zero_Setup_Email' ) && Zero_Setup_Email::is_enforced() ) {
				User_Helper::run_user_enforcement_check( $user );
			}

			return array(
				'user'                 => $user,
				'user_id'              => $user->ID,
				'show_preamble'        => $show_preamble,
				'preamble_title'       => WP2FA::get_wp2fa_white_label_setting( 'user-profile-form-preamble-title', true ),
				'preamble_desc'        => $description,
				'is_excluded'          => $is_excluded,
				'excluded_content'     => $excluded_content,
				'has_enabled_methods'  => $has_enabled_method,
				'primary_label'        => $primary_label,
				'backup_methods_label' => $backup_methods_label,
				'show_enabled'         => $show_enabled,
				'show_setup_card'      => $show_setup_card,
				'show_backup_card'     => $show_backup_card,
				'show_admin_actions'   => ! empty( $admin_data['reset_url'] ) || ! empty( $admin_data['temp_remove_url'] ) || ! empty( $admin_data['unlock_url'] ),
				'setup_card_data'      => $setup_card_data,
				'backup_card_data'     => $backup_card_data,
				'admin_actions_data'   => $admin_data,
				'totp_data'            => $totp_data,
				'extra_content'        => $extra_content,
				'user_type'            => $user_type,
				'styling_class'        => ( empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ) ? 'default_styling' : 'enable_styling',
			);
		}

		/**
		 * Build data for the 2FA Setup card.
		 *
		 * @param \WP_User $user            The target user.
		 * @param array    $user_type       User 2FA status flags.
		 * @param array    $additional_args Extra arguments.
		 *
		 * @return array
		 */
		private static function build_setup_card_data( \WP_User $user, array $user_type, array $additional_args ): array {
			$show_change_btn    = false;
			$show_configure_btn = false;
			$show_remove_btn    = false;
			$change_label       = \esc_html__( 'Change 2FA Settings', 'wp-2fa' );
			$configure_label    = \esc_html__( 'Configure 2FA', 'wp-2fa' );

			$viewing_own = in_array( 'viewing_own_profile', $user_type, true );

			/** This filter is documented in class-user-profile.php */
			$show_enable2fa = \apply_filters( WP_2FA_PREFIX . 'enable_2fa_user_setting', true, $user->ID );

			/** This filter is documented in class-user-profile.php */
			\apply_filters( WP_2FA_PREFIX . 'enable_2fa_user_setting_description', '' );

			// Premium: license/enforcement overrides.
			// When quota is exceeded or no license is present, fall back to free
			// version behaviour — all users can still configure free methods.
			if ( class_exists( '\WP2FA\Extensions_Loader' ) && Extensions_Loader::use_proxytron() ) {
				if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) && User_Licensing::quota_check() ) {
					$show_enable2fa = true;
				}
			}

			if ( $viewing_own ) {
				// User has an enabled method - show "Change" and optionally "Remove".
				if (
					User_Utils::in_array_all( array( 'has_enabled_methods' ), $user_type ) ||
					User_Utils::in_array_all( array( 'no_required_has_enabled' ), $user_type )
				) {
					if ( $show_enable2fa ) {
						$show_change_btn = true;
					}
					if ( User_Profile::can_user_remove_2fa( $user->ID ) ) {
						$show_remove_btn = true;
					}
				}

				// User needs to setup 2FA - show "Configure".
				$show_if_user_is_not_in = array(
					'user_is_excluded',
					'has_enabled_methods',
					'no_required_has_enabled',
				);

				if (
					User_Utils::in_array_all( array( 'user_needs_to_setup_2fa' ), $user_type ) ||
					User_Utils::role_is_not( $show_if_user_is_not_in, $user_type )
				) {
					if ( $show_enable2fa ) {
						$show_configure_btn = true;
					}
				}
			}

			return array(
				'show_change_btn'    => $show_change_btn,
				'show_configure_btn' => $show_configure_btn,
				'show_remove_btn'    => $show_remove_btn,
				'change_label'       => $change_label,
				'configure_label'    => $configure_label,
			);
		}

		/**
		 * Build data for the Backup 2FA Method card.
		 *
		 * @param \WP_User $user      The target user.
		 * @param array    $user_type User 2FA status flags.
		 *
		 * @return array
		 */
		private static function build_backup_card_data( \WP_User $user, array $user_type ): array {
			$show_generate_codes = false;
			$codes_remaining     = -1;
			$backup_codes_nonce  = '';
			$extra_buttons       = '';

			$role = User_Helper::get_user_role( $user );

			if ( class_exists( '\WP2FA\Methods\Backup_Codes' ) && Backup_Codes::are_backup_codes_enabled_for_role( $role ) ) {
				$show_generate_codes = true;
				$codes_remaining     = Backup_Codes::codes_remaining_for_user( $user );
				$backup_codes_nonce  = \wp_create_nonce( 'wp-2fa-backup-codes-generate-json-' . $user->ID );
			}

			/**
			 * Add an option for external providers to add their own form buttons.
			 *
			 * @since 2.0.0
			 */
			$extra_buttons = \apply_filters( WP_2FA_PREFIX . 'additional_form_buttons', $extra_buttons );

			return array(
				'show_generate_codes' => $show_generate_codes,
				'codes_remaining'     => $codes_remaining,
				'backup_codes_nonce'  => $backup_codes_nonce,
				'extra_buttons'       => $extra_buttons,
			);
		}

		/**
		 * Build data for admin-only actions section.
		 *
		 * @param \WP_User $user      The target user.
		 * @param array    $user_type User 2FA status flags.
		 *
		 * @return array
		 */
		private static function build_admin_actions_data( \WP_User $user, array $user_type ): array {
			$reset_url       = '';
			$temp_remove_url = '';
			$unlock_url      = '';
			$description     = '';

			$viewing_own = in_array( 'viewing_own_profile', $user_type, true );
			$is_admin    = in_array( 'can_manage_options', $user_type, true );

			// Admin viewing another user's profile with 2FA configured.
			if ( $is_admin && in_array( 'has_enabled_methods', $user_type, true ) && ! $viewing_own ) {
				$description = \esc_html__( 'The user has already configured 2FA. When you reset the user\'s current 2FA configuration, the user can log back in with just the username and password.', 'wp-2fa' );

				$reset_url = \add_query_arg(
					array(
						'action'       => 'remove_user_2fa',
						'user_id'      => $user->ID,
						'wp_2fa_nonce' => \wp_create_nonce( 'wp-2fa-remove-user-2fa-nonce' ),
						'admin_reset'  => 'yes',
					),
					\admin_url( 'user-edit.php' )
				);
			}

			// Premium: temporary removal.
			if ( class_exists( '\WP2FA\Admin\Helpers\User_Helper' ) ) {
				if ( method_exists( '\WP2FA\Admin\Helpers\User_Helper', 'is_2fa_for_user_temporary_removed' ) && ! User_Helper::is_2fa_for_user_temporary_removed() && ! $viewing_own && ! empty( User_Helper::get_enabled_method_for_user( $user ) ) ) {
					$temp_remove_url = \wp_nonce_url( 'users.php?action=remove-2fa-temporary&users=' . $user->ID, 'bulk-users' );
				}
			}

			// Admin viewing user whose grace period has expired.
			if ( User_Utils::in_array_all( array( 'can_manage_options', 'grace_has_expired' ), $user_type ) ) {
				$unlock_url = \add_query_arg(
					array(
						'action'       => 'unlock_account',
						'user_id'      => $user->ID,
						'wp_2fa_nonce' => \wp_create_nonce( 'wp-2fa-unlock-account-nonce' ),
					),
					\admin_url( 'user-edit.php' )
				);
			}

			return array(
				'description'     => $description,
				'reset_url'       => $reset_url,
				'temp_remove_url' => $temp_remove_url,
				'unlock_url'      => $unlock_url,
			);
		}

		/**
		 * Build TOTP-specific data (QR code, key).
		 *
		 * @param \WP_User $user           The target user.
		 * @param array    $user_type      User 2FA status flags.
		 * @param string   $enabled_method The user's currently enabled method.
		 *
		 * @return array
		 */
		private static function build_totp_data( \WP_User $user, array $user_type, string $enabled_method ): array {
			$viewing_own = in_array( 'viewing_own_profile', $user_type, true );
			$is_admin    = in_array( 'can_manage_options', $user_type, true );
			$has_enabled = in_array( 'has_enabled_methods', $user_type, true );

			if ( ( $viewing_own || $is_admin ) && $has_enabled && class_exists( '\WP2FA\Methods\TOTP' ) && TOTP::METHOD_NAME === $enabled_method ) {
				// Ensure TOTP methods resolve against the target user (important when admin views another user).
				User_Helper::set_user( $user );

				return array(
					'show_qr'     => true,
					'qr_code_url' => TOTP::get_qr_code(),
					'totp_key'    => TOTP::get_totp_decrypted(),
				);
			}

			return array(
				'show_qr' => false,
			);
		}
	}
}
