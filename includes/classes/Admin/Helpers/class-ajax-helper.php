<?php
/**
 * Responsible for the AJAX calls.
 *
 * @package    wp2fa
 * @subpackage helpers
 *
 * @since      2.6.0
 *
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

use WP2FA\WP2FA;
use WP2FA\Utils\User_Utils;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
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
		 * Get all users in AJAX matter and returns them
		 *
		 * @since 2.6.0
		 */
		public static function get_all_users() {
			// Check user permissions.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error( 'Access Denied.' );
			}

			// Verify nonce.
			$nonce = isset( $_GET['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_nonce'] ) ) : null;
			if ( null === $nonce || false === $nonce || ! \wp_verify_nonce( $nonce, 'wp-2fa-settings-nonce' ) ) {
				\wp_send_json_error( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			$term = isset( $_GET['term'] ) ? \sanitize_text_field( \wp_unslash( $_GET['term'] ) ) : null;

			if ( null === $term || false === $term ) {
				\wp_send_json_error( \esc_html__( 'Invalid term.', 'wp-2fa' ) );
			}

			$users_args = array(
				'fields' => array( 'ID', 'user_login' ),
			);
			if ( WP_Helper::is_multisite() ) {
				$users_args['blog_id'] = 0;
			}
			$users_data = User_Utils::get_all_user_ids_and_login_names( 'query', $users_args );

			// Create final array which we will fill in below.
			$users = array();

			foreach ( $users_data as $user ) {
				if ( stripos( $user['user_login'], $term ) !== false ) {
					$users[] = array(
						'value' => $user['user_login'],
						'label' => $user['user_login'],
					);
				}
			}

			echo wp_json_encode( $users );
			exit;
		}

		/**
		 * Get all network sites in AJAX way
		 *
		 * @since 2.6.0
		 */
		public static function get_all_network_sites() {
			// Check user permissions.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error( 'Access Denied.' );
			}

			// Verify nonce.
			$nonce = isset( $_GET['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_nonce'] ) ) : null;
			if ( null === $nonce || false === $nonce || ! \wp_verify_nonce( $nonce, 'wp-2fa-settings-nonce' ) ) {
				\wp_send_json_error( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			$term = isset( $_GET['term'] ) ? \sanitize_text_field( \wp_unslash( $_GET['term'] ) ) : null;

			if ( null === $term || false === $term ) {
				\wp_send_json_error( \esc_html__( 'Invalid term.', 'wp-2fa' ) );
			}
			// Fetch sites.
			$sites_found = array();

			foreach ( WP_Helper::get_multi_sites() as $site ) {
				if ( false !== stripos( $site->blogname, $term ) ) {
					$sites_found[] = array(
						'label' => $site->blog_id,
						'value' => $site->blogname,
					);
				}
			}

			echo \wp_json_encode( $sites_found );
			exit;
		}

		/**
		 * Unlock users accounts if they have overrun grace period it is also used in AJAX calls
		 *
		 * @param  int $user_id User ID.
		 *
		 * @since 2.6.0
		 */
		public static function unlock_account( $user_id ) {
			// Check user permissions.
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error( 'Access Denied.' );
			}

			$nonce = isset( $_GET['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_nonce'] ) ) : null;
			if ( null === $nonce || false === $nonce || ! \wp_verify_nonce( $nonce, 'wp-2fa-unlock-account-nonce' ) ) {
				\wp_send_json_error( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			$grace_period             = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user_id ), 'grace-period' );
			$grace_period_denominator = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user_id ), 'grace-period-denominator' );
			$create_a_string          = $grace_period . ' ' . $grace_period_denominator;
			// Turn that string into a time.
			$grace_expiry = strtotime( $create_a_string );

			$user_id = isset( $_GET['user_id'] ) ? \intval( \sanitize_text_field( \wp_unslash( $_GET['user_id'] ) ) ) : null;

			if ( isset( $user_id ) ) {

				User_Helper::remove_meta( WP_2FA_PREFIX . 'locked_account_notification', $user_id );
				User_Helper::remove_grace_period( $user_id );
				User_Helper::remove_meta( User_Helper::USER_LOCKED_STATUS );

				User_Helper::set_user_expiry_date( (string) $grace_expiry, $user_id );
				Settings_Page::send_account_unlocked_email( $user_id );

				/*
				* Fires after the user is unlocked.
				*
				* @param \WP_User $user - The user for which the method has been set.
				*
				* @since 2.6.0
				*/
				\do_action( WP_2FA_PREFIX . 'user_is_unlocked', User_Helper::get_user( $user_id ) );

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
								File_Writer::save_secret_key( $secret_key );
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

			// Verify nonce.
			$nonce = isset( $_GET['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_nonce'] ) ) : null;
			if ( null === $nonce || false === $nonce || ! \wp_verify_nonce( $nonce, 'wp-2fa-remove-user-2fa-nonce' ) ) {
				\wp_send_json_error( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
			}

			$user_id = isset( $_GET['user_id'] ) ? \intval( \sanitize_text_field( \wp_unslash( $_GET['user_id'] ) ) ) : null;

			if ( isset( $user_id ) ) {

				if ( ! \current_user_can( 'manage_options' ) && \get_current_user_id() !== $user_id ) {
					\wp_send_json_error( 'Access Denied.' );
				}

				User_Helper::remove_2fa_for_user( $user_id );

				if ( isset( $get_array['admin_reset'] ) ) {
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
				// Verify nonce.
				$nonce = isset( $_GET['wp_2fa_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['wp_2fa_nonce'] ) ) : null;
				if ( null === $nonce || false === $nonce || ! \wp_verify_nonce( $nonce, 'wp-2fa-settings-nonce' ) ) {
					\wp_send_json_error( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
				}

				$roles = array();

				$term = isset( $_GET['term'] ) ? \sanitize_text_field( \wp_unslash( $_GET['term'] ) ) : null;
				if ( null === $term || false === $term ) {
					\wp_send_json_error( esc_html__( 'Invalid term.', 'wp-2fa' ) );
				}

				foreach ( WP_Helper::get_roles_wp() as $role => $human_readable ) {
					if ( stripos( $human_readable, $term ) !== false ) {
						$roles[] = array(
							'label' => \sanitize_text_field( $role ),
							'value' => \sanitize_text_field( $human_readable ),
						);
					}
				}

				echo \wp_json_encode( $roles );
				exit;
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
					\wp_send_json_success( esc_html__( 'Test email was successfully sent to ', 'wp-2fa' ) . '<strong>' . esc_html( $email ) . '</strong>' );
				}

				\wp_send_json_error( esc_html__( 'Failed to send test email.', 'wp-2fa' ) );
			}

			$email_templates = Settings_Page_Email::get_email_notification_definitions();
			foreach ( $email_templates as $email_template ) {
				if ( $email_id === $email_template->get_email_content_id() ) {
					$subject = wp_strip_all_tags( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( $email_id . '_email_subject' ) ) );
					$message = \wpautop( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( $email_id . '_email_body' ), $user_id ) );

					$email_sent = Settings_Page::send_email( $email, $subject, $message );
					if ( $email_sent ) {
						\wp_send_json_success( esc_html__( 'Test email ', 'wp-2fa' ) . '<strong>' . \esc_html( $email_template->get_title() ) . '</strong>' . esc_html__( ' was successfully sent to ', 'wp-2fa' ) . '<strong>' . \esc_html( $email ) . '</strong>' );
					}

					\wp_send_json_error( esc_html__( 'Failed to send test email.', 'wp-2fa' ) );
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
			<div class="notice notice-success is-dismissible">
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
			<div class="notice notice-success is-dismissible">
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
			<div class="notice notice-success is-dismissible">
				<p><?php \esc_html_e( 'User account successfully unlocked. User can login again.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
			<?php
		}
	}
}
