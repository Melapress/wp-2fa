<?php
/**
 * Roles and main settings user login again after 2FA setup class.
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

if ( ! class_exists( '\WP2FA\Admin\Views\Re_Login_2FA' ) ) {
	/**
	 * Re_Login_2FA - Class for rendering the plugin settings related to 2fa when user sets the 2FA method.
	 *
	 * @since 2.7.0
	 */
	class Re_Login_2FA {
		public const RE_LOGIN_SETTINGS_NAME = 're-login-2fa-show';

		public const ENABLED_SETTING_VALUE = 're-login-2fa';

		/**
		 * Inits all the class related hooks.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function init() {
			if ( is_admin() ) {
				\add_filter( WP_2FA_PREFIX . 'before_grace_period', array( __CLASS__, 're_login_setting' ), 11, 5 );
				\add_filter( WP_2FA_PREFIX . 'loop_settings', array( __CLASS__, 'add_setting_value' ) );
				\add_action( 'wp_ajax_custom_ajax_logout', array( __CLASS__, 'redirect_after_logout' ) );
			}
			\add_filter( WP_2FA_PREFIX . 'default_settings', array( __CLASS__, 'add_default_settings' ) );
		}

		/**
		 * Logs out the current user and sends success message to the ajax request.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function redirect_after_logout() {
			\wp_logout();
			ob_clean(); // probably overkill for this, but good habit.
			\wp_send_json_success();
		}

		/**
		 * Shows the settings for the grace period notifications behavior.
		 *
		 * @param string $role        - The name of the role.
		 * @param string $name_prefix - Name prefix for the input name, includes the role name if provided.
		 * @param string $data_role   - Data attribute - used by the JS.
		 * @param string $role_id     - The role name, used to identify the inputs.
		 *
		 * @return string
		 *
		 * @since 2.7.0
		 */
		public static function reset_settings( string $role = '', string $name_prefix = '', string $data_role = '', string $role_id = '' ) {
			ob_start();

			if ( class_exists( 'WP2FA\Extensions\RoleSettings\Role_Settings_Controller' ) ) {
				$password_reset_action = Role_Settings_Controller::get_setting( $role, self::RE_LOGIN_SETTINGS_NAME, true );
			} else {
				$password_reset_action = Settings::get_role_or_default_setting( self::RE_LOGIN_SETTINGS_NAME, null, null, true );
			}
			?>
			<div class="sub-setting-indent">
				<fieldset>
					<label for="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE ); ?><?php echo \esc_attr( $role_id ); ?>" style="margin-bottom: 10px; display: inline-block;">
						<input type="checkbox" name="<?php echo \esc_attr( $name_prefix ); ?>[<?php echo \esc_attr( self::RE_LOGIN_SETTINGS_NAME ); ?>]" 
						id="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE ); ?><?php echo \esc_attr( $role_id ); ?>" 
						<?php echo $data_role; // phpcs:ignore?> 
						value="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE ); ?>" <?php checked( $password_reset_action, self::ENABLED_SETTING_VALUE ); ?> class="js-nested">
						<span><?php echo \esc_html__( 'Log out user after 2FA setup', 'wp-2fa' ); ?></span>
					</label>
				</fieldset>
			</div>
			<?php
			$html_content = ob_get_contents();
			ob_end_clean();

			return $html_content;
		}

		/**
		 * Adds global plugin setting options.
		 *
		 * @param array $loop_settings - Array with current plugin settings.
		 *
		 * @return array
		 *
		 * @since 2.7.0
		 */
		public static function add_setting_value( array $loop_settings ) {
			$loop_settings[] = self::RE_LOGIN_SETTINGS_NAME;

			return $loop_settings;
		}

		/**
		 * Adds the extension default settings to the main plugin settings.
		 *
		 * @param array $default_settings - array with plugin default settings.
		 *
		 * @return array
		 *
		 * @since 2.7.0
		 */
		public static function add_default_settings( array $default_settings ) {
			$default_settings[ self::RE_LOGIN_SETTINGS_NAME ] = self::RE_LOGIN_SETTINGS_NAME;

			return $default_settings;
		}

		/**
		 * Password reset settings.
		 *
		 * @param string $content     - HTML content.
		 * @param string $role        - The name of the role.
		 * @param string $name_prefix - Name prefix for the input name, includes the role name if provided.
		 * @param string $data_role   - Data attribute - used by the JS.
		 * @param string $role_id     - The role name, used to identify the inputs.
		 *
		 * @return string
		 *
		 * @since 2.7.0
		 */
		public static function re_login_setting( string $content, string $role = '', string $name_prefix = '', string $data_role = '', string $role_id = '' ) {
			ob_start();
			?>
		<h3><?php \esc_html_e( 'Do you want to logout users after setting up 2FA on their account?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php \esc_html_e( 'When you enable this setting users will be logged out automatically after configuring 2FA and they will need to log back in.', 'wp-2fa' ); ?>
		</p>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE ); ?><?php echo \esc_attr( $role_id ); ?>"><?php \esc_html_e( 'Re-login', 'wp-2fa' ); ?></label></th>
					<td>
					<fieldset class="contains-hidden-inputs">
					<?php echo self::reset_settings( $role, $name_prefix, $data_role, $role_id ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
			<?php

			$content .= ob_get_contents();
			ob_end_clean();

			return $content;
		}
	}
}
