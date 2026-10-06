<?php
/**
 * Responsible for WP2FA user's login forms.
 *
 * @package    wp2fa
 * @subpackage login
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Authenticator;

use WP2FA\WP2FA;
use WP2FA\Utils\Debugging;
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
use WP2FA\Extensions\Zero_Setup_Email\Zero_Setup_Email;
use WP2FA\Licensing\Licensing_Factory;
use WP2FA\Utils\Settings_Utils;

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
		public const USER_META_NONCE_KEY = 'wp_2fa_nonce';

		/**
		 * The action of the login nonce the grace period interstitial carries.
		 *
		 * Its own scope: it dismisses the reminder and nothing else, and cannot
		 * stand in for the nonce of a 2FA challenge.
		 */
		public const GRACE_NAG_NONCE_ACTION = 'grace_nag';
		public const INPUT_NAME_RESEND_CODE = 'wp-2fa-email-code-resend';

		/**
		 * Set when a resend was asked for too soon after the last code went out.
		 *
		 * @var bool
		 *
		 * @since 4.2.0
		 */
		private static $resend_withheld = false;

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
			\remove_action( 'wp_login', array( __CLASS__, 'wp_login' ), 20, 2 );

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
		 */
		public static function wp_login( $user_login, $user ) {

			if ( class_exists( '\wpengine\sign_on_plugin\WPESignOnPlugin' ) && isset( $_REQUEST['nonce'] ) && isset( $_REQUEST['install_name'] ) ) {
				// $user_nonce   = new \wpengine\sign_on_plugin\UserNonceHelper();
				// $nonce        = \wp_unslash( $_REQUEST['nonce'] );
				// $install_name = \wp_unslash( $_REQUEST['install_name'] );
				// $nonce_data   = $user_nonce->get_nonce_data( $user->ID );

				$req_check = new \wpengine\sign_on_plugin\UserRequestIdHelper();

				$request_id = \get_user_meta( $user->ID, \wpengine\sign_on_plugin\UserRequestIdHelper::WPE_LOGGED_REQUEST_IDS, false );

				// At this stage we are pretty sure that it is wp engine and everything is OK. $nonce_data must be empty because they are using user_meta and it is deleted - so there is no way to do a second validation, but that is enough.
				if ( $req_check->request_id_matches_logged_request_id_for_user( $user->data->user_email, $request_id ) ) {
					return;
				}

				// Capture backtrace.
				$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

				// Format and scan.
				foreach ( $trace as $index => $frame ) {

					// Extract details.
					$file  = $frame['file'] ?? '[internal function]';
					$line  = $frame['line'] ?? '-';
					$func  = $frame['function'] ?? '(unknown)';
					$class = $frame['class'] ?? '';

					// Check for specific class + method.
					if ( 'wpengine\sign_on_plugin\SignOnUserProvider' === $class && 'login_user' === $func ) {
						return;
					}
					if ( '\wpengine\sign_on_plugin\SignOnUserProvider' === $class && 'login_user' === $func ) {
						return;
					}
				}
			}

			// Flywheel auto login part starts here.
			if ( defined( 'FW_DIRECT_LOGIN_SHARED_KEY' ) && isset( $_REQUEST['payload'] ) && isset( $_REQUEST['nonce'] ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {

				/*
				 * This block must fail closed. Core has already queued the auth
				 * cookies by the time wp_login fires, and they are only withdrawn
				 * further down, when the 2FA challenge is raised. Anything thrown
				 * in here - a SodiumException for a nonce of the wrong length, a
				 * TypeError for payload[]=x under strict types - used to end the
				 * request as a fatal with those cookies still on it: a full session
				 * for anyone holding the password. Every failure now falls through
				 * to the challenge instead.
				 */
				try {
					$payload = is_string( $_REQUEST['payload'] ) ? base64_decode( \wp_unslash( $_REQUEST['payload'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					$nonce   = is_string( $_REQUEST['nonce'] ) ? base64_decode( \wp_unslash( $_REQUEST['nonce'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					$key     = is_string( FW_DIRECT_LOGIN_SHARED_KEY ) && is_readable( FW_DIRECT_LOGIN_SHARED_KEY ) ? file_get_contents( FW_DIRECT_LOGIN_SHARED_KEY ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

					if (
						is_string( $payload ) && '' !== $payload
						&& is_string( $nonce ) && SODIUM_CRYPTO_SECRETBOX_NONCEBYTES === strlen( $nonce )
						&& is_string( $key ) && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen( $key )
					) {
						$opened = sodium_crypto_secretbox_open( $payload, $nonce, $key );

						if ( false !== $opened ) {
							return;
						}
					}
				} catch ( \Throwable $e ) {
					// Fall through to the normal 2FA challenge.
					unset( $e );
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

			/*
			 * "Undetermined" only means the user had never logged in - which, from
			 * this moment, is no longer true. Work the status out now rather than
			 * on the next request: left undetermined, an optional user fell past
			 * every branch below that lets an optional user in, to the refusal
			 * meant for an enforced user with no method, and their first login
			 * failed with "no authentication method is available".
			 */
			if ( User_Helper::USER_UNDETERMINED_STATUS === $user_status ) {
				User_Helper::set_user_status( $user );
				$user_status = User_Helper::get_2fa_status( $user );
			}

			/**
			 * User is not required to use the 2FA
			 *
			 * The status is a stored, denormalised value rather than a live one:
			 * get_2fa_status() reads user meta that enrolment paths are expected to
			 * refresh through User_Helper::set_user_status(). Every shipped path
			 * does, but this branch admits the user outright with no second factor,
			 * so a stale copy is the whole difference between a challenge and none
			 * at all — and each new method has to remember the refresh for that to
			 * keep holding.
			 *
			 * is_user_using_two_factor() reads the enabled-methods meta directly, so
			 * it cannot disagree with reality. Confirming against it before acting
			 * on the cached answer costs one meta read and takes the correctness of
			 * this branch off the accuracy of a cache. A user who really has no
			 * method still leaves here; an enrolled one falls through to the
			 * enrolled-user branch below and is challenged.
			 */
			if ( 'no_required_not_enabled' === $user_status && ! User_Helper::is_user_using_two_factor( $user->ID ) ) {

				WP2FA::clear_user_after_login();

				return;
			}

			/*
			 * A user the policy actually excludes is not challenged, even if they hold a method.
			 *
			 * The enrolled-user branch below challenges anybody with a method and returns, which
			 * left the exclusion check further down unreachable for exactly the accounts it was
			 * meant to protect: an excluded user that an enrolment path had given a method was
			 * challenged at every login while the plugin's own screens called them excluded.
			 *
			 * This asks run_user_exclusion_check() rather than is_excluded(). The two are not
			 * interchangeable here. is_excluded() also reports true when a user's role cannot be
			 * resolved at all, which is the right answer for "should we enforce a policy on
			 * them" but the wrong one to act on before a challenge — it would wave an enrolled
			 * user straight past their second factor on nothing more than an unreadable role.
			 * run_user_exclusion_check() only says yes when the user genuinely matches an
			 * exclusion list, so nothing is skipped on the strength of missing data.
			 *
			 * A method on an excluded account is state that should not exist — update_user_state()
			 * clears it whenever it runs — so it is cleared here too, rather than left to raise
			 * the same challenge again at the next login.
			 */
			if ( User_Helper::run_user_exclusion_check( $user ) ) {
				if ( User_Helper::is_user_using_two_factor( $user->ID ) ) {
					Debugging::log( 'Removing a 2FA method from excluded user ' . $user->ID . '; excluded accounts are not challenged.' );

					User_Helper::remove_enabled_method_for_user( $user );
				}

				WP2FA::clear_user_after_login();

				return;
			}

			$global_methods       = Methods::get_available_2fa_methods( User_Helper::get_user_role( $user ) );
			$users_method         = User_Helper::get_enabled_method_for_user( $user );
			$users_method_removed = false;

			$enforced_check = User_Helper::is_enforced( $user, true );

			// An enforced user must always have a method to use or set up: the defaults, if the role has none.
			if ( $enforced_check && empty( $global_methods ) ) {
				Methods::ensure_default_methods_available( (string) User_Helper::get_user_role( $user ) );
				$global_methods = Methods::get_available_2fa_methods( User_Helper::get_user_role( $user ) );
			}

			if ( $enforced_check && ! empty( $users_method ) && empty( \array_intersect( array( $users_method ), $global_methods ) ) ) {
				$users_method_removed = true;
			}

			/*
			 * Locked for too many wrong codes: refused here, whatever comes next -
			 * the challenge, a reset and setup, the grace screen. Each of those
			 * used to answer the lock in its own way or not at all.
			 */
			self::refuse_if_second_factor_locked( $user );

			// leave if the user has already got 2FA authentication configured.
			if ( ! $users_method_removed && User_Helper::is_user_using_two_factor( $user->ID ) ) {
				/*
				 * The check below answers "is the method this user configured still a
				 * thing on this site?", and it throws when it cannot say.
				 *
				 * It used to fail open: the exception was swallowed and the login was
				 * allowed to complete with no second factor at all. But we are already
				 * inside is_user_using_two_factor(), so the user has 2FA configured —
				 * being unable to describe their method is a reason to challenge them,
				 * never a reason to wave them through. Showing the form is the safe
				 * branch in both directions, so both take it.
				 */
				try {
					Settings::is_provider_enabled_for_role( User_Helper::get_user_role(), User_Helper::get_enabled_method_for_user( $user ) );
				} catch ( \Exception $e ) {
					/*
					 * Insisting on the challenge is not much use if the request has no
					 * methods left to render it with, so pin the built-ins before going
					 * on. Declines to act when a real provider list is present — see
					 * Settings::apply_fallback_providers().
					 */
					$fell_back = Settings::apply_fallback_providers();

					Debugging::log(
						'2FA provider check failed for user ' . $user->ID . '; challenging anyway'
						. ( $fell_back ? ' on the built-in methods' : '' )
						. '. Reason: ' . $e->getMessage()
					);
				}

				self::clear_session_and_show_2fa_form( $user );

				return;
			}

			/*
			 * Enforced, and the method they set up is no longer offered. They used
			 * to be refused here, with no way to set anything else up. Their setup
			 * is cleared instead and the policy applied afresh - a new grace period
			 * from now if there is one - and they are sent to set 2FA up again.
			 */
			if ( $users_method_removed && User_Helper::is_user_using_two_factor( $user->ID ) ) {
				self::refuse_if_second_factor_locked( $user );
				User_Helper::restart_2fa_setup( $user );
				self::redirect_to_setup( $user );
			}

			// leave if 2FA is not enforced, but optional.
			$enforcement_policy = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'enforcement-policy' );
			if ( 'do-not-enforce' === $enforcement_policy ) {

				WP2FA::clear_user_after_login();

				return;
			}

			// leave if the user is not required to have 2FA enabled due to and exclusion rule.
			if ( User_Helper::is_excluded( $user->ID ) ) {

				WP2FA::clear_user_after_login();

				return;
			}

			// redirect to 2FA setup page if the 2FA configuration is enforced to happen instantly.
			$is_user_instantly_enforced = User_Helper::get_user_enforced_instantly( $user );
			if ( true === (bool) $is_user_instantly_enforced ) {
				self::redirect_to_setup( $user );
			}

			// if there is some grace period configured, and it is not instant, we can let the users in (if they needed to
			// be blocked, this would have already happened in wp_authenticate).
			$grace_policy = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'grace-policy' );
			if ( 'use-grace-period' === $grace_policy ) {

				if ( ! Grace_Period_Notifications::notify_using_dashboard( $user ) ) {
					$global_methods   = Methods::get_available_2fa_methods( User_Helper::get_user_role( $user ) );
					$users_method     = User_Helper::get_enabled_method_for_user( $user );
					$is_nag_dismissed = User_Helper::get_nag_status();
					$is_nag_needed    = $enforced_check;


					if ( ! $is_nag_dismissed && $is_nag_needed ) {

						$login_nonce = self::create_login_nonce( $user->ID, self::GRACE_NAG_NONCE_ACTION );
						if ( ! $login_nonce ) {
							\wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
						}

						if ( isset( $_REQUEST['_wp_http_referer'] ) && ! empty( $_REQUEST['_wp_http_referer'] ) ) {
							$redirect_to = \esc_url_raw( \wp_unslash( $_REQUEST['_wp_http_referer'] ) );
						}

						if ( isset( $_REQUEST['redirect_to'] ) && ! empty( $_REQUEST['redirect_to'] ) ) {
							$redirect_to = \esc_url_raw( \wp_unslash( $_REQUEST['redirect_to'] ) );
						}

						if ( empty( $redirect_to ) ) {
							// Not network_admin_url(): on a network only super admins can open it.
							$redirect_to = self::default_redirect_for( $user );
						}

						self::show_2fa_form_grace_form( $user, $login_nonce['key'], $redirect_to );
					} else {

						WP2FA::clear_user_after_login();

						return;
					}
				} else {
					WP2FA::clear_user_after_login();

					return;
				}
			}

			$provider = User_Helper::get_enabled_method_for_user( $user );
			if ( '' === trim( (string) $provider ) ) {
				/*
				 * Enforced, nothing set up yet, and no grace period - so 2FA is due
				 * now. That is the instant-enforcement case above, for a user whose
				 * flag was never recorded (it is set when their policy is worked
				 * out, which may not have happened yet). They used to be stopped
				 * here with "no authentication method is available", when all they
				 * had to do was set one up. Their role is given the default methods
				 * if it has none, and they are sent to set 2FA up.
				 */
				User_Helper::set_user_enforced_instantly( true, $user );
				self::redirect_to_setup( $user );
			}

			self::clear_session_and_show_2fa_form( $user );
		}

		/**
		 * Makes sure the user's role has a method to set up - the default ones if it has none.
		 *
		 * Stops the login only if even the defaults are not available to the role
		 * (a role policy that switches every method off): then there is nothing
		 * to send the user to.
		 *
		 * @param \WP_User $user - The user logging in.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function ensure_methods_to_set_up( \WP_User $user ): void {
			$role = (string) User_Helper::get_user_role( $user );

			if ( empty( Methods::get_available_2fa_methods( $role ) ) ) {
				Methods::ensure_default_methods_available( $role );
			}

			if ( empty( Methods::get_available_2fa_methods( $role ) ) ) {
				self::fail_closed_after_first_factor(
					$user,
					__( 'Two-factor authentication is required, but no authentication method is available. Please contact the website administrator.', 'wp-2fa' )
				);
			}
		}

		/**
		 * Sends a user who has to set 2FA up now to the setup page, and stops.
		 *
		 * @param \WP_User $user - The user logging in.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function redirect_to_setup( \WP_User $user ): void {
			// A user locked for wrong codes is not let in on the password alone, setup or not.
			self::refuse_if_second_factor_locked( $user );

			// Never to a setup page with nothing on it.
			self::ensure_methods_to_set_up( $user );

			\wp_safe_redirect(
				self::get_2fa_setup_url( $user ) . ( ( isset( $_REQUEST['_wp_http_referer'] ) && ! empty( $_REQUEST['_wp_http_referer'] ) ) ? '?return=' . urlencode( \esc_url_raw( \wp_unslash( $_REQUEST['_wp_http_referer'] ) ) ) : '' ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
			exit();
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
			$redirect_to = self::safe_redirect_target( $redirect_to );
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

			$interim_login   = isset( $_REQUEST['interim-login'] ) ? filter_var( wp_unslash( $_REQUEST['interim-login'] ), FILTER_VALIDATE_BOOLEAN ) : false; //phpcs:ignore
			$rememberme     = intval( self::rememberme() );
			$global_methods = Methods::get_available_2fa_methods( User_Helper::get_user_role( $user ) );
			$users_method   = User_Helper::get_enabled_method_for_user( $user );

			if ( ! function_exists( 'login_header' ) ) {
				// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
				include_once WP_2FA_PATH . 'includes/functions/login-header.php';
			}

			login_header();

			if ( ! empty( $error_msg ) ) {
				echo '<div id="login_error"><strong class="wp-2fa-error-msg">' . \apply_filters( 'login_errors', \esc_html( $error_msg ) ) . '</strong><br /></div>';
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


				// Resolve against the user who is signing in, not whoever happens
				// to be in User_Helper's ambient slot: this also puts that user in
				// context for the role-based fallback inside the formatter.
				$grace_expiry = (int) User_Helper::get_user_expiry_date( $user );

				$setup_url = Settings::get_setup_page_link( true );

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
					url: '<?php echo \esc_url( \admin_url( 'admin-ajax.php' ) ); ?>',
					/*
					 * Not a WordPress nonce: this page is built while the user is
					 * being logged in, before WordPress knows who they are, so one
					 * minted here belongs to no one and is refused. The login
					 * transaction's own nonce, in the form above, is theirs.
					 */
					type: 'POST',
					data: {
						action: 'dismiss_nag',
						'wp-auth-nonce': jQuery( '#wp-auth-nonce' ).val()
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
			\wp_clear_auth_cookie();

			self::refuse_if_second_factor_locked( $user );

			self::show_two_factor_login( $user );
			exit;
		}

		/**
		 * Locked for too many wrong codes: say so now, with when to try again, and stop.
		 *
		 * The code form used to be shown regardless, and the lock only came up
		 * once a code - even the right one - had been typed and refused. Asked
		 * before anything else the password step might do with the user: a reset
		 * and redirect to setup also cleared the attempts, which let a locked
		 * user straight in. The session the password created is ended first.
		 *
		 * @param \WP_User $user - The user logging in.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function refuse_if_second_factor_locked( \WP_User $user ): void {
			if ( ! Authentication::second_factor_locked( $user ) ) {
				return;
			}

			self::destroy_current_session_for_user( $user );
			\wp_clear_auth_cookie();

			\wp_die(
				\esc_html( Authentication::second_factor_lock_message( $user ) ),
				\esc_html__( 'Account temporarily locked', 'wp-2fa' ),
				array(
					'response'  => 429,
					'link_url'  => \esc_url( \wp_login_url() ),
					'link_text' => \esc_html__( 'Back to the login page', 'wp-2fa' ),
				)
			);
		}

		/**
		 * Reject a completed first factor when the required MFA flow cannot start.
		 *
		 * A configuration or provider failure is never authority to retain the
		 * WordPress session that core has just issued.
		 *
		 * @param \WP_User $user    User whose first-factor session must be revoked.
		 * @param string   $message Safe message to display to the user.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function fail_closed_after_first_factor( $user, string $message ): void {
			self::destroy_current_session_for_user( $user );
			\wp_clear_auth_cookie();
			\wp_die( \esc_html( $message ), \esc_html__( 'Authentication blocked', 'wp-2fa' ), array( 'response' => 403 ) );
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

			$page_slug = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'custom-user-page-url' );

			// Only use the custom page if the feature is enabled.
			if ( 'yes' !== Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'create-custom-user-page' ) ) {
				$page_slug = '';
			}

			// Lets check for multisite first and if that is the case - lets search for that page on the user's default blog.
			if ( WP_Helper::is_multisite() && false !== Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'separate-multisite-page-url' ) && ! empty( $page_slug ) ) {
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

			return network_admin_url( 'profile.php' );
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
		 *
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
				$user = \wp_get_current_user();
			}

			$login_nonce = self::create_login_nonce( $user->ID );
			if ( ! $login_nonce ) {
				\wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
			}

			$redirect_to = isset( $_REQUEST['redirect_to'] ) ? \esc_url_raw( \wp_unslash( $_REQUEST['redirect_to'] ) ) : (string) \apply_filters( 'login_redirect', '', '', $user );

			if ( self::is_woocommerce_activated() ) {

				$redirect_to_woo = isset( $_REQUEST['redirect'] ) ? \esc_url_raw( \wp_unslash( $_REQUEST['redirect'] ) ) : '';

				if ( empty( $redirect_to_woo ) ) {

					if (
					isset( $_POST['woocommerce-login-nonce'] ) && \wp_verify_nonce( $_POST['woocommerce-login-nonce'], 'woocommerce-login' )
					) {
						$referer = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
						if ( ! empty( $referer ) ) {

							// Absolute URL required for validation.
							$referer = \wp_validate_redirect( $referer, $referer );

							// Extra hardening: block external URLs.
							if ( strpos( $referer, \home_url() ) === 0 ) {
								$redirect_to_woo = $referer;
							}
						}
					}
				}

				if ( empty( $redirect_to_woo ) ) {
					$redirect_to = isset( $_REQUEST['redirect_to'] ) ? \esc_url_raw( \wp_unslash( $_REQUEST['redirect_to'] ) ) : (string) \apply_filters( 'login_redirect', $redirect_to, '', $user );
				} else {
					$redirect_to = $redirect_to_woo;
				}
			}

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( \is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
					$redirect_to = user_admin_url();
				} elseif ( \is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = \get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
				}
			}

			if ( empty( $redirect_to ) ) {
				$redirect_to = admin_url();
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
		 * Serves the backup code screen on requests that wp-login.php does not route.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function backup_2fa_early() {
			// wp-login.php fires login_form_backup_2fa for us, and does so with its
			// own login_header() already in place. Only requests served elsewhere -
			// a front end login, or a wp-login.php that the site has restricted -
			// need picking up this early.
			if ( self::is_wp_login_request() ) {
				return;
			}

			// The backup link is only ever rendered on a front end page view, so
			// keep this off every other kind of request entirely.
			if ( \is_admin() || \wp_doing_ajax() || \wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
				return;
			}

			$action = isset( $_GET['action'] ) ? \sanitize_key( \wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 'backup_2fa' !== $action ) {
				return;
			}

			self::backup_2fa();
		}

		/**
		 * Display the Backup code 2fa screen.
		 *
		 * @since 0.1-dev
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
			$redirect_to = self::safe_redirect_target( $redirect_to );

			/*
			 * Rendering a challenge is not harmless: the email page sends a code as
			 * it renders. So a provider named in the request (backup_2fa takes it
			 * from the query string) is only honoured when it is one of the user's
			 * own methods; anything else falls back to the method they set up.
			 * The documented object form can only come from PHP, never from a
			 * request, so it is left as it was.
			 */
			if ( ! $provider
				|| ( Backup_Codes::METHOD_NAME === $provider && ! Backup_Codes::are_backup_codes_enabled_for_role( User_Helper::get_user_role( $user ) ) )
				|| ( \is_string( $provider ) && ! self::is_allowed_login_provider( $user, $provider ) ) ) {
				$provider = User_Helper::get_enabled_method_for_user( $user );
			}

			$codes_remaining = Backup_Codes::codes_remaining_for_user( $user );
			$interim_login   = isset( $_REQUEST['interim-login'] ) ? filter_var( wp_unslash( $_REQUEST['interim-login'] ), FILTER_VALIDATE_BOOLEAN ) : false; //phpcs:ignore
			$rememberme      = intval( self::rememberme() );

			\add_filter(
				'login_body_class',
				function ( $classes, $action ) {
					$classes[] = 'wp-2fa-login';

					return $classes;
				},
				10,
				2
			);

			if ( ! function_exists( 'login_header' ) ) {
				// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
				include_once WP_2FA_PATH . 'includes/functions/login-header.php';
			}

			\wp_enqueue_script( 'wp_2fa_user_login_scripts' );

			\login_header();

			if ( ! empty( $error_msg ) ) {
				echo '<div id="login_error"><strong class="wp-2fa-error-msg">' . apply_filters( 'login_errors', \esc_html( $error_msg ) ) . '</strong><br /></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
					 * @param string $login_nonce - The verified login transaction nonce.
					 *
					 * @since 2.0.0
					 */
					\do_action( WP_2FA_PREFIX . 'login_form', $user, $provider, $login_nonce );
				}

				/**
				 * Gives the ability to remove the submit button from the plugin forms
				 *
				 * @param bool - Default at this point is true - no method is selected.
				 * @param array $input - The input array with all the data.
				 *
				 * @since 2.0.0
				 */
				$submit_button_disabled = \apply_filters( WP_2FA_PREFIX . 'login_disable_submit_button', false, $user, $provider );
				if ( ! $submit_button_disabled ) {

					/**
					 * Allows 3rd parties to render something before the login button on the 2FA "login" form.
					 *
					 * @param \WP_User $user - User for which the login form is shown.
					 * @param string $provider - The name of the provider.
					 *
					 * @since 2.0.0
					 */
					\do_action( WP_2FA_PREFIX . 'login_before_submit_button', $user, $provider );
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
						$button_text = \apply_filters( WP_2FA_PREFIX . 'login_button_text', \esc_html__( 'Log In', 'wp-2fa' ) );

						\submit_button(
							$button_text,
							'primary',   // $type
							'wp-submit', // $name
							true,        // $wrap
							'data-nonce="' . wp_create_nonce( 'wp-rest' ) . '"'         // $other_attributes
						);
					}
					?>
					</p>
					<?php
					if ( Email::METHOD_NAME === $provider ) {
						?>
						<p class="wp-2fa-email-resend">
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
				 * @param string $login_nonce - The verified login transaction nonce.
				 *
				 * @since 2.0.0
				 */
				\do_action( WP_2FA_PREFIX . 'login_html_before_end', $user, $provider, $login_nonce );
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
							<?php echo \esc_html( \wp_strip_all_tags( WP2FA::get_wp2fa_white_label_setting( 'backup-codes-login-text', true ) ) ); ?>
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
			\do_action( WP_2FA_PREFIX . 'login_html_after_backup_providers', $user, $provider, $login_nonce, $redirect_to, $rememberme );

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
		#login form > p {
				margin-bottom: 15px;
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
		 * A redirect target that is safe to put into the page.
		 *
		 * The challenge and grace screens carry redirect_to in a hidden field,
		 * and two client-side redirects - "I'll do it later" and the REST login
		 * script's fallback - navigate to that field as it stands. esc_url_raw()
		 * only filters out dangerous schemes, so wp-login.php?redirect_to=
		 * https://evil.example/ used to land a freshly authenticated user on
		 * someone else's site. The server-side redirects were never affected;
		 * they all go through wp_safe_redirect().
		 *
		 * @param mixed $redirect_to The requested target.
		 *
		 * @return string The target if its host is allowed, admin_url() otherwise,
		 *                and an empty string left empty for callers that treat it
		 *                as "choose for me".
		 *
		 * @since 4.2.0
		 */
		public static function safe_redirect_target( $redirect_to ): string {
			$redirect_to = \is_string( $redirect_to ) ? $redirect_to : '';

			if ( '' === $redirect_to ) {
				return '';
			}

			// With an empty fallback, an empty answer can only mean "not allowed".
			$validated = (string) \wp_validate_redirect( $redirect_to, '' );

			if ( '' === $validated ) {
				return \admin_url();
			}

			/*
			 * wp_validate_redirect() also re-roots relative targets - wp-admin/
			 * comes back as /wp-admin/ - and later code compares the posted value
			 * against 'wp-admin/' exactly. So a relative target is handed back as
			 * it was asked for, but only when sanitising leaves it untouched:
			 * whatever the sanitiser would strip is exactly what must not reach a
			 * browser. /\evil.example is re-rooted to a harmless path, while a
			 * browser reads the original as //evil.example.
			 */
			if ( null === \wp_parse_url( $redirect_to, PHP_URL_HOST )
				&& null === \wp_parse_url( $redirect_to, PHP_URL_SCHEME )
				&& \wp_sanitize_redirect( $redirect_to ) === $redirect_to ) {
				return $redirect_to;
			}

			return $validated;
		}

		/**
		 * Where to send a user after login when the login form named no target.
		 *
		 * The same choice wp-login.php makes: the user admin for a network user
		 * without a site, the profile for users who cannot edit posts (or the
		 * front end if they cannot even read), the dashboard for everyone else.
		 * Never the network admin, which on a network only super admins can open.
		 *
		 * @param \WP_User $user - The user signing in.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function default_redirect_for( \WP_User $user ): string {
			if ( \is_multisite() && ! \get_active_blog_for_user( $user->ID ) && ! \is_super_admin( $user->ID ) ) {
				return \user_admin_url();
			}

			if ( ! $user->has_cap( 'edit_posts' ) ) {
				return $user->has_cap( 'read' ) ? \admin_url( 'profile.php' ) : \home_url();
			}

			return \get_dashboard_url( $user->ID );
		}

		/**
		 * The providers this user may be challenged with at login.
		 *
		 * That is the method they configured, plus the backup methods they have
		 * actually set up - not everything their role would permit. Anything
		 * wider lets whoever holds the password pick the weakest factor on offer.
		 *
		 * @param \WP_User $user The user being authenticated.
		 *
		 * @return string[]
		 *
		 * @since 4.2.0
		 */
		public static function get_allowed_login_providers( $user ): array {
			$allowed = array();

			$primary = User_Helper::get_enabled_method_for_user( $user );
			if ( \is_string( $primary ) && '' !== $primary ) {
				$allowed[] = $primary;
			}

			$backups = User_Helper::get_enabled_backup_methods_for_user( $user );
			if ( \is_array( $backups ) ) {
				foreach ( array_keys( $backups ) as $backup ) {
					if ( \is_string( $backup ) && '' !== $backup ) {
						$allowed[] = $backup;
					}
				}
			}

			/**
			 * Filters the providers a user may be challenged with at login.
			 *
			 * @param string[] $allowed The user's configured method and set-up backup methods.
			 * @param \WP_User $user    The user being authenticated.
			 *
			 * @since 4.2.0
			 */
			$allowed = \apply_filters( WP_2FA_PREFIX . 'allowed_login_providers', array_values( array_unique( $allowed ) ), $user );

			return \is_array( $allowed ) ? array_values( array_filter( $allowed, 'is_string' ) ) : array();
		}

		/**
		 * Whether the user may be challenged with the given provider at login.
		 *
		 * @param \WP_User $user     The user being authenticated.
		 * @param string   $provider The provider name.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_allowed_login_provider( $user, string $provider ): bool {
			return '' !== $provider && in_array( $provider, self::get_allowed_login_providers( $user ), true );
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

			$base = self::login_url_base( $scheme );

			/**
			 * Filters the URL the second authentication step posts back to.
			 *
			 * Every control on the 2FA challenge - the code form, the "Resend
			 * code" button, the backup code and backup email links - is built on
			 * top of this. Sites that restrict wp-login.php by IP, or that move
			 * it, can point the whole second step somewhere reachable instead of
			 * having those controls fail.
			 *
			 * @param string $base   The base URL, without the query arguments.
			 * @param array  $params The query arguments about to be appended.
			 * @param string $scheme The URL scheme context.
			 *
			 * @since 4.2.0
			 */
			$base = (string) \apply_filters( WP_2FA_PREFIX . 'login_url', $base, $params, $scheme );

			if ( '' === trim( $base ) ) {
				$base = \site_url( 'wp-login.php', $scheme );
			}

			return add_query_arg( $params, $base );
		}

		/**
		 * Works out which URL the second authentication step belongs on.
		 *
		 * The challenge is rendered in place by show_two_factor_login(), so a login
		 * that started on a front end form - WooCommerce, LearnDash, any theme login
		 * - shows it on that front end URL. Sending the follow up requests back to
		 * wp-login.php from there is a round trip that buys nothing and that fails
		 * outright wherever wp-login.php is restricted. Stay where we already are.
		 *
		 * A request that is already on wp-login.php keeps using wp-login.php, so the
		 * ordinary login flow is untouched.
		 *
		 * @param string $scheme URL scheme context.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		private static function login_url_base( $scheme ): string {
			$default = \site_url( 'wp-login.php', $scheme );

			if ( self::is_wp_login_request() ) {
				return $default;
			}

			$current = self::current_request_url();

			return ( '' === $current ) ? $default : $current;
		}

		/**
		 * Whether the request being served is wp-login.php itself.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_wp_login_request(): bool {
			if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
				return true;
			}

			if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
				return false;
			}

			$path = (string) \wp_parse_url( \esc_url_raw( \wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );

			return 'wp-login.php' === basename( $path );
		}

		/**
		 * The URL of the current request, with the arguments this flow appends itself
		 * stripped back off so that they do not accumulate across steps.
		 *
		 * @return string Empty string when the request offers nothing usable.
		 *
		 * @since 4.2.0
		 */
		private static function current_request_url(): string {
			if ( empty( $_SERVER['REQUEST_URI'] ) ) {
				return '';
			}

			$request = \esc_url_raw( \wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$path    = (string) \wp_parse_url( $request, PHP_URL_PATH );

			if ( '' === $path ) {
				return '';
			}

			$query = (string) \wp_parse_url( $request, PHP_URL_QUERY );
			$args  = array();

			if ( '' !== $query ) {
				\wp_parse_str( $query, $args );

				foreach ( array( 'action', 'provider', 'wp-auth-id', 'wp-auth-nonce', 'redirect_to', 'rememberme', 'interim-login' ) as $own ) {
					unset( $args[ $own ] );
				}
			}

			// REQUEST_URI is already rooted at the domain, so a subdirectory install
			// needs no special handling here: only the scheme and host are missing.
			$home = \wp_parse_url( \home_url() );

			if ( empty( $home['host'] ) ) {
				return '';
			}

			$host = $home['host'];

			if ( ! empty( $home['port'] ) ) {
				$host .= ':' . $home['port'];
			}

			$url = ( empty( $home['scheme'] ) ? 'https' : $home['scheme'] ) . '://' . $host . $path;

			return empty( $args ) ? $url : \add_query_arg( $args, $url );
		}

		/**
		 * Create the login nonce.
		 *
		 * @since 0.1-dev
		 *
		 * @param int    $user_id User ID.
		 * @param string $action  The action this nonce is scoped to (default: 'login_2fa').
		 * @param int|null $ttl Lifetime in seconds; defaults to fifteen minutes.
		 *
		 * @return array|bool
		 */
		public static function create_login_nonce( $user_id, $action = 'login_2fa', ?int $ttl = null ) {
			$user = \get_userdata( $user_id );
			if ( ! $user ) {
				return false;
			}
			$login_nonce                   = array();
			$login_nonce['key']            = bin2hex( random_bytes( 32 ) );
			$login_nonce['expiration']     = time() + max( 1, $ttl ?? ( 15 * MINUTE_IN_SECONDS ) );
			$login_nonce['action']         = sanitize_key( $action );
			$stored_nonce                  = $login_nonce;
			$stored_nonce['key_hash']      = self::login_nonce_hash( $login_nonce['key'] );
			$stored_nonce['password_hash'] = self::login_nonce_hash( $user->user_pass );
			unset( $stored_nonce['key'] );

			self::prune_login_nonces( (int) $user_id );

			/*
			 * Each row is one authentication transaction. update_user_meta() made
			 * this account-wide: a second browser starting MFA replaced the first
			 * browser's nonce. Separate rows allow concurrent sign-ins and, more
			 * importantly, ensure consuming one transaction cannot consume another.
			 */
			if ( false === add_user_meta( $user_id, self::USER_META_NONCE_KEY, $stored_nonce, false ) ) {
				return false;
			}

			return $login_nonce;
		}

		/**
		 * Delete the login nonce.
		 *
		 * @since 0.1-dev
		 *
		 * @param int    $user_id User ID.
		 * @param string $nonce   Transaction nonce to remove. Empty removes all records.
		 *
		 * @return void
		 */
		public static function delete_login_nonce( $user_id, $nonce = '' ) {
			if ( '' === (string) $nonce ) {
				\delete_user_meta( $user_id, self::USER_META_NONCE_KEY );
				return;
			}

			foreach ( self::get_login_nonce_records( (int) $user_id ) as $record ) {
				if ( self::login_nonce_record_matches( $record, (string) $nonce ) ) {
					delete_user_meta( $user_id, self::USER_META_NONCE_KEY, $record );
					return;
				}
			}
		}

		/**
		 * Verify the login nonce.
		 *
		 * @since 0.1-dev
		 *
		 * @param int    $user_id User ID.
		 * @param string $nonce   Login nonce.
		 * @param string $action  Expected action scope (default: 'login_2fa').
		 * @return bool
		 */
		public static function verify_login_nonce( $user_id, $nonce, $action = 'login_2fa' ) {
			foreach ( self::get_login_nonce_records( (int) $user_id ) as $login_nonce ) {
				if ( ! self::login_nonce_record_matches( $login_nonce, (string) $nonce ) ) {
					continue;
				}

				if ( time() > (int) ( $login_nonce['expiration'] ?? 0 ) ) {
					delete_user_meta( $user_id, self::USER_META_NONCE_KEY, $login_nonce );
					return false;
				}

				// Password recovery must also revoke the first factor of pending logins.
				$user = \get_userdata( $user_id );
				if ( ! $user || ( isset( $login_nonce['password_hash'] ) && ! hash_equals( (string) $login_nonce['password_hash'], self::login_nonce_hash( $user->user_pass ) ) ) ) {
					delete_user_meta( $user_id, self::USER_META_NONCE_KEY, $login_nonce );
					return false;
				}

				// Records predating action scoping remain valid for login_2fa only.
				$stored_action = isset( $login_nonce['action'] ) ? sanitize_key( $login_nonce['action'] ) : 'login_2fa';
				if ( sanitize_key( $action ) !== $stored_action ) {
					delete_user_meta( $user_id, self::USER_META_NONCE_KEY, $login_nonce );
					return false;
				}

				return true;
			}

			// An invalid transaction must not invalidate other browsers' transactions.
			self::prune_login_nonces( (int) $user_id );
			return false;
		}

		/**
		 * Atomically retire a valid transaction before granting a session.
		 *
		 * Two requests may validate the same factor concurrently. Only the request
		 * that actually deletes the exact transaction row is allowed to continue.
		 *
		 * @param int    $user_id User ID.
		 * @param string $nonce   Transaction nonce.
		 * @param string $action  Expected action scope.
		 *
		 * @return bool True only for the one request that consumed the transaction.
		 *
		 * @since 4.2.0
		 */
		public static function consume_login_nonce( $user_id, $nonce, $action = 'login_2fa' ): bool {
			if ( ! self::verify_login_nonce( $user_id, $nonce, $action ) ) {
				return false;
			}

			foreach ( self::get_login_nonce_records( (int) $user_id ) as $record ) {
				if ( self::login_nonce_record_matches( $record, (string) $nonce ) ) {
					return (bool) delete_user_meta( $user_id, self::USER_META_NONCE_KEY, $record );
				}
			}

			return false;
		}

		/**
		 * Return every structurally valid MFA transaction for a user.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return array<int,array<string,mixed>>
		 *
		 * @since 4.2.0
		 */
		private static function get_login_nonce_records( int $user_id ): array {
			$records = get_user_meta( $user_id, self::USER_META_NONCE_KEY, false );

			return array_values( array_filter( $records, 'is_array' ) );
		}

		/**
		 * Compare a bearer nonce with a stored hash or a legacy cleartext record.
		 *
		 * @param array  $record Stored transaction record.
		 * @param string $nonce  Bearer nonce supplied by the client.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function login_nonce_record_matches( array $record, string $nonce ): bool {
			if ( isset( $record['key_hash'] ) ) {
				return hash_equals( (string) $record['key_hash'], self::login_nonce_hash( $nonce ) );
			}

			return isset( $record['key'] ) && hash_equals( (string) $record['key'], $nonce );
		}

		/**
		 * Hash a bearer nonce before persistence.
		 *
		 * @param string $nonce Bearer nonce.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		private static function login_nonce_hash( string $nonce ): string {
			return hash_hmac( 'sha256', $nonce, wp_salt( 'auth' ) );
		}

		/**
		 * Remove abandoned transaction records after their fixed lifetime.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function prune_login_nonces( int $user_id ): void {
			$now = time();

			foreach ( self::get_login_nonce_records( $user_id ) as $record ) {
				if ( $now > (int) ( $record['expiration'] ?? 0 ) ) {
					delete_user_meta( $user_id, self::USER_META_NONCE_KEY, $record );
				}
			}
		}

		/**
		 * Whether this request offers something to be checked against the second
		 * factor - and so has to spend one of the limited attempts.
		 *
		 * Worked out by elimination rather than by recognising answers: a request
		 * made of nothing but the fields every challenge request carries, and an
		 * empty code, answers nothing. Anything else counts - including a field no
		 * provider in this plugin reads, since an extension might, and including
		 * the query string, which providers read through $_REQUEST as well. So a
		 * code cannot be slipped past the limit under a name this list does not
		 * know.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_answering_challenge(): bool {
			$carried = array(
				'action',
				'wp-auth-id',
				'wp-auth-nonce',
				'provider',
				'redirect_to',
				'rememberme',
				'interim-login',
				'testcookie',
				self::INPUT_NAME_RESEND_CODE,
			);

			// phpcs:disable WordPress.Security.NonceVerification -- Only the shape of the request is looked at.
			foreach ( array( $_GET, $_POST ) as $fields ) {
				foreach ( (array) $fields as $name => $value ) {
					if ( \in_array( $name, $carried, true ) ) {
						continue;
					}
					// The code field is on every challenge form; empty, it answers nothing.
					if ( 'authcode' === $name && \is_string( $value ) && '' === \trim( $value ) ) {
						continue;
					}

					return true;
				}
			}
			// phpcs:enable

			return false;
		}

		/**
		 * The notice shown after a resend was asked for.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function resend_notice(): string {
			if ( self::$resend_withheld ) {
				return \esc_html__( 'A code was sent a moment ago. Please check your inbox, or wait a minute before asking for another one.', 'wp-2fa' );
			}

			if ( Code_Guard::dispatch_failed() ) {
				return self::dispatch_failure_message();
			}

			return \esc_html__( 'A new code has been sent.', 'wp-2fa' );
		}

		/**
		 * Login form validation.
		 *
		 * @since 0.1-dev
		 */
		public static function login_form_validate_2fa() {
			if ( ! isset( $_POST['wp-auth-id'], $_POST['wp-auth-nonce'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return;
			}

			// If form data comes from 2 factor password reset - bounce.
			if ( isset( $_POST['reset'] ) && 'reset-2fa' === $_POST['reset'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return;
			}

			$auth_id = (int) $_POST['wp-auth-id']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$user    = \get_userdata( $auth_id );
			if ( ! $user ) {
				return;
			}

			$nonce = ( isset( $_POST['wp-auth-nonce'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['wp-auth-nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( true !== self::verify_login_nonce( $user->ID, $nonce ) ) {
				\wp_safe_redirect( \get_bloginfo( 'url' ) );
				exit;
			}

			if ( Authentication::second_factor_locked( $user ) ) {
				self::delete_login_nonce( $user->ID, $nonce );
				\wp_die( \esc_html( Authentication::second_factor_lock_message( $user ) ), '', array( 'response' => 429 ) );
			}

			$provider = isset( $_POST['provider'] ) ? \sanitize_textarea_field( \wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			/*
			 * The provider arrives from the form, so it is the attacker's choice as
			 * much as the user's. The role check below only asks whether a method
			 * exists for the role - on its own it let a TOTP user be challenged by
			 * email code instead, which is precisely the compromised-mailbox case
			 * TOTP is there to cover. It has to be a method this user set up.
			 */
			if ( ! self::is_allowed_login_provider( $user, $provider ) ) {
				self::delete_login_nonce( $user->ID, $nonce );
				\wp_die( \esc_html__( 'Invalid provider.', 'wp-2fa' ), '', array( 'response' => 403 ) );
			}

			if ( ! Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user ), $provider ) ) {
				wp_die(
					\wp_sprintf(
						'<p><strong>%1$s</strong>: %2$s</p>',
						'WP 2FA',
						__( 'A server error prevented your login from being verified. Please contact the website administrator.', 'wp-2fa' )
					) . \esc_html__( 'Invalid provider.', 'wp-2fa' )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			/*
			 * Nothing offered and no new code asked for: this is the first sight of
			 * the challenge - what the passkey hand-over posts, and what any other
			 * caller routing a user here will look like. Show the challenge, clean.
			 *
			 * Left to the providers, only email knew this; TOTP, backup codes and
			 * the rest treated it as a wrong code - an error on a form nobody had
			 * typed into yet, and wp_login_failed, which security plugins count
			 * against a visitor who has just signed in with a passkey.
			 */
			if ( ! self::is_answering_challenge() && ! isset( $_REQUEST[ self::INPUT_NAME_RESEND_CODE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				self::delete_login_nonce( $user->ID, $nonce );
				$login_nonce = self::create_login_nonce( $user->ID );
				if ( ! $login_nonce ) {
					\wp_die( \esc_html__( 'Failed to create a login nonce.', 'wp-2fa' ) );
				}
				$redirect_to = isset( $_REQUEST['redirect_to'] ) ? \esc_url_raw( \wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				self::login_html( $user, $login_nonce['key'], $redirect_to, '', $provider );

				exit;
			}

			/*
			 * An attempt is a code offered for checking. Asking for a new code, or
			 * being handed over to the challenge after a passkey, offers none - yet
			 * every request here used to spend one, so five resends, or five
			 * abandoned passkey sign-ins, locked the user out for fifteen minutes
			 * before they had typed anything.
			 */
			if ( self::is_answering_challenge() && ! Authentication::reserve_second_factor_attempt( $user ) ) {
				self::delete_login_nonce( $user->ID, $nonce );
				\wp_die( \esc_html( Authentication::second_factor_lock_message( $user ) ), '', array( 'response' => 429 ) );
			}

			$authenticated = false;

			/**
			 * Allows providers to validate their 2FA "login" form.
			 * Providers should return true on successful validation, or call exit on failure
			 * (e.g. to re-display the login form with an error message).
			 * Default-deny: if no provider signals success, authentication is rejected.
			 *
			 * @param bool     $authenticated Whether authentication has passed.
			 * @param \WP_User $user          The user being authenticated.
			 * @param string   $provider      The provider name.
			 *
			 * @since 2.0.0
			 * @since 4.0.1 Changed from do_action to apply_filters with $authenticated parameter (default-deny).
			 */
			$authenticated = \apply_filters( WP_2FA_PREFIX . 'validate_login_form', $authenticated, $user, $provider );

			/**
			 * Filters whether a provider has authenticated the user.
			 * This is a secondary gate that can override the result of the validation filter.
			 *
			 * @param bool     $authenticated Whether the user has been authenticated.
			 * @param \WP_User $user          The user being authenticated.
			 * @param string   $provider      The provider name.
			 *
			 * @since 4.0.0
			 */
			$authenticated = \apply_filters( WP_2FA_PREFIX . 'authenticated_login_form', $authenticated, $user, $provider );

			if ( true !== $authenticated ) {
				self::delete_login_nonce( $user->ID, $nonce );
				\wp_die( \esc_html__( 'Authentication failed.', 'wp-2fa' ) );
			}

			if ( ! self::consume_login_nonce( $user->ID, $nonce ) ) {
				\wp_die( \esc_html__( 'Authentication transaction has expired or was already used.', 'wp-2fa' ) );
			}
			// Re-derive through rememberme() rather than trusting the submitted flag
			// directly, so a site forcing wp_2fa_rememberme still governs the final cookie.
			self::finish_second_factor( $user, self::rememberme() );

			// Must be global because that's how login_header() uses it.
			global $interim_login;
			$interim_login = ( isset( $_REQUEST['interim-login'] ) ) ? filter_var( $_REQUEST['interim-login'], FILTER_VALIDATE_BOOLEAN ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( $interim_login ) {
				$message       = '<p class="message">' . __( 'You have logged in successfully.', 'wp-2fa' ) . '</p>';
				$interim_login = 'success'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

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
				$redirect_to = apply_filters( 'login_redirect', \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), \esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), $user ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			Backup_Codes::clear_login_attempts( $user );

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || \network_admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( WP_Helper::is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
					$redirect_to = user_admin_url();
				} elseif ( WP_Helper::is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
				}

				$redirect_to = \apply_filters( WP_2FA_PREFIX . 'post_login_orphan_user_redirect', $redirect_to, $user );

				\wp_safe_redirect( $redirect_to );
				exit;
			}

			$redirect_to = \apply_filters( WP_2FA_PREFIX . 'post_login_user_redirect', $redirect_to, $user );

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
					$redirect_to = user_admin_url();
				} elseif ( is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
				}

				\wp_safe_redirect( $redirect_to );
				exit;
			}

			\wp_safe_redirect( $redirect_to );

			exit;
		}

		/**
		 * Complete a sign-in whose second factor has just succeeded.
		 *
		 * The one place every provider's success ends. The caller has already
		 * verified the factor and consumed its own transaction (a login nonce, a
		 * OneTouch request, an emailed link) and decided the remember-me answer;
		 * this clears what a success has to clear, issues the session and
		 * announces it.
		 *
		 * The shared account-level attempt counter was added with the login form
		 * and REST paths clearing it, while OneTouch and the out-of-band link set
		 * the cookie themselves and left it behind: a user who had mistyped a code
		 * and then approved on their phone started their next sign-in with those
		 * failures already counted.
		 *
		 * @param \WP_User $user       - The user who has passed the second factor.
		 * @param bool     $rememberme - Whether the session should be the long one.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function finish_second_factor( \WP_User $user, bool $rememberme ): void {
			Authentication::clear_second_factor_failures( $user );
			Authentication::clear_login_attempts( $user );

			\wp_set_auth_cookie( $user->ID, $rememberme );

			/**
			 * Fires when the user is authenticated.
			 *
			 * @param \WP_User - the logged in user
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'user_authenticated', $user );
		}

		/**
		 * Should the login session persist between sessions.
		 *
		 * @return boolean
		 */
		public static function rememberme() {
			return self::remember_policy( ! empty( $_REQUEST['rememberme'] ) ); //phpcs:ignore
		}

		/**
		 * The session length a sign-in gets: what the user asked for, put through
		 * the site's policy.
		 *
		 * Every path that issues a session has to ask this, whatever form the
		 * user's answer arrived in. Authy OneTouch read its own request value and
		 * went straight to the cookie, so a site forcing short sessions through
		 * wp_2fa_rememberme still handed out fortnight-long ones there.
		 *
		 * @param bool $requested - Whether the user ticked "Remember Me".
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function remember_policy( bool $requested ): bool {
			/**
			 * Changes the remember me value.
			 *
			 * @param bool $rememberme - Current state of the remember me variable.
			 *
			 * @since 2.0.0
			 */
			return (bool) \apply_filters( WP_2FA_PREFIX . 'rememberme', $requested );
		}

		/**
		 * Prints the form that prompts the user to authenticate.
		 *
		 * @since 0.1-dev
		 *
		 * @param \WP_User $user \WP_User object of the logged-in user.
		 * @param bool     $is_reset_protection - That call is for reset code.
		 * @param bool     $send_code           Whether this render may dispatch a code.
		 *
		 * @return void
		 */
		public static function email_authentication_page( $user, $is_reset_protection = false, $send_code = true ) {
			if ( ! $user ) {
				return;
			}

			$use_default     = ( 'use-custom' === WP2FA::get_wp2fa_white_label_setting( 'use_custom_2fa_message' ) ) ? 'custom-text-email-code-page' : 'default-text-code-page';
			$text_to_display = ( $is_reset_protection ) ? 'default-text-pw-reset-code-page' : $use_default;

			if ( $send_code ) {
				Code_Guard::send_once(
					static function () use ( $user, $is_reset_protection ) {
						return Setup_Wizard::send_authentication_setup_email( $user->ID, 'nominated_email_address', $is_reset_protection );
					},
					$user
				);
			}

			require_once ABSPATH . '/wp-admin/includes/template.php';
			?>
			<?php echo WP2FA::get_wp2fa_white_label_setting( $text_to_display, true );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php self::print_dispatch_failure(); ?>
			<p>
			</br>
				<label for="authcode"><?php \esc_html_e( 'Verification Code:', 'wp-2fa' ); ?></label>
				<input type="tel" name="authcode" id="authcode" class="input" value="" size="20" pattern="[0-9]*" autocomplete="off" />
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
			if ( ! isset( $user->ID ) || ! isset( $_REQUEST['authcode'] ) ) {
				return false;
			}
			return Authentication::validate_token( $user, \sanitize_text_field( \wp_unslash( $_REQUEST['authcode'] ) ) );
		}

		/**
		 * Send the email code if missing or requested. Stop the authentication
		 * validation if a new token has been generated and sent.
		 *
		 * @param  \WP_User $user \WP_User object of the logged-in user.
		 * @param bool     $is_reset_protection - That call is for reset code.
		 *
		 * @return boolean
		 *
		 * @since 3.0.0
		 */
		public static function pre_process_email_authentication( $user, $is_reset_protection = false ) {
			if ( isset( $user->ID ) && isset( $_REQUEST[ self::INPUT_NAME_RESEND_CODE ] ) ) { //phpcs:ignore -- nonce
				/*
				 * Resends no longer spend verification attempts, so nothing else
				 * would stop a held-down button, or a script, from filling the
				 * user's inbox. One code per debounce window; the one already sent
				 * is still good.
				 */
				if ( ! Code_Guard::may_resend( $user ) ) {
					self::$resend_withheld = true;

					return true;
				}

				Code_Guard::dispatch(
					static function () use ( $user, $is_reset_protection ) {
						return Setup_Wizard::send_authentication_setup_email( $user->ID, 'nominated_email_address', $is_reset_protection );
					},
					$user
				);

				return true;
			}
			return false;
		}

		/**
		 * The message for a code that could not be sent by email.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function dispatch_failure_message(): string {
			return \esc_html__( 'The verification code could not be sent by email. Please try again with the Resend Code button, or contact the site administrator if this keeps happening.', 'wp-2fa' );
		}

		/**
		 * Tell the user, on the challenge itself, that the code did not go out.
		 *
		 * A resend reports its own result through resend_notice(), so this only
		 * speaks for a code sent while rendering the challenge.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function print_dispatch_failure(): void {
			if ( ! Code_Guard::dispatch_failed() || isset( $_REQUEST[ self::INPUT_NAME_RESEND_CODE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			echo '<p class="wp-2fa-code-dispatch-failed" role="alert"><strong>' . self::dispatch_failure_message() . '</strong></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			\wp_enqueue_script( 'wp_2fa_user_login_scripts' );
			?>
			<p><?php echo WP2FA::get_wp2fa_white_label_setting( 'default-backup-code-page', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><br/>
			<p>
				<label for="authcode"><?php \esc_html_e( 'Verification Code:', 'wp-2fa' ); ?></label>
				<?php
				/*
				 * The pattern has to admit letters and spaces, not just digits.
				 *
				 * Codes generated here are numeric, but codes carried over from another plugin
				 * need not be: Wordfence Login Security issued hexadecimal ones, printed in
				 * spaced groups of four. With a digits-only pattern the browser refuses to
				 * submit the form at all, silently — the user gets no error and no way past it,
				 * and their recovery code is unusable however correct it is.
				 */
				?>
				<input type="tel" name="wp-2fa-backup-code" id="authcode" class="input" value="" size="20" pattern="[0-9A-Fa-f \t]*" autocomplete="off" />
				<script>
					const backup_code = document.getElementById('authcode');
					backup_code.addEventListener('input', function() {
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
			\wp_dequeue_style( 'wpaas-sso-login' );
		}
	}
}
