<?php
/**
 * Keeps remote-manager one-click logins behind the second factor.
 *
 * @package    wp2fa
 * @subpackage helpers
 * @since      4.2.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

use WP2FA\WP2FA;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Authenticator\Login;
use WP2FA\Utils\Settings_Utils;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP2FA\Admin\Helpers\ManageWP_Guard' ) ) {

	/**
	 * Holds a session the ManageWP worker created until the second factor is given.
	 *
	 * The worker signs its one-click login itself and then calls wp_set_auth_cookie()
	 * directly — it never fires `wp_login`, which is the action this plugin's challenge
	 * hangs off. It also runs while plugins are still being included, and puts itself
	 * first in the load order, so it has already answered the request and redirected
	 * before this plugin is loaded at all. The result was a full administrator session
	 * on an account with 2FA switched on, with nothing to show that the second factor
	 * had been skipped.
	 *
	 * Nothing can be done during that request, so the session is judged on the next one.
	 * Every session created while this plugin is loaded gets stamped as it is made; a
	 * session without that stamp was made before the plugin loaded, which is exactly the
	 * worker's path. Such a session is not trusted: the account is sent through the real
	 * challenge, and the session the worker made is destroyed in the process.
	 *
	 * This only applies where the worker is actually present, so no other site changes
	 * behaviour. A site that would rather keep the one-click login as it was can say so
	 * with the setting below, which is off unless someone turns it on.
	 *
	 * @since 4.2.0
	 */
	class ManageWP_Guard {

		/**
		 * General setting that lets the one-click login through unchallenged.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const SETTING = 'trust_managewp_login';

		/**
		 * Key stamped into a session record while this plugin is watching.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const SESSION_FLAG = 'wp_2fa_observed';

		/**
		 * When this guard started, so sessions older than it are left alone.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const SINCE_OPTION = 'managewp_guard_since';

		/**
		 * Register the hooks this needs.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function init() {
			/*
			 * Stamping has to happen even where the worker is absent. A site can connect
			 * to ManageWP at any time, and sessions that were already open would then look
			 * exactly like the worker's — stamping from the start keeps the distinction
			 * meaningful the moment the worker appears.
			 */
			\add_action( 'set_logged_in_cookie', array( __CLASS__, 'observe_session' ), 10, 6 );

			/*
			 * Ahead of Pending_2FA_Helper::enforce_on_request() at 9, so a worker session
			 * is judged before anything else acts on the request it is carrying.
			 */
			\add_action( 'wp_loaded', array( __CLASS__, 'enforce' ), 8 );

			self::record_start();
		}

		/**
		 * Whether the ManageWP worker is running on this site.
		 *
		 * Asked of the loaded code rather than the plugin list: the worker forces itself
		 * to the front of the load order, so by the time anything here runs it has either
		 * declared itself or it is not there at all.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_worker_active(): bool {
			return \function_exists( 'mwp_container' ) || \class_exists( '\MWP_Worker_Kernel', false );
		}

		/**
		 * Whether the site has chosen to let the one-click login through unchallenged.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_trusted(): bool {
			return Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( self::SETTING ) );
		}

		/**
		 * Whether the setting is worth showing on this site.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function should_offer_setting(): bool {
			return self::is_worker_active();
		}

		/**
		 * Note when this guard first ran, so older sessions are not caught by it.
		 *
		 * Without this, switching the plugin on would challenge everyone who happened to
		 * be signed in at the time — their sessions predate the stamp through no fault of
		 * their own. Only sessions made after this point are judged.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function record_start() {
			if ( ! Settings_Utils::get_option( self::SINCE_OPTION, false ) ) {
				Settings_Utils::update_option( self::SINCE_OPTION, \time() );
			}
		}

		/**
		 * Stamp a session as having been made while this plugin was loaded.
		 *
		 * @param string $cookie     - The logged-in cookie value.
		 * @param int    $expire     - When the cookie expires.
		 * @param int    $expiration - When the session expires.
		 * @param int    $user_id    - Owner of the session.
		 * @param string $scheme     - Cookie scheme.
		 * @param string $token      - Session token.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function observe_session( $cookie, $expire = 0, $expiration = 0, $user_id = 0, $scheme = '', $token = '' ) {
			$user_id = (int) $user_id;

			if ( 'logged_in' !== $scheme || $user_id <= 0 || ! \is_string( $token ) || '' === $token ) {
				return;
			}

			$manager = \WP_Session_Tokens::get_instance( $user_id );
			$session = $manager->get( $token );

			if ( ! \is_array( $session ) ) {
				return;
			}

			$session[ self::SESSION_FLAG ] = true;

			$manager->update( $token, $session );
		}

		/**
		 * Challenge a session this plugin never saw being made.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function enforce() {
			if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return;
			}

			if ( ! self::is_worker_active() || self::is_trusted() ) {
				return;
			}

			$user_id = (int) \get_current_user_id();

			if ( $user_id <= 0 ) {
				return;
			}

			$user = \get_user_by( 'id', $user_id );

			if ( ! $user instanceof \WP_User ) {
				return;
			}

			/*
			 * Nothing to challenge with, and nothing being skipped: an account with no
			 * method, or one the policy excludes, is not owed a second factor.
			 */
			if ( ! User_Helper::is_user_using_two_factor( $user_id ) || User_Helper::run_user_exclusion_check( $user ) ) {
				return;
			}

			if ( ! self::session_needs_challenge( $user_id, (string) \wp_get_session_token() ) ) {
				return;
			}

			/*
			 * A challenge cannot be rendered into these, and letting them through would
			 * hand the caller the very access the challenge exists to withhold.
			 */
			if ( \wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
				\wp_send_json_error(
					array( 'message' => \__( 'Two-factor authentication is required to complete sign-in.', 'wp-2fa' ) ),
					403
				);
			}

			/*
			 * Hands over to the ordinary challenge, which destroys the session it was
			 * given before rendering the form — so the worker's session does not outlive
			 * this request whether or not the second factor is ever supplied.
			 */
			Login::wp_login( $user->user_login, $user );
		}

		/**
		 * Whether the session carrying this request has to answer a challenge.
		 *
		 * Takes the token rather than reading it, so the decision is a function of what
		 * it is given and can be asked about any session.
		 *
		 * @param int    $user_id - Owner of the session.
		 * @param string $token   - Session token carrying the request.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function session_needs_challenge( int $user_id, string $token ): bool {
			if ( '' === $token ) {
				return false;
			}

			$session = \WP_Session_Tokens::get_instance( $user_id )->get( $token );

			/*
			 * No record to judge. Core removes the session before the cookie stops being
			 * presented, so this is a request arriving with a session that has already
			 * been ended rather than one that skipped the challenge.
			 */
			if ( ! \is_array( $session ) ) {
				return false;
			}

			if ( ! empty( $session[ self::SESSION_FLAG ] ) ) {
				return false;
			}

			$since = (int) Settings_Utils::get_option( self::SINCE_OPTION, 0 );

			if ( $since > 0 && ! empty( $session['login'] ) && (int) $session['login'] < $since ) {
				return false;
			}

			return true;
		}
	}
}
