<?php // phpcs:ignore

namespace WP2FA\Admin;

use \WP2FA\WP2FA as WP2FA;
use WP2FA\Utils\DateTimeUtils;
use WP2FA\Admin\Controllers\Settings;
use \WP2FA\Authenticator\Authentication as Authentication;

/**
 * UserNotices - Class for displaying notices to our users.
 */
class UserNotices {

	/**
	 * Lets set things up
	 */
	public function __construct() {
		$enforcement_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
		if ( ! empty( $enforcement_policy ) ) {
			// Check we are supposed to, before adding action to show nag.
			if ( in_array( $enforcement_policy, [ 'all-users', 'certain-roles-only', 'certain-users-only', 'superadmins-only', 'superadmins-siteadmins-only', 'enforce-on-multisite' ] ) ) {
				add_action( 'admin_notices', array( $this, 'user_setup_2fa_nag' ) );
				add_action( 'network_admin_notices', array( $this, 'user_setup_2fa_nag' ) );
			} elseif ( 'do-not-enforce' === WP2FA::get_wp2fa_setting( 'enforcement-policy' ) ) {
				add_action( 'admin_notices', array( $this, 'user_reconfigure_2fa_nag' ) );
				add_action( 'network_admin_notices', array( $this, 'user_setup_2fa_nag' ) );
			}
		}
	}

	/**
	 * The nag content
	 */
	public function user_setup_2fa_nag( $is_shortcode = '', $configure_2fa_url = '' ) {

		$this->ensureUser();

		if ( isset( $_GET['user_id'] ) ) {
			$current_profile_user_id = (int) $_GET['user_id'];
		} elseif ( ! is_null( $this->wp2faUser->getUser() ) ) {
			$current_profile_user_id = $this->wp2faUser->getUser()->ID;
		} else {
			$current_profile_user_id = false;
		}

		if ( ! $current_profile_user_id ||
			isset( $_GET['user_id'] ) &&
			$_GET['user_id'] !== $this->wp2faUser->getUser()->ID ||
			$this->wp2faUser->getEnforcedInstantly() ) {
			return;
		}

		$grace_expiry    = $this->wp2faUser->getGracePeriodExpiration();

		$class           = 'notice notice-info wp-2fa-nag';

		if ( $this->wp2faUser->needsToReconfigure2FA() ) {
			$message = esc_html__( 'The 2FA method you were using is no longer allowed on this website. Please reconfigure 2FA using one of the supported methods within', 'wp-2fa' );
		} else {
			$message = esc_html__( 'This website’s administrator requires you to enable 2FA authentication', 'wp-2fa' );
		}

		$is_nag_dismissed   = $this->wp2faUser->getDismissedNag();
		$is_nag_needed      = WP2FA::isUserEnforced( $this->wp2faUser->getUser()->ID );
		$is_user_excluded   = WP2FA::is_user_excluded( $this->wp2faUser->getUser()->ID );
		$enabled_methods    = $this->wp2faUser->getEnabledMethods();
		$new_page_id        = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
		$new_page_permalink = get_permalink( $new_page_id );

		$setup_url = Settings::getSetupPageLink();

		// Allow setup URL to be customized if outputting via shortcode.
		if ( isset( $is_shortcode ) && 'output_shortcode' === $is_shortcode && ! empty( $configure_2fa_url ) ) {
			$setup_url = $configure_2fa_url;
		}

		// Stop the page from being a link to a page this user cant access if needed.
		if ( WP2FA::is_this_multisite() && ! is_user_member_of_blog( $this->wp2faUser->getUser()->ID ) ) {
			$new_page_id = false;
		}

		// If we have a custom page generated, lets use it.
		if ( ! empty( $new_page_id ) && $new_page_permalink ) { 
			$setup_url = $new_page_permalink;
		}

		// If the nag has not already been dismissed, and of course if the user is eligible, lets show them something.
		if ( ! $is_nag_dismissed && $is_nag_needed && empty( $enabled_methods ) && ! $is_user_excluded && ! empty( $grace_expiry ) ) {
			echo '<div class="'.esc_attr( $class ).'">';
			echo '<p>'.esc_html( $message );
			echo ' <span class="grace-period-countdown">'.esc_attr( DateTimeUtils::format_grace_period_expiration_string(null, $grace_expiry) ).'</span>';
			echo ' <a href="'.esc_url( $setup_url ).'" class="button button-primary">'.esc_html__( 'Configure 2FA now', 'wp-2fa' ).'</a>';
			echo ' <a href="#" class="button button-secondary dismiss-user-configure-nag">'.esc_html__( 'Remind me on next login', 'wp-2fa' ).'</a></p>';
			echo '</div>';
		} else {
			$this->user_reconfigure_2fa_nag();
		}
	}

	/**
	 * The nag content
	 */
	public function user_reconfigure_2fa_nag() {

		$this->ensureUser();

		// If the nag has not already been dismissed, and of course if the user is eligible, lets show them something.
		if ( $this->wp2faUser->needsToReconfigureMethod() ) {
			$class           = 'notice notice-info wp-2fa-nag';

			$message = esc_html__( 'The 2FA method you were using is no longer allowed on this website. Please reconfigure 2FA using one of the supported methods.', 'wp-2fa' );

			echo '<div class="'.esc_attr( $class ).'"><p>'.esc_html( $message );
			echo ' <a href="'.esc_url( Settings::getSetupPageLink() ).'" class="button button-primary">'.esc_html__( 'Configure 2FA now', 'wp-2fa' ).'</a>';
			echo '  <a href="#" class="button button-secondary dismiss-user-reconfigure-nag">'.esc_html__( 'I\'ll do it later', 'wp-2fa' ).'</a></p>';
			echo '</div>';
		}
	}

	/**
	 * Dismiss notice and setup a user meta value so we know its been dismissed
	 */
	public function dismiss_nag() {
		$this->ensureUser();
		$this->wp2faUser->setDismissedNag();
	}

	/**
	 * Reset the nag when the user logs out, so they get it again next time.
	 */
	public function reset_nag( $user_id ) {
		$this->wp2faUser = new User( $user_id );
		$this->wp2faUser->deleteUserMeta('wp_2fa_update_nag_dismissed');
	}

	/**
	 * Sets user variable
	 *
	 * @return void
	 */
	public function ensureUser() {
		if ( ! isset( $this->wp2faUser ) ) {
			$this->wp2faUser = new User();
		}
	}
}
