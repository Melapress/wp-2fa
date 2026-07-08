<?php
/**
 * Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;

?>
	<div class="settings-page" id="customize-setup-wizard-wrap">

				<?php
				Settings_Builder::build_option(
					array(
						'parent'       => \esc_html__( 'White labeling', 'wp-2fa' ),
						'type'         => 'breadcrumb',
						'custom_class' => 'back-policies-settings-main-wrapper',
						'default'      => \esc_html__( 'Customize 2FA setup wizard', 'wp-2fa' ),
					)
				);
				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Customize 2FA setup wizard', 'wp-2fa' ),
						'id'    => 'wp-2fa-setup-wizard-settings-tab',
						'type'  => 'tab-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'Customize the messages and styling of the 2FA setup wizard. %1$s.', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=setup_wizard_settings_doc', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'wp-2fa-setup-wizard-settings-tab',
						'type'  => 'description',
					)
				);

				Settings_Builder::build_option(
					array(
						'class'       => 'description-settings-card',
						'id'          => 'wp-2fa-setup-wizard-settings-tab',
						'type'        => 'tabbed-navigation',
						'option_name' => 'wp-2fa-setup-wizard-settings-tabs',
						'values'      => array(
							array(
								'id'    => 'welcome-message',
								'title' => \esc_html__( 'Welcome Message', 'wp-2fa' ),
							),
							array(
								'id'    => 'wp-2fa-setup-settings',
								'title' => \esc_html__( '2FA Setup', 'wp-2fa' ),
							),
							array(
								'id'    => 'backup-method',
								'title' => \esc_html__( 'Backup Method', 'wp-2fa' ),
							),
							array(
								'id'    => 'reconfiguration',
								'title' => \esc_html__( 'Reconfiguration', 'wp-2fa' ),
							),
							array(
								'id'    => 'customize-styles',
								'title' => \esc_html__( 'Customize Styles', 'wp-2fa' ),
							),
						),
					)
				);
				?>

		<!-- ══════════════════════════════════════════════════════
			TAB 1 — Welcome Message
		══════════════════════════════════════════════════════════ -->
		<div class="tab-panel tab-panel-welcome-message">

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Welcome, initial and generic messages', 'wp-2fa' ),
						'id'    => 'general-settings-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'You can add an extra slide at the start of the 2FA setup wizard. It will be the first slide users see when they launch the wizard to set up 2FA for their account. %1$s', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-customize-user-2fa-experience/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_customize_2fa_user_experience', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
					)
				);
				?>

				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Enable "Welcome & initial message"', 'wp-2fa' ),
							'id'   => 'welcome-label',
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'enable_welcome',
								'value'       => 'enable_welcome',
								'type'        => 'checkbox',
								'text'        => \esc_html__( 'Enable to display optional welcome message.', 'wp-2fa' ),
								'option_name' => 'wp_2fa_white_label[enable_welcome]',
								'not_bool'    => true,
								'default'     => WP2FA::get_wp2fa_white_label_setting( 'enable_welcome', true ),
							)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Optional welcome content', 'wp-2fa' ),
						'id'   => 'welcome-content-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'Use the below editor to enter the text that is to be used in the first slide.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'welcome-content-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'welcome',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[welcome]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'welcome', true ),
						)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->

			<div class="settings-card">
				<?php
				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Other generic wizard steps', 'wp-2fa' ),
						'id'    => 'generic-wizard-steps-title',
						'type'  => 'section-title',
					)
				);
				?>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Prompt when user tries to cancel the wizard', 'wp-2fa' ),
						'id'   => 'cancel-wizard-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'Use the below editor to enter the text that is to be used when user closes/cancels the wizard.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'cancel-wizard-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'wp-2fa_wizard_cancel',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[wp-2fa_wizard_cancel]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'wp-2fa_wizard_cancel', true ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Wizard completion &ndash; Backup codes not available', 'wp-2fa' ),
						'id'   => 'backup-codes-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when setup is complete and backup codes are not available.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-intro-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_codes_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_codes_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro', true ),
						)
						);
						?>
					</div>
				</div>

			</div><!-- /.settings-card -->
		</div><!-- /.tab-panel-welcome-message -->

		<!-- ══════════════════════════════════════════════════════
			TAB 2 — 2FA Setup
		══════════════════════════════════════════════════════════ -->
		<div class="tab-panel tab-panel-wp-2fa-setup-settings">

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => \esc_html__( 'Customize 2FA Setup labels and messages', 'wp-2fa' ),
						'id'    => 'general-settings-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
							\esc_html__( 'Customize the labels and messages shown during the 2FA setup flow. %1$s', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-customize-user-2fa-experience/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_customize_2fa_user_experience', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
					)
				);
				?>

				<div class="settings-card">
					<!-- Providers List -->
					<div class="providers-container">
						<div class="provider-item">
							<div class="provider-summary" onclick="toggleProvider(this)">
								<span class="provider-name"><?php echo \esc_html__( 'General Labels', 'wp-2fa' ); ?></span>
								<span class="provider-arrow" aria-hidden="true"></span>
							</div>
							<div class="provider-content">

				<!-- <div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						// Settings_Builder::build_option(
						// 	array(
						// 		'text' => \esc_html__( '2FA required', 'wp-2fa' ),
						// 		'id'   => 'wp-2fa-required-label',
						// 		'type' => 'settings-label',
						// 	)
						// );

						// Settings_Builder::build_option(
						// 	array(
						// 		'text'  => \esc_html__( 'This message is shown to users when logging in when 2FA is required.', 'wp-2fa' ),
						// 		'class' => 'description-settings-card',
						// 		'type'  => 'description',
						// 	)
						// );
						?>
					</div>
					<div class="settings-control">
						<?php
						// Settings_Builder::build_option(
						// 	array(
						// 		'id'          => 'wp-2fa_required_intro',
						// 		'type'        => 'editor',
						// 		'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						// 		'option_name' => 'wp_2fa_white_label[wp-2fa_required_intro]',
						// 		'default'     => WP2FA::get_wp2fa_white_label_setting( 'wp-2fa_required_intro', true ),
						// 	)
						// );
						?>
					</div>
				</div> -->

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( '2FA method selection', 'wp-2fa' ),
						'id'   => 'wp-2fa-required-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when configuring 2FA with no previous configuration.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
						<span class="extra-text"><?php echo '<strong>{available_methods_count}</strong> <i>'. \esc_html__( 'this displays the number of 2FA methods available.', 'wp-2fa' ).'</i>'; ?></span>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'method_selection',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[method_selection]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'method_selection', true ),
						)
						);
						?>
					</div>
				</div>

							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Method selection label', 'wp-2fa' ),
						'id'    => 'general-settings-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
						// translators: 1. Link to documentation, 2. Link to support.
							\esc_html__( 'Customize the label shown for each method on the 2FA method-selection screen.', 'wp-2fa' ),
						),
						'class' => 'description-settings-card',
						'id'    => 'general-settings-tab',
						'type'  => 'description',
					)
				);

				$integration_providers = \apply_filters( WP_2FA_PREFIX . 'providers_settings_labels', array() );

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
		</div><!-- /.tab-panel-wp-2fa-setup-settings -->

		<!-- ══════════════════════════════════════════════════════
			TAB 3 — Backup Method
		══════════════════════════════════════════════════════════ -->
		<div class="tab-panel tab-panel-backup-method">

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Backup 2FA methods &amp; final steps', 'wp-2fa' ),
						'id'    => 'backup-method-settings-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'Here you can customize the messages shown to users once setup is complete.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-method-settings-tab',
						'type'  => 'description',
					)
				);
				?>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Choose backup method', 'wp-2fa' ),
						'id'   => 'backup-codes-intro-multi-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when more than one backup method is available.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-intro-multi-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_codes_intro_multi',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_codes_intro_multi]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro_multi', true ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Wizard completion &ndash; Backup codes optional', 'wp-2fa' ),
						'id'   => 'backup-codes-intro-continue-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when setup is complete and backup codes are optional.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-intro-continue-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_codes_intro_continue',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_codes_intro_continue]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_codes_intro_continue', true ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Backup email intro', 'wp-2fa' ),
						'id'   => 'backup-email-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when email 2FA backup is available.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-email-intro-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_email_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_email_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_email_intro', true ),
						)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Backup codes generation', 'wp-2fa' ),
						'id'    => 'backup-codes-generation-tab',
						'type'  => 'section-title',
					)
				);
				?>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Backup codes generation intro', 'wp-2fa' ),
						'id'   => 'backup-codes-generate-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users prior to generation of backup codes.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-generate-intro-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_codes_generate_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_codes_generate_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_codes_generate_intro', true ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Backup codes generated', 'wp-2fa' ),
						'id'   => 'backup-codes-generated-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when backup codes have been generated.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-generated-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_codes_generated',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_codes_generated]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_codes_generated', true ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Backup email radio select option', 'wp-2fa' ),
						'id'   => 'backup-email-select-method-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when backup email method is shown for selection.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-email-select-method-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup-email-select-method',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup-email-select-method]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup-email-select-method', true ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Backup code radio select option', 'wp-2fa' ),
						'id'   => 'backup-codes-select-method-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when backup code method is shown for selection.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-select-method-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_codes-select-method',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_codes-select-method]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_codes-select-method', true ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Backup codes learn more link', 'wp-2fa' ),
						'id'   => 'backup-codes-learn-more-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This link is shown to users next to the "Generate list of backup codes" button when no backup codes are available.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-learn-more-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'backup_codes_learn_more',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom link HTML', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[backup_codes_learn_more]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'backup_codes_learn_more', true ),
						)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Wizard completion', 'wp-2fa' ),
						'id'    => 'backup-wizard-completion-tab',
						'type'  => 'section-title',
					)
				);
				?>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Wizard completion', 'wp-2fa' ),
						'id'   => 'no-further-action-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when 2FA has been configured and no further actions are available.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'no-further-action-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'no_further_action',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[no_further_action]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'no_further_action', true ),
						)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->
		</div><!-- /.tab-panel-backup-method -->


		<!-- ══════════════════════════════════════════════════════
			TAB 4 — Reconfiguration
		══════════════════════════════════════════════════════════ -->
		<div class="tab-panel tab-panel-reconfiguration">

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( '2FA method reconfiguration', 'wp-2fa' ),
						'id'    => 'reconfiguration-settings-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'From here you can customize the wizard text shown to users when they already have 2FA configured and they want to reconfigure 2FA from their user profile page.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'reconfiguration-settings-tab',
						'type'  => 'description',
					)
				);
				?>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'Reconfigure %s intro', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
						),
						'id'   => 'totp-reconfigure-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'This message is shown to users when reconfiguring %s.', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'totp-option-label', true )
						),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'totp_reconfigure_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[totp_reconfigure_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'totp_reconfigure_intro', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'Reconfigure %s intro', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'email-option-label', true )
						),
						'id'   => 'hotp-reconfigure-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'This message is shown to users when reconfiguring %s.', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'email-option-label', true )
						),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'hotp_reconfigure_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[hotp_reconfigure_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'hotp_reconfigure_intro', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Reconfigure Authy 2FA service intro', 'wp-2fa' ),
						'id'   => 'authy-reconfigure-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when reconfiguring Authy 2FA service.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'authy_reconfigure_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[authy_reconfigure_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'authy_reconfigure_intro', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Authy &ndash; Service unavailable', 'wp-2fa' ),
						'id'   => 'authy-reconfigure-intro-unavailable-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when reconfiguring Authy 2FA service, but the service is unavailable.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'authy_reconfigure_intro_unavailable',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[authy_reconfigure_intro_unavailable]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'authy_reconfigure_intro_unavailable', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Reconfigure Twilio 2FA service intro', 'wp-2fa' ),
						'id'   => 'twilio-reconfigure-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when reconfiguring Twilio 2FA service.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'twilio_reconfigure_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[twilio_reconfigure_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'twilio_reconfigure_intro', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Twilio &ndash; Service unavailable', 'wp-2fa' ),
						'id'   => 'twilio-reconfigure-intro-unavailable-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when reconfiguring Twilio 2FA service, but the SMS service is unavailable.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'twilio_reconfigure_intro_unavailable',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[twilio_reconfigure_intro_unavailable]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'twilio_reconfigure_intro_unavailable', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Reconfigure Clickatell 2FA service intro', 'wp-2fa' ),
						'id'   => 'clickatell-reconfigure-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when reconfiguring Clickatell 2FA service.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'clickatell_reconfigure_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[clickatell_reconfigure_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'clickatell_reconfigure_intro', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Clickatell &ndash; Service unavailable', 'wp-2fa' ),
						'id'   => 'clickatell-reconfigure-intro-unavailable-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when reconfiguring Clickatell 2FA service, but the SMS service is unavailable.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'clickatell_reconfigure_intro_unavailable',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[clickatell_reconfigure_intro_unavailable]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'clickatell_reconfigure_intro_unavailable', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'Reconfigure %s intro', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'oob-option-label', true )
						),
						'id'   => 'oob-reconfigure-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'This message is shown to users when reconfiguring %s.', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'oob-option-label', true )
						),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'oob_reconfigure_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[oob_reconfigure_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'oob_reconfigure_intro', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( '%s &ndash; Service unavailable', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'oob-option-label', true )
						),
						'id'   => 'oob-reconfigure-intro-unavailable-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'This message is shown to users when reconfiguring %s, but the service is unavailable.', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'oob-option-label', true )
						),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'oob_reconfigure_intro_unavailable',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[oob_reconfigure_intro_unavailable]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'oob_reconfigure_intro_unavailable', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'Reconfigure 2FA with %s intro', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'yubico-option-label', true )
						),
						'id'   => 'yubico-reconfigure-intro-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \wp_sprintf(
						// translators: Method option label.
						\esc_html__( 'This message is shown to users when configuring or reconfiguring 2FA with %s.', 'wp-2fa' ),
						WP2FA::get_wp2fa_white_label_setting( 'yubico-option-label', true )
						),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'yubico_reconfigure_intro',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[yubico_reconfigure_intro]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'yubico_reconfigure_intro', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'YubiKey &ndash; Service unavailable', 'wp-2fa' ),
						'id'   => 'yubico-reconfigure-intro-unavailable-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'This message is shown to users when reconfiguring YubiKey 2FA, but the service is unavailable.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
						array(
						'id'          => 'yubico_reconfigure_intro_unavailable',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'option_name' => 'wp_2fa_white_label[yubico_reconfigure_intro_unavailable]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'yubico_reconfigure_intro_unavailable', true ),
						'legend'      => array( 'reconfigure_or_configure_capitalized', 'reconfigure_or_configure' ),
						)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->
		</div><!-- /.tab-panel-reconfiguration -->

		<!-- ══════════════════════════════════════════════════════
			TAB 5 — Customize Styles
		══════════════════════════════════════════════════════════ -->
		<div class="tab-panel tab-panel-customize-styles">

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Change the styling of the user 2FA wizards', 'wp-2fa' ),
						'id'    => 'customize-styles-styling-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'By default, the user 2FA wizards which the users see and use to set up 2FA have our own styling. Disable the below setting so the wizards use the styling of your website\'s theme.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'customize-styles-styling-tab',
						'type'  => 'description',
					)
				);
				?>

				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Enable styling', 'wp-2fa' ),
							'id'   => 'enable-wizard-styling-label',
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'enable_wizard_styling',
								'value'       => 'enable_wizard_styling',
								'type'        => 'checkbox',
								'text'        => \esc_html__( 'Enable our CSS within user wizards', 'wp-2fa' ),
								'option_name' => 'wp_2fa_white_label[enable_wizard_styling]',
								'not_bool'    => true,
								'default'     => WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling', false ),
							)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Display logo in the user 2FA wizards', 'wp-2fa' ),
						'id'    => 'customize-styles-logo-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
							// translators: Link to logo settings page.
							\esc_html__( 'Enable this setting to display your logo in the user 2FA wizards. To provide a logo, please use the Logo settings on the %s.', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" onclick="location.href=this.href;location.reload();return false;">%s</a>', \esc_url( \network_admin_url( 'admin.php?page=wp-2fa-white-labeling#section=customize-code-page&tab=styles' ) ), \esc_html__( '2FA Code page design settings', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'customize-styles-logo-tab',
						'type'  => 'description',
					)
				);
				?>

				<?php
				$wizard_logo_is_set = '' !== trim( (string) WP2FA::get_wp2fa_white_label_setting( 'logo-code-page', false ) );
				?>

				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Display logo', 'wp-2fa' ),
							'id'   => 'enable-wizard-logo-label',
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'enable_wizard_logo',
								'value'       => 'enable_wizard_logo',
								'hint'        => $wizard_logo_is_set
									? \esc_html__( 'Shows at max height of 50px.', 'wp-2fa' )
									: \esc_html__( 'To enable this option, first set a logo on the 2FA Code page design settings.', 'wp-2fa' ),
								'type'        => 'checkbox',
								'text'        => \esc_html__( 'Display logo on user 2FA modals', 'wp-2fa' ),
								'option_name' => 'wp_2fa_white_label[enable_wizard_logo]',
								'not_bool'    => true,
								'default'     => WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_logo', false ),
								'disabled'    => ! $wizard_logo_is_set,
							)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Help icon', 'wp-2fa' ),
						'id'    => 'customize-styles-help-icon-tab',
						'type'  => 'section-title',
					)
				);
				?>

				<div class="form-group settings-row">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Display a help icon in the 2FA setup wizard', 'wp-2fa' ),
							'id'   => 'show-help-text-label',
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'show_help_text',
								'value'       => 'show_help_text',
								'type'        => 'checkbox',
								'text'        => \esc_html__( 'Display a help icon in the setup wizard that provides additional guidance when clicked for users configuring 2FA with different methods.', 'wp-2fa' ),
								'option_name' => 'wp_2fa_white_label[show_help_text]',
								'not_bool'    => true,
								'default'     => WP2FA::get_wp2fa_white_label_setting( 'show_help_text', true ),
							)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->

			<div class="settings-card">
				<?php

				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Custom CSS &amp; Styling', 'wp-2fa' ),
						'id'    => 'customize-styles-css-tab',
						'type'  => 'section-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'In this section you can add your own styling to the user 2FA setup wizard via CSS. For your reference we have included a number of easy to use selectors. They are listed below the Custom CSS placeholder.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'customize-styles-css-tab',
						'type'  => 'description',
					)
				);
				?>

				<div class="form-group settings-row">
					<div class="settings-label-group">
						<?php
						Settings_Builder::build_option(
						array(
						'text' => \esc_html__( 'Custom CSS', 'wp-2fa' ),
						'id'   => 'custom-css-label',
						'type' => 'settings-label',
						)
						);

						Settings_Builder::build_option(
						array(
						'text'  => \esc_html__( 'Note: Only plain-text CSS is allowed. Avoid using "!important" unless absolutely necessary, as it makes styles harder to override later.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'custom-css-desc',
						'type'  => 'description',
						)
						);
						?>
					</div>
					<div class="settings-control">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'custom_css',
								'type'        => 'textarea',
								'option_name' => 'wp_2fa_white_label[custom_css]',
								'default'     => WP2FA::get_wp2fa_white_label_setting( 'custom_css', true ),
							)
						);

						Settings_Builder::build_option(
							array(
								'text'  => \wp_sprintf(
									'<strong>.wp-2fa-button-primary</strong> &mdash; %s<br><strong>.wp-2fa-button-secondary</strong> &mdash; %s<br><strong>.wp2fa-modal h3</strong> &mdash; %s<br><strong>.wp2fa-modal p</strong> &mdash; %s',
									\esc_html__( 'Primary button selector', 'wp-2fa' ),
									\esc_html__( 'Secondary button selector', 'wp-2fa' ),
									\esc_html__( 'Common heading text (unless altered with custom markup)', 'wp-2fa' ),
									\esc_html__( 'Common content text (unless altered with custom markup)', 'wp-2fa' )
								),
								'class' => 'description-settings-card',
								'type'  => 'description',
							)
						);
						?>
					</div>
				</div>
			</div><!-- /.settings-card -->
		</div><!-- /.tab-panel-customize-styles -->
	</div><!-- /.settings-page -->
