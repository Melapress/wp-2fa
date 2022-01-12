<?php // phpcs:ignore

namespace WP2FA\Admin;

use \WP2FA\WP2FA as WP2FA;
use WP2FA\Utils\GenerateModal;
use WP2FA\Admin\Views\WizardSteps;
use WP2FA\Authenticator\BackupCodes;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use \WP2FA\Utils\UserUtils as UserUtils;
use WP2FA\Utils\SettingsUtils as SettingsUtils;
use WP2FA\Authenticator\Open_SSL;

/**
 * UserProfile - Class for handling user things such as profile settings and admin list views.
 */
class UserProfile {

	const NOTICES_META_KEY = 'wp_2fa_totp_notices';

	/**
	 * Class constructor
	 */
	public function __construct() {
	}

	/**
	 * Add our buttons to the user profile editing screen.
	 *
	 * @param object $user User data.
	 */
	public function user_2fa_options( $user, $additional_args = array() ) {

		if ( isset( $_GET['user_id'] ) ) {
			$user_id = (int) $_GET['user_id'];
			$user    = get_user_by( 'id', $user_id );
		} else {
			// Get current user, we're going to need this regardless.
			$user = wp_get_current_user();
		}

		if ( ! is_a( $user, '\WP_User' ) ) {
			return;
		}

		// Ensure we have something in the settings.
		if ( empty( SettingsUtils::get_option( WP_2FA_POLICY_SETTINGS_NAME ) ) ) {
			return;
		}

		$show_preamble = true;
		if ( isset( $additional_args['show_preamble'] ) ) {
			$show_preamble = \filter_var( $additional_args['show_preamble'], FILTER_VALIDATE_BOOLEAN );
		}

		$user_type = UserUtils::determine_user_2fa_status( $user );

		$form_output     = '';
		$form_content    = '';
		$description     = __( 'Add two-factor authentication to strengthen the security of your WordPress user account.', 'wp-2fa' );
		$show_form_table = true;
		$page_url        = ( WP2FA::is_this_multisite() ) ? 'index.php' : 'options-general.php';

		// Orphan user (a user with no role or capabilities).
		if ( in_array( 'orphan_user', $user_type, true ) ) {
			// We want to use the same form/buttons used in the shortcode.
			$additional_args['is_shortcode'] = true;

			// Create useful message for admin.
			if ( UserUtils::in_array_all( array( 'user_needs_to_setup_2fa', 'can_manage_options' ), $user_type ) ) {
				$description = __( 'This user is required to setup 2FA but has not yet done so.', 'wp-2fa' );
			}

			if ( UserUtils::in_array_all( array( 'user_is_excluded', 'can_manage_options' ), $user_type ) ) {
				$description = __( 'This user is excluded from configuring 2FA.', 'wp-2fa' );
			}
		}

		// Excluded user.
		if ( in_array( 'user_is_excluded', $user_type, true ) ) {
			return;
		}

		// A user viewing their own profile AND has a 2FA method configured.
		if ( UserUtils::in_array_all( array( 'viewing_own_profile' ), $user_type ) ) {
			if (
				UserUtils::in_array_all( array( 'has_enabled_methods' ), $user_type ) ||
				UserUtils::in_array_all( array( 'no_required_has_enabled' ), $user_type )
			) {
				// Create wizard link based on which 2fa methods are allowed by admin.
				if ( ! empty( Settings::get_role_or_default_setting( 'enable_totp', $user ) ) && ! empty( Settings::get_role_or_default_setting( 'enable_email', $user ) ) ) {
					$setup_2fa_url = add_query_arg(
						array(
							'page'         => 'wp-2fa-setup',
							'current-step' => 'user_choose_2fa_method',
							'wizard_type'  => 'user_2fa_config',
						),
						admin_url( $page_url )
					);
				} else {
					$setup_2fa_url = add_query_arg(
						array(
							'page'         => 'wp-2fa-setup',
							'current-step' => 'reconfigure_method',
							'wizard_type'  => 'user_reconfigure_config',
						),
						admin_url( $page_url )
					);
				}

				// Create backup codes URL;
				$backup_codes_url = add_query_arg(
					array(
						'page'         => 'wp-2fa-setup',
						'current-step' => 'backup_codes',
						'wizard_type'  => 'backup_codes_config',
					),
					admin_url( $page_url )
				);

				$form_content .= '<a href="' . esc_url( $setup_2fa_url ) . '" class="button button-primary">' . __( 'Change 2FA Settings', 'wp-2fa' ) . '</a>';

				if ( self::can_user_remove_2fa( $user->ID ) ) {
					$form_content .= '<a href="#" class="button button-primary remove-2fa" onclick="MicroModal.show(\'confirm-remove-2fa\');">' . __( 'Remove 2FA', 'wp-2fa' ) . '</a>';
				}

				$form_content .= '<br /><br />';

				if ( SettingsPage::are_backup_codes_enabled( reset( $user->roles ) ) ) {
					$form_content .= '<a href="' . esc_url( $backup_codes_url ) . '" class="button button-primary">' . __( 'Generate list of Backup Codes', 'wp-2fa' ) . '</a>';

					$codes_remaining = BackupCodes::codes_remaining_for_user( $user );
					if ( $codes_remaining > 0 ) {
						$form_content .= '<span class="description mt-5px">' . esc_attr( (int) $codes_remaining ) . ' ' . __( 'unused backup codes remaining.', 'wp-2fa' ) . '</span>';
					} elseif ( 0 === $codes_remaining ) {
						$form_content .= '<a class="learn_more_link" href="https://www.wpwhitesecurity.com/2fa-backup-codes/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=settings+pages" target="_blank">' . __( 'Learn more about backup codes', 'wp-2fa' ) . '</a>';
					}
				}

				if ( isset( $additional_args['is_shortcode'] ) && $additional_args['is_shortcode'] ) {
					$form_content = '<a href="#" class="button button-primary remove-2fa" data-open-configure-2fa-wizard>' . __( 'Change 2FA settings', 'wp-2fa' ) . '</a>';

					if ( self::can_user_remove_2fa( $user->ID ) ) {
						$form_content .= '<a href="#" class="button button-primary remove-2fa" onclick="MicroModal.show(\'confirm-remove-2fa\');">' . __( 'Remove 2FA', 'wp-2fa' ) . '</a>';
					}
					if ( SettingsPage::are_backup_codes_enabled( reset( $user->roles ) ) ) {
					$form_content .= '</td><tr><th class="backup-methods-label">';
						$codes_remaining = BackupCodes::codes_remaining_for_user( $user );
						if ( $codes_remaining > 0 ) {
							$backup_codes_desc = '<span class="description mt-5px">' . esc_attr( (int) $codes_remaining ) . ' ' . __( 'unused backup codes remaining.', 'wp-2fa' ) . '</span>';
						} elseif ( 0 === $codes_remaining ) {
							$backup_codes_desc = '<a class="learn_more_link" href="https://www.wpwhitesecurity.com/2fa-backup-codes/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=settings+pages" target="_blank">' . __( 'Learn more about backup codes', 'wp-2fa' ) . '</a>';
						}

						$form_content .= WizardSteps::getGenerateCodesLink() . $backup_codes_desc;

						/**
						 * Add an option for external providers to add their own user form buttons.
						 *
						 * @since 2.0.0
						 */
						$form_content = apply_filters( 'wp_2fa_additional_form_buttons', $form_content );

						$form_content .= '</th></tr>';
					}
				}
			}

			$show_if_user_is_not_in = array(
				'user_is_excluded',
				'has_enabled_methods',
				'no_required_has_enabled',
			);

			// User viewing own profile and needs to enable 2FA.
			if (
				UserUtils::in_array_all( array( 'user_needs_to_setup_2fa' ), $user_type ) ||
				UserUtils::roleIsNot( $show_if_user_is_not_in, $user_type )
			) {
				$first_time_setup_url = Settings::get_setup_page_link();

				if ( isset( $additional_args['is_shortcode'] ) && $additional_args['is_shortcode'] ) {
					$form_content .= '<a href="#" class="button button-primary" data-open-configure-2fa-wizard>' . __( 'Configure 2FA', 'wp-2fa' ) . '</a>';
					$form_content .= '<p class="description">' . esc_html__( 'Once you configure 2FA you will also be able to generate backup codes, which can be used to log in when it is not possible to get the one-time code from the primary 2FA method.', 'wp-2fa' ) . '</p>';
				}

				if ( empty( $additional_args ) ) {
					$form_content .= '<a href="' . esc_url( $first_time_setup_url ) . '" class="button button-primary">' . __( 'Configure Two-factor authentication (2FA)', 'wp-2fa' ) . '</a>';
				}
			}
		}

		// Admin viewing users profile AND user has a configured 2FA method.
		if ( UserUtils::in_array_all( array( 'can_manage_options', 'has_enabled_methods' ), $user_type ) && ! in_array( 'viewing_own_profile', $user_type, true ) ) {
			$description = __( 'The user has already configured 2FA. When you reset the user\'s current 2FA configuration, the user can log back in with just the username and password.', 'wp-2fa' );

			$remove_users_2fa_url = add_query_arg(
				array(
					'action'       => 'remove_user_2fa',
					'user_id'      => $user->ID,
					'wp_2fa_nonce' => wp_create_nonce( 'wp-2fa-remove-user-2fa-nonce' ),
					'admin_reset'  => 'yes',
				),
				admin_url( 'user-edit.php' )
			);

			$form_content .= '<a href="' . esc_url( $remove_users_2fa_url ) . '" class="button button-primary">' . __( 'Reset 2FA configuration', 'wp-2fa' ) . '</a>';
		}

		// Admin viewing users profile AND users grace period has expired.
		if ( UserUtils::in_array_all( array( 'can_manage_options', 'grace_has_expired' ), $user_type ) ) {
			$unlock_user_url = add_query_arg(
				array(
					'action'       => 'unlock_account',
					'user_id'      => $user->ID,
					'wp_2fa_nonce' => wp_create_nonce( 'wp-2fa-unlock-account-nonce' ),
				),
				admin_url( 'user-edit.php' )
			);
			$form_content   .= '<a href="' . esc_url( $unlock_user_url ) . '" class="button button-primary">' . __( 'Unlock user and reset the grace period', 'wp-2fa' ) . '</a>';
		}

		if ( $show_preamble ) {
			$form_output .= '<h2>' . __( 'Two-factor authentication settings', 'wp-2fa' ) . '</h2>';

			if ( $description ) {
					$form_output .= '<p class="description">' . $description . '</p>';
			}
		}

		$form_content = apply_filters( 'wp_2fa_append_to_profile_form_content', $form_content );

		if ( $show_form_table && ! empty( $form_content ) ) {
			$form_output .= '
		  <table class="form-table wp-2fa-user-profile-form" role="presentation">
		    <tbody>
		      <tr>
		        <th><label>' . __( '2FA Setup:', 'wp-2fa' ) . '</label></th>
		        <td>
		          ' . $form_content . '
		        </td>
		      </tr>
	      </tbody>
	    </table>';

			if ( (isset($_GET['show']) && 'wp-2fa-setup' === $_GET['show']) || ( User::get_instance($user) )->getEnforcedInstantly() ) {
				$form_output .= '
					<script>
					window.addEventListener("load", function() {
						wp2fa_fireWizard();
					});
					</script>
				';
			}
		}

		echo $form_output;

		$this->generate_inline_modals( $user_type );
	}

	public function generate_inline_modals( $user_type = array() ) {

		ob_start();

		$user = wp_get_current_user();

		if ( UserUtils::in_array_all( array( 'user_needs_to_setup_2fa', 'viewing_own_profile' ), $user_type ) || UserUtils::in_array_all( array( 'has_enabled_methods', 'viewing_own_profile' ), $user_type ) || UserUtils::in_array_all( array( 'no_required_not_enabled', 'viewing_own_profile' ), $user_type ) || UserUtils::in_array_all( array( 'no_determined_yet', 'viewing_own_profile' ), $user_type ) ) { ?>
		<div>
			<div class="wp2fa-modal micromodal-slide" id="configure-2fa" aria-hidden="true">
				<div class="modal__overlay" tabindex="-1" data-micromodal-close>
					<div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
						<?php
							echo GenerateModal::generate_modal(
								'notify-users',
								__( 'Are you sure?', 'wp-2fa' ),
								__( 'Any unsaved changes will be lost!', 'wp-2fa' ),
								array(
									'<button class="modal__btn button-confirm" aria-label="Close this dialog window and the wizard">' . __( 'Yes', 'wp-2fa' ) . '</button>',
									'<button class="modal__btn button-decline" data-micromodal-close aria-label="Close this dialog window">' . __( 'No', 'wp-2fa' ) . '</button>',
								),
								'',
								'430px'
							);
						?>
						<button class="modal__close" aria-label="Close modal"></button>
						<main class="modal__content wp2fa-form-styles" id="modal-1-content">
						<?php
							$logo_section = '<p style="text-align: center; padding:0; margin: 0;"><img style="filter: invert(76.4%); width: 50px; margin: 0 auto;" src="' . WP_2FA_URL . 'dist/images/wp-2fa-white-icon20x28.svg' . '" /></p>';
							$logo_section = apply_filters( 'wp_fa_plugin_logo_wizard', $logo_section );

							echo $logo_section;

						if ( UserUtils::in_array_all( array( 'user_needs_to_setup_2fa', 'viewing_own_profile' ), $user_type ) || UserUtils::in_array_all( array( 'no_required_not_enabled', 'viewing_own_profile' ), $user_type ) || UserUtils::in_array_all( array( 'no_determined_yet', 'viewing_own_profile' ), $user_type ) ) {

							$available_methods = UserUtils::get_available_2fa_methods();

							$intro_text = esc_html__( 'Choose the 2FA authentication method', 'wp-2fa' );
							if ( count( $available_methods ) > 1 ) {
								$sub_text = WP2FA::getNumberOfPluginsText();
							} else {
								$sub_text = esc_html__( 'Only the below 2FA method is allowed on this website:', 'wp-2fa' );
							}
							?>

								<div class="wizard-step active">
									<h3><?php echo sanitize_text_field( $intro_text ); ?></h3>
									<p><?php echo sanitize_text_field( $sub_text ); ?></p>
									<fieldset>
									<?php WizardSteps::totpOption(); ?>
									<?php WizardSteps::emailOption(); ?>

									<?php
										/**
										 * Add an option for external providers to add their own 2fa methods options.
										 *
										 * @since 2.0.0
										 */
										do_action( 'wp_2fa_methods_options' );
									?>
									</fieldset>
									<br>
									<a href="#" class="modal__btn button button-primary 2fa-choose-method" data-name="next_step_setting_modal_wizard" data-next-step><?php esc_html_e( 'Next Step', 'wp-2fa' ); ?></a>
									<button class="modal__btn button" data-close-2fa-modal aria-label="Close this dialog window"><?php esc_html_e( 'Cancel', 'wp-2fa' ); ?></button>
								</div>
						<?php } ?>

							<?php if ( UserUtils::in_array_all( array( 'has_enabled_methods', 'viewing_own_profile' ), $user_type ) ) { ?>
								<div class="wizard-step active">
									<fieldset>
										<?php WizardSteps::totpReConfigure(); ?>
										<?php WizardSteps::emailReConfigure(); ?>
										<?php
											/**
											 * Add an option for external providers to add their own reconfigure methods options.
											 *
											 * @since 2.0.0
											 */
											do_action( 'wp_2fa_methods_reconfigure_options' );
										?>
									</fieldset>
								</div>
							<?php } ?>

							<?php WizardSteps::showModalMethods(); ?>
						<?php

						$backup_methods = Settings::get_enabled_backup_methods_for_user_role( $user );

						if ( count( $backup_methods ) > 1 ) {
							WizardSteps::choose_backup_method();
						}

						/**
						 * Add an option for external providers to add their own wizard steps.
						 *
						 * @since 2.0.0
						 */
						do_action( 'wp_2fa_additional_settings_steps' );

						// Create a nonce for use in ajax call to generate codes.
						if ( SettingsPage::are_backup_codes_enabled( reset( $user->roles ) ) ) {
							?>
							<div class="wizard-step" id="2fa-wizard-config-backup-codes">
							<?php WizardSteps::backup_codes_configure(); ?>
							<?php WizardSteps::generated_backup_codes(); ?>
							</div>
						<?php } else { ?>
							<div class="wizard-step" id="2fa-wizard-config-backup-codes">
							<?php WizardSteps::congratulations_step(); ?>
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
			do_action( 'wp_2fa_methods_wizards' );
		?>

		<?php if ( SettingsPage::are_backup_codes_enabled( reset( $user->roles ) ) ) { ?>
		<div>
			<div class="wp2fa-modal micromodal-slide" id="configure-2fa-backup-codes" aria-hidden="true">
				<div class="modal__overlay" tabindex="-1" data-micromodal-close>
					<div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
					<button class="modal__close" aria-label="Close modal" data-close-2fa-modal></button>
					<main class="modal__content wp2fa-form-styles" id="modal-1-content">
						<?php WizardSteps::generated_backup_codes( true ); ?>
					</main>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>
		<?php

		if ( self::can_user_remove_2fa( $user->ID ) ) :
			echo GenerateModal::generate_modal(
				'confirm-remove-2fa',
				__( 'Remove 2FA?', 'wp-2fa' ),
				__( 'Are you sure you want to remove two-factor authentication and lower the security of your user account?', 'wp-2fa' ),
				array(
					'<a href="#" class="modal__btn modal__btn-primary button button-primary" data-trigger-remove-2fa data-user-id="' . esc_attr( $user->ID ) . '" data-nonce="' . wp_create_nonce( 'wp-2fa-remove-user-2fa-nonce' ) . '">' . __( 'Yes', 'wp-2fa' ) . '</a>',
					'<button class="modal__btn button" data-close-2fa-modal aria-label="Close this dialog window">' . __( 'No', 'wp-2fa' ) . '</button>',
				)
			);
		endif;

		$output = ob_get_contents();
		ob_end_clean();

		echo $output;
	}

	/**
	 * Produces the 2FA configuration form for network users, or any user with no roles.
	 */
	public function inline_2fa_profile_form( $is_shortcode = '', $show_preamble = true ) {

		if ( isset( $_GET['user_id'] ) ) {
			$user_id = (int) $_GET['user_id'];
			$user    = get_user_by( 'id', $user_id );
		} else {
			$user = wp_get_current_user();
		}

		// Get current user, we going to need this regardless.
		$current_user = wp_get_current_user();

		// Bail if we still dont have an object.
		if ( ! is_a( $user, '\WP_User' ) || ! is_a( $current_user, '\WP_User' ) ) {
			return;
		}

		$additional_args = array(
			'is_shortcode'  => $is_shortcode,
			'show_preamble' => $show_preamble,
		);

		$this->user_2fa_options( $user, $additional_args );
	}

	/**
	 * Add custom unlock account link to user edit admin list.
	 *
	 * @param  string $actions     Default actions.
	 * @param  object $user_object User data.
	 * @return string              Appended actions.
	 */
	public function user_2fa_row_actions( $actions, $user_object ) {
		$nonce                = wp_create_nonce( 'wp-2fa-unlock-account-nonce' );
		$grace_period_expired = get_user_meta( $user_object->ID, WP_2FA_PREFIX . 'user_grace_period_expired', true );
		$url                  = add_query_arg(
			array(
				'action'       => 'unlock_account',
				'user_id'      => $user_object->ID,
				'wp_2fa_nonce' => $nonce,
			),
			admin_url( 'users.php' )
		);

		if ( $grace_period_expired ) {
			$actions['edit_badges'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Unlock user', 'wp-2fa' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Save user profile information.
	 */
	public function save_user_2fa_options( $input ) {

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

		// Grab authcode and ensure its a number.
		if ( isset( $_POST['wp-2fa-totp-authcode'] ) ) {
			$_POST['wp-2fa-totp-authcode'] = (int) $_POST['wp-2fa-totp-authcode'];
		}

		if ( ( ! isset( $_POST['custom-email-address'] ) || isset( $_POST['custom-email-address'] ) && empty( $_POST['custom-email-address'] ) ) &&
		( ! isset( $_POST['custom-oob-email-address'] ) || isset( $_POST['custom-oob-email-address'] ) && empty( $_POST['custom-oob-email-address'] ) ) ) {
			if ( isset( $_POST['email'] ) ) {
				update_user_meta( $user->ID, WP_2FA_PREFIX . 'nominated_email_address', $_POST['email'] );
			} elseif ( isset( $_POST['wp_2fa_email_address'] ) && isset( $_POST['wp-2fa-totp-authcode'] ) && ! empty( $_POST['wp-2fa-totp-authcode'] ) ) {
				update_user_meta( $user->ID, WP_2FA_PREFIX . 'nominated_email_address', $_POST['wp_2fa_email_address'] );
			} elseif ( isset( $_POST['wp_2fa_email_oob_address'] ) && isset( $_POST['wp-2fa-oob-authcode'] ) && ! empty( $_POST['wp-2fa-oob-authcode'] ) ) {
				if ( 'use_custom_email' !== $_POST['wp_2fa_email_oob_address'] ) {
					update_user_meta( $user->ID, WP_2FA_PREFIX . 'nominated_email_address', $_POST['wp_2fa_email_oob_address'] );
				} else {
					update_user_meta( $user->ID, WP_2FA_PREFIX . 'nominated_email_address', $user->user_email );
				}
			}
		} elseif ( isset( $_POST['custom-email-address'] ) && ! empty ( $_POST['custom-email-address'] ) ) {
			update_user_meta( $user->ID, WP_2FA_PREFIX . 'nominated_email_address', sanitize_email( wp_unslash( $_POST['custom-email-address'] ) ) );
		} elseif ( isset( $_POST['custom-oob-email-address'] ) && ! empty ( $_POST['custom-oob-email-address'] ) ) {
			update_user_meta( $user->ID, WP_2FA_PREFIX . 'nominated_email_address', sanitize_email( wp_unslash( $_POST['custom-oob-email-address'] ) ) );
		}

		// Check its one of our options.
		if ( ( isset( $_POST['wp_2fa_enabled_methods'] ) && 'totp' === $_POST['wp_2fa_enabled_methods'] ) ||
			( isset( $_POST['wp_2fa_enabled_methods'] ) && 'email' === $_POST['wp_2fa_enabled_methods'] ) ||
			( isset( $_POST['wp_2fa_enabled_methods'] ) && 'oob' === $_POST['wp_2fa_enabled_methods'] ) ) {
			update_user_meta( $user->ID, WP_2FA_PREFIX . 'enabled_methods', sanitize_text_field( wp_unslash( $_POST['wp_2fa_enabled_methods'] ) ) );
			self::delete_expire_and_enforced_keys( $user->ID );
			User::setUserStatus( $user );
		}

		if ( isset( $_POST['wp-2fa-email-authcode'] ) && ! empty( $_POST['wp-2fa-email-authcode'] ) ) {
			update_user_meta( $user->ID, WP_2FA_PREFIX . 'enabled_methods', 'email' );
			self::delete_expire_and_enforced_keys( $user->ID );
			User::setUserStatus( $user );
		}

		if ( isset( $_POST['wp-2fa-totp-authcode'] ) && ! empty( $_POST['wp-2fa-totp-authcode'] ) ) {
			update_user_meta( $user->ID, WP_2FA_PREFIX . 'enabled_methods', 'totp' );

			$totp_key = $_POST['wp-2fa-totp-key'];
			if ( Authentication::is_valid_key( $totp_key ) ) {
				if ( Open_SSL::is_ssl_available() ) {
					$totp_key = 'ssl_' . Open_SSL::encrypt( $totp_key );
				}
				update_user_meta( $user->ID, WP_2FA_PREFIX . 'totp_key', $totp_key );
				self::delete_expire_and_enforced_keys( $user->ID );
				User::setUserStatus( $user );
			}
		}
	}

	/**
	 * Utility function to quickly remove data via direct query.
	 *
	 * @param  int $user_id User id to process.
	 */
	public static function delete_expire_and_enforced_keys( $user_id ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"
				DELETE FROM $wpdb->usermeta
				WHERE user_id = %d
				AND meta_key IN ( %s, %s )
				",
				array(
					$user_id,
					WP_2FA_PREFIX . 'grace_period_expiry',
					WP_2FA_PREFIX . 'user_enforced_instantly',
				)
			)
		);
	}

	/**
	 * Validate a user's code when setting up 2fa via the inline form.
	 *
	 * @return string JSON result of validation.
	 */
	public function validate_authcode_via_ajax() {
		check_ajax_referer( 'wp-2fa-validate-authcode' );

		if ( isset( $_POST['form'] ) ) {
			$input = $_POST['form'];
		} else {
			return 'No form';
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
					$our_errors = esc_html__( 'Invalid Two Factor Authentication code.', 'wp-2fa' );
				}
			} else {
				$our_errors = esc_html__( 'Invalid Two Factor Authentication secret key.', 'wp-2fa' );
			}

			// If its not totp, is it email.
		} elseif ( ! empty( $input['wp-2fa-email-authcode'] ) ) {
			if ( ! Authentication::validate_token( $user, sanitize_text_field( wp_unslash( $input['wp-2fa-email-authcode'] ) ) ) ) {
				$our_errors = __( 'Invalid Email Authentication code.', 'wp-2fa' );
			}
		} else {
			$our_errors = __( 'Please enter the code to finalize the 2FA setup.', 'wp-2fa' );
		}

		if ( ! empty( $our_errors ) ) {
			// Send the response.
			wp_send_json_error(
				array(
					'error' => $our_errors,
				)
			);
		} else {
			$this->save_user_2fa_options( $input );
			// Send the response.
			wp_send_json_success();
		}
	}

	/**
	 * @param int $user_id User ID.
	 *
	 * @return bool True if the user can remove 2FA from their account.
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

			if ( 'do-not-enforce' !== $enforcement_policy ) {
				// one of possible enforcement options is set, check the target user.
				return User::is_enforced( $user_id );
			}
		}

		return true;
	}
}
