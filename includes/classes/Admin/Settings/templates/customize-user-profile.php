<?php
/**
 * Settings Template – Customize 2FA user profile page area.
 *
 * @package wp-2fa
 *
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

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
							/* translators: %1$s: the link to the user profile guide, already wrapped in an anchor. */
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
			//  array(
			//      'title' => esc_html__( 'Customize 2FA user profile page area', 'wp-2fa' ),
			//      'id'    => 'user-profile-section',
			//      'type'  => 'section-title',
			//  )
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

		<div class="settings-card">
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

			<div class="form-group settings-row">
				<div class="settings-label-group">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'Primary method label', 'wp-2fa' ),
						'id'   => 'user-profile-primary-method-label-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'The label shown before the user\'s currently configured primary 2FA method.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'user-profile-primary-method-label-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'user-profile-primary-method-label',
						'type'        => 'text',
						'placeholder' => \esc_html__( 'Enter custom label', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[user-profile-primary-method-label]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'user-profile-primary-method-label', true ),
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
						'text' => \esc_html__( 'Secondary method label', 'wp-2fa' ),
						'id'   => 'user-profile-secondary-method-label-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'The label shown before the user\'s configured backup 2FA methods.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'user-profile-secondary-method-label-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'user-profile-secondary-method-label',
						'type'        => 'text',
						'placeholder' => \esc_html__( 'Enter custom label', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[user-profile-secondary-method-label]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'user-profile-secondary-method-label', true ),
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
						'text' => \esc_html__( '2FA configuration section heading', 'wp-2fa' ),
						'id'   => 'user-profile-configuration-title-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'The heading above the 2FA setup and backup method cards.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'user-profile-configuration-title-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'user-profile-configuration-title',
						'type'        => 'text',
						'placeholder' => \esc_html__( 'Enter custom label', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[user-profile-configuration-title]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'user-profile-configuration-title', true ),
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
						'text' => \esc_html__( '2FA setup card title', 'wp-2fa' ),
						'id'   => 'user-profile-setup-card-title-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'The title of the card holding the change and remove 2FA buttons.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'user-profile-setup-card-title-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'user-profile-setup-card-title',
						'type'        => 'text',
						'placeholder' => \esc_html__( 'Enter custom label', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[user-profile-setup-card-title]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'user-profile-setup-card-title', true ),
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
						'text' => \esc_html__( 'Backup method card title', 'wp-2fa' ),
						'id'   => 'user-profile-backup-card-title-label',
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'The title of the card holding the backup codes and backup email buttons.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'user-profile-backup-card-title-desc',
						'type'  => 'description',
					)
				);
				?>
				</div>
				<div class="settings-control">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'user-profile-backup-card-title',
						'type'        => 'text',
						'placeholder' => \esc_html__( 'Enter custom label', 'wp-2fa' ),
						'class'       => 'form-input',
						'option_name' => 'wp_2fa_white_label[user-profile-backup-card-title]',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'user-profile-backup-card-title', true ),
						'hint'        => \esc_html__( 'Only plain text is allowed.', 'wp-2fa' ),
					)
				);
				?>
				</div>
			</div>
		</div>
	</div>
