<?php
/**
 * Responsible for WP2FA user's Email manipulation.
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
use WP2FA\Methods\Email;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Views\Wizard_Steps;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Admin\Methods\Traits\Methods_Wizards_Trait;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

/**
 * Class for handling email codes.
 *
 * @since 2.6.0
 *
 * @package WP2FA
 */
if ( ! class_exists( '\WP2FA\Methods\Wizards\Email_Wizard_Steps' ) ) {
	/**
	 * Email code class, for handling email code generation and such.
	 *
	 * @since 2.6.0
	 */
	class Email_Wizard_Steps extends Wizard_Steps {

		use Methods_Wizards_Trait;

		/**
		 * Keeps the main class method name, so we can call it when needed.
		 *
		 * @var string
		 *
		 * @since 2.6.0
		 */
		private static $main_class = Email::class;

		/**
		 * The default value of the method order in the wizards.
		 *
		 * @var integer
		 *
		 * @since 2.6.0
		 */
		private static $order = 2;

		/**
		 * Inits the class hooks
		 *
		 * @return void
		 *
		 * @since 2.4.0
		 */
		public static function init() {
			\add_filter( WP_2FA_PREFIX . 'methods_modal_options', array( __CLASS__, 'email_option' ), 10, 2 );
			\add_action( WP_2FA_PREFIX . 'modal_methods', array( __CLASS__, 'email_modal_configure' ) );
			\add_filter( WP_2FA_PREFIX . 'methods_re_configure', array( __CLASS__, 'email_re_configure' ), 10, 2 );
			\add_filter( WP_2FA_PREFIX . 'methods_settings', array( __CLASS__, 'email_wizard_settings' ), 10, 4 );
		}

		/**
		 * Shows the option for email method reconfiguring (if applicable)
		 *
		 * @param array  $methods - Array of methods collected.
		 * @param string $role - The name of the role to show option to.
		 *
		 * @since 2.6.0 - Parameter $methods is added, parameter $role (name) is added and array is now returned
		 *
		 * @return array
		 */
		public static function email_re_configure( array $methods, string $role ): array {

			if ( ! Email::is_enabled() ) {
				return $methods;
			}
			\ob_start();
			?>
			<div class="option-pill">
				<?php echo \wp_kses_post( WP2FA::contextual_reconfigure_text( WP2FA::get_wp2fa_white_label_setting( 'hotp_reconfigure_intro', true ), User_Helper::get_user_object()->ID, 'hotp' ) ); ?>
				<div class="wp2fa-setup-actions">
					<a class="button button-primary wp-2fa-button-primary" data-name="next_step_setting_modal_wizard" value="<?php \esc_attr_e( 'I\'m Ready', 'wp-2fa' ); ?>" data-user-id="<?php echo \esc_attr( User_Helper::get_user_object()->ID ); ?>" <?php echo WP_Helper::create_data_nonce( 'wp-2fa-send-setup-email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-next-step="2fa-wizard-email"><?php \esc_html_e( 'Change email address', 'wp-2fa' ); ?></a>
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
		 * Shows the initial email setup options based on enabled methods
		 *
		 * @param array  $methods - Array of methods collected.
		 * @param string $role - The name of the role to show option to.
		 *
		 * @since 2.6.0 - Parameter $methods is added, parameter $role (name) is added and array is now returned
		 *
		 * @return array
		 */
		public static function email_option( array $methods, string $role ): array {
			if ( Email::is_enabled() ) {
				\ob_start();
				?>
					<div class="option-pill">
						<label for="geek">
							<input id="geek" name="wp_2fa_enabled_methods" type="radio" value="email">
						<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'email-option-label', true ) ); ?>
						</label>
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
		 * @since 2.6.0
		 */
		public static function email_wizard_settings( array $methods, bool $setup_wizard, string $data_role, $role = null ) {
			$name_prefix = \WP_2FA_POLICY_SETTINGS_NAME;
			$role_id     = '';
			if ( null !== $role && '' !== trim( (string) $role ) ) {
				$name_prefix .= "[{$role}]";
				$data_role    = 'data-role="' . $role . '"';
				$role_id      = '-' . $role;
			}
			\ob_start();
			?>
				<div id="<?php echo \esc_attr( Email::METHOD_NAME ); ?>-method-wrapper" class="method-wrapper">
					<?php echo self::hidden_order_setting( $role ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<label for="hotp<?php echo \esc_attr( $role_id ); ?>" style="margin-bottom: 0 !important;">
							<input type="checkbox" id="hotp<?php echo \esc_attr( $role_id ); ?>" name="<?php echo \esc_attr( $name_prefix ); ?>[enable_email]" value="enable_email"
							<?php echo $data_role; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php if ( null !== $role && ! empty( $role ) ) { ?>
								<?php \checked( Email::POLICY_SETTINGS_NAME, Role_Settings_Controller::get_setting( $role, Email::POLICY_SETTINGS_NAME ), true ); ?>
								<?php
							} else {
								$use_role_setting = null;
								if ( null === $role || '' === trim( (string) $role ) ) {
									$use_role_setting = \WP_2FA_PREFIX . 'no-user';
								}

								$enabled_settings = Settings::get_role_or_default_setting( Email::POLICY_SETTINGS_NAME, $use_role_setting, $role, true );
								?>
								<?php \checked( $enabled_settings, Email::POLICY_SETTINGS_NAME ); ?>
							<?php } ?>
							>
							<?php
							\esc_html_e( 'One-time code via email (HOTP)', 'wp-2fa' );
							\esc_html_e( ' - ensure email deliverability with the free plugin ', 'wp-2fa' );
							echo '<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" rel="nofollow">WP Mail SMTP</a>.';
							?>
					</label>
					<?php
					if ( $setup_wizard ) {
						echo '<p class="description">' . \esc_html__( 'When using this method, users will receive the one-time login code over email. Therefore, email deliverability is very important. Users using this method should whitelist the address from which the codes are sent. By default, this is the email address configured in your WordPress. You can run an email test from the plugin\'s settings to confirm email deliverability. If you have had email deliverability / reliability issues, we highly recommend you to install the free plugin ', 'wp-2fa' ) . '<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" rel="nofollow">WP Mail SMTP</a><br><br>' . \esc_html__( 'Allowing users to set up a secondary 2FA method is highly recommended. You can do this in the next step of the wizard. This will allow users to log in using an alternative method should they, for example lose access to their phone.', 'wp-2fa' ) . '</p>';
					}
					?>
					<?php
					if ( null !== $role ) {
						$enabled_settings = Role_Settings_Controller::get_setting( $role, 'enable_email' );
					} else {
						$enabled_settings = Settings::get_role_or_default_setting( 'enable_email', ( ( null !== $role && '' !== $role ) ? '' : false ), $role, true, true );
					}
					?>
					<?php
					?>
					<?php if ( ! $setup_wizard ) { ?>
						<div class="use-different-hotp-mail<?php echo \esc_attr( ( false === $enabled_settings ? ' disabled' : '' ) ); ?>">
							<p class="description">
								<?php \esc_html_e( 'Allow user to specify the email address of choice', 'wp-2fa' ); ?>
							</p>
							<fieldset class="email-hotp-options">
							<?php
							$options = array(
								'yes' => array(
									'label' => \esc_html__( 'Yes', 'wp-2fa' ),
									'value' => 'specify-email_hotp',
								),
								'no'  => array(
									'label' => \esc_html__( 'No', 'wp-2fa' ),
									'value' => '',
								),
							);

							foreach ( $options as $option_key => $option_settings ) {
								?>
								<label for="specify-email_hotp-<?php echo \esc_attr( $option_key ); ?>">
									<input type="radio"
									name="<?php echo \esc_attr( $name_prefix ); ?>[specify-email_hotp]"
									<?php echo $data_role; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									id="specify-email_hotp-<?php echo \esc_attr( $option_key ); ?>"
									value="<?php echo \esc_attr( $option_settings['value'] ); ?>" class="js-nested"
									<?php if ( null !== $role ) { ?>
										<?php \checked( Role_Settings_Controller::get_setting( $role, 'specify-email_hotp' ), $option_settings['value'] ); ?>
										<?php
									} else {

										$use_role_setting = null;
										if ( null === $role || '' === trim( (string) $role ) ) {
											$use_role_setting = \WP_2FA_PREFIX . 'no-user';
										}

										\checked( Settings::get_role_or_default_setting( 'specify-email_hotp', $use_role_setting, $role, true, false ), $option_settings['value'] );
										?>
									<?php } ?>
									>
									<span><?php echo $option_settings['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								</label>
								<?php
							}
							?>
							</fieldset>
						</div>
					<?php } ?>
				</div>
			<?php
			$output = ob_get_contents();
			ob_end_clean();

			$methods[ self::get_order( $role, $methods ) ] = $output;

			return $methods;
		}

		/**
		 * Reconfigures email form
		 *
		 * @since 2.6.0
		 *
		 * @return void
		 */
		public static function email_modal_configure() {

			if ( ! Email::is_enabled() ) {
				return;
			}
			?>
			<div class="wizard-step" id="2fa-wizard-email">
				<fieldset>
					<div class="step-setting-wrapper active">
						<div class="mb-20">
							<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_hotp_intro', true ) ); ?>
						</div>
						<fieldset class="radio-cells">
						<div class="option-pill">
							<label for="use_wp_email">
								<input type="radio" name="wp_2fa_email_address" id="use_wp_email" value="<?php echo \esc_attr( User_Helper::get_user_object()->user_email ); ?>" checked>
								<span><?php \esc_html_e( 'Use my user email (', 'wp-2fa' ); ?><small><?php echo \esc_attr( User_Helper::get_user_object()->user_email ); ?></small><?php \esc_html_e( ')', 'wp-2fa' ); ?></span>
							</label>
						</div>
						<?php
						if ( Settings::get_role_or_default_setting( 'specify-email_hotp', User_Helper::get_user_object() ) ) {
							?>
						<div class="option-pill">
							<label for="use_custom_email">
								<input type="radio" name="wp_2fa_email_address" id="use_custom_email" value="use_custom_email">
								<span><?php \esc_html_e( 'Use a different email address:', 'wp-2fa' ); ?></span>
								<?php \esc_html_e( 'Email address', 'wp-2fa' ); ?>
								<input type="email" name="custom-email-address" id="custom-email-address" class="input" value=""/>
							</label>
						</div>
							<?php
						}
						?>
						</fieldset>
						<p class="description"><?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_help_hotp_help', true ) ); ?></p><br>

						<?php
						$from_email = \get_option( 'admin_email' );

						$custom_mail = WP2FA::get_wp2fa_email_templates( 'custom_from_email_address' );

						if ( isset( $custom_mail ) && ! empty( (string) $custom_mail ) ) {
							$from_email = $custom_mail;
						}

						echo \wp_kses_post( str_replace( '{from_email}', $from_email, WP2FA::get_wp2fa_white_label_setting( 'method_help_hotp_help_email', true ) ) );
						?>

						<div class="wp2fa-setup-actions">
							<button class="button button-primary wp-2fa-button-primary" name="next_step_setting_email_verify" value="<?php \esc_attr_e( 'I\'m Ready', 'wp-2fa' ); ?>" data-trigger-setup-email data-user-id="<?php echo \esc_attr( User_Helper::get_user_object()->ID ); ?>" <?php echo WP_Helper::create_data_nonce( 'wp-2fa-send-setup-email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> type="button"><?php \esc_html_e( 'I\'m Ready', 'wp-2fa' ); ?></button>
							<a class="button button-primary wp-2fa-button-primary modal_cancel"><?php \esc_attr_e( 'Cancel', 'wp-2fa' ); ?></a>
						</div>
					</div>

					<div class="step-setting-wrapper" data-step-title="<?php \esc_html_e( 'Verify configuration', 'wp-2fa' ); ?>" id="2fa-wizard-email">
						<div class="mb-20">
							<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'method_verification_hotp_pre', true ) ); ?>
						</div>
						<fieldset>
							<label for="2fa-email-authcode">
								<?php \esc_html_e( 'Authentication Code', 'wp-2fa' ); ?>
								<input type="tel" name="wp-2fa-email-authcode" id="wp-2fa-email-authcode" class="input" value="" size="20" pattern="[0-9]*" autocomplete="off"/>
								<script>
									const email_authcode = document.getElementById('wp-2fa-email-authcode');
									email_authcode.addEventListener('input', function() {
									this.value = this.value.trim();
									});
								</script>
							</label>
							<div class="verification-response"></div>
						</fieldset>
						<br />
						<a href="#" class="button wp-2fa-button-primary" data-validate-authcode-ajax <?php echo WP_Helper::create_data_nonce( 'wp-2fa-validate-authcode' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php \esc_html_e( 'Validate & Save', 'wp-2fa' ); ?></a>
						<a href="#" class="button wp-2fa-button-primary resend-email-code" data-trigger-setup-email data-user-id="<?php echo \esc_attr( User_Helper::get_user_object()->ID ); ?>" <?php echo WP_Helper::create_data_nonce( 'wp-2fa-send-setup-email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<span class="resend-inner"><?php \esc_html_e( 'Send me another code', 'wp-2fa' ); ?></span>
						</a>
						<button class="wp-2fa-button-secondary button" data-close-2fa-modal aria-label="Close this dialog window"><?php \esc_html_e( 'Cancel', 'wp-2fa' ); ?></button>
					</div>
				</fieldset>
			</div>
			<?php
		}
	}
}
