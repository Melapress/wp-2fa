<?php
/**
 * Responsible for the AJAX calls.
 *
 * @package    wp2fa
 * @subpackage helpers
 *
 * @since      2.6.0
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

use WP2FA\WP2FA;
use WP2FA\Utils\User_Utils;
use WP2FA\Admin\Settings_Page;
use WP2FA\Admin\User_Profile;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Helpers\Email_Templates;
use WP2FA\Admin\SettingsPages\Settings_Page_Email;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP2FA\Admin\Helpers\Ajax_Helper' ) ) {
	/**
	 * Responsible for the proper AJAX calls and responses.
	 */
	class Ajax_Helper {

		/**
		 * Cache TTL for search results (in seconds).
		 */
		private const CACHE_TTL = 300; // 5 minutes

		/**
		 * Rate limit attempts per minute.
		 */
		private const RATE_LIMIT_ATTEMPTS = 5;

		/**
		 * Verifies nonce and user permissions for AJAX requests.
		 *
		 * @param string $nonce_action The nonce action to verify.
		 * @param string $capability   The capability to check (default: manage_options).
		 *
		 * @return void
		 *
		 * @since 3.1.1
		 */
		private static function verify_request( string $nonce_action, string $capability = 'manage_options' ): void {
			if ( ! \current_user_can( $capability ) ) {
				\wp_send_json_error( \esc_html__( 'Access Denied.', 'wp-2fa' ) );
			}

			$nonce = isset( $_REQUEST['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['wp_2fa_nonce'] ) ) : null;
			if ( null === $nonce || false === $nonce || ! \wp_verify_nonce( $nonce, $nonce_action ) ) {
				\wp_send_json_error( \esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}
		}

		/**
		 * Checks rate limiting for sensitive actions.
		 *
		 * @param string $action_key Unique key for the action.
		 *
		 * @return bool True if allowed, false if rate limited.
		 *
		 * @since 3.1.1
		 */
		public static function check_rate_limit( string $action_key ): bool {
			$user_id   = \get_current_user_id();
			$cache_key = 'wp2fa_rate_limit_' . $action_key . '_' . $user_id;
			$attempts  = (int) \get_transient( $cache_key );

			if ( $attempts >= self::RATE_LIMIT_ATTEMPTS ) {
				return false;
			}

			\set_transient( $cache_key, $attempts + 1, 60 ); // 1 minute TTL
			return true;
		}

		/**
		 * Get all users in AJAX matter and returns them
		 *
		 * @since 2.6.0
		 */
		public static function get_all_users() {
			self::verify_request( 'wp-2fa-settings-nonce' );

			$term = isset( $_GET['term'] ) ? \sanitize_text_field( \wp_unslash( $_GET['term'] ) ) : null;

			if ( null === $term || false === $term ) {
				\wp_send_json_error( \esc_html__( 'Invalid term.', 'wp-2fa' ) );
			}

			$cache_key = 'wp2fa_users_search_' . md5( $term );
			$users     = \get_transient( $cache_key );

			if ( false === $users ) {
				$users_args = array(
					'fields'         => array( 'ID', 'user_login' ),
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'user_login' ),
					'number'         => 20,
					'orderby'        => 'user_login',
					'order'          => 'ASC',
				);
				if ( WP_Helper::is_multisite() ) {
					$users_args['blog_id'] = 0;
				}
				$users_data = \get_users( $users_args );

				// Create final array which we will fill in below.
				$users = array();

				foreach ( $users_data as $user ) {
					$users[] = array(
						'value' => $user->user_login,
						'label' => $user->user_login,
					);
				}

				\set_transient( $cache_key, $users, self::CACHE_TTL );
			}

			\wp_send_json_success( $users );
		}

		/**
		 * Get all network sites in AJAX way
		 *
		 * @since 2.6.0
		 */
		public static function get_all_network_sites() {
			self::verify_request( 'wp-2fa-settings-nonce' );

			$term = isset( $_GET['term'] ) ? \sanitize_text_field( \wp_unslash( $_GET['term'] ) ) : null;

			if ( null === $term || false === $term ) {
				\wp_send_json_error( \esc_html__( 'Invalid term.', 'wp-2fa' ) );
			}

			$cache_key   = 'wp2fa_sites_search_' . md5( $term );
			$sites_found = \get_transient( $cache_key );

			if ( false === $sites_found ) {
				$sites_found = array();

				foreach ( WP_Helper::get_multi_sites() as $site ) {
					if ( false !== stripos( $site->blogname, $term ) ) {
						$sites_found[] = array(
							'label' => $site->blog_id,
							'value' => $site->blogname,
						);
					}
				}

				\set_transient( $cache_key, $sites_found, self::CACHE_TTL );
			}

			\wp_send_json_success( $sites_found );
		}

		/**
		 * Unlock users accounts if they have overrun grace period it is also used in AJAX calls
		 *
		 * @param  int $user_id User ID.
		 *
		 * @since 2.6.0
		 */
		public static function unlock_account( $user_id ) {
			if ( ! self::check_rate_limit( 'unlock_account' ) ) {
				\wp_send_json_error( \esc_html__( 'Rate limit exceeded. Please try again later.', 'wp-2fa' ) );
			}

			self::verify_request( 'wp-2fa-unlock-account-nonce' );

			$user_id = isset( $_GET['user_id'] ) ? \intval( \sanitize_text_field( \wp_unslash( $_GET['user_id'] ) ) ) : null;

			if ( isset( $user_id ) && ! User_Helper::current_user_can_manage_2fa_for( $user_id ) ) {
				\wp_send_json_error( \esc_html__( 'Access Denied.', 'wp-2fa' ) );
			}

			$target = isset( $user_id ) ? \get_userdata( $user_id ) : false;

			/*
			 * Locked out by wrong 2FA codes, not by an expired grace period: clear
			 * that lock and nothing else. The grace-period reset below has no place
			 * here - this user has 2FA and is only waiting out the attempt limit.
			 */
			if ( $target instanceof \WP_User && ! User_Helper::get_grace_period( $target ) && ! User_Helper::is_user_locked( $target ) ) {
				\WP2FA\Authenticator\Authentication::clear_second_factor_failures( $target );
				\add_action( 'admin_notices', array( __CLASS__, 'user_unlocked_notice' ) );

				return;
			}

			if ( isset( $user_id ) ) {
				// An expired grace period, and whatever attempt lock came with it.
				if ( $target instanceof \WP_User ) {
					\WP2FA\Authenticator\Authentication::clear_second_factor_failures( $target );
				}

				$grace_period             = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user_id ), 'grace-period' );
				$grace_period_denominator = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user_id ), 'grace-period-denominator' );
				$create_a_string          = $grace_period . ' ' . $grace_period_denominator;
				// Turn that string into a time.
				$grace_expiry = strtotime( $create_a_string );

				User_Helper::remove_meta( WP_2FA_PREFIX . 'locked_account_notification', $user_id );
				User_Helper::remove_grace_period( $user_id );
				User_Helper::remove_meta( User_Helper::USER_LOCKED_STATUS, $user_id );

				User_Helper::set_user_expiry_date( (string) $grace_expiry, $user_id );

				// Recalculate the 2FA status based on current plugin settings.
				// get_userdata(): the user asked about, and nothing else. This used
				// to go through a User_Helper::get_user() that ignored its argument
				// and recalculated whoever the helper last held.
				$user_obj = \get_userdata( $user_id );
				if ( $user_obj instanceof \WP_User ) {
					User_Helper::set_user_status( $user_obj );
				}

				Settings_Page::send_account_unlocked_email( $user_id );

				/*
				* Fires after the user is unlocked.
				*
				* @param \WP_User $user - The user for which the method has been set.
				*
				* @since 2.6.0
				*/
				\do_action( WP_2FA_PREFIX . 'user_is_unlocked', $user_obj );

				\add_action( 'admin_notices', array( __CLASS__, 'user_unlocked_notice' ) );
			}
		}

		/**
		 * Sets the salt key into the wp-config.php file via AJAX request.
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function set_salt_key() {
			if ( \wp_doing_ajax() ) {
				if ( isset( $_REQUEST['_wpnonce'] ) ) {
					$nonce_check = \wp_verify_nonce( \sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wp-2fa-set-salt-nonce' );
					if ( ! $nonce_check ) {
						\wp_send_json_error( new \WP_Error( 500, esc_html__( 'Nonce checking failed', 'wp-2fa' ) ), 400 );
					} elseif ( \current_user_can( 'manage_options' ) ) {
						if ( ! File_Writer::can_write_to_file( File_Writer::get_wp_config_file_path() ) ) {
							\wp_send_json_error(
								new \WP_Error(
									500,
									\esc_html__( 'Unable to write to wp-config.php', 'wp-2fa' )
								),
								400
							);
						} else {
							$secret_key = Settings_Utils::get_option( 'secret_key' );
							if ( ! empty( $secret_key ) ) {
								/*
								 * The database copy is the only other copy of the key.
								 * It goes only once the key is confirmed on disk; this
								 * used to delete it regardless, and a failed write then
								 * left every TOTP seed on the site undecryptable.
								 */
								if ( true !== File_Writer::save_secret_key( $secret_key ) ) {
									\wp_send_json_error(
										new \WP_Error(
											500,
											\esc_html__( 'Unable to write to wp-config.php', 'wp-2fa' )
										),
										400
									);
								}
								Settings_Utils::delete_option( 'secret_key' );
								\wp_send_json_success(
									\esc_html__(
										'wp-config.php successfully update, global setting deleted',
										'wp-2fa'
									)
								);
							} else {
								\wp_send_json_error(
									new \WP_Error(
										500,
										\esc_html__( 'Unable to find global secret key', 'wp-2fa' )
									),
									400
								);
							}
						}
					}
				}
			}
		}

		/**
		 * Removes the salt key unable to store in the wp-config file notification.
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function unset_salt_key(): void {
			if ( \wp_doing_ajax() ) {
				if ( isset( $_REQUEST['_wpnonce'] ) ) {
					$nonce_check = \wp_verify_nonce( \sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wp-2fa-unset-salt-nonce' );
					if ( ! $nonce_check ) {
						\wp_send_json_error( new \WP_Error( 500, esc_html__( 'Nonce checking failed', 'wp-2fa' ) ), 400 );
					} elseif ( \current_user_can( 'manage_options' ) ) {

						Settings_Utils::update_option( 'remove_store_salt_in_wp_config_message', true );

						\wp_send_json_success(
							\esc_html__(
								'message is set for removal',
								'wp-2fa'
							)
						);

					}
				}
			}
		}

		/**
		 * Remove user 2fa config
		 *
		 * @param  int $user_id User ID.
		 *
		 * @since 2.6.0
		 */
		public static function remove_user_2fa( $user_id ) {
			if ( ! self::check_rate_limit( 'remove_user_2fa' ) ) {
				\wp_send_json_error( \esc_html__( 'Rate limit exceeded. Please try again later.', 'wp-2fa' ) );
			}

			$current_user_id = (int) \get_current_user_id();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
			$request_user_id = isset( $_POST['user_id'] ) ? \intval( \sanitize_text_field( \wp_unslash( $_POST['user_id'] ) ) ) : ( isset( $_GET['user_id'] ) ? \intval( \sanitize_text_field( \wp_unslash( $_GET['user_id'] ) ) ) : null );

			if ( null === $request_user_id || $request_user_id <= 0 ) {
				\wp_send_json_error( 'Access Denied.' );
			}

			if ( $request_user_id === $current_user_id ) {
				/*
				 * Acting on yourself. can_user_remove_2fa() used to decide only
				 * whether the Remove button was drawn; the request behind it was
				 * accepted regardless, so a policy that forbids removal was one
				 * hand-made POST away from not applying.
				 *
				 * The policy binds everyone who could not change it anyway. That is
				 * not every holder of manage_options: on a network a site admin has
				 * it and the policy is the network's, and with access limited only
				 * the settings owner may change it.
				 */
				if ( ! Settings_Page::can_manage_settings() && ! User_Profile::can_user_remove_2fa( $current_user_id ) ) {
					\wp_send_json_error( 'Access Denied.' );
				}
			} elseif ( ! User_Helper::current_user_can_manage_2fa_for( $request_user_id ) ) {
				// Acting on someone else: never past what this admin may manage.
				\wp_send_json_error( 'Access Denied.' );
			}

			// Verify nonce.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
			$nonce = isset( $_POST['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['wp_2fa_nonce'] ) ) : ( isset( $_GET['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_nonce'] ) ) : null );
			if ( null === $nonce || false === $nonce || ! \wp_verify_nonce( $nonce, 'wp-2fa-remove-user-2fa-nonce' ) ) {
				\wp_send_json_error( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
			$user_id = isset( $_POST['user_id'] ) ? \intval( \sanitize_text_field( \wp_unslash( $_POST['user_id'] ) ) ) : ( isset( $_GET['user_id'] ) ? \intval( \sanitize_text_field( \wp_unslash( $_GET['user_id'] ) ) ) : null );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
			$admin_reset = isset( $_POST['admin_reset'] ) ? (bool) \intval( \sanitize_text_field( \wp_unslash( $_POST['admin_reset'] ) ) ) : ( isset( $_GET['admin_reset'] ) ? (bool) \intval( \sanitize_text_field( \wp_unslash( $_GET['admin_reset'] ) ) ) : false );

			if ( isset( $user_id ) ) {

				User_Helper::remove_2fa_for_user( $user_id );

				if ( \wp_doing_ajax() ) {
					\wp_send_json_success();
				}

				if ( $admin_reset ) {
					\add_action( 'admin_notices', array( __CLASS__, 'admin_deleted_2fa_notice' ) );
				} else {
					\add_action( 'admin_notices', array( __CLASS__, 'user_deleted_2fa_notice' ) );
				}
			}
		}

		/**
		 * Returns the user roles in AJAX matter.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function get_ajax_user_roles() {
			if ( \wp_doing_ajax() ) {
				self::verify_request( 'wp-2fa-settings-nonce' );

				$roles = array();

				$term = isset( $_GET['term'] ) ? \sanitize_text_field( \wp_unslash( $_GET['term'] ) ) : null;
				if ( null === $term || false === $term ) {
					\wp_send_json_error( esc_html__( 'Invalid term.', 'wp-2fa' ) );
				}

				$cache_key = 'wp2fa_roles_search_' . md5( $term );
				$roles     = \get_transient( $cache_key );

				if ( false === $roles ) {
					$roles = array();

					foreach ( WP_Helper::get_roles_wp() as $role => $human_readable ) {
						if ( stripos( $human_readable, $term ) !== false ) {
							$roles[] = array(
								'label' => \sanitize_text_field( $role ),
								'value' => \sanitize_text_field( $human_readable ),
							);
						}
					}

					\set_transient( $cache_key, $roles, self::CACHE_TTL );
				}

				\wp_send_json_success( $roles );
			}
		}

		/**
		 * Handles AJAX calls for sending test emails.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function handle_send_test_email_ajax() {
			// Check user permissions.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error( \esc_html__( 'Access Denied.', 'wp-2fa' ) );
			}

			// Check email ID.
			$email_id = isset( $_POST['email_id'] ) ? \sanitize_text_field( \wp_unslash( $_POST['email_id'] ) ) : null;
			if ( null === $email_id || false === $email_id ) {
				\wp_send_json_error( \esc_html__( 'Invalid email ID.', 'wp-2fa' ) );
			}

			// Verify nonce.
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : null;
			if ( null === $nonce || false === $nonce || ! wp_verify_nonce( $nonce, 'wp-2fa-email-test-' . $email_id ) ) {
				wp_send_json_error( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			$user_id = \get_current_user_id();
			$user    = \get_userdata( $user_id );
			$email   = $user->user_email;

			if ( 'config_test' === $email_id ) {
				$email_sent = Settings_Page::send_email(
					$email,
					\esc_html__( 'Test email from WP 2FA', 'wp-2fa' ),
					\esc_html__( 'This email was sent by the WP 2FA plugin to test the email delivery.', 'wp-2fa' )
				);
				if ( $email_sent ) {
					\wp_send_json_success(
						sprintf(
							/* translators: %s: the recipient email address. */
							esc_html__( 'Test email was successfully sent to %s', 'wp-2fa' ),
							'<strong>' . esc_html( $email ) . '</strong>'
						)
					);
				}

				\wp_send_json_error( \wp_sprintf( /* translators: %s: the link to the email deliverability guide, already wrapped in an anchor. */ \esc_html__( 'Failed to send the test email. This is usually caused by an SMTP issue, a restricted "from" address, or your host blocking outgoing mail. Check your email settings or contact your hosting provider. %s.', 'wp-2fa' ), \wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/troubleshoot-2fa-email-delivery/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_troubleshoot_2fa_email_delivery&utm_content=test_email_error', \esc_html__( 'Read more about email deliverability', 'wp-2fa' ) ) ) );
			}

			$email_templates = Settings_Page_Email::get_email_notification_definitions();
			foreach ( $email_templates as $email_template ) {
				if ( $email_id === $email_template->get_email_content_id() ) {
					$subject = wp_strip_all_tags( Email_Templates::replace_email_strings( Email_Templates::get_wp2fa_email_templates( $email_id . '_email_subject' ) ) );
					$message = \wpautop( Email_Templates::replace_email_strings( Email_Templates::get_wp2fa_email_templates( $email_id . '_email_body' ), $user_id ) );

					$email_sent = Settings_Page::send_email( $email, $subject, $message );
					if ( $email_sent ) {
						\wp_send_json_success(
							sprintf(
							/* translators: %1$s: the email template name; %2$s: the recipient email address. */
								\esc_html__( 'Test email %1$s was successfully sent to %2$s', 'wp-2fa' ),
								'<strong>' . \esc_html( $email_template->get_title() ) . '</strong>',
								'<strong>' . \esc_html( $email ) . '</strong>'
							)
						);
					}

					\wp_send_json_error( \wp_sprintf( /* translators: %s: the link to the email deliverability guide, already wrapped in an anchor. */ \esc_html__( 'Failed to send the test email. This is usually caused by an SMTP issue, a restricted "from" address, or your host blocking outgoing mail. Check your email settings or contact your hosting provider. %s.', 'wp-2fa' ), \wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/troubleshoot-2fa-email-delivery/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_troubleshoot_2fa_email_delivery&utm_content=test_email_error', \esc_html__( 'Read more about email deliverability', 'wp-2fa' ) ) ) );
				}
			}
		}

		/**
		 * User deleted 2FA settings notification
		 *
		 * @since 2.6.0
		 */
		public static function user_deleted_2fa_notice() {
			?>
			<div class="notice notice-success is-dismissible wp-2fa-admin-notice">
				<p><?php \esc_html_e( '2FA settings have been removed.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
			<?php
		}

		/**
		 * Admin deleted user 2FA settings notification
		 *
		 * @since 2.6.0
		 */
		public static function admin_deleted_2fa_notice() {
			?>
			<div class="notice notice-success is-dismissible wp-2fa-admin-notice">
				<p><?php \esc_html_e( 'User 2FA settings have been removed.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
			<?php
		}

		/**
		 * User unlocked notice.
		 *
		 * @since 2.6.0
		 */
		public static function user_unlocked_notice() {
			?>
			<div class="notice notice-success is-dismissible wp-2fa-admin-notice">
				<p><?php \esc_html_e( 'User account successfully unlocked. User can login again.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
			<?php
		}

		/**
		 * Logs out the current user via AJAX request.
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		public static function logout_account() {
			if ( ! self::check_rate_limit( 'logout_account' ) ) {
				\wp_send_json_error( \esc_html__( 'Rate limit exceeded. Please try again later.', 'wp-2fa' ) );
			}

			if ( ! empty( $_REQUEST['_wpnonce'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wp-2fa-logout' ) ) {
				$user_id = \get_current_user_id();

				\wp_destroy_current_session();
				\wp_clear_auth_cookie();
				\wp_set_current_user( 0 );

				/**
				 * Fires after a user is logged out.
				 *
				 * @since 1.5.0
				 * @since 5.5.0 Added the `$user_id` parameter.
				 *
				 * @param int $user_id ID of the user that was logged out.
				 */
				\do_action( 'wp_logout', $user_id );

				\wp_send_json_success( esc_html__( 'User successfully logged out!', 'wp-2fa' ) );
			}

			\wp_send_json_error( esc_html__( 'Failed - wrong credentials.', 'wp-2fa' ) );
		}
	}
}
