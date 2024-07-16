<?php
/**
 * Settings rendering class.
 *
 * @package    wp2fa
 * @subpackage settings
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Admin\SettingsPages\{
	Settings_Page_Policies,
	Settings_Page_General,
	Settings_Page_Email
};
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Utils\Settings_Utils;

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
		 * Create admin menu entry and settings page
		 */
		public static function create_settings_admin_menu() {
			// Create admin menu item.
			\add_menu_page(
				\esc_html__( 'WP 2FA', 'wp-2fa' ),
				\esc_html__( 'WP 2FA', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				null,
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( WP_2FA_PATH . 'dist/images/wp-2fa-white-icon20x28.svg' ) ), // phpcs:ignore
				81
			);

			\add_submenu_page(
				self::TOP_MENU_SLUG,
				\esc_html__( '2FA Policies', 'wp-2fa' ),
				\esc_html__( '2FA Policies', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				array( \WP2FA\Admin\SettingsPages\Settings_Page_Policies::class, 'render' ),
				1
			);

			\add_submenu_page(
				self::TOP_MENU_SLUG,
				\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
				\esc_html__( 'Settings', 'wp-2fa' ),
				'manage_options',
				'wp-2fa-settings',
				array( \WP2FA\Admin\SettingsPages\Settings_Page_Render::class, 'render' ),
				2
			);

			// Register our policy settings.
			\register_setting(
				WP_2FA_POLICY_SETTINGS_NAME,
				WP_2FA_POLICY_SETTINGS_NAME,
				array( \WP2FA\Admin\SettingsPages\Settings_Page_Policies::class, 'validate_and_sanitize' )
			);

			// Register our white label settings.
			\register_setting(
				WP_2FA_WHITE_LABEL_SETTINGS_NAME,
				WP_2FA_WHITE_LABEL_SETTINGS_NAME,
				array( \WP2FA\Admin\SettingsPages\Settings_Page_White_Label::class, 'validate_and_sanitize' )
			);

			// Register our settings page.
			\register_setting(
				WP_2FA_SETTINGS_NAME,
				WP_2FA_SETTINGS_NAME,
				array( \WP2FA\Admin\SettingsPages\Settings_Page_General::class, 'validate_and_sanitize' )
			);

			\register_setting(
				WP_2FA_EMAIL_SETTINGS_NAME,
				WP_2FA_EMAIL_SETTINGS_NAME,
				array( \WP2FA\Admin\SettingsPages\Settings_Page_Email::class, 'validate_and_sanitize' )
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

			\add_action( WP_2FA_PREFIX . 'before_plugin_settings', array( __CLASS__, 'check_email' ) );
		}

		/**
		 * Create admin menu entry and settings page
		 */
		public static function create_settings_admin_menu_multisite() {
			// Create admin menu item.
			\add_menu_page(
				\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
				\esc_html__( 'WP 2FA', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				null,
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( WP_2FA_PATH . 'dist/images/wp-2fa-white-icon20x28.svg' ) ), // phpcs:ignore
				81
			);

			\add_submenu_page(
				self::TOP_MENU_SLUG,
				\esc_html__( '2FA Policies', 'wp-2fa' ),
				\esc_html__( '2FA Policies', 'wp-2fa' ),
				'manage_options',
				self::TOP_MENU_SLUG,
				array( \WP2FA\Admin\SettingsPages\Settings_Page_Policies::class, 'render' ),
				1
			);

			\add_submenu_page(
				self::TOP_MENU_SLUG,
				\esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
				\esc_html__( 'Settings', 'wp-2fa' ),
				'manage_options',
				'wp-2fa-settings',
				array( \WP2FA\Admin\SettingsPages\Settings_Page_Render::class, 'render' ),
				2
			);

			/**
			 * Fires after the main menu settings are registered.
			 *
			 * @param string - The menu slug.
			 * @param bool - Is that multisite install or not.
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'after_admin_menu_created', self::TOP_MENU_SLUG, true );
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
			if ( 'enable_account_unlocked_email' !== WP2FA::get_wp2fa_email_templates( 'send_account_unlocked_email' ) ) {
				return false;
			}

			// Grab user data.
			$user = get_userdata( $user_id );
			// Grab user email.
			$email = $user->user_email;
			// Setup the email contents.
			$subject = wp_strip_all_tags( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_unlocked_email_subject' ) ) );
			$message = wpautop( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_unlocked_email_body' ), $user_id ) );

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
					// Remove admin menu item.
					remove_submenu_page( 'options-general.php', self::TOP_MENU_SLUG );
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
			if ( ! function_exists( 'wp2fa_freemius' ) || ! wp2fa_freemius()->has_active_valid_license() ) {
				$trial_link = 'https://melapress.com/wordpress-2fa/pricing/?utm_source=plugin&utm_medium=link&utm_campaign=wp2fa';
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

			\WP2FA\Admin\SettingsPages\Settings_Page_White_Label::update_wp2fa_network_options();

			/**
			 * Gives the ability for extensions to set their settings in the plugin.
			 *
			 * @since 2.2.0
			 */
			do_action( WP_2FA_PREFIX . 'update_network_settings' );
		}

		/**
		 * Handle saving email options to the network main site options.
		 */
		public static function update_wp2fa_network_email_options() {
			Settings_Page_Email::update_wp2fa_network_options();
		}

		/**
		 * These are used instead of add_settings_error which in a network site. Used to show if settings have been updated or failed.
		 */
		public static function settings_saved_network_admin_notice() {
			if ( isset( $_GET['wp_2fa_network_settings_updated'] ) && 'true' === $_GET['wp_2fa_network_settings_updated'] ) {
				?>
			<div class="notice notice-success is-dismissible">
				<p><?php \esc_html_e( '2FA Settings Updated', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
				<?php
			}
			if ( isset( $_GET['wp_2fa_network_settings_updated'] ) && 'false' === $_GET['wp_2fa_network_settings_updated'] ) { // phpcs:ignore
				?>
			<div class="notice notice-error is-dismissible">
				<?php
				if ( isset( $_GET['wp_2fa_network_settings_custom_error_message'] ) ) { // phpcs:ignore
					$error = \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_network_settings_custom_error_message'] ) );
					?>
					<p><?php echo \esc_attr( \esc_url_raw( \urldecode_deep( $error ) ) ); ?></p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
					</button>
					<?php
				} else {
					?>
				<p><?php \esc_html_e( 'Please ensure both custom email address and display name are provided.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
					<?php
				}
				?>
			</div>
				<?php
			}
			if ( isset( $_GET['wp_2fa_network_settings_error'] ) ) { // phpcs:ignore
				?>
			<div class="notice notice-error is-dismissible">
				<?php
					$error = \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_network_settings_error'] ) );

				if ( true === \strpos( $error, 'http' ) ) {
					?>
				<p><?php echo \esc_attr( \esc_url_raw( \urldecode_deep( $error ) ) ); ?></p>
					<?php
				} else {
					?>
				<p><?php echo \esc_attr( ( $error ) ); ?></p>
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
					<div class="notice notice-<?php echo \esc_attr( $error['type'] ); ?> is-dismissible">
						<p><?php echo \esc_html( $error['message'] ); ?></p>
						<button type="button" class="notice-dismiss">
							<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
						</button>
					</div>
							<?php
						}
					} else {
						?>
					<div class="notice notice-success is-dismissible">
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
				<div class="notice notice-error is-dismissible">
					<p><?php \esc_html_e( 'Please ensure both custom email address and display name are provided.', 'wp-2fa' ); ?></p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
					</button>
				</div>
					<?php
				}
				if ( isset( $_GET['settings_error'] ) ) {
					?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo \esc_attr( \esc_url_raw( \urldecode_deep( \sanitize_text_field( \wp_unslash( $_GET['settings_error'] ) ) ) ) ); ?></p>
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

			// Specify our desired headers.
			$headers = 'Content-type: text/html;charset=utf-8' . "\r\n";

			if ( 'use-custom-email' === WP2FA::get_wp2fa_email_templates( 'email_from_setting' ) ) {
				$headers .= 'From: ' . WP2FA::get_wp2fa_email_templates( 'custom_from_display_name' ) . ' <' . WP2FA::get_wp2fa_email_templates( 'custom_from_email_address' ) . '>' . "\r\n";
			} else {

				$headers .= 'From: wp2fa <' . self::get_default_email_address() . '>' . "\r\n";
				// $headers .= 'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>' . "\r\n";
			}

			// Fire our email.
			return wp_mail( $recipient_email, stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) ), $message, $headers );
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

			if ( null !== $sitename ) {
				if ( str_starts_with( $sitename, 'www.' ) ) {
					$sitename = substr( $sitename, 4 );
				}

				$from_email .= $sitename;
			}

			return $from_email;
		}

		/**
		 * Turns user roles data in any form and shape to an array of strings.
		 *
		 * @param mixed $value User role names (slugs) as raw value.
		 *
		 * @return string[] List of user role names (slugs).
		 */
		public static function extract_roles_from_input( $value ) {
			if ( is_array( $value ) ) {
				return $value;
			}

			if ( is_string( $value ) && ! empty( $value ) ) {
				return explode( ',', $value );
			}

			return array();
		}

		/**
		 * Determine if any BG processes are currently running.
		 *
		 * @return int|false Number of jobs.
		 */
		public static function get_current_number_of_active_bg_processes() {
			global $wpdb;

			$bg_jobs = $wpdb->get_results( // phpcs:ignore
				"SELECT option_value FROM $wpdb->options
				WHERE option_name LIKE '%_2fa_bg_%'"
			);

			return count( $bg_jobs );
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
				if ( 'use-custom-email' === WP2FA::get_wp2fa_email_templates( 'email_from_setting' ) ) {
					$admin_email = WP2FA::get_wp2fa_email_templates( 'custom_from_email_address' );
				}

				if ( '' === trim( (string) $admin_email ) ) {
					$email_settings_url = \esc_url(
						add_query_arg(
							array(
								'page' => 'wp-2fa-settings',
								'tab'  => 'email-settings',
							),
							network_admin_url( 'admin.php' )
						)
					);
					?>
					<div class="notice notice-error" style="padding-top: 10px; padding-bottom: 10px;">
						<p class="description" ><?php \esc_html_e( 'By default, the plugin uses ', 'wp-2fa' ); ?> <b><?php echo \sanitize_email( self::get_default_email_address() ); ?></b> <?php \esc_html_e( 'as the "from address" when sending emails with the 2FA code for users to log in. Do you want to keep using this or change it?', 'wp-2fa' ); ?></p>
						<p>
							<a class="button button-primary" href="<?php echo \esc_url( $email_settings_url ); ?>"><?php \esc_html_e( 'Change it', 'wp-2fa' ); ?></a>
							<a class="button button-secondary 2fa-email-notice" style="margin-left:20px" href="#">
								<?php \esc_html_e( 'Keep using it', 'wp-2fa' ); ?>
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
	}
}
