<?php
/**
 * Responsible for date / time manipulation.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 * @since      1.4.2
 */

declare(strict_types=1);

namespace WP2FA\Utils;

use WP2FA\Admin\Helpers\User_Helper;

if ( ! class_exists( '\WP2FA\Utils\Date_Time_Utils' ) ) {
	/**
	 * Utility class for date and time manipulation, format conversion and so on.
	 *
	 * @package WP2FA\Utils
	 *
	 * @since 1.4.2
	 */
	class Date_Time_Utils {

		/**
		 * Formats the date string
		 *
		 * @param string|null $grace_policy Grace policy value.
		 * @param int         $grace_expiry Expiration time as unix based timestamp.
		 *
		 * @return string Translated grace period expiration string.
		 *
		 * @since 1.4.2
		 */
		public static function format_grace_period_expiration_string( $grace_policy = null, $grace_expiry = -1 ) {
			if ( null === $grace_policy ) {
				$grace_policy = Settings_Utils::get_setting_role( User_Helper::get_user_role(), 'grace-policy' );
			}

			if ( 'no-grace-period' === $grace_policy ) {
				return \esc_html__( 'no grace period', 'wp-2fa' );
			}

			if ( -1 === $grace_expiry ) {
				if ( 'use-grace-period' === $grace_policy ) {
					$grace_period             = Settings_Utils::get_setting_role( User_Helper::get_user_role(), 'grace-period' );
					$grace_period_denominator = Settings_Utils::get_setting_role( User_Helper::get_user_role(), 'grace-period-denominator' );
					$grace_period_string      = sanitize_text_field( $grace_period . ' ' . $grace_period_denominator );
					$grace_expiry             = (int) strtotime( $grace_period_string );
				} else {
					// this will probably never be reached, leaving it here for now just in case.
					$grace_expiry = time();
				}
			}

			$grace_expiry = \gmdate( 'Y-m-d H:i:s', $grace_expiry );

			$expiration_date_time = implode(
				' ',
				array(
					// Purposefully not using the SettingsUtil class as we don't want this prefixed.
					\get_date_from_gmt( $grace_expiry, get_option( 'date_format' ) ),
					\get_date_from_gmt( $grace_expiry, get_option( 'time_format' ) ),
					// wp_date( get_option( 'date_format' ), $grace_expiry ),
					// wp_date( get_option( 'time_format' ), $grace_expiry ),
				)
			);

			/* translators: Grace period expiration label. %s: Date and time formatted using WordPress date and time formats. */
			return sprintf( \esc_html__( 'before %s', 'wp-2fa' ), esc_html( $expiration_date_time ) );
		}
	}
}
