<?php
/**
 * Settings rendering class.
 *
 * @package    wp2fa
 * @subpackage settings
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Admin\SettingsPages\{
	Settings_Page_Policies,
	Settings_Page_Policies_New,
	Settings_Page_General,
	Settings_Page_Email,
	Settings_Page_New
};
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Licensing\Licensing_Factory;
use WP2FA\Admin\Helpers\Email_Templates;
use WP2FA\Admin\SettingsPages\Settings_Page_Render;
use WP2FA\Admin\SettingsPages\Settings_Page_Passkeys;
use WP2FA\Admin\SettingsPages\Settings_Page_White_Label;
use WP2FA\Admin\SettingsPages\Settings_Page_White_Labeling_New;

/**
 * Class for handling settings
 */
if ( ! class_exists( '\WP2FA\Admin\Settings_Page' ) ) {
	/**
	 * Class for handling settings
	 */
	class Settings_Page {

		const TOP_MENU_SLUG = 'wp-2fa-policies';

		/**
		 * Checks if the new interface is enabled.
		 *
		 * Returns true for fresh installs (setting not yet stored).
		 * Returns false for upgrades (migration sets it to false).
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		public static function is_new_interface_enabled(): bool {
			$setting = WP2FA::get_wp2fa_general_setting( 'use_new_interface', true );

			// Fresh installs: setting is not in DB yet → default to enabled.
			if ( null === $setting || '' === $setting ) {
				return true;
			}

			return Settings_Utils::string_to_bool( $setting );
		}

		/**
		 * Redirects users to the correct settings page when the interface mode changes.
		 *
		 * After toggling "Use new interface", WordPress redirects back to the
		 * previous page slug which no longer exists – causing a white-screen.
		 * This method runs on admin_init (before the page-access check) and
		 * sends the user to the matching page.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function redirect_on_interface_switch() {
			if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			$page              = \sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$use_new_interface = self::is_new_interface_enabled();
			$admin_url_func    = \is_network_admin() ? 'network_admin_url' : 'admin_url';

			// Old settings page requested but new interface is active → redirect.
			if ( 'wp-2fa-settings' === $page && $use_new_interface ) {
				\wp_safe_redirect( $admin_url_func( 'admin.php?page=wp-2fa-settings-new&settings-updated=true' ) );
				exit;
			}

			// New settings page requested but old interface is active → redirect.
			if ( 'wp-2fa-settings-new' === $page && ! $use_new_interface ) {
				\wp_safe_redirect( $admin_url_func( 'admin.php?page=wp-2fa-settings&settings-updated=true' ) );
				exit;
			}
		}

		/**
		 * Fallback redirect callback for a settings page whose slug is still
		 * registered but belongs to the inactive interface mode.
		 *
		 * Registered as the render callback of the hidden page so that if
		 * admin_init redirect did not fire (e.g. cached redirect from options.php),
		 * the user is still sent to the correct page instead of seeing a blank screen.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function redirect_to_active_settings_page() {
			$use_new_interface = self::is_new_interface_enabled();
			$target_page       = $use_new_interface ? 'wp-2fa-settings-new' : 'wp-2fa-settings';
			$admin_url_func    = \is_network_admin() ? 'network_admin_url' : 'admin_url';

			\wp_safe_redirect( $admin_url_func( 'admin.php?page=' . $target_page . '&settings-updated=true' ) );
			exit;
		}

		/**
		 * Create admin menu entry and settings page
		 */
		public static function create_settings_admin_menu() {
			$use_new_interface = self::is_new_interface_enabled();

			// Create admin menu item.
			\add_menu_page(
				\esc_html__( 'WP 2FA', 'wp-2fa' ),
				\esc_html__( 'WP 2FA', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				null,
				' data:image/svg+xml;base64,' . base64_encode( file_get_contents( WP_2FA_PATH . 'dist/images/wp-2fa-white-icon20x28.svg' ) ),
				81
			);

			if ( $use_new_interface ) {
				// New interface: "Policies New" takes the place of the default policies page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( '2FA policies', 'wp-2fa' ),
					\esc_html__( '2FA policies', 'wp-2fa' ),
					'manage_options',
					self::TOP_MENU_SLUG,
					array( Settings_Page_Policies_New::class, 'render' ),
					1
				);
			} else {
				// Old interface: original policies page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( '2FA policies', 'wp-2fa' ),
					\esc_html__( '2FA policies', 'wp-2fa' ),
					'manage_options',
					self::TOP_MENU_SLUG,
					array( Settings_Page_Policies::class, 'render' ),
					1
				);
			}

			\add_submenu_page(
				self::TOP_MENU_SLUG,
				\esc_html__( 'Passkeys', 'wp-2fa' ),
				\esc_html__( 'Passkeys', 'wp-2fa' ),
				'manage_options',
				Settings_Page_Passkeys::TOP_MENU_SLUG,
				array( Settings_Page_Passkeys::class, 'render' ),
				1
			);

			if ( ! $use_new_interface ) {
				// Old interface: show old settings page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
					\esc_html__( 'Settings', 'wp-2fa' ),
					'manage_options',
					'wp-2fa-settings',
					array( Settings_Page_Render::class, 'render' ),
					2
				);

				// Old interface: also show new pages as separate items.
				// \add_submenu_page(
				// self::TOP_MENU_SLUG,
				// \esc_html__( 'Settings New', 'wp-2fa' ),
				// \esc_html__( 'Settings New', 'wp-2fa' ),
				// 'manage_options',
				// 'wp-2fa-settings-new',
				// array( Settings_Page_New::class, 'render' ),
				// 3
				// );

				// \add_submenu_page(
				// self::TOP_MENU_SLUG,
				// \esc_html__( '2FA policies New', 'wp-2fa' ),
				// \esc_html__( '2FA policies New', 'wp-2fa' ),
				// 'manage_options',
				// Settings_Page_Policies_New::PAGE_SLUG,
				// array( Settings_Page_Policies_New::class, 'render' ),
				// 4
				// );
			} else {
				// New interface: separate White labeling sub-menu.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( 'White labeling', 'wp-2fa' ),
					\esc_html__( 'White labeling', 'wp-2fa' ),
					'manage_options',
					Settings_Page_White_Labeling_New::PAGE_SLUG,
					array( Settings_Page_White_Labeling_New::class, 'render' ),
					2
				);

				// New interface: show Settings New as the main "Settings" page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
					\esc_html__( 'Settings', 'wp-2fa' ),
					'manage_options',
					'wp-2fa-settings-new',
					array( Settings_Page_New::class, 'render' ),
					4
				);

				// Keep old slug registered (hidden) so options.php redirects
				// back to it don't hit a "not allowed" dead end.
				\add_submenu_page(
					'',
					'',
					'',
					'manage_options',
					'wp-2fa-settings',
					array( __CLASS__, 'redirect_to_active_settings_page' )
				);
			}

			// Register our policy settings.
			\register_setting(
				WP_2FA_POLICY_SETTINGS_NAME,
				WP_2FA_POLICY_SETTINGS_NAME,
				array( Settings_Page_Policies::class, 'validate_and_sanitize' )
			);

			// Register our passkeys settings.
			\register_setting(
				WP_2FA_PASSKEYS_SETTINGS_NAME,
				WP_2FA_PASSKEYS_SETTINGS_NAME,
				array( Settings_Page_Passkeys::class, 'validate_and_sanitize' )
			);

			// Register our white label settings.
			\register_setting(
				WP_2FA_WHITE_LABEL_SETTINGS_NAME,
				WP_2FA_WHITE_LABEL_SETTINGS_NAME,
				array( Settings_Page_White_Label::class, 'validate_and_sanitize' )
			);

			// Register our settings page.
			\register_setting(
				WP_2FA_SETTINGS_NAME,
				WP_2FA_SETTINGS_NAME,
				array( Settings_Page_General::class, 'validate_and_sanitize' )
			);

			\register_setting(
				WP_2FA_EMAIL_SETTINGS_NAME,
				WP_2FA_EMAIL_SETTINGS_NAME,
				array( Settings_Page_Email::class, 'validate_and_sanitize' )
			);

			// Register our "Settings New" option so Settings API calls on this page work.
			\register_setting(
				WP_2FA_NEW_SETTINGS_NAME,
				WP_2FA_NEW_SETTINGS_NAME,
				array( Settings_Page_New::class, 'validate_and_sanitize' )
			);

			/**
			 * Fires after the main menu settings are registered.
			 *
			 * @param string - The menu slug.
			 * @param bool - Is that multisite install or not.
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'after_admin_menu_created', self::TOP_MENU_SLUG, false );

			self::maybe_add_locked_reports_menu();

			\add_action( WP_2FA_PREFIX . 'before_plugin_settings', array( __CLASS__, 'check_email' ) );
		}

		/**
		 * Create admin menu entry and settings page
		 */
		public static function create_settings_admin_menu_multisite() {
			$use_new_interface = self::is_new_interface_enabled();

			// Create admin menu item.
			\add_menu_page(
				\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
				\esc_html__( 'WP 2FA', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				null,
				'data:image/svg+xml;base64,' . base64_encode( file_get_contents( WP_2FA_PATH . 'dist/images/wp-2fa-white-icon20x28.svg' ) ),
				81
			);

			if ( $use_new_interface ) {
				// New interface: "Policies New" takes the place of the default policies page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( '2FA policies', 'wp-2fa' ),
					\esc_html__( '2FA policies', 'wp-2fa' ),
					'manage_options',
					self::TOP_MENU_SLUG,
					array( Settings_Page_Policies_New::class, 'render' ),
					1
				);
			} else {
				// Old interface: original policies page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( '2FA policies', 'wp-2fa' ),
					\esc_html__( '2FA policies', 'wp-2fa' ),
					'manage_options',
					self::TOP_MENU_SLUG,
					array( Settings_Page_Policies::class, 'render' ),
					1
				);
			}

			\add_submenu_page(
				self::TOP_MENU_SLUG,
				\esc_html__( 'Passkeys', 'wp-2fa' ),
				\esc_html__( 'Passkeys', 'wp-2fa' ),
				'manage_options',
				Settings_Page_Passkeys::TOP_MENU_SLUG,
				array( Settings_Page_Passkeys::class, 'render' ),
				1
			);

			if ( ! $use_new_interface ) {
				// Old interface: show old settings page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
					\esc_html__( 'Settings', 'wp-2fa' ),
					'manage_options',
					'wp-2fa-settings',
					array( Settings_Page_Render::class, 'render' ),
					2
				);

				// Old interface: also show new pages as separate items.
				// \add_submenu_page(
				// self::TOP_MENU_SLUG,
				// \esc_html__( 'Settings New', 'wp-2fa' ),
				// \esc_html__( 'Settings New', 'wp-2fa' ),
				// 'manage_options',
				// 'wp-2fa-settings-new',
				// array( Settings_Page_New::class, 'render' ),
				// 3
				// );

				// \add_submenu_page(
				// self::TOP_MENU_SLUG,
				// \esc_html__( '2FA policies New', 'wp-2fa' ),
				// \esc_html__( '2FA policies New', 'wp-2fa' ),
				// 'manage_options',
				// Settings_Page_Policies_New::PAGE_SLUG,
				// array( Settings_Page_Policies_New::class, 'render' ),
				// 4
				// );
			} else {
				// New interface: separate White labeling sub-menu.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( 'White labeling', 'wp-2fa' ),
					\esc_html__( 'White labeling', 'wp-2fa' ),
					'manage_options',
					Settings_Page_White_Labeling_New::PAGE_SLUG,
					array( Settings_Page_White_Labeling_New::class, 'render' ),
					2
				);

				// New interface: show Settings New as the main "Settings" page.
				\add_submenu_page(
					self::TOP_MENU_SLUG,
					\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
					\esc_html__( 'Settings', 'wp-2fa' ),
					'manage_options',
					'wp-2fa-settings-new',
					array( Settings_Page_New::class, 'render' ),
					4
				);

				// Keep old slug registered (hidden) so options.php redirects
				// back to it don't hit a "not allowed" dead end.
				\add_submenu_page(
					null,
					'',
					'',
					'manage_options',
					'wp-2fa-settings',
					array( __CLASS__, 'redirect_to_active_settings_page' )
				);
			}

			/**
			 * Fires after the main menu settings are registered.
			 *
			 * @param string - The menu slug.
			 * @param bool - Is that multisite install or not.
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'after_admin_menu_created', self::TOP_MENU_SLUG, true );

			self::maybe_add_locked_reports_menu();
		}
		/**
		 * Send account unlocked notification via email.
		 *
		 * @param int $user_id user ID.
		 *
		 * @return boolean
		 */
		public static function send_account_unlocked_email( $user_id ) {
			// Bail if the user has not enabled this email.
			if ( 'enable_account_unlocked_email' !== Email_Templates::get_wp2fa_email_templates( 'send_account_unlocked_email' ) ) {
				return false;
			}

			// Grab user data.
			$user = get_userdata( $user_id );
			// Grab user email.
			$email = $user->user_email;
			// Setup the email contents.
			$subject = wp_strip_all_tags( Email_Templates::replace_email_strings( Email_Templates::get_wp2fa_email_templates( 'user_account_unlocked_email_subject' ) ) );
			$message = wpautop( Email_Templates::replace_email_strings( Email_Templates::get_wp2fa_email_templates( 'user_account_unlocked_email_body' ), $user_id ) );

			// @free:start
			$message .= '<p>' . \esc_html__( 'Email sent by', 'wp-2fa' );
			$message .= ' <a href="https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=melapress_wp_2fa_plugin_link" target="_blank">' . \esc_html__( 'WP 2FA plugin.', 'wp-2fa' ) . '</a>';
			$message .= '</p>';
			// @free:end

			return self::send_email( $email, $subject, $message );
		}

		/**
		 * Hide settings menu item
		 */
		public static function hide_settings() {
			$user = wp_get_current_user();

			// Check we have a user before doing anything else.
			if ( is_a( $user, '\WP_User' ) ) {
				if ( ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ) {
					$main_user = (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' );
				} else {
					$main_user = get_current_user_id();
				}
				if ( ! empty( WP2FA::get_wp2fa_general_setting( 'limit_access' ) ) && $user->ID !== $main_user ) {
					// Remove legacy admin menu item.
					remove_submenu_page( 'options-general.php', self::TOP_MENU_SLUG );

					// Remove all plugin submenu pages.
					remove_submenu_page( self::TOP_MENU_SLUG, self::TOP_MENU_SLUG );
					remove_submenu_page( self::TOP_MENU_SLUG, Settings_Page_Passkeys::TOP_MENU_SLUG );
					remove_submenu_page( self::TOP_MENU_SLUG, 'wp-2fa-settings' );
					remove_submenu_page( self::TOP_MENU_SLUG, 'wp-2fa-settings-new' );
					remove_submenu_page( self::TOP_MENU_SLUG, Settings_Page_White_Labeling_New::PAGE_SLUG );
					remove_submenu_page( self::TOP_MENU_SLUG, Docs_And_Support::TOP_MENU_SLUG );
					remove_submenu_page( self::TOP_MENU_SLUG, License_Page::PAGE_SLUG );

					// Remove the top-level menu item itself.
					remove_menu_page( self::TOP_MENU_SLUG );
				}
			}
		}

		/**
		 * Add unlock user link to user actions.
		 *
		 * @param array $links Default row content.
		 *
		 * @return array
		 * @throws \Freemius_Exception - freemius exception.
		 */
		public static function add_plugin_action_links( $links ) {
			// add link to the external free trial page in free version and also in premium version if license is not active.
			if ( ! Licensing_Factory::has_active_valid_license() ) {
				$trial_link = 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=upgrade_to_premium_menu';
				$links      = array_merge(
					array(
						'<a style="font-weight:bold" href="' . $trial_link . '" target="_blank">' . __( 'Upgrade to Premium', 'wp-2fa' ) . '</a>',
					),
					$links
				);
			}

			// add link to the plugin settings page.
			$url   = Settings::get_settings_page_link();
			$links = array_merge(
				array(
					'<a href="' . \esc_url( $url ) . '">' . \esc_html__( 'Configure 2FA Settings', 'wp-2fa' ) . '</a>',
				),
				$links
			);

			return $links;
		}

		/**
		 * Updates options for multisite
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function update_wp2fa_network_options() {

			Settings_Page_Policies::update_wp2fa_network_options();

			Settings_Page_General::update_wp2fa_network_options();

			Settings_Page_White_Label::update_wp2fa_network_options();

			Settings_Page_Passkeys::update_wp2fa_network_options();

			/**
			 * Gives the ability for extensions to set their settings in the plugin.
			 *
			 * @since 2.2.0
			 */
			\do_action( WP_2FA_PREFIX . 'update_network_settings' );
		}

		/**
		 * Handle saving email options to the network main site options.
		 */
		public static function update_wp2fa_network_email_options() {
			Settings_Page_Email::update_wp2fa_network_options();
		}

		/**
		 * Stores a network admin notice in a site transient keyed to the current user.
		 * This replaces passing messages via URL parameters to prevent reflected content injection.
		 *
		 * @param string $type    Notice type: 'success' or 'error'.
		 * @param string $message The notice message (already translated). Empty string for default error message.
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function set_network_admin_notice( string $type, string $message = '' ) {
			\set_site_transient(
				'wp_2fa_notice_' . \get_current_user_id(),
				array(
					'type'    => $type,
					'message' => $message,
				),
				30
			);
		}

		/**
		 * These are used instead of add_settings_error which in a network site. Used to show if settings have been updated or failed.
		 */
		public static function settings_saved_network_admin_notice() {
			$transient_key = 'wp_2fa_notice_' . \get_current_user_id();
			$notice        = \get_site_transient( $transient_key );

			if ( ! $notice || ! is_array( $notice ) || ! isset( $notice['type'] ) ) {
				return;
			}

			\delete_site_transient( $transient_key );

			$type    = $notice['type'];
			$message = isset( $notice['message'] ) ? $notice['message'] : '';

			if ( 'success' === $type ) {
				?>
			<div class="notice notice-success is-dismissible wp-2fa-admin-notice">
				<p><?php \esc_html_e( '2FA Settings Updated', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
				<?php
			} elseif ( 'error' === $type ) {
				?>
			<div class="notice notice-error is-dismissible wp-2fa-admin-notice">
				<?php if ( ! empty( $message ) ) { ?>
					<p><?php echo \esc_html( $message ); ?></p>
				<?php } else { ?>
					<p><?php \esc_html_e( 'Please ensure both custom email address and display name are provided.', 'wp-2fa' ); ?></p>
				<?php } ?>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
				<?php
			}
		}

		/**
		 * These are used instead of add_settings_error which in a network site. Used to show if settings have been updated or failed.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function settings_saved_admin_notice() {
			if ( isset( $_GET['page'] ) && 0 === strpos( \sanitize_text_field( \wp_unslash( $_GET['page'] ) ), 'wp-2fa-' ) ) {
				if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
					$wp_settings_errors = get_settings_errors();

					if ( count( $wp_settings_errors ) ) {
						foreach ( $wp_settings_errors as $error ) {
							?>
					<div class="notice notice-<?php echo \esc_attr( $error['type'] ); ?> is-dismissible wp-2fa-admin-notice">
						<p><?php echo \esc_html( $error['message'] ); ?></p>
						<button type="button" class="notice-dismiss">
							<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
						</button>
					</div>
							<?php
						}
					} else {
						?>
					<div class="notice notice-success is-dismissible wp-2fa-admin-notice">
						<p><?php \esc_html_e( '2FA Settings Updated', 'wp-2fa' ); ?></p>
						<button type="button" class="notice-dismiss">
							<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
						</button>
					</div>
						<?php
					}
				}
				if ( isset( $_GET['settings-updated'] ) && 'false' === $_GET['settings-updated'] ) {
					?>
				<div class="notice notice-error is-dismissible wp-2fa-admin-notice">
					<p><?php \esc_html_e( 'Please ensure both custom email address and display name are provided.', 'wp-2fa' ); ?></p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
					</button>
				</div>
					<?php
				}
			}
		}

		/**
		 * Add our custom state to our created page.
		 *
		 * @param array   $post_states - array with the post states.
		 * @param WP_Post $post - the WP post.
		 *
		 * @return array
		 */
		public static function add_display_post_states( $post_states, $post ) {
			if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
				if ( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) === $post->ID ) {
					$post_states['wp_2fa_page_for_user'] = __( 'WP 2FA User Page', 'wp-2fa' );
				}
			}

			return $post_states;
		}

		/**
		 * Handles sending of an email. It sets necessary header such as content type and custom from email address and name.
		 *
		 * @param string $recipient_email Email address to send message to.
		 * @param string $subject Email subject.
		 * @param string $message Message contents.
		 *
		 * @return bool Whether the email contents were sent successfully.
		 */
		public static function send_email( $recipient_email, $subject, $message ) {

			// Sanitize email inputs.
			$recipient_email = sanitize_email( $recipient_email );
			$subject = str_replace( array( "\r", "\n" ), '', \sanitize_text_field( $subject ) );
			$message         = wp_kses_post( $message );

			// Specify our desired headers.
			$headers = 'Content-type: text/html;charset=utf-8' . "\r\n";

			if ( 'use-custom-email' === Email_Templates::get_wp2fa_email_templates( 'email_from_setting' ) ) {
				$from_name  = sanitize_text_field( Email_Templates::get_wp2fa_email_templates( 'custom_from_display_name' ) );
				$from_email = sanitize_email( Email_Templates::get_wp2fa_email_templates( 'custom_from_email_address' ) );
				$headers   .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
			} else {
				$headers .= 'From: WP 2FA from Melapress <' . self::get_default_email_address() . '>' . "\r\n";
			}

			// Fire our email.
			return \wp_mail( $recipient_email, stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) ), $message, $headers );
		}

		/**
		 * Builds and returns the default email address used for the "from" email address when email is send
		 *
		 * @return string
		 *
		 * @since 2.6.4
		 */
		public static function get_default_email_address(): string {
			$sitename   = wp_parse_url( network_home_url(), PHP_URL_HOST );
			$from_email = 'wp2fa@';

			if ( ! empty( $sitename ) ) {
				if ( str_starts_with( $sitename, 'www.' ) ) {
					$sitename = substr( $sitename, 4 );
				}
				$from_email .= sanitize_text_field( $sitename );
			}

			return sanitize_email( $from_email );
		}

		/**
		 * Turns user roles data in any form and shape to an array of strings.
		 *
		 * @param mixed $value User role names (slugs) as raw value.
		 *
		 * @return string[] List of user role names (slugs).
		 *
		 * @since 3.0.0
		 */
		public static function extract_roles_from_input( $value ) {
			if ( is_array( $value ) ) {
				return array_map( 'sanitize_text_field', $value );
			}

			if ( is_string( $value ) && ! empty( $value ) ) {
				$roles = explode( ',', $value );
				return array_map( 'sanitize_text_field', $roles );
			}

			return array();
		}

		/**
		 * Checks the email against the current domain and shows an error message if they do not match.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function check_email() {
			$is_dismissed = (bool) Settings_Utils::get_option( 'dismiss_notice_mail_domain', false );
			if ( ! $is_dismissed ) {
				$admin_email = null;
				if ( 'use-custom-email' === Email_Templates::get_wp2fa_email_templates( 'email_from_setting' ) ) {
					$admin_email = sanitize_email( Email_Templates::get_wp2fa_email_templates( 'custom_from_email_address' ) );
				} else {
					$admin_email = self::get_default_email_address();
				}

				if ( '' === trim( (string) $admin_email ) ) {
					$email_settings_url = esc_url(
						add_query_arg(
							array(
								'page' => 'wp-2fa-settings',
								'tab'  => 'email-settings',
							),
							network_admin_url( 'admin.php' )
						)
					);
					?>
					<div class="notice notice-error wp-2fa-admin-notice">
						<p class="description"><?php esc_html_e( 'By default, the plugin uses ', 'wp-2fa' ); ?> <b><?php echo sanitize_email( self::get_default_email_address() ); ?></b> <?php esc_html_e( 'as the "from address" when sending emails with the 2FA code for users to log in. Do you want to keep using this or change it?', 'wp-2fa' );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( $email_settings_url ); ?>"><?php esc_html_e( 'Change it', 'wp-2fa' ); ?></a>
							<a class="button button-secondary wp-2fa-email-notice" style="margin-left:20px" href="#">
								<?php esc_html_e( 'Keep using it', 'wp-2fa' ); ?>
							</a>
						</p>
						
						<?php wp_nonce_field( 'wp2fa_dismiss_notice_mail_domain', 'wp2fa_dismiss_notice_mail_domain', false ); ?>
					</div>
					<?php
				} else {
					Settings_Utils::update_option( 'dismiss_notice_mail_domain', true );
				}
			}
		}

		/**
		 * Sets the email domain do not match setting as dismissed.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function dismiss_notice_mail_domain() {
			// Verify nonce.
			if ( isset( $_POST['nonce'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'wp2fa_dismiss_notice_mail_domain' ) ) {
				Settings_Utils::update_option( 'dismiss_notice_mail_domain', true );
				die();
			}

			die( 'Nonce verification failed!' );
		}

		/**
		 * Adds a locked "Reports" submenu item when no valid premium license is present.
		 *
		 * The menu item displays a lock icon and does nothing when clicked.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function maybe_add_locked_reports_menu() {
			if ( Licensing_Factory::has_active_valid_license() ) {
				return;
			}

			$lock_icon = '<span class="wp2fa-menu-lock-icon" aria-hidden="true"></span>';

			\add_submenu_page(
				self::TOP_MENU_SLUG,
				\esc_html__( 'Reports', 'wp-2fa' ),
				\esc_html__( 'Reports', 'wp-2fa' ) . $lock_icon,
				'manage_options',
				'wp-2fa-reports-locked',
				array( __CLASS__, 'render_locked_reports_page' ),
				3
			);

			\add_action( 'admin_head', array( __CLASS__, 'locked_reports_menu_styles' ) );
		}

		/**
		 * Outputs CSS for the locked Reports menu item lock icon.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function locked_reports_menu_styles() {
			?>
			<style>
			#adminmenu .wp-submenu a[href*="wp-2fa-reports-locked"] {
				cursor: pointer;
				position: relative;
				display: flex !important;
				align-items: center;
				justify-content: space-between;
			}
			#adminmenu .wp-submenu a[href*="wp-2fa-reports-locked"]:hover {
				color: #72aee6;
			}
			.wp2fa-menu-lock-icon {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 20px;
				height: 20px;
				background: #2c3338;
				border-radius: 4px;
				flex-shrink: 0;
				margin-left: 8px;
			}
			#adminmenu .wp-submenu li.current a[href*="wp-2fa-reports-locked"] .wp2fa-menu-lock-icon,
			#adminmenu .wp-submenu a.current[href*="wp-2fa-reports-locked"] .wp2fa-menu-lock-icon {
				background: #2271b1;
			}
			.wp2fa-menu-lock-icon::before {
				content: '';
				display: block;
				width: 12px;
				height: 12px;
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23c3c4c7'%3E%3Cpath d='M18 10h-1V7c0-2.8-2.2-5-5-5S7 4.2 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zM9 7c0-1.7 1.3-3 3-3s3 1.3 3 3v3H9V7zm9 13H6v-8h12v8zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z'/%3E%3C/svg%3E");
				background-size: contain;
				background-repeat: no-repeat;
				background-position: center;
			}
			#adminmenu .wp-submenu li.current a[href*="wp-2fa-reports-locked"] .wp2fa-menu-lock-icon::before,
			#adminmenu .wp-submenu a.current[href*="wp-2fa-reports-locked"] .wp2fa-menu-lock-icon::before {
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M18 10h-1V7c0-2.8-2.2-5-5-5S7 4.2 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zM9 7c0-1.7 1.3-3 3-3s3 1.3 3 3v3H9V7zm9 13H6v-8h12v8zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z'/%3E%3C/svg%3E");
			}

			.wp2fa-reports-teaser-wrap {
				max-width: 100%;
				margin-top: 20px;
				margin-right: 20px;
			}
			.wp2fa-reports-teaser-card {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 36px;
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				padding: 28px;
			}
			.wp2fa-reports-teaser-title-wrap {
				display: flex;
				align-items: center;
				gap: 14px;
				margin-bottom: 18px;
			}
			.wp2fa-reports-teaser-icon {
				width: 52px;
				height: 52px;
				border-radius: 8px;
				object-fit: contain;
			}
			.wp2fa-reports-teaser-title {
				margin: 0;
				font-size: 22px;
				line-height: 1.22;
				font-weight: 700;
				color: #3c434a;
			}
			.wp2fa-reports-teaser-content {
				font-size: 14px;
			}
			.wp2fa-reports-teaser-title span {
				color: #2271b1;
			}
			.wp2fa-reports-teaser-description {
				margin: 0 0 26px;
				font-size: 14px;
				line-height: 1.45;
				font-weight: 400;
				color: #50575e;
			}
			.wp2fa-reports-teaser-list {
				list-style: none;
				margin: 0 0 28px;
				padding: 0;
				display: flex;
				flex-direction: column;
				gap: 14px;
			}
			.wp2fa-reports-teaser-list li {
				display: flex;
				align-items: flex-start;
				gap: 10px;
			}
			.wp2fa-reports-teaser-list-icon {
				width: 32px;
				height: 32px;
				border-radius: 50%;
				background: #2271b1;
				flex-shrink: 0;
				margin-top: 2px;
				position: relative;
			}
			.wp2fa-reports-teaser-list-icon::before {
				content: '';
				position: absolute;
				top: 50%;
				left: 50%;
				width: 17px;
				height: 17px;
				transform: translate(-50%, -50%);
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E");
				background-repeat: no-repeat;
				background-size: contain;
			}
			.wp2fa-reports-teaser-point-title {
				display: block;
				font-size: 16px;
				line-height: 1.2;
				font-weight: 600;
				color: #3c434a;
			}
			.wp2fa-reports-teaser-point-description {
				display: block;
				margin-top: 2px;
				font-size: 14px;
				line-height: 1.34;
				color: #646970;
			}
			.wp2fa-reports-teaser-cta {
				display: inline-block;
				padding: 9px 18px;
				border-radius: 7px;
				background: #2271b1;
				border: 1px solid #2271b1;
				color: #fff;
				font-size: 17px;
				font-weight: 500;
				text-decoration: none;
			}
			.wp2fa-reports-teaser-cta:hover,
			.wp2fa-reports-teaser-cta:focus {
				background: #135e96;
				border-color: #135e96;
				color: #fff;
			}
			.wp2fa-reports-teaser-preview {
				background: #efeff0;
				border-radius: 10px;
				padding: 16px;
				overflow: hidden;
			}
			.wp2fa-reports-teaser-preview img {
				width: 100%;
				height: auto;
				display: block;
				border-radius: 8px;
				opacity: 0.94;
				margin-left: 60px;
			}

			@media screen and (max-width: 980px) {
				.wp2fa-reports-teaser-card {
					grid-template-columns: 1fr;
				}
				.wp2fa-reports-teaser-preview img {
					margin-left: 0;
				}
			}
			</style>
			<?php
		}

		/**
		 * Renders teaser page for the locked reports menu item.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function render_locked_reports_page() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			?>
			<div class="wp2fa-reports-teaser-wrap">
				<div class="wp2fa-reports-teaser-card">
					<div class="wp2fa-reports-teaser-content">
						<div class="wp2fa-reports-teaser-title-wrap">
							<svg class="wp2fa-reports-teaser-icon" xmlns="http://www.w3.org/2000/svg" width="33" height="49" viewBox="0 0 33 49" fill="none" aria-hidden="true" focusable="false">
								<path d="M10.7607 16.2846L18.7944 8.17949L26.9017 0H13.414H0V8.17949V16.2846L2.65332 13.6077L5.38035 10.8564L8.03367 13.6077L10.7607 16.2846Z" fill="#99FFFF"/>
								<path d="M32.2094 48.9278V37.997V27.1406L21.5224 37.997L10.7617 48.7791L21.5224 48.8534L32.2094 48.9278Z" fill="#3E6BFF"/>
								<path d="M10.7607 27.1406L8.03367 24.3893L5.38035 21.7124L2.65332 18.9611L0 16.2841V27.1406V37.997L2.65332 35.2457L5.38035 32.5688L8.03367 35.2457L10.7607 37.997L18.7944 29.8175L26.9017 21.7124L29.555 24.3893L32.2084 27.1406V16.2841V5.42773L21.5214 16.2841L10.7607 27.1406Z" fill="#40D3F0"/>
							</svg>
							<h1 class="wp2fa-reports-teaser-title"><?php echo \esc_html__( 'Unlock 2FA Reports & Statistics with Premium', 'wp-2fa' ); ?></h1>
						</div>

						<p class="wp2fa-reports-teaser-description"><?php echo \esc_html__( 'Get a clear overview of two-factor authentication adoption across your website and identify users who are not yet protected.', 'wp-2fa' ); ?></p>
						<p class="wp2fa-reports-teaser-description"><?php echo \esc_html__( 'WP 2FA Premium includes detailed reports and statistics that help you monitor 2FA usage, track adoption by user role, review authentication methods in use, and export data for auditing and compliance purposes.', 'wp-2fa' ); ?></p>

						<ul class="wp2fa-reports-teaser-list">
							<li>
								<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
								<span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Monitor 2FA adoption across your entire website', 'wp-2fa' ); ?></span>
								</span>
							</li>
							<li>
								<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
								<span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Identify users and roles that have not yet configured 2FA', 'wp-2fa' ); ?></span>
								</span>
							</li>
							<li>
								<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
								<span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Track which authentication methods users are relying on', 'wp-2fa' ); ?></span>
								</span>
							</li>
							<li>
								<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
								<span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Export reports as CSV for auditing, compliance, and internal reviews', 'wp-2fa' ); ?></span>
								</span>
							</li>
							<li>
								<span class="wp2fa-reports-teaser-list-icon" aria-hidden="true"></span>
								<span>
									<span class="wp2fa-reports-teaser-point-description"><?php echo \esc_html__( 'Measure the impact of your 2FA rollout and security policies', 'wp-2fa' ); ?></span>
								</span>
							</li>
						</ul>

						<a class="wp2fa-reports-teaser-cta" href="<?php echo \esc_url( 'https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=free-reports-page' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html__( 'Unlock 2FA Reports & Statistics', 'wp-2fa' ); ?></a>
					</div>

					<div class="wp2fa-reports-teaser-preview" aria-hidden="true">
						<img src="<?php echo \esc_url( WP_2FA_URL . 'includes/assets/images/reports-teaser-preview.png' ); ?>" alt="<?php echo \esc_attr__( 'Reports page preview', 'wp-2fa' ); ?>" />
					</div>
				</div>
			</div>
			<?php
		}
	}
}
