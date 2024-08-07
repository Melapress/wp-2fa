<?php
/**
 * White label settings class.
 *
 * @package    wp2fa
 * @subpackage settings-pages
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\SettingsPages;

use WP2FA\WP2FA;
use WP2FA\Utils\Debugging;
use WP2FA\Extensions\WhiteLabeling\White_Labeling_Render;

/**
 * White labeling settings tab
 */
if ( ! class_exists( '\WP2FA\Admin\SettingsPages\Settings_Page_White_Label' ) ) {
	/**
	 * Settings_Page_White_Label - Class for handling settings
	 *
	 * @since 2.0.0
	 */
	class Settings_Page_White_Label {

		/**
		 * Render the settings
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		public static function render() {
			settings_fields( WP_2FA_WHITE_LABEL_SETTINGS_NAME );
			self::white_labelling_tabs_wrapper();
			submit_button();
		}

		/**
		 * Validate options before saving
		 *
		 * @param array $input The settings array.
		 *
		 * @return array|void
		 *
		 * @since 2.0.0
		 */
		public static function validate_and_sanitize( $input ) {

			// Bail if user doesn't have permissions to be here.
			if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['action'] ) && ! check_admin_referer( 'wp2fa-step-choose-method' ) ) {
				return;
			}

			Debugging::log( 'The following settings will be processed (White Label): ' . "\n" . wp_json_encode( $input ) );

			$output['default-text-code-page'] = WP2FA::get_wp2fa_white_label_setting( 'default-text-code-page', false, false );

			if ( isset( $input['default-text-code-page'] ) && '' !== trim( (string) $input['default-text-code-page'] ) ) {
				$output['default-text-code-page'] = \wp_kses_post( $input['default-text-code-page'] );
			}

			$output['default-backup-code-page'] = WP2FA::get_wp2fa_white_label_setting( 'default-backup-code-page', false, false );

			if ( isset( $input['default-backup-code-page'] ) && '' !== trim( (string) $input['default-backup-code-page'] ) ) {
				$output['default-backup-code-page'] = \wp_strip_all_tags( $input['default-backup-code-page'] );
			}

			$output['login-to-view-area'] = WP2FA::get_wp2fa_white_label_setting( 'login-to-view-area', false, false );

			if ( isset( $input['login-to-view-area'] ) && '' !== trim( (string) $input['login-to-view-area'] ) ) {
				$output['login-to-view-area'] = \wp_strip_all_tags( $input['login-to-view-area'] );
			}

			$output['use_custom_2fa_message'] = WP2FA::get_wp2fa_white_label_setting( 'use_custom_2fa_message', false, false );

			if ( isset( $input['use_custom_2fa_message'] ) && '' !== trim( (string) $input['use_custom_2fa_message'] ) ) {
				$output['use_custom_2fa_message'] = \wp_strip_all_tags( $input['use_custom_2fa_message'] );
			}

			$output['custom-text-app-code-page']            = WP2FA::get_wp2fa_white_label_setting( 'custom-text-app-code-page', false, false );
			$output['custom-text-email-code-page']          = WP2FA::get_wp2fa_white_label_setting( 'custom-text-email-code-page', false, false );
			$output['custom-text-authy-code-page-intro']    = WP2FA::get_wp2fa_white_label_setting( 'custom-text-authy-code-page-intro', false, false );
			$output['custom-text-authy-code-page-awaiting'] = WP2FA::get_wp2fa_white_label_setting( 'custom-text-authy-code-page-awaiting', false, false );
			$output['custom-text-authy-code-page']          = WP2FA::get_wp2fa_white_label_setting( 'custom-text-authy-code-page', false, false );
			$output['custom-text-twilio-code-page']         = WP2FA::get_wp2fa_white_label_setting( 'custom-text-twilio-code-page', false, false );

			if ( isset( $input['custom-text-app-code-page'] ) && '' !== trim( (string) $input['custom-text-app-code-page'] ) ) {
				$output['custom-text-app-code-page'] = \wp_strip_all_tags( $input['custom-text-app-code-page'] );
			}

			if ( isset( $input['custom-text-email-code-page'] ) && '' !== trim( (string) $input['custom-text-email-code-page'] ) ) {
				$output['custom-text-email-code-page'] = \wp_strip_all_tags( $input['custom-text-email-code-page'] );
			}

			if ( isset( $input['custom-text-authy-code-page'] ) && '' !== trim( (string) $input['custom-text-authy-code-page'] ) ) {
				$output['custom-text-authy-code-page'] = \wp_strip_all_tags( $input['custom-text-authy-code-page'] );
			}

			if ( isset( $input['custom-text-authy-code-page-intro'] ) && '' !== trim( (string) $input['custom-text-authy-code-page-intro'] ) ) {
				$output['custom-text-authy-code-page-intro'] = \wp_strip_all_tags( $input['custom-text-authy-code-page-intro'] );
			}

			if ( isset( $input['custom-text-authy-code-page-awaiting'] ) && '' !== trim( (string) $input['custom-text-authy-code-page-awaiting'] ) ) {
				$output['custom-text-authy-code-page-awaiting'] = \wp_strip_all_tags( $input['custom-text-authy-code-page-awaiting'] );
			}

			if ( isset( $input['custom-text-twilio-code-page'] ) && '' !== trim( (string) $input['custom-text-twilio-code-page'] ) ) {
				$output['custom-text-twilio-code-page'] = \wp_strip_all_tags( $input['custom-text-twilio-code-page'] );
			}

			if ( isset( $_REQUEST['_wp_http_referer'] ) ) {
				$request_area      = wp_parse_url( \wp_unslash( $_REQUEST['_wp_http_referer'] ) ); // phpcs:ignore
				$request_area_path = strpos( $request_area['query'], 'white-label-section' );

				// If we have the input POSTed, we are on the right page so grab it.
				if ( isset( $input['enable_wizard_styling'] ) && '' !== trim( (string) $input['enable_wizard_styling'] ) ) {
					$output['enable_wizard_styling'] = \wp_strip_all_tags( $input['enable_wizard_styling'] );
				} else {
					// Are we on either the white labelling page (free and premium) or the custom CSS area (premium only)?
					if ( ! $request_area_path || $request_area_path && strpos( $request_area['query'], 'custom-css' ) ) {
						$output['enable_wizard_styling'] = '';
						$input['enable_wizard_styling']  = '';
					} else {
						$input['enable_wizard_styling']  = WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling', false );
						$output['enable_wizard_styling'] = WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling', false );
					}
				}

				if ( isset( $input['show_help_text'] ) && '' !== trim( (string) $input['show_help_text'] ) ) {
					$output['show_help_text'] = \wp_strip_all_tags( $input['show_help_text'] );
				} else {
					// Nothing was POSTed, check where we are in case that means we simple an empty/disabled checkbox.
					if ( $request_area_path && ! strpos( $request_area['query'], 'method_selection' ) ) {
						$input['show_help_text']  = WP2FA::get_wp2fa_white_label_setting( 'show_help_text', false );
						$output['show_help_text'] = WP2FA::get_wp2fa_white_label_setting( 'show_help_text', false );
					} else {
						$output['show_help_text'] = '';
						$input['show_help_text']  = '';
					}
				}

				// Same as above, but for the optional welcome.
				if ( isset( $input['enable_welcome'] ) && '' !== trim( (string) $input['enable_welcome'] ) ) {
					$output['enable_welcome'] = \wp_strip_all_tags( $input['enable_welcome'] );
				} elseif ( strpos( $request_area['query'], 'white-label-sub-section' ) && strpos( $request_area['query'], 'welcome' ) ) {
						$input['enable_welcome']  = '';
						$output['enable_welcome'] = '';
				} else {
					$input['enable_welcome']  = WP2FA::get_wp2fa_white_label_setting( 'enable_welcome', false );
					$output['enable_welcome'] = WP2FA::get_wp2fa_white_label_setting( 'enable_welcome', false );
				}

				if ( isset( $input['enable_wizard_logo'] ) && '' !== trim( (string) $input['enable_wizard_logo'] ) ) {
					$output['enable_wizard_logo'] = \wp_strip_all_tags( $input['enable_wizard_logo'] );
				} elseif ( strpos( $request_area['query'], 'white-label-sub-section' ) && strpos( $request_area['query'], 'welcome' ) ) {
						$input['enable_wizard_logo']  = '';
						$output['enable_wizard_logo'] = '';
				} else {
					$input['enable_wizard_logo']  = WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_logo', false );
					$output['enable_wizard_logo'] = WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_logo', false );
				}
			}


			if ( isset( $input['login_custom_css'] ) && ! empty( $input['login_custom_css'] ) ) {
				if ( preg_match( '#</?\w+#', $input['login_custom_css'] ) ) {
					add_settings_error(
						WP_2FA_SETTINGS_NAME,
						\esc_attr( 'markup_invalid_settings_error' ),
						\esc_html__( 'Markup is not allowed in Login area CSS.', 'wp-2fa' ),
						'error'
					);
					$output['login_custom_css'] = WP2FA::get_wp2fa_white_label_setting( 'login_custom_css', false );
					$input['login_custom_css']  = WP2FA::get_wp2fa_white_label_setting( 'login_custom_css', false );
				} else {
					$output['login_custom_css'] = \wp_strip_all_tags( $input['login_custom_css'] );
					$input['login_custom_css']  = \wp_strip_all_tags( $input['login_custom_css'] );
				}
			}

			if ( isset( $input['disable_login_css'] ) && '' !== trim( (string) $input['disable_login_css'] ) ) {
				$output['disable_login_css'] = \wp_strip_all_tags( $input['disable_login_css'] );
			} else {
				// Nothing was POSTed, check where we are in case that means we simple an empty/disabled checkbox.
				if ( $request_area_path && ! strpos( $request_area['query'], 'method_selection' ) ) {
					$input['disable_login_css']  = WP2FA::get_wp2fa_white_label_setting( 'disable_login_css', false );
					$output['disable_login_css'] = WP2FA::get_wp2fa_white_label_setting( 'disable_login_css', false );
				} else {
					$output['disable_login_css'] = '';
					$input['disable_login_css']  = '';
				}
			}

			// Remove duplicates from settings errors. We do this as this sanitization callback is actually fired twice, so we end up with duplicates when saving the settings for the FIRST TIME only. The issue is not present once the settings are in the DB as the sanitization wont fire again. For details on this core issue - https://core.trac.wordpress.org/ticket/21989.
			global $wp_settings_errors;
			if ( isset( $wp_settings_errors ) ) {
				$errors             = array_map( 'unserialize', array_unique( array_map( 'serialize', $wp_settings_errors ) ) );
				$wp_settings_errors = $errors; // phpcs:ignore
			}

			/**
			 * Filter the values we are about to store in the plugin settings.
			 *
			 * @param array $output - The output array with all the data we will store in the settings.
			 * @param array $input - The input array with all the data we received from the user.
			 *
			 * @since 2.0.0
			 */
			$output = \apply_filters( WP_2FA_PREFIX . 'filter_output_content', $output, $input );

			Debugging::log( 'The following settings are being saved (White Label): ' . "\n" . wp_json_encode( $output ) );

			return $output;
		}

		/**
		 * Updates global white label network options
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 *
		 * @SuppressWarnings(PHPMD.ExitExpressions)
		 */
		public static function update_wp2fa_network_options() {

			if ( isset( $_POST[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] ) ) {
				check_admin_referer( 'wp_2fa_white_label-options' );
				$options         = self::validate_and_sanitize( wp_unslash( $_POST[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] ) ); // phpcs:ignore
				$settings_errors = get_settings_errors( WP_2FA_WHITE_LABEL_SETTINGS_NAME );
				if ( ! empty( $settings_errors ) ) {

					// redirect back to our options page.
					wp_safe_redirect(
						add_query_arg(
							array(
								'page' => 'wp-2fa-settings',
								'wp_2fa_network_settings_error' => urlencode_deep( $settings_errors[0]['message'] ),
							),
							network_admin_url( 'settings.php' )
						)
					);
					exit;

				}
				WP2FA::update_plugin_settings( $options, false, WP_2FA_WHITE_LABEL_SETTINGS_NAME );

				// redirect back to our options page.
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => 'wp-2fa-settings',
							'tab'  => 'white-label-settings',
							'wp_2fa_network_settings_updated' => 'true',
						),
						network_admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}

		/**
		 * Wrapper which adds special tabbed navigation and content
		 *
		 * @return void
		 *
		 * @since 2.3.0
		 */
		private static function white_labelling_tabs_wrapper() {
			/**
			 * Fires right before the white label settings tab HTML, handles tabbed nav.
			 *
			 * @since 2.3.0
			 */
			do_action( WP_2FA_PREFIX . 'white_labeling_tabbed_navigation' );
				self::change_default_text_area();
		}

		/**
		 * Shows default settings input to the user
		 *
		 * @return void
		 *
		 * @since 2.0.0
		 */
		private static function change_default_text_area() {
			/**
			 * Fires right before the white label settings tab HTML rendering.
			 *
			 * @since 2.0.0
			 */
			do_action( WP_2FA_PREFIX . 'white_labeling_settings_page_before_default_text' );
			?>

		<h3><?php \esc_html_e( 'Change the default text used in the 2FA code page', 'wp-2fa' ); ?></h3>
		<p class="description">
			<?php \esc_html_e( 'This is the text shown to the users on the page when they are asked to enter the 2FA code. To change the default text, simply type it in the below placeholder.', 'wp-2fa' ); ?>
		</p>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="2fa-method"><?php \esc_html_e( '2FA code page text', 'wp-2fa' ); ?></label></th>
					<td>
						<?php
						if ( class_exists( 'WP2FA\Extensions\WhiteLabeling\White_Labeling_Render' ) ) {
							echo White_Labeling_Render::get_method_text_editor( 'default-text-code-page' ); // phpcs:ignore
						} else {
							echo self::create_standard_editor( WP2FA::get_wp2fa_white_label_setting( 'default-text-code-page', true ), 'default-text-code-page' );
						}
						?>
						<div style="margin-top: 5px;"><span><strong><i><?php \esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php \esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
					</td>
				</tr>
				<tr>
					<th><label for="backup-method"><?php \esc_html_e( 'Backup code page text', 'wp-2fa' ); ?></label></th>
					<td>
						<?php
						if ( class_exists( 'WP2FA\Extensions\WhiteLabeling\White_Labeling_Render' ) ) {
							echo White_Labeling_Render::get_method_text_editor( 'default-backup-code-page' ); // phpcs:ignore
						} else {
							echo self::create_standard_editor( WP2FA::get_wp2fa_white_label_setting( 'default-backup-code-page', true ), 'default-backup-code-page' );
						}
						?>
						<div style="margin-top: 5px;"><span><strong><i><?php \esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php \esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
					</td>
				</tr>

				<tr>
					<th><label for="backup-method"><?php \esc_html_e( 'Text for logged out users trying to access the 2FA configuration page', 'wp-2fa' ); ?></label></th>
					<td>
						<?php
						if ( class_exists( 'WP2FA\Extensions\WhiteLabeling\White_Labeling_Render' ) ) {
							echo White_Labeling_Render::get_method_text_editor( 'login-to-view-area' ); // phpcs:ignore
						} else {
							echo self::create_standard_editor( WP2FA::get_wp2fa_white_label_setting( 'login-to-view-area', true ), 'login-to-view-area' );
						}
						?>
					</td>
				</tr>

				<?php
				/**
				 * Gives the ability for the 3rd party extensions to add additional white label settings
				 */
				do_action( WP_2FA_PREFIX . 'white_labeling_settings_page_after_code_page' );
				?>
			</tbody>
		</table>
			<h3><?php \esc_html_e( 'Change the styling of the user 2FA wizards', 'wp-2fa' ); ?></h3>
			<p class="description">
				<?php \esc_html_e( 'By default, the user 2FA wizards which the users see and use to set up 2FA have our own styling. Disable the below setting so the wizards use the styling of your website\'s theme.', 'wp-2fa' ); ?>
			</p>
			<table class="form-table">
				<tbody>
					<tr>
						<th><label for="enable_wizard_styling"><?php \esc_html_e( 'Enable styling', 'wp-2fa' ); ?></label></th>
						<td>
							<fieldset>
								<input type="checkbox" id="enable_wizard_styling" name="wp_2fa_white_label[enable_wizard_styling]" value="enable_wizard_styling"
								<?php \checked( 'enable_wizard_styling', WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ), true ); ?>
								>
								<?php \esc_html_e( 'Enable our CSS within user wizards', 'wp-2fa' ); ?>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<?php
			/**
			 * Fires after the white label settings tab is rendered.
			 *
			 * @since 2.0.0
			 */
			do_action( WP_2FA_PREFIX . 'white_labeling_settings_page_after_default_text' );
		}

		/**
		 * Simple function to create a neat text editor in free.
		 *
		 * @param string $content
		 * @param string $requested_slide
		 * @return void
		 */
		private static function create_standard_editor( $content, $requested_slide ) {
			$settings = array(
				'media_buttons' => false,
				'editor_height' => 200,
				'textarea_name' => 'wp_2fa_white_label[' . $requested_slide . ']',
			);

			if ( isset( $content ) ) {
				wp_editor( $content, $requested_slide, $settings );
			}
		}
	}
}
