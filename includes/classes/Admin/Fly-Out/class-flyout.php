<?php
/**
 * Responsible for fly-out menu shown on some of the plugin pages.
 *
 * @package    wp2fa
 * @subpackage flyout
 *
 * @since 2.8.0
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\FlyOut;

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

				if ( in_array( $screen->id, self::$config['plugin_screen'] ) ) {
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
			$out .= '#mlp-flyout #mlp-elmnts-image-wrapper {
				border: ' . \sanitize_text_field( self::$config['icon_border'] ) . ';
			}';
			$out .= '#mlp-flyout #mlp-elmnts-button img {
				padding: ' . \sanitize_text_field( self::$config['icon_padding'] ) . ';
				width: ' . \sanitize_text_field( self::$config['icon_size'] ) . ';
				height: ' . \sanitize_text_field( self::$config['icon_size'] ) . ';
			}';
			$out .= '#mlp-flyout .mlp-elmnts-menu-item.accent {
				background: ' . \sanitize_text_field( self::$config['menu_accent_color'] ) . ';
			}';
			$out .= \sanitize_text_field( self::$config['custom_css'] );
			$out .= '</style>';

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
			$icons_url         = WP_2FA_URL . 'assets/images/';
			$default_link_item = array(
				'class'  => '',
				'href'   => '#',
				'target' => '_blank',
				'label'  => '',
				'icon'   => '',
				'data'   => '',
			);

			$out .= '<div id="mlp-overlay"></div>';

			$out .= '<div id="mlp-flyout">';

			$out .= '<a href="#" id="mlp-elmnts-button">';
			$out .= '<span class="mlp-elmnts-label">Open Quick Links</span>';
			$out .= '<span id="mlp-elmnts-image-wrapper">';
			$out .= '<img src="' . esc_url( $icons_url . self::$config['icon_image'] ) . '" alt="Open Quick Links" title="Open Quick Links">';
			$out .= '</span>';
			$out .= '</a>';

			$out .= '<div id="mlp-elmnts-menu">';
			$i    = 0;
			foreach ( array_reverse( self::$config['menu_items'] ) as $item ) {
				++$i;
				$item = array_merge( $default_link_item, $item );

				if ( ! empty( $item['icon'] ) && substr( $item['icon'], 0, 9 ) != 'dashicons' ) {
					$item['class'] .= ' mlp-elmnts-custom-icon';
					$item['class']  = trim( $item['class'] );
				}

				$out .= '<a ' . $item['data'] . ' href="' . esc_url( $item['href'] ) . '" class="mlp-elmnts-menu-item mlp-elmnts-menu-item-' . $i . ' ' . esc_attr( $item['class'] ) . '" target="_blank">';
				$out .= '<span class="mlp-elmnts-label visible">' . esc_html( $item['label'] ) . '</span>';
				if ( substr( $item['icon'], 0, 9 ) == 'dashicons' ) {
					$out .= '<span class="dashicons ' . sanitize_text_field( $item['icon'] ) . '"></span>';
				} elseif ( ! empty( $item['icon'] ) ) {
					$out .= '<span class="mlp-elmnts-icon"><img src="' . esc_url( $icons_url . $item['icon'] ) . '"></span>';
				}
				$out .= '</a>';
			} // foreach
			$out .= '</div>'; // #mlp-elmnts-menu

			$out .= '</div>'; // #mlp-flyout

			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Reads the config file remotely and sets 2 days transient for caching. If for some reason cant read the remote - false is returned
		 *
		 * @return bool|array
		 *
		 * @since 2.8.0
		 */
		public static function read_remote_config() {
			$config = \get_transient( self::CONFIG_TRANSIENT_NAME );

			if ( false === $config || empty( $config ) ) {

				$api_response = \wp_remote_request( 'https://melapress.com/downloads/plugins-files/wp-2fa-flyout-config.php', array() );

				$response_code = \wp_remote_retrieve_response_code( $api_response );

				if ( \is_wp_error( $api_response ) || 200 !== (int) $response_code ) {

					return false;
				} else {
					$config = \wp_remote_retrieve_body( $api_response );

					\set_transient( self::CONFIG_TRANSIENT_NAME, $config, \DAY_IN_SECONDS * 3 );

					return \json_decode( $config, true );
				}
			}

			$config = json_decode( $config, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$config = false;
			}

			return $config;
		}
	}
}
