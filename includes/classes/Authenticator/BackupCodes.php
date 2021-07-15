<?php // phpcs:ignore
/**
 * Class for handling backup codes
 *
 * @since 0.1-dev
 *
 * @package WP2FA
 */

namespace WP2FA\Authenticator;

use \WP2FA\Authenticator\Authentication as Authentication;

/**
 * Backup code class, for handling backup code generation and such.
 */
class BackupCodes {

	/**
	 * Key used for backup codes
	 *
	 * @var string
	 */
	const BACKUP_CODES_META_KEY = 'wp_2fa_backup_codes';

	/**
	 * The number backup codes.
	 *
	 * @type int
	 */
	const NUMBER_OF_CODES = 10;

	/**
	 * Lets build!
	 */
	public function __construct() {
		add_action( 'wp_ajax_run_ajax_generate_json', array( $this, 'run_ajax_generate_json' ) );
	}

	/**
	 * Generate backup codes
	 *
	 * @param  object $user User data.
	 * @param  string $args possible args.
	 */
	public static function generate_codes( $user, $args = '' ) {
		$codes        = array();
		$codes_hashed = array();

		// Check for arguments.
		if ( isset( $args['number'] ) ) {
			$num_codes = (int) $args['number'];
		} else {
			$num_codes = self::NUMBER_OF_CODES;
		}

		// Append or replace (default).
		if ( isset( $args['method'] ) && 'append' === $args['method'] ) {
			$codes_hashed = (array) get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );
		}

		for ( $i = 0; $i < $num_codes; $i++ ) {
			$code           = Authentication::get_code();
			$codes_hashed[] = wp_hash_password( $code );
			$codes[]        = $code;
			unset( $code );
		}

		update_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, $codes_hashed );

		// Unhashed.
		return $codes;
	}

	/**
	 * Generate codes and check remaining amount for user.
	 */
	public static function run_ajax_generate_json() {
		if ( isset( $_POST['user_id'] ) ) {
			$user_id = (int) $_POST['user_id'];
			$user    = get_user_by( 'id', sanitize_text_field( $user_id ) );
		} else {
			$user = wp_get_current_user();
		}

		check_ajax_referer( 'wp-2fa-backup-codes-generate-json-' . $user->ID, 'nonce' );

		// Setup the return data.
		$codes = self::generate_codes( $user );
		$count = self::codes_remaining_for_user( $user );
		$i18n  = array(
			'count' => esc_html(
				sprintf(
					/* translators: %s: count */
					_n( '%s unused code remaining.', '%s unused codes remaining.', $count, 'wp-2fa' ),
					$count
				)
			),
			/* translators: %s: the site's domain */
			'title' => esc_html__( 'Two-Factor Backup Codes for %s', 'wp-2fa' ),
		);

		// Send the response.
		wp_send_json_success(
			array(
				'codes' => $codes,
				'i18n'  => $i18n,
			)
		);
	}

	/**
	 * Grab number of unused backup codes within the users possition.
	 *
	 * @param  object $user User data.
	 * @return int          Count of codes.
	 */
	public static function codes_remaining_for_user( $user ) {
		$backup_codes = get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );
		if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
			return count( $backup_codes );
		}
		return 0;
	}

	/**
	 * Validate backup codes
	 *
	 * @param  object $user User data.
	 * @param  string $code The code we are checking.
	 * @return bool   Is is valid or not.
	 */
	public static function validate_code( $user, $code ) {
		$backup_codes = get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );
		if ( is_array( $backup_codes ) && ! empty( $backup_codes ) ) {
			foreach ( $backup_codes as $code_index => $code_hashed ) {
				if ( wp_check_password( $code, $code_hashed, $user->ID ) ) {
					self::delete_code( $user, $code_hashed );
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Delete code once its used.
	 *
	 * @param  object $user User data.
	 * @param  string $code_hashed Code to delete.
	 */
	public static function delete_code( $user, $code_hashed ) {
		$backup_codes = get_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, true );

		// Delete the current code from the list since it's been used.
		$backup_codes = array_flip( $backup_codes );
		unset( $backup_codes[ $code_hashed ] );
		$backup_codes = array_values( array_flip( $backup_codes ) );

		// Update the backup code master list.
		update_user_meta( $user->ID, self::BACKUP_CODES_META_KEY, $backup_codes );
	}

}
