<?php
/**ValidatorAbstract migration class.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2026 Melapress
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
						$variable    = \is_string( $variable ) ? \sanitize_email( $variable ) : '';
						$valid       = self::validate_email( $variable );
						$default_val = '';
						break;

					/*
					 * A valid integer or boolean comes back as one - 0 and false
					 * included. Handing back what was posted meant a valid "false"
					 * was stored as the string "false", which reads as true.
					 */
					case 'int':
					case 'integer':
						$valid       = self::validate_integer( $variable );
						$variable    = $valid ? (int) self::filter_validate( $variable, 'int' ) : $variable;
						$default_val = 0;
						break;
					case 'bool':
					case 'boolean':
						$valid       = self::validate_boolean( $variable );
						$variable    = $valid ? (bool) self::filter_validate( $variable, 'bool' ) : $variable;
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
		 * @param mixed $variable - Value which needs to be validated.
		 *
		 * @return bool
		 *
		 * @since 2.8.0
		 */
		public static function validate_email( $variable ): bool {
			if ( null === self::filter_validate( $variable, 'email' ) ) {
				self::$errors[] = 'Variable is not valid e-mail' . "\n";

				return false;
			}

			return true;
		}

		/**
		 * Validates integer
		 *
		 * @param mixed $variable - Value which needs to be validated: an int or a string of one.
		 *
		 * @return bool
		 *
		 * @since 2.8.0
		 */
		public static function validate_integer( $variable ): bool {
			if ( null === self::filter_validate( $variable, 'int' ) ) {
				self::$errors[] = 'Variable is not valid integer' . "\n";

				return false;
			}

			return true;
		}

		/**
		 * Validates boolean
		 *
		 * No union type on the parameter: the plugin still supports PHP 7.4,
		 * which cannot parse one, and the whole class failed to load there.
		 *
		 * @param mixed $variable - Value which needs to be validated: a bool or a string of one.
		 *
		 * @return bool
		 *
		 * @since 2.8.0
		 */
		public static function validate_boolean( $variable ): bool {
			if ( null === self::filter_validate( $variable, 'bool' ) ) {
				self::$errors[] = 'Variable is not valid boolean' . "\n";

				return false;
			}

			return true;
		}

		/**
		 * Uses standard PHP filter validation
		 *
		 * Answers with the filtered value, or null when the value is not valid.
		 * Casting the filter's answer to bool made a valid 0 or false
		 * indistinguishable from a failure.
		 *
		 * @param mixed  $variable - The value which needs to be validated.
		 * @param string $type - The type of the variable - using that info method knows which validation to execute.
		 *
		 * @return mixed
		 *
		 * @since 2.8.0
		 */
		private static function filter_validate( $variable, string $type ) {
			switch ( $type ) {
				case 'email':
					return \is_string( $variable ) ? \filter_var( $variable, \FILTER_VALIDATE_EMAIL, \FILTER_NULL_ON_FAILURE ) : null;
				case 'boolean':
				case 'bool':
					if ( \is_bool( $variable ) ) {
						return $variable;
					}

					return \is_string( $variable ) || \is_int( $variable ) ? \filter_var( $variable, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE ) : null;
				case 'integer':
				case 'int':
					// true would pass the filter as 1: a boolean is not an integer.
					return \is_string( $variable ) || \is_int( $variable ) ? \filter_var( $variable, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE ) : null;
				default:
					return null;
			}
		}
	}
}
