<?php
/**
 * Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\Admin\Settings_Builder;

?>
<div class="settings-page" id="providers-integrations-wrap">
		<?php
		Settings_Builder::build_option(
			array(
				'parent'       => \esc_html__( 'Settings', 'wp-2fa' ),
				'type'         => 'breadcrumb',
				'custom_class' => 'back-policies-settings-main-wrapper',
				'default'      => \esc_html__( '2FA provider integrations', 'wp-2fa' ),
			)
		);

		Settings_Builder::build_option(
			array(
				'title' => esc_html__( '2FA provider integrations', 'wp-2fa' ),
				'id'    => 'providers-integrations-tab',
				'type'  => 'tab-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
					\esc_html__( 'Manage the integrations for the available 2FA providers. %1$s.', 'wp-2fa' ),
					\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-add-authentication-methods/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_add_supplementary_2fa_methods', \esc_html__( 'Learn more', 'wp-2fa' ) )
				),
				'class' => 'description-settings-card',
				'id'    => 'providers-integrations-tab',
				'type'  => 'description',
			)
		);

		$integration_providers = \apply_filters( WP_2FA_PREFIX . 'integrations_providers_settings_group', array() );

		?>
	<div class="settings-card">

		<!-- Providers List -->
		<div class="providers-container">
			<?php
			foreach ( $integration_providers as $provider_key => $provider ) {
				?>
					<div class="provider-item">
						<div class="provider-summary" onclick="toggleProvider(this)">
							<span class="provider-name"><?php echo esc_html( $provider['provider_name'] ); ?></span>
							<span class="provider-arrow" aria-hidden="true"></span>
						</div>
						<div class="provider-content">
						<?php if ( ! empty( $provider['docs_url'] ) ) : ?>
							<p class="provider-docs-link">
								<?php
								echo \wp_kses(
									\wp_sprintf(
										/* translators: %s: link to the documentation page. */
										\esc_html__( 'Need help setting up this method? %s', 'wp-2fa' ),
										\wp_sprintf(
											'<a href="%s" target="_blank">%s</a>',
											\esc_url( $provider['docs_url'] ),
											\esc_html__( 'Visit docs', 'wp-2fa' )
										)
									),
									array(
										'a' => array(
											'href'   => array(),
											'target' => array(),
										),
									)
								);
								?>
							</p>
						<?php endif; ?>
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
