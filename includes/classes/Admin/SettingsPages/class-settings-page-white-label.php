<?php
/**
 * White label settings class.
 *
 * @package   wp2fa
 * @copyright 2021 WP White Security
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\SettingsPages;

use \WP2FA\WP2FA as WP2FA;
use WP2FA\Utils\Debugging;

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
	public function render() {
		settings_fields( WP_2FA_WHITE_LABEL_SETTINGS_NAME );
		$this->change_default_text_area();
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
	public function validate_and_sanitize( $input ) {

		// Bail if user doesn't have permissions to be here.
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['action'] ) && ! check_admin_referer( 'wp2fa-step-choose-method' ) ) {
			return;
		}

		$output['default-text-code-page'] = WP2FA::get_wp2fa_setting( 'default-text-code-page', false, true );

		if ( isset( $input['default-text-code-page'] ) && '' !== trim( $input['default-text-code-page'] ) ) {
			$output['default-text-code-page'] = \wp_strip_all_tags( $input['default-text-code-page'] );
		}

		$output['default-backup-code-page'] = WP2FA::get_wp2fa_setting( 'default-backup-code-page', false, true );

		if ( isset( $input['default-backup-code-page'] ) && '' !== trim( $input['default-backup-code-page'] ) ) {
			$output['default-backup-code-page'] = \wp_strip_all_tags( $input['default-backup-code-page'] );
		}

		// Remove duplicates from settings errors. We do this as this sanitization callback is actually fired twice, so we end up with duplicates when saving the settings for the FIRST TIME only. The issue is not present once the settings are in the DB as the sanitization wont fire again. For details on this core issue - https://core.trac.wordpress.org/ticket/21989.
		global $wp_settings_errors;
		if ( isset( $wp_settings_errors ) ) {
			$errors             = array_map( 'unserialize', array_unique( array_map( 'serialize', $wp_settings_errors ) ) );
			$wp_settings_errors = $errors; // @codingStandardsIgnoreLine WP $wp_settings_errors assignment
		}

		$log_content = __( 'Settings saving processes complete', 'wp-2fa' );
		Debugging::log( $log_content );

		/**
		 * Filter the values we are about to store in the plugin settings.
		 *
		 * @param array $output - The output array with all the data we will store in the settings.
		 * @param array $input - The input array with all the data we received from the user.
		 *
		 * @since 2.0.0
		 */
		$output = apply_filters( 'wp_2fa_filter_output_content', $output, $input );

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
	public function update_wp2fa_network_options() {

		if ( isset( $_POST[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] ) ) {
			check_admin_referer( 'wp_2fa_white_label-options' );
			$options         = $this->validate_and_sanitize( wp_unslash( $_POST[ WP_2FA_WHITE_LABEL_SETTINGS_NAME ] ) ); // @codingStandardsIgnoreLine - Not sanitized warning
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
			WP2FA::updatePluginSettings( $options, false, WP_2FA_WHITE_LABEL_SETTINGS_NAME );

			// redirect back to our options page.
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                            => 'wp-2fa-settings',
						'tab'                             => 'white-label-settings',
						'wp_2fa_network_settings_updated' => 'true',
					),
					network_admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Shows default settings input to the user
	 *
	 * @return void
	 *
	 * @since 2.0.0
	 */
	private function change_default_text_area() {
		do_action( WP_2FA_PREFIX . 'white_labeling_settings_page_before_default_text' );
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
							<textarea cols="70" rows="10" name="wp_2fa_white_label[default-text-code-page]" id="default-text-code-page"><?php echo \esc_html( WP2FA::get_wp2fa_white_label_setting( 'default-text-code-page', true ) ); ?></textarea>
							<div><span><strong><i><?php esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
						</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th><label for="backup-method"><?php esc_html_e( 'Backup code page text', 'wp-2fa' ); ?></label></th>
					<td>
						<fieldset>
						<label for="default-backup-code-page">
							<textarea cols="70" rows="10" name="wp_2fa_white_label[default-backup-code-page]" id="default-backup-code-page"><?php echo \esc_html( WP2FA::get_wp2fa_white_label_setting( 'default-backup-code-page', true ) ); ?></textarea>
							<div><span><strong><i><?php esc_html_e( 'Note:', 'wp-2fa' ); ?></i></strong> <?php esc_html_e( 'Only plain text is allowed.', 'wp-2fa' ); ?></span></div>
						</label>
						</fieldset>
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
		<?php

		do_action( WP_2FA_PREFIX . 'white_labeling_settings_page_after_default_text' );
	}
}
