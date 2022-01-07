<?php
/**
 * Settings page render class.
 *
 * @package   wp2fa
 * @copyright 2021 WP White Security
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://wordpress.org/plugins/wp-2fa/
 */

namespace WP2FA\Admin\Views;

use \WP2FA\WP2FA;
use WP2FA\Admin\SettingsPages\{
	Settings_Page_General,
	Settings_Page_White_Label,
	Settings_Page_Email
};

/**
 * Settings_Page_Render - Class for rendering the plugin settings settings
 *
 * @since 2.0.0
 */
class Settings_Page_Render {

	/**
	 * Render the settings
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! empty( WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' ) ) ) {
			$main_user = (int) WP2FA::get_wp2fa_setting( '2fa_settings_last_updated_by' );
		} else {
			$main_user = get_current_user_id();
		}
		?>

		<div class="wrap wp-2fa-settings-wrapper wp2fa-form-styles">
			<h2><?php esc_html_e( 'WP 2FA Settings', 'wp-2fa' ); ?></h2>
			<hr>
			<?php if ( ! empty( WP2FA::get_wp2fa_general_setting( 'limit_access' ) ) && $main_user !== $user->ID ) : ?>
				<?php
				echo esc_html__( 'These settings have been disabled by your site administrator, please contact them for further assistance.', 'wp-2fa' );
				?>
			<?php else : ?>
				<?php do_action( 'wp2fa_before_plugin_settings' ); ?>
				<div class="nav-tab-wrapper">
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page' => 'wp-2fa-settings',
								'tab'  => 'generic-settings',
							),
							network_admin_url( 'admin.php' )
						)
					);
					?>
					" class="nav-tab <?php echo ( ! isset( $_REQUEST['tab']) || isset( $_REQUEST['tab'] ) && 'generic-settings' === $_REQUEST['tab'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General plugin settings', 'wp-2fa' ); // @codingStandardsIgnoreLine - No nonce verification warning?></a>
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
					" class="nav-tab <?php echo ( isset( $_REQUEST['tab'] ) && 'email-settings' === $_REQUEST['tab'] ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Email Settings & Templates', 'wp-2fa' ); // @codingStandardsIgnoreLine - No nonce verification warning?></a>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page' => 'wp-2fa-settings',
								'tab'  => 'white-label-settings',
							),
							network_admin_url( 'admin.php' )
						)
					);
					?>
								" class="nav-tab <?php echo isset( $_REQUEST['tab'] ) && 'white-label-settings' === $_REQUEST['tab'] ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'White labeling', 'wp-2fa' ); // @codingStandardsIgnoreLine - No nonce verification warning?></a>
				</div>
					<?php
					if ( WP2FA::is_this_multisite() ) {
						$action = 'edit.php?action=update_wp2fa_network_options';
					} else {
						$action = 'options.php';
					}
					if ( ! isset( $_REQUEST['tab'] ) || isset( $_REQUEST['tab'] ) && 'generic-settings' === $_REQUEST['tab'] ) : // @codingStandardsIgnoreLine - No nonce verification warning
						?>
					<br/>
						<?php
						printf(
							'<p class="description">%1$s <a href="mailto:support@wpwhitesecurity.com">%2$s</a></p>',
							esc_html__( 'Use the settings below to configure the properties of the two-factor authentication on your website and how users use it. If you have any questions send us an email at', 'wp-2fa' ),
							esc_html__( 'support@wpwhitesecurity.com', 'wp-2fa' )
						);
						?>
					<br/>
						<?php $total_users = count_users(); ?>
					<form id="wp-2fa-admin-settings" action='<?php echo esc_attr( $action ); ?>' method='post' autocomplete="off" data-2fa-total-users="<?php echo esc_attr( $total_users['total_users'] ); ?>">
						<?php
						$settings_general = new Settings_Page_General();
						$settings_general->render();
						?>
					</form>
				<?php endif; ?>
				<?php
				if ( isset( $_REQUEST['tab'] ) && 'white-label-settings' === $_REQUEST['tab'] ) : // @codingStandardsIgnoreLine - No nonce verification warning
					?>
					<br/>
					<?php
					printf(
						'<p class="description">%1$s <a href="mailto:support@wpwhitesecurity.com">%2$s</a></p>',
						esc_html__( 'Use the settings below to configure the properties of the two-factor authentication on your website and how users use it. If you have any questions send us an email at', 'wp-2fa' ),
						esc_html__( 'support@wpwhitesecurity.com', 'wp-2fa' )
					);
					?>
					<br/>
					<?php $total_users = count_users(); ?>
					<form id="wp-2fa-admin-settings" action='<?php echo esc_attr( $action ); ?>' method='post' autocomplete="off" data-2fa-total-users="<?php echo esc_attr( $total_users['total_users'] ); ?>">
						<?php
						$settings_white_label = new Settings_Page_White_Label();
						$settings_white_label->render();
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

				<?php if ( isset( $_REQUEST['tab'] ) && 'email-settings' === $_REQUEST['tab'] ) : // @codingStandardsIgnoreLine - No nonce verification warning?>
					<br/>
					<?php
						printf(
							'<p class="description">%1$s <a href="mailto:support@wpwhitesecurity.com">%2$s</a></p>',
							esc_html__( 'Use the settings below to configure the emails which are sent to users as part of the 2FA plugin. If you have any questions send us an email at', 'wp-2fa' ),
							esc_html__( 'support@wpwhitesecurity.com', 'wp-2fa' )
						);
					?>
					<br/>
					<form action='<?php echo esc_attr( $action ); ?>' method='post' autocomplete="off">
						<?php
							$settings_email = new Settings_Page_Email();
							$settings_email->render();
						?>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
