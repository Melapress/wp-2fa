<?php
/**
 * Responsible for WP2FA user's TOTP manipulation.
 *
 * @package    wp2fa
 * @subpackage methods-wizard
 * @since      2.6.0
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WP2FA\Methods\Wizards;

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Views\Wizard_Steps;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Methods\Traits\Methods_Wizards_Trait;
use WP2FA\Authenticator\Authentication;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

/**
 * Class for handling totp codes.
 *
 * @since 2.6.0
 *
 * @package WP2FA
 */
if ( ! class_exists( '\WP2FA\Methods\Wizards\TOTP_Wizard_Steps' ) ) {
	/**
	 * TOTP code class, for handling totp (app) code generation and such.
	 *
	 * @since 2.6.0
	 */
	class TOTP_Wizard_Steps extends Wizard_Steps {

		use Methods_Wizards_Trait;

		/**
		 * Keeps the main class method name, so we can call it when needed.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		private static $main_class = TOTP::class;

		/**
		 * The default value of the method order in the wizards.
		 *
		 * @var integer
		 *
		 * @since 2.6.0
		 */
		private static $order = 1;

		/**
		 * Inits the class hooks
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function init() {
			\add_filter( WP_2FA_PREFIX . 'methods_modal_options', array( __CLASS__, 'totp_option' ), 10, 2 );
			\add_action( WP_2FA_PREFIX . 'modal_methods', array( __CLASS__, 'totp_modal_configure' ) );
			\add_filter( WP_2FA_PREFIX . 'methods_re_configure', array( __CLASS__, 'totp_re_configure' ), 10, 2 );
			\add_filter( WP_2FA_PREFIX . 'methods_settings', array( __CLASS__, 'totp_wizard_settings' ), 10, 4 );
		}

		/**
		 * Shows the option to reconfigure email (if applicable)
		 *
		 * @param array  $methods - Array of methods collected.
		 * @param string $role - The name of the role to show option to.
		 *
		 * @since 2.6.0
		 *
		 * @return array
		 */
		public static function totp_re_configure( array $methods, string $role ): array {

			if ( ! TOTP::is_enabled() ) {
				return $methods;
			}
			\ob_start();
			?>
				<div class="option-pill">
					<?php echo \wp_kses_post( WP2FA::contextual_reconfigure_text( WP2FA::get_wp2fa_white_label_setting( 'totp_reconfigure_intro', true ), User_Helper::get_user_object()->ID, TOTP::METHOD_NAME ) ); ?>
					<div class="wp2fa-setup-actions">
						<a href="#" class="button button-primary wp-2fa-button-primary" data-name="next_step_setting_modal_wizard" data-trigger-reset-key <?php echo WP_Helper::create_data_nonce( self::json_nonce() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-user-id="<?php echo \esc_attr( User_Helper::get_user_object()->ID ); ?>" data-next-step="2fa-wizard-totp"><?php \esc_html_e( 'Reset Key', 'wp-2fa' ); ?></a>
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
		 * Shows the initial totp setup options based on enabled methods
		 *
		 * @param array  $methods - Array of methods collected.
		 * @param string $role - The name of the role to show option to.
		 *
		 * @since 2.6.0
		 *
		 * @return array
		 */
		public static function totp_option( array $methods, string $role ): array {
			if ( TOTP::is_enabled() ) {
				\ob_start();
				?>
				<div class="option-pill">
					<label for="basic">
						<input id="basic" name="wp_2fa_enabled_methods" type="radio" value="totp">
						<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true ) ); ?><span class="wizard-tooltip" data-tooltip-content="data-totp-tooltip-content-wrapper">i</span>
					</label>
					<?php
						echo '<p class="description tooltip-content-wrapper" data-totp-tooltip-content-wrapper>';
						echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'totp-option-label-hint', true ) );
						echo '</p>';
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
		 * Shows the TOTP modal configuration.
		 *
		 * @return void
		 *
		 * @since 2.6.0
		 */
		public static function totp_modal_configure() {
			if ( TOTP::is_enabled() ) {
				?>
			<div class="wizard-step" id="2fa-wizard-totp">
				<fieldset>
					<?php self::totp_configure(); ?>
				</fieldset>
			</div>
				<?php
			}
		}

		/**
		 * Reconfigures the totp form
		 *
		 * @since 2.6.0
		 *
		 * @return void
		 */
		public static function totp_configure() {

			if ( ! TOTP::is_enabled() ) {
				return;
			}

			// Regenerate the code if the method is not in use.
			if ( TOTP::METHOD_NAME !== User_Helper::get_enabled_method_for_user() ) {
				TOTP::remove_user_totp_key();
			}

			/**
			 * Active on modal, additional attribute is required on standard HTML (check below)
			 */
			$add_step_attributes = 'active';

			/**
			 * Closing div for extra modal wrappers see lines above
			 */
			$close_div = '';

			$qr_code                = '<img class="qr-code" src="' . ( TOTP::get_qr_code() ) . '" id="wp-2fa-totp-qrcode" />';
			$open30_wrapper         = '
				<div class="mb-30 clear-both">
				';
					$open60_wrapper = '
					<div class="modal-60">
				';
					$open40_wrapper = '
					<div class="modal-40">
				';
					$close_div      = '
				</div>
				';

			?>
				<div class="step-setting-wrapper <?php echo \esc_attr( $add_step_attributes ); ?>">
					<div class="mb-20">
						<?php echo wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_intro', true ) ); ?>
					</div>
					<?php echo $open30_wrapper . $open40_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<div class="qr-code-wrapper">
						<?php echo $qr_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<?php
					echo $close_div; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $open60_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>

					<div class="radio-cells option-pill mb-0">
						<ol class="wizard-custom-counter">
							<li><?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_1', true ) ); ?>
											<?php
											if ( ! empty( WP2FA::get_wp2fa_white_label_setting( 'show_help_text' ) ) ) {
												?>
								<span class="wizard-tooltip" data-tooltip-content="data-totp-setup-tooltip-content-wrapper">i</span><?php } ?></li>
							<li><?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_2', true ) ); ?>
								<div class="app-key-wrapper">
									<input type="text" id="app-key-input" readonly value="<?php echo \esc_html( TOTP::get_totp_decrypted() ); ?>" class="app-key">
									<?php
									if ( is_ssl() ) {
										?>
										<span class="click-to-copy"><?php \esc_html_e( 'COPY', 'wp-2fa' ); ?></span>
									<?php } ?>
								</div>
							</li>
							<li><?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_totp_step_3', true ) ); ?></li>
						</ol>
					</div>
						<?php
						echo $close_div; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $close_div; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<?php if ( ! empty( WP2FA::get_wp2fa_white_label_setting( 'show_help_text' ) ) ) : ?>
					<div class="tooltip-content-wrapper" data-totp-setup-tooltip-content-wrapper>
						<p class="description"><?php \esc_html_e( 'Click on the icon of the app that you are using for a detailed guide on how to set it up.', 'wp-2fa' ); ?></p>
						<div class="apps-wrapper">
							<?php foreach ( Authentication::get_apps() as $app ) { ?>
								<a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa#<?php echo $app['hash']; ?>" target="_blank" class="app-logo"><img src="<?php echo \esc_url( WP_2FA_URL . 'dist/images/' . $app['logo'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"></a>
							<?php } ?>
						</div>
					</div>
					<?php endif; ?>
					<div class="wp2fa-setup-actions">
						<button class="button wp-2fa-button-primary" name="next_step_setting" value="<?php \esc_attr_e( 'I\'m Ready', 'wp-2fa' ); ?>" type="button"><?php \esc_html_e( 'I\'m Ready', 'wp-2fa' ); ?></button>
						<a class="button button-primary wp-2fa-button-secondary modal_cancel"><?php \esc_attr_e( 'Cancel', 'wp-2fa' ); ?></a>
					</div>
				</div>
				<div class="step-setting-wrapper" data-step-title="<?php \esc_html_e( 'Verify configuration', 'wp-2fa' ); ?>">
					<div class="mb-20">
						<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_verification_totp_pre', true ) ); ?>
					</div>
					<fieldset>
						<label for="2fa-totp-authcode">
							<?php \esc_html_e( 'Authentication Code', 'wp-2fa' ); ?>
							<input type="tel" name="wp-2fa-totp-authcode" id="wp-2fa-totp-authcode" class="input" value="" size="20" pattern="[0-9]*" autocomplete="off"/>
							<script>
								const totp_authcode = document.getElementById('wp-2fa-totp-authcode');
								totp_authcode.addEventListener('input', function() {
								this.value = this.value.trim();
								});
							</script>
						</label>
						<div class="verification-response"></div>
					</fieldset>
					<input type="hidden" name="wp-2fa-totp-key" value="<?php echo \esc_attr( TOTP::get_totp_decrypted() ); ?>" />
					
					<a href="#" class="modal__btn button button-primary wp-2fa-button-primary" data-validate-authcode-ajax <?php echo WP_Helper::create_data_nonce( 'wp-2fa-validate-authcode' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php \esc_html_e( 'Validate & Save', 'wp-2fa' ); ?></a>
					<button class="modal__btn wp-2fa-button-secondary button button-secondary wp-2fa-button-secondary" data-close-2fa-modal aria-label="Close this dialog window"><?php \esc_html_e( 'Cancel', 'wp-2fa' ); ?></button>
				</div>

			<?php
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
		 * @since 2.6.0
		 */
		public static function totp_wizard_settings( array $methods, bool $setup_wizard, string $data_role, $role = null ) {
			$name_prefix = WP_2FA_POLICY_SETTINGS_NAME;
			$role_id     = '';
			if ( null !== $role && '' !== trim( (string) $role ) ) {
				$name_prefix .= "[{$role}]";
				$data_role    = 'data-role="' . $role . '"';
				$role_id      = '-' . $role;
			}
			\ob_start();
			?>
			<div id="<?php echo \esc_attr( TOTP::METHOD_NAME ); ?>-method-wrapper" class="method-wrapper">
				<?php echo self::hidden_order_setting( $role ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<label for="totp<?php echo \esc_attr( $role_id ); ?>" style="margin-bottom: 0 !important;">
					<input type="checkbox" id="totp<?php echo \esc_attr( $role_id ); ?>" name="<?php echo \esc_attr( $name_prefix ); ?>[enable_totp]" value="enable_totp"
					<?php echo $data_role; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<?php if ( null !== $role && ! empty( $role ) ) { ?>
						<?php \checked( TOTP::POLICY_SETTINGS_NAME, Role_Settings_Controller::get_setting( $role, TOTP::POLICY_SETTINGS_NAME ), true ); ?>
						<?php
					} else {
						$use_role_setting = null;
						if ( null === $role || '' === trim( (string) $role ) ) {
							$use_role_setting = \WP_2FA_PREFIX . 'no-user';
						}

						$enabled_settings = Settings::get_role_or_default_setting( TOTP::POLICY_SETTINGS_NAME, $use_role_setting, $role, true );
						?>
						<?php \checked( $enabled_settings, TOTP::POLICY_SETTINGS_NAME ); ?>
					<?php } ?>
					>
					<?php \esc_html_e( 'One-time code via 2FA App (TOTP) - ', 'wp-2fa' ); ?><a href="https://melapress.com/support/kb/wp-2fa-configuring-2fa-apps/?&utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank" rel=noopener><?php \esc_html_e( 'complete list of supported 2FA apps.', 'wp-2fa' ); ?></a>
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
		 * @since 2.6.0
		 */
		public static function totp_authentication_page( $user ) {
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