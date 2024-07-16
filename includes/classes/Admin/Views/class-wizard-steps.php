<?php
/**
 * Settings page render class.
 *
 * @package    wp2fa
 * @subpackage views
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Views;

use WP2FA\WP2FA;
use WP2FA\Utils\User_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WP2FA\Admin\Views\Wizard_Steps' ) ) {
	/**
	 * WP2FA Wizard Settings view controller
	 *
	 * @since 1.7
	 */
	class Wizard_Steps {

		/**
		 * Holds the nonce for json calls
		 *
		 * @since 1.7
		 *
		 * @var string
		 */
		private static $json_nonce = null;

		/**
		 * Holds the url to which to redirect the user after the setup is finished
		 *
		 * @var string
		 *
		 * @since 2.0.0
		 */
		private static $redirect_url = null;

		/**
		 * Introduction step form
		 *
		 * @since 1.7
		 *
		 * @return void
		 */
		public static function optional_user_welcome_step() {
			?>
			<div class="wizard-step active">
				<div class="mb-20">
					<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'welcome', true ) ); ?>
				</div>

				<div class="wp2fa-setup-actions">
					<a href="#" class="button wp-2fa-button-primary button-primary" data-name="next_step_setting_modal_wizard" data-next-step="choose-2fa-method"><?php \esc_html_e( 'Next Step', 'wp-2fa' ); ?></a>
					<button class="wp-2fa-button-secondary button button-secondary wp-2fa-button-secondary" data-close-2fa-modal aria-label="Close this dialog window"><?php \esc_html_e( 'Cancel', 'wp-2fa' ); ?></button>
				</div>
			</div>
			<?php
		}

		/**
		 * Introduction step form
		 *
		 * @since 1.7
		 *
		 * @return void
		 */
		public static function introduction_step() {
			?>
			<form method="post" class="wp2fa-setup-form">
				<?php wp_nonce_field( 'wp2fa-step-addon' ); ?>
				<div class="mb-20">
					<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( '2fa_required_intro', true ) ); ?>
				</div>

				<div class="wp2fa-setup-actions">
					<button class="button button-primary wp-2fa-button-primary"
					type="submit"
					name="save_step"
					value="<?php \esc_attr_e( 'Next', 'wp-2fa' ); ?>">
					<?php \esc_html_e( 'Next', 'wp-2fa' ); ?>
					</button>
				</div>
			</form>
			<?php
		}

		/**
		 * Welcome step of the wizard
		 *
		 * @since 1.7
		 *
		 * @param string $next_step - url of the next step.
		 *
		 * @return void
		 */
		public static function welcome_step( $next_step ) {
			$redirect = Settings::get_settings_page_link();

			?>
			<h3><?php \esc_html_e( 'Let us help you get started', 'wp-2fa' ); ?></h3>
			<p><?php \esc_html_e( 'Thank you for installing the WP 2FA plugin. This quick wizard will assist you with configuring the plugin and the two-factor authentication (2FA) settings for your user and the users on this website.', 'wp-2fa' ); ?></p>

			<div class="wp2fa-setup-actions">
				<a class="button button-primary"
					href="<?php echo \esc_url( $next_step ); ?>">
					<?php \esc_html_e( 'Let’s get started!', 'wp-2fa' ); ?>
				</a>
				<a class="button button-secondary wp-2fa-button-secondary first-time-wizard"
					href="<?php echo \esc_url( $redirect ); ?>">
					<?php \esc_html_e( 'Skip Wizard - I know how to do this', 'wp-2fa' ); ?>
				</a>
			</div>
			<?php
		}

		/**
		 * Configure backup codes step
		 *
		 * @since 1.7
		 *
		 * @return void
		 */
		public static function backup_codes_configure() {

			$user_type = User_Utils::determine_user_2fa_status( User_Helper::get_user_object() );

			$redirect = self::determine_redirect_url();
			?>
			<div class="step-setting-wrapper active">
			<?php
			if ( in_array( 'user_needs_to_setup_backup_codes', $user_type, true ) ) {
				?>
				<div class="mb-20">
					<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro', true ) ); ?>
				</div>
			<?php } else { ?>
				<div class="mb-20">
					<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro_continue', true ) ); ?>
				</div>
			<?php } ?>
			<div class="wp2fa-setup-actions">
			<?php if ( in_array( 'user_needs_to_setup_backup_codes', $user_type, true ) ) { ?>
				<button class="button button-primary wp-2fa-button-primary" name="next_step_setting" value="<?php \esc_attr_e( 'Generate backup codes', 'wp-2fa' ); ?>" data-trigger-generate-backup-codes <?php echo WP_Helper::create_data_nonce( self::json_nonce() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php \esc_html_e( 'Generate list of backup codes', 'wp-2fa' ); ?>
				</button>
				<?php
				if ( ! empty( $redirect ) ) {
					?>
					<a href="<?php echo \esc_url( $redirect ); ?>" class="button button-secondary wp-2fa-button-secondary wp-2fa-button-secondary close-first-time-wizard">
						<?php \esc_html_e( 'I’ll generate them later', 'wp-2fa' ); ?>
					</a>
					<?php
				} else {
					?>
					<a href="#" class="button wp-2fa-button-secondary" data-close-2fa-modal value="<?php \esc_attr_e( 'I’ll generate them later', 'wp-2fa' ); ?>">
						<?php \esc_html_e( 'I’ll generate them later', 'wp-2fa' ); ?>
					</a>
				<?php } ?>
			<?php } else { ?>
				<?php
				if ( ! empty( $redirect ) ) {
					?>
					<a href="<?php echo \esc_url( $redirect ); ?>" class="button button-secondary wp-2fa-button-secondary close-first-time-wizard">
					<?php \esc_html_e( 'Close wizard', 'wp-2fa' ); ?>
					</a>
					<?php
				} else {
					?>
				<a href="#" class="button button-secondary wp-2fa-button-secondary" data-reload>
					<?php \esc_html_e( 'Close wizard', 'wp-2fa' ); ?>
				</a>
				<?php } ?>
			<?php } ?>
			</div>
			</div>
				<?php
		}

		/**
		 * Generate backup codes step
		 *
		 * @since 1.7
		 *
		 * @return void
		 */
		public static function generate_backup_codes() {
			?>
			<div class="step-setting-wrapper active" data-step-title="<?php \esc_html_e( 'Generate codes', 'wp-2fa' ); ?>">
				<div class="mb-20">
					<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_generate_intro', true ) ); ?>
				</div>
				<div class="wp2fa-setup-actions">
					<button class="button button-primary" name="next_step_setting" value="<?php \esc_attr_e( 'Generate backup codes', 'wp-2fa' ); ?>" data-trigger-generate-backup-codes <?php echo WP_Helper::create_data_nonce( self::json_nonce() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php \esc_html_e( 'Generate list of backup codes', 'wp-2fa' ); ?>
					</button>
					<a href="#" class="button button-secondary wp-2fa-button-secondary" value="<?php \esc_attr_e( 'I’ll generate them later', 'wp-2fa' ); ?>" data-close-2fa-modal="">
						<?php \esc_html_e( 'I’ll generate them later', 'wp-2fa' ); ?>
					</a>
				</div>
			</div>

			<?php
		}

		/**
		 * Creates link for generating the backup codes
		 *
		 * @since 1.7
		 *
		 * @return string
		 */
		public static function get_generate_codes_label() {
			$label = __( 'Backup 2FA methods:', 'wp-2fa' );

			return $label . '</th><td>';
		}

		/**
		 * Creates backup codes URL link
		 *
		 * @return string
		 *
		 * @since 2.6.0
		 */
		public static function get_backup_codes_link(): string {
			return '<a href="#" class="button button-primary remove-2fa" data-trigger-generate-backup-codes ' . WP_Helper::create_data_nonce( self::json_nonce() ) . ' onclick="MicroModal.show( \'configure-2fa-backup-codes\' );">' . __( 'Generate list of backup codes', 'wp-2fa' ) . '</a>';
		}

		/**
		 * Shows the wrapper where backup code are generated and showed to the user
		 *
		 * @param boolean $backup_only - If we want to show backup window only - sets the class of the div to active.
		 *
		 * @since 1.7
		 *
		 * @return void
		 */
		public static function generated_backup_codes( $backup_only = false ) {

			$redirect = self::determine_redirect_url();

			?>
			<div class="step-setting-wrapper align-center<?php echo ( $backup_only ) ? ' active' : ''; ?>" data-step-title="<?php \esc_html_e( 'Your backup codes', 'wp-2fa' ); ?>">
				<div class="mb-20">
					<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_generated', true ) ); ?>
				</div>
				<div class="backup-key-wrapper">
					<textarea id="backup-codes-wrapper" readonly rows="4" cols="50" class="app-key"></textarea>
				</div>
				<div class="wp2fa-setup-actions">
					<?php if ( is_ssl() ) { ?>
						<button class="button button-primary wp-2fa-button-primary" type="submit" value="<?php \esc_attr_e( 'Download', 'wp-2fa' ); ?>" data-trigger-backup-code-copy>
							<?php \esc_html_e( 'Copy', 'wp-2fa' ); ?>
						</button>
					<?php } else { ?>
						<button class="button button-primary wp-2fa-button-primary" type="submit" value="<?php \esc_attr_e( 'Download', 'wp-2fa' ); ?>" data-trigger-backup-code-download data-user="<?php echo \esc_attr( User_Helper::get_user_object()->display_name ); ?>" data-website-url="<?php echo \esc_attr( get_home_url() ); ?>">
							<?php \esc_html_e( 'Download', 'wp-2fa' ); ?>
						</button>
					<?php } ?>
					<button class="button button-primary wp-2fa-button-primary" type="submit" value="<?php \esc_attr_e( 'Print', 'wp-2fa' ); ?>" data-trigger-print <?php echo WP_Helper::create_data_nonce( self::json_nonce() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-user-id="<?php echo \esc_attr( User_Helper::get_user_object()->display_name ); ?>" data-website-url="<?php echo \esc_attr( get_home_url() ); ?>">
						<?php \esc_html_e( 'Print', 'wp-2fa' ); ?>
					</button>

					<button class="button button-primary wp-2fa-button-primary" type="submit" value="<?php \esc_attr_e( 'Send me the codes via email', 'wp-2fa' ); ?>" data-trigger-backup-code-email <?php echo WP_Helper::create_data_nonce( 'wp-2fa-send-backup-codes-email-nonce' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-user-id="<?php echo \esc_attr( User_Helper::get_user_object()->ID ); ?>" data-website-url="<?php echo \esc_attr( get_home_url() ); ?>">
						<?php \esc_html_e( 'Send me the codes via email', 'wp-2fa' ); ?>
					</button>
					<?php
					if ( ! empty( $redirect ) ) {
						?>
						<a href="<?php echo \esc_url( $redirect ); ?>" class="button button-secondary wp-2fa-button-secondary wp-2fa-button-secondary close-first-time-wizard">
						<?php \esc_html_e( 'I\'m ready, close the wizard', 'wp-2fa' ); ?>
						</a>
						<?php
					} else {
						?>
					<button class="button button-secondary wp-2fa-button-secondary wp-2fa-button-secondary" type="submit" data-close-2fa-modal-and-refresh>
						<?php \esc_html_e( 'I\'m ready, close the wizard', 'wp-2fa' ); ?>
					</button>
					<?php } ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Final step for congratulating the user
		 *
		 * @since 1.7
		 *
		 * @param boolean $setup_wizard - Is that a call from setup wizard or not.
		 *
		 * @return void
		 */
		public static function congratulations_step( $setup_wizard = false ) {

			if ( $setup_wizard ) {
				self::congratulations_step_plugin_wizard();
				return;
			}

			$redirect = ( '' !== self::determine_redirect_url() ) ? self::determine_redirect_url() : '';
			?>

			<div class="step-setting-wrapper active">
			<div class="mb-20">
				<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'no_further_action', true ) ); ?>
			</div>
			<div class="wp2fa-setup-actions">
				<?php if ( '' !== trim( $redirect ) ) { ?>
				<a href="<?php echo \esc_url( $redirect ); ?>" class="button button-secondary wp-2fa-button-secondary close-first-time-wizard">
						<?php \esc_html_e( 'Close wizard', 'wp-2fa' ); ?>
				</a>
				<?php } else { ?>
				<button class="modal__btn wp-2fa-button-secondary button" data-close-2fa-modal aria-label="Close this dialog window"><?php \esc_html_e( 'Close wizard', 'wp-2fa' ); ?></button>
				<?php } ?>
			</div>
			</div>
			<?php
		}

		/**
		 * Final step for congratulating the user
		 *
		 * @since 1.7
		 *
		 * @return void
		 */
		public static function congratulations_step_plugin_wizard() {
			$redirect    = ( '' !== self::determine_redirect_url() ) ? self::determine_redirect_url() : get_edit_profile_url( User_Helper::get_user_object()->ID );
			$slide_title = ( User_Helper::is_excluded( User_Helper::get_user_object()->ID ) ) ? \esc_html__( 'Congratulations.', 'wp-2fa' ) : \esc_html__( 'Congratulations, you\'re almost there...', 'wp-2fa' );
			?>
				<h3><?php echo \esc_html( $slide_title ); ?></h3>
				<p><?php \esc_html_e( 'Great job, the plugin and 2FA policies are now configured. You can always change the plugin settings and 2FA policies at a later stage from the WP 2FA entry in the WordPress menu.', 'wp-2fa' ); ?></p>

					<?php
					if ( User_Helper::is_excluded( User_Helper::get_user_object()->ID ) ) {
						?>
				<div class="wp2fa-setup-actions">
					<a href="<?php echo \esc_url( $redirect ); ?>" class="button button-secondary wp-2fa-button-secondary close-first-time-wizard">
							<?php \esc_html_e( 'Close wizard', 'wp-2fa' ); ?>
					</a>
				</div>
						<?php
					} else {
						?>
				<p><?php \esc_html_e( 'Now you need to configure 2FA for your own user account. You can do this now (recommended) or later.', 'wp-2fa' ); ?></p>
				<div class="wp2fa-setup-actions">
					<a href="<?php echo \esc_url( Settings::get_setup_page_link() ); ?>" class="button button-primary wp-2fa-button-secondary">
						<?php \esc_html_e( 'Configure 2FA now', 'wp-2fa' ); ?>
					</a>
					<a href="<?php echo \esc_url( Settings::get_settings_page_link() ); ?>" class="button button-secondary wp-2fa-button-secondary close-first-time-wizard">
						<?php \esc_html_e( 'Close wizard & configure 2FA later', 'wp-2fa' ); ?>
					</a>
				</div>
					<?php } ?>
			<?php
		}

		/**
		 * Shows the methods in the modal wizard, so the user can choose from the available ones
		 *
		 * @return void
		 */
		public static function show_modal_methods() {
			/**
			 * Add an option for external providers to add their own modal methods options.
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'modal_methods' );
		}

		/**
		 * Choosing backup method step
		 * When there are more than one backup method - give the user ability to choose one
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function choose_backup_method() {
			$redirect = self::determine_redirect_url();
			?>
			<div class="wizard-step" id="2fa-wizard-backup-methods">
				<div class="option-pill mb-20">
					<?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro_multi', true ) ); ?>
				</div>
				<div class="radio-cells">
				<?php
				$backup_methods = Settings::get_backup_methods();

				$i = 0;
				foreach ( $backup_methods as $method_name => $method ) {
					$checked = '';
					if ( ! $i ) {
						$checked = ' checked="checked"';
					}
					$i = 1;
					?>
					<div class="option-pill"><label for="<?php echo \esc_attr( $method_name ); ?>"><input name="backup_method_select" data-step="<?php echo \esc_attr( $method['wizard-step'] ); ?>" type="radio" id="<?php echo \esc_attr( $method_name ); ?>" <?php echo $checked; ?>><?php echo $method['button_name']; // phpcs:ignore ?></label><br /></div>
					<?php
				}
				?>
				</div>
				<div class="wp2fa-setup-actions">
					<a id="select-backup-method" href="<?php echo \esc_url( Settings::get_setup_page_link() ); ?>" class="button button-primary wp-2fa-button-primary">
						<?php \esc_html_e( 'Configure backup 2FA method', 'wp-2fa' ); ?>
					</a>
					<a href="<?php echo \esc_url( $redirect ); ?>" class="button button-secondary wp-2fa-button-secondary close-first-time-wizard"  <?php echo ( ( '' === trim( (string) $redirect ) ) ? 'data-close-it=""' : '' ); ?>  >
							<?php \esc_html_e( 'Close wizard & configure 2FA later', 'wp-2fa' ); ?>
					</a>
					<script>
						const closeButton = document.querySelector('[data-close-it]');

						if (closeButton) {
							closeButton.addEventListener('click', (event) => {
								event.preventDefault();
								let url = new URL( location.href );
								let params = new URLSearchParams( url.search );
								params.delete('show'); 
								location.replace( `${location.pathname}?${params}` );
							});
						}
					</script>
				</div>
			</div>
			<?php
		}

		/**
		 * Determines the redirect url for the user
		 *
		 * @return string
		 *
		 * @since 2.0.0
		 */
		public static function determine_redirect_url(): string {
			if ( null === self::$redirect_url ) {
				$redirect_page      = Settings::get_role_or_default_setting( 'redirect-user-custom-page-global', User_Helper::get_user_object() );
				self::$redirect_url = ( '' !== trim( (string) $redirect_page ) ) ? \trailingslashit( get_site_url() ) . $redirect_page : '';

				if (
				'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', User_Helper::get_user_object() ) ||
				'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page' ) ) {
					if (
					'' !== trim( (string) Settings::get_role_or_default_setting( 'redirect-user-custom-page', User_Helper::get_user_object() ) ) ||
					'' !== trim( (string) Settings::get_role_or_default_setting( 'redirect-user-custom-page' ) ) ) {
						if ( 'yes' === Settings::get_role_or_default_setting( 'create-custom-user-page', User_Helper::get_user_object() ) ) {
							self::$redirect_url = trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page', User_Helper::get_user_object() );
						} else {
							self::$redirect_url = trailingslashit( get_site_url() ) . Settings::get_role_or_default_setting( 'redirect-user-custom-page' );
						}
					}
				}
			}

			return self::$redirect_url;
		}

		/**
		 * Generates nonce for JSON calls
		 *
		 * @since 1.7
		 *
		 * @return string
		 */
		protected static function json_nonce() {
			if ( null === self::$json_nonce ) {
				self::$json_nonce = 'wp-2fa-backup-codes-generate-json-' . User_Helper::get_user_object()->ID;
			}

			return self::$json_nonce;
		}
	}
}
