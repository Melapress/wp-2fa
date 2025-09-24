<?php
/**
 * Roles and main settings password reset class.
 *
 * @package    wp2fa
 * @subpackage views
 *
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Admin\Views;

use WP2FA\Utils\Settings_Utils;

if ( ! class_exists( '\WP2FA\Admin\Views\Password_Reset_2FA' ) ) {
	/**
	 * Password_Reset_2FA - Class for rendering the plugin settings related to 2fa when user resets the password.
	 *
	 * @since 2.5.0
	 */
	class Password_Reset_2FA {
		public const PASSWORD_RESET_SETTINGS_NAME = 'password-reset-2fa-show';

		public const ENABLED_SETTING_VALUE = 'password-reset-2fa';

		/**
		 * Inits all the class related hooks.
		 *
		 * @return void
		 *
		 * @since 2.5.0
		 */
		public static function init() {
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
		 * @since 2.5.0
		 */
		public static function reset_settings( string $role = '', string $name_prefix = '', string $data_role = '', string $role_id = '' ): string {
			ob_start();

			$password_reset_action = Settings_Utils::get_setting_role( $role, self::PASSWORD_RESET_SETTINGS_NAME, true );

			?>
			<div class="sub-setting-indent">
				<fieldset>
					<label for="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE . $role_id ); ?>" style="margin-bottom: 10px; display: inline-block;">
						<input type="checkbox" name="<?php echo \esc_attr( $name_prefix . '[' . self::PASSWORD_RESET_SETTINGS_NAME . ']' ); ?>" 
						id="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE . $role_id ); ?>" 
						<?php echo $data_role;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 
						value="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE ); ?>" <?php checked( $password_reset_action, self::ENABLED_SETTING_VALUE ); ?> class="js-nested">
						<span><?php echo \esc_html__( 'Require 2FA on password reset', 'wp-2fa' ); ?></span>
					</label>
				</fieldset>
			</div>
			<?php
			$html_content = ob_get_clean();

			return $html_content;
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
		public static function add_setting_value( array $loop_settings ): array {
			$loop_settings[] = self::PASSWORD_RESET_SETTINGS_NAME;

			return $loop_settings;
		}

		/**
		 * Adds the extension default settings to the main plugin settings.
		 *
		 * @param array $default_settings - array with plugin default settings.
		 *
		 * @return array
		 *
		 * @since 2.5.0
		 */
		public static function add_default_settings( array $default_settings ): array {
			$default_settings[ self::PASSWORD_RESET_SETTINGS_NAME ] = self::PASSWORD_RESET_SETTINGS_NAME;

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
		 * @since 2.5.0
		 */
		public static function password_reset_setting( string $content, string $role = '', string $name_prefix = '', string $data_role = '', string $role_id = '' ): string {
			ob_start();
			?>
			<h3><?php \esc_html_e( 'Do you want to require 2FA when users reset their password?', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php \esc_html_e( 'When enabled, users who reset their password via the “Lost/Forgot Password” process will need to enter a one-time code sent to their email before completing the reset. This does not apply to password changes made while logged in.', 'wp-2fa' ); ?>
			</p>

			<table class="form-table">
				<tbody>
					<tr>
						<th><label for="<?php echo \esc_attr( self::ENABLED_SETTING_VALUE . $role_id ); ?>"><?php \esc_html_e( 'Password reset', 'wp-2fa' ); ?></label></th>
						<td>
						<fieldset class="contains-hidden-inputs">
						<?php echo self::reset_settings( $role, $name_prefix, $data_role, $role_id ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
			<?php

			$content .= ob_get_clean();

			return $content;
		}
	}
}
