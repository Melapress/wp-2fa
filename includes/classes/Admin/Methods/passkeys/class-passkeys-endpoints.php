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

namespace WP2FA\Passkeys;

use WP2FA\Passkeys\API_Signin;
use WP2FA\Passkeys\API_Register;
use WP2FA\Admin\Controllers\Endpoints;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Endpoints registering
 */
if ( ! class_exists( '\WP2FA\Passkeys\PassKeys_Endpoints' ) ) {

	/**
	 * Creates and registers all the API endpoints for the proxytron
	 *
	 * @since 3.0.0
	 */
	class PassKeys_Endpoints {

		/**
		 * All of the endpoints supported by the plugin.
		 *
		 * @var array
		 *
		 * @since 3.0.0
		 */
		public static $endpoints = array(
			self::class => array(
				'register' => array(
					'class'     => API_Register::class,
					'namespace' => 'wp-2fa-passkeys/v1',

					'endpoints' => array(
						array(
							'request' => array(
								'methods'          => array(
									'method'   => \WP_REST_Server::CREATABLE,
									'callback' => 'register_request_action',
								),
								'checkPermissions' => array(
									Endpoints::class,
									'check_permissions',
								),
								'showInIndex'      => false,
							),
						),
						array(
							'response' => array(
								'methods'          => array(
									'method'   => \WP_REST_Server::CREATABLE,
									'callback' => 'register_response_action',
								),
								'checkPermissions' => array(
									Endpoints::class,
									'check_permissions',
								),
								'showInIndex'      => true,
							),
						),
						array(
							'revoke' => array(
								'methods'          => array(
									'method'   => \WP_REST_Server::CREATABLE,
									'callback' => 'register_revoke_action',
								),
								'checkPermissions' => array(
									Endpoints::class,
									'check_permissions',
								),
								'showInIndex'      => false,
							),
						),
						array(
							'enable' => array(
								'methods'          => array(
									'method'   => \WP_REST_Server::CREATABLE,
									'callback' => 'register_enable_action',
								),
								'checkPermissions' => array(
									Endpoints::class,
									'check_permissions',
								),
								'showInIndex'      => false,
							),
						),
					),
				),
				'singin'   => array(
					'class'     => API_Signin::class,
					'namespace' => 'wp-2fa-passkeys/v1',

					'endpoints' => array(
						array(
							'request' => array(
								'methods'          => array(
									'method'   => \WP_REST_Server::CREATABLE,
									'callback' => 'signin_request_action',
								),
								'parameters'      => array(
									'user' => array(
										'description' => 'Username or email of the user trying to sign in.',
										'type'        => 'string',
										'required'    => true,
									),
								),
								'checkPermissions' => '__return_true',
								'showInIndex'      => false,
							),
						),
						array(
							'response' => array(
								'methods'          => array(
									'method'   => \WP_REST_Server::CREATABLE,
									'callback' => 'signin_response_action',
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
		 * Inits all the endpoints from given structure
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function init() {
			\add_filter(
				WP_2FA_PREFIX . 'api_endpoints',
				function ( $endpoints ) {
					return array_merge( $endpoints, self::$endpoints );
				}
			);
		}
	}
}
