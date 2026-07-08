<?php
/**
 * Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\Admin\Settings_Builder;

?>
<div class="settings-page" id="plugins-integrations-wrap">
			<?php
			Settings_Builder::build_option(
				array(
					'parent'       => \esc_html__( 'Settings', 'wp-2fa' ),
					'type'         => 'breadcrumb',
					'custom_class' => 'back-policies-settings-main-wrapper',
					'default'      => \esc_html__( 'Plugin integrations', 'wp-2fa' ),
				)
			);

			Settings_Builder::build_option(
				array(
					'title' => \esc_html__( 'Plugin integrations', 'wp-2fa' ),
					'id'    => 'plugins-integrations-tab',
					'type'  => 'tab-title',
				)
			);

			Settings_Builder::build_option(
				array(
					'text'  => \wp_sprintf(
					// translators: 1. Link to documentation, 2. Link to support.
						\esc_html__( 'Configure available integrations for WordPress plugins. %1$s.', 'wp-2fa' ),
						\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/woocommerce-2fa/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_woocommerce_2fa', \esc_html__( 'Learn more', 'wp-2fa' ) )
					),
					'class' => 'description-settings-card',
					'id'    => 'plugins-integrations-tab',
					'type'  => 'description',
				)
			);

			$integration_plugins = \apply_filters( WP_2FA_PREFIX . 'integrations_plugins_settings_group', array() );

			?>
		<div class="settings-card">

			<!-- Providers List -->
			<div class="providers-container">
				<?php
				foreach ( $integration_plugins as $provider_key => $provider ) {
					?>
						<div class="provider-item">
							<div class="provider-summary" onclick="toggleProvider(this)">
								<span class="provider-name"><?php echo \esc_html( $provider['provider_name'] ); ?></span>
								<span class="provider-arrow" aria-hidden="true"></span>
							</div>
							<div class="provider-content  <?php echo \esc_attr( $provider['disabled'] ); ?>">
							<?php
							if ( isset( $provider['content'] ) ) {
								echo $provider['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
							</div>
						</div>
						<?php
				}
				?>
			</div>
		</div>
</div>
