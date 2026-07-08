<?php
/**
 * Upgrade Guard - prevents fatal errors during plugin version transitions.
 *
 * When WordPress updates a plugin, file replacement is not atomic. This means:
 * 1. OPcache may serve stale bytecode from the old version's files.
 * 2. Serialized objects in the DB may reference classes/methods that no longer exist.
 * 3. Scheduled cron hooks may point to removed callbacks.
 * 4. The autoloader classmap may reference files that have been renamed or deleted.
 *
 * This class mitigates these issues by:
 * - Invalidating OPcache for the plugin directory after an update.
 * - Detecting version transitions and running in a degraded "safe mode".
 * - Providing a custom error handler during autoload to catch missing file errors.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @since 4.0.0
 */

declare(strict_types=1);

namespace WP2FA\Utils;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP2FA\Utils\Upgrade_Guard' ) ) {

	/**
	 * Handles safe plugin transitions during updates.
	 *
	 * @since 4.0.0
	 */
	class Upgrade_Guard {

		/**
		 * Option name storing the last known file-version hash.
		 *
		 * @var string
		 */
		private const VERSION_HASH_OPTION = 'wp_2fa_file_version_hash';

		/**
		 * Transient flag set during an active upgrade transition.
		 *
		 * @var string
		 */
		private const UPGRADING_TRANSIENT = 'wp_2fa_upgrading';

		/**
		 * Whether safe mode is currently active.
		 *
		 * @var bool
		 */
		private static $safe_mode = false;

		/**
		 * Initialize the upgrade guard.
		 *
		 * Must be called BEFORE the Composer autoloader is loaded,
		 * so it can register the error handler and detect transitions.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function init(): void {
			// Register a custom error handler for autoload failures.
			self::register_autoload_error_handler();

			// Hook into the upgrader to clear caches after plugin update.
			\add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrader_complete' ), 5, 2 );

			// Also handle updates via direct file replacement (e.g., WP Playground, WP-CLI).
			self::detect_version_transition();
		}

		/**
		 * Check if the plugin is currently in safe mode (mid-upgrade transition).
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		public static function is_safe_mode(): bool {
			return self::$safe_mode;
		}

		/**
		 * Detect a version transition by comparing file version to stored hash.
		 *
		 * If the file version changed but the migration hasn't run yet,
		 * we're in a transitional state where classes may be inconsistent.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function detect_version_transition(): void {
			// filemtime() can return false if the file is being replaced mid-update.
			$mtime = @filemtime( WP_2FA_FILE ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $mtime ) {
				// File is actively being replaced — enable safe mode, skip further checks.
				self::$safe_mode = true;
				return;
			}

			$current_hash = md5( WP_2FA_VERSION . '|' . $mtime );
			$stored_hash  = \get_option( self::VERSION_HASH_OPTION, '' );

			if ( $stored_hash !== $current_hash ) {
				// Version transition detected — invalidate caches.
				self::invalidate_opcache();
				\update_option( self::VERSION_HASH_OPTION, $current_hash, true );

				// Check if migration has already run for this version.
				// Use get_option directly — Settings_Utils is not available yet (autoloader hasn't loaded).
				$stored_version = (string) \get_option( 'wp_2fa_plugin_version', '0.0.0' );
				if ( version_compare( $stored_version, WP_2FA_VERSION, '<' ) ) {
					// Migration hasn't run yet — we're in a transitional state.
					self::$safe_mode = true;
					\set_transient( self::UPGRADING_TRANSIENT, '1', 60 );
				}
			} elseif ( \get_transient( self::UPGRADING_TRANSIENT ) ) {
				self::$safe_mode = true;
			}
		}

		/**
		 * Callback for upgrader_process_complete.
		 *
		 * Invalidates OPcache and marks the plugin as transitioning
		 * when this plugin is being updated.
		 *
		 * @param \WP_Upgrader $upgrader Upgrader instance.
		 * @param array        $options  Update options with type and plugins/themes.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function on_upgrader_complete( $upgrader, $options ): void {
			if ( 'update' !== ( $options['action'] ?? '' ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
				return;
			}

			$plugins = $options['plugins'] ?? array();
			if ( ! is_array( $plugins ) ) {
				$plugins = array( $plugins );
			}

			$our_basename = \plugin_basename( WP_2FA_FILE );
			if ( ! in_array( $our_basename, $plugins, true ) ) {
				return;
			}

			// Plugin was just updated — flush OPcache for our directory.
			self::invalidate_opcache();

			// Set a short-lived transient to signal the next request that we're transitioning.
			\set_transient( self::UPGRADING_TRANSIENT, '1', 60 );

			// Clear config hash so the next load detects the new version.
			\delete_option( self::VERSION_HASH_OPTION );

			// Clear any cached class references.
			\delete_transient( 'wp_2fa_config_file_hash' );

			// Record whether this was a manual (single plugin) update for the new interface dialog.
			$is_manual_update = ! ( defined( 'WP_CLI' ) && WP_CLI ) && empty( $options['bulk'] );
			\set_transient( 'wp_2fa_manual_update', $is_manual_update ? '1' : '0', 300 );
		}

		/**
		 * Invalidate OPcache for the entire plugin directory.
		 *
		 * OPcache caches compiled PHP bytecode. After a plugin update,
		 * stale bytecode from old files causes "class not found" or
		 * "method not found" fatals because the cached code references
		 * the old file structure.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function invalidate_opcache(): void {
			if ( ! function_exists( 'opcache_invalidate' ) ) {
				return;
			}

			if ( function_exists( 'opcache_reset' ) && ! self::is_opcache_file_invalidation_available() ) {
				// If per-file invalidation isn't available, reset the entire cache.
				// This is a last resort — it affects all PHP files on the server.
				\opcache_reset();
				return;
			}

			// Invalidate all PHP files in the plugin directory.
			$plugin_dir = WP_2FA_PATH;
			self::invalidate_directory( $plugin_dir );
		}

		/**
		 * Check if OPcache supports per-file invalidation.
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		private static function is_opcache_file_invalidation_available(): bool {
			// OPcache is typically disabled for CLI (opcache.enable_cli=0).
			if ( 'cli' === PHP_SAPI && ! filter_var( ini_get( 'opcache.enable_cli' ), FILTER_VALIDATE_BOOLEAN ) ) {
				return false;
			}

			// opcache.restrict_api can prevent invalidation from certain paths.
			$restrict_api = (string) ini_get( 'opcache.restrict_api' );
			if ( '' !== $restrict_api ) {
				// Check if current script path starts with the restriction.
				// In CLI/WP-CLI, SCRIPT_FILENAME may be absent or point to the phar.
				$script_path = $_SERVER['SCRIPT_FILENAME'] ?? '';
				if ( '' === $script_path || 0 !== strpos( $script_path, $restrict_api ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Recursively invalidate OPcache for all PHP files in a directory.
		 *
		 * @param string $dir Directory path.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function invalidate_directory( string $dir ): void {
			// Canonicalize to prevent symlink traversal outside the plugin boundary.
			$real_dir = realpath( $dir );
			if ( false === $real_dir || ! is_dir( $real_dir ) ) {
				return;
			}

			try {
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $real_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);

				foreach ( $iterator as $file ) {
					if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
						continue;
					}

					// Skip symlinks that resolve outside the plugin directory.
					$real_path = $file->getRealPath();
					if ( false === $real_path || 0 !== strpos( $real_path, $real_dir ) ) {
						continue;
					}

					\opcache_invalidate( $real_path, true );
				}
			} catch ( \UnexpectedValueException $e ) {
				// Directory structure changed mid-iteration (files being replaced).
				// Silently bail — OPcache will be invalidated on next request.
				return;
			}
		}

		/**
		 * Register a custom autoload error handler.
		 *
		 * Catches "file not found" or "class not found" issues during
		 * autoloading that happen when the classmap references stale paths.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		private static function register_autoload_error_handler(): void {
			// Register a shutdown function to catch fatal autoload errors.
			\register_shutdown_function( array( __CLASS__, 'handle_fatal_autoload_error' ) );
		}

		/**
		 * Handle fatal errors caused by stale autoloader references.
		 *
		 * If a fatal error occurs because a class file was removed during
		 * an update, log the error and attempt to redirect to a safe state
		 * rather than showing a white screen.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public static function handle_fatal_autoload_error(): void {
			$error = error_get_last();
			if ( null === $error ) {
				return;
			}

			// Only handle fatal errors.
			if ( ! in_array( $error['type'], array( E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR ), true ) ) {
				return;
			}

			// Check if it's related to our plugin.
			if ( false === strpos( $error['file'], 'wp-2fa' ) && false === strpos( $error['message'], 'WP2FA' ) ) {
				return;
			}

			// Check if it's a class/file not found error typical of version transitions.
			$transition_patterns = array(
				'Class .* not found',
				'Failed opening required.*wp-2fa',
				'include\(\).*wp-2fa.*failed to open stream',
				'Call to undefined method.*WP2FA',
				'Interface .* not found',
				'Trait .* not found',
			);

			$is_transition_error = false;
			foreach ( $transition_patterns as $pattern ) {
				if ( preg_match( '/' . $pattern . '/i', $error['message'] ) ) {
					$is_transition_error = true;
					break;
				}
			}

			if ( ! $is_transition_error ) {
				return;
			}

			// Log the error for debugging.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[WP 2FA Upgrade Guard] Caught transition error: %s in %s on line %d. '
						. 'This typically resolves on the next page load after the update completes.',
						$error['message'],
						$error['file'],
						$error['line']
					)
				);
			}

			// Invalidate OPcache to help the next request succeed.
			if ( function_exists( 'opcache_invalidate' ) && isset( $error['file'] ) ) {
				\opcache_invalidate( $error['file'], true );
			}

			// Set the upgrading transient so the next request knows.
			// Wrapped in try-catch: during fatal shutdown the DB connection may be gone.
			if ( function_exists( 'set_transient' ) ) {
				try {
					\set_transient( self::UPGRADING_TRANSIENT, '1', 60 );
				} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					// Best-effort; not critical if it fails.
				}
			}

			// If we're in an admin HTTP context, show a "retry" page instead of a white screen.
			// Skip for CLI (WP-CLI / cron) — no browser to refresh.
			if ( defined( 'WP_CLI' ) || 'cli' === PHP_SAPI ) {
				// In CLI context, just let the error propagate naturally — the user
				// will see the error message in their terminal and can retry.
				return;
			}

			if ( ! headers_sent() && defined( 'WP_ADMIN' ) && WP_ADMIN ) {
				// Clear output buffer if any.
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}

				// Send a "retry" response that tells the browser to try again.
				http_response_code( 503 );
				header( 'Retry-After: 2' );
				header( 'Content-Type: text/html; charset=utf-8' );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'X-Frame-Options: DENY' );
				header( 'Cache-Control: no-store, no-cache, must-revalidate' );
				echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="2"></head>';
				// Use a hardcoded string — esc_html__() may not be loaded during fatal shutdown.
				echo '<body><p>WP 2FA is updating. The page will reload momentarily&hellip;</p></body></html>';
			}
		}

		/**
		 * Safely check if a class method exists before it's called via a hook.
		 *
		 * Use this when registering callbacks that reference methods which may
		 * be renamed or removed in future versions.
		 *
		 * @param string $class_name  Fully-qualified class name.
		 * @param string $method_name Method name.
		 *
		 * @return bool
		 *
		 * @since 4.0.0
		 */
		public static function method_available( string $class_name, string $method_name ): bool {
			return class_exists( $class_name, true ) && method_exists( $class_name, $method_name );
		}
	}
}
