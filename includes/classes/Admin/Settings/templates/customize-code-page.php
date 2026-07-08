<?php
/**
 * Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Licensing\Licensing_Factory;

?>
	<div class="settings-page" id="customize-code-page-wrap">

				<?php
				Settings_Builder::build_option(
					array(
						'parent'       => \esc_html__( 'White labeling', 'wp-2fa' ),
						'type'         => 'breadcrumb',
						'custom_class' => 'back-policies-settings-main-wrapper',
						'default'      => \esc_html__( 'Customize 2FA code page', 'wp-2fa' ),
					)
				);

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Customize 2FA code page', 'wp-2fa' ),
						'id'    => 'wp-2fa-code-page-settings-tab',
						'type'  => 'tab-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'Customize the messages and styling of the 2FA code page. %1$s.', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=code_page_settings_doc', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'wp-2fa-code-page-settings-tab',
						'type'  => 'description',
					)
				);


				$code_page_tabs = array(
					array(
						'id'    => 'content',
						'title' => esc_html__( 'Edit content', 'wp-2fa' ),
					),
				);

				// @free:start
				$code_page_tabs[] = array(
					'id'     => 'styles',
					'title'  => esc_html__( 'Edit styles', 'wp-2fa' ),
					'locked' => true,
					'premium_context' => 'wp-2fa-white-labeling:customize-code-page-design',
				);
				// @free:end


				Settings_Builder::build_option(
					array(
						'class'       => 'description-settings-card',
						'id'          => 'wp-2fa-code-page-settings-tab',
						'type'        => 'tabbed-navigation',
						'option_name' => 'wp-2fa-code-page-settings-tabs',
						'values'      => $code_page_tabs,
					)
				);
				?>

			
		<!-- ══════════════════════════════════════════════════════
			TAB 1 — 2FA Content
		══════════════════════════════════════════════════════════ -->
		<div class="tab-panel tab-panel-content">
			<?php
			$is_enterprise = Licensing_Factory::has_active_valid_license() && ( Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'business', true ) || Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'ent', true ) || Licensing_Factory::provider_call( 'is_plan_or_trial__premium_only', 'enterprise', true ) );
			$enterprise_plan_url = 'https://melapress.com/wordpress-2fa/pricing/#utm_source=plugin&utm_medium=wp2fa&utm_campaign=white-labeling-page';
			if ( ! $is_enterprise ) :
			?>
			<div class="wp2fa-enterprise-upsell-banner" role="region" aria-label="<?php echo \esc_attr__( 'Enterprise upgrade banner', 'wp-2fa' ); ?>">
				<div class="wp2fa-enterprise-upsell-banner__content">
					<span class="wp2fa-enterprise-upsell-banner__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="33" height="49" viewBox="0 0 33 49" fill="none">
							<path d="M10.7607 16.2846L18.7944 8.17949L26.9017 0H13.414H0V8.17949V16.2846L2.65332 13.6077L5.38035 10.8564L8.03367 13.6077L10.7607 16.2846Z" fill="#99FFFF"/>
							<path d="M32.2094 48.9278V37.997V27.1406L21.5224 37.997L10.7617 48.7791L21.5224 48.8534L32.2094 48.9278Z" fill="#3E6BFF"/>
							<path d="M10.7607 27.1406L8.03367 24.3893L5.38035 21.7124L2.65332 18.9611L0 16.2841V27.1406V37.997L2.65332 35.2457L5.38035 32.5688L8.03367 35.2457L10.7607 37.997L18.7944 29.8175L26.9017 21.7124L29.555 24.3893L32.2084 27.1406V16.2841V5.42773L21.5214 16.2841L10.7607 27.1406Z" fill="#40D3F0"/>
						</svg>
					</span>
					<div class="wp2fa-enterprise-upsell-banner__text">
						<h3 class="wp2fa-enterprise-upsell-banner__title">
							<?php echo \esc_html__( 'Unlock more settings with the', 'wp-2fa' ); ?>
							<span class="wp2fa-enterprise-upsell-banner__highlight"><?php echo \esc_html__( 'Enterprise version', 'wp-2fa' ); ?></span>
						</h3>
						<p class="wp2fa-enterprise-upsell-banner__desc"><?php echo \esc_html__( 'Get access to even more features with Enterprise version of WP2FA', 'wp-2fa' ); ?></p>
					</div>
				</div>
				<a class="wp2fa-enterprise-upsell-banner__button" href="<?php echo \esc_url( $enterprise_plan_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo \esc_html__( 'View Enterprise plan', 'wp-2fa' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<div class="settings-card">
				<?php
				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Customize content', 'wp-2fa' ),
						'id'    => 'general-settings-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'Use these settings to customize messages shown to users upon login when a 2FA verification code is requested', 'wp-2fa' ),
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
					)
				);

					$customized_content_dialogs = array(
						'default-text-code-page' => array(
							'label'       => \esc_html__( '2FA code page text', 'wp-2fa' ),
							'description' => \esc_html__( 'The main message shown on the 2FA login screen, prompting the user to enter their verification code.', 'wp-2fa' ),
							'note'        => \esc_html__( 'Only plain text is allowed.', 'wp-2fa' ),
						),
					);

					$section_open = true;
					foreach ( $customized_content_dialogs as $setting_key => $setting_options ) {
						if ( isset( $setting_options['section'] ) ) {
							if ( $section_open ) {
								echo '</div>'; // Close previous settings-card.
							}
							$section_open = true;
							?>
							<div class="settings-card">
								<?php
								Settings_Builder::build_option(
									array(
										'title' => $setting_options['section'],
										'id'    => \esc_attr( $setting_key ),
										'type'  => 'section-title',
									)
								);
								?>
							<?php
							continue;
						}
						?>
						<div class="form-group settings-row">
							<div class="settings-label-group">
							<?php
							Settings_Builder::build_option(
								array(
									'text' => $setting_options['label'],
									'id'   => 'general-settings-tab',
									'type' => 'settings-label',
								)
							);

							if ( isset( $setting_options['description'] ) ) {
								Settings_Builder::build_option(
									array(
										'text'  => $setting_options['description'],
										'class' => 'description-settings-card',
										'id'    => 'general-settings-tab',
										'type'  => 'description',
									)
								);
							}
							?>
							</div>
							<div class="settings-control">
							<?php
							$setting = array(
								'id'          => $setting_key,
								'type'        => ( isset( $setting_options['type'] ) && trim( $setting_options['type'] ) ) ? $setting_options['type'] : 'editor',
								'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
								'class'       => 'form-input',
								'option_name' => 'wp_2fa_white_label[' . $setting_key . ']',
								'default'     => WP2FA::get_wp2fa_white_label_setting( sanitize_key( $setting_key ), true ),
							);
							if ( isset( $setting_options['note'] ) ) {
								$setting['hint'] = $setting_options['note'];
							}

							Settings_Builder::build_option(
								$setting
							);
							?>
							</div>
						</div>
						<?php
					}
					?>

				<?php
					$customized_content_dialogs = array(
						'default-backup-code-page'     => array(
							'label'       => \esc_html__( 'Backup code page text', 'wp-2fa' ),
							'description' => \esc_html__( 'Shown on the backup-code screen, asking the user to enter one of their backup codes.', 'wp-2fa' ),
							'note'        => \esc_html__( 'Only plain text is allowed.', 'wp-2fa' ),
						),
						'login-to-view-area'           => array(
							'label'       => \esc_html__( 'Text for logged out users trying to access the 2FA configuration page', 'wp-2fa' ),
							'description' => \esc_html__( 'Shown to logged-out users who open the 2FA setup page, prompting them to log in.', 'wp-2fa' ),
						),
					);

					if ( $is_enterprise ) {
						$customized_content_dialogs = array_merge(
							array(
								'backup-email-link-login-text' => array(
									'label'       => \esc_html__( 'Send backup code via email link text', 'wp-2fa' ),
									'description' => \esc_html__( 'This text is shown on the 2FA code page as the link to send a backup code via email.', 'wp-2fa' ),
								),
								'backup-codes-login-text'      => array(
									'label'       => \esc_html__( 'Use backup code link text', 'wp-2fa' ),
									'description' => \esc_html__( 'This text is shown on the 2FA code page as the link to use a backup code instead.', 'wp-2fa' ),
								),
							),
							$customized_content_dialogs
						);
					}

					/**
					 * Filter to add more customizable content dialogs on the 2FA code page. Each dialog should have a unique setting key and an array of options which should include:
					 * 'label' (string) - The label for the dialog, shown in the settings page.
					 * 'description' (string) - A description for the dialog, shown in the settings page.
					 * 'type' (string) - The type of input to use in the settings page. Can be 'text' or 'editor'. Default is 'editor'.
					 * 'note' (string) - A note about the dialog, shown in the settings page.
					 */
					$customized_content_dialogs = \apply_filters( WP_2FA_PREFIX . 'customizable_content_dialogs', $customized_content_dialogs );

					if ( ! $is_enterprise ) {
						$allowed_non_enterprise_keys = array(
							'default-text-code-page',
							'default-backup-code-page',
							'login-to-view-area',
						);
						$customized_content_dialogs = array_intersect_key( $customized_content_dialogs, array_flip( $allowed_non_enterprise_keys ) );
					}

					$section_open = true;
					foreach ( $customized_content_dialogs as $setting_key => $setting_options ) {
						if ( isset( $setting_options['section'] ) ) {
							if ( $section_open ) {
								echo '</div>'; // Close previous settings-card.
							}
							$section_open = true;
							?>
							<div class="settings-card">
								<?php
								Settings_Builder::build_option(
									array(
										'title' => $setting_options['section'],
										'id'    => \esc_attr( $setting_key ),
										'type'  => 'section-title',
									)
								);
								?>
							<?php
							continue;
						}
						?>
						<div class="form-group settings-row">
							<div class="settings-label-group">
							<?php
							Settings_Builder::build_option(
								array(
									'text' => $setting_options['label'],
									'id'   => 'general-settings-tab',
									'type' => 'settings-label',
								)
							);

							if ( isset( $setting_options['description'] ) ) {
								Settings_Builder::build_option(
									array(
										'text'  => $setting_options['description'],
										'class' => 'description-settings-card',
										'id'    => 'general-settings-tab',
										'type'  => 'description',
									)
								);
							}
							?>
							</div>
							<div class="settings-control">
							<?php
							$setting = array(
								'id'          => $setting_key,
								'type'        => ( isset( $setting_options['type'] ) && trim( $setting_options['type'] ) ) ? $setting_options['type'] : 'editor',
								'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
								'class'       => 'form-input',
								'option_name' => 'wp_2fa_white_label[' . $setting_key . ']',
								'default'     => WP2FA::get_wp2fa_white_label_setting( sanitize_key( $setting_key ), true ),
							);
							if ( isset( $setting_options['note'] ) ) {
								$setting['hint'] = $setting_options['note'];
							}

							Settings_Builder::build_option(
								$setting
							);
							?>
							</div>
						</div>
						<?php
					}
					?>
			</div>

		</div><!-- /.tab-panel-settings -->


		<!-- ══════════════════════════════════════════════════════
			TAB 2 — 2FA Styles
		══════════════════════════════════════════════════════════ -->
	</div>
<style>
	.settings-toggleable {
		display: none;
	}
</style>
