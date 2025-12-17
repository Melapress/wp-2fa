<?php
/**
 * Responsible for fly-out menu shown on some of the plugin pages.
 *
 * @package    wp2fa
 * @subpackage flyout
 *
 * @since 2.8.0
 *
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\FlyOut;

use WP2FA\WP2FA;
use WP2FA\Admin\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP2FA\Admin\FlyOut\FlyOut' ) ) {
	/**
	 * Generates fly-out menu on the plugin admin screen.
	 *
	 * @since 2.8.0
	 */
	class FlyOut {

		private const ENQUEUE_NAME          = 'mlp_flyout';
		private const CONFIG_TRANSIENT_NAME = \WP_2FA_PREFIX . 'flyout_config';
		private const REMOTE_CONFIG_URL     = 'https://melapress.com/downloads/plugins-files/wp-2fa-flyout-config.php';

		/**
		 * Array with the configuration of the fly-out menu
		 *
		 * @var array
		 *
		 * @since 2.8.0
		 */
		private static $config = array();

		/**
		 * Class cache for the current screen (if admin is on it)
		 *
		 * @var bool
		 *
		 * @since 2.8.0
		 */
		private static $screen = null;

		/**
		 * Inits the class and its hooks
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function init() {
			if ( ! \is_admin() ) {
				return;
			} else {
				self::load_config();
				if ( ! empty( self::$config ) ) {
					\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
					\add_action( 'admin_head', array( __CLASS__, 'admin_head' ) );
					\add_action( 'admin_footer', array( __CLASS__, 'admin_footer' ) );
				}
			}
		}

		/**
		 * Loads the external config for processing
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function load_config() {
			$config = array();

			$config = self::read_remote_config();

			if ( false === $config ) {
				return;
			}

			$defaults = array(
				'plugin_screen'     => '',
				'icon_border'       => '#0000ff',
				'icon_right'        => '40px',
				'icon_bottom'       => '40px',
				'icon_image'        => '',
				'icon_padding'      => '2px',
				'icon_size'         => '55px',
				'menu_accent_color' => '#ca4a1f',
				'custom_css'        => '',
				'menu_items'        => array(),
			);

			$config = array_merge( $defaults, (array) $config );
			if ( ! is_array( $config['plugin_screen'] ) ) {
				$config['plugin_screen'] = array( $config['plugin_screen'] );
			}

			if ( WP_Helper::is_multisite() ) {
				foreach ( $config['plugin_screen'] as $key => $value ) {
					$config['plugin_screen'][ $key ] = $value . '-network';

					if ( ! in_array( $value, WP_Helper::PLUGIN_PAGES, true ) ) {
						unset( $config['plugin_screen'][ $key ] );
					}
				}
			}

			self::$config = $config;
		}

		/**
		 * Checks the current screen and returns true if it is the plugin one
		 *
		 * @return boolean
		 *
		 * @since 2.8.0
		 */
		public static function is_plugin_screen(): bool {

			if ( \is_null( self::$screen ) ) {

				$screen       = \get_current_screen();
				self::$screen = false;

				if ( null !== $screen && in_array( $screen->id, self::$config['plugin_screen'], true ) ) {
					self::$screen = true;
				}
			}

			return self::$screen;
		}

		/**
		 * Loads the fly-out css and JS files
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function admin_enqueue_scripts() {
			if ( false === self::is_plugin_screen() ) {
				return;
			}

			\wp_enqueue_style(
				self::ENQUEUE_NAME,
				WP_2FA_URL . '/includes/classes/Admin/Fly-Out/assets/css/flyout.css',
				array(),
				WP_2FA_VERSION
			);
			\wp_enqueue_script(
				self::ENQUEUE_NAME,
				WP_2FA_URL . '/includes/classes/Admin/Fly-Out/assets/js/flyout.js',
				array(),
				WP_2FA_VERSION,
				true
			);
		}

		/**
		 * Writes additional custom code in the header of the page
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function admin_head() {
			if ( false === self::is_plugin_screen() ) {
				return;
			}

			$out  = '<style type="text/css">';
			$out .= '#mlp-flyout {
				right: ' . \sanitize_text_field( self::$config['icon_right'] ) . ';
				bottom: ' . \sanitize_text_field( self::$config['icon_bottom'] ) . ';
			}';
			$out .= '#mlp-flyout #mlp-elements-image-wrapper {
				border: ' . \sanitize_text_field( self::$config['icon_border'] ) . ';
			}';
			$out .= '#mlp-flyout #mlp-elements-button img {
				padding: ' . \sanitize_text_field( self::$config['icon_padding'] ) . ';
				width: ' . \sanitize_text_field( self::$config['icon_size'] ) . ';
				height: ' . \sanitize_text_field( self::$config['icon_size'] ) . ';
			}';
			$out .= '#mlp-flyout .mlp-elements-menu-item.accent {
				background: ' . \sanitize_text_field( self::$config['menu_accent_color'] ) . ';
			}';
			$out .= \wp_strip_all_tags( self::$config['custom_css'] );
			$out .= '</style>';

			// The output below is built from sanitized and escaped values such
			// as sanitize_text_field and wp_strip_all_tags. PHPCS cannot
			// always infer that across a large concatenated string, so the
			// OutputNotEscaped sniff is ignored here to avoid a false positive.
			// If this is refactored to echo parts individually the ignore can
			// be removed.
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Writes additional custom code in the footer of the page
		 *
		 * @return void
		 *
		 * @since 2.8.0
		 */
		public static function admin_footer() {
			if ( false === self::is_plugin_screen() ) {
				return;
			}

			$out               = '';
			$icons_url         = WP_2FA_URL . 'dist/images/';
			$default_link_item = array(
				'class'  => '',
				'href'   => '#',
				'target' => '_blank',
				'label'  => '',
				'icon'   => '',
				'data'   => '',
				'type'   => 'all',
			);

			$out .= '<div id="mlp-overlay"></div>';

			$out .= '<div id="mlp-flyout">';

			$out .= '<a href="#" id="mlp-elements-button">';
			$out .= '<span class="mlp-elements-label">Open Quick Links</span>';
			$out .= '<span id="mlp-elements-image-wrapper">';
			if ( ! empty( self::$config['icon_image'] ) ) {
				$out .= '<img src="' . \esc_url( $icons_url . self::$config['icon_image'] ) . '" alt="Open Quick Links" title="Open Quick Links">';
			}
			$out .= '</span>';
			$out .= '</a>';

			$out .= '<div id="mlp-elements-menu">';
			$i    = 0;
			foreach ( array_reverse( self::$config['menu_items'] ) as $item ) {
				$item = array_merge( $default_link_item, $item );

				if ( ( isset( $item['type'] ) && 'all' === $item['type'] ) || ( isset( $item['type'] ) && WP2FA::get_plugin_version() === $item['type'] ) ) {
					++$i;

					if ( ! empty( $item['icon'] ) && \str_starts_with( $item['icon'], 'dashicons' ) ) {
						$item['class'] .= ' mlp-elements-custom-icon';
						$item['class']  = trim( $item['class'] );
					}

					$pattern = '/^data-[a-z0-9\-]+=(["\'])([A-Za-z0-9_\-]+)\1$/';
					if ( ! preg_match( $pattern, $item['data'] ) ) {
						$item['data'] = '';
					}

					$out .= '<a ' . $item['data'] . ' href="' . \esc_url( $item['href'] ) . '" class="mlp-elements-menu-item mlp-elements-menu-item-' . $i . ' ' . \esc_attr( $item['class'] ) . '" target="' . \esc_attr( $item['target'] ) . '">';
					$out .= '<span class="mlp-elements-label visible">' . esc_html( $item['label'] ) . '</span>';
					if ( \str_starts_with( $item['icon'], 'dashicons' ) ) {
						$out .= '<span class="dashicons ' . \sanitize_text_field( $item['icon'] ) . '"></span>';
					} elseif ( ! empty( $item['icon'] ) ) {
						$out .= '<span class="mlp-elements-icon"><img src="' . \esc_url( $icons_url . $item['icon'] ) . '"></span>';
					}
					$out .= '</a>';
				}
			} // foreach
			$out .= '</div>'; // #mlp-elements-menu

			$out .= '</div>'; // #mlp-flyout

			// The footer output is built from escaped and sanitized values such
			// as esc_url, esc_attr and esc_html. PHPCS cannot reliably infer
			// that across concatenation, so the OutputNotEscaped sniff is
			// ignored here to avoid noise.
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Reads the config file remotely and sets 2 days transient for caching. If for some reason cant read the remote - false is returned
		 *
		 * @return bool|array|false
		 *
		 * @since 2.8.0
		 */
		public static function read_remote_config() {
			$config = \get_transient( self::CONFIG_TRANSIENT_NAME );

			// Transient used to throttle remote requests on repeated failures.
			$timeout_transient_key = WP_2FA_PREFIX . 'flyout_config_timeout';
			// Allow filter to adjust timeout TTL (default 1 hour).
			$timeout_ttl = (int) apply_filters( WP_2FA_PREFIX . 'flyout_remote_timeout_ttl', HOUR_IN_SECONDS );

			// If we recently had a failure, bail early to avoid hammering remote host.
			if ( \get_transient( $timeout_transient_key ) ) {
				// Intentionally not logging here to avoid flooding logs when the
				// timeout transient is active.
				return false;
			}

			if ( false === $config || empty( $config ) ) {
				$request_args = \apply_filters(
					WP_2FA_PREFIX . 'flyout_remote_request_args',
					array(
						'timeout'     => 30,
						'redirection' => 5,
						'user-agent'  => 'WP-2FA-FlyOut/' . WP_2FA_VERSION,
					)
				);

				$api_response = \wp_remote_get( self::REMOTE_CONFIG_URL, $request_args );

				if ( \is_wp_error( $api_response ) ) {
					self::log_remote_error( 'Request error: ' . $api_response->get_error_message() );
					// Set timeout transient so we don't retry immediately.
					\set_transient( $timeout_transient_key, true, $timeout_ttl );
					return false;
				}

				$response_code = \wp_remote_retrieve_response_code( $api_response );

				if ( 200 !== (int) $response_code ) {
					self::log_remote_error( 'Unexpected response code: ' . $response_code );
					// Set timeout transient so we don't retry immediately.
					\set_transient( $timeout_transient_key, true, $timeout_ttl );
					return false;
				}

				$config_body = \wp_remote_retrieve_body( $api_response );
				if ( empty( $config_body ) ) {
					self::log_remote_error( 'Empty response body.' );
					// Set timeout transient so we don't retry immediately.
					\set_transient( $timeout_transient_key, true, $timeout_ttl );
					return false;
				}

				$decoded_config = \json_decode( $config_body, true );
				if ( null === $decoded_config ) {
					self::log_remote_error( 'Invalid JSON payload.' );
					// Set timeout transient so we don't retry immediately.
					\set_transient( $timeout_transient_key, true, $timeout_ttl );
					return false;
				}

				\set_transient( self::CONFIG_TRANSIENT_NAME, $config_body, \DAY_IN_SECONDS * 3 );

				return $decoded_config;
			}

			$config = json_decode( $config, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				self::log_remote_error( 'Cached config decode failed.' );
				$config = false;
			}

			return $config;
		}

		/**
		 * Logs remote fetch issues for debugging.
		 *
		 * @param string $message Error details.
		 *
		 * @return void
		 *
		 * @since 3.1.0
		 */
		private static function log_remote_error( string $message ): void {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// Logging is conditional on WP_DEBUG and intended for
				// developer troubleshooting. Allow error_log in this context;
				// ignore the PHPCS development-function sniff for this helper
				// call.
				\error_log( '[WP 2FA FlyOut] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			\do_action( WP_2FA_PREFIX . 'flyout_remote_error', $message );
		}
	}
}
