<?php
/**
 * Responsible for the Passkeys extension plugin settings
 *
 * @package    wp2fa
 * @subpackage passkeys
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Passkeys;

use WP2FA\Methods\Passkeys;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Passkeys\Source_Repository;
use WP2FA\Passkeys\Helpers\Authenticators_Helper;
use WP2FA\WP2FA;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Passkeys user settings class
 */
if ( ! class_exists( '\WP2FA\Passkeys\Passkeys_User_Profile' ) ) {

	/**
	 * Responsible for setting different 2FA Passkeys settings
	 *
	 * @since 3.0.0
	 */
	class Passkeys_User_Profile {

		/**
		 * Initialize the Passkeys_User_Profile class
		 *
		 * @since 3.0.0
		 */
		public static function init() {
			\add_filter( WP_2FA_PREFIX . 'append_to_profile_form_content', array( __CLASS__, 'add_user_profile_form' ), 10, 2 );
		}

		/**
		 * Gives the ability to add more content to the profile page.
		 *
		 * @param string   $content - The parsed HTML of the form.
		 * @param \WP_User $user - The user object.
		 *
		 * @since 3.0.0
		 */
		public static function add_user_profile_form( $content, \WP_User $user ) {

			if ( ! Passkeys::is_enabled( User_Helper::get_user_role( $user ) ) ) {
				return $content;
			}

			$public_key_credentials = Source_Repository::find_all_for_user( $user );

			$add_button = true;

			if ( 'free' === WP2FA::get_plugin_version() && ! empty( $public_key_credentials ) && \count( $public_key_credentials ) >= 1 ) {
				$public_key_credentials = array( reset( $public_key_credentials ) );

				$add_button = false;
			}

			if ( ! class_exists( 'ParagonIE_Sodium_Core_Base64_UrlSafe', false ) ) {
				require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Base64/UrlSafe.php';
				require_once ABSPATH . WPINC . '/sodium_compat/src/Core/Util.php';
			}

			// Prepare template variables.
			$section_title = \esc_html( WP2FA::get_wp2fa_white_label_setting( 'passkeys-option-label', true ) ) . __( ' - by WP2FA', 'wp-2fa' );

			$section_description_html = sprintf(
				\wp_kses(
					/* translators: %s: link to the passkeys guide. */
					__( 'Passkeys make login easier and safer by replacing passwords with your fingerprint, face, or device PIN, reducing password risks and speeding up authentication. %s', 'wp-2fa' ),
					array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
				),
				'<a href="' . \esc_url( 'https://melapress.com/support/kb/wp-2fa-activate-use-passkeys/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=passkeys_user' ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'Learn more about Passkeys.', 'wp-2fa' ) . '</a>'
			);

			$date_format = \get_option( 'date_format' ) ?: 'F j, Y';
			$time_format = \get_option( 'time_format' ) ?: 'g:iA';

			\ob_start();
			include WP_2FA_PATH . 'templates' . DIRECTORY_SEPARATOR . 'passkeys' . DIRECTORY_SEPARATOR . 'user-profile-table.php';
			$output = \ob_get_clean();

			return $content . $output;
		}

		/**
		 * Print passkey underscore templates in the admin footer.
		 *
		 * @since 4.0.0
		 */
		public static function print_passkey_templates(): void {
			$template_dir = WP_2FA_PATH . 'templates' . DIRECTORY_SEPARATOR . 'passkeys' . DIRECTORY_SEPARATOR;

			$templates = array(
				'setup-modal'   => $template_dir . 'setup-modal.php',
				'success-modal' => $template_dir . 'success-modal.php',
			);

			foreach ( $templates as $name => $path ) {
				if ( \file_exists( $path ) ) {
					include $path;
				}
			}
		}
	}
}
