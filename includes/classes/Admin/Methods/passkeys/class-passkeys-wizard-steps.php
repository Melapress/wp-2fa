<?php
/**
 * Responsible for WP2FA user's Passkeys manipulation.
 *
 * @package    wp2fa
 * @subpackage methods-wizard
 * @since 3.0.0
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Methods\Wizards;

use WP2FA\WP2FA;
use WP2FA\Methods\Passkeys;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Views\Wizard_Steps;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Methods\Traits\Methods_Wizards_Trait;

/**
 * Class for handling passkeys codes.
 *
 * @since 3.0.0
 *
 * @package WP2FA
 */
if ( ! class_exists( '\WP2FA\Methods\Wizards\PassKeys_Wizard_Steps' ) ) {
	/**
	 * Passkeys code class, for handling passkeys (app) code generation and such.
	 *
	 * @since 3.0.0
	 */
	class PassKeys_Wizard_Steps extends Wizard_Steps {

		use Methods_Wizards_Trait;

		/**
		 * Keeps the main class method name, so we can call it when needed.
		 *
		 * @var string
		 *
		 * @since 3.0.0
		 */
		private static $main_class = Passkeys::class;

		/**
		 * The default value of the method order in the wizards.
		 *
		 * @var integer
		 *
		 * @since 3.0.0
		 */
		private static $order = 9;

		/**
		 * Inits the class hooks
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function init() {
			// \add_filter( WP_2FA_PREFIX . 'methods_modal_options', array( __CLASS__, 'passkeys_option' ), 10, 2 );
			// \add_filter( WP_2FA_PREFIX . 'methods_re_configure', array( __CLASS__, 'passkeys_re_configure' ), 10, 2 );
			// \add_filter( WP_2FA_PREFIX . 'methods_settings', array( __CLASS__, 'passkeys_wizard_settings' ), 10, 4 );
		}

		/**
		 * Shows the option to reconfigure email (if applicable)
		 *
		 * @param array  $methods - Array of methods collected.
		 * @param string $role - The name of the role to show option to.
		 *
		 * @since 3.0.0
		 *
		 * @return array
		 */
		public static function passkeys_re_configure( array $methods, string $role ): array {

			if ( ! Passkeys::is_enabled() ) {
				return $methods;
			}
			\ob_start();
			?>
				<div class="option-pill">
					<?php echo \wp_kses_post( WP2FA::contextual_reconfigure_text( WP2FA::get_wp2fa_white_label_setting( 'passkeys_reconfigure_intro', true ), User_Helper::get_user_object()->ID, Passkeys::METHOD_NAME ) ); ?>
					<div class="wp2fa-setup-actions">
						<a href="#" class="button button-primary wp-2fa-button-primary" data-name="next_step_setting_modal_wizard" data-trigger-reset-key <?php echo WP_Helper::create_data_nonce( self::json_nonce() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-user-id="<?php echo \esc_attr( User_Helper::get_user_object()->ID ); ?>" data-next-step="2fa-wizard-passkeys"><?php \esc_html_e( 'Reset Key', 'wp-2fa' ); ?></a>
					</div>
				</div>
			<?php
				$output = ob_get_contents();
				ob_end_clean();

				$methods[ self::get_order( $role, $methods ) ] = array(
					'name'   => self::$main_class::METHOD_NAME,
					'output' => $output,
				);

				return $methods;
		}

		/**
		 * Shows the initial passkeys setup options based on enabled methods
		 *
		 * @param array  $methods - Array of methods collected.
		 * @param string $role - The name of the role to show option to.
		 *
		 * @since 3.0.0
		 *
		 * @return array
		 */
		public static function passkeys_option( array $methods, string $role ): array {
			if ( Passkeys::is_enabled() ) {
				\ob_start();
				?>
				<div class="option-pill">
					<label for="basic">
						<input id="basic" name="wp_2fa_enabled_methods" type="radio" value="passkeys">
						<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'passkeys-option-label', true ) ); ?>
						<?php
						$show = ( 'show_help_text' === WP2FA::get_wp2fa_white_label_setting( 'show_help_text' ) && WP2FA::get_wp2fa_white_label_setting( \esc_attr( Passkeys::METHOD_NAME ) . '-option-label-hint', true ) ) ?? false;
						if ( $show ) {
							echo '<br><span class="wizard-tooltip" data-tooltip-content="data-' . \esc_attr( Passkeys::METHOD_NAME ) . '-tooltip-content-wrapper">i</span>';
						}
						?>
					</label>
					<?php
					if ( $show ) {
						echo '<p class="description tooltip-content-wrapper" data-' . \esc_attr( Passkeys::METHOD_NAME ) . '-tooltip-content-wrapper>' . \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( \esc_attr( Passkeys::METHOD_NAME ) . '-option-label-hint', true ) ) . '</p>';
					}
					?>
				</div>
				<?php
				$output = ob_get_contents();
				ob_end_clean();

				$methods[ self::get_order( $role, $methods ) ] = $output;
			}

			return $methods;
		}

		/**
		 * Settings page and first time wizard settings render
		 *
		 * @param array   $methods - Array with all the methods in which we have to add this one.
		 * @param boolean $setup_wizard - Is that the first time setup wizard.
		 * @param string  $data_role - Additional HTML data attribute.
		 * @param mixed   $role - Name of the role.
		 *
		 * @return array - The array with the methods with all the methods wizard steps.
		 *
		 * @since 3.0.0
		 */
		public static function passkeys_wizard_settings( array $methods, bool $setup_wizard, string $data_role, $role = null ) {
			$name_prefix = WP_2FA_POLICY_SETTINGS_NAME;
			$role_id     = '';
			if ( null !== $role && '' !== trim( (string) $role ) ) {
				$name_prefix .= "[{$role}]";
				$data_role    = 'data-role="' . $role . '"';
				$role_id      = '-' . $role;
			}

			/**
			 * Filter to check if the interface has to disable this method.
			 *
			 * @param bool - Should we disable the method - default false.
			 * @param Providers - The current method class.
			 * @param null|string - The role name for which the status must be checked.
			 *
			 * @since 2.9.2
			 */
			$method_disabled_class = \apply_filters( WP_2FA_PREFIX . 'method_settings_disabled', false, self::$main_class, $role );

			if ( $method_disabled_class ) {
				$method_disabled_class = 'disabled';
			}

			\ob_start();
			?>
			<div id="<?php echo \esc_attr( Passkeys::METHOD_NAME ); ?>-method-wrapper" class="method-wrapper">
				<?php echo self::hidden_order_setting( $role ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<label class="<?php echo \esc_html( $method_disabled_class ); ?>" for="passkeys<?php echo \esc_attr( $role_id ); ?>" style="margin-bottom: 0 !important;">
					<input type="checkbox" id="passkeys<?php echo \esc_attr( $role_id ); ?>" name="<?php echo \esc_attr( $name_prefix ); ?>[enable_passkeys]" value="enable_passkeys"
					<?php echo $data_role; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					
						<?php \checked( Passkeys::POLICY_SETTINGS_NAME, Settings_Utils::get_setting_role( $role, Passkeys::POLICY_SETTINGS_NAME ), true ); ?>
						
					>
					<?php \esc_html_e( 'Passkeys - ', 'wp-2fa' ); ?><a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank" rel=noopener><?php \esc_html_e( 'complete list of supported 2FA apps.', 'wp-2fa' ); ?></a>
				</label>
				<?php
				if ( $setup_wizard ) {
					echo '<p class="description">';
					printf(
						/* translators: link to the knowledge base website */
						\esc_html__( 'When using this method, users will need to configure a 2FA app to get the one-time login code. The plugin supports all standard 2FA apps. Refer to the %s for more information. Allowing users to set up a secondary 2FA method is highly recommended. You can do this in the next step of the wizard. This will allow users to log in using an alternative method should they, for example lose access to their phone.', 'wp-2fa' ),
						'<a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank">' . \esc_html__( 'guide on how to set up 2FA apps', 'wp-2fa' ) . '</a>'
					);
					echo '</p>';
				}
				if ( ! $setup_wizard ) {
					echo '<p class="description">';
					printf(
						/* translators: link to the knowledge base website */
						\esc_html__( 'Refer to the %s for more information on how to setup these apps and which apps are supported.', 'wp-2fa' ),
						'<a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank">' . \esc_html__( 'guide on how to set up 2FA apps', 'wp-2fa' ) . '</a>'
					);
					echo '</p>';
				}
				?>
			</div>
			<?php
			$output = ob_get_contents();
			ob_end_clean();

			$methods[ self::get_order( $role, $methods ) ] = $output;

			return $methods;
		}

		/**
		 * Prints the form that prompts the user to authenticate.
		 *
		 * @param \WP_User $user - \WP_User object of the logged-in user.
		 *
		 * @since 3.0.0
		 */
		public static function passkeys_authentication_page( $user ) {
			require_once ABSPATH . '/wp-admin/includes/template.php';
			?>
			<?php
			if ( 'use-custom' == WP2FA::get_wp2fa_white_label_setting( 'use_custom_2fa_message' ) ) {
				echo WP2FA::get_wp2fa_white_label_setting( 'custom-text-app-code-page', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo WP2FA::get_wp2fa_white_label_setting( 'default-text-code-page', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
				<p>
					</br>
					<label for="authcode"><?php \esc_html_e( 'Authentication Code:', 'wp-2fa' ); ?></label>
					<input type="tel" name="authcode" id="authcode" class="input" value="" size="20" pattern="[0-9]*" autocomplete="off" />
					<script>
						const authcode = document.getElementById('authcode');
						authcode.addEventListener('input', function() {
						this.value = this.value.trim();
						});
					</script>
				</p>
			<?php
		}
	}
}
