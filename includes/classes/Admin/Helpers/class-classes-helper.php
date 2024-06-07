<?php
/**
 * Responsible for the User's operations.
 *
 * @package    wp2fa
 * @subpackage helpers
 *
 * @since      2.4.0
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP2FA\Admin\Helpers\Classes_Helper' ) ) {
	/**
	 * Responsible for the proper class loading.
	 */
	class Classes_Helper {
		/**
		 * Holds the classmap array for more info check @see autoload_classmap.php from the auto generated Composer file.
		 *
		 * @var array
		 *
		 * @since 2.4.0
		 */
		private static $class_map = array();

		/**
		 * Caches and returns the classmap structure of the plugin.
		 *
		 * @since 2.4.0
		 */
		public static function get_class_map(): array {
			if ( empty( self::$class_map ) ) {
				self::$class_map = require WP_2FA_PATH . 'vendor/composer/autoload_classmap.php';
			}

			return self::$class_map;
		}

		/**
		 * Returns the class by its filename. Checks if it exists and returns it as string. Returns false otherwise.
		 *
		 * @param string $file - The filename of the class to check.
		 *
		 * @return string|false
		 *
		 * @since 2.4.0
		 */
		public static function get_class_by_filename( string $file ) {
			if ( in_array( $file, self::get_class_map(), true ) ) {
				$class = array_search( $file, self::get_class_map(), true );

				if ( class_exists( $class ) ) {
					return $class;
				}
			}

			return false;
		}

		/**
		 * Extracts subclasses of the given class, optionally abstract classes could be included as well.
		 *
		 * @param string $current_class     - The calling class.
		 * @param string $base_class        - The class which subclasses should be extracted.
		 * @param bool   $exclude_abstracts - Should we exclude abstract classes.
		 *
		 * @since 2.4.0
		 */
		public static function get_subclasses_of_class( string $current_class, string $base_class, bool $exclude_abstracts = true ): array {
			$matching_classes = array();
			foreach ( array_keys( self::get_class_map() ) as $class_name ) {
				if ( $current_class !== $class_name && is_subclass_of( $class_name, $base_class ) ) {
					if ( $exclude_abstracts && ( false !== strpos( $class_name, 'Abstract' ) ) ) {
						continue;
					}
					$matching_classes[ $class_name ] = $class_name;
				}
			}

			return $matching_classes;
		}

		/**
		 * Returns all the classes which are part of the given namespace.
		 *
		 * @param string $extract_namespace - The extract_namespace to search for.
		 *
		 * @return array
		 *
		 * @since 2.4.0
		 */
		public static function get_classes_by_namespace( string $extract_namespace ) {
			if ( 0 === strpos( $extract_namespace, '\\' ) ) {
				$extract_namespace = ltrim( $extract_namespace, '\\' );
			}

			$extract_namespace = rtrim( $extract_namespace, '\\' );

			$term_upper = strtoupper( $extract_namespace );

			return array_filter(
				array_keys( self::get_class_map() ),
				function ( $found_class ) use ( $term_upper ) {
					$class_name = strtoupper( $found_class );

					/**
					 * Find class name, by finding the last occurrence of the \
					 * if it is false  (from the strrchr) then class does not belong to any namespace currently.
					 */
					$esc_position = strrchr( $class_name, '\\' );

					if ( false !== $esc_position ) {
						$class_name_no_ns = substr( $esc_position, 1 );
					} else {
						return false;
					}

					if ( $class_name_no_ns &&
						$term_upper . '\\' . $class_name_no_ns === $class_name &&
						false === strpos( $class_name, strtoupper( 'Abstract' ) ) &&
						false === strpos( $class_name, strtoupper( 'Interface' ) )
					) {
						return $found_class;
					}

					return false;
				}
			);
		}

		/**
		 * Search for classes by given term.
		 *
		 * @param string $term - The term to search for.
		 *
		 * @return array
		 *
		 * @since 2.4.0
		 */
		public static function get_classes_with_term( $term ) {
			$term_upper = strtoupper( $term );

			return array_filter(
				self::get_class_map(),
				function ( $found_class ) use ( $term_upper ) {
					$class_name = strtoupper( $found_class );
					if (
						false !== strpos( $class_name, $term_upper ) &&
						false === strpos( $class_name, strtoupper( 'Abstract' ) ) &&
						false === strpos( $class_name, strtoupper( 'Interface' ) )
					) {
						return $found_class;
					}

					return false;
				}
			);
		}

		/**
		 * Adds a class (or classes) to the class map.
		 *
		 * @param array $class_add - Array with class or classes to add.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function add_to_class_map( array $class_add ) {
			if ( empty( self::$class_map ) ) {
				self::$class_map = require WP_2FA_PATH . 'vendor/composer/autoload_classmap.php';
			}

			self::$class_map = \array_merge( self::$class_map, $class_add );
		}
	}
}
