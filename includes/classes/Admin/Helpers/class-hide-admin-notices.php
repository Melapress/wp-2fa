<?php
/**
 * Responsible for hiding unrelated admin notices on the plugin's own screens.
 *
 * @package    wp2fa
 * @subpackage helpers
 * @since      4.2.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Helpers\Hide_Admin_Notices' ) ) {

	/**
	 * Keeps the plugin's own screens free of unrelated admin notices.
	 *
	 * @since 4.2.0
	 */
	class Hide_Admin_Notices {

		/**
		 * The other Melapress plugins, by wordpress.org slug.
		 *
		 * Their notices are kept on these screens: a customer running two of our
		 * plugins should still be told that one of them needs a licence renewing
		 * or has finished an upgrade, and silencing our own family made the
		 * plugin look like the thing that had gone quiet.
		 *
		 * Premium builds install under the same slug with "-premium" appended,
		 * which is handled where this list is read. The list is a fast path
		 * rather than the whole answer — a directory renamed on the way in still
		 * matches through the plugin header, see is_sibling_plugin_dir().
		 *
		 * @var string[]
		 *
		 * @since 4.2.0
		 */
		private const SIBLING_PLUGIN_SLUGS = array(
			'admin-notices-manager',
			'melapress-file-monitor',
			'melapress-login-security',
			'melapress-role-editor',
			'website-file-changes-monitor',
			'wp-2fa',
			'wp-security-audit-log',
		);

		/**
		 * Plugin directories that belong to us, resolved once per request.
		 *
		 * @var array<string, bool>|null
		 *
		 * @since 4.2.0
		 */
		private static $sibling_dirs = null;

		/**
		 * Remove all unrelated notices from the plugin's own pages.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		public static function hide_unrelated_notices() {
			if ( ! WP_Helper::is_admin_page() ) {
				return;
			}

			foreach ( array( 'user_admin_notices', 'admin_notices', 'all_admin_notices', 'network_admin_notices' ) as $action ) {
				self::remove_unrelated_actions( $action );
			}
		}

		/**
		 * Remove unrelated notices registered on one action.
		 *
		 * @param string $action - The name of the action.
		 *
		 * @return void
		 *
		 * @since 4.2.0
		 */
		private static function remove_unrelated_actions( $action ) {
			global $wp_filter;

			if ( empty( $wp_filter[ $action ]->callbacks ) || ! is_array( $wp_filter[ $action ]->callbacks ) ) {
				return;
			}

			foreach ( $wp_filter[ $action ]->callbacks as $priority => $hooks ) {
				if ( ! is_array( $hooks ) ) {
					continue;
				}

				foreach ( $hooks as $name => $arr ) {
					if ( self::is_own_callback( $name, $arr ) || self::is_sibling_callback( $arr ) ) {
						continue;
					}

					unset( $wp_filter[ $action ]->callbacks[ $priority ][ $name ] );
				}
			}
		}

		/**
		 * Whether a registered notice callback belongs to this plugin.
		 *
		 * The identity is derived from the callback itself rather than trusting
		 * the array key. Two reasons:
		 *
		 * - The key is not necessarily a string. WordPress keys its callbacks by
		 *   a computed id, but anything that appends straight into
		 *   `$wp_filter[ $hook ]->callbacks[ $priority ][]` — a pattern several
		 *   notice-managing plugins use — gets an integer key instead. This file
		 *   declares strict_types, so handing that integer to strpos() would be a
		 *   fatal TypeError rather than a coercion, taking down every plugin
		 *   admin screen for anyone with such a plugin installed.
		 *
		 * - A static callback registered as `array( 'WP2FA\Foo', 'bar' )` has a
		 *   string class name in the callback, not an object, and matching only
		 *   objects would drop one of this plugin's own notices.
		 *
		 * @param string|int $name - Key the callback is registered under.
		 * @param mixed      $arr  - The registration record.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function is_own_callback( $name, $arr ): bool {
			if ( is_string( $name ) && false !== strpos( $name, 'WP2FA' ) ) {
				return true;
			}

			if ( ! is_array( $arr ) || ! isset( $arr['function'] ) ) {
				return false;
			}

			$callback = $arr['function'];

			if ( is_string( $callback ) ) {
				return false !== strpos( $callback, 'WP2FA' );
			}

			if ( is_array( $callback ) && isset( $callback[0] ) ) {
				$class = is_object( $callback[0] )
					? get_class( $callback[0] )
					: ( is_string( $callback[0] ) ? $callback[0] : '' );

				return '' !== $class && false !== strpos( $class, 'WP2FA' );
			}

			// A closure or invokable carries no name to match on.
			return false;
		}

		/**
		 * Whether a notice callback comes from another Melapress plugin.
		 *
		 * Decided by where the callback is defined rather than what it is
		 * called. Our plugins prefix their classes half a dozen different ways —
		 * WSAL, WP2FA, MLS, ANM and so on — and a closure or a plain function has
		 * no prefix at all, so matching on names would keep some of our notices
		 * and drop others. The file a callback lives in says plainly which plugin
		 * registered it.
		 *
		 * @param mixed $arr - The registration record.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function is_sibling_callback( $arr ): bool {
			if ( ! is_array( $arr ) || ! isset( $arr['function'] ) ) {
				return false;
			}

			$file = self::callback_source_file( $arr['function'] );

			if ( '' === $file ) {
				return false;
			}

			return self::is_sibling_plugin_dir( self::plugin_dir_from_file( $file ) );
		}

		/**
		 * The file a callback is defined in.
		 *
		 * @param mixed $callback - Anything WordPress accepts as a callback.
		 *
		 * @return string Absolute path, or '' when it cannot be resolved.
		 *
		 * @since 4.2.0
		 */
		private static function callback_source_file( $callback ): string {
			try {
				if ( $callback instanceof \Closure ) {
					$reflection = new \ReflectionFunction( $callback );
				} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
					$reflection = new \ReflectionMethod( $callback );
				} elseif ( is_string( $callback ) ) {
					if ( ! function_exists( $callback ) ) {
						return '';
					}
					$reflection = new \ReflectionFunction( $callback );
				} elseif ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
					$reflection = new \ReflectionMethod( $callback[0], (string) $callback[1] );
				} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
					$reflection = new \ReflectionMethod( $callback, '__invoke' );
				} else {
					return '';
				}
			} catch ( \ReflectionException $e ) {
				// A callback we cannot look at is one we cannot vouch for.
				return '';
			}

			$file = $reflection->getFileName();

			return is_string( $file ) ? \wp_normalize_path( $file ) : '';
		}

		/**
		 * The plugin directory a file sits in, if any.
		 *
		 * @param string $file - Absolute path, already normalised.
		 *
		 * @return string Directory name directly under the plugins folder, or ''.
		 *
		 * @since 4.2.0
		 */
		private static function plugin_dir_from_file( string $file ): string {
			if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
				return '';
			}

			$plugins_root = \trailingslashit( \wp_normalize_path( WP_PLUGIN_DIR ) );

			if ( 0 !== strpos( $file, $plugins_root ) ) {
				return '';
			}

			$relative = substr( $file, strlen( $plugins_root ) );
			$segments = explode( '/', $relative );

			// A single-file plugin has no directory of its own to judge.
			return count( $segments ) > 1 ? $segments[0] : '';
		}

		/**
		 * Whether a plugin directory is one of ours.
		 *
		 * @param string $dir - Directory name under the plugins folder.
		 *
		 * @return bool
		 *
		 * @since 4.2.0
		 */
		private static function is_sibling_plugin_dir( string $dir ): bool {
			if ( '' === $dir ) {
				return false;
			}

			if ( null === self::$sibling_dirs ) {
				self::$sibling_dirs = self::resolve_sibling_dirs();
			}

			return ! empty( self::$sibling_dirs[ $dir ] );
		}

		/**
		 * Work out which installed plugin directories belong to us.
		 *
		 * The known slugs answer for a normal install, with "-premium" allowed on
		 * the end because that is how the paid builds are packaged. The plugin
		 * headers answer for everything else — a directory renamed by hand, a
		 * build we ship later, a bundle installed under its own name — by looking
		 * for us as the author.
		 *
		 * @return array<string, bool>
		 *
		 * @since 4.2.0
		 */
		private static function resolve_sibling_dirs(): array {
			$dirs = array();

			foreach ( self::SIBLING_PLUGIN_SLUGS as $slug ) {
				$dirs[ $slug ]              = true;
				$dirs[ $slug . '-premium' ] = true;
			}

			if ( ! function_exists( 'get_plugins' ) ) {
				$plugin_admin = ABSPATH . 'wp-admin/includes/plugin.php';

				if ( ! is_readable( $plugin_admin ) ) {
					return $dirs;
				}

				require_once $plugin_admin;
			}

			foreach ( \get_plugins() as $plugin_file => $plugin_data ) {
				$dir = dirname( \wp_normalize_path( $plugin_file ) );

				if ( '.' === $dir || isset( $dirs[ $dir ] ) ) {
					continue;
				}

				$author = strtolower( ( $plugin_data['Author'] ?? '' ) . ' ' . ( $plugin_data['AuthorURI'] ?? '' ) );

				if ( false !== strpos( $author, 'melapress' ) || false !== strpos( $author, 'wp white security' ) ) {
					$dirs[ $dir ] = true;
				}
			}

			return $dirs;
		}
	}
}
