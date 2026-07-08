<?php
/**
 * Settings Template – Customize 2FA user profile page area.
 *
 * @package wp-2fa
 *
 * @since 4.0.0
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;

?>
	<div class="settings-page" id="customize-user-profile-wrap">

				<?php
				Settings_Builder::build_option(
					array(
						'parent'       => \esc_html__( 'White labeling', 'wp-2fa' ),
						'type'         => 'breadcrumb',
						'custom_class' => 'back-policies-settings-main-wrapper',
						'default'      => \esc_html__( 'Customize 2FA user profile page area', 'wp-2fa' ),
					)
				);
				Settings_Builder::build_option(
					array(
						'title' => esc_html__( 'Customize 2FA user profile page area', 'wp-2fa' ),
						'id'    => 'wp-2fa-user-profile-settings-tab',
						'type'  => 'tab-title',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \wp_sprintf(
							\esc_html__( 'Customize the title and description shown in the 2FA section of each user\'s WordPress profile page. %1$s.', 'wp-2fa' ),
							\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-customize-user-2fa-experience/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_customize_2fa_user_experience', \esc_html__( 'Learn more', 'wp-2fa' ) )
						),
						'class' => 'description-settings-card',
						'id'    => 'wp-2fa-user-profile-settings-tab',
						'type'  => 'description',
					)
				);
				?>

		<div class="settings-card">
			<?php
			// Settings_Builder::build_option(
			// 	array(
			// 		'title' => esc_html__( 'Customize 2FA user profile page area', 'wp-2fa' ),
			// 		'id'    => 'user-profile-section',
			// 		'type'  => 'section-title',
			// 	)
			// );
			?>

			<div class="form-group settings-row">
				<div class="settings-label-group">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'User profile 2FA configuration area title', 'wp-2fa' ),
						'id'   => 'user-profile-title-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'The heading shown above the 2FA section on each user\'s WordPress profile page.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'user-profile-title-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'user-profile-form-preamble-title',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[user-profile-form-preamble-title]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'user-profile-form-preamble-title', true ),
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
						'text' => \esc_html__( 'User profile 2FA configuration area description', 'wp-2fa' ),
						'id'   => 'user-profile-desc-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'The short text shown under that heading, describing the 2FA section on the user\'s profile page.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'user-profile-desc-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'user-profile-form-preamble-desc',
						'type'        => 'editor',
						'placeholder' => \esc_html__( 'Enter custom message', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[user-profile-form-preamble-desc]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'user-profile-form-preamble-desc', true ),
						'hint'        => \esc_html__( 'Only plain text is allowed.', 'wp-2fa' ),
					)
				);
				?>
				</div>
			</div>
		</div>
	</div>
