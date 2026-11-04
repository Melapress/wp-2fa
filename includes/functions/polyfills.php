<?php
/**
 * PHP 8 functions and interfaces the plugin uses, for PHP 7.4.
 *
 * WordPress adds str_starts_with() and str_contains() itself only from 5.9, and the plugin
 * supports 5.7. Loaded first thing by wp-2fa.php, before any plugin code runs, so a call made
 * while the plugin is still loading finds them too. Each is only defined when PHP (or
 * WordPress, or another plugin) has not defined it already.
 *
 * @package wp2fa
 * @since   2.6.4
 */

defined( 'ABSPATH' ) || exit;

if ( \PHP_VERSION_ID < 80000 && ! \interface_exists( 'Stringable' ) ) {
	interface Stringable {
 // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		/**
		 * Mockup function for PHP versions lower than 8.
		 *
		 * @return string
		 */
		public function __toString();
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * PHP lower than 8 is missing that function but it required in the newer versions of our plugin.
	 *
	 * @param string $haystack - The string to search in.
	 * @param string $needle - The needle to search for.
	 *
	 * @return bool
	 *
	 * @since 2.6.4
	 */
	function str_starts_with( $haystack, $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * PHP 7.4 fallback for str_contains().
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The string to search for.
	 *
	 * @return bool
	 *
	 * @since 4.2.0
	 */
	function str_contains( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}
