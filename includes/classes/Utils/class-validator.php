<?php
/**ValidatorAbstract migration class.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 *
 * @since 2.8.0
 */

declare(strict_types=1);

namespace WP2FA\Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Validator class
 */
if ( ! class_exists( '\WP2FA\Utils\Validator' ) ) {

	/**
	 * Provides validation functionalities for the plugin
	 *
	 * @since 2.8.0
	 */
	class Validator {

		/**
		 * All the errors collected by the validator
		 *
		 * @var array
		 *
		 * @since 2.1.0
		 */
		private static $errors = array();

		/**
		 * Validates variables against given rules
		 *
		 * @param mixed  $variable - Variable to validate.
		 * @param string $type - Type to be used for validation.
		 * @param bool   $default - If set to true, will return default value based on given validation type.
		 * @param mixed  $default_value - If the default value to return.
		 *
		 * @return mixed
		 *
		 * @since 2.8.0
		 */
		public static function validate( $variable, string $type, bool $default = false, $default_value = null ) {
			$valid       = true;
			$default_val = null;

			if ( empty( $type ) ) {
				$valid = false;
			} else {
				$type = strval( $type );
				switch ( $type ) {
					case 'slug':
					case 'string':
						$default_val = '';
						if ( ! isset( $variable ) ) {
							$valid          = false;
							self::$errors[] = 'Variable is not set';
						} else {
							$variable = \sanitize_text_field( \wp_unslash( $variable ) );
						}
						break;
					case 'email':
						$variable    = \sanitize_email( $variable );
						$valid       = self::validate_email( $variable );
						$default_val = '';
						break;
					case 'int':
					case 'integer':
						$valid       = self::validate_integer( $variable );
						$default_val = 0;
						break;
					case 'bool':
					case 'boolean':
						$valid       = self::validate_boolean( $variable );
						$default_val = false;
						break;
					default:
						$valid          = false;
						self::$errors[] = 'No rules are set - nothing to test against';
						break;
				}
			}

			if ( ! $valid && $default ) {
				return $default_value ?? $default_val;
			} elseif ( ! $valid ) {
				return false;
			}

			return $variable;
		}

		/**
		 * Validates email
		 *
		 * @param string $variable - Value which needs to be validated.
		 *
		 * @return bool
		 *
		 * @since 2.8.0
		 */
		public static function validate_email( string $variable ): bool {
			$valid = true;

			if ( false === ( $valid = self::filter_validate( $variable, 'email' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
				self::$errors[] = 'Variable is not valid e-mail' . "\n";
				$valid          = false;
			}

			return $valid;
		}

		/**
		 * Validates integer
		 *
		 * @param string $variable - Value which needs to be validated.
		 *
		 * @return bool
		 *
		 * @since 2.8.0
		 */
		public static function validate_integer( string $variable ): bool {
			$valid = true;

			if ( false === ( $valid = self::filter_validate( $variable, 'int' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
				self::$errors[] = 'Variable is not valid integer' . "\n";
				$valid          = false;
			}

			return $valid;
		}

		/**
		 * Validates boolean
		 *
		 * @param string $variable - Value which needs to be validated.
		 *
		 * @return bool
		 *
		 * @since 2.8.0
		 */
		public static function validate_boolean( string $variable ): bool {
			$valid = true;

			if ( false === ( $valid = self::filter_validate( $variable, 'bool' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
				self::$errors[] = 'Variable is not valid boolean' . "\n";
				$valid          = false;
			}

			return $valid;
		}

		/**
		 * Uses standard PHP filter validation
		 *
		 * @param mixed  $variable - The value which needs to be validated.
		 * @param string $type - The type of the variable - using that info method knows which validation to execute.
		 *
		 * @return bool
		 *
		 * @since 2.8.0
		 */
		private static function filter_validate( $variable, string $type ): bool {
			$result = false;

			switch ( $type ) {
				case 'email':
					$result = (bool) filter_var( $variable, \FILTER_VALIDATE_EMAIL );
					break;
				case 'boolean':
				case 'bool':
					$result = (bool) filter_var( $variable, \FILTER_VALIDATE_BOOLEAN );
					break;
				case 'integer':
				case 'int':
					$result = (bool) filter_var( $variable, \FILTER_VALIDATE_INT );
					break;
				default:
					// code...
					break;
			}

			return $result;
		}
	}
}
