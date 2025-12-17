<?php
/**
 * Responsible for the File writing operations
 *
 * @package    wp2fa
 * @subpackage helpers
 * @since      2.4.0
 * @copyright  2025 Melapress
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
					self::write( $file, $contents );
				}
				self::write_wp_config( '/** WP 2FA plugin data encryption key. For more information please visit melapress.com */' . "\n" . $definition );
				return true;
			}

			if ( $definition_list > 1 ) {
				return false;
			}

			$replaced = self::replace_secret_definition( $contents, $definition );

			if ( false === $replaced ) {
				return false;
			}

			$written = self::write( $file, $replaced );

			if ( false === $written ) {
				return false;
			}

			return true;
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
			if ( ! is_dir( $dir ) ) {
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

			// Different permissions to try in case the starting set of permissions are prohibiting write.
			$trial_perms = array(
				false,
				0644,
				0664,
				0666,
			);

			foreach ( $trial_perms as $perms ) {
				if ( false !== $perms ) {
					if ( ! isset( $original_file_perms ) ) {
						$original_file_perms = self::get_permissions( $file );
					}

					self::chmod( $file, $perms );
				}

				if ( in_array( 'fopen', $callable, true ) ) {
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

				if ( ! $success && in_array( 'file_put_contents', $callable, true ) ) {
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
						// Set default file permissions for the new file.
						self::chmod( $file, self::get_default_permissions() );
					} elseif ( isset( $original_file_perms ) && ! is_wp_error( $original_file_perms ) ) {
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

			return false;
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

			if ( self::exists( $dir . \DIRECTORY_SEPARATOR . 'index.php' ) ) {
				return true;
			}

			return self::write( $dir . \DIRECTORY_SEPARATOR . '.htaccess', 'Deny from all' ) &&
			self::write( $dir . \DIRECTORY_SEPARATOR . 'index.php', "<?php\n// Silence is golden." );
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
				return new \WP_Error( '2fa_uplaods_dir_missing', __( 'The base of WSAL working directory cannot be determined. Custom path is invalid or there is some other issue with your WordPress installation.', 'wp-2fa' ) );
			}

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

				self::add_file_listing_protection( $result );
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
		private static function write_wp_config( $modification ) {
			$file_path = self::get_wp_config_file_path();

			return self::update( $file_path, $modification );
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
		private static function update( string $file, string $modification ): bool {
			// Check to make sure that the settings give permission to write files.
			if ( ! self::can_write_to_file( $file ) ) {

				return false;
			}

			$contents = self::read( $file );

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
			return self::write( $file, $contents );
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

			$contents = false;

			// Different permissions to try in case the starting set of permissions are prohibiting read.
			$trial_perms = array(
				false,
				0644,
				0664,
				0666,
			);

			foreach ( $trial_perms as $perms ) {
				if ( false !== $perms ) {
					if ( ! isset( $original_file_perms ) ) {
						$original_file_perms = self::get_permissions( $file );
					}

					self::chmod( $file, $perms );
				}

				if ( in_array( 'fopen', $callable, true ) ) {
						// Assignment in the conditional is intentional to attempt open
						// and capture the handle in one expression; ignore PHPCS here.
					if ( false !== ( $fh = @fopen( $file, 'rb' ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
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

				if ( ( false === $contents ) && in_array( 'file_get_contents', $callable, true ) ) {
					// file_get_contents used as a fallback for reading files; suppress
					// PHPCS warnings about remote-get usage in this low-level helper.
					$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				}

				if ( false !== $contents ) {
					if ( isset( $original_file_perms ) && is_int( $original_file_perms ) ) {
						// Reset the original file permissions if they were modified.
						self::chmod( $file, $original_file_perms );
					}

					return $contents;
				}
			}

			return false;
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
			if ( ! is_int( $perms ) ) {
				return \CURLOPT_SSL_FALSESTART;
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

			if ( ! is_wp_error( $perms ) ) {
				return $perms;
			}

			return 0755;
		}
	}
}
