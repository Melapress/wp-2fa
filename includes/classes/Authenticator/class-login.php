<?php
/**
 * Responsible for WP2FA user's login forms.
 *
 * @package    wp2fa
 * @subpackage login
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Authenticator;

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Methods\Email;
use WP2FA\Admin\Setup_Wizard;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use WP2FA\Methods\Wizards\TOTP_Wizard_Steps;
use WP2FA\Admin\Views\Grace_Period_Notifications;
use WP2FA\Admin\SettingsPages\Settings_Page_Policies;

/**
 * Responsible for user login process.
 *
 * @since 2.0.0
 */
if ( ! class_exists( '\WP2FA\Authenticator\Login' ) ) {
	/**
	 * Class for handling logins.
	 */
	class Login {

		/**
		 * Keys used for backup codes
		 *
		 * @var string
		 */
		const USER_META_NONCE_KEY    = 'wp_2fa_nonce';
		const INPUT_NAME_RESEND_CODE = 'wp-2fa-email-code-resend';

		/**
		 * Keep track of all the password-based authentication sessions that
		 * need to invalidated before the second factor authentication.
		 *
		 * @var array
		 */
		private static $password_auth_tokens = array();

		/**
		 * Keep track of all the authentication cookies that need to be
		 * invalidated before the second factor authentication.
		 *
		 * @param string $cookie Cookie string.
		 *
		 * @return void
		 */
		public static function collect_auth_cookie_tokens( $cookie ) {
			$parsed = wp_parse_auth_cookie( $cookie );

			if ( ! empty( $parsed['token'] ) ) {
				self::$password_auth_tokens[] = $parsed['token'];
			}
		}

		/**
		 * Leave the memberpress alone
		 *
		 * @return bool
		 *
		 * @since 2.6.0
		 */
		public static function mepr_login(): bool {
			\remove_action( 'wp_login', array( '\WP2FA\Authenticator\Login', 'wp_login' ), 20, 2 );

			return true;
		}
		/**
		 * Handle the browser-based login.
		 *
		 * Note: All user meta data is in sync with the current version of plugin settings. This is taken care of in filter
		 * wp_authenticate_user.
		 *
		 * @since 0.1-dev
		 *
		 * @param string   $user_login Username.
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 *
		 * @SuppressWarnings(PHPMD.ExitExpression)
		 */
		public static function wp_login( $user_login, $user ) {

			if ( class_exists( '\wpengine\sign_on_plugin\WPESignOnPlugin' ) && isset( $_REQUEST['nonce'] ) && isset( $_REQUEST['install_name'] ) ) {
				$user_nonce   = new \wpengine\sign_on_plugin\UserNonceHelper();
				$nonce        = \wp_unslash( $_REQUEST['nonce'] );
				$install_name = \wp_unslash( $_REQUEST['install_name'] );
				$nonce_data   = $user_nonce->get_nonce_data( $user->ID );

				// At this stage we are pretty sure that it is wp engine and everything is OK. $nonce_data must be empty because they are using user_meta and it is deleted - so there is no way to do a second validation, but that is enough.
				if ( empty( $nonce_data ) ) {
					return;
				}
			}

			// Flywheel auto login part starts here.
			if ( defined( 'FW_DIRECT_LOGIN_SHARED_KEY' ) && isset( $_REQUEST['payload'] ) && isset( $_REQUEST['nonce'] ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {

				$playload = base64_decode( \wp_unslash( $_REQUEST['payload'] ) );
				$nonce    = base64_decode( \wp_unslash( $_REQUEST['nonce'] ) );
				$key      = file_get_contents( FW_DIRECT_LOGIN_SHARED_KEY );

				$playload = sodium_crypto_secretbox_open( $playload, $nonce, $key );
				if ( false !== $playload ) {
					return;
				}
			}
			// Flywheel auto login end.

			global $wp_current_filter;

			if ( isset( $wp_current_filter ) && ! empty( $wp_current_filter ) && \is_array( $wp_current_filter ) ) {
				foreach ( $wp_current_filter as $filter ) {
					if ( 'wp_ajax_nopriv_mepr_stripe_confirm_payment' === $filter ) {
						// That request comes from unprivileged user (maybe new), lets skip our checks in that case.
						return;
					}
				}
			}

			$user_status = User_Helper::get_2fa_status( $user );

			if ( User_Helper::USER_UNDETERMINED_STATUS === $user_status ) {
				User_Helper::remove_global_settings_hash_for_user( $user->ID );
			}
			User_Helper::set_login_date_for_user( time(), $user );

			WP2FA::clear_user_after_login();

			/**
			 * User is not required to use the 2FA
			 */
			if ( 'no_required_not_enabled' === $user_status ) {
				return;
			}

			$global_methods       = Methods::get_available_2fa_methods();
			$users_method         = User_Helper::get_enabled_method_for_user( $user );
			$users_method_removed = false;

			if ( User_Helper::is_enforced( $user ) && ! empty( $users_method ) && empty( \array_intersect( array( $users_method ), $global_methods ) ) ) {
				$users_method_removed = true;
			}

			// leave if the user has already got 2FA authentication configured.
			if ( ! $users_method_removed && User_Helper::is_user_using_two_factor( $user->ID ) ) {
				// phpcs:disable
				// phpcs:enable				
				try {
					Settings::is_provider_enabled_for_role( User_Helper::get_user_role(), User_Helper::get_enabled_method_for_user( $user ) );
					self::clear_session_and_show_2fa_form( $user );
					return;
				} catch ( \Exception $e ) {
					return;
				}
			}

			// Method is no longer available, but the user is using it - bail.
			if ( $users_method_removed && User_Helper::is_user_using_two_factor( $user->ID ) ) {
				return;
			}

			// leave if 2FA is not enforced, but optional.
			$enforcement_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
			if ( 'do-not-enforce' === $enforcement_policy ) {
				return;
			}

			// leave if the user is not required to have 2FA enabled due to and exclusion rule.
			if ( User_Helper::is_excluded( $user->ID ) ) {
				return;
			}

			// redirect to 2FA setup page if the 2FA configuration is enforced to happen instantly.
			$is_user_instantly_enforced = User_Helper::get_user_enforced_instantly( $user );
			if ( true === (bool) $is_user_instantly_enforced ) {
				wp_safe_redirect(
				self::get_2fa_setup_url( $user ) . ( ( isset( $_REQUEST['_wp_http_referer'] ) && ! empty( $_REQUEST['_wp_http_referer'] ) ) ? '?return=' . urlencode( \esc_url_raw( \wp_unslash( $_REQUEST['_wp_http_referer'] ) ) ) : '' ) // phpcs:ignore
				);
				exit();
			}

			// if there is some grace period configured, and it is not instant, we can let the users in (if they needed to
			// be blocked, this would have already happened in wp_authenticate).
			$grace_policy = WP2FA::get_wp2fa_setting( 'grace-policy' );
			if ( 'use-grace-period' === $grace_policy ) {

				if ( ! Grace_Period_Notifications::notify_using_dashboard( $user ) ) {
					$global_methods   = Methods::get_available_2fa_methods();
					$users_method     = User_Helper::get_enabled_method_for_user( $user );
					$is_nag_dismissed = User_Helper::get_nag_status();
					$is_nag_needed    = User_Helper::is_enforced( User_Helper::get_user_object()->ID );


					if ( ! $is_nag_dismissed && $is_nag_needed ) {

						$login_nonce = self::create_login_nonce( $user->ID );
						if ( ! $login_nonce ) {
							wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
						}

						$redirect_to = isset( $_REQUEST['redirect_to'] ) ? \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url(); //phpcs:ignore

						self::show_2fa_form_grace_form( $user, $login_nonce['key'], $redirect_to );
					} else {
						return;
					}
				} else {
					return;
				}
			}

			$provider = User_Helper::get_enabled_method_for_user( $user );
			if ( '' === trim( (string) $provider ) ) {
				return;
			}

			self::clear_session_and_show_2fa_form( $user );
		}

		/**
		 * Generates the html form for the second step of the authentication process.
		 *
		 * @since 2.5.0
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 * @param string   $login_nonce A string nonce stored in usermeta.
		 * @param string   $redirect_to The URL to which the user would like to be redirected.
		 * @param string   $error_msg Optional. Login error message.
		 */
		public static function show_2fa_form_grace_form( $user, $login_nonce, $redirect_to, $error_msg = '' ) {
			$interim_login   = isset( $_REQUEST['interim-login'] ) ? filter_var( wp_unslash( $_REQUEST['interim-login'] ), FILTER_VALIDATE_BOOLEAN ) : false; //phpcs:ignore
			$rememberme     = intval( self::rememberme() );
			$global_methods = Methods::get_available_2fa_methods();
			$users_method   = User_Helper::get_enabled_method_for_user( $user );

			if ( ! function_exists( 'login_header' ) ) {
				// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
				include_once WP_2FA_PATH . 'includes/functions/login-header.php';
			}

			login_header();

			if ( ! empty( $error_msg ) ) {
				echo '<div id="login_error"><strong>' . apply_filters( 'login_errors', \esc_html( $error_msg ) ) . '</strong><br /></div>';
			}
			?>
			<form name="grace_2fa_form" id="lgraceform" action="<?php echo \esc_url( self::login_url( array( 'action' => 'grace_2fa' ), 'login_post' ) ); ?>" method="post" autocomplete="off">
				<input type="hidden" name="wp-auth-id"    id="wp-auth-id"    value="<?php echo \esc_attr( $user->ID ); ?>" />
				<input type="hidden" name="wp-auth-nonce" id="wp-auth-nonce" value="<?php echo \esc_attr( $login_nonce ); ?>" />
				<?php if ( $interim_login ) : ?>
					<input type="hidden" name="interim-login" value="1" />
				<?php else : ?>
					<input type="hidden" name="redirect_to" value="<?php echo \esc_attr( $redirect_to ); ?>" />
				<?php endif; ?>
				<input type="hidden" name="rememberme" id="rememberme" value="<?php echo \esc_attr( $rememberme ); ?>"/>

				<?php
				$class = 'wp-2fa-nag';

				if ( User_Helper::get_user_needs_to_reconfigure_2fa( User_Helper::get_user_object() ) ) {
					$message = WP2FA::get_wp2fa_white_label_setting( 'default-2fa-resetup-required-notice', true );
				} else {
					$message = WP2FA::get_wp2fa_white_label_setting( 'default-2fa-required-notice', true );
				}


				$grace_expiry = (int) User_Helper::get_user_expiry_date( User_Helper::get_user_object() );

				$setup_url = Settings::get_setup_page_link();

				echo '<div class="' . \esc_attr( $class ) . '">';
				echo \wpautop( \wp_kses_post( WP2FA::replace_remaining_grace_period( $message, $grace_expiry ) ) );
				echo '<p>&nbsp;</p><div> <a href="' . \esc_url( $setup_url ) . '" class="button button-primary">' . \esc_html__( 'Configure 2FA now', 'wp-2fa' ) . '</a>';
				echo ' <a href="#" class="button button-secondary dismiss-user-configure-nag">' . \esc_html__( 'I\'ll do it later', 'wp-2fa' ) . '</a></div>';
				echo '</div>';

				/**
				 * Allows 3rd parties to render something at the end of the existing grace form.
				 *
				 * @param \WP_User $user - User for which the login form is shown.
				 * @param string $provider - The name of the provider.
				 *
				 * @since 2.0.0
				 */
				do_action( WP_2FA_PREFIX . 'grace_html_before_end', $user );
				?>
			</form>

			<?php
			/** This action is documented in wp-login.php */
			do_action( 'login_footer' );
			?>

		</div>
		<div class="clear"></div>
			<?php wp_print_scripts( 'jquery' ); ?>
		<script>
			jQuery( document ).on( 'click', '.dismiss-user-configure-nag', function(e) {
				e.preventDefault();
				const thisNotice = jQuery( this ).closest( '.notice' );
				jQuery.ajax( {
					url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
					data: {
						action: 'dismiss_nag'
					},
					complete: function() {
						window.location.replace( jQuery( '[name="redirect_to"]' ).val() );
					},
				} );
			} );
		</script>
		<style>
			#login form p:empty + p {
				margin-top: 15px;
			}
		</style>
		</body>
		</html>
			<?php

			exit();
		}

		/**
		 * Clears current user session and displays a "clone" of login screen with form to capture 2FA code.
		 *
		 * It also terminates current web request.
		 *
		 * @param \WP_User $user WordPress user object.
		 *
		 * @since 2.0.0
		 *
		 * @SuppressWarnings(PHPMD.ExitExpression)
		 */
		private static function clear_session_and_show_2fa_form( $user ) {
			/**
			 * The filter can be user to skip the 2FA "login" form in some cases. For example if the user has set their
			 * device as trusted.
			 *
			 * @param bool $skip
			 * @param \WP_User $user
			 *
			 * @return bool
			 */
			$should_form_be_skipped = apply_filters( WP_2FA_PREFIX . 'skip_2fa_login_form', false, $user );
			if ( $should_form_be_skipped ) {
				return;
			}

			// Invalidate the current login session to prevent from being re-used.
			self::destroy_current_session_for_user( $user );

			// Also clear the cookies which are no longer valid.
			wp_clear_auth_cookie();

			self::show_two_factor_login( $user );
			exit;
		}

		/**
		 * Retrieves the correct URL to the 2FA setup page. It handles configurable custom page as well as multisite.
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 *
		 * @return string 2FA setup page URL.
		 *
		 * @since 2.0.0
		 * @since 2.5.0 $user parameter is added
		 */
		private static function get_2fa_setup_url( $user ) {

			$page_slug = Settings::get_role_or_default_setting( 'custom-user-page-url', $user );

			// Lets check for multisite first and if that is the case - lets search for that page on the user's default blog.
			if ( WP_Helper::is_multisite() && false !== Settings::get_role_or_default_setting( 'separate-multisite-page-url', $user ) && ! empty( $page_slug ) ) {
				$blog_id = User_Helper::get_user_default_blog( $user );
				if ( 0 === $blog_id ) {
					$new_page_permalink = '';
				} else {
					// Switch to the blog context.
					\switch_to_blog( $blog_id );

					$page_exists = Settings_Page_Policies::get_post_by_post_name( $page_slug, 'page' );

					// Restore global context.
					\restore_current_blog();

					if ( false === $page_exists ) {
						// Switch to the blog context.
						switch_to_blog( $blog_id );

						$result = Settings_Page_Policies::generate_custom_user_profile_page( $page_slug, User_Helper::get_user_role( $user ) );

						// Restore global context.
						restore_current_blog();

						if ( $result && ! is_wp_error( $result ) ) {
							$new_page_permalink = get_permalink( $result );
						}
					} else {
						$new_page_permalink = get_permalink( $page_exists->ID );
					}
				}
			} else {
				$page_exists = Settings_Page_Policies::get_post_by_post_name( $page_slug, 'page' );

				if ( $page_exists instanceof \WP_Post ) {
					$new_page_permalink = get_permalink( $page_exists->ID );
				}
			}

			if ( ! empty( $new_page_permalink ) ) {
				return $new_page_permalink;
			}

			// If multisite - redirect the user properly in the admin.
			if ( WP_Helper::is_multisite() ) {
				return Settings::get_setup_page_link();
			}

			return admin_url( 'profile.php' );
		}

		/**
		 * Destroy the known password-based authentication sessions for the current user.
		 *
		 * Is there a better way of finding the current session token without
		 * having access to the authentication cookies which are just being set
		 * on the first password-based authentication request.
		 *
		 * @param \WP_User $user User object.
		 *
		 * @return void
		 */
		public static function destroy_current_session_for_user( $user ) {
			$session_manager = \WP_Session_Tokens::get_instance( $user->ID );

			foreach ( self::$password_auth_tokens as $auth_token ) {
				$session_manager->destroy( $auth_token );
			}
		}

		/**
		 * Prevent login through XML-RPC and REST API for users with at least one
		 * 2FA method enabled.
		 *
		 * @param  \WP_User|\WP_Error $user Valid \WP_User only if the previous filters
		 *                                have verified and confirmed the
		 *                                authentication credentials.
		 *
		 * @return \WP_User|\WP_Error
		 */
		public static function filter_authenticate( $user ) {
			if ( $user instanceof \WP_User && self::is_api_request() && User_Helper::is_user_using_two_factor( $user->ID ) && ! self::is_user_api_login_enabled( $user->ID ) ) {
				return new \WP_Error(
					'invalid_application_credentials',
					\esc_html__( 'Error: API login for user disabled.', 'wp-2fa' )
				);
			}

			return $user;
		}

		/**
		 * Checks if the user should be locked and return WordPress error if that's the case. It doesn't check the account
		 * if it receives an error object as an input.
		 *
		 * @param \WP_User|\WP_Error $user User data.
		 * @param string             $password Password.
		 *
		 * @return \WP_User|\WP_Error
		 */
		public static function run_authentication_check( $user, $password ) {
			// we don't need to do anything if we already received an error.
			if ( is_a( $user, '\WP_Error' ) ) {
				return $user;
			}

			if ( User_Helper::is_user_locked( $user->ID ) && ! User_Helper::is_excluded( $user->ID ) ) {
				return self::get_user_locked_error();
			}

			return $user;
		}

		/**
		 * Generates an error object representing locked user account.
		 *
		 * @return \WP_Error User account locked error.
		 * @since 2.0.0
		 */
		public static function get_user_locked_error() {
			return new \WP_Error(
				'account_locked',
				\esc_html__( 'Your user account has been locked because you have not configured 2FA within the grace period. Please contact the website administrator to unlock your user and you can configure 2FA.', 'wp-2fa' )
			);
		}

		/**
		 * If the current user can login via API requests such as XML-RPC and REST.
		 *
		 * @param  integer $user_id User ID.
		 *
		 * @return boolean
		 */
		public static function is_user_api_login_enabled( $user_id ) {
			return (bool) apply_filters( 'two_factor_user_api_login_enable', false, $user_id );
		}

		/**
		 * Is the current request an XML-RPC or REST request.
		 *
		 * @return boolean
		 */
		public static function is_api_request() {
			if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
				return true;
			}

			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return true;
			}

			return false;
		}

		/**
		 * Display the login form.
		 *
		 * @since 0.1-dev
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 */
		public static function show_two_factor_login( $user ) {
			if ( ! $user ) {
				$user = wp_get_current_user();
			}

			$login_nonce = self::create_login_nonce( $user->ID );
			if ( ! $login_nonce ) {
				wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
			}

			$redirect_to = isset( $_REQUEST['redirect_to'] ) ? \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url(); //phpcs:ignore

			if ( self::is_woocommerce_activated() ) {
				$redirect_to = isset( $_REQUEST['redirect'] ) ? \esc_url_raw( wp_unslash( $_REQUEST['redirect'] ) ) : admin_url();
			}

			self::login_html( $user, $login_nonce['key'], $redirect_to );
		}

		/**
		 * Checks if woocommerce is enabled.
		 *
		 * @return boolean
		 *
		 * @since 2.2.2
		 */
		public static function is_woocommerce_activated(): bool {
			if ( class_exists( 'woocommerce' ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Display the Backup code 2fa screen.
		 *
		 * @since 0.1-dev
		 *
		 * @SuppressWarnings(PHPMD.ExitExpression)
		 */
		public static function backup_2fa() {
			if ( ! isset( $_GET['wp-auth-id'], $_GET['wp-auth-nonce'], $_GET['provider'] ) ) { //phpcs:ignore
				return;
			}

			// Filter $_GET array for security.
			$get_array = filter_input_array( INPUT_GET );
			$auth_id   = (int) $get_array['wp-auth-id'];
			$user      = \get_userdata( $auth_id );
			if ( ! $user ) {
				return;
			}

			$nonce = \sanitize_text_field( $get_array['wp-auth-nonce'] );
			if ( true !== self::verify_login_nonce( $user->ID, $nonce ) ) {
				wp_safe_redirect( get_bloginfo( 'url' ) );
				exit;
			}

			if ( ! isset( $get_array['provider'] ) ) {
				\wp_die( \esc_html__( 'Cheatin&#8217; uh?', 'wp-2fa' ), 403 );
			} else {
				$provider = \sanitize_textarea_field( \wp_unslash( $_GET['provider'] ) ); //phpcs:ignore
			}

			\delete_transient( 'wp_2fa_code_login_' . $user->ID );

			self::login_html( $user, $nonce, \esc_url_raw( \wp_unslash( $get_array['redirect_to'] ) ), '', $provider );

			exit;
		}

		/**
		 * Generates the html form for the second step of the authentication process.
		 *
		 * @since 0.1-dev
		 *
		 * @param \WP_User      $user \WP_User object of the logged-in user.
		 * @param string        $login_nonce A string nonce stored in usermeta.
		 * @param string        $redirect_to The URL to which the user would like to be redirected.
		 * @param string        $error_msg Optional. Login error message.
		 * @param string|object $provider An override to the provider.
		 */
		public static function login_html( $user, $login_nonce, $redirect_to, $error_msg = '', $provider = null ) {
			if ( ! $provider || ( Backup_Codes::METHOD_NAME === $provider && ! Backup_Codes::are_backup_codes_enabled_for_role( User_Helper::get_user_role( $user ) ) ) ) {
				$provider = User_Helper::get_enabled_method_for_user( $user );
			}

			$codes_remaining = Backup_Codes::codes_remaining_for_user( $user );
			$interim_login   = isset( $_REQUEST['interim-login'] ) ? filter_var( wp_unslash( $_REQUEST['interim-login'] ), FILTER_VALIDATE_BOOLEAN ) : false; //phpcs:ignore
			$rememberme      = intval( self::rememberme() );

			if ( ! function_exists( 'login_header' ) ) {
				// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
				include_once WP_2FA_PATH . 'includes/functions/login-header.php';
			}

			login_header();

			if ( ! empty( $error_msg ) ) {
				echo '<div id="login_error"><strong>' . apply_filters( 'login_errors', \esc_html( $error_msg ) ) . '</strong><br /></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<form name="validate_2fa_form" id="loginform" action="<?php echo \esc_url( self::login_url( array( 'action' => 'validate_2fa' ), 'login_post' ) ); ?>" method="post" autocomplete="off">
				<input type="hidden" name="provider"      id="provider"      value="<?php echo \esc_attr( $provider ); ?>" />
				<input type="hidden" name="wp-auth-id"    id="wp-auth-id"    value="<?php echo \esc_attr( $user->ID ); ?>" />
				<input type="hidden" name="wp-auth-nonce" id="wp-auth-nonce" value="<?php echo \esc_attr( $login_nonce ); ?>" />
				<?php if ( $interim_login ) : ?>
					<input type="hidden" name="interim-login" value="1" />
				<?php else : ?>
					<input type="hidden" name="redirect_to" value="<?php echo \esc_attr( $redirect_to ); ?>" />
				<?php endif; ?>
				<input type="hidden" name="rememberme" id="rememberme" value="<?php echo \esc_attr( $rememberme ); ?>"/>

				<?php
				// Check to see what provider is set and give the relevant authentication page.
				if ( TOTP::METHOD_NAME === $provider ) {
					TOTP_Wizard_Steps::totp_authentication_page( $user );
				} elseif ( Email::METHOD_NAME === $provider ) {
					self::email_authentication_page( $user );
				} elseif ( Backup_Codes::METHOD_NAME === $provider ) {
					self::backup_codes_authentication_page( $user );
				} else {

					/**
					 * Allows 3rd parties to render their own 2FA "login" form.
					 *
					 * @param \WP_User $user - User for which the login form is shown.
					 * @param string $provider - The name of the provider.
					 *
					 * @since 2.0.0
					 */
					do_action( WP_2FA_PREFIX . 'login_form', $user, $provider );
				}

				/**
				 * Gives the ability to remove the submit button from the plugin forms
				 *
				 * @param bool - Default at this point is true - no method is selected.
				 * @param array $input - The input array with all the data.
				 *
				 * @since 2.0.0
				 */
				$submit_button_disabled = apply_filters( WP_2FA_PREFIX . 'login_disable_submit_button', false, $user, $provider );
				if ( ! $submit_button_disabled ) {

					/**
					 * Allows 3rd parties to render something before the login button on the 2FA "login" form.
					 *
					 * @param \WP_User $user - User for which the login form is shown.
					 * @param string $provider - The name of the provider.
					 *
					 * @since 2.0.0
					 */
					do_action( WP_2FA_PREFIX . 'login_before_submit_button', $user, $provider );
					?>
					<p>
					<?php
					if ( function_exists( 'submit_button' ) ) {

						/**
						 * Using that filter, the default text of the login button could be changed
						 *
						 * @param callback - Callback function which is responsible for text manipulation.
						 *
						 * @since 2.0.0
						 */
						$button_text = apply_filters( WP_2FA_PREFIX . 'login_button_text', \esc_html__( 'Log In', 'wp-2fa' ) );

						submit_button( $button_text );
						?>
						<script type="text/javascript">
							setTimeout(function () {
								var d
								try {
									d = document.getElementById('authcode')
									d.value = ''
									d.focus()
								} catch (e) {}
							}, 200)
						</script>
					<?php } ?>
					</p>
					<?php
					if ( Email::METHOD_NAME === $provider ) {
						?>
						<p class="2fa-email-resend">
							<input type="submit" class="button"
							name="<?php echo \esc_attr( self::INPUT_NAME_RESEND_CODE ); ?>"
							value="<?php \esc_attr_e( 'Resend Code', 'wp-2fa' ); ?>"/>
						</p>
						<?php
					}
				} // submit button not disabled

				/**
				 * Allows 3rd parties to render something at the end of the existing login form.
				 *
				 * @param \WP_User $user - User for which the login form is shown.
				 * @param string $provider - The name of the provider.
				 *
				 * @since 2.0.0
				 */
				do_action( WP_2FA_PREFIX . 'login_html_before_end', $user, $provider );
				?>
			</form>

			<?php
			if ( Backup_Codes::METHOD_NAME !== $provider && Backup_Codes::are_backup_codes_enabled_for_role( User_Helper::get_user_role( $user ) ) && isset( $codes_remaining ) && $codes_remaining > 0 ) {
				$login_url = self::login_url(
					array(
						'action'        => 'backup_2fa',
						'provider'      => Backup_Codes::METHOD_NAME,
						'wp-auth-id'    => $user->ID,
						'wp-auth-nonce' => $login_nonce,
						'redirect_to'   => $redirect_to,
						'rememberme'    => $rememberme,
					)
				);
				?>
				<div class="backup-methods-wrap">
					<p class="backup-methods">
						<a href="<?php echo \esc_url( $login_url ); ?>">
							<?php \esc_html_e( 'Or, use a backup code.', 'wp-2fa' ); ?>
						</a>
					</p>
				</div>
				<?php
			}

			/**
			 * Allows 3rd parties to render something after the backup methods.
			 *
			 * @param \WP_User $user - User for which the login form is shown.
			 * @param string $provider - The name of the provider.
			 * @param string $login_nonce - The login nonce created.
			 * @param string $redirect_to - Where to redirect the user after successful login.
			 * @param bool $rememberme - Remember me status.
			 *
			 * @since 2.0.0
			 */
			do_action( WP_2FA_PREFIX . 'login_html_after_backup_providers', $user, $provider, $login_nonce, $redirect_to, $rememberme );

			?>

		<p id="backtoblog">
			<a href="<?php echo \esc_url( home_url( '/' ) ); ?>" title="<?php \esc_attr_e( 'Are you lost?', 'wp-2fa' ); ?>">
				<?php
				echo \esc_html(
					sprintf(
						// translators: %s: site name.
						__( '&larr; Back to %s', 'wp-2fa' ),
						get_bloginfo( 'title', 'display' )
					)
				);
				?>
			</a>
		</p>
		</div>
		<style>
		/* @todo: migrate to an external stylesheet. */
		.backup-methods-wrap {
			margin-top: 16px;
			padding: 0 24px;
		}
		.backup-methods-wrap a {
			color: #50575e;
			text-decoration: none;
		}
		ul.backup-methods {
			display: none;
			padding-left: 1.5em;
		}
		/* Prevent Jetpack from hiding our controls, see https://github.com/Automattic/jetpack/issues/3747 */
		.jetpack-sso-form-display #loginform > p,
		.jetpack-sso-form-display #loginform > div {
			display: block;
		}
		</style>

			<?php
			/** This action is documented in wp-login.php */
			do_action( 'login_footer' );
			?>
		<div class="clear"></div>
		</body>
		</html>
			<?php
		}

		/**
		 * Generate the 2FA login form URL.
		 *
		 * @param  array  $params List of query argument pairs to add to the URL.
		 * @param  string $scheme URL scheme context.
		 *
		 * @return string
		 */
		public static function login_url( $params = array(), $scheme = 'login' ) {
			if ( ! is_array( $params ) ) {
				$params = array();
			}

			$params = urlencode_deep( $params );

			return add_query_arg( $params, site_url( 'wp-login.php', $scheme ) );
		}

		/**
		 * Create the login nonce.
		 *
		 * @since 0.1-dev
		 *
		 * @param int $user_id User ID.
		 *
		 * @return array|bool
		 */
		public static function create_login_nonce( $user_id ) {
			$login_nonce = array();
			try {
				$login_nonce['key'] = bin2hex( random_bytes( 32 ) );
			} catch ( \Exception $ex ) {
				$login_nonce['key'] = wp_hash( $user_id . mt_rand() . microtime(), 'nonce' ); //phpcs:ignore
			}
			$login_nonce['expiration'] = time() + HOUR_IN_SECONDS;

			if ( ! update_user_meta( $user_id, self::USER_META_NONCE_KEY, $login_nonce ) ) {
				return false;
			}

			return $login_nonce;
		}

		/**
		 * Delete the login nonce.
		 *
		 * @since 0.1-dev
		 *
		 * @param int $user_id User ID.
		 *
		 * @return void
		 */
		public static function delete_login_nonce( $user_id ) {
			User_Helper::remove_meta( self::USER_META_NONCE_KEY, $user_id );
		}

		/**
		 * Verify the login nonce.
		 *
		 * @since 0.1-dev
		 *
		 * @param int    $user_id User ID.
		 * @param string $nonce Login nonce.
		 * @return bool
		 */
		public static function verify_login_nonce( $user_id, $nonce ) {
			$login_nonce = get_user_meta( $user_id, self::USER_META_NONCE_KEY, true );
			if ( ! $login_nonce ) {
				return false;
			}

			if ( $nonce !== $login_nonce['key'] || time() > $login_nonce['expiration'] ) {
				self::delete_login_nonce( $user_id );
				return false;
			}

			return true;
		}

		/**
		 * Login form validation.
		 *
		 * @since 0.1-dev
		 *
		 * @SuppressWarnings(PHPMD.ExitExpression)
		 */
		public static function login_form_validate_2fa() {
			if ( ! isset( $_POST['wp-auth-id'], $_POST['wp-auth-nonce'] ) ) { // phpcs:ignore
				return;
			}

			// If form data comes from 2 factor password reset - bounce.
			if ( isset( $_POST['reset'] ) && 'reset-2fa' === $_POST['reset'] ) { // phpcs:ignore
				return;
			}

			$auth_id = (int) $_POST['wp-auth-id']; // phpcs:ignore
			$user    = get_userdata( $auth_id );
			if ( ! $user ) {
				return;
			}

			$nonce = ( isset( $_POST['wp-auth-nonce'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['wp-auth-nonce'] ) ) : ''; // phpcs:ignore
			if ( true !== self::verify_login_nonce( $user->ID, $nonce ) ) {
				wp_safe_redirect( get_bloginfo( 'url' ) );
				exit;
			}

			if ( isset( $_POST['provider'] ) ) { // phpcs:ignore
				$provider  = sanitize_textarea_field( wp_unslash( $_POST['provider'] ) ); // phpcs:ignore
			}

			if ( ! Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user ), $provider ) ) {
				wp_die( __( '<p> <strong>WP-2FA</strong>: Please contact the administrator for further assistance!</p>', 'wp-2fa' ) . \esc_html__( 'Invalid provider.', 'wp-2fa' ) ); // phpcs:ignore
			}

			// If this is an email login, or if the user failed validation previously, lets send the code to the user.
			if ( Email::METHOD_NAME === $provider && true !== self::pre_process_email_authentication( $user ) ) {
				$login_nonce = self::create_login_nonce( $user->ID );
				if ( ! $login_nonce ) {
					wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
				}
			}

			// Validate TOTP.
			if ( TOTP::METHOD_NAME === $provider && true !== TOTP::validate_totp_authentication( $user ) ) {
				do_action(
					'wp_login_failed',
					$user->user_login,
					new \WP_Error(
						'authentication_failed',
						__( '<strong>Error</strong>: User can not be authenticated.', 'wp-2fa' )
					)
				);

				$login_nonce = self::create_login_nonce( $user->ID );
				if ( ! $login_nonce ) {
					wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
				}

				if ( Authentication::check_number_of_attempts( $user ) ) {
					self::login_html( $user, $login_nonce['key'], \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), \esc_html__( 'ERROR: Invalid verification code.', 'wp-2fa' ), $provider ); // phpcs:ignore
				} else {
					// Reached the maximum number of attempts - clear the attempts and redirect the user to the login page.
					Authentication::clear_login_attempts( $user );
					\wp_redirect( \wp_login_url() );
				}
				exit;
			}

			// Backup Codes.
			if ( Backup_Codes::METHOD_NAME === $provider && true !== Backup_Codes::validate_backup_codes( $user ) ) {
				do_action(
					'wp_login_failed',
					$user->user_login,
					new \WP_Error(
						'authentication_failed',
						__( '<strong>Error</strong>: User can not be authenticated.', 'wp-2fa' )
					)
				);
				$login_nonce = self::create_login_nonce( $user->ID );
				if ( ! $login_nonce ) {
					wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
				}

				if ( Backup_Codes::check_number_of_attempts( $user ) ) {

					self::login_html( $user, $login_nonce['key'], \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), \esc_html__( 'ERROR: Invalid backup code.', 'wp-2fa' ), $provider ); // phpcs:ignore
				} else {
					Backup_Codes::clear_login_attempts( $user );
					\wp_redirect( \wp_login_url() );
				}
				exit;
			}

			// Validate Email.
			if ( Email::METHOD_NAME === $provider && true !== self::validate_email_authentication( $user ) ) {
				do_action(
					'wp_login_failed',
					$user->user_login,
					new \WP_Error(
						'authentication_failed',
						__( '<strong>Error</strong>: User can not be authenticated.', 'wp-2fa' )
					)
				);

				$login_nonce = self::create_login_nonce( $user->ID );
				if ( ! $login_nonce ) {
					wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
				}

				if ( isset( $_REQUEST['wp-2fa-email-code-resend'] ) ) { //phpcs:ignore
					self::login_html( $user, $login_nonce['key'], \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), \esc_html__( 'A new code has been sent.', 'wp-2fa' ), $provider ); // phpcs:ignore
				} elseif ( Authentication::check_number_of_attempts( $user ) ) {
					$msg = \esc_html__( 'ERROR: Invalid verification code.', 'wp-2fa' );
					if ( empty( WP2FA::get_wp2fa_general_setting( 'brute_force_disable' ) ) ) {
						$msg .= \esc_html__( ' For security reasons you have been sent a new code via email. Please use this new code to log in.', 'wp-2fa' );
					}
					self::login_html( $user, $login_nonce['key'], \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), $msg, $provider ); // phpcs:ignore
				} else {
					Authentication::clear_login_attempts( $user );
					\wp_redirect( \wp_login_url() );
				}

				exit;
			}

			/**
			 * Allows 3rd parties to validate their own 2FA "login" form.
			 *
			 * @param \WP_User $user - User for which the login form is shown.
			 * @param string $provider - The name of the provider.
			 *
			 * @since 2.0.0
			 */
			do_action( WP_2FA_PREFIX . 'validate_login_form', $user, $provider );

			self::delete_login_nonce( $user->ID );

			$rememberme = false;
			$remember   = ( isset( $_REQUEST['rememberme'] ) ) ? filter_var( $_REQUEST['rememberme'], FILTER_VALIDATE_BOOLEAN ) : ''; // phpcs:ignore
			if ( ! empty( $remember ) ) {
				$rememberme = true;
			}

			wp_set_auth_cookie( $user->ID, $rememberme );

			/**
			 * Fires when the user is authenticated.
			 *
			 * @param \WP_User - the logged in user
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'user_authenticated', $user );

			// Must be global because that's how login_header() uses it.
			global $interim_login;
			$interim_login = ( isset( $_REQUEST['interim-login'] ) ) ? filter_var( $_REQUEST['interim-login'], FILTER_VALIDATE_BOOLEAN ) : false; // phpcs:ignore

			if ( $interim_login ) {
				$message       = '<p class="message">' . __( 'You have logged in successfully.', 'wp-2fa' ) . '</p>';
				$interim_login = 'success'; // phpcs:ignore

				if ( ! function_exists( 'login_header' ) ) {
					// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
					include_once WP_2FA_PATH . 'includes/functions/login-header.php';
				}

				login_header( '', $message );
				?>
			</div>
				<?php
				/** This action is documented in wp-login.php */
				do_action( 'login_footer' );
				?>
			</body></html>
				<?php
				exit;
			}

			// Check if user has any roles/caps set - if they dont, we know its a "network" user.
			if ( WP_Helper::is_multisite() && ! get_active_blog_for_user( $user->ID ) && empty( $user->caps ) && empty( $user->caps ) ) {
				$redirect_to = user_admin_url();
			} else {
				$redirect_to = apply_filters( 'login_redirect', \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), $user ); // phpcs:ignore
			}

			Backup_Codes::clear_login_attempts( $user );

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( WP_Helper::is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
					$redirect_to = user_admin_url();
				} elseif ( WP_Helper::is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
				}

				$redirect_to = apply_filters( WP_2FA_PREFIX . 'post_login_orphan_user_redirect', $redirect_to, $user );

				wp_redirect( $redirect_to );
				exit;
			}

			$redirect_to = apply_filters( WP_2FA_PREFIX . 'post_login_user_redirect', $redirect_to, $user );

			wp_safe_redirect( $redirect_to );

			exit;
		}

		/**
		 * Should the login session persist between sessions.
		 *
		 * @return boolean
		 */
		public static function rememberme() {
			$rememberme = false;

			if ( ! empty( $_REQUEST['rememberme'] ) ) { //phpcs:ignore
				$rememberme = true;
			}

			/**
			 * Changes the remember me value.
			 *
			 * @param bool $rememberme - Current state of the remember me variable.
			 *
			 * @since 2.0.0
			 */
			return (bool) apply_filters( WP_2FA_PREFIX . 'rememberme', $rememberme );
		}

		/**
		 * Prints the form that prompts the user to authenticate.
		 *
		 * @since 0.1-dev
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 */
		public static function email_authentication_page( $user, $is_reset_protection = false ) {
			if ( ! $user ) {
				return;
			}

			$code_sent       = (bool) User_Helper::get_meta( WP_2FA_PREFIX . 'code_sent' );
			$use_default     = ( 'use-custom' == WP2FA::get_wp2fa_white_label_setting( 'use_custom_2fa_message' ) ) ? 'custom-text-email-code-page' : 'default-text-code-page';
			$text_to_display = ( $is_reset_protection ) ? 'default-text-pw-reset-code-page' : $use_default;

			if ( ! $code_sent && ! isset( $_REQUEST[ self::INPUT_NAME_RESEND_CODE ] ) ) {
				Setup_Wizard::send_authentication_setup_email( $user->ID, 'nominated_email_address', $is_reset_protection );
				if ( ! empty( WP2FA::get_wp2fa_general_setting( 'brute_force_disable' ) ) ) {
					User_Helper::set_meta( WP_2FA_PREFIX . 'code_sent', true );
				}
			}

			require_once ABSPATH . '/wp-admin/includes/template.php';
			?>
	<?php echo WP2FA::get_wp2fa_white_label_setting( $text_to_display, true ); // phpcs:ignore ?>
	<p>
	</br>
		<label for="authcode"><?php \esc_html_e( 'Verification Code:', 'wp-2fa' ); ?></label>
		<input type="tel" name="wp-2fa-email-code" id="authcode" class="input" value="" size="20" pattern="[0-9]*" autocomplete="off" />
		<script>
			const email_code = document.getElementById('authcode');
			email_code.addEventListener('input', function() {
			this.value = this.value.trim();
			});
		</script>
	</p>
			<?php
		}

		/**
		 * Validates the users input token.
		 *
		 * @since 0.1-dev
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 * @return boolean
		 */
		public static function validate_email_authentication( $user ) {
			if ( ! isset( $user->ID ) || ! isset( $_REQUEST['wp-2fa-email-code'] ) ) { //phpcs:ignore
				return false;
			}
			return Authentication::validate_token( $user, \sanitize_text_field( \wp_unslash( $_REQUEST['wp-2fa-email-code'] ) ) );
		}

		/**
		 * Send the email code if missing or requested. Stop the authentication
		 * validation if a new token has been generated and sent.
		 *
		 * @param  \WP_User $user \WP_User object of the logged-in user.
		 * @return boolean
		 */
		public static function pre_process_email_authentication( $user ) {
			if ( isset( $user->ID ) && isset( $_REQUEST[ self::INPUT_NAME_RESEND_CODE ] ) ) { //phpcs:ignore -- nonce
				Setup_Wizard::send_authentication_setup_email( $user->ID );
				return true;
			}
			return false;
		}

		/**
		 * Prints the form that prompts the user to authenticate.
		 *
		 * @since 0.1-dev
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 */
		public static function backup_codes_authentication_page( $user ) {
			require_once ABSPATH . '/wp-admin/includes/template.php';
			?>
		<p><?php echo WP2FA::get_wp2fa_white_label_setting( 'default-backup-code-page', true ); // phpcs:ignore ?></p><br/>
		<p>
			<label for="authcode"><?php \esc_html_e( 'Verification Code:', 'wp-2fa' ); ?></label>
			<input type="tel" name="wp-2fa-backup-code" id="authcode" class="input" value="" size="20" pattern="[0-9]*" autocomplete="off" />
			<script>
				const backup_code = document.getElementById('authcode');
				input.addEventListener('input', function() {
				this.value = this.value.trim();
				});
			</script>
		</p>
			<?php
		}

		/**
		 * Removes GoDaddy style which causing the form elements to be shown
		 *
		 * @return void
		 *
		 * @since 2.2.0
		 */
		public static function dequeue_style() {
			wp_dequeue_style( 'wpaas-sso-login' );
		}
	}
}
