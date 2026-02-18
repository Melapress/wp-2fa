<?php
/**
 * Passkeys formatters
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Passkeys\Format;

use WP2FA\Admin\Methods\passkeys\Authenticator_Data;

/**
 * Responsible for default format
 *
 * @since 3.0.0
 */
class None extends Format_Base {

	/**
	 * Default constructor
	 *
	 * @param Array              $attestion_object - Default comment.
	 * @param Authenticator_Data $authenticator_data - Default comment.
	 *
	 * @since 3.1.0
	 */
	public function __construct( $attestion_object, Authenticator_Data $authenticator_data ) {
		parent::__construct( $attestion_object, $authenticator_data );
	}

	/**
	 * Returns the key certificate in PEM format
	 *
	 * @return null
	 *
	 * @since 3.1.0
	 */
	public function get_certificate_pem() {
		return null;
	}

	/**
	 * Validate attestation
	 *
	 * @param string $client_data_hash - The client hash.
	 *
	 * @return boolean
	 *
	 * @since 3.1.0
	 */
	public function validate_attestation( $client_data_hash ) {
		return true;
	}

	/**
	 * Validates the certificate against root certificates.
	 * Format 'none' does not contain any ca, so always false.
	 *
	 * @param array $root_cas - The root cas.
	 *
	 * @return boolean
	 *
	 * @since 3.1.0
	 */
	public function validate_root_certificate( $root_cas ) {
		return false;
	}
}
