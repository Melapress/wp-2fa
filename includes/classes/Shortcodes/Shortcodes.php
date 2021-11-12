<?php // phpcs:ignore

namespace WP2FA\Shortcodes;

use \WP2FA\Core as Core;
use \WP2FA\WP2FA as WP2FA;
use WP2FA\Admin\Controllers\Settings;
use \WP2FA\Admin\UserNotices as UserNotices;
use \WP2FA\Admin\UserProfile as UserProfile;

/**
 * Class for rendering shortcodes.
 */
class Shortcodes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'wp-2fa-setup-form', array( $this, 'user_setup_2fa_form' ) );
		add_shortcode( 'wp-2fa-setup-notice', array( $this, 'user_setup_2fa_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_2fa_shortcode_scripts' ) );
	}

	/**
	 * Register scripts and styles.
	 */
	public function register_2fa_shortcode_scripts() {
		// Add our front end stuff, which we only want to load when the shortcode is present.
		wp_register_script( 'wp_2fa_frontend_scripts', Core\script_url( 'wp-2fa', 'admin' ), array( 'jquery', 'wp_2fa_micro_modals' ), WP_2FA_VERSION, true );
		wp_register_script( 'wp_2fa_micro_modals', Core\script_url( 'micromodal', 'admin' ), array(), WP_2FA_VERSION, true );
		wp_register_style( 'wp_2fa_styles', Core\style_url( 'styles', 'frontend' ) );
	}

	/**
	 * Output setup form.
	 */
	public function user_setup_2fa_form( $atts ) {

		/** Shortcode redirect_after is supported, with which the user can override all other settings */
		extract(
			shortcode_atts(
				[
					'show_preamble' => 'true',
					'redirect_after' => '',
				],
				$atts
			)
		);

		if ( is_user_logged_in() ) {
			wp_enqueue_script( 'wp_2fa_frontend_scripts' );
			wp_enqueue_style( 'wp_2fa_styles' );

			$data_array = array(
				'ajaxURL'        => admin_url( 'admin-ajax.php' ),
				'roles'          => WP2FA::wp_2fa_get_roles(),
				'nonce'          => wp_create_nonce( 'wp-2fa-settings-nonce' ),
				'codesPreamble'  => esc_html__( 'These are the 2FA backup codes for the user', 'wp-2fa' ),
				'readyText'      => esc_html__( 'I\'m ready', 'wp-2fa' ),
				'codeReSentText' => esc_html__( 'New code sent', 'wp-2fa' ),
				'allDoneHeading' => esc_html__( 'All done.', 'wp-2fa' ),
				'allDoneText'    => esc_html__( 'Your login just got more secure.', 'wp-2fa' ),
				'closeWizard'    => esc_html__( 'Close Wizard', 'wp-2fa' ),
			);
			wp_localize_script( 'wp_2fa_frontend_scripts', 'wp2faData', $data_array );

			$data_array = array(
				'ajaxURL'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wp2fa-verify-wizard-page' ),
				'codesPreamble'  => esc_html__( 'These are the 2FA backup codes for the user', 'wp-2fa' ),
				'readyText'      => esc_html__( 'I\'m ready', 'wp-2fa' ),
				'codeReSentText' => esc_html__( 'New code sent', 'wp-2fa' ),
			);

			$role = array_key_first( WP2FA::wp_2fa_get_roles() );
			$redirect_page = Settings::get_role_or_default_setting( 'redirect-user-custom-page-global', 'current', $role );
			$data_array['redirectToUrl'] = ( '' !== trim( $redirect_page ) ) ? \trailingslashit( get_site_url() ) . $redirect_page : '';
			// Check and override if custom redirect page is selected and custom redirect is set.
			if (
				'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', 'current', $role ) ||
				'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page' ) ) {
				if (
					'' !== trim( Settings::get_role_or_default_setting( 'redirect-user-custom-page', 'current', $role ) ) ||
					'' !== trim( Settings::get_role_or_default_setting( 'redirect-user-custom-page' ) ) ) {
					if ( 'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', 'current', $role ) ) {
						$data_array['redirectToUrl'] = trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page', 'current', $role );
					} else {
						$data_array['redirectToUrl'] = trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page' );
					}
				}
			}

			// Check for shortcode parameter - if one is present use it to redirect the user - highest priority.
			if ( isset( $redirect_after ) && ! empty( $redirect_after ) ) {

				$data_array['redirectToUrl'] = trailingslashit( get_site_url() ).\urlencode( $redirect_after );
			} elseif ( isset( $_GET['return'] ) && ! empty( $_GET['return'] ) ) {

				$data_array['redirectToUrl'] = trailingslashit( get_site_url() ) . strip_tags( \urlencode( $_GET['return'] ) );
			}
			wp_localize_script( 'wp_2fa_frontend_scripts', 'wp2faWizardData', $data_array );

			$forms = new UserProfile();
			ob_start();
			echo '<form id="your-profile" class="wp-2fa-configuration-form">';
				$forms->inline_2fa_profile_form( 'output_shortcode', $show_preamble );
			echo '</form>';
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		} elseif ( ! is_admin() && ! is_user_logged_in() ) {
			$new_page_id = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
			$redirect_to = ! empty( $new_page_id  ) ? get_permalink( $new_page_id ) : get_home_url();
			ob_start();
			echo '<p>' . esc_html__( 'You must be logged in to view this page.', 'wp-2fa' ) . ' <a href="' . esc_url( wp_login_url( $redirect_to ) ) . '">' . esc_html__( 'Login here.', 'wp-2fa' ) . '</a></p>';
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}
	}

	/**
	 * Output setup nag.
	 */
	public function user_setup_2fa_notice( $atts ) {
		extract(
			shortcode_atts(
				array(
					'configure_2fa_url' => '',
				),
				$atts
			)
		);
		$notice = new UserNotices();

		if ( ! is_admin() && is_user_logged_in() ) {
			wp_enqueue_script( 'wp_2fa_micro_modals' );
			wp_enqueue_script( 'wp_2fa_frontend_scripts' );
			wp_enqueue_style( 'wp_2fa_styles' );

			$data_array = array(
				'ajaxURL'        => admin_url( 'admin-ajax.php' ),
				'roles'          => WP2FA::wp_2fa_get_roles(),
				'nonce'          => wp_create_nonce( 'wp-2fa-settings-nonce' ),
				'codesPreamble'  => esc_html__( 'These are the 2FA backup codes for the user', 'wp-2fa' ),
				'readyText'      => esc_html__( 'I\'m ready', 'wp-2fa' ),
				'codeReSentText' => esc_html__( 'New code sent', 'wp-2fa' ),
				'allDoneHeading' => esc_html__( 'All done.', 'wp-2fa' ),
				'allDoneText'    => esc_html__( 'Your login just got more secure.', 'wp-2fa' ),
				'closeWizard'    => esc_html__( 'Close Wizard', 'wp-2fa' ),
			);
			wp_localize_script( 'wp_2fa_frontend_scripts', 'wp2faData', $data_array );

			ob_start();
			echo $notice->user_setup_2fa_nag( 'output_shortcode', $configure_2fa_url );
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}
	}

}
