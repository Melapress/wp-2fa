<?php
/**
 * Responsible for the API endpoints
 *
 * @package    wp-2fa
 * @since 3.0.0
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Controllers;

use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\Classes_Helper;
use WP2FA\Admin\Controllers\API\API_Login;
use WP2FA\WP2FA;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Endpoints registering
 */
if ( ! class_exists( '\WP2FA\Admin\Controllers\Endpoints' ) ) {

	/**
	 * Creates and registers all the API endpoints for the plugin
	 *
	 * @since 3.0.0
	 */
	class Endpoints {

		/**
		 * All of the endpoints supported by the plugin.
		 *
		 * @var array
		 *
		 * @since 3.0.0
		 */
		public static $endpoints = array(
			self::class => array(
				'login' => array(
					'class'     => API_Login::class,
					'namespace' => 'wp-2fa-methods/v1',

					'endpoints' => array(
						array(
							'(?P<user_id>\d+)(?:/(?P<token>\w+))(?:/(?P<provider>\w+))(?:/(?P<remember_device>\w+))?' => array(
								'methods'          => array(
									'method'   => \WP_REST_Server::READABLE,
									'callback' => 'validate_provider',
								),
								'args'             => array(
									'user_id'         => array(
										'required'    => true,
										'type'        => 'integer',
										'description' => 'User ID',
										'minimum'     => 1,
										'sanitize_callback' => 'absint',
									),
									'token'           => array(
										'required'    => false,
										'type'        => 'string',
										'description' => 'Provider token',
										'minimum'     => 3,
									),
									'provider'           => array(
										'required'    => false,
										'type'        => 'string',
										'description' => 'Provider name',
										'minimum'     => 3,
									),
									'remember_device' => array(
										'required'    => false,
										'type'        => 'boolean',
										'description' => 'Remember device',
									),
								),
								'checkPermissions' => '__return_true',
								'showInIndex'      => false,
							),
						),
					),
				),
			),
		);

		/**
		 * Inits the class
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function init() {

			\add_action( 'rest_api_init', array( __CLASS__, 'init_endpoints' ) );
			/**
			 * Enables the API endpoints for the plugin.
			 *
			 * @since 2.9.1
			 */
			if ( Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'enable_rest' ) ) ) {
				\add_action( 'login_enqueue_scripts', array( __CLASS__, 'dequeue_style' ), PHP_INT_MAX );
			}

			$api_classes = Classes_Helper::get_classes_by_namespace( 'WP2FA\Admin\Controllers\API' );

			if ( \is_array( $api_classes ) && ! empty( $api_classes ) ) {
				foreach ( $api_classes as $class ) {
					if ( \method_exists( $class, 'init' ) ) {
						$class::init();
					}
				}
			}
		}

		/**
		 * Inits all the endpoints from given structure
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function init_endpoints() {
			self::$endpoints = \apply_filters( WP_2FA_PREFIX . 'api_endpoints', self::$endpoints );

			foreach ( self::$endpoints as $endpoint_provider ) {
				foreach ( $endpoint_provider as $root_endpoint => $settings ) {
					$class     = $settings['class'];
					$namespace = $settings['namespace'];

					foreach ( $settings['endpoints'] as $routes ) {
						foreach ( $routes as $route => $endpoint ) {
							$args = array();
							if ( isset( $endpoint['args'] ) ) {
								$args = $endpoint['args'];
							}
							$check_permissions = array();
							if ( isset( $endpoint['checkPermissions'] ) ) {
								$check_permissions = $endpoint['checkPermissions'];
							}
							$show_in_index = $endpoint['showInIndex'];
							\register_rest_route(
								$namespace,
								'/' . $root_endpoint . '/' . $route,
								array(
									array(
										'methods'       => $endpoint['methods']['method'],
										'callback'      => array( $class, $endpoint['methods']['callback'] ),
										'args'          => $args,
										'permission_callback' => $check_permissions,
										'show_in_index' => $show_in_index,
									),
								),
								false
							);
						}
					}
				}
			}
		}

		/**
		 * Global method to check permissions for API endpoints - this one checks if the user has read capability.
		 *
		 * @return bool
		 *
		 * @since 3.0.0
		 */
		public static function check_permissions() {
			return \current_user_can( 'read' );
		}

		/**
		 * Removes GoDaddy style which causing the form elements to be shown
		 *
		 * @return void
		 *
		 * @since 2.9.0
		 */
		public static function dequeue_style() {

			\wp_register_script(
				'wp_2fa_user_login_scripts',
				WP_2FA_URL . 'includes/classes/Authenticator/assets/user-login.js',
				array( 'wp-api-fetch', 'wp-dom-ready' ),
				WP_2FA_VERSION,
				array( 'in_footer' => true )
			);
		}
	}
}
