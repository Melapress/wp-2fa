<?php // phpcs:ignore

namespace WP2FA\Cron;

use WP2FA\Admin\SettingsPage;
use WP2FA\Admin\User;
use WP2FA\Utils\UserUtils;
use \WP2FA\WP2FA as WP2FA;

/**
 * Class for handling our crons.
 */
class CronTasks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_2fa_check_grace_period_status', array( $this, 'wp_2fa_check_users_grace_period_status' ) );
		add_action( 'init', array( $this, 'register_check_users_grace_period_status_event' ) );
	}

	// This function will run once the 'wp_2fa_check_users_grace_period_status' is called
	public function wp_2fa_check_users_grace_period_status() {
		//  check if the cronjob is enabled in plugin settings
		if ( empty( WP2FA::get_wp2fa_setting( 'enable_grace_cron' ) ) ) {
			return;
		}

		//  grab all users
		$users_args = array(
			'fields' => array( 'ID' ),
		);

		if ( WP2FA::is_this_multisite() ) {
			$users_args['blog_id'] = 0;
		}

		$users = UserUtils::get_all_user_ids( 'query', $users_args );
		if ( ! is_array( $users ) ) {
			$users = explode( ',', $users );
		}

		if ( empty( $users ) ) {
			return;
		}

		foreach ( $users as $index => $user_id ) {
			//	creating the user object will update their meta fields to reflect latest plugin settings
			$wp2faUser = User::get_instance( $user_id );

			//	run a check to see if user account needs to be locked (this happens only here and during the login)
			$wp2faUser->lock_user_account_if_needed();
		}
	}

	// Function which will register the event
	function register_check_users_grace_period_status_event() {
		// Make sure this event hasn't been scheduled
		if ( ! wp_next_scheduled( 'wp_2fa_check_grace_period_status' ) && ! empty( WP2FA::get_wp2fa_setting( 'enable_grace_cron' ) ) ) {
			// Schedule the event
			wp_schedule_event( time(), 'hourly', 'wp_2fa_check_grace_period_status' );
		}
	}

	/**
	 * Send email to setup authentication
	 */
	public static function send_expired_grace_email( $user_id ) {
		// Bail if the user has not enabled this email.
		if ( 'enable_account_locked_email' !== WP2FA::get_wp2fa_email_templates( 'send_account_locked_email' ) ) {
			return false;
		}

		// Grab user data
		$user = get_userdata( $user_id );
		// Grab user email
		$email = $user->user_email;

		$subject = wp_strip_all_tags( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_locked_email_subject' ), $user_id ) );
		$message = wpautop( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_locked_email_body' ), $user_id ) );

		SettingsPage::send_email( $email, $subject, $message );
	}
}
