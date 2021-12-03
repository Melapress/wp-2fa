<?php

namespace WP2FA\Utils;

/**
 * Utility class to extract info from current request.
 *
 * @package WP2FA\Utils
 * @since 2.0.0
 */
class RequestUtils {

	/**
	 * Extracts the IP address for the currently browsing user
	 *
	 * @return string
	 *
	 * @since 2.0.0
	 */
	public static function get_ip() {
		foreach (
			array(
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'REMOTE_ADDR'
			) as $key
		) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( array_map( 'trim', explode( ',', $_SERVER[ $key ] ) ) as $ip ) { // @codingStandardsIgnoreLine
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}
	}

	/**
	 * Extracts the User agent for the currently request.
	 *
	 * @return string
	 *
	 * @since 2.0.0
	 */
	public static function get_user_agent() {
		if ( ! array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) ) {
			return '';
		}

		return trim( $_SERVER['HTTP_USER_AGENT'] );
	}
}
