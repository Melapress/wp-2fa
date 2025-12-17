<?php
/**
 * Responsible for the Passkeys extension plugin settings
 *
 * @package    wp2fa
 * @subpackage passkeys
 * @since 3.0.0
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Methods\Passkeys;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Passkeys WebAuthn
 */
if ( ! class_exists( '\WP2FA\Methods\Passkeys\Web_Authn_Exception' ) ) {

	/**
	 * Throws Web Authn exceptions
	 *
	 * @since 3.0.0
	 */
	class Web_Authn_Exception extends \Exception {

		public const INVALID_DATA            = 1;
		public const INVALID_TYPE            = 2;
		public const INVALID_CHALLENGE       = 3;
		public const INVALID_ORIGIN          = 4;
		public const INVALID_RELYING_PARTY   = 5;
		public const INVALID_SIGNATURE       = 6;
		public const INVALID_PUBLIC_KEY      = 7;
		public const CERTIFICATE_NOT_TRUSTED = 8;
		public const USER_PRESENT            = 9;
		public const USER_VERIFICATED        = 10;
		public const SIGNATURE_COUNTER       = 11;
		public const CRYPTO_STRONG           = 13;
		public const BYTE_BUFFER             = 14;
		public const CBOR                    = 15;
		public const ANDROID_NOT_TRUSTED     = 16;

		/**
		 * Default constructor
		 *
		 * @param string  $message - The message of the exception.
		 * @param integer $code -  The code of the exception.
		 * @param [type]  $previous - Previous exception.
		 *
		 * @since 3.0.0
		 */
		public function __construct( $message = '', $code = 0, $previous = null ) {
			parent::__construct( $message, $code, $previous );
		}
	}
}
