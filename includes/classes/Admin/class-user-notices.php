<?php
/**
 * Responsible for WP2FA user's notifying.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Extensions_Loader;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Freemius\User_Licensing;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Views\Grace_Period_Notifications;
use WP2FA\Extensions\WhiteLabeling\White_Labeling_Render;

/**
 * User_Notices class with user notification filters
 *
 * @since 2.4.0
 */
if ( ! class_exists( '\WP2FA\Admin\User_Notices' ) ) {
	/**
	 * User_Notices - Class for displaying notices to our users.
	 */
	class User_Notices {

		/**
		 * Lets set things up
		 */
		public static function init() {
			$enforcement_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
			if ( ! empty( $enforcement_policy ) ) {
				// Check we are supposed to, before adding action to show nag.
				if ( in_array( $enforcement_policy, array( 'all-users', 'certain-roles-only', 'certain-users-only', 'superadmins-only', 'superadmins-siteadmins-only', 'enforce-on-multisite', true ), true ) ) {
					$global_methods = Methods::get_available_2fa_methods();
					$user           = User_Helper::get_user_object();
					$users_method   = User_Helper::get_enabled_method_for_user( User_Helper::get_user_object() );

					if ( Grace_Period_Notifications::notify_using_dashboard( User_Helper::get_user_object() ) ) {
						add_action( 'admin_notices', array( __CLASS__, 'user_setup_2fa_nag' ) );
						add_action( 'network_admin_notices', array( __CLASS__, 'user_setup_2fa_nag' ) );
					}

					// If enaabled method is no longer available, show nag so users reconfigures using an available remaining method.
					if ( User_Helper::is_enforced( $user ) && ! empty( $users_method ) && empty( \array_intersect( array( $users_method ), $global_methods ) ) ) {
					}
				} elseif ( 'do-not-enforce' === WP2FA::get_wp2fa_setting( 'enforcement-policy' ) ) {
					add_action( 'admin_notices', array( __CLASS__, 'user_reconfigure_2fa_nag' ) );
					add_action( 'network_admin_notices', array( __CLASS__, 'user_setup_2fa_nag' ) );
				}
			}
		}

		/**
		 * The nag content
		 *
		 * @param string $is_shortcode - Is that a call from shortcode.
		 * @param string $configure_2fa_url - The configuration url.
		 *
		 * @return void
		 */
		public static function user_setup_2fa_nag( $is_shortcode = '', $configure_2fa_url = '' ) {

			if ( isset( $_GET['user_id'] ) ) { // phpcs:ignore
				$current_profile_user_id = (int) $_GET['user_id']; // phpcs:ignore
			} elseif ( ! is_null( User_Helper::get_user_object() ) ) {
				$current_profile_user_id = User_Helper::get_user_object()->ID;
			} else {
				$current_profile_user_id = false;
			}

			if ( ! $current_profile_user_id ||
			isset( $_GET['user_id'] ) && // phpcs:ignore
			$_GET['user_id'] !== User_Helper::get_user_object()->ID || // phpcs:ignore
			User_Helper::get_user_enforced_instantly( User_Helper::get_user_object() ) ) {
				return;
			}

			$grace_expiry = (int) User_Helper::get_user_expiry_date( User_Helper::get_user_object() );

			$class = 'notice notice-info wp-2fa-nag';

			if ( User_Helper::get_user_needs_to_reconfigure_2fa( User_Helper::get_user_object() ) ) {
				$message = WP2FA::get_wp2fa_white_label_setting( 'default-2fa-resetup-required-notice', true );
			} else {
				$message = WP2FA::get_wp2fa_white_label_setting( 'default-2fa-required-notice', true );
			}

			$is_nag_dismissed = User_Helper::get_nag_status();
			$is_nag_needed    = User_Helper::is_enforced( User_Helper::get_user_object()->ID );
			$is_user_excluded = User_Helper::is_excluded( User_Helper::get_user_object()->ID );
			$enabled_methods  = User_Helper::get_enabled_method_for_user( User_Helper::get_user_object() );
			$new_page_id      = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );

			if ( empty( $new_page_id ) ) {
				$new_page_id = Settings::get_custom_settings_page_id( '', User_Helper::get_user_object() );
			}

			$new_page_permalink = get_permalink( $new_page_id );

			$setup_url = Settings::get_setup_page_link();

			// Allow setup URL to be customized if outputting via shortcode.
			if ( isset( $is_shortcode ) && 'output_shortcode' === $is_shortcode && ! empty( $configure_2fa_url ) ) {
				$setup_url = $configure_2fa_url;
			}

			// Stop the page from being a link to a page this user cant access if needed.
			if ( WP_Helper::is_multisite() && ! is_user_member_of_blog( User_Helper::get_user_object()->ID ) ) {
				$new_page_id = false;
			}

			// If we have a custom page generated, lets use it.
			if ( ! empty( $new_page_id ) && $new_page_permalink ) {
				$setup_url = $new_page_permalink;
			}

			// If the nag has not already been dismissed, and of course if the user is eligible, lets show them something.
			if ( ! $is_nag_dismissed && $is_nag_needed && empty( $enabled_methods ) && ! $is_user_excluded && ! empty( $grace_expiry ) ) {

				$show = true;

				if ( class_exists( '\WP2FA\Freemius\User_Licensing' ) ) {
					if ( Extensions_Loader::use_proxytron() ) {
						$show = User_Licensing::enable_2fa_user_setting( true );
					}
				}

				if ( $show ) {
					echo '<div class="' . \esc_attr( $class ) . '">';
					echo wpautop( \wp_kses_post( WP2FA::replace_remaining_grace_period( $message, (int) $grace_expiry ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo ' <a href="' . \esc_url( $setup_url ) . '" class="button button-primary">' . \esc_html__( 'Configure 2FA now', 'wp-2fa' ) . '</a>';
					echo ' <a href="#" class="button button-secondary dismiss-user-configure-nag">' . \esc_html__( 'Remind me on next login', 'wp-2fa' ) . '</a></p>';
					echo '</div>';
				}
			} else {
				self::user_reconfigure_2fa_nag();
			}
		}

		/**
		 * The nag content
		 */
		public static function user_reconfigure_2fa_nag() {

			// If the nag has not already been dismissed, and of course if the user is eligible, lets show them something.
			if ( User_Helper::needs_to_reconfigure_method() ) {
				$class = 'notice notice-info wp-2fa-nag';

				$message = \esc_html__( 'The 2FA method you were using is no longer allowed on this website. Please reconfigure 2FA using one of the supported methods.', 'wp-2fa' );

				echo '<div class="' . \esc_attr( $class ) . '"><p>' . \esc_html( $message );
				echo ' <a href="' . \esc_url( Settings::get_setup_page_link() ) . '" class="button button-primary">' . \esc_html__( 'Configure 2FA now', 'wp-2fa' ) . '</a>';
				echo '  <a href="#" class="button button-secondary wp-2fa-button-secondary dismiss-user-reconfigure-nag">' . \esc_html__( 'I\'ll do it later', 'wp-2fa' ) . '</a></p>';
				echo '</div>';
			}
		}


		/**
		 * Dismiss notice and setup a user meta value so we know its been dismissed
		 */
		public static function dismiss_nag() {
			User_Helper::set_nag_status( true );
		}

		/**
		 * Reset the nag when the user logs out, so they get it again next time.
		 *
		 * @param [type] $user_id - The ID of the user.
		 *
		 * @return void
		 */
		public static function reset_nag( $user_id ) {
			User_Helper::remove_nag_status( $user_id );
		}

		/**
		 * Adds setting option in the white label settings page.
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		public static function white_label_settings_text() {
			?>
			<tr>
				<th><label for="email-backup-method"><?php \esc_html_e( '2FA mandatory notice', 'wp-2fa' ); ?></label></th>
				<td>
					<?php
						echo White_Labeling_Render::get_method_text_editor( 'default-2fa-required-notice' ); // phpcs:ignore
					?>
					<div style="margin-top: 5px;"><span><strong><i><?php \esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php \esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
				</td>
			</tr>
			<tr>
				<th><label for="email-backup-method"><?php \esc_html_e( '2FA reconfiguration mandatory notice', 'wp-2fa' ); ?></label></th>
				<td>
					<?php
						echo White_Labeling_Render::get_method_text_editor( 'default-2fa-resetup-required-notice' ); // phpcs:ignore
					?>
					<div style="margin-top: 5px;"><span><strong><i><?php \esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php \esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
				</td>
			</tr>
			<tr>
				<th><label><?php \esc_html_e( 'User profile 2FA configuration area title', 'wp-2fa' ); ?></label></th>
				<td>
					<?php
						echo White_Labeling_Render::get_method_text_editor( 'user-profile-form-preamble-title' ); // phpcs:ignore
					?>
					<div style="margin-top: 5px;"><span><strong><i><?php \esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php \esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
				</td>
			</tr>
			<tr>
			<th><label><?php \esc_html_e( 'User profile 2FA configuration area description', 'wp-2fa' ); ?></label></th>
				<td>
					<?php
						echo White_Labeling_Render::get_method_text_editor( 'user-profile-form-preamble-desc' ); // phpcs:ignore
					?>
					<div style="margin-top: 5px;"><span><strong><i><?php \esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php \esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
				</td>
			</tr>
			<?php
			// phpcs:disable
			// phpcs:enable
			?>
			<?php
		}

		/**
		 * Adds and filters extension values in the settings store array ($output).
		 *
		 * @param array $output - Array with the currently stored settings.
		 * @param array $input  - Array with the input ($_POST) values.
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		public static function settings_store( array $output, array $input ) {
			if ( isset( $input['default-2fa-required-notice'] ) ) {
				$output['default-2fa-required-notice'] = \wp_kses_post( $input['default-2fa-required-notice'] );
			}

			if ( isset( $input['default-2fa-resetup-required-notice'] ) ) {
				$output['default-2fa-resetup-required-notice'] = \wp_kses_post( $input['default-2fa-resetup-required-notice'] );
			}

			return $output;
		}
	}
}
