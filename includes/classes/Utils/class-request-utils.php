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
		 * Forwarded addresses are trusted only from explicitly configured proxies.
		 * Private addresses alone do not identify a trusted reverse proxy.
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function get_ip(): string {
			$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? trim( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			if ( false === filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
				return '';
			}

			/**
			 * Exact proxy IP addresses authorized to supply X-Forwarded-For.
			 *
			 * Include every trusted hop in a proxy chain. An empty list ignores
			 * forwarded headers, including on private networks.
			 *
			 * @param string[] $proxies Trusted IPv4 or IPv6 addresses (not CIDR ranges).
			 */
			$proxies = \apply_filters( 'wp_2fa_trusted_proxies', array() );
			$trusted = array();
			foreach ( is_array( $proxies ) ? $proxies : array() as $proxy ) {
				if ( is_string( $proxy ) && false !== filter_var( $proxy, FILTER_VALIDATE_IP ) ) {
					$trusted[] = inet_pton( $proxy );
				}
			}
			$ip = $remote_addr;
			if ( ! in_array( inet_pton( $ip ), $trusted, true ) || empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) || ! is_string( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				return $ip;
			}

			$hops = array_reverse( array_map( 'trim', explode( ',', \wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
			foreach ( $hops as $hop ) {
				// Stop at the first untrusted peer, including a private-network client.
				if ( false === filter_var( $hop, FILTER_VALIDATE_IP ) ) {
					break;
				}
				$ip = $hop;
				if ( ! in_array( inet_pton( $hop ), $trusted, true ) ) {
					break;
				}
			}
			return $ip;
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
