<?php
/**
 * Responsible for rendering the short codes.
 *
 * @package    wp2fa
 * @subpackage short-codes
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Shortcodes;

use WP2FA\Core;
use WP2FA\WP2FA;
use WP2FA\Admin\User_Notices;
use WP2FA\Admin\User_Profile;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Views\Re_Login_2FA;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;

if ( ! class_exists( '\WP2FA\Shortcodes\Shortcodes' ) ) {
	/**
	 * Class for rendering shortcodes.
	 */
	class Shortcodes {

		/**
		 * Constructor.
		 */
		public static function init() {
			\add_shortcode( 'wp-2fa-setup-form', array( __CLASS__, 'user_setup_2fa_form' ) );
			\add_shortcode( 'wp-2fa-setup-notice', array( __CLASS__, 'user_setup_2fa_notice' ) );
			\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_2fa_shortcode_scripts' ) );
		}

		/**
		 * Register scripts and styles.
		 */
		public static function register_2fa_shortcode_scripts() {
			// Add our front end stuff, which we only want to load when the shortcode is present.
			\wp_register_script( 'wp_2fa_frontend_scripts', Core\script_url( 'wp-2fa', 'admin' ), array( 'jquery', 'wp_2fa_micro_modals' ), WP_2FA_VERSION, true );
			\wp_register_script( 'wp_2fa_micro_modals', Core\script_url( 'micromodal', 'admin' ), array(), WP_2FA_VERSION, true );
			\wp_register_style( 'wp_2fa_styles', Core\style_url( 'styles', 'frontend' ), array(), WP_2FA_VERSION );

			$data_array = array(
				'ajaxURL'        => \admin_url( 'admin-ajax.php' ),
				'roles'          => WP_Helper::get_roles_wp(),
				'nonce'          => \wp_create_nonce( 'wp-2fa-settings-nonce' ),
				'codesPreamble'  => \esc_html__( 'These are the 2FA backup codes for the user', 'wp-2fa' ),
				'readyText'      => \esc_html__( 'I\'m ready', 'wp-2fa' ),
				'codeReSentText' => \esc_html__( 'New code sent', 'wp-2fa' ),
				'allDoneHeading' => \esc_html__( 'All done.', 'wp-2fa' ),
				'allDoneText'    => \esc_html__( 'Your login just got more secure.', 'wp-2fa' ),
				'closeWizard'    => \esc_html__( 'Close Wizard', 'wp-2fa' ),
				'invalidEmail'   => \esc_html__( 'Please use a valid email address', 'wp-2fa' ),
			);
			\wp_localize_script( 'wp_2fa_frontend_scripts', 'wp2faData', $data_array );

			$role = User_Helper::get_user_role();

			$re_login = Settings::get_role_or_default_setting( Re_Login_2FA::RE_LOGIN_SETTINGS_NAME, 'current', $role );

			$data_array                  = array(
				'ajaxURL'         => \admin_url( 'admin-ajax.php' ),
				'nonce'           => \wp_create_nonce( 'wp2fa-verify-wizard-page' ),
				'codesPreamble'   => \esc_html__( 'These are the 2FA backup codes for the user', 'wp-2fa' ),
				'readyText'       => \esc_html__( 'I\'m ready', 'wp-2fa' ),
				'codeReSentText'  => \esc_html__( 'New code sent', 'wp-2fa' ),
				'invalidEmail'    => \esc_html__( 'Please use a valid email address', 'wp-2fa' ),
				'backupCodesSent' => \esc_html__( 'Backup codes sent', 'wp-2fa' ),
				'reLogin'         => $re_login,
				'reLoginEnabled'  => Re_Login_2FA::ENABLED_SETTING_VALUE,
			);
			$redirect_page               = Settings::get_role_or_default_setting( 'redirect-user-custom-page-global', 'current', $role );
			$data_array['redirectToUrl'] = ( '' !== trim( (string) $redirect_page ) ) ? \trailingslashit( get_site_url() ) . $redirect_page : '';
			// Check and override if custom redirect page is selected and custom redirect is set.
			if (
			'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', 'current', $role ) ||
			'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page' ) ) {
				if (
				'' !== trim( (string) Settings::get_role_or_default_setting( 'redirect-user-custom-page', 'current', $role ) ) ||
				'' !== trim( (string) Settings::get_role_or_default_setting( 'redirect-user-custom-page' ) ) ) {
					if ( 'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', 'current', $role ) ) {
						$data_array['redirectToUrl'] = \trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page', 'current', $role );
					} else {
						$data_array['redirectToUrl'] = \trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page' );
					}
				}
			}

			// Check for shortcode parameter - if one is present use it to redirect the user - highest priority.
			if ( isset( $redirect_after ) && ! empty( $redirect_after ) ) {
				$data_array['redirectToUrl'] = \trailingslashit( \get_site_url() ) . \urlencode( $redirect_after );
			} elseif ( isset( $_GET['return'] ) && ! empty( $_GET['return'] ) ) {
				$data_array['redirectToUrl'] = \trailingslashit( \get_site_url() ) . strip_tags( \wp_unslash( $_GET['return'] ) ); // phpcs:ignore
			}

			\wp_localize_script( 'wp_2fa_frontend_scripts', 'wp2faWizardData', $data_array );
		}

		/**
		 * Output setup form.
		 *
		 * @param array $atts - Array with the attributes passed to shortcode.
		 *
		 * @return string
		 */
		public static function user_setup_2fa_form( $atts ) {

			/** Shortcode redirect_after is supported, with which the user can override all other settings */
			extract( // phpcs:ignore
				\shortcode_atts(
					array(
						'show_preamble'       => 'true',
						'redirect_after'      => '',
						'do_not_show_enabled' => 'false',
					),
					$atts
				)
			);

			/**
			 * Fires when the FE shortcode scripts are registered.
			 *
			 * @param bool $shortcodes - True if called from the short codes method.
			 *
			 * @since 2.2.0
			 */
			\do_action( WP_2FA_PREFIX . 'shortcode_scripts', true );

			if ( is_user_logged_in() ) {
				\wp_enqueue_script( 'wp_2fa_frontend_scripts' );
				\wp_enqueue_style( 'wp_2fa_styles' );

				ob_start();
				echo '<form id="your-profile" class="wp-2fa-configuration-form">';
				User_Profile::inline_2fa_profile_form( 'output_shortcode', $show_preamble, array( 'do_not_show_enabled' => $do_not_show_enabled ) );
				echo '</form>';
				$content = ob_get_contents();
				ob_end_clean();

				return $content;
			} elseif ( ! is_admin() && ! is_user_logged_in() ) {
				ob_start();
				$new_page_id = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
				$redirect_to = ! empty( $new_page_id ) ? \get_permalink( $new_page_id ) : \get_home_url();
				$link_markup = '<a href="' . \esc_url( \wp_login_url( $redirect_to ) ) . '">' . \esc_html__( 'Login here.', 'wp-2fa' ) . '</a>';
				$message     = '<p id="wp_2fa_login_to_view_text">' . str_replace( '{login_url}', $link_markup, WP2FA::get_wp2fa_white_label_setting( 'login-to-view-area', true ) ) . '</p>';
				echo \wp_kses_post( $message );
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
			}
		}

		/**
		 * Output setup nag.
		 *
		 * @param array $atts - Array with the attributes passed to shortcode.
		 *
		 * @return string
		 */
		public static function user_setup_2fa_notice( $atts ) {
			extract( // phpcs:ignore
				\shortcode_atts(
					array(
						'configure_2fa_url' => '',
					),
					$atts
				)
			);

			// TODO: is that really necessary?
			User_Notices::init();

			if ( ! is_admin() && is_user_logged_in() ) {
				\wp_enqueue_script( 'wp_2fa_micro_modals' );
				\wp_enqueue_script( 'wp_2fa_frontend_scripts' );
				\wp_enqueue_style( 'wp_2fa_styles' );

				$data_array = array(
					'ajaxURL'        => \admin_url( 'admin-ajax.php' ),
					'roles'          => WP_Helper::get_roles_wp(),
					'nonce'          => \wp_create_nonce( 'wp-2fa-settings-nonce' ),
					'codesPreamble'  => \esc_html__( 'These are the 2FA backup codes for the user', 'wp-2fa' ),
					'readyText'      => \esc_html__( 'I\'m ready', 'wp-2fa' ),
					'codeReSentText' => \esc_html__( 'New code sent', 'wp-2fa' ),
					'allDoneHeading' => \esc_html__( 'All done.', 'wp-2fa' ),
					'allDoneText'    => \esc_html__( 'Your login just got more secure.', 'wp-2fa' ),
					'closeWizard'    => \esc_html__( 'Close Wizard', 'wp-2fa' ),
				);
				\wp_localize_script( 'wp_2fa_frontend_scripts', 'wp2faData', $data_array );

				ob_start();
				User_Notices::user_setup_2fa_nag( 'output_shortcode', $configure_2fa_url );
				$content = ob_get_contents();
				ob_end_clean();

				return $content;
			}

			return '';
		}
	}
}
