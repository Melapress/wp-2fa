<?php // phpcs:ignore

namespace WP2FA\Admin;

use WP2FA\EmailTemplate;
use \WP2FA\WP2FA as WP2FA;
use WP2FA\Utils\UserUtils;
use WP2FA\Admin\Controllers\Settings;
use WP2FA\Authenticator\Authentication;
use \WP2FA\Utils\Debugging as Debugging;
use WP2FA\Admin\Views\FirstTimeWizardSteps;
use WP2FA\Utils\SettingsUtils as SettingsUtils;
use \WP2FA\Utils\GenerateModal as GenerateModal;

/**
 * SettingsPage - Class for handling settings
 */
class SettingsPage {

	/**
	 * Holds the status of the backupcodes functionality
	 *
	 * @var bool
	 */
	private static $backupCodesEnabled = null;

	/**
	 * Create admin menu entru and settings page
	 */
	public function create_settings_admin_menu() {
		// Create admin menu item.
		add_menu_page(
			esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
			esc_html__( 'WP 2FA', 'wp-2fa' ),
			'manage_options',
			'wp-2fa-settings',
			[
				$this,
				'settings_page_render',
			],
			"data:image/svg+xml;base64,".base64_encode(file_get_contents(WP_2FA_PATH . 'dist/images/wp-2fa-white-icon20x28.svg')),
			81
		);
		// Register our settings page.
		register_setting(
			WP_2FA_SETTINGS_NAME,
			WP_2FA_SETTINGS_NAME,
			array( $this, 'validate_and_sanitize' )
		);

		register_setting(
			WP_2FA_EMAIL_SETTINGS_NAME,
			WP_2FA_EMAIL_SETTINGS_NAME,
			array( $this, 'validate_and_sanitize_email' )
		);
	}

	/**
	 * Create admin menu entru and settings page
	 */
	public function create_settings_admin_menu_multisite() {
		// Create admin menu item.
		add_menu_page(
			esc_html__( 'WP 2FA Settings', 'wp-2fa' ),
			esc_html__( 'WP 2FA', 'wp-2fa' ),
			'manage_options',
			'wp-2fa-settings',
			[
				$this,
				'settings_page_render',
			],
			"data:image/svg+xml;base64,".base64_encode(file_get_contents(WP_2FA_PATH . 'dist/images/wp-2fa-white-icon20x28.svg')),
			81
		);
	}

	/**
	 * Render the settings
	 */
	public function settings_page_render() {

		$user    = wp_get_current_user();
		if ( ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ) {
			$main_user = (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' );
		} else {
			$main_user = '';
		}

		// Check if new user page has been published.
		if ( ! empty( get_transient( WP_2FA_PREFIX . 'new_custom_page_created' ) ) ) {
			delete_transient( WP_2FA_PREFIX . 'new_custom_page_created' );
			$new_page_id        = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
			$new_page_permalink = get_permalink( $new_page_id );

			$new_page_modal_content = '<h3>'.esc_html__( 'The plugin created the 2FA settings page with the URL:', 'wp-2fa' ).'</h3>';
			$new_page_modal_content .= '<h4><a target="_blank" href="'.esc_url( $new_page_permalink ).'">'.esc_url( $new_page_permalink ).'</a></h4>';
			$new_page_modal_content .= '<p>'.esc_html__( 'You can edit this page using the page editor, like you do with all other pages.', 'wp-2fa' );
			$new_page_modal_content .= '</p>';
			$new_page_modal_content .= sprintf(
				esc_html__( 'Use the %s html tag in the email templates to include the URL of the 2FA configuration page when notifying the users to configure two-factor authentication.', 'wp-2fa' ),
				'<strong>{2fa_settings_page_url}</strong>'
			);
			$new_page_modal_content .= '</p>';

			echo GenerateModal::generate_modal(
				'new-page-created',
				false,
				$new_page_modal_content,
				[
					'<a href="#" class="modal__btn modal__btn-primary button-primary" data-close-2fa-modal>'. __( 'OK', 'wp-2fa' ) .'</a>',
				],
				true,
				'560px'
			);
		}

		$wp2faUser = new User( $user );
		$enabled_methods = $wp2faUser->getEnabledMethods();

		if ( empty( $enabled_methods ) ) {
			$new_page_modal_content = '<h3>'.esc_html__( 'Exclude yourself?', 'wp-2fa' ).'</h3>';
			$new_page_modal_content .= '</p>'. esc_html__( 'You are about to enforce 2FA instantly on all users, including yourself, however you have not yet configured your own 2FA method. What would you like to do?', 'wp-2fa' ) .'</p>';

			echo GenerateModal::generate_modal(
				'exclude-self-from-instant-2fa',
				false,
				$new_page_modal_content,
				[
					'<a href="#" class="modal__btn modal__btn-primary button-secondary" data-close-2fa-modal>'. __( 'Continue anyway', 'wp-2fa' ) .'</a>',
					'<a href="#" class="modal__btn modal__btn-primary button-primary" data-close-2fa-modal data-user-login-name="'. esc_attr( $user->user_login ) .'">'. __( 'Exclude myself from 2FA policies', 'wp-2fa' ) .'</a>',
				],
				false,
				'560px'
			);
		}

		$exclusions_modal_content = '<h3>'.esc_html__( 'Excluded Roles/Users still present', 'wp-2fa' ).'</h3>';
		$exclusions_modal_content .= '</p>'. esc_html__( 'Changing this setting whislt you still have roles/users excluded will result in these being removed from the settngs.', 'wp-2fa' ) .'</p>';


		echo GenerateModal::generate_modal(
			'warn-exclusions-will-be-removed',
			false,
			$exclusions_modal_content,
			[
				'<a href="#" class="modal__btn modal__btn-primary button-secondary" data-close-2fa-modal data-clear-exclusions>'. __( 'Continue anyway', 'wp-2fa' ) .'</a>',
				'<a href="#" class="modal__btn modal__btn-primary button-primary" data-close-2fa-modal data-cancel-action>'. __( 'Cancel', 'wp-2fa' ) .'</a>',
			],
			false,
			'560px'
		);
		?>

		<div class="wrap wp-2fa-settings-wrapper">
			<h2><?php esc_html_e( 'WP 2FA Settings', 'wp-2fa' ); ?></h2>
			<hr>
			<?php if ( ! empty( WP2FA::get_wp2fa_setting( 'limit_access' ) ) && $main_user !== $user->ID ) { ?>

				<?php
				echo esc_html__( 'These settings have been disabled by your site administrator, please contact them for further assistance.', 'wp-2fa' );
				?>

			<?php } else { ?>

				<div class="nav-tab-wrapper">
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-2fa-settings' ), network_admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo ! isset( $_REQUEST['tab'] ) ? 'nav-tab-active' : ''; ?>"><?php _e( '2FA Settings', 'wp-2fa' ); ?></a>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page' => 'wp-2fa-settings',
								'tab'  => 'email-settings',
							),
							network_admin_url( 'admin.php' )
						)
					);
					?>
					" class="nav-tab <?php echo isset( $_REQUEST['tab'] ) && 'email-settings' === $_REQUEST['tab'] ? 'nav-tab-active' : ''; ?>"><?php _e( 'Email Settings & Templates', 'wp-2fa' ); ?></a>
				</div>
					<?php
					if ( ! current_user_can( 'manage_options' ) ) {
						return;
					}
					if ( WP2FA::is_this_multisite() ) {
						$action = 'edit.php?action=update_wp2fa_network_options';
					} else {
						$action = 'options.php';
					}
					if ( ! isset( $_REQUEST['tab'] ) || isset( $_REQUEST['tab'] ) && '2fa-settings' === $_REQUEST['tab'] ) :
						?>
					<br/>
						<?php
						printf( '<p class="description">%1$s <a href="mailto:support@wpwhitesecurity.com">%2$s</a></p>',
							esc_html__( 'Use the settings below to configure the properties of the two-factor authentication on your website and how users use it. If you have any questions send us an email at', 'wp-2fa' ),
							esc_html__( 'support@wpwhitesecurity.com', 'wp-2fa' )
						);
						?>
					<br/>
					<?php $total_users = count_users(); ?>
					<form id="wp-2fa-admin-settings" action='<?php echo esc_attr( $action ); ?>' method='post' autocomplete="off" data-2fa-total-users="<?php echo $total_users['total_users']; ?>">
						<?php
						if ( ! current_user_can( 'manage_options' ) ) {
							return;
						}

							settings_fields( WP_2FA_SETTINGS_NAME );
							$this->select_method_setting();
							$this->select_enforcement_policy_setting();
							$this->excluded_roles_or_users_setting();
							if ( WP2FA::is_this_multisite() ) {
								$this->excluded_network_sites();
							}
							$this->grace_period_setting();
							$this->user_redirect_after_wizard();
							$this->user_profile_settings();
							$this->disable_2fa_removal_setting();
							$this->changeDefaultTextArea();
							$this->gracePeriodFrequency();
							$this->limit_settings_access();
							$this->remove_data_upon_uninstall();
							submit_button();
						?>
					</form>
				<?php endif; ?>

				<?php
				if ( WP2FA::is_this_multisite() ) {
					$action = 'edit.php?action=update_wp2fa_network_email_options';
				} else {
					$action = 'options.php';
				}
				?>

				<?php if ( isset( $_REQUEST['tab'] ) && 'email-settings' === $_REQUEST['tab'] ) : ?>
					<br/>
					<?php
						printf( '<p class="description">%1$s <a href="mailto:support@wpwhitesecurity.com">%2$s</a></p>',
							esc_html__( 'Use the settings below to configure the emails which are sent to users as part of the 2FA plugin. If you have any questions send us an email at', 'wp-2fa' ),
							esc_html__( 'support@wpwhitesecurity.com', 'wp-2fa' )
						);
					?>
					<br/>
					<form action='<?php echo esc_attr( $action ); ?>' method='post' autocomplete="off">
						<?php
						if ( ! current_user_can( 'manage_options' ) ) {
							return;
						}

						settings_fields( WP_2FA_EMAIL_SETTINGS_NAME );
						$this->email_from_settings();
						$this->email_settings();
						submit_button( 'Save email settings and templates' );
						?>
					</form>
				<?php endif; ?>

			<?php } ?>
		</div>
		<?php
	}

	private function changeDefaultTextArea() {
		?>
		<h3><?php esc_html_e( 'Change the default text used in the 2FA code page?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'This is the text shown to the users on the page when they are asked to enter the 2FA code. To change the default text, simply type it in the below placeholder.', 'wp-2fa' ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="2fa-method"><?php esc_html_e( '2FA code page text', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
						<label for="default-text-code-page">
							<textarea cols="70" rows="10" name="wp_2fa_settings[default-text-code-page]" id="default-text-code-page"><?php echo WP2FA::get_wp2fa_setting( 'default-text-code-page', true ); ?></textarea>
							<div><span><strong><i><?php esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
						</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<?php

	}

	/**
	 * General settings
	 */
	private function select_method_setting() {
		FirstTimeWizardSteps::selectMethod( false );
	}

	/**
	 * Policy settings
	 */
	private function select_enforcement_policy_setting() {
		FirstTimeWizardSteps::enforcementPolicy( false );
	}

	/**
	 * User profile settings
	 */
	private function user_profile_settings() {
		?>
		<h3><?php esc_html_e( 'Can users access the WordPress dashboard or you have custom profile pages? ', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'If your users do not have access to the WordPress dashboard (because you use custom user profile pages) enable this option. Once enabled, the plugin creates a page which ONLY authenticated users can access to configure their user 2FA settings. A link to this page is sent in the 2FA welcome email.', 'wp-2fa' ); ?></a>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="enforcement-policy"><?php esc_html_e( 'Frontend 2FA settings page', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<label class="radio-inline">
								<input id="use_custom_page" type="radio" name="wp_2fa_settings[create-custom-user-page]" value="yes"
								<?php checked( WP2FA::get_wp2fa_setting( 'create-custom-user-page' ), 'yes' ); ?>
								>
								<?php esc_html_e( 'Yes', 'wp-2fa' ); ?>
							</label>
							<label class="radio-inline">
								<input id="dont_use_custom_page" type="radio" name="wp_2fa_settings[create-custom-user-page]" value="no"
								<?php checked( WP2FA::get_wp2fa_setting( 'create-custom-user-page' ), 'no' ); ?>
								<?php checked( WP2FA::get_wp2fa_setting( 'create-custom-user-page' ), '' ); ?>
								>
								<?php esc_html_e( 'No', 'wp-2fa' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr class="custom-user-page-setting disabled">
					<th><label for="enforcement-policy"><?php esc_html_e( 'Frontend 2FA settings page URL', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
								$custom_slug = get_post_field( 'post_name', get_post( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) );
							} else {
								$custom_slug = WP2FA::get_wp2fa_setting( 'custom-user-page-url' );
							}

							$has_error = false;
							$settings_errors = get_settings_errors( WP_2FA_SETTINGS_NAME );
							if (!empty($settings_errors)) {
								foreach ( $settings_errors as $error ) {
									if ($error['code'] == 'no_page_slug_provided') {
										$has_error = true;
										break;
									}
								}
							}

							?>
							<?php echo trailingslashit( get_site_url() ); ?>
							<input type="text" id="custom-user-page-url" name="wp_2fa_settings[custom-user-page-url]" value="<?php echo sanitize_text_field( $custom_slug ); ?>"<?php if ($has_error): ?> class="error"<?php endif; ?>>
						</fieldset>
						<?php
						if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
							$edit_post_link = get_edit_post_link( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) );
							$view_post_link = get_permalink( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) );
							?>
							<br>
							<a href="<?php echo esc_url( $edit_post_link ); ?>" target="_blank" class="button button-secondary" style="margin-right: 5px;"><?php esc_html_e( 'Edit Page', 'wp-2fa' ); ?></a> <a href="<?php echo esc_url( $view_post_link ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'View Page', 'wp-2fa' ); ?></a>
							<?php
						}
						?>
					</td>
				</tr>
				<tr class="custom-user-page-setting">
					<th colspan="2"><p class="description"><?php esc_html_e( 'Specify the page where you want to redirect your users to after they complete the 2FA setup. This will override the global redirect setting.', 'wp-2fa' ); ?></p></th>
				</tr>
				<tr class="custom-user-page-setting">
					<th><label for="enforcement-policy"><?php esc_html_e( 'Redirect users after 2FA setup', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							$custom_slug = WP2FA::get_wp2fa_setting( 'redirect-user-custom-page' );
							?>
							<?php echo trailingslashit( get_site_url() ); ?>
							<input type="text" id="redirect-user-custom-page" name="wp_2fa_settings[redirect-user-custom-page]" value="<?php echo sanitize_text_field( $custom_slug ); ?>">
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * User profile settings
	 */
	private function user_redirect_after_wizard() {
		?>
		<h3><?php esc_html_e( 'Do you want to redirect the user to a specific page after completing the 2FA setup wizard?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Specify a URL of a page where you want to redirect the users once they complete the 2FA setup wizard. Leave empty for default behaviour, in which users are redirected back to the page from where they launched the wizard.', 'wp-2fa' ); ?></a>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="redirect-user-custom-page-global"><?php esc_html_e( 'Redirect users after 2FA setup to', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<?php echo trailingslashit( get_site_url() ); ?>
							<input type="text" id="redirect-user-custom-page-global" name="wp_2fa_settings[redirect-user-custom-page-global]" value="<?php echo sanitize_text_field( WP2FA::get_wp2fa_setting( 'redirect-user-custom-page-global' ) ); ?>">
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Role and users exclusion settings
	 */
	private function excluded_roles_or_users_setting() {
		$enforcement = WP2FA::get_wp2fa_setting( 'enforcement-policy' );
		$disabled_class = ( 'all-users' == $enforcement ) ? 'enabled' : 'disabled';
		?>
		<div id="exclusion_settings_wrapper" class="<?php echo esc_attr(  $disabled_class ); ?>">
		<?php FirstTimeWizardSteps::excludeUsers(); ?>
		</div>
		<?php
	}

	/**
	 * Role and users exclusion settings
	 */
	private function excluded_network_sites() {
		FirstTimeWizardSteps::excludedNetworkSites();
	}

	/**
	 * Grace period settings
	 */
	private function grace_period_setting() {
		?>
		<br>
		<h3><?php esc_html_e( 'Should users be asked to setup 2FA instantly or should they have a grace period?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'When you enforce 2FA on users they have a grace period to configure 2FA. If they fail to configure it within the configured stipulated time, their account will be locked and have to be unlocked manually. Note that user accounts cannot be unlocked automatically, even if you change the settings. As a security precaution they always have to be unlocked them manually. Maximum grace period is 10 days.', 'wp-2fa' ); ?> <a href="https://www.wpwhitesecurity.com/support/kb/configure-grace-period-2fa/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=settings+pages" target="_blank"><?php esc_html_e( 'Learn more.', 'wp-2fa' ); ?></a>
		</p>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="grace-policy"><?php esc_html_e( 'Grace period', 'wp-2fa' ); ?></label></th>
					<td>
					<?php FirstTimeWizardSteps::gracePeriod( true ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	<?php
	}

	/**
	 * Grace period frequency
	 */
	private function gracePeriodFrequency() {
		?>
		<h3><?php esc_html_e( 'How often should the plugin check if a user\'s grace period is over?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'By default the plugin checks if a users grace periods to setup 2FA has passed when the user tries to login. If you would like the plugin to advise the user within an hour, enable the below option to add a cron job that runs every hour.', 'wp-2fa' ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="grace-period"><?php esc_html_e( 'Enable cron', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<input type="checkbox" id="grace-cron" name="wp_2fa_settings[enable_grace_cron]" value="enable_grace_cron"
							<?php checked( 1, WP2FA::get_wp2fa_setting( 'enable_grace_cron' ), true ); ?>
							>
							<?php esc_html_e( 'Use cron job to check grace periods', 'wp-2fa' ); ?>
						</fieldset>
					</td>
				</tr>
				<tr class="disabled destory-session-setting">
					<th><label for="destory-session"><?php esc_html_e( 'Destroy session', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<input type="checkbox" id="destory-session" name="wp_2fa_settings[enable_destroy_session]" value="enable_destroy_session"
							<?php checked( 1, WP2FA::get_wp2fa_setting( 'enable_destroy_session' ), true ); ?>
							>
							<?php esc_html_e( 'Destroy user session when grace period expires?', 'wp-2fa' ); ?>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Disable removal of 2FA settings
	 */
	private function disable_2fa_removal_setting() {
		$user = wp_get_current_user();
		?>
		<br>
		<h3><?php esc_html_e( 'Should users be able to disable 2FA on their user profile?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Users can configure and also disable 2FA on their profile by clicking the "Remove 2FA" button. Enable this setting to disable the Remove 2FA button so users cannot disable 2FA from their user profile.', 'wp-2fa' ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="hide-remove-2fa"><?php esc_html_e( 'Hide the Remove 2FA button', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<input type="checkbox" id="hide-remove-2fa" name="wp_2fa_settings[hide_remove_button]" value="hide_remove_button"
							<?php checked( 1, WP2FA::get_wp2fa_setting( 'hide_remove_button' ), true ); ?>
							>
							<?php esc_html_e( 'Hide the Remove 2FA button on user profile pages', 'wp-2fa' ); ?>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Limit settings setting
	 */
	private function limit_settings_access() {
		?>
		<br>
		<h3><?php esc_html_e( 'Limit 2FA settings access?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Use this setting to hide this plugin configuration area from all other admins.', 'wp-2fa' ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="grace-period"><?php esc_html_e( 'Limit access to 2FA settings', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<input type="checkbox" id="limit_access" name="wp_2fa_settings[limit_access]" value="limit_access"
							<?php checked( 1, WP2FA::get_wp2fa_setting( 'limit_access' ), true ); ?>
							>
							<?php esc_html_e( 'Hide settings from other administrators', 'wp-2fa' ); ?>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Limit settings setting
	 */
	private function remove_data_upon_uninstall() {
		?>
		<div class="danger-zone-wrapper">
			<h3><?php esc_html_e( 'Do you want to delete the plugin data from the database upon uninstall?', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'The plugin saves its settings in the WordPress database. By default the plugin settings are kept in the database so if it is installed again, you do not have to reconfigure the plugin. Enable this setting to delete the plugin settings from the database upon uninstall.', 'wp-2fa' ); ?>
			</p>
			<table class="form-table">
				<tbody>
					<tr>
						<th><label for="delete_data"><?php esc_html_e( 'Delete data', 'wp-2fa' ); ?></label></th>
						<td>
							<fieldset>
								<input type="checkbox" id="elete_data" name="wp_2fa_settings[delete_data_upon_uninstall]" value="delete_data_upon_uninstall"
								<?php checked( 1, WP2FA::get_wp2fa_setting( 'delete_data_upon_uninstall' ), true ); ?>
								>
								<?php esc_html_e( 'Delete data upon uninstall', 'wp-2fa' ); ?>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Get all users
	 */
	public function get_all_users() {
		// Die if user does not have permission to view.
		if ( ! current_user_can( 'manage_options' ) ) {
			die( 'Access Denied.' );
		}
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( sanitize_text_field( $get_array['wp_2fa_nonce'] ), 'wp-2fa-settings-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
		}

		$users_args = array(
			'fields'         => array( 'ID', 'user_login' ),
		);
		if ( WP2FA::is_this_multisite() ) {
			$users_args['blog_id'] = 0;
		}
		$users_data = UserUtils::get_all_user_ids_and_login_names( 'query', $users_args );

		// Create final array which we will fill in below.
		$users = [];

		foreach ( $users_data as $user ) {
			if ( strpos( $user['user_login'], $get_array['term'] ) !== false ) {
				array_push( $users, [
					'value' => $user['user_login'],
					'label' => $user['user_login']
				]);
			}
		}

		echo wp_json_encode( $users );
		exit;
	}

	/**
	 * Get all network sites
	 */
	public function get_all_network_sites() {
		// Die if user does not have permission to view.
		if ( ! current_user_can( 'manage_options' ) ) {
			die( 'Access Denied.' );
		}
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( sanitize_text_field( $get_array['wp_2fa_nonce'] ), 'wp-2fa-settings-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
		}
		// Fetch sites.
		$sites_found = array();

		foreach ( get_sites() as $site ) {
			$subsite_id                  = get_object_vars( $site )['blog_id'];
			$subsite_name                = get_blog_details( $subsite_id )->blogname;
			$site_details                = '';
			$site_details[ $subsite_id ] = $subsite_name;
			if ( false !== stripos( $subsite_name, $get_array['term'] ) ) {
				array_push( $sites_found, [
					'label' => $subsite_id,
					'value' => $subsite_name
				]);
			}
		}
		echo wp_json_encode( $sites_found );
		exit;
	}

	/**
	 * Unlock users accounts if they have overrun grace period
	 *
	 * @param  int $user_id User ID.
	 */
	public function unlock_account( $user_id ) {
		// Die if user does not have permission to view.
		if ( ! current_user_can( 'manage_options' ) ) {
			die( 'Access Denied.' );
		}

		$grace_period             = WP2FA::get_wp2fa_setting( 'grace-period' );
		$grace_period_denominator = WP2FA::get_wp2fa_setting( 'grace-period-denominator' );
		$create_a_string          = $grace_period . ' ' . $grace_period_denominator;
		// Turn that string into a time.
		$grace_expiry = strtotime( $create_a_string );

		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$nonce     = sanitize_text_field( $get_array['wp_2fa_nonce'] );

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( $nonce, 'wp-2fa-unlock-account-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
		}

		if ( isset( $get_array['user_id'] ) ) {
			global $wpdb;
			$wpdb->query(
		   $wpdb->prepare(
			   "
			   DELETE FROM $wpdb->usermeta
				 WHERE user_id = %d
				 AND meta_key IN ( %s, %s )
			   ",
			   [
					 intval( $get_array['user_id'] ),
					 WP_2FA_PREFIX . 'user_grace_period_expired',
					 WP_2FA_PREFIX . 'locked_account_notification',
				 ]
		   )
			);
			$update       = update_user_meta( intval( $get_array['user_id'] ), WP_2FA_PREFIX . 'grace_period_expiry', $grace_expiry );
			$this->send_account_unlocked_email( intval( $get_array['user_id'] ) );
			add_action( 'admin_notices', array( $this, 'user_unlocked_notice' ) );
		}
	}

	/**
	 * Remove user 2fa config
	 *
	 * @param  int $user_id User ID.
	 */
	public function remove_user_2fa( $user_id ) {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$nonce     = sanitize_text_field( $get_array['wp_2fa_nonce'] );

		if ( ! wp_verify_nonce( $nonce, 'wp-2fa-remove-user-2fa-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
		}

		if ( isset( $get_array['user_id'] ) ) {
			$user_id = intval( $get_array['user_id'] );
			global $wpdb;
			$wpdb->query(
					$wpdb->prepare(
							"DELETE FROM $wpdb->usermeta
				 WHERE user_id = %d
				 AND meta_key LIKE %s",
							[
									$user_id,
									'wp_2fa_%'
							]
					)
			);

			$is_needed = WP2FA::isUserEnforced( $user_id );

			if ( $is_needed ) {
				if ( 'do-not-enforce' !== WP2FA::get_wp2fa_setting( 'enforcement-policy' ) ) {
					// Turn inputs into a useable string.
					$create_a_string = WP2FA::get_wp2fa_setting( 'grace-period' ) . ' ' . WP2FA::get_wp2fa_setting( 'grace-period-denominator' );
					// Turn that string into a time.
					$grace_expiry = strtotime( $create_a_string );
					update_user_meta( $user_id, WP_2FA_PREFIX . 'grace_period_expiry', $grace_expiry );
					update_user_meta( $user_id, WP_2FA_PREFIX . 'update_nag_dismissed', true );
				}
				$grace_policy = WP2FA::get_wp2fa_setting( 'grace-policy' );
				if ( 'no-grace-period' === $grace_policy ) {
					update_user_meta( $user_id, WP_2FA_PREFIX . 'user_enforced_instantly', true );
					// Set this to a known older value so its already expired.
					update_user_meta( $user_id, WP_2FA_PREFIX . 'grace_period_expiry', '1609502400' );
					// Get sessions for user with ID $user_id.
					$sessions = \WP_Session_Tokens::get_instance( $user_id );
					// Log them out.
					$sessions->destroy_all();
				}
			}
			if ( isset( $get_array['admin_reset'] ) ) {
				add_action( 'admin_notices', array( $this, 'admin_deleted_2fa_notice' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'user_deleted_2fa_notice' ) );
			}
		}
	}

	/**
	 * Send account unlocked notification via email.
	 *
	 * @param int $user_id user ID.
	 *
	 * @return boolean
	 */
	public static function send_account_unlocked_email( $user_id ) {
		// Bail if the user has not enabled this email.
		if ( 'enable_account_unlocked_email' !== WP2FA::get_wp2fa_email_templates( 'send_account_unlocked_email' ) ) {
			return false;
		}

		// Grab user data.
		$user = get_userdata( $user_id );
		// Grab user email.
		$email = $user->user_email;
		// Setup the email contents.
		$subject = wp_strip_all_tags( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_unlocked_email_subject' ) ) );
		$message = wpautop( WP2FA::replace_email_strings( WP2FA::get_wp2fa_email_templates( 'user_account_unlocked_email_body' ), $user_id ) );

		self::send_email($email, $subject, $message);
	}

	/**
	 * Validate options before saving
	 *
	 * @param array $input The settings array.
	 *
	 * @return array|void
	 */
	public function validate_and_sanitize( $input ) {

		// Bail if user doesnt have permissions to be here.
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['action'] ) && ! check_admin_referer( 'wp2fa-step-choose-method' ) ) {
			return;
		}

		$noMethodEnabled = false;
		if ( ! isset( $input['enable_totp'] ) && ! isset( $input['enable_email'] ) && ! isset( $_POST['save_step'] ) ) {
			add_settings_error(
				WP_2FA_SETTINGS_NAME,
				esc_attr( 'enable_email_settings_error' ),
				esc_html__( 'At least one 2FA method should be enabled.', 'wp-2fa' ),
				'error'
			);
			$noMethodEnabled = true;
		}

		$simple_settings_we_can_loop = array(
			'enable_totp',
			'enable_email',
			'backup_codes_enabled',
			'grace-policy',
			'enable_grace_cron',
			'enable_destroy_session',
			'2fa_settings_last_updated_by',
			'limit_access',
			'delete_data_upon_uninstall',
			'hide_remove_button',
			'redirect-user-custom-page',
			'redirect-user-custom-page-global',
			'superadmins-role-add',
			'superadmins-role-exclude',
		);

		$settings_to_turn_into_bools = array(
			'enable_grace_cron',
			'enable_destroy_session',
			'limit_access',
			'delete_data_upon_uninstall',
			'hide_remove_button'
		);

		$settings_to_turn_into_array = [
			'enforced_roles',
			'enforced_users',
			'excluded_users',
			'excluded_roles',
			'excluded_sites',
		];

		foreach ( $simple_settings_we_can_loop as $simple_setting ) {
			if ( ! in_array( $simple_setting, $settings_to_turn_into_bools ) ) {
				// Is item is not one of our possible settings we want to turn into a bool, process.
				$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? trim( sanitize_text_field( $input[ $simple_setting ] ) ) : false;
			} else {
				// This item is one we treat as a bool, so process correctly.
				$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? true : false;
			}
		}

		if ( $noMethodEnabled ) {
			// No method is enabled, fall back to previous selected one - we don't want to break the logic
			$totpEnabled = WP2FA::get_wp2fa_setting( 'enable_totp' );
			$emailEnabled = WP2FA::get_wp2fa_setting( 'enable_email' );

			if ( $totpEnabled ) {
				$output['enable_totp'] = $totpEnabled;
			}
			if ( $emailEnabled ) {
				$output['enable_email'] = $emailEnabled;
			}
		}

		$output['included_sites'] = [];
		if ( isset( $input['included_sites'] ) && is_array($input['included_sites']) && ! empty( $input['included_sites'] ) ) {
			foreach ( $input['included_sites'] as &$site ) {
				if ( ! filter_var($site, FILTER_VALIDATE_INT) ) {
					unset( $site );
					continue;
				}

				$output['included_sites'][] = $site;
			}
		}
		unset( $site );

		foreach ($settings_to_turn_into_array as $setting) {
			if ( isset( $input[$setting] ) ) {
				$output[$setting] = $input[$setting];
			} else {
				$output[$setting] = [];
			}
		}

		$output['default-text-code-page'] = WP2FA::get_wp2fa_setting('default-text-code-page', false, true);

		if ( isset( $input['default-text-code-page'] ) && '' !== trim( $input['default-text-code-page'] ) ) {
			$output['default-text-code-page'] = \strip_tags( $input['default-text-code-page'] );
		}

		$log_content = __( 'The following setting are being saved: ', 'wp-2fa' ) . "\n" . json_encode( $input ) . "\n";
		Debugging::log( $log_content );

		if ( isset( $input['grace-period'] ) ) {
			if ( 0 === (int) $input['grace-period'] ) {
				add_settings_error(
					WP_2FA_SETTINGS_NAME,
					esc_attr( 'grace_settings_error' ),
					esc_html__( 'Grace period must be at least 1 day/hour', 'wp-2fa' ),
					'error'
				);
				$output['grace-period'] = 1;
			} else {
				$output['grace-period'] = (int) $input['grace-period'];
			}
		}

		if ( isset( $input['grace-period-denominator'] ) && 'days' === $input['grace-period-denominator'] || isset( $input['grace-period-denominator'] ) && 'hours' === $input['grace-period-denominator'] || isset( $input['grace-period-denominator'] ) && 'seconds' === $input['grace-period-denominator'] ) {
			$output['grace-period-denominator'] = sanitize_text_field( $input['grace-period-denominator'] );
		}

		if ( isset( $input['create-custom-user-page'] ) && 'yes' === $input['create-custom-user-page'] || isset( $input['create-custom-user-page'] ) && 'no' === $input['create-custom-user-page'] ) {
			$output['create-custom-user-page'] = sanitize_text_field( $input['create-custom-user-page'] );
		}

		if ( isset( $input['custom-user-page-url'] ) ) {
			if ( $input['custom-user-page-url'] !== WP2FA::get_wp2fa_setting( 'custom-user-page-url' ) ) {
				if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
					$updated_post = array(
						'ID'        => WP2FA::get_wp2fa_setting( 'custom-user-page-id' ),
						'post_name' => sanitize_title_with_dashes( $input['custom-user-page-url'] ),
					);
					wp_update_post( $updated_post );
					$output['custom-user-page-url'] = sanitize_title_with_dashes( $input['custom-user-page-url'] );
					$output['custom-user-page-id']  = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
				} elseif ( 'yes' === $input['create-custom-user-page'] && ! empty( $input['custom-user-page-url'] ) ) {
					$output['custom-user-page-url'] = sanitize_title_with_dashes( $input['custom-user-page-url'] );
					$create_page                    = $this->generate_custom_user_profile_page( $output['custom-user-page-url'] );
					$output['custom-user-page-id']  = (int) $create_page;
				}
			} else {
				$output['custom-user-page-url'] = sanitize_title_with_dashes( $input['custom-user-page-url'] );
				$output['custom-user-page-id']  = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
			}
		}

		if ( isset( $_REQUEST['page'] ) && 'wp-2fa-setup' !== $_REQUEST['page'] || isset( $_REQUEST[ WP_2FA_SETTINGS_NAME ]['create-custom-user-page'] ) ) {

			if ( isset( $input['create-custom-user-page'] ) && 'no' === $input['create-custom-user-page'] ) {
				$output['custom-user-page-url'] = '';
				$output['custom-user-page-id']  = '';
				wp_delete_post( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ), true );
			}
		}

		if ( isset( $input['create-custom-user-page'] ) && 'yes' === $input['create-custom-user-page'] && empty( $input['custom-user-page-url'] ) ) {
			add_settings_error(
				WP_2FA_SETTINGS_NAME,
				esc_attr( 'no_page_slug_provided' ),
				esc_html__( 'You must provide a new page slug.', 'wp-2fa' ),
				'error'
			);
		}

		if ( isset( $input['grace-period'] ) && isset( $input['grace-period-denominator'] ) ) {
			// Turn inputs into a useable string.
			$create_a_string = $output['grace-period'] . ' ' . $output['grace-period-denominator'];
			// Turn that string into a time.
			$grace_expiry                       = strtotime( $create_a_string );
			$output['grace-period-expiry-time'] = sanitize_text_field( $grace_expiry );
		}

		// Process main policy.
		if ( isset( $input['enforcement-policy'] ) && in_array( $input['enforcement-policy'], [ 'all-users', 'certain-users-only', 'certain-roles-only', 'do-not-enforce', 'superadmins-only', 'superadmins-siteadmins-only', "enforce-on-multisite" ] ) ) {

			// Clear enforced roles/users if setting has changed.
			if ( 'all-users' === $input['enforcement-policy'] || 'do-not-enforce' === $input['enforcement-policy'] ) {
				$input['enforced_users']            = [];
				$input['enforced_roles']            = [];
				$output['enforced_users']           = [];
				$output['enforced_roles']           = [];
				$output['superadmins-role-add']     = 'no';
			}

			$output['enforcement-policy'] = sanitize_text_field( $input['enforcement-policy'] );

			if ( 'certain-roles-only' === $input['enforcement-policy'] && empty( $input['enforced_roles'] ) && empty( $input['enforced_users'] ) ) {
				add_settings_error(
					WP_2FA_SETTINGS_NAME,
					esc_attr( 'enforced_roles_settings_error' ),
					esc_html__( 'You must specify at least one role or user', 'wp-2fa' ),
					'error'
				);
			}

			// If any users are being exluded, delete any wp 2fa data.
			if ( isset( $output['excluded_users'] ) &&
				!empty( array_diff(WP2FA::get_wp2fa_setting( 'excluded_users' ), $output['excluded_users']) ) ) {
				// Wipe user 2fa data.
				$user_array = $output['excluded_users'];
				foreach ( $user_array as $user ) {
					if ( ! empty( $user ) ) {
						$user_to_wipe = get_user_by( 'login', $user );
						global $wpdb;
						$wpdb->query(
							$wpdb->prepare(
								"
								DELETE FROM $wpdb->usermeta
								WHERE user_id = %d
								AND meta_key LIKE %s
								",
								[
									$user_to_wipe->ID,
									'wp_2fa_%'
								]
							)
						);

					}
				}
			}
		}

		// Remove duplicates from settings errors. We do this as this sanitization callback is actually fired twice, so we end up with duplicates when saving the settings for the FIRST TIME only. The issue is not present once the settings are in the DB as the sanitization wont fire again. For details on this core issue - https://core.trac.wordpress.org/ticket/21989.
		global $wp_settings_errors;
		if ( isset( $wp_settings_errors ) ) {
			$errors             = array_map( 'unserialize', array_unique( array_map( 'serialize', $wp_settings_errors ) ) );
			$wp_settings_errors = $errors;
		}

		// Create a hash for comparison when we interact with a use.
		$settings_hash  = SettingsUtils::create_settings_hash( $output );
		$update_options = SettingsUtils::update_option( WP_2FA_PREFIX . 'settings_hash', $settings_hash );

		$log_content = __( 'Settings saving processes complete', 'wp-2fa' );
		Debugging::log( $log_content );

		// We have overwridden any defaults by now so can clear this.
		SettingsUtils::delete_option( WP_2FA_PREFIX . 'default_settings_applied' );

		return $output;
	}

	/**
	 * Hide settings menu item
	 */
	public function hide_settings() {
		$user = wp_get_current_user();

		// Check we have a user before doing anything else.
		if ( is_a( $user, '\WP_User' ) ) {
			$user_id = (int) $user->ID;
			if ( ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ) {
				$main_user = (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' );
			} else {
				$main_user = '';
			}
			if ( ! empty( WP2FA::get_wp2fa_setting( 'limit_access' ) ) && $user->ID !== $main_user ) {
				// Remove admin menu item.
				remove_submenu_page( 'options-general.php', 'wp-2fa-settings' );
			}
		}
	}

	/**
	 * Add unlock user link to user actions.
	 *
	 * @param array $links Default row content.
	 *
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$url = network_admin_url( '/admin.php?page=wp-2fa-settings' );

		$url = Settings::getSettingsPageLink();

		$links = array_merge(
			array(
				'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Configure 2FA Settings', 'wp-2fa' ) . '</a>',
			),
			$links
		);

		return $links;

	}

	/**
	 * User unlocked notice.
	 */
	public function user_unlocked_notice() {
		?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'User account successfully unlocked. User can login again.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
		<?php
	}

	/**
	 * User deleted 2FA settings notification
	 */
	public function user_deleted_2fa_notice() {
		?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Your 2FA settings have been removed.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
		<?php
	}

	/**
	 * Admin deleted user 2FA settings notification
	 */
	public function admin_deleted_2fa_notice() {
		?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'User 2FA settings have been removed.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
		<?php
	}

	public function update_wp2fa_network_options() {
		check_admin_referer( 'wp_2fa_settings-options' );

		if ( isset( $_POST[ WP_2FA_SETTINGS_NAME ] ) ) {
			$options        = $this->validate_and_sanitize( wp_unslash( $_POST[ WP_2FA_SETTINGS_NAME ] ) );
			$settings_errors = get_settings_errors( WP_2FA_SETTINGS_NAME );
			if ( ! empty( $settings_errors ) ) {

				// redirect back to our options page.
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'                            => 'wp-2fa-settings',
							'wp_2fa_network_settings_error'   => urlencode_deep( $settings_errors[ 0 ]['message'] ),
						),
						network_admin_url( 'settings.php' )
					)
				);
				exit;

			}
			$update_options = SettingsUtils::update_option( WP_2FA_SETTINGS_NAME, $options );
		}

		// redirect back to our options page.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                            => 'wp-2fa-settings',
					'wp_2fa_network_settings_updated' => 'true',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle saving email options to the network main site options.
	 */
	public function update_wp2fa_network_email_options() {
		if ( isset( $_POST['email_from_setting'] ) ) {
			$options = $this->validate_and_sanitize_email( wp_unslash( $_POST ) );

			if ( isset( $_POST['email_from_setting'] ) && 'use-custom-email' === $_POST['email_from_setting'] && isset( $_POST['custom_from_display_name'] ) && empty( $_POST['custom_from_display_name'] ) || isset( $_POST['email_from_setting'] ) && 'use-custom-email' === $_POST['email_from_setting'] && isset( $_POST['custom_from_email_address'] ) && empty( $_POST['custom_from_email_address'] ) ) {
				// redirect back to our options page.
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => 'wp-2fa-settings',
							'wp_2fa_network_settings_updated' => 'false',
							'tab'  => 'email-settings',
						),
						network_admin_url( 'admin.php' )
					)
				);
				exit;
			}

			$update_options = SettingsUtils::update_option( WP_2FA_EMAIL_SETTINGS_NAME, $options );
		}

		// redirect back to our options page.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                            => 'wp-2fa-settings',
					'wp_2fa_network_settings_updated' => 'true',
					'tab'                             => 'email-settings',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * These are used instead of add_settings_error which in a network site. Used to show if settings have been updated or failed.
	 */
	public function settings_saved_network_admin_notice() {
		if ( isset( $_GET['wp_2fa_network_settings_updated'] ) && $_GET['wp_2fa_network_settings_updated'] == 'true' ) :
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( '2FA Settings Updated', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
			<?php
		endif;
		if ( isset( $_GET['wp_2fa_network_settings_updated'] ) && $_GET['wp_2fa_network_settings_updated'] == 'false' ) :
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'Please ensure both custom email address and display name are provided.', 'wp-2fa' ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
			<?php
		endif;
		if ( isset( $_GET['wp_2fa_network_settings_error'] ) ) :
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo urldecode_deep( $_GET['wp_2fa_network_settings_error'] ); ?></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-2fa' ); ?></span>
				</button>
			</div>
			<?php
		endif;
	}

	/**
	 * Email settings
	 */
	private function email_from_settings() {
		?>
		<h3><?php esc_html_e( 'Which email address should the plugin use as a from address?', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Use these settings to customize the "from" name and email address for all correspondence sent from our plugin.', 'wp-2fa' ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="2fa-method"><?php esc_html_e( 'From email & name', 'wp-2fa' ); ?></label>
					</th>
					<td>
						<fieldset class="contains-hidden-inputs">
							<label for="use-defaults">
								<input type="radio" name="email_from_setting" id="use-defaults" value="use-defaults"
								<?php checked( WP2FA::get_wp2fa_email_templates( 'email_from_setting' ), 'use-defaults' ); ?>
								>
							<span><?php esc_html_e( 'Use the email address from the WordPress general settings.', 'wp-2fa' ); ?></span>
							</label>

							<br/>
							<label for="use-custom-email">
								<input type="radio" name="email_from_setting" id="use-custom-email" value="use-custom-email"
								<?php checked( WP2FA::get_wp2fa_email_templates( 'email_from_setting' ), 'use-custom-email' ); ?>
								data-unhide-when-checked=".custom-from-inputs">
								<span><?php esc_html_e( 'Use another email address', 'wp-2fa' ); ?></span>
							</label>
							<fieldset class="hidden custom-from-inputs">
								<br/>
								<span><?php esc_html_e( 'Email Address:', 'wp-2fa' ); ?></span> <input type="text" id="custom_from_email_address" name="custom_from_email_address" value="<?php echo WP2FA::get_wp2fa_email_templates( 'custom_from_email_address' ); ?>"><br><br>
								<span><?php esc_html_e( 'Display Name:', 'wp-2fa' ); ?></span> <input type="text" id="custom_from_display_name" name="custom_from_display_name" value="<?php echo WP2FA::get_wp2fa_email_templates( 'custom_from_display_name' ); ?>">
							</fieldset>

						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<br>
		<hr>

		<h3><?php esc_html_e( 'Email delivery test', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'The plugin sends emails with one-time codes, blocked account notifications and more. Use the button below to confirm the plugin can successfully send emails.', 'wp-2fa' ); ?>
		</p>
		<p>
			<button type="button" name="test_email_config_test"
					class="button js-button-test-email-trigger"
					data-email-id="config_test"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-2fa-email-test-config_test' ) ); ?>">
				<?php esc_html_e( 'Test email delivery', 'wp-2fa' ); ?>
			</button>
		</p>

		<br>
		<hr>

		<?php
	}

	/**
	 * Creates the email notification definitions.
	 *
	 * @return EmailTemplate[]
	 */
	public function get_email_notification_definitions( ) {
		$result = [
				new EmailTemplate(
						'login_code',
						esc_html__( 'Login code email', 'wp-2fa' ),
						esc_html__( 'This is the email sent to a user when a login code is required.', 'wp-2fa' )
				),
				new EmailTemplate(
						'account_locked',
						esc_html__( 'User account locked email', 'wp-2fa' ),
						esc_html__( 'This is the email sent to a user upon grace period expiry.', 'wp-2fa' )
				),
				new EmailTemplate(
						'account_unlocked',
						esc_html__( 'User account unlocked email', 'wp-2fa' ),
						esc_html__( 'This is the email sent to a user when the user\'s account has been unlocked.', 'wp-2fa' )
				)
		];

		$result[0]->setCanBeToggled(false);
		$result[1]->setEmailContentId('user_account_locked');
		$result[2]->setEmailContentId('user_account_unlocked');
		return $result;
	}
	/**
	 * Email settings
	 */
	private function email_settings() {
		$custom_user_page_id = WP2FA::get_wp2fa_setting( 'custom-user-page-id' );
		$email_template_definitions = $this->get_email_notification_definitions();
		?>
		<h1><?php esc_html_e( 'Email Templates', 'wp-2fa' ); ?></h1>
		<?php foreach ($email_template_definitions as $email_template) : ?>
		<?php $template_id = $email_template->getId(); ?>
		<h3><?php echo $email_template->getTitle(); ?></h3>
		<p class="description"><?php echo $email_template->getDescription(); ?></p>
		<table class="form-table">
			<tbody>
			<?php if ($email_template->canBeToggled()): ?>
				<tr>
					<th><label for="send_<?php echo $template_id; ?>_email"><?php esc_html_e( 'Send this email', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<input type="checkbox" id="send_<?php echo $template_id; ?>_email" name="send_<?php echo $template_id; ?>_email" value="enable_<?php echo $template_id; ?>_email"
							<?php checked( 'enable_' . $template_id . '_email', WP2FA::get_wp2fa_email_templates( 'send_' . $template_id . '_email' )); ?>
							>
							<label for="send_<?php echo $template_id; ?>_email"><?php esc_html_e( 'Uncheck to disable this message.', 'wp-2fa' ); ?></label>
						</fieldset>
					</td>
				</tr>
			<?php endif; ?>
				<?php $template_id = $email_template->getEmailContentId(); ?>
				<tr>
					<th><label for="<?php echo $template_id; ?>_email_subject"><?php esc_html_e( 'Email subject', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="<?php echo $template_id; ?>_email_subject" name="<?php echo $template_id; ?>_email_subject" class="large-text" value="<?php esc_html_e( WP2FA::get_wp2fa_email_templates( $template_id . '_email_subject' ) ); ?>">
						</fieldset>
					</td>
				</tr>
				<tr>
					<th>
						<label for="<?php echo $template_id; ?>_email_body"><?php esc_html_e( 'Email body', 'wp-2fa' ); ?></label>
						</br>
						<label for="<?php echo $template_id; ?>_email_tags" style="font-weight: 400;"><?php esc_html_e( 'Available template tags:', 'wp-2fa' ); ?></label>
						</br>
						</br>
						<span style="font-weight: 400;">
							{site_url}</br>
							{site_name}</br>
							{grace_period}</br>
							{user_login_name}</br>
							{user_first_name}</br>
							{user_last_name}</br>
							{user_display_name}</br>
							{login_code}
							<?php
							if ( ! empty( $custom_user_page_id ) ) {
								echo '</br>{2fa_settings_page_url}';
							}
							?>
						</span>
					</th>
					<td>
						<fieldset>
							<?php
							$message   = WP2FA::get_wp2fa_email_templates( $template_id . '_email_body' );
							$content   = $message;
							$editor_id = $template_id . '_email_body';
							$settings  = array(
								'media_buttons' => false,
								'editor_height' => 200,
							);
							wp_editor( $content, $editor_id, $settings );
							?>
						</fieldset>
						<p>
							<button type="button" name="test_email_<?php echo esc_attr( $template_id ); ?>"
									class="button js-button-test-email-trigger"
									data-email-id="<?php echo esc_attr( $template_id ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-2fa-email-test-' . $template_id ) ); ?>">
								<?php esc_html_e( 'Send test email', 'wp-2fa' ); ?>
							</button>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<br>
		<hr>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Validate email templates before saving
	 *
	 * @param  array $input The settings array.
	 */
	public function validate_and_sanitize_email( $input ) {

		// Bail if user doesnt have permissions to be here.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST ) || ! wp_verify_nonce( $_POST['_wpnonce'], WP_2FA_PREFIX .'email_settings-options' ) && ! wp_verify_nonce( $_POST['_wpnonce'], WP_2FA_PREFIX .'settings-options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], WP_2FA_PREFIX . 'email_settings-options' ) && ! wp_verify_nonce( $_POST['_wpnonce'], WP_2FA_PREFIX . 'settings-options' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-2fa' ) );
		}

		if ( isset( $_POST['email_from_setting'] ) && 'use-defaults' === $_POST['email_from_setting'] || isset( $_POST['email_from_setting'] ) && 'use-custom-email' === $_POST['email_from_setting'] ) {
			$output['email_from_setting'] = sanitize_text_field( wp_unslash( $_POST['email_from_setting'] ) );
		}

		if ( isset( $_POST['email_from_setting'] ) && 'use-custom-email' === $_POST['email_from_setting'] && isset( $_POST['custom_from_email_address'] ) && empty( $_POST['custom_from_email_address'] ) ) {
			add_settings_error(
				WP_2FA_SETTINGS_NAME,
				esc_attr( 'email_from_settings_error' ),
				esc_html__( 'Please provide an email address', 'wp-2fa' ),
				'error'
			);
			$output['custom_from_email_address'] = '';
		}

		if ( isset( $_POST['email_from_setting'] ) && 'use-custom-email' === $_POST['email_from_setting'] && isset( $_POST['custom_from_display_name'] ) && empty( $_POST['custom_from_display_name'] ) ) {
			add_settings_error(
				WP_2FA_SETTINGS_NAME,
				esc_attr( 'display_name_settings_error' ),
				esc_html__( 'Please provide a display name.', 'wp-2fa' ),
				'error'
			);
			$output['custom_from_email_address'] = '';
		}

		if ( isset( $_POST['custom_from_email_address'] ) && ! empty( $_POST['custom_from_email_address'] ) ) {
			if ( ! filter_var( $_POST['custom_from_email_address'], FILTER_VALIDATE_EMAIL ) ) {
				add_settings_error(
					WP_2FA_SETTINGS_NAME,
					esc_attr( 'email_invalid_settings_error' ),
					esc_html__( 'Please provide a valid email address. Your email address has not been updated.', 'wp-2fa' ),
					'error'
				);
			}
			$output['custom_from_email_address'] = sanitize_email( wp_unslash( $_POST['custom_from_email_address'] ) );
		}

		if ( isset( $_POST['custom_from_display_name'] ) && ! empty( $_POST['custom_from_display_name'] ) ) {
			// Check if the string contains HTML/tags.
			preg_match( "/<\/?\w+((\s+\w+(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?>/", $_POST['custom_from_display_name'], $matches );
			if ( count( $matches ) > 0 ) {
				add_settings_error(
					WP_2FA_SETTINGS_NAME,
					esc_attr( 'display_name_invalid_settings_error' ),
					esc_html__( 'Please only use alphanumeric text. Your display name has not been updated.', 'wp-2fa' ),
					'error'
				);
			} else {
				$output['custom_from_display_name'] = sanitize_text_field( wp_unslash( $_POST['custom_from_display_name'] ) );
			}
		}

		if ( isset( $_POST['login_code_email_subject'] ) ) {
			$output['login_code_email_subject'] = wp_kses_post( wp_unslash( $_POST['login_code_email_subject'] ) );
		}

		if ( isset( $_POST['login_code_email_body'] ) ) {
			$output['login_code_email_body'] = wpautop( wp_kses_post( wp_unslash( $_POST['login_code_email_body'] ) ) );
		}

		if ( isset( $_POST['user_account_locked_email_subject'] ) ) {
			$output['user_account_locked_email_subject'] = wp_kses_post( wp_unslash( $_POST['user_account_locked_email_subject'] ) );
		}

		if ( isset( $_POST['user_account_locked_email_body'] ) ) {
			$output['user_account_locked_email_body'] = wpautop( wp_kses_post( wp_unslash( $_POST['user_account_locked_email_body'] ) ) );
		}

		if ( isset( $_POST['user_account_unlocked_email_subject'] ) ) {
			$output['user_account_unlocked_email_subject'] = wp_kses_post( wp_unslash( $_POST['user_account_unlocked_email_subject'] ) );
		}

		if ( isset( $_POST['user_account_unlocked_email_body'] ) ) {
			$output['user_account_unlocked_email_body'] = wpautop( wp_kses_post( wp_unslash( $_POST['user_account_unlocked_email_body'] ) ) );
		}

		if ( isset( $_POST['send_account_locked_email'] ) && 'enable_account_locked_email' === $_POST['send_account_locked_email'] ) {
			$output['send_account_locked_email'] = sanitize_text_field( $_POST['send_account_locked_email'] );
		}

		if ( isset( $_POST['send_account_unlocked_email'] ) && 'enable_account_unlocked_email' === $_POST['send_account_unlocked_email'] ) {
			$output['send_account_unlocked_email'] = sanitize_text_field( $_POST['send_account_unlocked_email'] );
		}

		// Remove duplicates from settings errors. We do this as this sanitization callback is actually fired twice, so we end up with duplicates when saving the settings for the FIRST TIME only. The issue is not present once the settings are in the DB as the sanitization wont fire again. For details on this core issue - https://core.trac.wordpress.org/ticket/21989.
		global $wp_settings_errors;
		if ( isset( $wp_settings_errors ) ) {
			$errors             = array_map( 'unserialize', array_unique( array_map( 'serialize', $wp_settings_errors ) ) );
			$wp_settings_errors = $errors;
		}

		if ( isset( $output ) ) {
			return $output;
		} else {
			return;
		}

	}

	/**
	 * Creates a new page with our shortcode present.
	 */
	public function generate_custom_user_profile_page( $page_slug ) {
		// Bail if user doesnt have permissions to be here.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if a page with slug exists.
		$page_exists = $this->get_post_by_post_name( $page_slug, 'page' );
		if ( $page_exists ) {
			// Seeing as the page exisits, return its ID.
			return $page_exists->ID;
		}

		$generated_by_message = '<p>'.esc_html__( 'Page generated by', 'wp-2fa' );
		$generated_by_message .= ' <a href="https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/" target="_blank">'.esc_html__( 'WP 2FA Plugin', 'wp-2fa' ).'</a>';
		$generated_by_message .= '</p>';

		$user      = wp_get_current_user();
		$post_data = array(
			'post_title'   => 'WP 2FA User Profile',
			'post_name'    => $page_slug,
			'post_content' => '[wp-2fa-setup-form] ' . $generated_by_message,
			'post_status'  => 'publish',
			'post_author'  => $user->ID,
			'post_type'    => 'page',
		);

		// Lets insert the post now.
		$result = wp_insert_post( $post_data );

		if ( $result && ! is_wp_error( $result ) ) {
			$post_id = $result;
			set_transient( WP_2FA_PREFIX . 'new_custom_page_created', true, 60 );
			set_site_transient( WP_2FA_PREFIX .'new_custom_page_created', true, 60 );
			return $post_id;
		}
	}

	/**
	 * Check if page with slug exisits.
	 */
	public function get_post_by_post_name( $slug = '', $post_type = '' ) {
		if ( ! $slug || ! $post_type ) {
			return false;
		}

		$post_object = get_page_by_path( $slug, OBJECT, $post_type );

		if ( ! $post_object ) {
			return false;
		}

		return $post_object;
	}

	/**
	 * Add our custom state to our created page.
	 */
	public function add_display_post_states( $post_states, $post ) {
		if ( ! empty( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) ) ) {
			if ( WP2FA::get_wp2fa_setting( 'custom-user-page-id' ) === $post->ID ) {
				$post_states['wp_2fa_page_for_user'] = __( 'WP 2FA User Page', 'wp-2fa' );
			}
		}

		return $post_states;
	}

	/**
	 * Handles sending of an email. It sets necessary header such as content type and custom from email address and name.
	 *
	 * @param string $recipient_email Email address to send message to.
	 * @param string $subject Email subject.
	 * @param string $message Message contents.
	 *
	 * @return bool Whether the email contents were sent successfully.
	 */
	public static function send_email( $recipient_email, $subject, $message ) {

		// Specify our desired headers.
		$headers = 'Content-type: text/html;charset=utf-8' . "\r\n";

		if ( 'use-custom-email' === WP2FA::get_wp2fa_email_templates( 'email_from_setting' ) ) {
			$headers .= 'From: ' . WP2FA::get_wp2fa_email_templates( 'custom_from_display_name' ) . ' <' . WP2FA::get_wp2fa_email_templates( 'custom_from_email_address' ) . '>' . "\r\n";
		} else {
			$headers .= 'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
		}

		// Fire our email.
		return wp_mail( $recipient_email, $subject, $message, $headers );

	}

	/**
	 * Turns user roles data in any form and shape to an array of strings.
	 *
	 * @param mixed $value User role names (slugs) as raw value.
	 *
	 * @return string[] List of user role names (slugs).
	 */
	public static function extract_roles_from_input( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && ! empty( $value ) ) {
			return explode( ',', $value );
		}

		return [];
	}

	/**
	 * Determine if any BG processes are currently running.
	 *
	 * @return int|false Number of jobs.
	 */
	public function get_current_number_of_active_bg_processes() {
		global $wpdb;

		$bg_jobs = $wpdb->get_results(
				"SELECT option_value FROM $wpdb->options
				WHERE option_name LIKE '%_2fa_bg_%'"
		);

		return count( $bg_jobs );
	}

	/**
	 * Cancel BG processes.
	 *
	 */
	public function cancel_bg_processes() {
		global $wpdb;
		$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_2fa_bg_%'");

		$cron_hook_identifiers = [ '2fa_check_grace_period_status', '2fa_bg_update_user_meta', '2fa_bg_wipe_all_user_data', '2fa_bg_remove_enabled_methods' ];

		foreach ( $cron_hook_identifiers as $cron_hook_identifier ) {
			$cleared_jobs = wp_clear_scheduled_hook( $wpdb->prefix.$cron_hook_identifier );
		}

		wp_send_json_success( $cleared_jobs );
	}

	/**
	 * Checks if the backup codes option is globally enabled
	 *
	 * @return bool
	 */
	public static function are_backup_codes_enabled() {

		if ( null === self::$backupCodesEnabled ) {
			self::$backupCodesEnabled = false;

			if ( 'yes' === WP2FA::get_wp2fa_setting( 'backup_codes_enabled' ) ) {
				self::$backupCodesEnabled = true;
			}
		}

		return self::$backupCodesEnabled;
	}
}
