<?php
/**
 * EDD Software Licensing Plugin Updater.
 *
 * Handles automatic updates by connecting directly to the Melapress store API.
 * Based on the EDD Software Licensing updater implementation, refactored for
 * modern strict typing and namespaced for portability.
 *
 * @since      2.0.0
 * @package    wp2fa
 * @subpackage Licensing
 * @link       https://easydigitaldownloads.com/docs/software-licensing-updater-implementation-for-wordpress-plugins/
 */

declare(strict_types=1);

namespace WP2FA\Licensing;

use stdClass;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\EDD_Plugin_Updater' ) ) {

	/**
	 * Allows plugins to use their own update API.
	 *
	 * @since 2.0.0
	 */
	class EDD_Plugin_Updater {

		/**
		 * The URL of the EDD store API.
		 *
		 * @var string
		 */
		private static string $api_url = '';

		/**
		 * Data to send with API calls.
		 *
		 * @var array
		 */
		private static array $api_data = array();

		/**
		 * Path to the plugin file.
		 *
		 * @var string
		 */
		private static string $plugin_file = '';

		/**
		 * Plugin slug (directory name).
		 *
		 * @var string
		 */
		private static string $slug = '';

		/**
		 * Whether to override WP's plugin update data.
		 *
		 * @var bool
		 */
		private static bool $wp_override = false;

		/**
		 * Whether to include beta versions in updates.
		 *
		 * @var bool
		 */
		private static bool $beta = false;

		/**
		 * Cache key for failed HTTP requests.
		 *
		 * @var string
		 */
		private static string $failed_request_cache_key = '';

		/**
		 * Set up the updater with the given parameters and register hooks.
		 *
		 * @param string     $api_url     - The URL pointing to the custom API endpoint.
		 * @param array|null $api_data    - Optional data to send with API calls.
		 *
		 * @return void
		 */
		public static function setup( string $api_url, ?array $api_data = null ): void {

			global $edd_plugin_data;

			self::$api_url                  = \trailingslashit( $api_url );
			self::$api_data                 = $api_data ?? array();
			self::$wp_override              = isset( self::$api_data['wp_override'] ) ? (bool) self::$api_data['wp_override'] : false;
			self::$beta                     = ! empty( self::$api_data['beta'] );
			self::$failed_request_cache_key = Edd_Provider::CACHE_KEY_PREFIX . md5( self::$api_url );

			$edd_plugin_data[ Licensing_Factory::PLUGIN_SLUG ] = self::$api_data;

			/**
			 * Fires after the $edd_plugin_data is setup.
			 *
			 * @since 2.0.0
			 *
			 * @param array $edd_plugin_data Array of EDD SL plugin data.
			 */
			\do_action( 'post_edd_sl_plugin_updater_setup', $edd_plugin_data );

			// Set up hooks.
			self::init();
		}

		/**
		 * Set up WordPress filters to hook into WP's update process.
		 *
		 * @return void
		 */
		public static function init(): void {
			\add_filter( 'pre_set_site_transient_update_plugins', array( static::class, 'check_update' ) );
			\add_filter( 'plugins_api', array( static::class, 'plugins_api_filter' ), 10, 3 );
			\add_action( 'after_plugin_row', array( static::class, 'show_update_notification' ), 10, 2 );
			\add_action( 'admin_init', array( static::class, 'show_changelog' ) );
		}

		/**
		 * Check for updates at the defined API endpoint and modify the update array.
		 *
		 * @param mixed $transient_data - Update array built by WordPress.
		 *
		 * @return object Modified update array with custom plugin data.
		 */
		public static function check_update( $transient_data ) {

			if ( ! is_object( $transient_data ) ) {
				$transient_data = new stdClass();
			}

			if ( ! empty( $transient_data->response ) && ! empty( $transient_data->response[ Licensing_Factory::PLUGIN_BASENAME ] ) && false === self::$wp_override ) {
				return $transient_data;
			}

			$current = self::get_update_transient_data();
			if ( false !== $current && is_object( $current ) && isset( $current->new_version ) ) {
				if ( version_compare( Licensing_Factory::PLUGIN_VERSION, $current->new_version, '<' ) ) {
					$transient_data->response[ Licensing_Factory::PLUGIN_BASENAME ] = $current;
				} else {
					$transient_data->no_update[ Licensing_Factory::PLUGIN_BASENAME ] = $current;
				}
			}
			$transient_data->last_checked           = time();
			$transient_data->checked[ Licensing_Factory::PLUGIN_BASENAME ] = Licensing_Factory::PLUGIN_VERSION;

			return $transient_data;
		}

		/**
		 * Get repo API data from store and save to cache.
		 *
		 * @return object|false
		 */
		public static function get_repo_api_data() {
			$version_info = self::get_cached_version_info();

			if ( false === $version_info ) {
				$version_info = self::api_request(
					'plugin_latest_version',
					array(
						'slug' => Licensing_Factory::PLUGIN_SLUG,
						'beta' => self::$beta,
					)
				);
				if ( ! $version_info ) {
					return false;
				}

				$version_info->plugin = Licensing_Factory::PLUGIN_BASENAME;
				$version_info->id     = Licensing_Factory::PLUGIN_BASENAME;
				$version_info->tested = self::get_tested_version( $version_info );
				if ( ! isset( $version_info->requires ) ) {
					$version_info->requires = '';
				}
				if ( ! isset( $version_info->requires_php ) ) {
					$version_info->requires_php = '';
				}

				self::set_version_info_cache( $version_info );
			}

			return $version_info;
		}

		/**
		 * Gets a limited set of data from the API response for the update_plugins transient.
		 *
		 * @return stdClass|false
		 */
		private static function get_update_transient_data() {
			$version_info = self::get_repo_api_data();

			if ( ! $version_info ) {
				return false;
			}

			$limited_data               = new stdClass();
			$limited_data->slug         = Licensing_Factory::PLUGIN_SLUG;
			$limited_data->plugin       = Licensing_Factory::PLUGIN_BASENAME;
			$limited_data->url          = $version_info->url;
			$limited_data->package      = $version_info->package;
			$limited_data->icons        = self::convert_object_to_array( $version_info->icons );
			$limited_data->banners      = self::convert_object_to_array( $version_info->banners );
			$limited_data->new_version  = $version_info->new_version;
			$limited_data->tested       = $version_info->tested;
			$limited_data->requires     = $version_info->requires;
			$limited_data->requires_php = $version_info->requires_php;

			return $limited_data;
		}

		/**
		 * Gets the plugin's tested version.
		 *
		 * @param object $version_info - Version info object from API.
		 *
		 * @return string|null
		 */
		private static function get_tested_version( $version_info ) {
			if ( empty( $version_info->tested ) ) {
				return null;
			}

			list( $current_wp_version ) = explode( '-', \get_bloginfo( 'version' ) );

			if ( version_compare( $version_info->tested, $current_wp_version, '>=' ) ) {
				return $version_info->tested;
			}

			$current_version_parts = explode( '.', $current_wp_version );
			$tested_parts          = explode( '.', $version_info->tested );

			if ( isset( $current_version_parts[2] ) && $current_version_parts[0] === $tested_parts[0] && $current_version_parts[1] === $tested_parts[1] ) {
				$tested_parts[2] = $current_version_parts[2];
			}

			return implode( '.', $tested_parts );
		}

		/**
		 * Show the update notification on multisite subsites.
		 *
		 * @param string $file   - Plugin file path.
		 * @param array  $plugin - Plugin data array.
		 *
		 * @return void
		 */
		public static function show_update_notification( $file, $plugin ) {

			if ( \is_network_admin() || ! \is_multisite() ) {
				return;
			}

			if ( ! \current_user_can( 'activate_plugins' ) ) {
				return;
			}

			if ( Licensing_Factory::PLUGIN_BASENAME !== $file ) {
				return;
			}

			$update_cache = \get_site_transient( 'update_plugins' );

			if ( ! isset( $update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ] ) ) {
				if ( ! is_object( $update_cache ) ) {
					$update_cache = new stdClass();
				}
				$update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ] = self::get_repo_api_data();
			}

			if ( empty( $update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ] ) || version_compare( Licensing_Factory::PLUGIN_VERSION, $update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ]->new_version, '>=' ) ) {
				return;
			}

			printf(
				'<tr class="plugin-update-tr %3$s" id="%1$s-update" data-slug="%1$s" data-plugin="%2$s">',
				Licensing_Factory::PLUGIN_SLUG,
				$file,
				in_array( Licensing_Factory::PLUGIN_BASENAME, self::get_active_plugins(), true ) ? 'active' : 'inactive'
			);

			echo '<td colspan="3" class="plugin-update colspanchange">';
			echo '<div class="update-message notice inline notice-warning notice-alt"><p>';

			$changelog_link = '';
			if ( ! empty( $update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ]->sections->changelog ) ) {
				$changelog_link = \add_query_arg(
					array(
						'edd_sl_action' => 'view_plugin_changelog',
						'plugin'        => urlencode( Licensing_Factory::PLUGIN_BASENAME ),
						'slug'          => urlencode( Licensing_Factory::PLUGIN_SLUG ),
						'TB_iframe'     => 'true',
						'width'         => 77,
						'height'        => 911,
					),
					\self_admin_url( 'index.php' )
				);
			}
			$update_link = \add_query_arg(
				array(
					'action' => 'upgrade-plugin',
					'plugin' => urlencode( Licensing_Factory::PLUGIN_BASENAME ),
				),
				\self_admin_url( 'update.php' )
			);

			printf(
				/* translators: 1: plugin slug */
				\esc_html__( 'There is a new version of %1$s available.', 'wp-2fa' ),
				\esc_html( $plugin['Name'] )
			);

			if ( ! \current_user_can( 'update_plugins' ) ) {
				echo ' ';
				\esc_html_e( 'Contact your network administrator to install the update.', 'wp-2fa' );
			} elseif ( empty( $update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ]->package ) && ! empty( $changelog_link ) ) {
				echo ' ';
				printf(
					/* translators: 1: opening anchor tag 2: the new plugin version 3: closing anchor tag */
					\esc_html__( '%1$sView version %2$s details%3$s.', 'wp-2fa' ),
					'<a target="_blank" class="thickbox open-plugin-details-modal" href="' . \esc_url( $changelog_link ) . '">',
					\esc_html( $update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ]->new_version ),
					'</a>'
				);
			} elseif ( ! empty( $changelog_link ) ) {
				echo ' ';
				printf(
					/* translators: 1: opening anchor tag 2: the new plugin version 3: closing anchor tag 4: opening anchor tag 5: closing anchor tag */
					\esc_html__( '%1$sView version %2$s details%3$s or %4$supdate now%5$s.', 'wp-2fa' ),
					'<a target="_blank" class="thickbox open-plugin-details-modal" href="' . \esc_url( $changelog_link ) . '">',
					\esc_html( $update_cache->response[ Licensing_Factory::PLUGIN_BASENAME ]->new_version ),
					'</a>',
					'<a target="_blank" class="update-link" href="' . \esc_url( \wp_nonce_url( $update_link, 'upgrade-plugin_' . $file ) ) . '">',
					'</a>'
				);
			} else {
				printf(
					' %1$s%2$s%3$s',
					'<a target="_blank" class="update-link" href="' . \esc_url( \wp_nonce_url( $update_link, 'upgrade-plugin_' . $file ) ) . '">',
					\esc_html__( 'Update now.', 'wp-2fa' ),
					'</a>'
				);
			}

			\do_action( "in_plugin_update_message-{$file}", $plugin, $plugin );

			echo '</p></div></td></tr>';
		}

		/**
		 * Gets the plugins active in a multisite network.
		 *
		 * @return array
		 */
		private static function get_active_plugins(): array {
			$active_plugins         = (array) \get_option( 'active_plugins' );
			$active_network_plugins = (array) \get_site_option( 'active_sitewide_plugins' );

			return array_merge( $active_plugins, array_keys( $active_network_plugins ) );
		}

		/**
		 * Updates information on the "View version x.x details" page with custom data.
		 *
		 * @param mixed  $data   - Plugin data.
		 * @param string $action - API action.
		 * @param object $args   - Request arguments.
		 *
		 * @return object Modified plugin data.
		 */
		public static function plugins_api_filter( $data, $action = '', $args = null ) {

			if ( 'plugin_information' !== $action ) {
				return $data;
			}

			if ( ! isset( $args->slug ) || ( $args->slug !== Licensing_Factory::PLUGIN_SLUG ) ) {
				return $data;
			}

			$to_send = array(
				'slug'   => Licensing_Factory::PLUGIN_SLUG,
				'is_ssl' => \is_ssl(),
				'fields' => array(
					'banners' => array(),
					'reviews' => false,
					'icons'   => array(),
				),
			);

			$edd_api_request_transient = self::get_cached_version_info();

			if ( empty( $edd_api_request_transient ) ) {
				$api_response = self::api_request( 'plugin_information', $to_send );

				self::set_version_info_cache( $api_response );

				if ( false !== $api_response ) {
					$data = $api_response;
				}
			} else {
				$data = $edd_api_request_transient;
			}

			if ( isset( $data->sections ) && ! is_array( $data->sections ) ) {
				$data->sections = self::convert_object_to_array( $data->sections );
			}

			if ( isset( $data->banners ) && ! is_array( $data->banners ) ) {
				$data->banners = self::convert_object_to_array( $data->banners );
			}

			if ( isset( $data->icons ) && ! is_array( $data->icons ) ) {
				$data->icons = self::convert_object_to_array( $data->icons );
			}

			if ( isset( $data->contributors ) && ! is_array( $data->contributors ) ) {
				$data->contributors = self::convert_object_to_array( $data->contributors );
			}

			if ( ! isset( $data->plugin ) ) {
				$data->plugin = Licensing_Factory::PLUGIN_BASENAME;
			}

			if ( ! isset( $data->version ) && ! empty( $data->new_version ) ) {
				$data->version = $data->new_version;
			}

			return $data;
		}

		/**
		 * Convert objects to arrays for plugin update API compatibility.
		 *
		 * @param mixed $data - Object or array to convert.
		 *
		 * @return array
		 */
		private static function convert_object_to_array( $data ): array {
			if ( ! is_array( $data ) && ! is_object( $data ) ) {
				return array();
			}
			$new_data = array();
			foreach ( $data as $key => $value ) {
				$new_data[ $key ] = is_object( $value ) ? self::convert_object_to_array( $value ) : $value;
			}

			return $new_data;
		}

		/**
		 * Disable SSL verification in order to prevent download update failures.
		 *
		 * @param array  $args - HTTP request arguments.
		 * @param string $url  - Request URL.
		 *
		 * @return array Modified arguments.
		 */
		public static function http_request_args( $args, $url ) {
			if ( str_contains( $url, 'https://' ) && str_contains( $url, 'edd_action=package_download' ) ) {
				$args['sslverify'] = self::verify_ssl();
			}

			return $args;
		}

		/**
		 * Calls the API and, if successful, returns the object delivered by the API.
		 *
		 * @param string $action - The requested action.
		 * @param array  $data   - Parameters for the API action.
		 *
		 * @return false|object|void
		 */
		private static function api_request( $action, $data ) {
			$data = array_merge( self::$api_data, $data );

			if ( $data['slug'] !== Licensing_Factory::PLUGIN_SLUG ) {
				return;
			}

			if ( \trailingslashit( \home_url() ) === self::$api_url ) {
				return false;
			}

			if ( self::request_recently_failed() ) {
				return false;
			}

			return self::get_version_from_remote();
		}

		/**
		 * Determines if a request has recently failed.
		 *
		 * @return bool
		 */
		private static function request_recently_failed(): bool {
			$failed_request_details = \get_option( self::$failed_request_cache_key );

			if ( empty( $failed_request_details ) || ! is_numeric( $failed_request_details ) ) {
				return false;
			}

			if ( time() > $failed_request_details ) {
				\delete_option( self::$failed_request_cache_key );

				return false;
			}

			return true;
		}

		/**
		 * Logs a failed HTTP request for this API URL.
		 * Sets a timestamp for 1 hour from now to prevent repeated failed requests.
		 *
		 * @see EDD_Plugin_Updater::request_recently_failed
		 *
		 * @return void
		 */
		private static function log_failed_request(): void {
			\update_option( self::$failed_request_cache_key, strtotime( '+1 hour' ) );
		}

		/**
		 * If available, show the changelog for sites in a multisite install.
		 *
		 * @return void
		 */
		public static function show_changelog(): void {

			if ( empty( $_REQUEST['edd_sl_action'] ) || 'view_plugin_changelog' !== $_REQUEST['edd_sl_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			if ( empty( $_REQUEST['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			if ( empty( $_REQUEST['slug'] ) || Licensing_Factory::PLUGIN_SLUG !== $_REQUEST['slug'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			if ( ! \current_user_can( 'update_plugins' ) ) {
				\wp_die( \esc_html__( 'You do not have permission to install plugin updates', 'wp-2fa' ), \esc_html__( 'Error', 'wp-2fa' ), array( 'response' => 403 ) );
			}

			$version_info = self::get_repo_api_data();
			if ( isset( $version_info->sections ) ) {
				$sections = self::convert_object_to_array( $version_info->sections );
				if ( ! empty( $sections['changelog'] ) ) {
					echo '<div style="background:#fff;padding:10px;">' . \wp_kses_post( $sections['changelog'] ) . '</div>';
				}
			}

			exit;
		}

		/**
		 * Gets the current version information from the remote site.
		 *
		 * @return object|false
		 */
		private static function get_version_from_remote() {
			$api_params = array(
				'edd_action'  => 'get_version',
				'license'     => ! empty( self::$api_data['license'] ) ? self::$api_data['license'] : '',
				'item_name'   => isset( self::$api_data['item_name'] ) ? self::$api_data['item_name'] : false,
				'item_id'     => isset( self::$api_data['item_id'] ) ? self::$api_data['item_id'] : false,
				'version'     => isset( self::$api_data['version'] ) ? self::$api_data['version'] : false,
				'slug'        => Licensing_Factory::PLUGIN_SLUG,
				'author'      => self::$api_data['author'],
				'url'         => \home_url(),
				'beta'        => self::$beta,
				'php_version' => phpversion(),
				'wp_version'  => \get_bloginfo( 'version' ),
			);

			/**
			 * Filters the parameters sent in the API request.
			 *
			 * @param array  $api_params  The array of data sent in the request.
			 * @param array  $api_data    The array of data set up in the class constructor.
			 * @param string $plugin_file The full path and filename of the file.
			 */
			$api_params = \apply_filters( 'edd_sl_plugin_updater_api_params', $api_params, self::$api_data, self::$plugin_file );

			$request = \wp_remote_post(
				self::$api_url,
				array(
					'timeout'   => 15,
					'sslverify' => self::verify_ssl(),
					'body'      => $api_params,
				)
			);

			if ( \is_wp_error( $request ) || ( 200 !== \wp_remote_retrieve_response_code( $request ) ) ) {
				self::log_failed_request();

				return false;
			}

			$request = json_decode( \wp_remote_retrieve_body( $request ) );

			if ( $request && isset( $request->sections ) ) {
				$request->sections = self::safe_unserialize( $request->sections );
			} else {
				$request = false;
			}

			if ( $request && isset( $request->banners ) ) {
				$request->banners = self::safe_unserialize( $request->banners );
			}

			if ( $request && isset( $request->icons ) ) {
				$request->icons = self::safe_unserialize( $request->icons );
			}

			if ( ! empty( $request->sections ) ) {
				foreach ( $request->sections as $key => $section ) {
					$request->$key = (array) $section;
				}
			}

			return $request;
		}

		/**
		 * Get the version info from the cache, if it exists.
		 *
		 * @param string $cache_key - Optional cache key override.
		 *
		 * @return object|false
		 */
		public static function get_cached_version_info( string $cache_key = '' ) {

			if ( empty( $cache_key ) ) {
				$cache_key = self::get_cache_key();
			}

			$cache = \get_option( $cache_key );

			if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
				return false;
			}

			$cache['value'] = json_decode( $cache['value'] );
			if ( ! empty( $cache['value']->icons ) ) {
				$cache['value']->icons = (array) $cache['value']->icons;
			}

			return $cache['value'];
		}

		/**
		 * Adds the plugin version information to the database cache.
		 *
		 * @param mixed  $value     - Version info to cache.
		 * @param string $cache_key - Optional cache key override.
		 *
		 * @return void
		 */
		public static function set_version_info_cache( $value = '', string $cache_key = '' ): void {

			if ( empty( $cache_key ) ) {
				$cache_key = self::get_cache_key();
			}

			$data = array(
				'timeout' => strtotime( '+3 hours', time() ),
				'value'   => \wp_json_encode( $value ),
			);

			\update_option( $cache_key, $data, 'no' );

			// Delete the duplicate option.
			\delete_option( 'edd_api_request_' . md5( serialize( Licensing_Factory::PLUGIN_SLUG . self::$api_data['license'] . self::$beta ) ) );
		}

		/**
		 * Returns if the SSL of the store should be verified.
		 *
		 * @return bool
		 */
		private static function verify_ssl(): bool {
			return (bool) \apply_filters( 'edd_sl_api_request_verify_ssl', true, static::class );
		}

		/**
		 * Gets the unique cache key (option name) for this plugin.
		 *
		 * @return string
		 */
		private static function get_cache_key(): string {
			$string = Licensing_Factory::PLUGIN_SLUG . self::$api_data['license'] . self::$beta;

			return 'edd_sl_' . md5( serialize( $string ) );
		}

		/**
		 * Unserialize a value that came from the licensing API.
		 *
		 * `maybe_unserialize()` instantiates whatever classes the payload names,
		 * which turns a compromised or spoofed update endpoint into PHP object
		 * injection. These fields only ever carry strings and arrays, so no
		 * classes are allowed through.
		 *
		 * @param mixed $value - Value from the API response.
		 *
		 * @return mixed
		 *
		 * @since 2.4.0
		 */
		private static function safe_unserialize( $value ) {
			if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
				return $value;
			}

			$result = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

			return ( false === $result && 'b:0;' !== $value ) ? array() : $result;
		}
	}

}
