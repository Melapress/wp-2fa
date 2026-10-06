<?php
/**
 * Responsible for the File writing operations
 *
 * @package    wp2fa
 * @subpackage helpers
 * @since      2.4.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * File writer settings class
 */
if ( ! class_exists( '\WP2FA\Admin\Helpers\File_Writer' ) ) {

	/**
	 * All the file operations must go trough this class.
	 *
	 * @since 2.4.0
	 */
	class File_Writer {

		public const SECRET_NAME = 'WP2FA_ENCRYPT_KEY';

		public const WP2FA_UPLOADS_DIR = 'wp-2fa-data';

		/**
		 * The one-line rule earlier versions wrote, recognised so it can be replaced.
		 *
		 * @var string
		 *
		 * @since 4.2.0
		 */
		public const LEGACY_HTACCESS = 'Deny from all';

		/**
		 * Saves a secret key in `wp-config.php`.
		 *
		 * @param string $secret The secret key to save.
		 *
		 * @return bool
		 *
		 * @since 2.4.0
		 */
		public static function save_secret_key( string $secret ): bool {
			if ( ! self::can_write_to_file( self::get_wp_config_file_path() ) ) {
				return false;
			}

			$file     = self::get_wp_config_file_path();
			$contents = self::read( $file );

			if ( false === $contents ) {
				return false;
			}

			$secret_literal = self::build_secret_literal( $secret );

			if ( '' === $secret_literal ) {
				return false;
			}

			$definition      = "define( '" . self::SECRET_NAME . "', " . $secret_literal . ' );';
			$definition_list = preg_match_all( self::get_secret_definition_pattern(), $contents );

			if ( false === $definition_list ) {
				return false;
			}

			if ( 0 === $definition_list ) {
				if ( substr_count( $contents, self::SECRET_NAME ) ) {

					$line_ending = self::get_line_ending( $contents );

					$contents = explode( $line_ending, $contents );

					foreach ( $contents as $key => $line ) {
						if ( stristr( $line, '/** WP 2FA plugin data encryption key. ' ) ) {
							unset( $contents[ $key ] );

							continue;
						}
						if ( stristr( $line, self::SECRET_NAME ) ) {
							unset( $contents[ $key ] );

							continue;
						}
					}

					$contents = implode( $line_ending, array_values( $contents ) );
				}

				/*
				 * This used to return true whatever happened. The callers take
				 * true to mean the key is safely in wp-config.php and delete the
				 * only other copy, so a failed write lost the key outright: a new
				 * one was generated on the next request, every TOTP seed decrypted
				 * to garbage, and every TOTP user was silently re-seeded.
				 */
				if ( true !== self::write_wp_config( '/** WP 2FA plugin data encryption key. For more information please visit melapress.com */' . "\n" . $definition, $contents ) ) {
					return false;
				}

				return self::is_definition_on_disk( $file, $definition );
			}

			if ( $definition_list > 1 ) {
				return false;
			}

			$replaced = self::replace_secret_definition( $contents, $definition );

			if ( false === $replaced ) {
				return false;
			}

			$written = self::write_config_file( $file, $replaced );

			if ( true !== $written ) {
				return false;
			}

			return self::is_definition_on_disk( $file, $definition );
		}

		/**
		 * Remove the plugin's key definition during an opted-in uninstall.
		 * Failure leaves the config file intact and can be repaired manually.
		 *
		 * @return bool
		 */
		public static function remove_secret_key(): bool {
			$file = self::get_wp_config_file_path();
			if ( ! self::can_write_to_file( $file ) ) {
				return false;
			}
			$contents = self::read( $file );
			if ( ! is_string( $contents ) ) {
				return false;
			}
			$pattern = '/^[ \t]*\/\*\* WP 2FA plugin data encryption key\.[^\r\n]*\*\/[\r\n]+[ \t]*' . substr( self::get_secret_definition_pattern(), 1, -3 ) . '[ \t]*(?:\r?\n)?/mi';
			$updated = preg_replace( $pattern, '', $contents, 1, $count );
			if ( 1 !== $count || ! is_string( $updated ) ) {
				return false;
			}
			return self::write_config_file( $file, $updated );
		}

		/**
		 * Never truncate wp-config.php in place. A failed atomic replacement
		 * leaves its old contents in place and callers retain the database key.
		 *
		 * @param string $file     Config path, possibly a symlink.
		 * @param string $contents Complete replacement contents.
		 * @return bool
		 */
		private static function write_config_file( string $file, string $contents ): bool {
			$target = realpath( $file );
			if ( false === $target || ! self::is_path_allowed( $file ) || ! is_writable( $target ) ) {
				return false;
			}
			return true === self::replace_atomically( $target, $contents );
		}

		/**
		 * Whether wp-config.php, as it now stands on disk, carries the definition.
		 *
		 * A successful write is not proof: another process, a caching layer or a
		 * host that rewrites the file can all stand between the write and the
		 * next request. Only what is actually in the file counts before the
		 * database copy of the key is thrown away.
		 *
		 * @param string $file       The wp-config.php path.
		 * @param string $definition The exact define() statement expected.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function is_definition_on_disk( string $file, string $definition ): bool {
			@clearstatcache( true, $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			$contents = self::read( $file );

			return \is_string( $contents ) && false !== strpos( $contents, $definition );
		}

		/**
		 * Gets the permissions of given directory
		 *
		 * @param string $dir - The name of the directory to check.
		 *
		 * @return bool|int
		 *
		 * @since 2.4.0
		 */
		public static function get_permissions( string $dir ) {
			// Files as well: write() asks this about the file it is about to
			// chmod, and answering false for anything but a directory meant the
			// original mode was never put back after a permissions fallback.
			if ( ! is_dir( $dir ) && ! is_file( $dir ) ) {
				return false;
			}

			if ( ! PHP_Helper::is_callable( 'fileperms' ) ) {
				return false;
			}

			$dir = rtrim( $dir, '/' );

			@clearstatcache( true, $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			return fileperms( $dir ) & 0777;
		}

		/**
		 * Writes a content to a given file
		 *
		 * @param string  $file - The file to write to.
		 * @param string  $contents - The contents of the file to write.
		 * @param boolean $append - Append the contents of the file or overwrite.
		 *
		 * @return mixed
		 *
		 * @since 2.4.0
		 */
		public static function write( string $file, string $contents, $append = false ) {
			$callable = array();

			if ( PHP_Helper::is_callable( 'fopen' ) && PHP_Helper::is_callable( 'fwrite' ) && PHP_Helper::is_callable( 'flock' ) ) {
				$callable[] = 'fopen';
			}
			if ( PHP_Helper::is_callable( 'file_put_contents' ) ) {
				$callable[] = 'file_put_contents';
			}

			if ( empty( $callable ) ) {
				return false;
			}

			if ( ! self::is_path_allowed( $file ) ) {
				return false;
			}

			if ( is_dir( $file ) ) {
				return false;
			}

			if ( ! is_dir( dirname( $file ) ) ) {
				$result = self::create_dir( dirname( $file ) );

				if ( false === $result ) {
					return false;
				}
			}

			$file_existed = is_file( $file );
			$success      = false;

			/*
			 * Replace an existing file in one step where the filesystem allows it.
			 * Opening with 'wb' truncates first, so a write that failed half way -
			 * a full disk, a quota - left wp-config.php cut short and the site
			 * down. Not for appends, symlinks (rename() would replace the link
			 * itself) or files that are not writable, which somebody may have
			 * made read-only on purpose.
			 */
			if ( ! $append && $file_existed && ! is_link( $file ) && is_writable( $file ) ) {
				if ( true === self::replace_atomically( $file, $contents ) ) {
					return true;
				}
			}

			// Different permissions to try in case the starting set of permissions
			// are prohibiting write. Never 0666: a world-writable wp-config.php is
			// worse than a key that could not be saved.
			$trial_perms = array(
				false,
				0644,
				0664,
			);

			foreach ( $trial_perms as $perms ) {
				if ( false !== $perms ) {
					if ( ! isset( $original_file_perms ) ) {
						$original_file_perms = self::get_permissions( $file );
					}

					self::chmod( $file, $perms );
				}

				if ( ! $append && $file_existed ) {
					/*
					 * Replacing a file that is there, without the atomic path - a
					 * symlink, a directory PHP cannot create the copy in, a file
					 * owned by someone else. Opening it 'wb' truncated it first, so a
					 * write cut short by a full disk or a quota left wp-config.php
					 * half written. In place, and put back as it was if the write
					 * does not complete.
					 */
					$success = self::overwrite_in_place( $file, $contents );
				} elseif ( in_array( 'fopen', $callable, true ) ) {
					if ( $append ) {
						$mode = 'ab';
					} else {
						$mode = 'wb';
					}

					// Assignment inside condition is intentional to attempt the
					// fopen and capture the resource in one step; ignore the PHPCS
					// assignment-in-condition sniff.
					if ( false !== ( $fh = @fopen( $file, $mode ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure, WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
						flock( $fh, LOCK_EX );

						mbstring_binary_safe_encoding();

						$data_length = strlen( $contents );
						// Error suppression is deliberate: we attempt several IO
						// strategies and handle failures without raising warnings.
						$bytes_written = @fwrite( $fh, $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

						reset_mbstring_encoding();

						// Silencing these cleanup calls to avoid noise if resources
						// are invalid or already closed in older environments.
						@flock( $fh, LOCK_UN ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						@fclose( $fh ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

						if ( $data_length === $bytes_written ) {
							$success = true;
						}
					}
				}

				// file_put_contents() truncates as well: only for appends and new files.
				if ( ! $success && ( $append || ! $file_existed ) && in_array( 'file_put_contents', $callable, true ) ) {
					if ( $append ) {
						$flags = FILE_APPEND;
					} else {
						$flags = 0;
					}

					mbstring_binary_safe_encoding();

					$data_length = strlen( $contents );
					// Use file_put_contents when available; suppress warnings and
					// detect success via return value instead of raising errors.
					$bytes_written = @file_put_contents( $file, $contents, $flags ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

					reset_mbstring_encoding();

					if ( $data_length === $bytes_written ) {
						$success = true;
					}
				}

				if ( $success ) {
					if ( ! $file_existed ) {
						// A file's mode, not the ABSPATH directory's (typically 0755).
						self::chmod( $file, self::get_default_file_permissions() );
				} elseif ( isset( $original_file_perms ) && is_int( $original_file_perms ) ) {
					// Reset the original file permissions if they were modified.
					self::chmod( $file, $original_file_perms );
					}

					// clearstatcache may be silenced on some hosts; ignore the PHPCS
					// NoSilencedErrors warning for this cleanup call.
					@clearstatcache( true, $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

					return true;
				}

				if ( ! $file_existed ) {
					// If the file is new, there is no point attempting different permissions.
					break;
				}
			}

			if ( $file_existed && isset( $original_file_perms ) && is_int( $original_file_perms ) ) {
				self::chmod( $file, $original_file_perms );
			}

			return false;
		}

		/**
		 * Replaces a file's contents in place, without ever leaving it cut short.
		 *
		 * The file is not truncated before the write, so the blocks it already
		 * has stay allocated: if the new contents do not go in whole - a full
		 * disk, a quota - the original is written back over them, which needs
		 * no space it did not already have, and the file ends up as it was.
		 * Only a complete write is trimmed to its new length.
		 *
		 * @param string $file     - The file to replace the contents of.
		 * @param string $contents - The new contents.
		 *
		 * @return bool True when the new contents are in place.
		 *
		 * @since 4.2.0
		 */
		private static function overwrite_in_place( string $file, string $contents ): bool {
			foreach ( array( 'fopen', 'fread', 'fwrite', 'flock', 'ftruncate', 'rewind', 'fflush' ) as $function ) {
				if ( ! PHP_Helper::is_callable( $function ) ) {
					return false;
				}
			}

			// 'r+': read and write, no truncation, and no creating a file that is not there.
			$fh = @fopen( $file, 'r+b' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( false === $fh ) {
				return false;
			}

			if ( ! flock( $fh, LOCK_EX ) ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return false;
			}

			$original = stream_get_contents( $fh );
			$written  = false;

			if ( false !== $original && rewind( $fh ) ) {
				mbstring_binary_safe_encoding();
				$written = @fwrite( $fh, $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				reset_mbstring_encoding();
			}

			$success = false !== $original
				&& strlen( $contents ) === $written
				&& fflush( $fh )
				&& ftruncate( $fh, strlen( $contents ) );

			if ( ! $success && false !== $original && false !== $written ) {
				// Whatever went in, take it out again.
				rewind( $fh );
				mbstring_binary_safe_encoding();
				@fwrite( $fh, $original ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				reset_mbstring_encoding();
				ftruncate( $fh, strlen( $original ) );
				fflush( $fh );
			}

			flock( $fh, LOCK_UN );
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			return $success;
		}

		/**
		 * Writes a file by writing a sibling and renaming it over the original.
		 *
		 * The rename is atomic on the same filesystem, so a reader sees either
		 * the old file or the whole new one, never a truncated one.
		 *
		 * @param string $file     The existing file to replace.
		 * @param string $contents The new contents.
		 *
		 * @return bool|null True when replaced; null when it could not be tried
		 *                   here, in which case the caller writes in place.
		 *
		 * @since 4.2.0
		 */
		private static function replace_atomically( string $file, string $contents ): ?bool {
			$dir = dirname( $file );

			if ( ! is_writable( $dir ) || ! PHP_Helper::is_callable( 'rename' ) ) {
				return null;
			}

			/*
			 * rename() hands the file to whoever is running PHP, owner and group
			 * both. On a host where wp-config.php belongs to the account user, or
			 * where the web server reads .htaccess through its group, that would
			 * lock the owner out of their own file or turn the directory into a
			 * 403. So only when PHP already owns the file, with the same group;
			 * anywhere else - or where that cannot be told - it is written in
			 * place, as it always was.
			 */
			if ( ! function_exists( 'posix_geteuid' ) || ! function_exists( 'posix_getegid' ) ) {
				return null;
			}

			if ( @fileowner( $file ) !== posix_geteuid() || @filegroup( $file ) !== posix_getegid() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return null;
			}

			// random_bytes(), not wp_generate_password(): this can run before
			// pluggable functions are loaded.
			try {
				$suffix = bin2hex( random_bytes( 6 ) );
			} catch ( \Throwable $e ) {
				return null;
			}

			/*
			 * The copy sits beside the original until the rename, and for
			 * wp-config.php that is the web root with the database credentials
			 * in it. A .tmp name would be served as plain text to anyone who
			 * asked for it, and left behind for good if the rename and the clean
			 * up both failed. So a PHP file's copy ends in .php too - requesting
			 * it runs it, exactly as requesting wp-config.php does - and the copy
			 * is private to its owner from the first byte.
			 */
			$extension = ( '.php' === strtolower( substr( $file, -4 ) ) ) ? '.php' : '.tmp';
			$temp      = $dir . \DIRECTORY_SEPARATOR . '.' . basename( $file ) . '.wp2fa-' . $suffix . $extension;

			$handle = @fopen( $temp, 'xb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( false === $handle ) {
				return null;
			}

			self::chmod( $temp, 0600 );

			mbstring_binary_safe_encoding();
			$length  = strlen( $contents );
			$written = @fwrite( $handle, $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			reset_mbstring_encoding();

			$closed = @fclose( $handle ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			if ( $written !== $length || ! $closed ) {
				@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				return null;
			}

			// The copy was created private; give it the original's mode, or
			// WordPress's file mode rather than leaving a 0600 file behind.
			$perms = self::get_permissions( $file );
			self::chmod( $temp, false !== $perms ? $perms : self::get_default_file_permissions() );

			if ( ! @rename( $temp, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
				@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				return null;
			}

			@clearstatcache( true, $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			return true;
		}

		/**
		 * Adds index.php and .htaccess files to the given directory
		 *
		 * @param string $dir - The directory to protect.
		 *
		 * @return bool
		 *
		 * @since 2.4.0
		 */
		public static function add_file_listing_protection( string $dir ) {
			$dir = rtrim( $dir, \DIRECTORY_SEPARATOR );

			if ( ! is_dir( $dir ) ) {
				return false;
			}

			if ( ! self::is_path_allowed( $dir ) ) {
				return false;
			}

			$htaccess_file = $dir . \DIRECTORY_SEPARATOR . '.htaccess';
			$index_file    = $dir . \DIRECTORY_SEPARATOR . 'index.php';
			$webconfig     = $dir . \DIRECTORY_SEPARATOR . 'web.config';

			/*
			 * Rewritten when it is the old one-liner, rather than left because a file is there.
			 * Sites that already have the previous version keep the weaker rule for good
			 * otherwise, which is the case most in need of the stronger one.
			 */
			$htaccess_protected = self::exists( $htaccess_file ) && self::LEGACY_HTACCESS !== \trim( (string) @\file_get_contents( $htaccess_file ) ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading our own file to decide whether to replace it.
				? true
				: self::write( $htaccess_file, self::htaccess_contents() );

			$index_protected = self::exists( $index_file ) || self::write( $index_file, "<?php\n// Silence is golden." );

			// IIS reads neither .htaccess nor an index file for this.
			$webconfig_protected = self::exists( $webconfig ) || self::write( $webconfig, self::webconfig_contents() );

			return $htaccess_protected && $index_protected && $webconfig_protected;
		}

		/**
		 * The deny rule, written so it applies on both Apache generations.
		 *
		 * "Deny from all" on its own is Apache 2.2 syntax. Apache 2.4 only understands it while
		 * mod_access_compat is loaded, and that module is absent on plenty of modern builds —
		 * where the directive is not ignored but fatal, taking the whole directory down with a
		 * 500. Each form is therefore guarded by the module that understands it.
		 *
		 * Neither helps on Nginx, which does not read these files at all. What protects the
		 * contents there is that the filenames carry a per-site random component and the
		 * directory cannot be listed; the documented Nginx rule in the readme closes the rest.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function htaccess_contents(): string {
			return "# Generated by WP 2FA. Denies direct access to the files in this directory.\n"
				. "<IfModule mod_authz_core.c>\n"
				. "\tRequire all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "\tOrder allow,deny\n"
				. "\tDeny from all\n"
				. "</IfModule>\n";
		}

		/**
		 * The same denial for IIS.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function webconfig_contents(): string {
			return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
				. "<configuration>\n"
				. "\t<system.webServer>\n"
				. "\t\t<authorization>\n"
				. "\t\t\t<deny users=\"*\" />\n"
				. "\t\t</authorization>\n"
				. "\t</system.webServer>\n"
				. "</configuration>\n";
		}

		/**
		 * Checks if given file exists
		 *
		 * @param string $file - The name of the file to check.
		 *
		 * @return bool
		 *
		 * @since 2.4.0
		 */
		public static function exists( string $file ): bool {
			if ( ! self::is_path_allowed( $file ) ) {
				return false;
			}

			@clearstatcache( true, $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			// Use error suppression to avoid warnings on inaccessible paths; this
			// helper handles the error conditions, so ignore the PHPCS rule.
			return @file_exists( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		/**
		 * Check the setting that allows writing files.
		 *
		 * @param string $filename - The name of the file and path.
		 *
		 * @since 2.4.0
		 *
		 * @return bool True if files can be written to, false otherwise.
		 */
		public static function can_write_to_file( string $filename ) {
			if ( ! self::is_path_allowed( $filename ) ) {
				return false;
			}

			return is_writable( $filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		}

		/**
		 * Get full file path to the site's wp-config.php file.
		 *
		 * @since 2.4.0
		 *
		 * @return string Full path to the wp-config.php file or a blank string if modifications for the file are disabled.
		 */
		public static function get_wp_config_file_path() {

			if ( file_exists( ABSPATH . 'wp-config.php' ) ) {

				/** The config file resides in ABSPATH */
				$path = ABSPATH . 'wp-config.php';

			} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

				/** The config file resides one level above ABSPATH */
				$path = dirname( ABSPATH ) . '/wp-config.php';

			} else {
				$path = '';
			}

			/**
			 * Gives the ability to manually change the path to the config file.
			 *
			 * @param string - The current value for WP config file path.
			 *
			 * @since 2.6.2
			 */
			$path = \apply_filters( WP_2FA_PREFIX . 'config_file_path', (string) $path );

			return $path;
		}

		/**
		 * Creates a directory structure
		 *
		 * @param string $dir - The directory to create.
		 *
		 * @return boolean
		 *
		 * @since 2.4.0
		 */
		public static function create_dir( string $dir ): bool {
			$dir = rtrim( $dir, '/' );

			if ( ! self::is_path_allowed( $dir ) ) {
				return false;
			}

			if ( is_dir( $dir ) ) {
				self::add_file_listing_protection( $dir );

				return true;
			}

			if ( self::exists( $dir ) ) {
				return false;
			}

			if ( ! PHP_Helper::is_callable( 'mkdir' ) ) {
				return false;
			}

			$parent = dirname( $dir );

			while ( ! empty( $parent ) && ! is_dir( $parent ) && dirname( $parent ) !== $parent ) {
				$parent = dirname( $parent );
			}

			if ( empty( $parent ) ) {
				return false;
			}

			$perms = self::get_permissions( $parent );

			if ( ! is_int( $perms ) ) {
				$perms = self::get_default_permissions();
			}

			$cached_umask = umask( 0 );
			// Attempt mkdir but suppress warnings on failure — we handle return
			// values and do not want noisy warnings on restricted hosts.
			$result = @mkdir( $dir, $perms, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			umask( $cached_umask );

			if ( $result ) {
				self::add_file_listing_protection( $dir );

				return true;
			}

			return false;
		}

		/**
		 * Retrieves the full path to plugin's working directory. Returns a folder path with a trailing slash. It also
		 * creates the folder unless the $skip_creation parameter is set to true.
		 *
		 * Default path is "{uploads folder}/self::WP2FA_UPLOADS_DIR/"
		 *
		 * @param string $path          Optional path relative to the working directory.
		 * @param bool   $skip_creation If true, the folder will not be created.
		 * @param bool   $ignore_site   If true, there will be no sub-site specific subfolder in multisite context.
		 *
		 * @return string|\WP_Error
		 *
		 * @since 2.6.0
		 */
		public static function get_upload_path( $path = '', $skip_creation = false, $ignore_site = false ) {
			$result = '';

			$upload_dir = wp_upload_dir( null, false );
			if ( is_array( $upload_dir ) && array_key_exists( 'basedir', $upload_dir ) ) {
				$result = $upload_dir['basedir'] . \DIRECTORY_SEPARATOR . self::WP2FA_UPLOADS_DIR . \DIRECTORY_SEPARATOR;
			} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
				// Fallback in case there is a problem with filesystem.
				$result = WP_CONTENT_DIR . \DIRECTORY_SEPARATOR . 'uploads' . \DIRECTORY_SEPARATOR . self::WP2FA_UPLOADS_DIR . \DIRECTORY_SEPARATOR;
			}

			if ( empty( $result ) ) {
				// Empty result here means invalid custom path or a problem with WordPress (uploads folder issue or mission WP_CONTENT_DIR).
				return new \WP_Error( 'wp-2fa_uplaods_dir_missing', __( 'The base of WSAL working directory cannot be determined. Custom path is invalid or there is some other issue with your WordPress installation.', 'wp-2fa' ) );
			}

			$data_root = $result;

			// Append site specific subfolder in multisite context.
			if ( ! $ignore_site && WP_Helper::is_multisite() ) {
				$site_id = \get_current_blog_id();
				if ( $site_id > 0 ) {
					$result .= 'sites' . \DIRECTORY_SEPARATOR . $site_id . \DIRECTORY_SEPARATOR;
				}
			}

			// Append optional path passed as a parameter.
			if ( is_string( $path ) && '' !== $path ) {
				$sanitized_subpath = self::sanitize_relative_subpath( $path );

				if ( null === $sanitized_subpath ) {
					return new \WP_Error(
						'invalid_subdirectory',
						__( 'The requested WP 2FA storage subdirectory is invalid.', 'wp-2fa' )
					);
				}

				if ( '' !== $sanitized_subpath ) {
					$result .= $sanitized_subpath . \DIRECTORY_SEPARATOR;
				}
			}

			if ( ! file_exists( $result ) ) {
				if ( ! $skip_creation ) {
					if ( ! \wp_mkdir_p( $result ) ) {
						return new \WP_Error(
							'mkdir_failed',
							sprintf(
								/* translators: %s: Directory path. */
								__( 'Unable to create directory %s. Is its parent directory writable by the server?', 'wp-2fa' ),
								esc_html( $result )
							)
						);
					}
				}

			}

			$directory = rtrim( $result, \DIRECTORY_SEPARATOR );
			$data_root = rtrim( $data_root, \DIRECTORY_SEPARATOR );

			while ( self::path_starts_with( $directory, $data_root ) ) {
				if ( is_dir( $directory ) ) {
					self::add_file_listing_protection( $directory );
				}

				if ( $directory === $data_root ) {
					break;
				}

				$directory = dirname( $directory );
			}

			return $result;
		}

		/**
		 * Remove the supplied file.
		 *
		 * @param string $file - The name of the file and path.
		 *
		 * @return bool|WP_Error Boolean true on success or a WP_Error object if an error occurs.
		 *
		 * @since 2.6.0
		 */
		public static function remove( $file ) {
			if ( ! self::is_path_allowed( $file ) ) {
				return new \WP_Error(
					'wp-2fa',
					__( 'Removing files outside the WP 2FA data directory is not permitted.', 'wp-2fa' )
				);
			}

			if ( ! self::exists( $file ) ) {
				return true;
			}

			if ( ! PHP_Helper::is_callable( 'unlink' ) ) {
				return new \WP_Error(
					'wp-2fa',
					// translators: the name of the file.
					sprintf( __( 'The file %s could not be removed as the unlink() function is disabled. This is a system configuration issue.', 'wp-2fa' ), $file )
				);
			}

			// Suppress unlink warnings and accept the return value; the helper
			// handles failure paths and this avoids noisy errors on some hosts.
			$result = @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink

			// clearstatcache may not support args on older PHP; ignore PHPCS
			// warnings for compatibility.
			@clearstatcache( true, $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( $result ) {
				return true;
			}

			return new \WP_Error(
				'wp-2fa',
				sprintf(
					// translators: the name of the file.
					__( 'Unable to remove %s due to an unknown error.', 'wp-2fa' ),
					$file
				)
			);
		}

		/**
		 * Gets the content of a file
		 *
		 * @param string $file - The name of the file.
		 *
		 * @return bool|string
		 *
		 * @since 2.4.0
		 */
		protected static function get_file_contents( string $file ) {
			if ( ! self::is_path_allowed( $file ) ) {
				return false;
			}

			if ( ! self::exists( $file ) ) {
				return '';
			}

			$contents = self::read( $file );

			if ( is_wp_error( $contents ) ) {
				return false;
			}

			return $contents;
		}

		/**
		 * Write the supplied modification to the wp-config.php file.
		 *
		 * @since 2.4.0
		 *
		 * @param string $modification - The modification to add to the wp-config.php file.
		 *
		 * @return bool
		 */
		private static function write_wp_config( $modification, $contents = null ) {
			$file_path = self::get_wp_config_file_path();

			return self::update( $file_path, $modification, $contents );
		}

		/**
		 * Updates the content of a file
		 *
		 * @param string $file - The name of the file to update.
		 * @param string $modification - The modification to be added to the file.
		 *
		 * @return boolean
		 *
		 * @since 2.4.0
		 */
		private static function update( string $file, string $modification, $contents_override = null ): bool {
			// Check to make sure that the settings give permission to write files.
			if ( ! self::can_write_to_file( $file ) ) {

				return false;
			}

			$contents = null === $contents_override ? self::read( $file ) : $contents_override;

			if ( is_wp_error( $contents ) ) {
				return $contents;
			}

			if ( ! $contents ) {
				return false;
			}

			$modification = ltrim( $modification, "\x0B\r\n\0" );
			$modification = rtrim( $modification, " \t\x0B\r\n\0" );

			if ( empty( $modification ) ) {
				// If there isn't a new modification, write the content without any modification and return the result.

				if ( empty( $contents ) ) {
					$contents = PHP_EOL;
				}

				return false;
			}

			$placeholder = self::get_placeholder();

			// Ensure that the generated placeholder can be uniquely identified in the contents.
			while ( false !== strpos( $contents, $placeholder ) ) {
				$placeholder = self::get_placeholder();
			}

			// Put the placeholder at the beginning of the file, after the <?php tag.
			$contents = preg_replace( '/^(.*?<\?(?:php)?)\s*(?:\r\r\n|\r\n|\r|\n)/', "\${1}$placeholder", $contents, 1 );

			if ( false === strpos( $contents, $placeholder ) ) {
				$contents = preg_replace( '/^(.*?<\?(?:php)?)\s*(.+(?:\r\r\n|\r\n|\r|\n))/', "\${1}$placeholder$2", $contents, 1 );
			}

			if ( false === strpos( $contents, $placeholder ) ) {
				$contents = "<?php$placeholder?" . ">$contents";
			}

			// Pad away from existing sections when adding iThemes Security modifications.
			$line_ending = self::get_line_ending( $contents );

			while ( ! preg_match( "/(?:^|(?:(?<!\r)\n|\r(?!\n)|(?<!\r)\r\n|\r\r\n)(?:(?<!\r)\n|\r(?!\n)|(?<!\r)\r\n|\r\r\n))$placeholder/", $contents ) ) {
				$contents = preg_replace( "/$placeholder/", "$line_ending$placeholder", $contents );
			}
			while ( ! preg_match( "/$placeholder(?:$|(?:(?<!\r)\n|\r(?!\n)|(?<!\r)\r\n|\r\r\n)(?:(?<!\r)\n|\r(?!\n)|(?<!\r)\r\n|\r\r\n))/", $contents ) ) {
				$contents = preg_replace( "/$placeholder/", "$placeholder$line_ending", $contents );
			}

			// Ensure that the file ends in a newline if the placeholder is at the end.
			$contents = preg_replace( "/$placeholder$/", "$placeholder$line_ending", $contents );

			if ( ! empty( $modification ) ) {
				// Normalize line endings of the modification to match the file's line endings.
				$modification = self::normalize_line_endings( $modification, $line_ending );

				// Exchange the placeholder with the modification.
				$contents = preg_replace( "/$placeholder/", $modification, $contents );
			}

			// Write the new contents to the file and return the results.
			return self::write_config_file( $file, $contents );
		}

		/**
		 * Create a PHP literal suitable for inclusion inside wp-config.php.
		 *
		 * @param string $secret Secret value to persist.
		 *
		 * @return string Empty string when the value is invalid.
		 *
		 * @since 3.1.0
		 */
		private static function build_secret_literal( string $secret ): string {
			$secret = trim( $secret );

			if ( '' === $secret ) {
				return '';
			}

			if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $secret ) ) {
				return '';
			}

			return var_export( $secret, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		/**
		 * Retrieve the regex pattern used to locate the secret definition.
		 *
		 * @return string
		 *
		 * @since 3.1.0
		 */
		private static function get_secret_definition_pattern(): string {
			$constant = preg_quote( self::SECRET_NAME, '/' );

			return '/define\s*\(\s*([\'\"])' . $constant . '\1\s*,\s*([\'\"])(.*?)\2\s*\);/is';
		}

		/**
		 * Replace the stored secret definition with an updated value.
		 *
		 * @param string $contents   Full contents of the wp-config.php file.
		 * @param string $definition Sanitized definition to store.
		 *
		 * @return string|false
		 *
		 * @since 3.1.0
		 */
		private static function replace_secret_definition( string $contents, string $definition ) {
			$replaced = preg_replace( self::get_secret_definition_pattern(), $definition, $contents, 1 );

			if ( null === $replaced || $replaced === $contents ) {
				return false;
			}

			return $replaced;
		}

		/**
		 * Determine whether a path is inside the allowed data directory or points to wp-config.php.
		 *
		 * @param string $path Absolute file or directory path.
		 *
		 * @return bool
		 *
		 * @since 3.1.0
		 */
		private static function is_path_allowed( string $path ): bool {
			$canonical = self::canonicalize_path( $path );

			if ( '' === $canonical ) {
				return false;
			}

			$wp_config_path = self::canonicalize_path( self::get_wp_config_file_path() );

			if ( '' !== $wp_config_path && $canonical === $wp_config_path ) {
				return true;
			}

			$data_root = self::get_data_root_path();

			if ( '' !== $data_root && self::path_starts_with( $canonical, $data_root ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Return the canonical plugin data root.
		 *
		 * @return string
		 *
		 * @since 3.1.0
		 */
		private static function get_data_root_path(): string {
			static $data_root = null;

			if ( null !== $data_root ) {
				return $data_root;
			}

			$data_root  = '';
			$upload_dir = wp_upload_dir( null, false );

			if ( is_array( $upload_dir ) && isset( $upload_dir['basedir'] ) ) {
				$base = $upload_dir['basedir'];
			} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
				$base = WP_CONTENT_DIR . \DIRECTORY_SEPARATOR . 'uploads';
			} else {
				return $data_root;
			}

			$data_root = self::canonicalize_path( $base . \DIRECTORY_SEPARATOR . self::WP2FA_UPLOADS_DIR );

			return $data_root;
		}

		/**
		 * Normalize any given path by removing traversal tokens and normalizing separators.
		 *
		 * @param string $path Raw file or directory path.
		 *
		 * @return string
		 *
		 * @since 3.1.0
		 */
		private static function canonicalize_path( string $path ): string {
			if ( '' === $path ) {
				return '';
			}

			if ( function_exists( 'wp_normalize_path' ) ) {
				$path = \wp_normalize_path( $path );
			} else {
				$path = str_replace( '\\', '/', $path );
			}

			$drive      = '';
			$path_start = substr( $path, 0, 2 );
			if ( preg_match( '/^[A-Za-z]:$/', $path_start ) ) {
				$drive = strtoupper( $path_start );
				$path  = substr( $path, 2 );
			}

			$is_absolute = ( 0 === strpos( $path, '/' ) );
			$segments    = explode( '/', trim( $path, '/' ) );
			$resolved    = array();

			foreach ( $segments as $segment ) {
				if ( '' === $segment || '.' === $segment ) {
					continue;
				}

				if ( '..' === $segment ) {
					array_pop( $resolved );
					continue;
				}

				$resolved[] = $segment;
			}

			$normalized = implode( '/', $resolved );

			if ( $is_absolute ) {
				$normalized = '/' . ltrim( $normalized, '/' );
			}

			if ( '' !== $drive ) {
				$normalized = $drive . $normalized;
			}

			if ( '' === $normalized ) {
				return '';
			}

			if ( '/' === $normalized || preg_match( '/^[A-Za-z]:\/$/', $normalized ) ) {
				return $normalized;
			}

			return rtrim( $normalized, '/' );
		}

		/**
		 * Check if a path is inside a given root directory.
		 *
		 * @param string $path Absolute canonicalized path.
		 * @param string $root Canonicalized root directory.
		 *
		 * @return bool
		 *
		 * @since 3.1.0
		 */
		private static function path_starts_with( string $path, string $root ): bool {
			$normalized_root = rtrim( $root, '/' );
			$normalized_path = rtrim( $path, '/' );

			if ( '' === $normalized_root || '' === $normalized_path ) {
				return false;
			}

			$normalized_root .= '/';
			$normalized_path .= '/';

			return 0 === strpos( $normalized_path, $normalized_root );
		}

		/**
		 * Sanitize a relative path segment appended to the data directory.
		 *
		 * @param string $path Raw subpath requested by the caller.
		 *
		 * @return string|null Empty string for blank input, null when invalid.
		 *
		 * @since 3.1.0
		 */
		private static function sanitize_relative_subpath( string $path ): ?string {
			$path = trim( $path );

			if ( '' === $path ) {
				return '';
			}

			if ( function_exists( 'wp_normalize_path' ) ) {
				$path = \wp_normalize_path( $path );
			} else {
				$path = str_replace( '\\', '/', $path );
			}

			$path      = trim( $path, '/\\' );
			$segments  = explode( '/', $path );
			$sanitized = array();

			foreach ( $segments as $segment ) {
				if ( '' === $segment || '.' === $segment || '..' === $segment ) {
					return null;
				}

				if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $segment ) ) {
					return null;
				}

				$sanitized[] = $segment;
			}

			return implode( \DIRECTORY_SEPARATOR, $sanitized );
		}

		/**
		 * Generates unique placeholder to be used in the string
		 *
		 * @return string
		 *
		 * @since 2.4.0
		 */
		private static function get_placeholder(): string {
			$characters = str_split( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );

			$string = '';

			for ( $x = 0; $x < 100; $x++ ) {
				$string .= array_rand( $characters );
			}

			return $string;
		}

		/**
		 * Returns to proper line endings of a given content
		 *
		 * @param string $contents - The text to be checked.
		 *
		 * @return string
		 *
		 * @since 2.4.0
		 */
		private static function get_line_ending( string $contents ) {
			if ( empty( $contents ) ) {
				return PHP_EOL;
			}

			$count["\n"]     = preg_match_all( "/(?<!\r)\n/", $contents, $matches );
			$count["\r"]     = preg_match_all( "/\r(?!\n)/", $contents, $matches );
			$count["\r\n"]   = preg_match_all( "/(?<!\r)\r\n/", $contents, $matches );
			$count["\r\r\n"] = preg_match_all( "/\r\r\n/", $contents, $matches );

			if ( 0 === array_sum( $count ) ) {
				return PHP_EOL;
			}

			$maxes = array_keys( $count, max( $count ), true );

			if ( in_array( "\r\r\n", $maxes, true ) ) {
				return "\r\r\n";
			}

			return $maxes[0];
		}

		/**
		 * Normalizing fileendings for different platforms
		 *
		 * @param string $content - The file content to be checked.
		 * @param string $line_ending - Line endings to be used.
		 *
		 * @return string
		 *
		 * @since 2.4.0
		 */
		private static function normalize_line_endings( string $content, string $line_ending = "\n" ): string {
			return preg_replace( '/(?<!\r)\n|\r(?!\n)|(?<!\r)\r\n|\r\r\n/', $line_ending, $content );
		}

		/**
		 * Reads the content of a file
		 *
		 * @param string $file - The file to read.
		 *
		 * @return bool|string
		 *
		 * @since 2.4.0
		 */
		private static function read( string $file ) {
			if ( ! self::is_path_allowed( $file ) ) {
				return false;
			}

			if ( ! is_file( $file ) ) {
				return false;
			}

			$callable = array();

			if ( PHP_Helper::is_callable( 'file_get_contents' ) ) {
				$callable[] = 'file_get_contents';
			}
			if ( PHP_Helper::is_callable( 'fopen' ) && PHP_Helper::is_callable( 'feof' ) && PHP_Helper::is_callable( 'fread' ) && PHP_Helper::is_callable( 'flock' ) ) {
				$callable[] = 'fopen';
			}

			if ( empty( $callable ) ) {
				return false;
			}

			$contents = self::read_once( $file, $callable );
			if ( false !== $contents ) {
				return $contents;
			}

			/*
			 * One retry, with the owner's read bit added - the only bit that can
			 * help. chmod() works only for the file's owner (or root, who can read
			 * anyway), and for the owner the group and world bits change nothing.
			 * This used to try 0644, 0664 and finally 0666, and put the original
			 * mode back only when a read succeeded: a read that kept failing left
			 * wp-config.php world-writable.
			 */
			$original = self::get_permissions( $file );
			if ( ! is_int( $original ) || 0400 === ( $original & 0400 ) ) {
				return false;
			}

			if ( ! self::chmod( $file, $original | 0400 ) ) {
				return false;
			}

			try {
				$contents = self::read_once( $file, $callable );
			} finally {
				// Whatever happened, the file keeps the mode it had.
				if ( ! self::chmod( $file, $original ) || self::get_permissions( $file ) !== $original ) {
					/*
					 * Nothing wider than the owner's read bit is left behind, but it is
					 * still not what the site set, and the site should know.
					 */
					if ( class_exists( '\\WP2FA\\Utils\\Debugging' ) ) {
						\WP2FA\Utils\Debugging::log( sprintf( 'Could not restore the permissions of %s to %04o.', $file, $original ) );
					}
				}
			}

			return $contents;
		}

		/**
		 * One attempt at reading a file, with each of the available readers.
		 *
		 * @param string   $file     - The file to read.
		 * @param string[] $readers - The readers that may be used.
		 *
		 * @return false|string
		 *
		 * @since 4.2.0
		 */
		private static function read_once( string $file, array $readers ) {
			$contents = false;

			if ( in_array( 'fopen', $readers, true ) ) {
				// Assignment in the conditional is intentional to attempt open
				// and capture the handle in one expression; ignore PHPCS here.
				if ( false !== ( $fh = @fopen( $file, 'rb' ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
					flock( $fh, LOCK_SH );

					$contents = '';

					while ( ! feof( $fh ) ) {
						// fread can trigger warnings on read errors; we deliberately
						// manage return values and ignore PHPCS for the raw call.
						$contents .= fread( $fh, 1024 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
					}

					flock( $fh, LOCK_UN );
					// Close the handle and ignore potential warnings; errors are
					// handled by higher-level checks.
					fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				}
			}

			if ( ( false === $contents ) && in_array( 'file_get_contents', $readers, true ) ) {
				// file_get_contents used as a fallback for reading files; suppress
				// PHPCS warnings about remote-get usage in this low-level helper.
				$contents = @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}

			return $contents;
		}

		/**
		 * Changes the permissions of a file
		 *
		 * @param string $file - The file to change permissions to.
		 * @param mixed  $perms - The permissions to be set.
		 *
		 * @return bool
		 *
		 * @since 2.4.0
		 */
		private static function chmod( string $file, $perms ): bool {
			// This returned \CURLOPT_SSL_FALSESTART, which coerces to true (a
			// failed chmod reported as success) and is a fatal "undefined
			// constant" wherever ext-curl is not loaded.
			if ( ! is_int( $perms ) ) {
				return false;
			}

			if ( ! PHP_Helper::is_callable( 'chmod' ) ) {
				return false;
			}

			// Attempt chmod and suppress warnings; we handle return values and
			// do not want noise when permissions cannot be changed.
			return @chmod( $file, $perms ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		}

		/**
		 * Returns the default filesystem permissions
		 *
		 * @return integer
		 *
		 * @since 2.4.0
		 */
		private static function get_default_permissions() {

			$perms = self::get_permissions( ABSPATH );

			// get_permissions() answers false, never a WP_Error, so the old
			// is_wp_error() test passed false straight through.
			return is_int( $perms ) ? $perms : 0755;
		}

		/**
		 * Returns the mode a newly created file should get.
		 *
		 * WordPress's own FS_CHMOD_FILE when the site defines it, 0644 otherwise.
		 * get_default_permissions() is a directory's mode and not meant for files.
		 *
		 * @return int
		 *
		 * @since 4.2.0
		 */
		private static function get_default_file_permissions(): int {
			return ( defined( 'FS_CHMOD_FILE' ) && is_int( FS_CHMOD_FILE ) ) ? FS_CHMOD_FILE : 0644;
		}
	}
}
