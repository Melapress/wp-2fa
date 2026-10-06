<?php
/**
 * Migrates TOTP enrolments from the discontinued Wordfence Login Security plugin.
 *
 * @package    wp2fa
 * @subpackage admin
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Migrations;

use WP2FA\Methods\TOTP;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Utils\Debugging;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Authenticator\Authentication;
use WP2FA\Authenticator\Open_SSL;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Migrations\Wordfence_Login_Security' ) ) {

	/**
	 * Reads a user's TOTP enrolment out of Wordfence Login Security and re-creates it in WP 2FA.
	 *
	 * Wordfence stores the TOTP shared secret as a raw, unencrypted 20-byte blob in its own
	 * table, and its parameters are the RFC 6238 defaults that WP 2FA also uses (HMAC-SHA1,
	 * 6 digits, 30-second step). Re-creating the enrolment is therefore a pure re-encoding:
	 * base32 the same 20 bytes and store them the way WP 2FA stores its own secrets. The
	 * authenticator app the user already has keeps working, and they are never asked to
	 * re-enrol.
	 *
	 * Nothing is read from Wordfence's two global secrets. Those sign the "remember this
	 * device" cookie and the CAPTCHA payloads; they are not involved in TOTP at all.
	 *
	 * Migration happens one user at a time, on login, rather than as a bulk pass. That keeps
	 * a large site from having every secret decrypted and rewritten in a single request, and
	 * means a user is only touched at the moment their enrolment is actually needed.
	 *
	 * @since 4.2.0
	 */
	class Wordfence_Login_Security {

		/**
		 * The plugin we migrate from, as WordPress identifies it.
		 *
		 * Only the standalone Login Security plugin counts. The full Wordfence plugin bundles
		 * the same code and can leave the same table behind, but it is not discontinued, so
		 * offering to migrate away from it would be wrong.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const SOURCE_PLUGIN = 'wordfence-login-security/wordfence-login-security.php';

		/**
		 * The AJAX action Wordfence's login form calls before it will submit.
		 *
		 * @var string
		 */
		private const SOURCE_PREFLIGHT_ACTION = 'wordfence_ls_authenticate';

		/**
		 * Wordfence's enrolment table, without the prefix.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		private const SECRETS_TABLE = 'wfls_2fa_secrets';

		/**
		 * Set on every user whose enrolment we have re-created, and what the status figures count.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const MIGRATED_META = WP_2FA_PREFIX . 'wfls_migrated';

		/**
		 * Whether the administrator has turned automatic migration on.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const ENABLED_SETTING = 'wordfence_migration_enabled';

		/**
		 * The length of a Wordfence TOTP secret. Anything else is not one.
		 *
		 * @var int
		 *
		 * @since 4.2.0
		 */
		private const SECRET_BYTES = 20;

		/**
		 * The length of one Wordfence recovery code. They are stored end to end, no delimiter.
		 *
		 * @var int
		 */
		private const RECOVERY_CODE_BYTES = 8;

		/**
		 * Cached table-exists answer, so a login does not re-run SHOW TABLES.
		 *
		 * @var bool|null
		 *
		 * @since 4.2.0
		 */
		private static $table_exists = null;

		/**
		 * User IDs whose Wordfence challenge should be skipped during this request.
		 *
		 * @var array<int,bool>
		 *
		 * @since 4.2.0
		 */
		private static $suppressed_user_ids = array();

		/**
		 * Wordfence's own preflight handler, kept so it can still be called for other users.
		 *
		 * @var callable|null
		 */
		private static $source_preflight_handler = null;

		/**
		 * Wordfence's original authentication callback.
		 *
		 * @var callable|null
		 *
		 * @since 4.2.0
		 */
		private static $source_authenticate_callback = null;

		/**
		 * Hooks the migration into the login sequence.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function init() {
			/*
			 * wp_authenticate_user fires inside wp_authenticate_username_password(), which is
			 * itself the 'authenticate' filter at priority 20. Wordfence challenges from the
			 * same filter at 25 and WP 2FA at 50, so this runs after the password has been
			 * checked but before either plugin decides to ask for a second factor — the one
			 * point where we can migrate and have WP 2FA pick the result up in the same request.
			 */
			\add_filter( 'wp_authenticate_user', array( __CLASS__, 'maybe_migrate_on_login' ), 5, 2 );

			/*
			 * Wordfence's login form asks a second, separate question over AJAX before it will
			 * submit, and that question does not run through the authenticate filter at all.
			 * Late on init, so its own handler is registered by the time we look for it.
			 */
			\add_action( 'init', array( __CLASS__, 'maybe_proxy_source_preflight' ), \PHP_INT_MAX );
		}

		/**
		 * Whether the source plugin is on this site.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_source_plugin_active(): bool {
			$active_plugins = (array) \get_option( 'active_plugins', array() );

			if ( \in_array( self::SOURCE_PLUGIN, $active_plugins, true ) ) {
				return true;
			}

			if ( ! \is_multisite() ) {
				return false;
			}

			$network_plugins = (array) \get_site_option( 'active_sitewide_plugins', array() );

			return isset( $network_plugins[ self::SOURCE_PLUGIN ] );
		}

		/**
		 * Wordfence's enrolment table for this install.
		 *
		 * Wordfence uses base_prefix, so on multisite there is a single network-wide table
		 * rather than one per site. Using $wpdb->prefix here would read the wrong table on
		 * every subsite but the main one.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function secrets_table(): string {
			global $wpdb;

			return $wpdb->base_prefix . self::SECRETS_TABLE;
		}

		/**
		 * Whether Wordfence's table is actually present.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function table_exists(): bool {
			if ( null !== self::$table_exists ) {
				return self::$table_exists;
			}

			global $wpdb;

			$table = self::secrets_table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

			self::$table_exists = ( $found === $table );

			return self::$table_exists;
		}

		/**
		 * Whether there is anything here to migrate from.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_available(): bool {
			return self::is_source_plugin_active() && self::table_exists();
		}

		/**
		 * Whether the administrator has switched automatic migration on.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_enabled(): bool {
			return (bool) Settings_Utils::get_option( self::ENABLED_SETTING, false );
		}

		/**
		 * Turns automatic migration on or off.
		 *
		 * @param bool $enabled - The new state.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function set_enabled( bool $enabled ) {
			Settings_Utils::update_option( self::ENABLED_SETTING, $enabled );
		}

		/**
		 * Whether this user's enrolment has already been re-created here.
		 *
		 * @param int $user_id - The user to check.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_user_migrated( int $user_id ): bool {
			/*
			 * The third argument of User_Helper::get_meta() is get_user_meta()'s $single flag,
			 * not a default value — passing false here would hand back an array and make this
			 * true for every user, migrated or not.
			 */
			return ! empty( User_Helper::get_meta( self::MIGRATED_META, $user_id ) );
		}

		/**
		 * Converts a Wordfence secret into the string WP 2FA stores.
		 *
		 * Wordfence keeps the raw 20 bytes; WP 2FA keeps them base32-encoded and, where
		 * OpenSSL is available, encrypted behind a prefix. Both use the RFC 4648 alphabet,
		 * and 20 bytes encode to exactly 32 characters with no padding, so the result matches
		 * what WP 2FA's own generator produces.
		 *
		 * @param string $raw_secret - The raw bytes as Wordfence stored them.
		 *
		 * @return string The value to store, or '' if the input was not a usable secret.
		 *
		 * @since 4.2.0
		 */
		public static function convert_secret( string $raw_secret ): string {
			if ( self::SECRET_BYTES !== \strlen( $raw_secret ) ) {
				return '';
			}

			$base32 = Authentication::base32_encode( $raw_secret );

			if ( '' === $base32 ) {
				return '';
			}

			$stored = Open_SSL::encrypt( $base32 );

			if ( Open_SSL::is_ssl_available() ) {
				$stored = Open_SSL::SECRET_KEY_PREFIX . $stored;
			}

			/*
			 * Prove the value round-trips before it is written anywhere.
			 *
			 * TOTP::get_user_totp_key_auth() quietly replaces a key it cannot read with a
			 * freshly generated one, so a malformed import would not raise an error — it
			 * would silently issue the user a secret their authenticator app does not know,
			 * and lock them out of an account that was working a moment earlier.
			 */
			$check = $stored;

			if ( ! Authentication::is_valid_key( $check ) ) {
				Authentication::clear_decrypted_key();

				return '';
			}

			Authentication::clear_decrypted_key();

			return $stored;
		}

		/**
		 * Converts Wordfence's recovery blob into the codes the user was given.
		 *
		 * Wordfence concatenates its recovery codes into a single blob with no delimiter, eight
		 * raw bytes each, and shows them hex-encoded as four groups of four characters. Splitting
		 * on that fixed width and hex-encoding each chunk reproduces exactly what was printed.
		 *
		 * Only unused codes are present: Wordfence rewrites the blob without a code once it has
		 * been spent, so whatever is left is what the user can still redeem.
		 *
		 * @param string $blob - The recovery column as Wordfence stored it.
		 *
		 * @return string[] The remaining codes, or an empty array if the blob is not usable.
		 *
		 * @since 4.2.0
		 */
		public static function convert_recovery_codes( string $blob ): array {
			$length = \strlen( $blob );

			/*
			 * A partial trailing chunk means this is not a recovery blob of the shape we expect.
			 * Importing half a code would hand the user something that can never be redeemed, so
			 * the whole blob is refused rather than guessed at.
			 */
			if ( 0 === $length || 0 !== $length % self::RECOVERY_CODE_BYTES ) {
				return array();
			}

			$codes = array();

			foreach ( \str_split( $blob, self::RECOVERY_CODE_BYTES ) as $chunk ) {
				$codes[] = \strtolower( \bin2hex( $chunk ) );
			}

			return $codes;
		}

		/**
		 * Brings across whatever recovery codes the user has left.
		 *
		 * Stored the way WP 2FA stores its own backup codes — hashed with wp_hash_password() —
		 * so the codes already on the user's printout keep working while nothing redeemable is
		 * written to the database in the clear.
		 *
		 * Failing here is deliberately not fatal to the migration. The second factor itself has
		 * already been re-created by this point, and losing a spare code is a far smaller harm
		 * than abandoning the enrolment and leaving the user on a discontinued plugin.
		 *
		 * @param \WP_User $user - The user being migrated.
		 * @param string   $blob - The recovery column as Wordfence stored it.
		 *
		 * @return int How many codes were imported.
		 *
		 * @since 4.2.0
		 */
		public static function migrate_recovery_codes( $user, string $blob ): int {
			if ( ! \is_a( $user, '\WP_User' ) || 0 === (int) $user->ID ) {
				return 0;
			}

			// Do not hand a user a method the policy does not allow for their role.
			if ( ! Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user ), Backup_Codes::get_method_name() ) ) {
				return 0;
			}

			/*
			 * Never replace backup codes the user already holds here. Overwriting them would
			 * silently invalidate a sheet of codes they may be relying on.
			 */
			$existing = \get_user_meta( $user->ID, Backup_Codes::BACKUP_CODES_META_KEY, true );

			if ( ! empty( $existing ) ) {
				return 0;
			}

			$codes = self::convert_recovery_codes( $blob );

			if ( empty( $codes ) ) {
				return 0;
			}

			$hashed = array();

			foreach ( $codes as $code ) {
				$hashed[] = \wp_hash_password( $code );
			}

			\update_user_meta( $user->ID, Backup_Codes::BACKUP_CODES_META_KEY, $hashed );

			return \count( $hashed );
		}

		/**
		 * Reads a user's Wordfence enrolment.
		 *
		 * @param int $user_id - The user to read.
		 *
		 * @return array|null The row, or null when the user has no Wordfence enrolment.
		 *
		 * @since 4.2.0
		 */
		public static function get_enrolment( int $user_id ) {
			global $wpdb;

			$table = self::secrets_table();

			/*
			 * The table name cannot be bound as a parameter, so it is built from base_prefix
			 * and a class constant and never from request data.
			 */
			$previous_suppress_errors = $wpdb->suppress_errors();

			try {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$row = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT `user_id`, `secret`, `recovery`, `ctime`, `mode` FROM `' . $table . '` WHERE `user_id` = %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$user_id
					),
					ARRAY_A
				);
			} finally {
				$wpdb->suppress_errors( $previous_suppress_errors );
			}

			return empty( $row ) ? null : $row;
		}

		/**
		 * Re-creates one user's TOTP enrolment in WP 2FA.
		 *
		 * @param \WP_User $user - The user to migrate.
		 *
		 * @return bool Whether an enrolment was created.
		 *
		 * @since 4.2.0
		 */
		public static function migrate_user( $user ): bool {
			try {
				return self::migrate_user_enrolment( $user );
			} catch ( \Throwable $exception ) {
				Debugging::log( 'Wordfence Login Security migration failed: ' . $exception->getMessage() );

				return false;
			}
		}

		/**
		 * Re-creates one user's TOTP enrolment after the public error boundary.
		 *
		 * @param \WP_User $user - The user to migrate.
		 *
		 * @return bool Whether an enrolment was created.
		 *
		 * @since 4.2.0
		 */
		private static function migrate_user_enrolment( $user ): bool {
			if ( ! \is_a( $user, '\WP_User' ) || 0 === (int) $user->ID ) {
				return false;
			}

			$user_id       = (int) $user->ID;
			$was_migrated  = self::is_user_migrated( $user_id );
			$current_method = (string) User_Helper::get_enabled_method_for_user( $user_id );

			/*
			 * A marked user with a usable WP 2FA enrolment is already done. A different
			 * method that was configured independently must never be overwritten.
			 */
			if ( ( $was_migrated && self::has_usable_wp2fa_enrolment( $user_id ) ) || ( '' !== $current_method && TOTP::METHOD_NAME !== $current_method ) ) {
				return false;
			}

			$row = self::get_enrolment( $user_id );

			if ( null === $row || empty( $row['secret'] ) ) {
				return false;
			}

			/*
			 * Wordfence's column is an enum that only ever holds 'authenticator', but it is
			 * the field that says what the blob is, so it is worth honouring rather than
			 * assuming.
			 */
			if ( isset( $row['mode'] ) && 'authenticator' !== $row['mode'] ) {
				return false;
			}

			// Do not hand a user a method the policy does not allow for their role.
			if ( ! Settings::is_provider_enabled_for_role( User_Helper::get_user_role( $user ), TOTP::METHOD_NAME ) ) {
				return false;
			}

			$secret = self::convert_secret( (string) $row['secret'] );

			if ( '' === $secret ) {
				return false;
			}

			/*
			 * A prior attempt may have stored the method but failed to store its marker.
			 * Recognize that exact enrolment and retry the marker without rotating a key.
			 */
			if ( TOTP::METHOD_NAME === $current_method && self::secrets_match( (string) TOTP::get_user_totp_key( $user_id ), $secret ) ) {
				self::migrate_recovery_codes( $user, (string) ( $row['recovery'] ?? '' ) );

				User_Helper::set_meta( self::MIGRATED_META, \time(), $user_id );

				return self::is_user_migrated( $user_id );
			}

			// An unmarked TOTP enrolment belongs to WP 2FA and must not be replaced.
			if ( TOTP::METHOD_NAME === $current_method && ! $was_migrated ) {
				return false;
			}

			/*
			 * Persist and verify the key before enabling the method. set_user_method() does
			 * those writes in the opposite order and exposes no result, which could turn a
			 * failed key write into a lockout if Wordfence were then suppressed.
			 */
			TOTP::set_user_totp_key( $secret, $user );

			if ( $secret !== (string) TOTP::get_user_totp_key( $user_id ) ) {
				return false;
			}

			TOTP::set_user_method( $user, $secret );

			if ( TOTP::METHOD_NAME !== User_Helper::get_enabled_method_for_user( $user_id ) || $secret !== (string) TOTP::get_user_totp_key( $user_id ) ) {
				return false;
			}

			self::migrate_recovery_codes( $user, (string) ( $row['recovery'] ?? '' ) );

			User_Helper::set_meta( self::MIGRATED_META, \time(), $user_id );

			if ( ! self::is_user_migrated( $user_id ) ) {
				return false;
			}

			/**
			 * Fires once a user's Wordfence enrolment has been re-created in WP 2FA.
			 *
			 * @param \WP_User $user - The migrated user.
			 *
			 * @since 4.1.0
			 */
			\do_action( WP_2FA_PREFIX . 'wordfence_user_migrated', $user );

			return true;
		}

		/**
		 * Migrates the user logging in, then stops Wordfence challenging them.
		 *
		 * @param \WP_User|\WP_Error $user     - The user being authenticated.
		 * @param string             $password - The submitted password, unused.
		 *
		 * @return \WP_User|\WP_Error The untouched authentication result.
		 *
		 * @since 4.2.0
		 */
		public static function maybe_migrate_on_login( $user, $password = '' ) {
			// Never interfere with the authentication result itself.
			if ( ! \is_a( $user, '\WP_User' ) ) {
				return $user;
			}

			try {
				if ( ! self::is_enabled() || ! self::is_source_plugin_active() ) {
					return $user;
				}

				$already = self::is_user_migrated( (int) $user->ID );

				if ( ! $already || ! self::has_usable_wp2fa_enrolment( (int) $user->ID ) ) {
					$already = self::migrate_user( $user );
				}

				/*
				 * The marker alone is not enough: an administrator may have reset or removed the
				 * WP 2FA enrolment after migration. Keep Wordfence active unless the replacement
				 * enrolment is still usable in this request.
				 */
				if ( $already && self::has_usable_wp2fa_enrolment( (int) $user->ID ) ) {
					self::suppress_source_challenge( (int) $user->ID );
				}
			} catch ( \Throwable $exception ) {
				Debugging::log( 'Wordfence Login Security login migration failed: ' . $exception->getMessage() );
			}

			return $user;
		}

		/**
		 * Whether WP 2FA has a usable replacement enrolment for this user.
		 *
		 * @param int $user_id - The user to inspect.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function has_usable_wp2fa_enrolment( int $user_id ): bool {
			$method = (string) User_Helper::get_enabled_method_for_user( $user_id );

			if ( '' === $method ) {
				return false;
			}

			if ( TOTP::METHOD_NAME !== $method ) {
				return true;
			}

			$key = (string) TOTP::get_user_totp_key( $user_id );

			try {
				Authentication::decrypt_key_if_needed( $key );

				return 32 === \strlen( $key ) && Authentication::validate_base32_string( $key );
			} catch ( \Throwable $exception ) {
				return false;
			} finally {
				Authentication::clear_decrypted_key();
			}
		}

		/**
		 * Whether two stored WP 2FA secrets contain the same base32 key.
		 *
		 * @param string $first  - The first stored secret.
		 * @param string $second - The second stored secret.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function secrets_match( string $first, string $second ): bool {
			try {
				Authentication::decrypt_key_if_needed( $first );
				Authentication::decrypt_key_if_needed( $second );

				return 32 === \strlen( $first ) && \hash_equals( $first, $second );
			} catch ( \Throwable $exception ) {
				return false;
			} finally {
				Authentication::clear_decrypted_key();
			}
		}

		/**
		 * Routes Wordfence's second-factor prompt around migrated users in this request.
		 *
		 * Wordfence Login Security offers no filter for this, so the only way to avoid
		 * challenging a migrated user twice is to unhook the callback. It is registered on
		 * 'authenticate' at priority 25 from a public method on a singleton, and we are called
		 * from priority 20, so the removal lands before it would have run — WordPress supports
		 * removing a later callback while an earlier one is executing.
		 *
		 * Reaching into another plugin's hooks is normally the wrong instinct. It is defensible
		 * here only because that plugin is discontinued and will not be changing underneath us,
		 * and because every step is guarded: if the class, the singleton or the hook is not
		 * what we expect, we leave the challenge alone and the user simply sees both prompts.
		 *
		 * @param int $user_id - The migrated user whose challenge should be skipped.
		 *
		 * @return bool Whether the challenge was suppressed for the user.
		 *
		 * @since 4.2.0
		 */
		public static function suppress_source_challenge( int $user_id ): bool {
			if ( $user_id <= 0 ) {
				return false;
			}

			$proxy = array( __CLASS__, 'filter_source_authentication' );

			if ( false !== \has_filter( 'authenticate', $proxy ) ) {
				self::$suppressed_user_ids[ $user_id ] = true;

				return true;
			}

			$controller = '\WordfenceLS\Controller_WordfenceLS';

			if ( ! \class_exists( $controller ) || ! \method_exists( $controller, 'shared' ) ) {
				return false;
			}

			$instance = \call_user_func( array( $controller, 'shared' ) );

			if ( ! \is_object( $instance ) || ! \method_exists( $instance, '_authenticate' ) ) {
				return false;
			}

			$callback = array( $instance, '_authenticate' );
			$priority = \has_filter( 'authenticate', $callback );

			if ( false === $priority || ! \remove_filter( 'authenticate', $callback, $priority ) ) {
				return false;
			}

			self::$source_authenticate_callback       = $callback;
			self::$suppressed_user_ids[ $user_id ]    = true;
			\add_filter( 'authenticate', $proxy, $priority, 3 );

			return true;
		}

		/**
		 * Runs Wordfence's original authentication callback except for migrated users.
		 *
		 * @param mixed  $user     - The authentication result so far.
		 * @param string $username - The submitted username.
		 * @param string $password - The submitted password.
		 *
		 * @return mixed The authentication result.
		 *
		 * @since 4.2.0
		 */
		public static function filter_source_authentication( $user, $username = '', $password = '' ) {
			if ( \is_a( $user, '\WP_User' ) && isset( self::$suppressed_user_ids[ (int) $user->ID ] ) ) {
				return $user;
			}

			if ( \is_callable( self::$source_authenticate_callback ) ) {
				return \call_user_func( self::$source_authenticate_callback, $user, $username, $password );
			}

			return $user;
		}

		/**
		 * Takes over Wordfence's login preflight for migrated users.
		 *
		 * Wordfence's login form does not simply submit. Its JavaScript first asks
		 * admin-ajax.php whether the credentials need a second factor, and that handler answers
		 * from Controller_Users::has_2fa_active(), which reads its own table directly. It never
		 * consults the authenticate filter, so unhooking the filter — which is enough for a
		 * plain form post — leaves the browser still being told to ask for a Wordfence code.
		 *
		 * Rather than delete the row that answer is derived from, the handler is proxied: for a
		 * user we have already migrated we give the same reply Wordfence gives for someone with
		 * no second factor, and for everybody else we hand the request straight back to it.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function maybe_proxy_source_preflight() {
			if ( ! \wp_doing_ajax() ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = isset( $_REQUEST['action'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['action'] ) ) : '';

			if ( self::SOURCE_PREFLIGHT_ACTION !== $action ) {
				return;
			}

			if ( ! self::is_enabled() || ! self::is_source_plugin_active() ) {
				return;
			}

			$controller = '\WordfenceLS\Controller_AJAX';

			if ( ! \class_exists( $controller ) || ! \method_exists( $controller, 'shared' ) ) {
				return;
			}

			$instance = \call_user_func( array( $controller, 'shared' ) );

			if ( ! \is_object( $instance ) || ! \method_exists( $instance, '_ajax_handler' ) ) {
				return;
			}

			$original = array( $instance, '_ajax_handler' );

			foreach ( array( 'wp_ajax_nopriv_', 'wp_ajax_' ) as $prefix ) {
				$hook     = $prefix . self::SOURCE_PREFLIGHT_ACTION;
				$priority = \has_action( $hook, $original );

				if ( false === $priority ) {
					continue;
				}

				\remove_action( $hook, $original, $priority );

				self::$source_preflight_handler = $original;

				\add_action( $hook, array( __CLASS__, 'filter_source_preflight' ), $priority );
			}
		}

		/**
		 * Answers Wordfence's preflight for a migrated user, and defers for everyone else.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function filter_source_preflight() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$username = isset( $_POST['log'] ) ? \wp_unslash( $_POST['log'] ) : ( isset( $_POST['username'] ) ? \wp_unslash( $_POST['username'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			$user = \is_string( $username ) && '' !== $username ? \get_user_by( 'login', \sanitize_user( $username ) ) : false;

			if ( ! $user ) {
				$user = \is_string( $username ) && '' !== $username ? \get_user_by( 'email', \sanitize_email( $username ) ) : false;
			}

			// Use the normal authentication filters before telling Wordfence to submit the login.
			// This also migrates a user on their first valid password through the authentication hook.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
			$password = isset( $_POST['pwd'] ) ? \wp_unslash( $_POST['pwd'] ) : ( isset( $_POST['password'] ) ? \wp_unslash( $_POST['password'] ) : '' );

			if ( $user && \is_string( $password ) && '' !== $password ) {
				$authenticated = \wp_authenticate( $user->user_login, $password );

				if (
					$authenticated instanceof \WP_User
					&& (int) $authenticated->ID === (int) $user->ID
					&& self::is_user_migrated( (int) $user->ID )
					&& self::has_usable_wp2fa_enrolment( (int) $user->ID )
				) {
					\wp_send_json( array( 'login' => 1 ) );

					return;
				}
			}

			if ( \is_callable( self::$source_preflight_handler ) ) {
				\call_user_func( self::$source_preflight_handler );
			}
		}

		/**
		 * How many users Wordfence has an enrolment for.
		 *
		 * @return int
		 *
		 * @since 4.2.0
		 */
		public static function source_user_count(): int {
			if ( ! self::table_exists() ) {
				return 0;
			}

			global $wpdb;

			$table = self::secrets_table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' );
		}

		/**
		 * How many of those users we have migrated.
		 *
		 * Counted against Wordfence's own table rather than against every user with the meta
		 * key, so the two figures on the status screen always add up to the same total.
		 *
		 * @return int
		 *
		 * @since 4.2.0
		 */
		public static function migrated_user_count(): int {
			if ( ! self::table_exists() ) {
				return 0;
			}

			global $wpdb;

			$table = self::secrets_table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . $table . '` w INNER JOIN `' . $wpdb->usermeta . '` m ON m.user_id = w.user_id AND m.meta_key = %s', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					self::MIGRATED_META
				)
			);
		}

		/**
		 * How many users are still only enrolled with Wordfence.
		 *
		 * @return int
		 *
		 * @since 4.2.0
		 */
		public static function pending_user_count(): int {
			return \max( 0, self::source_user_count() - self::migrated_user_count() );
		}

		/**
		 * Whether every Wordfence enrolment has been re-created here.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		public static function is_complete(): bool {
			$total = self::source_user_count();

			return $total > 0 && self::migrated_user_count() >= $total;
		}
	}
}
