<?php
/**
 * Responsible for the User's operations
 *
 * @package    wp2fa
 * @subpackage helpers
 * @since      latest
 * @copyright  2023 WP White Security
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * User's settings class
 */
if ( ! class_exists( '\WP2FA\Admin\Helpers\File_Writer' ) ) {

	/**
	 * All the user related settings must go trough this class.
	 *
	 * @since 2.4.0
	 */
	class File_Writer {

		public const SECRET_NAME = 'WP2FA_ENCRYPT_KEY';

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

			set_error_handler(
				function ( $err_severity, $err_msg, $err_file, $err_line, array $err_context ) {
					throw new \Error( $err_msg, 0, $err_severity, $err_file, $err_line );
				},
				E_WARNING
			);

			try {
				$current_secret = constant( self::SECRET_NAME );
			} catch ( \Error $e ) {
				$current_secret = null;
			}

			restore_error_handler();

			$matches_found = $current_secret ? substr_count( $contents, $current_secret ) : 0;

			if ( ! $current_secret || ! $matches_found ) {
				if ( substr_count( $contents, self::SECRET_NAME ) ) {

					$line_ending = self::get_line_ending( $contents );

					$contents = explode( $line_ending, $contents );

					foreach ( $contents as $key => $line ) {
						if ( stristr( $line, self::SECRET_NAME ) ) {
							unset( $contents[ $key ] );
						}
					}

					$contents = implode( $line_ending, array_values( $contents ) );
					self::write( $file, $contents );
				}
				self::write_wp_config( '/** WP 2FA plugin data encryption key. For more information please visit wp2fa.io */' . "\n" . 'define( \'' . self::SECRET_NAME . '\', \'' . $secret . '\' );' );
				return true;
			}

			if ( $matches_found > 1 ) {
				return false;
			}

			$replaced = str_replace( $current_secret, $secret, $contents );

			if ( ! $replaced ) {
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
			// phpcs:ignore -- Have Tide ignore the following line. We use arguments that don't exist in early versions, but these versions ignore the arguments.
			@clearstatcache( true, $dir );

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

					if ( false !== ( $fh = @fopen( $file, $mode ) ) ) { // phpcs:ignore -- Ignored the assignment on the same line
						flock( $fh, LOCK_EX );

						mbstring_binary_safe_encoding();

						$data_length   = strlen( $contents );
						$bytes_written = @fwrite( $fh, $contents ); // phpcs:ignore -- Ignored the error silencing

						reset_mbstring_encoding();

						@flock( $fh, LOCK_UN ); // phpcs:ignore -- Ignored the error silencing
						@fclose( $fh ); // phpcs:ignore -- Ignored the error silencing

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

					$data_length   = strlen( $contents );
					$bytes_written = @file_put_contents( $file, $contents, $flags ); // phpcs:ignore -- Ignored the silencing warning

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
			$dir = rtrim( $dir, '/' );

			if ( ! is_dir( $dir ) ) {
				return false;
			}

			if ( self::exists( "$dir/index.php" ) ) {
				return true;
			}

			return self::write( "$dir/index.php", "<?php\n// Silence is golden." );
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

			@clearstatcache( true, $file ); // phpcs:ignore -- Have Tide ignore the following line. We use arguments that don't exist in early versions, but these versions ignore the arguments.

			return @file_exists( $file ); // phpcs:ignore -- Have Tide ignore the following line. We use arguments that don't exist in early versions, but these versions ignore the arguments.
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
			return is_writable( $filename );
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
				$path = ABSPATH . 'wp-config.php';
			} else {
				$path = '';
			}

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
			$result       = @mkdir( $dir, $perms, true ); // phpcs:ignore -- We don't want to have fatalities here.
			umask( $cached_umask );

			if ( $result ) {
				self::add_file_listing_protection( $dir );

				return true;
			}

			return false;
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
					if ( false !== ( $fh = fopen( $file, 'rb' ) ) ) { // phpcs:ignore -- Ignored the assigned on the same line error
						flock( $fh, LOCK_SH );

						$contents = '';

						while ( ! feof( $fh ) ) {
							$contents .= fread( $fh, 1024 ); // phpcs:ignore -- Ignored the file operation notification
						}

						flock( $fh, LOCK_UN );
						fclose( $fh ); // phpcs:ignore -- Ignored the file operation notification
					}
				}

				if ( ( false === $contents ) && in_array( 'file_get_contents', $callable, true ) ) {
					$contents = file_get_contents( $file );  // phpcs:ignore -- Ignored the wp_remote_get usage
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

			return @chmod( $file, $perms ); // phpcs:ignore -- Don't need fatalities here.
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
