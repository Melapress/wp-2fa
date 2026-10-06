<?php
/**
 * Responsible for logging.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 * @since      1.4.2
 */

declare(strict_types=1);

namespace WP2FA\Utils;

use WP2FA\Admin\Helpers\File_Writer;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Utils\Debugging' ) ) {
	/**
	 * Utility class for creating modal popup markup.
	 *
	 * @package WP2FA\Utils
	 *
	 * @since 1.4.2
	 */
	class Debugging {

		public const CLEANUP_HOOK = 'wp_2fa_debug_log_cleanup';

		public const LOG_RETENTION = 7 * DAY_IN_SECONDS;

		/**
		 * Local cache for the logging dir so that it doesn't need to be repopulated each time get_logging_dir_path is called.
		 *
		 * @var string
		 *
		 * @since 1.4.2
		 */
		private static $logging_dir_path = '';

		/**
		 * Lines logged during this request that are waiting to be written, by
		 * file name. Only used when the log cannot be appended to directly.
		 *
		 * @var array<string,string>
		 *
		 * @since 4.2.0
		 */
		private static $pending = array();

		/**
		 * Registers debug log maintenance.
		 *
		 * @return void
		 */
		public static function init() {
			/*
			 * Only while there is something to look after. This scheduled an hourly
			 * event on every install, although logging is off unless a site turns
			 * it on. An event already scheduled is left to run: cleanup_logs()
			 * unschedules it once logging is off and no log is left, so logs kept
			 * from a time logging was on still expire.
			 */
			if ( self::is_logging_enabled() && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CLEANUP_HOOK );
			}

			add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_logs' ) );
		}

		/**
		 * Retrieve the logging status
		 *
		 * @return boolean
		 *
		 * @since 1.4.2
		 */
		private static function is_logging_enabled() {
			/**
			 * Enables / Disables the logging for the plugin.
			 *
			 * @param bool $disabled - Default logging for the plugin.
			 */
			return apply_filters( WP_2FA_PREFIX . 'logging_enabled', false );
		}

		/**
		 * Logs the given message
		 *
		 * @param string $message - The message to log.
		 *
		 * @return void
		 *
		 * @since 1.4.2
		 */
		public static function log( $message ) {
			if ( self::is_logging_enabled() ) {
				self::write_to_log(
					self::get_log_timestamp() . "\n" . sanitize_text_field( $message ) . "\n" . sprintf(
					/* translators: %s: the current memory usage in bytes. */
						__( 'Current memory usage: %s', 'wp-2fa' ),
						memory_get_usage( true )
					) . "\n"
				);
			}
		}

		/**
		 * Retrieves the path to the log file
		 *
		 * @return string
		 *
		 * @since 1.4.2
		 */
		private static function get_logging_dir_path() {
			if ( strlen( self::$logging_dir_path ) === 0 ) {
				$uploads_dir            = wp_upload_dir( null, false );
				self::$logging_dir_path = trailingslashit( trailingslashit( $uploads_dir['basedir'] ) . WP_2FA_LOGS_DIR );
			}

			return self::$logging_dir_path;
		}

		/**
		 * Write data to log file.
		 *
		 * @param string $data     - Data to write to file.
		 * @param bool   $override - Set to true if overriding the file.
		 *
		 * @return bool
		 *
		 * @since 1.4.2
		 */
		private static function write_to_log( $data, $override = false ) {
			static $maintained = false;

			/*
			 * Once per request, not once per line. Both used to run for every line
			 * logged: a glob over the directory and a check of the protection files
			 * each time.
			 *
			 * The protection files are still checked on every request that logs,
			 * not only when the directory is being made. They are what stands
			 * between a log and the open web, and a directory that has lost them -
			 * to a botched restore, a sync, a tidy-up - would otherwise stay
			 * unprotected for as long as the site keeps logging into it.
			 */
			if ( ! $maintained ) {
				$maintained = true;
				self::cleanup_logs();
				self::ensure_directory_protection();
			}

			/*
			 * A log is about to exist, so something has to expire it - on Nginx the
			 * protection files do nothing and a log is only safe once it is gone.
			 * init() only schedules while logging is on at that moment, which misses
			 * logging switched on later by a theme's filter. This reads the cron
			 * array already in memory, so it is cheap enough to ask every time.
			 */
			if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CLEANUP_HOOK );
			}

			$log_file_name = gmdate( 'Y-m-d' );

			return self::write_to_file( 'wp-2fa-debug-' . $log_file_name . '-' . self::get_random_file_string_addon() . '.log', $data, $override );
		}

		/**
		 * Deletes debug logs older than the configured retention period.
		 *
		 * @return void
		 */
		public static function cleanup_logs() {
			$logging_dir = self::get_logging_dir_path();
			if ( ! is_dir( $logging_dir ) ) {
				return;
			}

			$retention = (int) apply_filters( WP_2FA_PREFIX . 'debug_log_retention', self::LOG_RETENTION );
			$files     = glob( $logging_dir . 'wp-2fa-debug-*.log' );

			if ( ! is_array( $files ) ) {
				return;
			}

			$remaining = 0;

			foreach ( $files as $file ) {
				$modified = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false !== $modified && $retention >= 0 && $modified + $retention <= time() ) {
					wp_delete_file( $file );
				} else {
					++$remaining;
				}
			}

			// Nothing left to expire and nothing being written: stop waking up.
			if ( 0 === $remaining && ! self::is_logging_enabled() ) {
				wp_clear_scheduled_hook( self::CLEANUP_HOOK );
			}
		}

		/**
		 * Puts the files that keep this directory off the open web where they belong.
		 *
		 * Written only when missing or out of date. write_to_file() appends to a file that is
		 * already there, so calling it unconditionally would repeat the rule on every log line
		 * until the file was mostly deny directives.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function ensure_directory_protection(): bool {
			global $wp_filesystem;

			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			$dir = self::get_logging_dir_path();

			if ( ! is_dir( $dir ) && false === wp_mkdir_p( $dir ) ) {
				return false;
			}

			$expected = array(
				'.htaccess'  => File_Writer::htaccess_contents(),
				'web.config' => File_Writer::webconfig_contents(),
				'index.php'  => "<?php\n// Silence is golden.",
			);

			$ok = true;

			foreach ( $expected as $name => $contents ) {
				$path = $dir . $name;

				if ( ! $wp_filesystem->exists( $path ) ) {
					$ok = $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE ) && $ok;
					continue;
				}

				/*
				 * Replaced only when it is the rule an earlier version wrote. Anything a site
				 * has put here on purpose is left as it is — on a server where these files are
				 * read, overwriting someone's own rule could open the directory rather than
				 * close it.
				 */
				if ( '.htaccess' === $name
					&& File_Writer::LEGACY_HTACCESS === trim( (string) $wp_filesystem->get_contents( $path ) ) ) {
					$ok = $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE ) && $ok;
				}
			}

			return $ok;
		}

		/**
		 * Write data to log file in the uploads directory.
		 *
		 * @param string $filename - File name.
		 * @param string $content  - Contents of the file.
		 * @param bool   $override - (Optional) True if overriding file contents.
		 *
		 * @return bool
		 *
		 * @since 1.4.2
		 */
		private static function write_to_file( $filename, $content, $override = false ) {
			global $wp_filesystem;

			if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$logging_dir = self::get_logging_dir_path();

			$result = false;

			if ( ! is_dir( $logging_dir ) ) {
				if ( false === wp_mkdir_p( $logging_dir ) ) {
					return false;
				}
			}

			$filepath = $logging_dir . $filename;
			if ( $override ) {
				$result = $wp_filesystem->put_contents( $filepath, $content, FS_CHMOD_FILE );
			} elseif ( $wp_filesystem instanceof \WP_Filesystem_Direct || \wp_is_writable( $logging_dir ) ) {
				/*
				 * A real append, for a new log as much as for an existing one.
				 * Checking for the file and then writing it let two requests both
				 * find it missing, and the second write replaced the first. An
				 * append creates the file when it is not there, and the lock keeps
				 * concurrent lines whole.
				 *
				 * The uploads directory is written by PHP itself - that is how media
				 * uploads get there - so this holds on sites whose filesystem method
				 * for code updates is FTP or SSH as well.
				 */
				$is_new = ! \file_exists( $filepath );
				$result = false !== @file_put_contents( $filepath, $content, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( $result && $is_new ) {
					@chmod( $filepath, FS_CHMOD_FILE ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod
				}
			}

			if ( ! $result && ! $override && ! $wp_filesystem instanceof \WP_Filesystem_Direct ) {
				/*
				 * PHP cannot write here itself, which leaves the FTP or SSH
				 * filesystem - no append and no lock. Reading the log and writing it back lost a
				 * line whenever two requests did it at once, and cost more with
				 * every line. So each request keeps its lines and writes them once,
				 * at the end, to a file of its own that no other request touches.
				 */
				if ( empty( self::$pending ) ) {
					\add_action( 'shutdown', array( __CLASS__, 'flush_pending' ), PHP_INT_MAX );
				}
				self::$pending[ $filename ] = ( self::$pending[ $filename ] ?? '' ) . $content;
				$result                     = true;
			}

			return $result;
		}

		/**
		 * Writes the lines this request could not append as it went.
		 *
		 * Each request gets its own file next to the day's log, named like it so
		 * that cleanup_logs() expires it with the rest.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function flush_pending() {
			global $wp_filesystem;

			$pending        = self::$pending;
			self::$pending  = array();
			$request_suffix = '-' . \strtolower( \wp_generate_password( 12, false, false ) );

			if ( empty( $pending ) || ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
				return;
			}

			foreach ( $pending as $filename => $content ) {
				$filepath = self::get_logging_dir_path() . \preg_replace( '/\.log$/', $request_suffix . '.log', $filename );
				$wp_filesystem->put_contents( $filepath, $content, FS_CHMOD_FILE );
			}
		}

		/**
		 * Returns the timestamp for log files.
		 *
		 * @return string
		 *
		 * @since 1.4.2
		 */
		private static function get_log_timestamp() {
			return '[' . gmdate( 'd-M-Y H:i:s' ) . ' UTC]';
		}

		/**
		 * Generates a short random string which is used to generate log file name.
		 *
		 * @return string
		 *
		 * @since 2.8.0
		 */
		private static function get_random_file_string_addon(): string {
			$rnd_string = Settings_Utils::get_option( 'debug_name', false );
			if ( ! $rnd_string ) {
				$rnd_string = (string) \wp_generate_password( 20, false, false );
				Settings_Utils::update_option( 'debug_name', $rnd_string );
			}

			return sanitize_text_field( $rnd_string );
		}
	}
}
