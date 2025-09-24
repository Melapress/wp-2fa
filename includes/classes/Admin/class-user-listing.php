<?php
/**
 * Responsible for user listing in admin manipulation.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin;

use WP2FA\Utils\User_Utils;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Extensions\TrustedDevices\Core;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * User_Listing class with user listing filters
 */
if ( ! class_exists( '\WP2FA\Admin\User_Listing' ) ) {

	/**
	 * User_Listing - Shows extra column in user table with WP2FA status for every user
	 */
	class User_Listing {

		/**
		 * The users table column name
		 *
		 * @var string
		 *
		 * @since 3.0.0
		 */
		private static $column_name = '2fa-status';

		/**
		 * Inits all the hooks used for showing the extra user data in the users column
		 *
		 * @return void
		 *
		 * @since 3.0.0
		 */
		public static function init() {
			\add_filter( 'manage_users_columns', array( __CLASS__, 'add_wp_2fa_column' ) );
			\add_filter( 'wpmu_users_columns', array( __CLASS__, 'add_wp_2fa_column' ) );
			\add_filter( 'manage_users_custom_column', array( __CLASS__, 'show_column_data' ), 10, 3 );
			\add_filter( 'bulk_actions-users', array( __CLASS__, 'add_bulk_action' ), 10, 1 );
			\add_filter( 'handle_bulk_actions-users', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
			\add_action( 'admin_notices', array( __CLASS__, 'show_admin_notice' ) );
			\add_filter( 'user_row_actions', array( __CLASS__, 'add_users_hover' ), 10, 2 );
		}

		/**
		 * Sets the column in the admin users table
		 *
		 * @param array $columns - Array with all the columns.
		 *
		 * @return array
		 *
		 * @since 3.0.0
		 */
		public static function add_wp_2fa_column( array $columns ): array {
			$columns[ self::$column_name ] = esc_html__( '2FA Status', 'wp-2fa' );
			return $columns;
		}

		/**
		 * Shows the user WP 2FA status data in the users table
		 *
		 * @param mixed  $value - The value of the column.
		 * @param string $column_name - The name of the column.
		 * @param int    $user_id - the ID of the user.
		 *
		 * @return mixed
		 *
		 * @since 3.0.0
		 */
		public static function show_column_data( $value, string $column_name, $user_id ) {
			if ( self::$column_name === $column_name ) {
				return esc_html( self::get_user2fa_status( $user_id ) );
			}
			return $value;
		}

		/**
		 * Retrieves the translated 2FA status label for given user.
		 *
		 * @param int $user_id - The id of the user for which the info should be extracted.
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		private static function get_user2fa_status( $user_id ): string {
			$status_meta_value = User_Helper::get_2fa_status( $user_id );
			if ( ! empty( $status_meta_value ) ) {
				$status_data = User_Utils::extract_statuses( array( $status_meta_value ) );
				if ( ! empty( $status_data ) ) {
					return $status_data['label'];
				}
			}
			return User_Helper::set_user_status( new \WP_User( $user_id ) );
		}

		/**
		 * Returns the users table column name
		 *
		 * @return string
		 *
		 * @since 3.0.0
		 */
		public static function get_column_name(): string {
			return self::$column_name;
		}

		/**
		 * Adds bulk action to the WP users menu
		 *
		 * @param array $bulk_actions - Array of bulk actions.
		 *
		 * @return array
		 *
		 * @since 2.2.2
		 */
		public static function add_bulk_action( $bulk_actions ): array {
			$bulk_actions['remove-2fa']         = \esc_html__( 'Remove 2FA', 'wp-2fa' );
			$bulk_actions['remove-2fa-trusted'] = \esc_html__( 'Reset list of 2FA trusted devices', 'wp-2fa' );

			return $bulk_actions;
		}

		/**
		 * Removes the 2fa from the list of the selected users.
		 *
		 * @param string $redirect_url - The redirect URL to redirect to when action is performed.
		 * @param string $action - The action to perform.
		 * @param array  $user_ids - The user IDs to remove from.
		 *
		 * @return string
		 *
		 * @since 2.2.2
		 */
		public static function handle_bulk_actions( $redirect_url, $action, $user_ids ): string {
			if ( ! current_user_can( 'manage_options' ) ) {
				return esc_url_raw( \network_admin_url() );
			}

			if ( 'remove-2fa' === $action ) {

				foreach ( (array) $user_ids as $user_id ) {
					User_Helper::remove_2fa_for_user( (int) $user_id );
				}
				$redirect_url = add_query_arg( '2fa-removed', count( (array) $user_ids ), $redirect_url );
			}

			if ( class_exists( '\WP2FA\Extensions\TrustedDevices\Core' ) && 'remove-2fa-trusted' === $action ) {

				Core::remove_trusted_devices_for_users( (array) $user_ids );
				$redirect_url = add_query_arg( '2fa-trusted-removed', count( (array) $user_ids ), $redirect_url );
			}

			return esc_url_raw( $redirect_url );
		}

		/**
		 * Adds links to the on hover state of the users table row
		 *
		 * @param array    $actions - Array with all the actions for the current row.
		 * @param \WP_User $user_object - The user object from the current row.
		 *
		 * @return array
		 *
		 * @since 2.4.0
		 */
		public static function add_users_hover( $actions, $user_object ): array {
			if ( class_exists( '\WP2FA\Extensions\TrustedDevices\Core' ) ) {
				$actions['remove-2fa-trusted'] = "<a class='resetpassword' href='" . \esc_url( \wp_nonce_url( "users.php?action=remove-2fa-trusted&amp;users=$user_object->ID", 'bulk-users' ) ) . "'>" . \esc_html__( 'Reset list of 2FA trusted devices', 'wp-2fa' ) . '</a>';
			}
			return $actions;
		}

		/**
		 * Handles the Admin notice for the users removed 2FA.
		 *
		 * @return void
		 *
		 * @since 2.2.2
		 */
		public static function show_admin_notice() {
			if ( ! empty( $_REQUEST['2fa-removed'] ) ) {
				$num_changed = (int) $_REQUEST['2fa-removed'];
				printf(
					'<div id="message" class="updated notice is-dismissable"><p>' .
					// translators: The number of the affected users.
					\esc_html__( 'Removed 2FA from %d users.', 'wp-2fa' ) .
					'</p></div>',
					$num_changed
				);
			}
			if ( ! empty( $_REQUEST['2fa-trusted-removed'] ) ) {
				$num_changed = (int) $_REQUEST['2fa-trusted-removed'];
				printf(
					'<div id="message" class="updated notice is-dismissable"><p>' .
					// translators: The number of the affected users.
					\esc_html__( 'Removed 2FA trusted devices from %d users.', 'wp-2fa' ) .
					'</p></div>',
					$num_changed
				);
			}
		}
	}
}
