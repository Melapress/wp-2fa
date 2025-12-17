<?php
/**
 * Responsible for the register API endpoints
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2025 Melapress
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

			\add_action(
				'wp_login',
				function( $user_login, $user ) {
					self::clear_login_attempts( $user );
				},
				20,
				2
			);
		}

		/**
		 * Returns result by ID or GET parameters
		 *
		 * @param \WP_REST_Request $request The request object.
		 *
		 * @return \WP_REST_Response|\WP_Error
		 *
		 * @since 3.0.0
		 */
		public static function validate_provider( \WP_REST_Request $request ) {

			$request_parameters = $request->get_params();

			if ( ! isset( $request_parameters['user_id'] ) ) {
				return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 400 ) );
			}

			$user_id = (int) $request->get_param( 'user_id' );
			$user    = \get_user_by( 'id', $user_id );

			if ( ! $user ) {
				return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 400 ) );
			}

			// Simple method enforcement; ensure only POST requests are honored.
			if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 405 ) );
			}

			if ( ! self::check_number_of_attempts( $user ) ) {
				return \rest_ensure_response(
					array(
						'status'      => false,
						'message'     => __( 'Too many attempts. Please try again later.', 'wp-2fa' ),
						'redirect_to' => \esc_url_raw( \wp_login_url() ),
					)
				);
			}

			self::increase_login_attempts( $user );

			$proceed = false;

			if ( 0 !== \wp_get_current_user()->ID ) {
				if ( \wp_get_current_user()->ID === $user_id ) {
					// Optional action nonce for additional CSRF mitigation when already logged in.
					$action_nonce = isset( $request_parameters['action_nonce'] ) ? $request_parameters['action_nonce'] : '';
					if ( empty( $action_nonce ) || ! \wp_verify_nonce( $action_nonce, 'wp2fa_login_api_' . $user_id ) ) {
						return new \WP_Error( 'invalid_request', 'Authentication failed.', array( 'status' => 400 ) );
					}
					$proceed = true;

					// Requested 2fa login is for the currently logged-in user. Destroy the session and proceed.

					// Invalidate the current login session to prevent from being re-used.
					Login::destroy_current_session_for_user( \wp_get_current_user() );

					// Also clear the cookies which are no longer valid.
					\wp_clear_auth_cookie();
				}
			}

			if ( ! $proceed ) {
				// The user is not logged in - lets check for our nonce existence and expiration.

				$proceed = true;

				$login_nonce = \get_user_meta( $user_id, Login::USER_META_NONCE_KEY, true );
				if ( ! $login_nonce || ! \is_array( $login_nonce ) || empty( $login_nonce ) || ! \key_exists( 'expiration', $login_nonce ) ) {
					$proceed = false;
				}

				if ( $proceed && time() > $login_nonce['expiration'] ) {
					Login::delete_login_nonce( $user_id );
					$proceed = false;
				}
			}

			if ( ! $proceed ) {
				return new \WP_Error( 'invalid_request', 'Unauthorized user 2FA attempt', array( 'status' => 400 ) );
			}

			try {
				$provider = User_Helper::get_enabled_method_for_user( $user_id );

				if ( empty( $provider ) ) {
					return new \WP_Error( 'invalid_request', 'No 2FA method enabled for this user', array( 'status' => 400 ) );
				}

				$raw_token       = $request_parameters['token'] ?? '';
				$token           = is_string( $raw_token ) ? trim( $raw_token ) : '';
				$remember_device = \sanitize_text_field( $request_parameters['remember_device'] ?? '' );

				// Basic provider-aware token format validation (non-breaking fallback).
				if ( strlen( $token ) > 128 ) { // Generic length guard.
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
					$valid['valid'] = false;
				}

				if ( $valid['valid'] ) {

					\wp_set_current_user( $user_id, $user->user_login );
					\wp_set_auth_cookie( $user->ID );

					/**
					 * Fires when the user is authenticated.
					 *
					 * @param \WP_User - the logged in user
					 *
					 * @since 2.0.0
					 */
					\do_action( WP_2FA_PREFIX . 'user_authenticated', $user );

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

					// Consume nonce on successful authentication to prevent replay.
					Login::delete_login_nonce( $user_id );

					if ( WP_Helper::is_multisite() && ! \get_active_blog_for_user( $user->ID ) && ! \is_super_admin( $user->ID ) ) {
						$redirect_to = \user_admin_url();
					} elseif ( WP_Helper::is_multisite() && ! $user->has_cap( 'read' ) ) {
						$redirect_to = \get_dashboard_url( $user->ID );
					} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
						$redirect_to = $user->has_cap( 'read' ) ? \network_admin_url( 'profile.php' ) : \home_url();
					} else {
						$redirect_to = \apply_filters( 'login_redirect', $redirect_to, $redirect_to, $user );
					}

					$redirect_to = \apply_filters( WP_2FA_PREFIX . 'post_login_user_redirect', $redirect_to, $user );

					/**
					 * Filters the login redirect URL.
					 *
					 * @since 3.0.0
					 *
					 * @param string           $redirect_to           The redirect destination URL.
					 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
					 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
					 */
					$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );

					if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || \admin_url() === $redirect_to ) ) {
						// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
						if ( is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
							$redirect_to = user_admin_url();
						} elseif ( is_multisite() && ! $user->has_cap( 'read' ) ) {
							$redirect_to = get_dashboard_url( $user->ID );
						} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
							$redirect_to = $user->has_cap( 'read' ) ? \admin_url( 'profile.php' ) : \home_url();
						}
					}

					self::clear_login_attempts( $user );

				} else {
					$provider = \sanitize_text_field( $request_parameters['provider'] ?? '' );
					if ( $provider && isset( $valid[ $provider ] ) && isset( $valid[ $provider ]['error'] ) ) {
						$message = \esc_html( $valid[ $provider ]['error'] );
					} else {
						$message = \esc_html__( 'Provided details are wrong.', 'wp-2fa' );
					}
				}
			} catch ( \Exception $error ) {
				return new \WP_Error( 'invalid_request', 'Invalid request: ' . esc_html( $error->getMessage() ), array( 'status' => 400 ) );
			}

			return \rest_ensure_response(
				array(
					'status'      => $valid['valid'],
					'message'     => $message,
					'redirect_to' => \esc_url_raw( $redirect_to ),
				)
			);
		}
	}
}
