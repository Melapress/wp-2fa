<?php
/**
 * Responsible for WP2FA user's profile settings.
 *
 * @package    wp2fa
 * @subpackage user-utils
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin;

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Methods\Email;
use WP2FA\Utils\User_Utils;
use WP2FA\Extensions_Loader;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Utils\Generate_Modal;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Authenticator\Open_SSL;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Freemius\User_Licensing;
use WP2FA\Admin\Views\Wizard_Steps;
use WP2FA\Admin\Controllers\Methods;
use WP2FA\Admin\Helpers\User_Helper;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use WP2FA\Extensions\OutOfBand\Out_Of_Band;

/**
 * User_Profile class responsible for the profile page operations
 *
 * @since 2.4.0
 */
if ( ! class_exists( '\WP2FA\Admin\User_Profile' ) ) {
	/**
	 * User_Profile - Class for handling user things such as profile settings and admin list views.
	 *
	 * @since 2.7.0
	 */
	class User_Profile {

		/**
		 * Add our buttons to the user profile editing screen.
		 *
		 * @param object $user User data.
		 * @param array  $additional_args - Array with extra parameters for the method.
		 *
		 * @since 2.7.0
		 */
		public static function user_2fa_options( $user, $additional_args = array() ) {

			if ( isset( $_GET['user_id'] ) ) { // phpcs:ignore
				$user_id = (int) $_GET['user_id']; // phpcs:ignore
				$user    = \get_user_by( 'id', $user_id );
			} else {
				// Get current user, we're going to need this regardless.
				$user = \wp_get_current_user();
			}

			if ( ! is_a( $user, '\WP_User' ) ) {
				return;
			}

			// Ensure we have something in the settings.
			if ( empty( Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME ) ) ) {
				return;
			}

			$show_preamble = true;
			if ( isset( $additional_args['show_preamble'] ) ) {
				$show_preamble = \filter_var( $additional_args['show_preamble'], FILTER_VALIDATE_BOOLEAN );
			}

			$user_type = User_Utils::determine_user_2fa_status( $user );

			$form_output     = '';
			$form_content    = '';
			$description     = WP2FA::get_wp2fa_white_label_setting( 'user-profile-form-preamble-desc', true );
			$show_form_table = true;
			$page_url        = ( WP_Helper::is_multisite() ) ? 'index.php' : 'options-general.php';

			// Orphan user (a user with no role or capabilities).
			if ( in_array( 'orphan_user', $user_type, true ) ) {
				// We want to use the same form/buttons used in the shortcode.
				$additional_args['is_shortcode'] = true;

				// Create useful message for admin.
				if ( User_Utils::in_array_all( array( 'user_needs_to_setup_2fa', 'can_manage_options' ), $user_type ) ) {
					$description = \esc_html__( 'This user is required to setup 2FA but has not yet done so.', 'wp-2fa' );
				}

				if ( User_Utils::in_array_all( array( 'user_is_excluded', 'can_manage_options' ), $user_type ) ) {
					$description = \esc_html__( 'This user is excluded from configuring 2FA.', 'wp-2fa' );
				}
			}

			// Excluded user.
			if ( in_array( 'user_is_excluded', $user_type, true ) ) {
				return;
			}

			// A user viewing their own profile AND has a 2FA method configured.
			if ( User_Utils::in_array_all( array( 'viewing_own_profile' ), $user_type ) ) {
				if (
				User_Utils::in_array_all( array( 'has_enabled_methods' ), $user_type ) ||
				User_Utils::in_array_all( array( 'no_required_has_enabled' ), $user_type )
				) {

					if ( isset( $additional_args['is_shortcode'] ) && $additional_args['is_shortcode'] ) {
						$form_content = '';

						/**
						 * Gives the ability to remove the user's settings.
						 *
						 * @param bool - The status of the settings.
						 *
						 * @since 2.2.2
						 */
						$show_enable2fa = \apply_filters( WP_2FA_PREFIX . 'enable_2fa_user_setting', true );

						/**
						 * Gives the ability to change the user profile description message.
						 *
						 * @param bool - The status of the settings.
						 *
						 * @since 2.4.0
						 */
						$description = \apply_filters( WP_2FA_PREFIX . 'enable_2fa_user_setting_description', $description );

						$styling_class = ( empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ) ? 'default_styling' : 'enable_styling';

						if ( $show_enable2fa ) {
							$form_content = '<a href="#" class="button button-primary remove-2fa ' . \esc_attr( $styling_class ) . '" data-open-configure-2fa-wizard>' . \esc_html__( 'Change 2FA settings', 'wp-2fa' ) . '</a>';
						}

						if ( self::can_user_remove_2fa( $user->ID ) ) {
							$form_content .= '<a href="#" class="button button-primary remove-2fa ' . \esc_attr( $styling_class ) . '" onclick="MicroModal.show(\'confirm-remove-2fa\');">' . \esc_html__( 'Remove 2FA', 'wp-2fa' ) . '</a>';
						}

						$form_content     .= '</td><tr><th class="backup-methods-label">';
						$backup_codes_desc = '';
						if ( Backup_Codes::are_backup_codes_enabled_for_role( User_Helper::get_user_role( $user ) ) ) {
							$codes_remaining = Backup_Codes::codes_remaining_for_user( $user );
							if ( $codes_remaining > 0 ) {
								$backup_codes_desc = '<span class="description mt-5px">' . \esc_attr( (int) $codes_remaining ) . ' ' . \esc_html__( 'unused backup codes remaining.', 'wp-2fa' ) . '</span>';
							} elseif ( 0 === $codes_remaining ) {
								$backup_codes_desc = '<a class="learn_more_link" href="https://melapress.com/2fa-backup-codes/?utm_source=plugin&utm_medium=link&utm_campaign=wp2fa" target="_blank">' . \esc_html__( 'Learn more about backup codes', 'wp-2fa' ) . '</a>';
							}

							if ( ! empty( $backup_codes_desc ) ) {
								$backup_codes_desc = Wizard_Steps::get_backup_codes_link() . $backup_codes_desc;
							}
						}

						/**
						 * Add an option for external providers to add their own user form buttons.
						 *
						 * @since 2.0.0
						 */
						$backup_codes_desc = apply_filters( WP_2FA_PREFIX . 'additional_form_buttons', $backup_codes_desc );

						if ( ! empty( $backup_codes_desc ) ) {
							$form_content .= Wizard_Steps::get_generate_codes_label() . $backup_codes_desc;
						}

						$form_content .= '</th></tr>';
					}
				}

				$show_if_user_is_not_in = array(
					'user_is_excluded',
					'has_enabled_methods',
					'no_required_has_enabled',
				);

				// User viewing own profile and needs to enable 2FA.
				if (
				User_Utils::in_array_all( array( 'user_needs_to_setup_2fa' ), $user_type ) ||
				User_Utils::role_is_not( $show_if_user_is_not_in, $user_type )
				) {

					$first_time_setup_url = Settings::get_setup_page_link();

					/**
					 * Gives the ability to remove the user's settings.
					 *
					 * @param bool - The status of the settings.
					 *
					 * @since 2.2.2
					 */
					$show_enable2fa = \apply_filters( WP_2FA_PREFIX . 'enable_2fa_user_setting', true );


					/**
					 * Gives the ability to change the user profile description message.
					 *
					 * @param bool - The status of the settings.
					 *
					 * @since 2.4.0
					 */
					$description = \apply_filters( WP_2FA_PREFIX . 'enable_2fa_user_setting_description', $description );

					$styling_class = ( empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ) ? 'default_styling' : 'enable_styling';

					if ( $show_enable2fa ) {

						if ( isset( $additional_args['is_shortcode'] ) && $additional_args['is_shortcode'] ) {
							$form_content .= '<a href="#" class="button button-primary ' . \esc_attr( $styling_class ) . '" data-open-configure-2fa-wizard>' . \esc_html__( 'Configure 2FA', 'wp-2fa' ) . '</a>';
						}

						if ( empty( $additional_args ) ) {
							$form_content .= '<a href="' . \esc_url( $first_time_setup_url ) . '" class="button button-primary ' . \esc_attr( $styling_class ) . '">' . \esc_html__( 'Configure Two-factor authentication (2FA)', 'wp-2fa' ) . '</a>';
						}
					}
				}
			}

			// Admin viewing users profile AND user has a configured 2FA method.
			if ( User_Utils::in_array_all( array( 'can_manage_options', 'has_enabled_methods' ), $user_type ) && ! in_array( 'viewing_own_profile', $user_type, true ) ) {
				$description = \esc_html__( 'The user has already configured 2FA. When you reset the user\'s current 2FA configuration, the user can log back in with just the username and password.', 'wp-2fa' );

				$remove_users_2fa_url = add_query_arg(
					array(
						'action'       => 'remove_user_2fa',
						'user_id'      => $user->ID,
						'wp_2fa_nonce' => wp_create_nonce( 'wp-2fa-remove-user-2fa-nonce' ),
						'admin_reset'  => 'yes',
					),
					admin_url( 'user-edit.php' )
				);

				$form_content .= '<a href="' . \esc_url( $remove_users_2fa_url ) . '" class="button button-primary">' . \esc_html__( 'Reset 2FA configuration', 'wp-2fa' ) . '</a>';
			}

			// Admin viewing users profile AND users grace period has expired.
			if ( User_Utils::in_array_all( array( 'can_manage_options', 'grace_has_expired' ), $user_type ) ) {
				$unlock_user_url = add_query_arg(
					array(
						'action'       => 'unlock_account',
						'user_id'      => $user->ID,
						'wp_2fa_nonce' => wp_create_nonce( 'wp-2fa-unlock-account-nonce' ),
					),
					admin_url( 'user-edit.php' )
				);
				$form_content   .= '<a href="' . \esc_url( $unlock_user_url ) . '" class="button button-primary">' . \esc_html__( 'Unlock user and reset the grace period', 'wp-2fa' ) . '</a>';
			}

			if ( $show_preamble ) {
				$form_output .= '<h2>' . WP2FA::get_wp2fa_white_label_setting( 'user-profile-form-preamble-title', true ) . '</h2>';

				if ( $description ) {
					$form_output .= '<p class="description">' . $description . '</p>';
				}
			}
			/**
			 * Gives the ability to add more content to the profile page.
			 *
			 * @param string $form_content - The parsed HTML of the form.
			 */
			$form_content = apply_filters( WP_2FA_PREFIX . 'append_to_profile_form_content', $form_content );

			if ( $show_form_table && ! empty( $form_content ) ) {

				$enabled_methods        = User_Helper::get_enabled_method_for_user( $user );
				$primary_label          = ( isset( $enabled_methods ) && ! empty( $enabled_methods ) ) ? Settings::get_providers_translate_names()[ $enabled_methods ] : \esc_html__( 'No enabled primary method', 'wp-2fa' );
				$enabled_backup_methods = User_Helper::get_enabled_backup_methods_for_user( $user );
				$backup_methods_enabled = \esc_html__( 'No enabled backup methods', 'wp-2fa' );

				if ( isset( $enabled_backup_methods ) && ! empty( $enabled_backup_methods ) ) {
					$backup_methods_enabled = \implode( ', ', $enabled_backup_methods );
				}

				$show_enabled = true;

				if ( isset( $additional_args ) && ! empty( $additional_args ) && isset( $additional_args['options'] ) && ! empty( $additional_args['options'] ) ) {
					if ( isset( $additional_args['options']['do_not_show_enabled'] ) && 'false' !== $additional_args['options']['do_not_show_enabled'] ) {
						$show_enabled = false;
					}
				}

				if ( $show_enabled ) {

					$form_output .= '<h3>' . \esc_html__( 'Currently configured:', 'wp-2fa' ) . '</h3>';

					$form_output .= '
					<table id="2fa-currently-configured-methods" class="form-table wp-2fa-user-profile-form" role="presentation">
						<tbody>
							<tr>
								<th><label>' . \esc_html__( 'Primary method:', 'wp-2fa' ) . '</label></th>
								<td>
								' . $primary_label . '
								</td>
							</tr>';

							$form_output .= '
							<tr>
								<th><label>' . \esc_html__( 'Secondary method(s):', 'wp-2fa' ) . '</label></th>
								<td>
								' . $backup_methods_enabled . '
								</td>
							</tr>';

							$form_output .= '
						</tbody>
					</table>';
				}

				$form_output .= '<h3>' . \esc_html__( '2FA configuration:', 'wp-2fa' ) . '</h3>';

				if ( User_Utils::in_array_all( array( 'has_enabled_methods', 'viewing_own_profile' ), $user_type ) && isset( $enabled_methods ) && TOTP::METHOD_NAME === $enabled_methods ) {
					$form_output .= '
					<table id="2fa-configuration-options" class="form-table wp-2fa-user-profile-form remove-tr-padding" role="presentation">
						<tbody>
							<tr>
								<th><label>' . Settings::get_providers_translate_names()[ $enabled_methods ] . '</label></th>
								<td>
								<details>
									<summary class="qr-btn">' . \esc_html__( 'Show QR code', 'wp-2fa' ) . '</summary>
									<p><img class="qr-code" src="' . ( TOTP::get_qr_code() ) . '" /></p>
									<div class="app-key-wrapper">
										<input type="text" id="app-key-input" readonly value="' . \esc_html( TOTP::get_totp_decrypted() ) . '" class="app-key">
										' .
										( ( is_ssl() ) ?
											'<span class="click-to-copy">' . \esc_html__( 'COPY', 'wp-2fa' ) . '</span>' : '' ) . '
									</div>	
								</details>
								</td>
							</tr>
						</tbody>
					</table>';
				}

				$form_output .= '
					<table id="2fa-user-global-configuration" class="form-table wp-2fa-user-profile-form" role="presentation">
						<tbody>
							<tr>
								<th><label>' . \esc_html__( '2FA Setup:', 'wp-2fa' ) . '</label></th>
								<td>
								' . $form_content . '
								</td>
							</tr>
						</tbody>
					</table>';

				if ( ( isset( $_GET['show'] ) && 'wp-2fa-setup' === $_GET['show'] ) || User_Helper::get_user_enforced_instantly( $user ) ) { // phpcs:ignore
					$form_output .= '
					<script>
					window.addEventListener("load", function() {
						wp2fa_fireWizard();
					});
					</script>
				';
				}
			}

			echo $form_output; // phpcs:ignore

			self::generate_inline_modals( $user_type );
		}

		/**
		 * Responsible for the building of all the modals.
		 *
		 * @param array $user_type - The WP user type.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function generate_inline_modals( $user_type = array() ) {

			ob_start();

			$user = \wp_get_current_user();

			$styling_class = ( empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ) ? 'default_styling' : 'enable_styling';

			if ( User_Utils::in_array_all( array( 'user_needs_to_setup_2fa', 'viewing_own_profile' ), $user_type ) || User_Utils::in_array_all( array( 'has_enabled_methods', 'viewing_own_profile' ), $user_type ) || User_Utils::in_array_all( array( 'no_required_not_enabled', 'viewing_own_profile' ), $user_type ) || User_Utils::in_array_all( array( User_Helper::USER_UNDETERMINED_STATUS, 'viewing_own_profile' ), $user_type ) ) { ?>
			<div>
				<div class="wp2fa-modal micromodal-slide <?php echo \esc_attr( $styling_class ); ?>" id="configure-2fa" aria-hidden="true">
					<div class="modal__overlay" tabindex="-1">
						<div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
								<?php
								echo Generate_Modal::generate_modal( // phpcs:ignore
									'notify-users',
									__( 'Are you sure?', 'wp-2fa' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									__( 'Any unsaved changes will be lost!', 'wp-2fa' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									array(
										'<button class="button wp-2fa-button-primary button-primary button-confirm" aria-label="Close this dialog window and the wizard">' . \esc_html__( 'Yes', 'wp-2fa' ) . '</button>',
										'<button class="button wp-2fa-button-secondary button-secondary button-decline" data-micromodal-close aria-label="Close this dialog window">' . \esc_html__( 'No', 'wp-2fa' ) . '</button>',
									),
									'',
									'430px'
								);
								?>
							<button class="modal__close modal_cancel" aria-label="Close modal"></button>
							<main class="modal__content wp2fa-form-styles" id="modal-1-content">
								<?php
								$logo_url     = WP2FA::get_wp2fa_white_label_setting( 'logo-code-page', false );
								$logo_section = ( $logo_url ) ? '<p class="modal-logo-wrapper"><img style="max-height: 60px;margin: 0 auto 30px;" src="' . \esc_url( $logo_url ) . '" /></p>' : '';
								$enable_logo  = WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_logo', false );

								if ( $enable_logo ) {
									echo $logo_section; // phpcs:ignore */
								}

								if ( User_Utils::in_array_all( array( 'user_needs_to_setup_2fa', 'viewing_own_profile' ), $user_type ) || User_Utils::in_array_all( array( 'no_required_not_enabled', 'viewing_own_profile' ), $user_type ) || User_Utils::in_array_all( array( User_Helper::USER_UNDETERMINED_STATUS, 'viewing_own_profile' ), $user_type ) ) {

									$available_methods = Methods::get_enabled_methods( User_Helper::get_user_role( $user ) );
									$optional_welcome  = WP2FA::get_wp2fa_white_label_setting( 'welcome', false );
									$enable_welcome    = WP2FA::get_wp2fa_white_label_setting( 'enable_welcome', false );

									$intro_text = '';
									if ( count( $available_methods[ User_Helper::get_user_role( $user ) ] ) > 1 ) {
										$intro_text = WP2FA::replace_wizard_strings( WP2FA::get_wp2fa_white_label_setting( 'method_selection', true ), $user );
									} elseif ( 1 === count( $available_methods[ User_Helper::get_user_role( $user ) ] ) ) {
										$intro_text = WP2FA::get_wp2fa_white_label_setting( 'method_selection_single', true );
									} else {
										$intro_text = '<h3>' . __( 'No available 2FA methods set', 'wp-2fa' ) . '</h3><p>' . __( 'Ask your administrator to enable 2FA methods', 'wp-2fa' ) . '</p>';
									}

									if ( ! empty( $optional_welcome ) && $enable_welcome ) {
										Wizard_Steps::optional_user_welcome_step();
									}
									?>

									<div class="wizard-step <?php echo ( empty( $optional_welcome ) ) ? 'active' : ''; ?>" id="choose-2fa-method">
										<div class="mb-20"><?php echo \wp_kses_post( $intro_text ); ?></div>
										<fieldset class="radio-cells">
										<?php
											/**
											 * Adds an option for external providers to add their own 2fa methods options. And sorts them (our logic).
											 *
											 * @since 2.0.0
											 */
											\do_action( WP_2FA_PREFIX . 'methods_options' );
										?>
										</fieldset>
										<br>
										<?php
										if ( 0 !== count( $available_methods[ User_Helper::get_user_role( $user ) ] ) ) {
											?>
										<a href="#" class="button wp-2fa-button-primary button-primary 2fa-choose-method" data-name="next_step_setting_modal_wizard" data-next-step><?php \esc_html_e( 'Next Step', 'wp-2fa' ); ?></a>
											<?php
										}
										?>
										<button class="button wp-2fa-button-secondary button-secondary" data-close-2fa-modal aria-label="Close this dialog window"><?php \esc_html_e( 'Cancel', 'wp-2fa' ); ?></button>
									</div>
								<?php } ?>

								<?php if ( User_Utils::in_array_all( array( 'has_enabled_methods', 'viewing_own_profile' ), $user_type ) ) { ?>
									<div class="wizard-step active">
										<fieldset class="radio-cells max-3">
											<?php
												/**
												 * Add an option for external providers to add their own reconfigure methods options.
												 *
												 * @since 2.0.0
												 */
												\do_action( WP_2FA_PREFIX . 'methods_reconfigure_options' );
											?>
										</fieldset>
									</div>
								<?php } ?>

									<?php Wizard_Steps::show_modal_methods(); ?>
								<?php

								$backup_methods = Settings::get_enabled_backup_methods_for_user_role( $user );

								if ( count( $backup_methods ) > 1 ) {
									Wizard_Steps::choose_backup_method();
								}

								/**
								 * Add an option for external providers to add their own wizard steps.
								 *
								 * @since 2.0.0
								 */
								\do_action( WP_2FA_PREFIX . 'additional_settings_steps' );

								// Create a nonce for use in ajax call to generate codes.
								if ( Backup_Codes::are_backup_codes_enabled_for_role( User_Helper::get_user_role( $user ) ) ) {
									?>
								<div class="wizard-step" id="2fa-wizard-config-backup-codes">
									<?php Wizard_Steps::backup_codes_configure(); ?>
									<?php Wizard_Steps::generated_backup_codes(); ?>
								</div>
								<?php } else { ?>
								<div class="wizard-step" id="2fa-wizard-config-backup-codes">
									<?php Wizard_Steps::congratulations_step(); ?>
								</div>
							<?php } ?>
							</main>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>

			<?php
			/**
			 * Add an option for external providers to add their own 2fa methods options.
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'methods_wizards' );
			?>

			<?php if ( Backup_Codes::are_backup_codes_enabled_for_role( User_Helper::get_user_role( $user ) ) ) { ?>
			<div>
				<div class="wp2fa-modal micromodal-slide <?php echo \esc_attr( $styling_class ); ?>" id="configure-2fa-backup-codes" aria-hidden="true">
					<div class="modal__overlay" tabindex="-1">
						<div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
						<button class="modal__close modal_cancel" aria-label="Close modal" data-close-2fa-modal></button>
						<main class="modal__content wp2fa-form-styles" id="modal-1-content">
							<?php Wizard_Steps::generated_backup_codes( true ); ?>
						</main>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>
				<div>
					<?php

					if ( self::can_user_remove_2fa( $user->ID ) ) :
						echo Generate_Modal::generate_modal( // phpcs:ignore
							'confirm-remove-2fa',
							__( 'Remove 2FA?', 'wp-2fa' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							__( 'Are you sure you want to remove two-factor authentication and lower the security of your user account?', 'wp-2fa' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							array(
								'<a href="#" class="button wp-2fa-button-primary" data-trigger-remove-2fa data-user-id="' . \esc_attr( $user->ID ) . '" ' . WP_Helper::create_data_nonce( 'wp-2fa-remove-user-2fa-nonce' ) . '>' . \esc_html__( 'Yes', 'wp-2fa' ) . '</a>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								'<button class="modal__btn  wp-2fa-button-secondary button" data-close-2fa-modal aria-label="Close this dialog window">' . \esc_html__( 'No', 'wp-2fa' ) . '</button>',
							)
						);
					endif;
					?>
				</div>
				<?php

				$output = ob_get_contents();
				ob_end_clean();

				echo $output; // phpcs:ignore
		}

		/**
		 * Produces the 2FA configuration form for network users, or any user with no roles.
		 *
		 * @param string  $is_shortcode - Current logic expects that to be set always.
		 * @param boolean $show_preamble - Show / hide preamble.
		 * @param array   $options - Array with additional options.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function inline_2fa_profile_form( $is_shortcode = 'true', $show_preamble = true, array $options = array() ) {

			if ( isset( $_GET['user_id'] ) ) { // phpcs:ignore
				$user_id = (int) $_GET['user_id']; // phpcs:ignore
				$user    = \get_user_by( 'id', $user_id );
			} else {
				$user = \wp_get_current_user();
			}

			// Get current user, we going to need this regardless.
			$current_user = \wp_get_current_user();

			if ( \is_multisite() ) {
				if ( '' === trim( (string) \WP2FA\Admin\Helpers\User_Helper::get_user_role( $user ) ) ) {
					return;
				}
			}

			// Bail if we still dont have an object.
			if ( ! is_a( $user, '\WP_User' ) || ! is_a( $current_user, '\WP_User' ) ) {
				return;
			}

			$additional_args = array(
				'is_shortcode'  => $is_shortcode,
				'show_preamble' => $show_preamble,
				'options'       => $options,
			);

			self::user_2fa_options( $user, $additional_args );
		}

		/**
		 * Add custom unlock account link to user edit admin list.
		 *
		 * @param  string $actions     Default actions.
		 * @param  object $user_object User data.
		 * @return string              Appended actions.
		 *
		 * @since 2.7.0
		 */
		public static function user_2fa_row_actions( $actions, $user_object ) {
			$nonce                = wp_create_nonce( 'wp-2fa-unlock-account-nonce' );
			$grace_period_expired = User_Helper::get_grace_period( $user_object );
			$url                  = add_query_arg(
				array(
					'action'       => 'unlock_account',
					'user_id'      => $user_object->ID,
					'wp_2fa_nonce' => $nonce,
				),
				admin_url( 'users.php' )
			);

			if ( $grace_period_expired ) {
				$actions['edit_badges'] = '<a href="' . \esc_url( $url ) . '">' . \esc_html__( 'Unlock user', 'wp-2fa' ) . '</a>';
			}

			return $actions;
		}

		/**
		 * Save user profile information.
		 *
		 * @param array $input - The array with values to process.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function save_user_2fa_options( $input ) {

			// Ensure we have the inputs we want before we process.
			// To avoid causing issues with the rest of the user profile.
			if ( ! is_array( $input ) ) {
				return;
			}

			// Assign the input to post, in case we are dealing with saving the data from another page.
			if ( isset( $input ) ) {
				$_POST = $input;
			}

			// Grab current user.
			$user = wp_get_current_user();

			// phpcs:disable
			// Grab authcode and ensure its a number.
			if ( isset( $_POST['wp-2fa-totp-authcode'] ) ) {
				$_POST['wp-2fa-totp-authcode'] = (int) $_POST['wp-2fa-totp-authcode'];
			}
			if ( ( ! isset( $_POST['custom-email-address'] ) || isset( $_POST['custom-email-address'] ) && empty( $_POST['custom-email-address'] ) ) &&
			( ! isset( $_POST['custom-oob-email-address'] ) || isset( $_POST['custom-oob-email-address'] ) && empty( $_POST['custom-oob-email-address'] ) ) ) {
				if ( isset( $_POST['email'] ) ) {
					User_Helper::set_nominated_email_for_user( $_POST['email'], $user );
				} elseif ( isset( $_POST['wp_2fa_email_address'] ) && isset( $_POST['wp-2fa-totp-authcode'] ) && ! empty( $_POST['wp-2fa-totp-authcode'] ) ) {
					User_Helper::set_nominated_email_for_user( $_POST['wp_2fa_email_address'], $user );
				} elseif ( isset( $_POST['wp_2fa_email_oob_address'] ) && isset( $_POST['wp-2fa-oob-authcode'] ) && ! empty( $_POST['wp-2fa-oob-authcode'] ) ) {
					if ( 'use_custom_email' !== $_POST['wp_2fa_email_oob_address'] ) {
						User_Helper::set_nominated_email_for_user( $_POST['wp_2fa_email_oob_address'], $user );
					}
				}
			} elseif ( isset( $_POST['custom-email-address'] ) && ! empty( $_POST['custom-email-address'] ) ) {
				User_Helper::set_nominated_email_for_user( $_POST['custom-email-address'], $user );
			} elseif ( isset( $_POST['custom-oob-email-address'] ) && ! empty( $_POST['custom-oob-email-address'] ) ) {
				User_Helper::set_nominated_email_for_user( $_POST['custom-oob-email-address'], $user );
			}

			// Check its one of our options.
			if ( ( isset( $_POST['wp_2fa_enabled_methods'] ) && TOTP::METHOD_NAME === $_POST['wp_2fa_enabled_methods'] ) ||
			( isset( $_POST['wp_2fa_enabled_methods'] ) && Email::METHOD_NAME === $_POST['wp_2fa_enabled_methods'] ) ||
			( isset( $_POST['wp_2fa_enabled_methods'] ) && ( class_exists( '\WP2FA\Extensions\OutOfBand\Out_Of_Band', false ) && Out_Of_Band::METHOD_NAME === $_POST['wp_2fa_enabled_methods'] ) ) ) {
				User_Helper::set_enabled_method_for_user(sanitize_text_field( wp_unslash( $_POST['wp_2fa_enabled_methods'] ) ), $user);
				self::delete_expire_and_enforced_keys( $user->ID );
				User_Helper::set_user_status( $user );
			}

			if ( isset( $_POST['wp-2fa-email-authcode'] ) && ! empty( $_POST['wp-2fa-email-authcode'] ) ) {
				User_Helper::set_enabled_method_for_user( Email::METHOD_NAME, $user );
				self::delete_expire_and_enforced_keys( $user->ID );
				User_Helper::set_user_status( $user );
			}

			if ( isset( $_POST['wp-2fa-totp-authcode'] ) && ! empty( $_POST['wp-2fa-totp-authcode'] ) ) {
				$totp_key = $_POST['wp-2fa-totp-key'];
				if ( Authentication::is_valid_key( $totp_key ) ) {
					if ( Open_SSL::is_ssl_available() ) {
						$totp_key = Open_SSL::SECRET_KEY_PREFIX . Open_SSL::encrypt( $totp_key );
					}

					TOTP::set_user_method( $user, $totp_key );
				}
			}
			// phpcs:enable
		}

		/**
		 * Utility function to remove user expiry and enforced data.
		 *
		 * @param  int $user_id User id to process.
		 *
		 * @since 2.7.0
		 */
		public static function delete_expire_and_enforced_keys( $user_id ) {
			User_Helper::remove_user_expiry_date( $user_id );
			User_Helper::remove_user_enforced_instantly( $user_id );
			User_Helper::remove_grace_period( $user_id );
		}

		/**
		 * Validate a user's code when setting up 2fa via the inline form.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function validate_authcode_via_ajax() {
			check_ajax_referer( 'wp-2fa-validate-authcode' );

			if ( isset( $_POST['form'] ) ) {
				$input = wp_unslash( $_POST['form'] ); // phpcs:ignore
			} else {
				wp_send_json_error(
					array(
						'error' => \esc_html__( 'No form', 'wp-2fa' ),
					)
				);
			}

			$user = wp_get_current_user();

			$our_errors = '';

			// Grab key from the $_POST.
			if ( isset( $input['wp-2fa-totp-key'] ) ) {
				$current_key = sanitize_text_field( wp_unslash( $input['wp-2fa-totp-key'] ) );
			}

			// Grab authcode and ensure its a number.
			if ( isset( $input['wp-2fa-totp-authcode'] ) ) {
				$input['wp-2fa-totp-authcode'] = (int) $input['wp-2fa-totp-authcode'];
			}

			// Check if we are dealing with totp or email, if totp validate and store a new secret key.
			if ( ! empty( $input['wp-2fa-totp-authcode'] ) && ! empty( $current_key ) ) {
				if ( Authentication::is_valid_key( $current_key ) || ! is_numeric( $input['wp-2fa-totp-authcode'] ) ) {
					if ( ! Authentication::is_valid_authcode( $current_key, sanitize_text_field( wp_unslash( $input['wp-2fa-totp-authcode'] ) ) ) ) {
						$our_errors = \esc_html__( 'Invalid Two Factor Authentication code.', 'wp-2fa' );
					}
				} else {
					$our_errors = \esc_html__( 'Invalid Two Factor Authentication secret key.', 'wp-2fa' );
				}

				// If its not totp, is it email.
			} elseif ( ! empty( $input['wp-2fa-email-authcode'] ) ) {
				if ( ! Authentication::validate_token( $user, sanitize_text_field( wp_unslash( $input['wp-2fa-email-authcode'] ) ) ) ) {
					$our_errors = \esc_html__( 'Invalid Email Authentication code.', 'wp-2fa' );
				}
			} else {
				$our_errors = \esc_html__( 'Please enter the code to finalize the 2FA setup.', 'wp-2fa' );
			}

			if ( ! empty( $our_errors ) ) {
				// Send the response.
				wp_send_json_error(
					array(
						'error' => $our_errors,
					)
				);
			} else {
				self::save_user_2fa_options( $input );
				// Send the response.
				wp_send_json_success();
			}

			wp_send_json_error(
				array(
					'error' => \esc_html__( 'Error processing form', 'wp-2fa' ),
				)
			);
		}

		/**
		 * Checks the user for remove 2FA capabilities.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return bool True if the user can remove 2FA from their account.
		 *
		 * @since 2.7.0
		 */
		public static function can_user_remove_2fa( $user_id ) {
			// check the "Hide the Remove 2FA button" setting.
			if ( Settings::get_role_or_default_setting( 'hide_remove_button', $user_id ) ) {
				return false;
			}

			// check grace period policy.
			$grace_policy = Settings::get_role_or_default_setting( 'grace-policy', $user_id );
			if ( 'no-grace-period' === $grace_policy ) {
				// we only need to run further checks to find out if the 2FA is enforced for the user in question if there
				// is no grace period.
				$enforcement_policy = WP2FA::get_wp2fa_setting( 'enforcement-policy' );

				if ( 'all-users' === $enforcement_policy ) {
					// enforced for all users, target user is definitely included.
					return false;
				}

				if ( 'certain-roles-only' === $enforcement_policy && ! User_Helper::is_enforced( $user_id ) ) {
					// Users specific role is not enforced, allow removal.
					return true;
				}

				if ( 'do-not-enforce' !== $enforcement_policy ) {
					// one of possible enforcement options is set, check the target user.
					return User_Helper::is_enforced( $user_id );
				}
			}

			return true;
		}

		/**
		 * Add script to admin footer to allow for nags to be dismissed from all admin pages.
		 *
		 * @return void
		 *
		 * @since 2.7.0
		 */
		public static function dismiss_nag_notice() {
			?>
			<script type="text/javascript">
				jQuery( document ).on( 'click', '.dismiss-user-configure-nag', function() {
					const thisNotice = jQuery( this ).closest( '.notice' );
					jQuery.ajax( {
						url: '<?php echo admin_url( 'admin-ajax.php' ); // phpcs:ignore ?>',
						data: {
							action: 'dismiss_nag'
						},
						complete: function() {
							jQuery( thisNotice ).slideUp();
						},
					} );
				} );
			</script>
			<?php
		}
	}
}
