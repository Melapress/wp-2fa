<?php
/**
 * Roles and main settings grace period notifications class.
 *
 * @package    wp2fa
 * @subpackage views
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Views;

use WP2FA\Admin\Controllers\Settings;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

if ( ! class_exists( '\WP2FA\Admin\Views\Grace_Period_Notifications' ) ) {
	/**
	 * Grace_Period_Notifications - Class for rendering the grace period notification settings.
	 *
	 * @since 2.5.0
	 */
	class Grace_Period_Notifications {
		public const GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME = 'grace-policy-notification-show';

		/**
		 * Inits all the class related hooks.
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		public static function init() {

			if ( is_admin() ) {

				\add_filter( WP_2FA_PREFIX . 'after_grace_period', array( __CLASS__, 'grace_period_notification_settings' ), 11, 5 );
				\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'add_setting_value' ) );
			}
			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );
		}

		/**
		 * Shows the settings for the grace period notifications behavior.
		 *
		 * @param string $content     - HTML content.
		 * @param string $role        - The name of the role.
		 * @param string $name_prefix - Name prefix for the input name, includes the role name if provided.
		 * @param string $data_role   - Data attribute - used by the JS.
		 * @param string $role_id     - The role name, used to identify the inputs.
		 *
		 * @return string
		 *
		 * @since 2.5.0
		 */
		public static function grace_period_notification_settings( string $content, string $role = '', string $name_prefix = '', string $data_role = '', string $role_id = '' ) {
			ob_start();

			if ( class_exists( 'WP2FA\Extensions\RoleSettings\Role_Settings_Controller' ) ) {
				$expire_action = Role_Settings_Controller::get_setting( $role, self::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME, true );
			} else {
				$expire_action = Settings::get_role_or_default_setting( self::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME, null, null, true );
			}
			?>
			<div class="sub-setting-indent">
				<p class="description" style="margin-top: 15px; margin-bottom: 8px;">
					<?php echo \esc_html__( 'How do you want users to be informed they are enforced to setup 2FA?', 'wp-2fa' ); ?>
				</p>
				<fieldset>
					<label for="dashboard-notification<?php echo \esc_attr( $role_id ); ?>" style="margin-bottom: 10px; display: inline-block;">
						<input type="radio" name="<?php echo \esc_attr( $name_prefix ); ?>[<?php echo \esc_attr( self::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME ); ?>]" 
						id="dashboard-notification<?php echo \esc_attr( $role_id ); ?>" 
						<?php echo $data_role; // phpcs:ignore?> 
						value="dashboard-notification" <?php checked( $expire_action, 'dashboard-notification' ); ?> class="js-nested">
						<span><?php echo \esc_html__( 'Show an admin notice in the dashboard', 'wp-2fa' ); ?></span>
					</label>

					<br>
					<div style="clear:both">
					<label for="after-login-notification<?php echo \esc_attr( $role_id ); ?>">
						<input type="radio" name="<?php echo \esc_attr( $name_prefix ); ?>[<?php echo \esc_attr( self::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME ); ?>]" <?php checked( $expire_action, 'after-login-notification' ); ?> 
						id="after-login-notification<?php echo \esc_attr( $role_id ); ?>"
						<?php echo $data_role; // phpcs:ignore?> 
						value="after-login-notification" <?php checked( $expire_action, 'after-login-notification' ); ?> class="js-nested">
						<span><?php echo \esc_html__( 'Show a notification on a page on its own after the user authenticates and before accessing the dashboard', 'wp-2fa' ); ?></span>
					</label>
					</div>
				</fieldset>
			</div>
			<?php
			$html_content = ob_get_contents();
			ob_end_clean();

			return $content . $html_content;
		}

		/**
		 * Adds global plugin setting options.
		 *
		 * @param array $loop_settings - Array with current plugin settings.
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		public static function add_setting_value( array $loop_settings ) {
			$loop_settings[] = self::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME;

			return $loop_settings;
		}

		/**
		 * Checks the grace policy setting for the given user.
		 *
		 * @param \WP_User $user - The user for which we have to check the settings.
		 *
		 * @return bool
		 *
		 * @since 2.5.0
		 */
		public static function notify_using_dashboard( \WP_User $user ) {
			if ( 'dashboard-notification' !== Settings::get_role_or_default_setting( self::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME, $user ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Adds the extension default settings to the main plugin settings
		 *
		 * @param array $default_settings - array with plugin default settings.
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		public static function add_default_settings( array $default_settings ) {
			$default_settings[ self::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME ] = 'after-login-notification';
			return $default_settings;
		}
	}
}
