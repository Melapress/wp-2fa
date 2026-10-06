<?php
/**
 * Responsible for WP2FA user's reset password forms.
 *
 * @package    wp2fa
 * @subpackage resetpassword
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 *
 * @since     2.5.0
 */

declare(strict_types=1);

namespace WP2FA\Authenticator;

defined( 'ABSPATH' ) || exit;

use WP2FA\Methods\Email;
use WP2FA\Authenticator\Login;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Setup_Wizard;
use WP2FA\Admin\Views\Password_Reset_2FA;
use WP2FA\Admin\Methods\Traits\Login_Attempts;
use WP2FA\Passkeys\Passkeys_Rate_Limiter;
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
		 * How many reset codes an account may be sent in one window.
		 */
		private const RESET_CODE_QUOTA = 5;

		/** Maximum code checks per account per window, independent of email sends. */
		private const RESET_VERIFICATION_QUOTA = 5;

		/**
		 * The quota window, in seconds.
		 */
		private const RESET_CODE_WINDOW = 15 * MINUTE_IN_SECONDS;

		/**
		 * The maximum reset requests from one source across accounts per window.
		 */
		private const RESET_SOURCE_QUOTA = 100;

		/**
		 * Leave room for another source when one source targets an account.
		 */
		private const RESET_ACCOUNT_SOURCE_QUOTA = 3;

		/**
		 * Explicit resends can happen once per account per minute.
		 */
		private const RESET_RESEND_COOLDOWN = MINUTE_IN_SECONDS;

		/**
		 * Stored in the network's main options table, whose option_name is unique.
		 */
		private const QUOTA_KEY_PREFIX = WP_2FA_PREFIX . 'reset_code_quota_v2_';

		/**
		 * Cleanup is registered on every request but scheduled only after use.
		 */
		public const QUOTA_CLEANUP_HOOK = 'wp_2fa_reset_code_quota_cleanup';

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
			if ( false === $user_data && self::any_reset_protection_enabled() && ! empty( $_POST['user_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				self::show_initial_challenge();
			}
			if ( $errors->has_errors() ) {
				return $errors;
			}
			if ( false === $user_data ) {
				return $errors;
			}
			if ( null === $user_data ) {

				\add_filter(
					'lostpassword_errors',
					function ( $errors ) {
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

			/*
			 * Who is signed in says nothing about the account being reset. This
			 * used to stand aside whenever any other account was logged in, so a
			 * subscriber's session was enough to take the 2FA code out of an
			 * administrator's reset - and since this is an action, returning here
			 * let WordPress carry on with its ordinary reset email.
			 *
			 * The one request it is right to leave alone is an administrator
			 * using "Send password reset" in the dashboard for an account they
			 * may edit: that is not the account holder asking, and the code page
			 * this would redirect to has no place in a users screen or its AJAX
			 * call. The emailed link still has to be followed by the account
			 * holder.
			 */
			if ( \is_admin() && \get_current_user_id() !== (int) $user_data->ID && \current_user_can( 'edit_user', $user_data->ID ) ) {
				return $errors;
			}

			$expire_action = Settings_Utils::get_setting_role( User_Helper::get_user_role( $user_data ), Password_Reset_2FA::PASSWORD_RESET_SETTINGS_NAME, true );

			if ( 'password-reset-2fa' !== $expire_action ) {
				return $errors;
			}

			if ( User_Helper::get_reset_password_valid_for_user( $user_data ) ) {
				return $errors;
			}

			$existing = self::current_reset_transaction( (int) $user_data->ID );
			if ( $existing ) {
				if ( self::spend_resend_cooldown( (int) $user_data->ID ) && self::spend_reset_code_quota( $user_data ) ) {
					self::send_reset_code( $user_data );
				}
				self::show_initial_challenge( $user_data );
			}

			// Before any row is written or any mail is sent.
			if ( ! self::spend_reset_code_quota( $user_data ) ) {
				self::show_initial_challenge();
			}

			$transaction = self::get_or_create_reset_transaction( (int) $user_data->ID );
			if ( ! $transaction ) {
				self::show_initial_challenge();
			}
			if ( $transaction['created'] ) {
				self::spend_resend_cooldown( (int) $user_data->ID );
				self::send_reset_code( $user_data );
			}
			self::show_initial_challenge( $user_data );
		}

		/**
		 * Prompt immediately without disclosing whether the submitted account exists.
		 * The identifier is echoed from the request; only an emailed code and a
		 * valid transaction can advance this form to WordPress's reset email.
		 *
		 * @param \WP_User|null $user Protected account, if available.
		 */
		private static function show_initial_challenge( $user = null ): void {
			$identifier = isset( $_POST['user_login'] ) ? \sanitize_text_field( \wp_unslash( $_POST['user_login'] ) ) : '';
			self::show_two_factor_login( $user ? $user : new \WP_User(), self::initial_challenge_nonce( $identifier ), '', false, $identifier );
			exit;
		}

		/**
		 * A stateless browser challenge looks the same for known and unknown users.
		 * It grants no reset permission: the active transaction and emailed OTP
		 * must still be validated. Email links retain their own transaction nonce.
		 *
		 * @param string $identifier Submitted username or email.
		 * @param int    $offset     Previous time window when validating.
		 * @return string
		 */
		private static function initial_challenge_nonce( string $identifier, int $offset = 0 ): string {
			$window = (int) floor( time() / ( 15 * MINUTE_IN_SECONDS ) ) + $offset;
			return hash_hmac( 'sha256', 'reset_initial:' . $identifier . ':' . $window, \wp_salt( 'auth' ) );
		}

		/** A resend must not disclose whether the submitted account exists. */
		private static function resend_message(): string {
			return \esc_html__( 'If a reset is pending, a code has been sent. Please wait at least a minute before requesting another code.', 'wp-2fa' );
		}

		/** Invalid email links return to the generic confirmation page. */
		private static function redirect_to_check_email(): void {
			$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['redirect_to'] ) ) : 'wp-login.php?checkemail=confirm';
			\wp_safe_redirect( $redirect_to );
			exit;
		}

		/** Unknown identifiers receive the same initial form when reset 2FA is enabled. */
		private static function any_reset_protection_enabled(): bool {
			foreach ( array_keys( \wp_roles()->roles ) as $role ) {
				if ( 'password-reset-2fa' === Settings_Utils::get_setting_role( $role, Password_Reset_2FA::PASSWORD_RESET_SETTINGS_NAME, true ) ) {
					return true;
				}
			}
			return false;
		}

		/** Send the reset code email and record its dispatch. */
		private static function send_reset_code( \WP_User $user ): void {
			Code_Guard::dispatch(
				static function () use ( $user ) {
					return Setup_Wizard::send_authentication_setup_email( $user->ID, 'nominated_email_address', true );
				},
				$user
			);
		}

		/**
		 * Generates the html form for the second step of the authentication process.
		 *
		 * @param \WP_User    $user        User whose reset is being verified.
		 * @param string      $login_nonce Generated challenge nonce.
		 * @param string      $error_msg   Error message (if any) to show.
		 * @param bool        $send_code   Whether rendering may dispatch a new code.
		 * @param string|null $identifier  Submitted identifier for an initial browser challenge.
		 *
		 * @since 2.5.0
		 */
		public static function show_two_factor_login( $user, $login_nonce, $error_msg = '', $send_code = true, $identifier = null ) {

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
				echo '<div id="login_error"><strong class="wp-2fa-error-msg">' . \esc_html( \apply_filters( 'login_errors', \esc_html( $error_msg ) ) ) . '</strong><br /></div>';
			}
			?>
			<form name="lostpasswordform" id="lostpasswordform" action="<?php echo \esc_url( network_site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>" method="post">
				<?php if ( null !== $identifier ) : ?>
					<input type="hidden" name="user_login" value="<?php echo \esc_attr( $identifier ); ?>" />
				<?php else : ?>
					<input type="hidden" name="wp-auth-id" id="wp-auth-id" value="<?php echo \esc_attr( $user->ID ); ?>" />
				<?php endif; ?>
				<input type="hidden" name="wp-auth-nonce" id="wp-auth-nonce" value="<?php echo \esc_attr( $login_nonce ); ?>" />
				<input type="hidden" name="reset"      id="reset"      value="<?php echo \esc_attr( 'reset-2fa' ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo \esc_attr( $redirect_to ); ?>" />
				<?php
				// Check to see what provider is set and give the relevant authentication page.

				Login::email_authentication_page( $user, true, $send_code );
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

					<p class="wp-2fa-email-resend">
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
		 * Spends one reset code from durable source and account quotas, or stops.
		 *
		 * Anyone who knows a username or email address can start a password
		 * reset, and with reset 2FA on, requests, resends and failed codes
		 * could send more mail and create more transaction rows. The old attempt
		 * counter cleared at its limit, allowing the next request to start over.
		 *
		 * A transient get/increment/set lost updates when requests raced and could
		 * disappear early. An options row has a unique key and is incremented by
		 * one database statement. The window is fixed, so old rows are cleaned
		 * after they can no longer affect any request. This quota is separate
		 * from the login lock: exhausting reset requests must not block login.
		 *
		 * @param \WP_User $user  The account the code would go to.
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function spend_reset_code_quota_or_stop( \WP_User $user ): void {
			if ( ! self::spend_reset_code_quota( $user ) ) {
				self::stop_for_quota();
			}
		}

		/**
		 * Initial requests use the result to keep their public response generic.
		 *
		 * @param \WP_User $user  The account the code would go to.
		 *
		 * @return bool True if the reset code quota was successfully spent, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function spend_reset_code_quota( \WP_User $user ): bool {
			self::schedule_quota_cleanup();
			$window               = max( MINUTE_IN_SECONDS, min( DAY_IN_SECONDS, (int) \apply_filters( WP_2FA_PREFIX . 'reset_code_window', self::RESET_CODE_WINDOW ) ) );
			$quota                = (int) \apply_filters( WP_2FA_PREFIX . 'reset_code_quota', self::RESET_CODE_QUOTA, $user );
			$source_quota         = (int) \apply_filters( WP_2FA_PREFIX . 'reset_source_quota', self::RESET_SOURCE_QUOTA );
			$account_source_quota = (int) \apply_filters( WP_2FA_PREFIX . 'reset_account_source_quota', self::RESET_ACCOUNT_SOURCE_QUOTA, $user );
			$bucket               = (int) ( floor( time() / $window ) * $window );

			// A source quota limits campaigns across accounts. Use the same trusted
			// proxy rules as passkey login; never trust a supplied XFF by itself.
			$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
			if ( ( $source_quota > 0 || $account_source_quota > 0 ) && false !== \filter_var( $remote, FILTER_VALIDATE_IP ) ) {
				$source = Passkeys_Rate_Limiter::get_client_ip();
				if ( '' !== $source ) {
					$source_hash = hash_hmac( 'sha256', $source, \wp_salt( 'auth' ) );
					if ( $account_source_quota > 0 && ! self::spend_quota_bucket( 'as_' . $user->ID . '_' . $source_hash, $bucket, $account_source_quota ) ) {
						return false;
					}
					if ( $source_quota > 0 && ! self::spend_quota_bucket( 's_' . $source_hash, $bucket, $source_quota ) ) {
						return false;
					}
				}
			}

			if ( $quota > 0 && ! self::spend_quota_bucket( 'a_' . $user->ID, $bucket, $quota ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Ensure even a rejected request's counters are eventually removed.
		 *
		 * @since 4.2.0
		 *
		 * @return void
		 */
		private static function schedule_quota_cleanup(): void {
			if ( ! \wp_next_scheduled( self::QUOTA_CLEANUP_HOOK ) ) {
				\wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::QUOTA_CLEANUP_HOOK );
			}
		}

		/**
		 * Atomically increment one fixed-window row; readback may deny early, never late.
		 *
		 * @param string $scope  The scope of the quota bucket.
		 * @param int    $bucket The fixed-window bucket identifier.
		 * @param int    $quota  The maximum allowed quota for the bucket.
		 *
		 * @return bool True if the quota was successfully spent, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function spend_quota_bucket( string $scope, int $bucket, int $quota ): bool {
			global $wpdb;
			$table   = self::quota_options_table();
			$key     = self::QUOTA_KEY_PREFIX . sprintf( '%010d', $bucket ) . '_' . $scope;
			$cap     = min( PHP_INT_MAX - 1, $quota ) + 1;
			$written = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"INSERT INTO $table (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = LEAST(CAST(option_value AS UNSIGNED) + 1, %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table is generated by WordPress; %i requires WP 6.2.
					$key,
					$cap
				)
			);
			if ( false === $written ) {
				return false;
			}

			$count = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			return null !== $count && ctype_digit( (string) $count ) && (int) $count <= $quota;
		}

		/**
		 * Reserve a real 60-second resend interval with a conditional DB update.
		 *
		 * @param int $user_id The ID of the user for whom to spend the resend cooldown.
		 * @return bool True if the resend cooldown was successfully spent, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function spend_resend_cooldown( int $user_id ): bool {
			global $wpdb;
			$table    = self::quota_options_table();
			$key      = self::QUOTA_KEY_PREFIX . 'c_' . $user_id;
			$now      = time();
			$inserted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "INSERT IGNORE INTO $table (option_name, option_value, autoload) VALUES (%s, %d, 'no')", $key, $now ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name.
			);
			if ( false === $inserted ) {
				return false;
			}
			if ( 1 === $inserted ) {
				return true;
			}

			$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"UPDATE $table SET option_value = %d WHERE option_name = %s AND CAST(option_value AS UNSIGNED) <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table name.
					$now,
					$key,
					$now - self::RESET_RESEND_COOLDOWN
				)
			);
			return 1 === $updated;
		}

		/**
		 * The main site's unique option_name index works for network-wide users.
		 *
		 * @return string The name of the options table to use for quota tracking.
		 *
		 * @since 4.2.0
		 */
		private static function quota_options_table(): string {
			global $wpdb;
			return \is_multisite() ? $wpdb->get_blog_prefix( \get_main_site_id() ) . 'options' : $wpdb->options;
		}

		/**
		 * One recoverable reset transaction per account; the creator sends its initial code.
		 *
		 * @param int $user_id The ID of the user for whom to get or create the reset transaction.
		 * @return array|false The reset transaction data if successful, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function get_or_create_reset_transaction( int $user_id ) {
			global $wpdb;
			$table   = self::quota_options_table();
			$key     = self::QUOTA_KEY_PREFIX . 't_' . $user_id;
			$now     = time();
			$record  = $now . ':' . bin2hex( random_bytes( 32 ) );
			$written = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "INSERT IGNORE INTO $table (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $record ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
			);
			if ( false === $written ) {
				return false;
			}
			$created = 1 === $written;
			if ( ! $created ) {
				$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"UPDATE $table SET option_value = %s WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted WordPress table name.
						$record,
						$key,
						$now - 15 * MINUTE_IN_SECONDS
					)
				);
				if ( false === $updated ) {
					return false;
				}
				$created = 1 === $updated;
			}
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			return self::valid_reset_record( $stored ) ? array(
				'nonce'   => self::reset_transaction_nonce( $user_id, $stored ),
				'created' => $created,
			) : false;
		}

		/**
		 * Validate the DB record before deriving or accepting its bearer nonce.
		 *
		 * @param string $record The reset transaction record from the database.
		 * @return bool True if the record is valid, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function valid_reset_record( $record ): bool {
			if ( ! is_string( $record ) || ! preg_match( '/^[0-9]{10}:[a-f0-9]{64}$/', $record ) ) {
				return false;
			}
			return time() <= (int) substr( $record, 0, 10 ) + 15 * MINUTE_IN_SECONDS;
		}

		/**
		 * The secret-derived value need not be stored or exposed in the database.
		 *
		 * @param int    $user_id The ID of the user for whom to generate the nonce.
		 * @param string $record  The reset transaction record from the database.
		 * @return string The derived reset transaction nonce.
		 *
		 * @since 4.2.0
		 */
		private static function reset_transaction_nonce( int $user_id, string $record ): string {
			return hash_hmac( 'sha256', 'reset_2fa:' . $user_id . ':' . $record, \wp_salt( 'auth' ) );
		}

		/**
		 * Reopening an active challenge has no email or nonce cost.
		 *
		 * @param int $user_id The ID of the user for whom to get the current reset transaction.
		 * @return string|false The current reset transaction nonce if available, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function current_reset_transaction( int $user_id ) {
			global $wpdb;
			$table  = self::quota_options_table();
			$key    = self::QUOTA_KEY_PREFIX . 't_' . $user_id;
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			return self::valid_reset_record( $stored ) ? self::reset_transaction_nonce( $user_id, $stored ) : false;
		}

		/**
		 * Existing usermeta reset transactions remain usable for their 15-minute lifetime.
		 *
		 * @param int    $user_id The ID of the user for whom to verify the reset transaction.
		 * @param string $nonce   The reset transaction nonce to verify.
		 * @return bool True if the reset transaction is valid, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function verify_reset_transaction( int $user_id, string $nonce ): bool {
			global $wpdb;
			$table  = self::quota_options_table();
			$key    = self::QUOTA_KEY_PREFIX . 't_' . $user_id;
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			if ( self::valid_reset_record( $stored ) && hash_equals( self::reset_transaction_nonce( $user_id, $stored ), $nonce ) ) {
				return true;
			}
			return Login::verify_login_nonce( $user_id, $nonce, 'reset_2fa' );
		}

		/**
		 * Delete exactly the transaction that passed verification.
		 *
		 * @param int    $user_id The ID of the user for whom to consume the reset transaction.
		 * @param string $nonce   The reset transaction nonce to consume.
		 * @return bool True if the reset transaction was successfully consumed, false otherwise.
		 *
		 * @since 4.2.0
		 */
		private static function consume_reset_transaction( int $user_id, string $nonce ): bool {
			global $wpdb;
			$table  = self::quota_options_table();
			$key    = self::QUOTA_KEY_PREFIX . 't_' . $user_id;
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			if ( self::valid_reset_record( $stored ) && hash_equals( self::reset_transaction_nonce( $user_id, $stored ), $nonce ) ) {
				return 1 === $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE option_name = %s AND option_value = %s", $key, $stored ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			}
			return Login::consume_login_nonce( $user_id, $nonce, 'reset_2fa' );
		}

		/**
		 * Remove expired fixed-window rows after every possible window ends.
		 *
		 * @since 4.2.0
		 */
		public static function cleanup_reset_code_quotas(): void {
			global $wpdb;
			$table  = self::quota_options_table();
			$prefix = $wpdb->esc_like( self::QUOTA_KEY_PREFIX ) . '%';
			$before = self::QUOTA_KEY_PREFIX . sprintf( '%010d', time() - DAY_IN_SECONDS ) . '_';
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE option_name LIKE %s AND option_name < %s", $prefix, $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			$cooldown_prefix = $wpdb->esc_like( self::QUOTA_KEY_PREFIX . 'c_' ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $cooldown_prefix, time() - DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			$transaction_prefix = $wpdb->esc_like( self::QUOTA_KEY_PREFIX . 't_' ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d", $transaction_prefix, time() - DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM $table WHERE option_name LIKE %s LIMIT 1", $prefix ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Trusted table name.
			if ( null === $remaining ) {
				\wp_clear_scheduled_hook( self::QUOTA_CLEANUP_HOOK );
			}
		}

		/**
		 * Stop before mail or nonce writes, leaving any existing challenge valid.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function stop_for_quota(): void {
			\wp_die( \esc_html__( 'Too many verification attempts. Please try again later.', 'wp-2fa' ), '', array( 'response' => 429 ) );
		}

		/**
		 * Explain the attempt limit without revealing whether an account exists
		 * or has an active reset transaction through a different error response.
		 */
		private static function verification_failure_message(): string {
			return \esc_html__( 'ERROR: Invalid verification code or attempt limit reached. After five attempts, wait 15 minutes before trying again.', 'wp-2fa' );
		}

		/**
		 * Login form validation.
		 *
		 * @since 2.5.0
		 */
		public static function login_form_validate_2fa() {
			if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_GET['wp-auth-id'], $_GET['wp-auth-nonce'], $_GET['reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$auth_id = absint( $_GET['wp-auth-id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$nonce   = sanitize_text_field( wp_unslash( $_GET['wp-auth-nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$user    = \get_userdata( $auth_id );
				if ( $user && self::verify_reset_transaction( $auth_id, $nonce ) ) {
					self::show_two_factor_login( $user, $nonce, '', false );
					exit;
				}
				self::redirect_to_check_email();
			}
			if ( ! isset( $_POST['wp-auth-nonce'], $_POST['reset'] ) || 'reset-2fa' !== $_POST['reset'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return;
			}

			$identifier = isset( $_POST['user_login'] ) ? \sanitize_text_field( \wp_unslash( $_POST['user_login'] ) ) : null;
			if ( null !== $identifier ) {
				// Match WordPress retrieve_password(): prefer email, then try username.
				$user = strpos( $identifier, '@' ) ? \get_user_by( 'email', $identifier ) : false;
				if ( ! $user ) {
					$user = \get_user_by( 'login', $identifier );
				}
			} else {
				$user = \get_userdata( isset( $_POST['wp-auth-id'] ) ? absint( $_POST['wp-auth-id'] ) : 0 );
			}
			$nonce             = \sanitize_text_field( \wp_unslash( $_POST['wp-auth-nonce'] ) );
			$transaction_nonce = $nonce;
			if ( null !== $identifier ) {
				$valid_browser_nonce = hash_equals( self::initial_challenge_nonce( $identifier ), $nonce ) || hash_equals( self::initial_challenge_nonce( $identifier, -1 ), $nonce );
				$transaction_nonce   = $user && $valid_browser_nonce ? self::current_reset_transaction( (int) $user->ID ) : false;
			}
			if ( ! $user || ! $transaction_nonce || ! self::verify_reset_transaction( (int) $user->ID, $transaction_nonce ) ) {
				if ( null !== $identifier ) {
					self::show_two_factor_login( new \WP_User(), $nonce, isset( $_REQUEST[ Login::INPUT_NAME_RESEND_CODE ] ) ? self::resend_message() : self::verification_failure_message(), false, $identifier );
					exit;
				}
				\wp_safe_redirect( \wp_login_url() );
				exit;
			}

			$provider = Email::METHOD_NAME;

			if ( isset( $_REQUEST[ Login::INPUT_NAME_RESEND_CODE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( null !== $identifier ) {
					if ( self::spend_resend_cooldown( $user->ID ) && self::spend_reset_code_quota( $user ) ) {
						self::send_reset_code( $user );
					}
					self::show_two_factor_login( $user, $nonce, self::resend_message(), false, $identifier );
					exit;
				}
				self::schedule_quota_cleanup();
				if ( ! self::spend_resend_cooldown( $user->ID ) ) {
					// Keep the existing transaction and code; rendering must not send mail.
					self::show_two_factor_login( $user, $nonce, \esc_html__( 'Please wait before requesting another code.', 'wp-2fa' ), false, $identifier );
					exit;
				}
				self::spend_reset_code_quota_or_stop( $user );
				self::send_reset_code( $user );
				self::show_two_factor_login( $user, $nonce, Code_Guard::dispatch_failed() ? Login::dispatch_failure_message() : \esc_html__( 'A new code has been sent.', 'wp-2fa' ), false, $identifier );
				exit;
			}

			// Reserve before checking the code, across both browser and legacy forms.
			// Email-send quotas must not consume the user's verification attempts.
			self::schedule_quota_cleanup();
			$bucket = (int) ( floor( time() / self::RESET_CODE_WINDOW ) * self::RESET_CODE_WINDOW );
			if ( ! self::spend_quota_bucket( 'v_' . $user->ID, $bucket, self::RESET_VERIFICATION_QUOTA ) ) {
				self::show_two_factor_login( $user, $nonce, self::verification_failure_message(), false, $identifier );
				exit;
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

				// Keep the current code and challenge; retries must not send more mail.
				self::show_two_factor_login( $user, $nonce, self::verification_failure_message(), false, $identifier );

				exit;
			}

			User_Helper::set_reset_password_valid_for_user( true, $user );
			// The emailed code has been consumed. Retire its reset transaction too,
			// so two concurrent submits cannot trigger duplicate password emails.
			if ( ! self::consume_reset_transaction( (int) $user->ID, $transaction_nonce ) ) {
				User_Helper::remove_reset_password_valid_for_user( $user );
				\wp_safe_redirect( \wp_login_url() );
				exit;
			}

			$errors = \retrieve_password( $user->user_email );
			User_Helper::remove_reset_password_valid_for_user( $user );

			if ( ! \is_wp_error( $errors ) ) {
				$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['redirect_to'] ) ) : 'wp-login.php?checkemail=confirm';
				// Remove any counter left by earlier plugin versions after success.
				self::clear_login_attempts( $user );
				\wp_safe_redirect( $redirect_to );
				exit;
			}

			\wp_safe_redirect( site_url( 'wp-login.php?action=lostpassword' ) );
		}
	}
}
