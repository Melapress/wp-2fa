<?php
/**
 * Responsible for the requests.
 *
 * @package    wp2fa
 * @subpackage utils
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 * @since      2.0.0
 */

declare(strict_types=1);

namespace WP2FA\Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Utils\Request_Utils' ) ) {

	/**
	 * Utility class to extract info from current request.
	 *
	 * @package WP2FA\Utils
	 * @since 2.0.0
	 */
	class Request_Utils {

		/**
		 * Extracts the IP address for the currently browsing user.
		 *
		 * Security: forwarded headers (X-Forwarded-For, etc.) are only trusted when
		 * REMOTE_ADDR is a private/reserved IP, indicating a reverse proxy.
		 * When reading forwarded headers, the rightmost public IP is used (closest
		 * to the trusted infrastructure) to prevent client-side spoofing.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function get_ip(): string {
			$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

			// If REMOTE_ADDR is a valid public IP, use it directly — no proxy involved.
			if ( filter_var( $remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
				return $remote_addr;
			}

			// REMOTE_ADDR is private/reserved, meaning we are behind a reverse proxy.
			// Read forwarded headers and pick the rightmost valid public IP.
			$forwarded_headers = array(
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'HTTP_CLIENT_IP',
				'HTTP_X_CLUSTER_CLIENT_IP',
			);

			foreach ( $forwarded_headers as $key ) {
				if ( array_key_exists( $key, $_SERVER ) ) {
					$ips = array_map( 'trim', explode( ',', \wp_unslash( $_SERVER[ $key ] ) ) );
					// Iterate from right to left — rightmost entries are added by trusted proxies.
					$ips = array_reverse( $ips );
					foreach ( $ips as $ip ) {
						if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
							return $ip;
						}
					}
				}
			}

			// Fallback: return REMOTE_ADDR even if private (e.g. local/dev environments).
			return filter_var( $remote_addr, FILTER_VALIDATE_IP ) !== false ? $remote_addr : '';
		}

		/**
		 * Extracts the User agent for the current request.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function get_user_agent(): string {
			return array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) ? trim( (string) \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		}
	}
}
