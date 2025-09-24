<?php
/**
 * Responsible for WP2FA user's reset password forms.
 *
 * @package    wp2fa
 * @subpackage resetpassword
 *
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 *
 * @since     2.5.0
 */

declare(strict_types=1);

namespace WP2FA\Authenticator;

use WP2FA\Methods\Email;
use WP2FA\Authenticator\Login;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Views\Password_Reset_2FA;
use WP2FA\Admin\Methods\Traits\Login_Attempts;
use WP2FA\WP2FA;

/**
 * Responsible for user login process.
 *
 * @since 2.5.0
 */
if ( ! class_exists( '\WP2FA\Authenticator\Reset_Password' ) ) {
	/**
	 * Class for handling logins.
	 */
	class Reset_Password {

		use Login_Attempts;

		/**
		 * Holds the name of the meta key for the allowed login attempts.
		 *
		 * @var string
		 *
		 * @since 2.9.2
		 */
		private static $logging_attempts_meta_key = WP_2FA_PREFIX . 'api-reset-password-attempts';

		/**
		 * Show 2FA on password reset request.
		 *
		 * @param \WP_Error      $errors    A WP_Error object containing any errors generated
		 *                                 by using invalid credentials.
		 * @param \WP_User|false $user_data WP_User object if found, false if the user does not exist.
		 *
		 * @return \WP_Error|void
		 *
		 * @since 2.5.0
		 */
		public static function lostpassword_post( $errors, $user_data = null ) {
			if ( $errors->has_errors() ) {
				return $errors;
			}
			if ( false === $user_data ) {
				return $errors;
			}
			if ( null === $user_data ) {

				\add_filter(
					'lostpassword_errors',
					function( $errors ) {
						$errors->add(
							WP_2FA_PREFIX . 'password_reset',
							\wp_sprintf(
							// translators: anchor link, contact us text, closing anchor.
								__( 'This process cannot be completed because one or more parameters are missing from the request. This could be caused by outdated plugins. Ensure all the plugins are up to date. If the problem persists %1$1s%2$2s%3$3s - WP 2FA.', 'wp-2fa' ),
								'<a href="mailto:support@melapress.com">',
								__( 'contact us', 'wp-2fa' ),
								'</a>'
							)
						);

						return $errors;
					}
				);

				return $errors;
			}

			if ( ! ( $user_data instanceof \WP_User ) ) {
				return $errors;
			}

			global $current_user;

			if ( isset( $current_user ) && 0 !== $current_user->ID && $current_user->ID !== $user_data->ID ) {
				return;
			}

			$expire_action = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user_data ), Password_Reset_2FA::PASSWORD_RESET_SETTINGS_NAME, true );

			if ( 'password-reset-2fa' !== $expire_action ) {
				return $errors;
			}

			if ( User_Helper::get_reset_password_valid_for_user() ) {
				return $errors;
			}

			$login_nonce = Login::create_login_nonce( $user_data->ID );
			if ( ! $login_nonce ) {
				\wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
			}

			if ( self::check_number_of_attempts( $user_data ) ) {
				self::increase_login_attempts( $user_data );
				self::show_two_factor_login( $user_data, $login_nonce['key'] );
			} else {
				// Reached the maximum number of attempts - clear the attempts and redirect the user to the login page.
				self::clear_login_attempts( $user_data );
				\wp_redirect( \wp_login_url() );
			}

			exit;
		}

		/**
		 * Generates the html form for the second step of the authentication process.
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 * @param string   $login_nonce - The generated nonce.
		 * @param string   $error_msg - Error message (if any) to show.
		 *
		 * @since 2.5.0
		 */
		public static function show_two_factor_login( $user, $login_nonce, $error_msg = '' ) {

			if ( ! function_exists( 'login_header' ) ) {
				// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
				include_once WP_2FA_PATH . 'includes/functions/login-header.php';
			}

			$lostpassword_redirect = ! empty( $_REQUEST['redirect_to'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
			/**
			 * Filters the URL redirected to after submitting the lostpassword/retrievepassword form.
			 *
			 * @since 3.0.0
			 *
			 * @param string $lostpassword_redirect The redirect destination URL.
			 */
			$redirect_to = \apply_filters( 'lostpassword_redirect', $lostpassword_redirect );

			if ( ! function_exists( 'login_header' ) ) {
				// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
				include_once WP_2FA_PATH . 'includes/functions/login-header.php';
			}

			login_header();

			if ( ! empty( $error_msg ) ) {
				echo '<div id="login_error"><strong>' . \esc_html( \apply_filters( 'login_errors', \esc_html( $error_msg ) ) ) . '</strong><br /></div>';
			}
			?>
			<form name="lostpasswordform" id="lostpasswordform" action="<?php echo \esc_url( network_site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>" method="post">
				<input type="hidden" name="wp-auth-id"    id="wp-auth-id"    value="<?php echo \esc_attr( $user->ID ); ?>" />
				<input type="hidden" name="wp-auth-nonce" id="wp-auth-nonce" value="<?php echo \esc_attr( $login_nonce ); ?>" />
				<input type="hidden" name="reset"      id="reset"      value="<?php echo \esc_attr( 'reset-2fa' ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo \esc_attr( $redirect_to ); ?>" />
				<?php
				// Check to see what provider is set and give the relevant authentication page.

				Login::email_authentication_page( $user, true );
				?>
					<p>
				<?php

				/**
				 * Using that filter, the default text of the login button could be changed
				 *
				 * @param callback - Callback function which is responsible for text manipulation.
				 *
				 * @since 2.0.0
				 */
				$button_text = apply_filters( WP_2FA_PREFIX . 'new_password_button_text', \esc_html__( 'Get New Password', 'wp-2fa' ) );
				?>

						<p class="submit">
							<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php echo \esc_attr( $button_text ); ?>" />
						</p>
					</p>

					<p class="2fa-email-resend">
						<input type="submit" class="button"
						name="<?php echo \esc_attr( Login::INPUT_NAME_RESEND_CODE ); ?>"
						value="<?php \esc_attr_e( 'Resend Code', 'wp-2fa' ); ?>"/>
					</p>

			</form>
			<?php
			if ( function_exists( 'login_footer' ) ) {
				\login_footer( 'user_login' );
			}
		}

		/**
		 * Login form validation.
		 *
		 * @since 2.5.0
		 */
		public static function login_form_validate_2fa() {
			if ( ! isset( $_POST['wp-auth-id'], $_POST['wp-auth-nonce'], $_POST['reset'] ) ) { // phpcs:ignore
				return;
			}

			$auth_id = (int) $_POST['wp-auth-id']; // phpcs:ignore
			$user    = \get_userdata( $auth_id );
			if ( ! $user ) {
				return;
			}

			$nonce = ( isset( $_POST['wp-auth-nonce'] ) ) ? \sanitize_textarea_field( wp_unslash( $_POST['wp-auth-nonce'] ) ) : ''; // phpcs:ignore
			if ( true !== Login::verify_login_nonce( $user->ID, $nonce ) ) {
				\wp_safe_redirect( \get_bloginfo( 'url' ) );
				exit;
			}

			$provider = Email::METHOD_NAME;

			self::increase_login_attempts( $user );

			// If this is an email login, or if the user failed validation previously, lets send the code to the user.
			if ( Email::METHOD_NAME === $provider && true !== Login::pre_process_email_authentication( $user, true ) ) {
				$login_nonce = Login::create_login_nonce( $user->ID );
				if ( ! $login_nonce ) {
					\wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
				}
			}

			// Validate Email.
			if ( Email::METHOD_NAME === $provider && true !== Login::validate_email_authentication( $user ) ) {
				\do_action(
					'wp_login_failed',
					$user->user_login,
					new \WP_Error(
						'authentication_failed',
						__( '<strong>Error</strong>: User can not be authenticated.', 'wp-2fa' )
					)
				);

				$login_nonce = Login::create_login_nonce( $user->ID );
				if ( ! $login_nonce ) {
					\wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
				}

				if ( self::check_number_of_attempts( $user ) ) {

					if ( isset( $_REQUEST['wp-2fa-email-code-resend'] ) ) {
						self::show_two_factor_login( $user, $login_nonce['key'], \esc_html__( 'A new code has been sent.', 'wp-2fa' ), $provider ); // phpcs:ignore
					} else {
						self::show_two_factor_login( $user, $login_nonce['key'], \esc_html__( 'ERROR: Invalid verification code.', 'wp-2fa' ), $provider ); // phpcs:ignore
					}
				} else {
					// Reached the maximum number of attempts - clear the attempts and redirect the user to the login page.
					self::clear_login_attempts( $user );
					\wp_redirect( \wp_login_url() );
				}

				exit;
			}

			User_Helper::set_reset_password_valid_for_user( true );

			$errors = \retrieve_password( $user->user_email );

			if ( ! \is_wp_error( $errors ) ) {
				$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['redirect_to'] ) ) : 'wp-login.php?checkemail=confirm';
				User_Helper::remove_reset_password_valid_for_user();
				self::clear_login_attempts( $user );
				\wp_safe_redirect( $redirect_to );
				exit;
			}

			\wp_redirect( site_url( 'wp-login.php?action=lostpassword' ) );
		}
	}
}
