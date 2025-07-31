<?php
/**
 * Main file of the grace period settings extension class.
 *
 * @package    wp2fa
 * @subpackage grace-period
 * @since      2.0.0
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\App;

use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\User_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Grace period class
 */
if ( ! class_exists( '\WP2FA\App\Grace_Period' ) ) {

	/**
	 * Responsible for users which grace period has expired
	 *
	 * Gives the administrator the ability to select what action the plugin should take:
	 * - Lock the user
	 * - Force the user to set up their 2FA immediately
	 *
	 * @since 2.0.0
	 */
	class Grace_Period {

		/**
		 * Inits all the hooks
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function init() {
			\add_filter( WP_2FA_PREFIX . 'after_grace_period', array( __CLASS__, 'grace_period_options' ), 10, 5 );
			\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'add_setting_value' ) );
			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );
			\add_filter( WP_2FA_PREFIX . 'should_account_be_locked_on_grace_period_expiration', array( __CLASS__, 'maybe_prevent_account_lock' ), 10, 2 );
		}

		/**
		 * Prevent account locking if allowed, depending on the plugin settings.
		 *
		 * @param boolean  $state - Current state of the checking.
		 * @param \WP_User $user - The User class.
		 *
		 * @return bool
		 *
		 * @since 2.0.0
		 */
		public static function maybe_prevent_account_lock( bool $state, \WP_User $user ) {
			if ( 'configure-right-away' === Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'grace-policy-after-expire-action' ) ) {
				User_Helper::set_user_enforced_instantly( true, $user );

				return false;
			}

			return $state;
		}

		/**
		 * Collects the options for the main plugin settings page and returns them
		 *
		 * @param string $content - HTML content.
		 * @param string $role - The name of the role.
		 * @param string $name_prefix - Name prefix for the input name, includes the role name if provided.
		 * @param string $data_role - Data attribute - used by the JS.
		 * @param string $role_id - The role name, used to identify the inputs.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function grace_period_options( string $content, string $role = '', string $name_prefix = '', string $data_role = '', string $role_id = '' ) {
			return $content . self::grace_options( $role, $name_prefix, $data_role, $role_id );
		}

		/**
		 * Adds global plugin setting options
		 *
		 * @param array $loop_settings - Array with current plugin settings.
		 *
		 * @return array
		 *
		 * @since 2.0.0
		 */
		public static function add_setting_value( array $loop_settings ) {
			$loop_settings[] = 'grace-policy-after-expire-action';

			return $loop_settings;
		}

		/**
		 * Checks the grace policy setting for the given user
		 *
		 * @param \WP_User $user - The user for which we have to check the settings.
		 *
		 * @return boolean
		 *
		 * @since 2.0.0
		 */
		public static function is_set_up_immediately_set( \WP_User $user ) {
			if ( 'configure-right-away' === Settings_Utils::get_setting_role( User_Helper::get_user_role( $user ), 'grace-policy-after-expire-action' ) ) {

				return true;
			}

			return false;
		}

		/**
		 * Adds the extension default settings to the main plugin settings
		 *
		 * @param array $default_settings - array with plugin default settings.
		 *
		 * @return array
		 *
		 * @since 2.0.0
		 */
		public static function add_default_settings( array $default_settings ) {
			$default_settings['grace-policy-after-expire-action'] = 'configure-right-away';

			return $default_settings;
		}

		/**
		 * Adds options to the settings page
		 *
		 * @param string $role - The name of the role.
		 * @param string $name_prefix - Name prefix for the input name, includes the role name if provided.
		 * @param string $data_role - Data attribute - used by the JS.
		 * @param string $role_id - The role name, used to identify the inputs.
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		private static function grace_options( string $role = '', string $name_prefix = '', string $data_role = '', string $role_id = '' ): string {
			ob_start();

			$expire_action = Settings_Utils::get_setting_role( sanitize_text_field( $role ), 'grace-policy-after-expire-action', true );

			if ( false === $expire_action ) {
				$expire_action = 'configure-right-away';
			}
			?>
			<div class="sub-setting-indent">
				<p class="description" style="margin-top: 15px; margin-bottom: 8px;">
					<?php echo \esc_html__( 'What should the plugin do with users who do not configure 2FA within the grace period?', 'wp-2fa' ); ?>
				</p>
				<fieldset>
					<label for="configure-right-away<?php echo \esc_attr( sanitize_text_field( $role_id ) ); ?>" style="margin-bottom: 10px; display: inline-block;">
						<input type="radio" name="<?php echo \esc_attr( sanitize_text_field( $name_prefix ) ); ?>[grace-policy-after-expire-action]" 
						id="configure-right-away<?php echo \esc_attr( sanitize_text_field( $role_id ) ); ?>" 
						<?php echo ( $data_role );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 
						value="configure-right-away" <?php checked( $expire_action, 'configure-right-away' ); ?> class="js-nested">
						<span><?php echo \esc_html__( 'Do not let them access the dashboard / user page once they log in until they configure 2FA', 'wp-2fa' ); ?></span>
					</label>

					<br>
					<div style="clear:both">
					<label for="manual-block<?php echo \esc_attr( sanitize_text_field( $role_id ) ); ?>">
						<input type="radio" name="<?php echo \esc_attr( sanitize_text_field( $name_prefix ) ); ?>[grace-policy-after-expire-action]" <?php checked( $expire_action, 'manual-block' ); ?> 
						id="manual-block<?php echo \esc_attr( sanitize_text_field( $role_id ) ); ?>"
						<?php echo ( $data_role );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 
						value="manual-block" class="js-nested">
						<span><?php echo \esc_html__( 'Lock the user (administrators have to manually unlock them)', 'wp-2fa' ); ?></span>
					</label>
					</div>
				</fieldset>
			</div>
			<?php
			$html_content = ob_get_contents();
			ob_end_clean();

			return $html_content;
		}
	}
}
