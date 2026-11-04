<?php
/**
 * Advanced Settings Template
 *
 * @package wp-2fa
 *
 * @since 4.3.0
 */

defined( 'ABSPATH' ) || exit;

use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\SettingsPages\Setup_Wizard_New;

?>
	<div class="settings-page" id="advanced-settings-wrap">

		<?php
		Settings_Builder::build_option(
			array(
				'parent'       => \esc_html__( 'Settings', 'wp-2fa' ),
				'type'         => 'breadcrumb',
				'custom_class' => 'back-policies-settings-main-wrapper',
				'default'      => \esc_html__( 'Advanced settings', 'wp-2fa' ),
			)
		);

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Advanced settings', 'wp-2fa' ),
				'id'    => 'advanced-settings-tab',
				'type'  => 'tab-title',
			)
		);
		?>

		<div class="settings-card">
			<div class="advanced-settings-section">
				<div class="section-content">
					<h3 class="section-heading"><?php \esc_html_e( 'Setup wizard', 'wp-2fa' ); ?></h3>
					<p class="section-description">
						<?php \esc_html_e( 'Run the setup wizard again to review the 2FA methods, policies, excluded users and grace period. It starts from your current settings, which are only saved when you finish it.', 'wp-2fa' ); ?>
					</p>
				</div>
				<div class="section-actions">
					<a href="<?php echo \esc_url( Setup_Wizard_New::launch_url() ); ?>" class="button button-primary" id="launch-setup-wizard-btn"><?php \esc_html_e( 'Launch setup wizard', 'wp-2fa' ); ?></a>
				</div>
			</div>
		</div>

	</div>

	<style>
	.advanced-settings-section {
		display: flex;
		gap: 30px;
		align-items: flex-start;
	}

	.advanced-settings-section .section-content {
		flex: 1;
	}

	.advanced-settings-section .section-heading {
		margin: 0 0 12px 0;
		font-size: 16px;
		font-weight: 600;
		color: #333;
	}

	.advanced-settings-section .section-description {
		margin: 0;
		font-size: 14px;
		color: #666;
		line-height: 1.5;
	}

	.advanced-settings-section .section-actions {
		flex-shrink: 0;
	}

	#advanced-settings-wrap .button {
		font-size: 14px;
		padding: 8px 16px;
		border-radius: 4px;
		white-space: nowrap;
	}

	@media screen and (max-width: 600px) {
		.advanced-settings-section {
			flex-direction: column;
			gap: 12px;
		}
	}
	</style>
