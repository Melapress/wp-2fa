<?php
/**
 * Generals settings class.
 *
 * @package   wp2fa
 * @copyright 2021 WP White Security
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\SettingsPages;

use \WP2FA\WP2FA as WP2FA;
use \WP2FA\Utils\Debugging as Debugging;
use WP2FA\Utils\SettingsUtils as SettingsUtils;

/**
 * Settings_Page_General - Class for handling general settings
 *
 * @since 2.0.0
 */
class Settings_Page_General {

	/**
	 * Renders the settings
	 *
	 * @return void
	 *
	 * @since 2.0.0
	 */
	public function render() {
		settings_fields( WP_2FA_SETTINGS_NAME );
		$this->grace_period_frequency();
		$this->limit_settings_access();
		$this->remove_data_upon_uninstall();
		submit_button( null, 'primary', WP_2FA_SETTINGS_NAME . '[submit]' );
	}

	/**
	 * Grace period frequency
	 *
	 * @return void
	 *
	 * @since 2.0.0
	 */
	private function grace_period_frequency() {
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
							<?php checked( 1, WP2FA::get_wp2fa_general_setting( 'enable_grace_cron' ), true ); ?>
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
							<?php checked( 1, WP2FA::get_wp2fa_general_setting( 'enable_destroy_session' ), true ); ?>
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
	 * Limit settings setting
	 *
	 * @return void
	 *
	 * @since 2.0.0
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
							<?php checked( 1, WP2FA::get_wp2fa_general_setting( 'limit_access' ), true ); ?>
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

		$simple_settings_we_can_loop = array(
			'enable_grace_cron',
			'enable_destroy_session',
			'limit_access',
			'delete_data_upon_uninstall',
		);

		$simple_settings_we_can_loop = apply_filters( 'wp_2fa_loop_general_settings', $simple_settings_we_can_loop );

		$settings_to_turn_into_bools = array(
			'enable_grace_cron',
			'enable_destroy_session',
			'limit_access',
			'delete_data_upon_uninstall',
		);

		foreach ( $simple_settings_we_can_loop as $simple_setting ) {
			if ( ! in_array( $simple_setting, $settings_to_turn_into_bools, true ) ) {
				// Is item is not one of our possible settings we want to turn into a bool, process.
				$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? trim( sanitize_text_field( $input[ $simple_setting ] ) ) : false;
			} else {
				// This item is one we treat as a bool, so process correctly.
				$output[ $simple_setting ] = ( isset( $input[ $simple_setting ] ) && ! empty( $input[ $simple_setting ] ) ) ? true : false;
			}
		}

		if ( isset( $input['2fa_settings_last_updated_by'] ) && ! empty( $input['2fa_settings_last_updated_by'] ) ) {
			$policies = WP2FA::get_wp2fa_setting();
			if ( false === $policies ) {
				$policies = WP2FA::getDefaultSettings();
			}
			$policies['2fa_settings_last_updated_by'] = (int) $input['2fa_settings_last_updated_by'];

			WP2FA::updatePluginSettings( $policies );
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
		$output = apply_filters( 'wp_2fa_filter_output_content_general_settings', $output, $input );

		// We have overridden any defaults by now so can clear this.
		SettingsUtils::delete_option( WP_2FA_PREFIX . 'default_settings_applied' );

		return $output;
	}

	/**
	 * Updates global settings network options
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.ExitExpressions)
	 */
	public function update_wp2fa_network_options() {

		if ( isset( $_POST[ WP_2FA_SETTINGS_NAME ] ) ) {
			check_admin_referer( 'wp_2fa_settings-options' );
			$options         = $this->validate_and_sanitize( wp_unslash( $_POST[ WP_2FA_SETTINGS_NAME ] ) ); // @codingStandardsIgnoreLine - Not sanitized warning
			$settings_errors = get_settings_errors( WP_2FA_SETTINGS_NAME );
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
			WP2FA::updatePluginSettings( $options, false, WP_2FA_SETTINGS_NAME );

			// redirect back to our options page.
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                            => 'wp-2fa-settings',
						'tab'                             => 'generic-settings',
						'wp_2fa_network_settings_updated' => 'true',
					),
					network_admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Limit settings setting
	 *
	 * @return void
	 *
	 * @since 2.0.0
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
								<input type="checkbox" id="delete_data" name="wp_2fa_settings[delete_data_upon_uninstall]" value="delete_data_upon_uninstall"
								<?php checked( 1, WP2FA::get_wp2fa_general_setting( 'delete_data_upon_uninstall' ), true ); ?>
								>
								<?php esc_html_e( 'Delete data upon uninstall', 'wp-2fa' ); ?>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
			$last_user_to_update_settings = get_current_user_id();

		?>
		<input type="hidden" id="2fa_main_user" name="wp_2fa_settings[2fa_settings_last_updated_by]" value="<?php echo esc_attr( $last_user_to_update_settings ); ?>">
		<?php
	}
}
