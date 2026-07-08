<?php
/**
 * Settings Template – Customize 2FA user prompts and notifications.
 *
 * @package wp-2fa
 *
 * @since 4.0.0
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;

?>
	<div class="settings-page" id="customize-user-prompts-wrap">

				<?php
				Settings_Builder::build_option(
					array(
						'parent'       => \esc_html__( 'White labeling', 'wp-2fa' ),
						'type'         => 'breadcrumb',
						'custom_class' => 'back-policies-settings-main-wrapper',
						'default'      => \esc_html__( 'Customize 2FA user prompts and notifications', 'wp-2fa' ),
					)
				);
				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Customize 2FA user prompts and notifications', 'wp-2fa' ),
						'id'    => 'wp-2fa-user-prompts-settings-tab',
						'type'  => 'tab-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
							\esc_html__( 'Customize the prompts and notifications users see when 2FA is required or must be reconfigured. %1$s.', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-customize-user-2fa-experience/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_customize_2fa_user_experience', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'wp-2fa-user-prompts-settings-tab',
						'type'  => 'description',
					)
				);
				?>

		<div class="settings-card">
			<?php
			// Settings_Builder::build_option(
			// 	array(
			// 		'title' => esc_html__( 'Customize 2FA user prompts and notifications', 'wp-2fa' ),
			// 		'id'    => 'user-prompts-section',
			// 		'type'  => 'section-title',
			// 	)
			// );
			?>

			<div class="form-group settings-row">
				<div class="settings-label-group">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( '2FA mandatory notice', 'wp-2fa' ),
						'id'   => '2fa-mandatory-notice-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'Shown to enforced users who are required to set up 2FA, warning them to configure it before the grace period ends.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => '2fa-mandatory-notice-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'default-2fa-required-notice',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[default-2fa-required-notice]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'default-2fa-required-notice', true ),
						'hint'        => \esc_html__( 'Only plain text is allowed.', 'wp-2fa' ),
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
						'text' => \esc_html__( '2FA reconfiguration mandatory notice', 'wp-2fa' ),
						'id'   => '2fa-reconfiguration-notice-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'Shown to users who must reconfigure their 2FA, warning them to do so before the grace period ends.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => '2fa-reconfiguration-notice-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'default-2fa-resetup-required-notice',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[default-2fa-resetup-required-notice]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'default-2fa-resetup-required-notice', true ),
						'hint'        => \esc_html__( 'Only plain text is allowed.', 'wp-2fa' ),
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
						'text' => \esc_html__( '2FA method unavailable reconfiguration mandatory notice', 'wp-2fa' ),
						'id'   => '2fa-method-removed-notice-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'Shown when a user\'s current 2FA method is no longer available, asking them to reconfigure 2FA.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => '2fa-method-removed-notice-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'default-2fa-method-removed-notice',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[default-2fa-method-removed-notice]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'default-2fa-method-removed-notice', true ),
						'hint'        => \esc_html__( 'Only plain text is allowed.', 'wp-2fa' ),
					)
				);
				?>
				</div>
			</div>
		</div>
	</div>
