<?php
/**
 * Policy settings class.
 *
 * @package    wp2fa
 * @subpackage settings-pages
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\SettingsPages;

use WP2FA\WP2FA;
use WP2FA\Utils\Debugging;
use WP2FA\Methods\Passkeys;
use WP2FA\Admin\Settings_Page;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;

/*
 * Policies settings tab
 */
if ( ! class_exists( '\WP2FA\Admin\SettingsPages\Settings_Page_Passkeys' ) ) {
	/**
	 * Settings_Page_Passkeys - Class for handling settings.
	 *
	 * @since 2.0.0
	 */
	class Settings_Page_Passkeys {

		public const TOP_MENU_SLUG = 'wp-2fa-passkeys';

		/**
		 * Renders the settings.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function render() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			$user      = \wp_get_current_user();
			$main_user = ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ? (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) : \get_current_user_id();

			/**
			 * Used from user settings controller.
			 *
			 * @param bool - Default at this point is false - no user settings.
			 *
			 * @since 2.4.0
			 */
			$roles_controller = \apply_filters( WP_2FA_PREFIX . 'roles_controller_exists', false );
			?>

			<div class="wrap wp-2fa-settings-wrapper wp2fa-form-styles">
				<h2><?php \esc_html_e( 'Passkeys', 'wp-2fa' ); ?></h2>
				<hr>
				<?php if ( ! empty( WP2FA::get_wp2fa_general_setting( 'limit_access' ) ) && $main_user !== $user->ID ) { ?>
					<?php
					echo \esc_html__( 'These settings have been disabled by your site administrator, please contact them for further assistance.', 'wp-2fa' );
					?>
				<?php } else { ?>
					<?php
						/**
						 * Fires before the plugin settings rendering.
						 *
						 * @since 2.0.0
						 */
						\do_action( WP_2FA_PREFIX . 'before_plugin_settings_passkeys' );
					?>
						<?php
						if ( WP_Helper::is_multisite() ) {
							$action = 'edit.php?action=update_wp2fa_network_options';
						} else {
							$action = 'options.php';
						}
						?>
						<br/>
							<?php
							printf(
								'<p class="description">%1$s %2$s</p>',
								\esc_html__( 'Passkeys are not a two-factor authentication (2FA) method. They are a passwordless multi-factor authentication (MFA) solution that allows users to securely log in without using a password.', 'wp-2fa' ),
								\wp_kses(
									sprintf(
										/* translators: 1: opening link tag, 2: closing link tag */
										__( 'For more information about Passkeys and how to configure these settings refer to the guide %1$sHow to set up Passkeys in WP 2FA%2$s.', 'wp-2fa' ),
										'<a href="' . \esc_url( 'https://melapress.com/support/kb/wp-2fa-set-up-passkeys/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=passkeys_admin' ) . '" target="_blank" rel="noopener noreferrer">',
										'</a>'
									),
									array(
										'a' => array(
											'href'   => array(),
											'target' => array(),
											'rel'    => array(),
										),
									)
								)
							);
							?>
						<br/>
							
						<form id="wp-2fa-admin-settings" action='<?php echo \esc_attr( $action ); ?>' method='post' autocomplete="off" data-disabled-note="<?php echo \esc_attr__( 'Please select at least one role to save.', 'wp-2fa' ); ?>">
							<?php
							\settings_fields( WP_2FA_PASSKEYS_SETTINGS_NAME );
							?>
							<table class="form-table">
								<tbody>
								<tr>
									<th>
										<label for="passkeys-enabled">
											<?php \esc_html_e( 'Allow users to register Passkeys', 'wp-2fa' ); ?>
										</label>
									</th>
									<td>
										<fieldset>
											<label for="passkeys-enabled">
												<?php
												$enabled      = (bool) Settings_Utils::get_setting_role( null, Passkeys::POLICY_SETTINGS_NAME );
												$first_check  = $enabled;
												$second_check = false;
												if ( ! $enabled ) {
													$enabled      = (bool) Settings_Utils::get_setting_role( null, Passkeys::POLICY_SETTINGS_NAME . '_certain_roles' );
													$second_check = $enabled;
													$first_check  = false;
												}
												?>
												<input type="checkbox" id="passkeys-enabled" value="yes"
														name="wp_2fa_passkeys[<?php echo \esc_attr( Passkeys::POLICY_SETTINGS_NAME ); ?>]"
														<?php \checked( true, $enabled ); ?>
												>
											</label>
										</fieldset>
										<?php
										if ( class_exists( Role_Settings_Controller::class, false ) ) {
											// Preload roles for the enforcement policy row below.
											$roles = array_flip( WP_Helper::get_roles() );
										}
										?>
									</td>
								<?php if ( class_exists( Role_Settings_Controller::class, false ) ) { ?>
								<tr class="enforcement-policy-row" style="display:none;">
									<th>
										<label>
											<?php \esc_html_e( 'Allow the following users', 'wp-2fa' ); ?>
										</label>
									</th>
									<td>
										<label style="margin:.35em 0 .5em !important; display: block;">
											<?php $first_check = (bool) ( ! $second_check ); ?>
											<input type="radio" name="wp_2fa_passkeys[enforcement-policy]" id="all-roles" value="all-roles" <?php \checked( true, $first_check ); ?>>
											<span><?php \esc_html_e( 'All users with any role', 'wp-2fa' ); ?></span>
										</label>
										<label style="margin:.35em 0 .5em !important; display: block;">
											<?php $checked = in_array( WP2FA::get_wp2fa_setting( 'enforcement-policy' ), array( 'certain-roles-only', 'certain-users-only' ), true ); ?>
											<input type="radio" name="wp_2fa_passkeys[enforcement-policy]" id="certain-roles-only" value="certain-roles-only" <?php checked( true, $second_check ); ?> data-unhide-when-checked=".certain-roles-only-inputs, .certain-users-only-inputs">
											<span><?php \esc_html_e( 'Only users with the below user roles:', 'wp-2fa' ); ?></span>
										</label>

										<div class="roles-selector-wrapper" style="margin-left:24px;">
											<?php
											foreach ( $roles as $role => $value ) {
												?>
												<div>
													<label for="passkeys-enabled-role-<?php echo \esc_attr( $role ); ?>">
														<input type="checkbox" id="passkeys-enabled-role-<?php echo \esc_attr( $role ); ?>" value="yes" name="wp_2fa_passkeys[enabled_roles][<?php echo \esc_attr( $role ); ?>]" <?php if ( Passkeys::is_enabled( $role ) ) { ?>checked="checked"<?php } ?>><?php echo \esc_html( $value ); ?>
													</label>
												</div>
												<?php
											}
											?>
										</div>
									</td>
								</tr>
								<?php } ?>
								</tr>
								<?php
								// @free:start
								?>
								<tr>
									<td colspan="2" style="padding:0px;">
										<?php
										echo wp_sprintf(
											/* translators: 1: opening bold tag, 2: closing bold tag, 3: opening link tag, 4: closing link tag */
											__( '%1$sNote:%2$s In the Free edition of WP 2FA, each user can register only one Passkey, and Passkey logins bypass 2FA. The %3$sPremium edition%4$s enables users to register multiple Passkeys and gives you the option to enforce 2FA even when users sign in with a Passkey.', 'wp-2fa' ),
											'<b>',
											'</b>',
											'<a href="https://melapress.com/wordpress-2fa/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=passkeys+settings+page" target="_blank" rel="noopener noreferrer">',
											'</a>'
										);
										?>
									</td>
								</tr>
								<?php
								// @free:end
								?>
								</tbody>
							</table>
							<?php

							// @free:start
							?>
							<input type="hidden" name="wp_2fa_passkeys[skip_2fa_for_passkeys]" value="1">
							<?php
							// @free:end


							\submit_button();

							$javascript = <<<JS
			<script>
				document.addEventListener('DOMContentLoaded', function(){
					const passkeysEnabledCheckbox = document.getElementById('passkeys-enabled');
					const certainRolesRadio = document.getElementById('certain-roles-only');
					const allRolesRadio = document.getElementById('all-roles');
					const certainRolesWrapper = document.querySelectorAll('.roles-selector-wrapper');
					const enforcementRow = document.querySelector('.enforcement-policy-row');
						const form = document.getElementById('wp-2fa-admin-settings');
						const submitBtn = form ? form.querySelector('#submit, [type="submit"]') : null;
						const noteText = form ? (form.dataset.disabledNote || 'Please select at least one role to save.') : 'Please select at least one role to save.';
						let submitNoteEl = null;

						function ensureSubmitNote() {
							if (!submitBtn) return;
							if (!submitNoteEl) {
								submitNoteEl = document.createElement('span');
								submitNoteEl.className = 'wp2fa-submit-disabled-note description';
								submitNoteEl.style.marginLeft = '8px';
								submitNoteEl.style.color = '#646970';
								submitNoteEl.style.fontSize = '12px';
								submitNoteEl.style.fontStyle = 'italic';
								submitBtn.insertAdjacentElement('afterend', submitNoteEl);
							}
						}

						function updateSubmitState() {
							if (!submitBtn) return;
							const rolesWrapper = document.querySelector('.roles-selector-wrapper');
							const requireSelection = !!(passkeysEnabledCheckbox && certainRolesRadio && rolesWrapper && rolesWrapper.style.display !== 'none' && passkeysEnabledCheckbox.checked && certainRolesRadio.checked);
							let checkedCount = 0;
							if (requireSelection) {
								checkedCount = rolesWrapper.querySelectorAll('input[type="checkbox"]:checked').length;
							}
							const shouldDisable = requireSelection && checkedCount === 0;
							submitBtn.disabled = shouldDisable;
							ensureSubmitNote();
							if (submitNoteEl) {
								submitNoteEl.textContent = shouldDisable ? noteText : '';
								submitNoteEl.style.display = shouldDisable ? 'inline' : 'none';
							}
						}

					function toggleCertainRolesInputs() {
						if ( passkeysEnabledCheckbox.checked ) {
							// Show enforcement row when passkeys enabled.
							if ( enforcementRow ) enforcementRow.style.display = 'table-row';
							// Show roles selector only when certain-roles is selected.
							if ( certainRolesRadio.checked ) {
								certainRolesWrapper.forEach(function(element) { element.style.display = 'block'; });
							} else {
								certainRolesWrapper.forEach(function(element) { element.style.display = 'none'; });
							}
						} else {
							// Hide enforcement row and roles if passkeys disabled.
							if ( enforcementRow ) enforcementRow.style.display = 'none';
							certainRolesWrapper.forEach(function(element) { element.style.display = 'none'; });
						}

						// Re-evaluate submit availability when UI visibility changes
						updateSubmitState();
					}

					passkeysEnabledCheckbox.addEventListener('change', toggleCertainRolesInputs);
					certainRolesRadio.addEventListener('change', toggleCertainRolesInputs);
					allRolesRadio.addEventListener('change', toggleCertainRolesInputs);

					// Role checkbox listeners to enable/disable submit
					document.querySelectorAll('.roles-selector-wrapper input[type="checkbox"]').forEach(function(cb){
						cb.addEventListener('change', updateSubmitState);
					});

					// Initial check
					toggleCertainRolesInputs();
					updateSubmitState();
				});
			</script>
			JS;

							if ( class_exists( Role_Settings_Controller::class, false ) ) {

								echo $javascript;
							}
							?>
						</form>
					
				<?php } ?>
			</div>
			<?php
		}

		/**
		 * Validate options before saving.
		 *
		 * @param array $input The settings array.
		 *
		 * @return array|void
		 *
		 * @since 2.0.0
		 */
		public static function validate_and_sanitize( $input ) {
			Debugging::log( 'The following settings will be processed (Passkeys): ' . "\n" . \wp_json_encode( $input ) );

			/*
			 * Adds the ability to check the referer and act accordingly.
			 *
			 * @since 2.0.0
			 */
			\do_action( WP_2FA_PREFIX . 'change_referer' );

			// Bail if user doesn't have permissions to be here.
			if ( ! \current_user_can( 'manage_options' ) || ! isset( $_POST['action'] ) && ! \check_admin_referer( 'wp2fa-step-choose-method' ) ) {
				return;
			}
			$output = array();

			$simple_settings_we_can_loop = array(
				Passkeys::POLICY_SETTINGS_NAME,
			);

			$settings_to_turn_into_bools = array(
				Passkeys::POLICY_SETTINGS_NAME,
			);

			$settings_to_turn_into_array = array(
				'enabled_roles',
			);

			foreach ( $simple_settings_we_can_loop as $simple_setting ) {
				if ( ! in_array( $simple_setting, $settings_to_turn_into_bools, true ) ) {
					// Is item is not one of our possible settings we want to turn into a bool, process.
					$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? trim( (string) sanitize_text_field( $input[ $simple_setting ] ) ) : false;
				} else {
					// This item is one we treat as a bool, so process correctly.
					$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? true : false;
				}
			}

			foreach ( $settings_to_turn_into_array as $setting ) {
				if ( isset( $input[ $setting ] ) ) {
					$output[ $setting ] = $input[ $setting ];
				} else {
					$output[ $setting ] = array();
				}
			}

			// Remove duplicates from settings errors. We do this as this sanitization callback is actually fired twice, so we end up with duplicates when saving the settings for the FIRST TIME only. The issue is not present once the settings are in the DB as the sanitization wont fire again. For details on this core issue - https://core.trac.wordpress.org/ticket/21989.
			global $wp_settings_errors;
			if ( isset( $wp_settings_errors ) ) {
				$errors             = array_map( 'unserialize', array_unique( array_map( 'serialize', $wp_settings_errors ) ) );
				$wp_settings_errors = $errors; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}

			$settings = WP2FA::get_policy_settings();

			if ( isset( $output['enable_passkeys'] ) && true === (bool) $output['enable_passkeys'] ) {
				$settings[ Passkeys::POLICY_SETTINGS_NAME ] = Passkeys::POLICY_SETTINGS_NAME;
				unset( $settings[ Passkeys::POLICY_SETTINGS_NAME . '_certain_roles' ] );

				if ( class_exists( Role_Settings_Controller::class, false ) ) {
					if ( isset(
						$input['enforcement-policy']
					) && 'certain-roles-only' === $input['enforcement-policy'] ) {
						$settings[ Passkeys::POLICY_SETTINGS_NAME ]                    = '';
						$settings[ Passkeys::POLICY_SETTINGS_NAME . '_certain_roles' ] = 'certain-roles-only';

						$roles_options_passkeys = array();
						$roles                  = WP_Helper::get_roles();
						foreach ( $roles as $role ) {
							if ( ! isset( $input['enabled_roles'][ $role ] ) || empty( $input['enabled_roles'][ $role ] ) ) {
								continue;
							}

							$roles_options_passkeys[ $role ][ Passkeys::POLICY_SETTINGS_NAME ] = Passkeys::POLICY_SETTINGS_NAME;
						}

						$roles_options = (array) Settings_Utils::get_option( Role_Settings_Controller::SETTINGS_NAME );

						foreach ( $roles as $role_name ) {
							if ( isset( $roles_options_passkeys[ $role_name ][ Passkeys::POLICY_SETTINGS_NAME ] ) ) {
								$roles_options[ $role_name ][ Passkeys::POLICY_SETTINGS_NAME ] = Passkeys::POLICY_SETTINGS_NAME;
							}

							if ( ! isset( $roles_options_passkeys[ $role_name ][ Passkeys::POLICY_SETTINGS_NAME ] ) && isset( $roles_options[ $role_name ][ Passkeys::POLICY_SETTINGS_NAME ] ) ) {
								unset( $roles_options[ $role_name ][ Passkeys::POLICY_SETTINGS_NAME ] );
								unset( $roles_options[ $role_name ][ Passkeys::POLICY_SETTINGS_NAME . '_certain_roles' ] );
							}
						}
					}
					if ( isset(
						$input['enforcement-policy']
					) && 'all-roles' === $input['enforcement-policy'] ) {
						$roles_options = (array) Settings_Utils::get_option( Role_Settings_Controller::SETTINGS_NAME );

						$roles = WP_Helper::get_roles();
						foreach ( $roles as $role ) {
							if ( isset( $roles_options[ $role ][ Passkeys::POLICY_SETTINGS_NAME ] ) ) {
								unset( $roles_options[ $role ][ Passkeys::POLICY_SETTINGS_NAME ] );
								unset( $roles_options[ $role ][ Passkeys::POLICY_SETTINGS_NAME . '_certain_roles' ] );
							}
						}
					}
				}
			} else {
				unset( $settings[ Passkeys::POLICY_SETTINGS_NAME ] );
				unset( $settings[ Passkeys::POLICY_SETTINGS_NAME . '_certain_roles' ] );
				if ( class_exists( Role_Settings_Controller::class, false ) ) {
					$roles_options = (array) Settings_Utils::get_option( Role_Settings_Controller::SETTINGS_NAME );

					$roles = WP_Helper::get_roles();
					foreach ( $roles as $role ) {
						if ( isset( $roles_options[ $role ][ Passkeys::POLICY_SETTINGS_NAME ] ) ) {
							unset( $roles_options[ $role ][ Passkeys::POLICY_SETTINGS_NAME ] );
						}
					}
				}
			}

			if ( class_exists( Role_Settings_Controller::class, false ) ) {

				if ( isset( $roles_options ) && ! empty( $roles_options ) ) {

					Settings_Utils::update_option( Role_Settings_Controller::SETTINGS_NAME, $roles_options );
					unset( $settings['role-settings'] );
					$settings['role-settings'] = $roles_options;

					foreach ( $roles_options as $role_name => $value ) {
						$settings[ $role_name ] = $value;
					}
				}
				\remove_filter( WP_2FA_PREFIX . 'filter_output_content', array( Role_Settings_Controller::class, 'validate_and_sanitize' ), 10, 2 );
			}

			// WordPress saves the option to the database, but we still need to do some work when the settings are saved.
			WP2FA::update_plugin_settings( $settings );

			Debugging::log( 'The following settings are being saved (Passkeys): ' . "\n" . \wp_json_encode( $output ) );

			// We have overridden any defaults by now so can clear this.
			// Unless it is not Wizard process in which case we will leave that as is for now.
			if ( ! isset( $_GET['page'] ) || ( isset( $_GET['page'] ) && 'wp-2fa-setup' !== $_GET['page'] ) ) {
				Settings_Utils::delete_option( WP_2FA_PREFIX . 'default_settings_applied' );
				Settings_Utils::delete_option( 'wizard_not_finished' );
			}

			/**
			 * Notify the extensions and 3rd party developers that the settings array is saved.
			 *
			 * @param array - Array with all the stored settings.
			 *
			 * @since 2.6.0
			 */
			\do_action( WP_2FA_PREFIX . 'after_settings_save', Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() ) );

			if ( isset( $_POST[ WP_2FA_SETTINGS_NAME . 'pass' ]['skip_2fa_for_passkeys'] ) && true === (bool) $_POST[ WP_2FA_SETTINGS_NAME . 'pass' ]['skip_2fa_for_passkeys'] ) {
				$options                          = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );
				$options['skip_2fa_for_passkeys'] = true;
			} else {
				$options                          = Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );
				$options['skip_2fa_for_passkeys'] = false;
			}

			WP2FA::update_plugin_settings( $options, false, WP_2FA_SETTINGS_NAME );

			Settings_Utils::update_option( self::TOP_MENU_SLUG, $options );

			\wp_safe_redirect( \add_query_arg(
				array(
					'page'                           => self::TOP_MENU_SLUG,
				),
				\network_admin_url( 'admin.php' )
			) );
			exit;

			remove_filter( 'sanitize_option_' . self::TOP_MENU_SLUG, array( __CLASS__, 'validate_and_sanitize' ) );
			add_filter( 'default_option_' . self::TOP_MENU_SLUG, '__return_false' );

			return $settings;
		}

		/**
		 * Updates global policy network options.
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function update_wp2fa_network_options() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-2fa' ) );
			}

			if ( isset( $_POST[ WP_2FA_PASSKEYS_SETTINGS_NAME ] ) ) {
				\check_admin_referer( WP_2FA_PASSKEYS_SETTINGS_NAME . '-options' );
				$options = self::validate_and_sanitize(wp_unslash($_POST[WP_2FA_PASSKEYS_SETTINGS_NAME])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$settings_errors = \get_settings_errors( WP_2FA_PASSKEYS_SETTINGS_NAME );
				if ( ! empty( $settings_errors ) ) {
					// redirect back to our options page.
					\wp_safe_redirect(
						\add_query_arg(
							array(
								'page' => Settings_Page::TOP_MENU_SLUG,
								'wp_2fa_network_settings_error' => urlencode_deep( $settings_errors[0]['message'] ),
							),
							\network_admin_url( 'admin.php' )
						)
					);
					exit;
				}
				WP2FA::update_plugin_settings( $options );

				// redirect back to our options page.
				\wp_safe_redirect(
					\add_query_arg(
						array(
							'page' => self::TOP_MENU_SLUG,
							'wp_2fa_network_settings_updated' => 'true',
						),
						\network_admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}
	}
}

