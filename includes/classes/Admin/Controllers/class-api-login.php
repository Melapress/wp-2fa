<?php
/**
 * Responsible for the register API endpoints
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Controllers\API;

use WP2FA\Authenticator\Login;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Methods\Traits\Login_Attempts;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Endpoints registering
 */
if ( ! class_exists( '\WP2FA\Admin\Controllers\API\API_Login' ) ) {

	/**
	 * Login API controller
	 *
	 * @since 3.0.0
	 */
	class API_Login {

		use Login_Attempts;

		/**
		 * Holds the name of the meta key for the allowed login attempts.
		 *
		 * @var string
		 *
		 * @since 3.0.0
		 */
		private static $logging_attempts_meta_key = WP_2FA_PREFIX . 'api-login-attempts';

		/**
		 * Inits the class and hooks.
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function init() {
			// A password login must not reset second-factor failure state.
		}

		/**
		 * A fresh transaction for another attempt, or '' when there must not be one.
		 *
		 * A failed attempt spends its transaction, so a captured nonce is good for
		 * one guess and no more. The form path then renders a new challenge with a
		 * new nonce; this path returned nothing, and the page kept submitting the
		 * spent one - every later attempt, the right code included, was refused as
		 * an expired transaction and the user could not sign in at all without
		 * starting over from the password.
		 *
		 * Once the attempts are used up there is nothing to retry with: the caller
		 * sends the user back to the login screen instead.
		 *
		 * @param \WP_User $user The user who just failed an attempt.
		 *
		 * @return string The new nonce, or '' when locked or none could be made.
		 *
		 * @since 4.2.0
		 */
		private static function retry_after_failure( \WP_User $user ): string {
			if ( self::second_factor_locked( $user ) ) {
				return '';
			}

			$fresh = Login::create_login_nonce( $user->ID );

			return ( \is_array( $fresh ) && ! empty( $fresh['key'] ) ) ? (string) $fresh['key'] : '';
		}

		private static function reject_second_factor( \WP_User $user, string $login_nonce ): void {
			Login::consume_login_nonce( $user->ID, $login_nonce );
			\do_action( 'wp_login_failed', $user->user_login, new \WP_Error( 'authentication_failed', __( 'Invalid verification code.', 'wp-2fa' ) ) );
		}

		/**
		 * Validates the 2FA provider token via POST request.
		 *
		 * @param \WP_REST_Request $request The request object.
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @since 3.0.0
		 */
		public static function validate_provider( \WP_REST_Request $request ) {

			$request_parameters = $request->get_params();

			if ( ! isset( $request_parameters['user_id'] ) || ! isset( $request_parameters['token'] ) || ! isset( $request_parameters['provider'] ) || ! isset( $request_parameters['login_nonce'] ) ) {
				return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 400 ) );
			}

			$user_id = (int) $request->get_param( 'user_id' );
			$user    = \get_user_by( 'id', $user_id );

			if ( ! $user ) {
				return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 400 ) );
			}

			// Verify the login nonce from user meta - this is our primary auth mechanism.
			$login_nonce = \sanitize_text_field( $request_parameters['login_nonce'] );
			if ( true !== Login::verify_login_nonce( $user_id, $login_nonce ) ) {
				return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 403 ) );
			}

			if ( self::second_factor_locked( $user ) || ! self::reserve_second_factor_attempt( $user ) ) {
				Login::consume_login_nonce( $user_id, $login_nonce );
				return \rest_ensure_response(
					array(
						'status'      => false,
						'message'     => \esc_html( self::second_factor_lock_message( $user ) ),
						'redirect_to' => \esc_url_raw( \wp_login_url() ),
					)
				);
			}

			// If the user is still logged in (normal login → 2FA flow), destroy the session.
			if ( 0 !== \wp_get_current_user()->ID && \wp_get_current_user()->ID === $user_id ) {
				Login::destroy_current_session_for_user( \wp_get_current_user() );
				\wp_clear_auth_cookie();
			}

			// Set once the session is issued, so a throw after that point - in a
			// third-party hook, say - is not held against a user who got in.
			$signed_in = false;

			// The replacement transaction handed back after a failed attempt.
			$retry_nonce = '';

			// A successful session-expired login inside the editor: close, do not navigate.
			$interim = false;

			try {
				$provider = User_Helper::get_enabled_method_for_user( $user_id );

				if ( empty( $provider ) ) {
					return new \WP_Error( 'invalid_request', 'No 2FA method enabled for this user', array( 'status' => 400 ) );
				}

				$raw_token       = $request_parameters['token'] ?? '';
				$token           = is_string( $raw_token ) ? trim( $raw_token ) : '';
				$remember_device = \sanitize_text_field( $request_parameters['remember_device'] ?? '' );

				/*
				 * Core's "Remember Me", which decides how long the session lasts — a separate
				 * thing from remember_device above, which decides whether this challenge is
				 * asked for again on this device.
				 *
				 * The sign-in completes here rather than at wp-login.php, so the box the user
				 * ticked there reaches this point or not at all. It used not to, and every
				 * session issued through this endpoint got the short lifetime no matter what
				 * the user chose, with nothing anywhere to say why.
				 *
				 * Put through the same filter the form-based path uses, so anything overriding
				 * the choice keeps working whichever way the challenge was answered.
				 */
				$rememberme = Login::remember_policy( \rest_sanitize_boolean( $request_parameters['rememberme'] ?? false ) );

				// Basic provider-aware token format validation (non-breaking fallback).
				if ( strlen( $token ) > 128 ) { // Generic length guard.
					self::reject_second_factor( $user, $login_nonce );
					return \rest_ensure_response(
						array(
							'status'      => false,
							'message'     => __( 'Authentication failed.', 'wp-2fa' ),
							'redirect_to' => \esc_url_raw( \wp_login_url() ),
						)
					);
				}

				$valid = array( 'valid' => false );

				$valid = \apply_filters( WP_2FA_PREFIX . 'validate_login_api', $valid, $user_id, $token, $provider );

				$redirect_to = '';

				if ( ! is_array( $valid ) || ! isset( $valid['valid'] ) ) {
					$valid = array( 'valid' => false );
				}

				if ( $valid['valid'] ) {
					if ( ! Login::consume_login_nonce( $user_id, $login_nonce ) ) {
						return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 403 ) );
					}
					\wp_set_current_user( $user_id, $user->user_login );
					Login::finish_second_factor( $user, (bool) $rememberme );
					$signed_in = true;

					if ( isset( $remember_device ) && ! empty( $remember_device ) ) {
						/**
						 * Fires when the user is authenticated.
						 *
						 * @param \WP_User - the logged in user
						 *
						 * @since 3.0.0
						 */
						\do_action( WP_2FA_PREFIX . 'remember_device', $user, $remember_device );
					}

					$message = \esc_html__( 'Successfully signed in with WP 2FA.', 'wp-2fa' );

					/*
					 * Where to go now, decided as the form-based challenge decides it
					 * (Login::login_form_validate_2fa()): the destination the login was
					 * asked for, through the same filters, with a default only when
					 * none was given. This used to start from the default - the profile,
					 * for anyone without edit_posts - so a subscriber heading for a
					 * members page landed on their profile instead, and the script took
					 * that over the destination the page knew. Nor did it know about the
					 * session-expired login inside the editor, which has to close, not
					 * navigate.
					 */
					if ( ! empty( $request_parameters['interim_login'] ) && \rest_sanitize_boolean( $request_parameters['interim_login'] ) ) {
						$interim = true;
					} else {
						$requested   = isset( $request_parameters['redirect_to'] ) && \is_string( $request_parameters['redirect_to'] ) ? \esc_url_raw( $request_parameters['redirect_to'] ) : '';
						$redirect_to = \apply_filters( 'login_redirect', $requested, $requested, $user );
						$redirect_to = \apply_filters( WP_2FA_PREFIX . 'post_login_user_redirect', $redirect_to, $user );

						if ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || \admin_url() === $redirect_to ) {
							// The user's natural landing place, as the form gives it.
							if ( WP_Helper::is_multisite() && ! \get_active_blog_for_user( $user->ID ) && ! \is_super_admin( $user->ID ) ) {
								$redirect_to = \user_admin_url();
							} elseif ( WP_Helper::is_multisite() && ! $user->has_cap( 'read' ) ) {
								$redirect_to = \get_dashboard_url( $user->ID );
							} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
								$redirect_to = $user->has_cap( 'read' ) ? \admin_url( 'profile.php' ) : \home_url();
							} elseif ( empty( $redirect_to ) ) {
								$redirect_to = \admin_url();
							}
						}
					}

					self::clear_login_attempts( $user );

				} else {
					self::reject_second_factor( $user, $login_nonce );
					$provider = \sanitize_text_field( $request_parameters['provider'] ?? '' );
					if ( $provider && isset( $valid[ $provider ] ) && isset( $valid[ $provider ]['error'] ) ) {
						$message = \esc_html( $valid[ $provider ]['error'] );
					} else {
						$message = \esc_html__( 'Provided details are wrong.', 'wp-2fa' );
					}

					/*
					 * The same rhythm as the login form: after a few wrong codes in one
					 * sign-in, back to the login page. The REST path kept the user on
					 * the challenge until the account-wide limit locked them out, so a
					 * handful of typos ended in a fifteen-minute lock with no warning.
					 */
					self::increase_login_attempts( $user );
					$session_spent = ! self::check_number_of_attempts( $user );

					$retry = ( $session_spent ? '' : self::retry_after_failure( $user ) );
					if ( self::second_factor_locked( $user ) ) {
						$message     = \esc_html( self::second_factor_lock_message( $user ) );
						$redirect_to = \wp_login_url();
					} elseif ( $session_spent ) {
						$message     = \esc_html__( 'Too many failed attempts. Please log in again.', 'wp-2fa' );
						$redirect_to = \wp_login_url();
					}
					if ( $session_spent ) {
						self::clear_login_attempts( $user );
					}
					$retry_nonce = $retry;
				}
			} catch ( \Throwable $error ) {
				/*
				 * This is a public route. The exception text used to go back in the
				 * response, and whatever a provider or an extension put in it -
				 * paths, configuration, service errors - went with it. It is logged
				 * for the site owner when debugging is on, and the caller gets the
				 * same answer as any other failure. \Throwable, not \Exception, or a
				 * type error became a 500 instead.
				 */
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side diagnostics only.
					\error_log( sprintf( '[WP-2FA] login validation error: %s', $error->getMessage() ) );
				}

				$error_data = array( 'status' => 400 );

				if ( ! $signed_in ) {
					self::reject_second_factor( $user, $login_nonce );

					$retry = self::retry_after_failure( $user );
					if ( '' !== $retry ) {
						$error_data['login_nonce'] = $retry;
					}
				}

				return new \WP_Error( 'invalid_request', \esc_html__( 'Authentication failed.', 'wp-2fa' ), $error_data );
			}

			$response = array(
				'status'      => $valid['valid'],
				'message'     => $message,
				// The script navigates straight to this, so it gets the same
				// allowed-hosts check core applies to login_redirect's output.
				'redirect_to' => \esc_url_raw( Login::safe_redirect_target( $redirect_to ) ),
			);

			if ( '' !== $retry_nonce ) {
				$response['login_nonce'] = $retry_nonce;
			}

			if ( $interim ) {
				$response['interim_login'] = true;
			}

			return \rest_ensure_response( $response );
		}
	}
}
