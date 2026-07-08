<?php
/**
 * Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Utils\Settings_Utils;

?>
<input type="hidden" id="use_new_interface" name="wp_2fa_settings[use_new_interface]" value="use_new_interface" <?php echo Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'use_new_interface' ) ) ? 'checked' : ''; ?>>
<div class="settings-page" id="generic-settings-wrap">
	<?php
	Settings_Builder::build_option(
		array(
			'parent'       => \esc_html__( 'Settings', 'wp-2fa' ),
			'type'         => 'breadcrumb',
			'custom_class' => 'back-policies-settings-main-wrapper',
			'default'      => \esc_html__( 'General settings', 'wp-2fa' ),
		)
	);

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'General settings', 'wp-2fa' ),
				'id'    => 'general-settings-tab',
				'type'  => 'tab-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
					\esc_html__( 'Use the settings below to configure several 2FA settings on your website. If you have any questions, %1$s or %2$s.', 'wp-2fa' ),
					\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=settings_page_doc', \esc_html__( 'visit documentation', 'wp-2fa' ) ),
					\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=settings_page_support', \esc_html__( 'contact support', 'wp-2fa' ) )
				),
				'class' => 'description-settings-card',
				'id'    => 'general-settings-tab',
				'type'  => 'description',
			)
		);
		?>
	<div class="settings-card">
		<?php

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Unavailable service', 'wp-2fa' ),
				'id'    => 'general-settings-tab',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
					\esc_html__( 'There may be cases in which the 2FA service is unavailable when a user is trying to log in. For example, the service is unreachable or there are no credits to complete the action. In this case you can configure the plugin to either block the login process, or allow the user to log in without 2FA authentication.', 'wp-2fa' ),
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
					'text' => \esc_html__( 'Enforce 2FA on', 'wp-2fa' ),
					'id'   => 'general-settings-tab',
					'type' => 'settings-label',
				)
			);

			Settings_Builder::build_option(
				array(
					'id'          => 'method_invalid_setting',
					'type'        => 'radio',
					'option_name' => 'wp_2fa_settings[method_invalid_setting]',
					'default'     => WP2FA::get_wp2fa_general_setting( 'method_invalid_setting', true ),
					'options'     => array(
						'login_block'                => \esc_html__( 'Block the login', 'wp-2fa' ),
						'allow_login_without_method' => \esc_html__( 'Allow the login without 2FA', 'wp-2fa' ),
					),
				)
			);
			?>
		</div>
	</div>

	<div class="settings-card">
		<?php

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Disable 2FA code brute force protection', 'wp-2fa' ),
				'id'    => 'general-settings-tab',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
					\esc_html__( 'When using email or SMS 2FA, the plugin sends users a new one-time code each time they enter a wrong code at login. This acts as a form of brute-force protection. You can disable it using the setting below, although doing so is not recommended. %1$s', 'wp-2fa' ),
					\wp_sprintf( '<a href="%s" target="_blank">%s</a>', 'https://melapress.com/support/kb/wp-2fa-plugin-getting-started/?#utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_getting_started_wp2fa', \esc_html__( 'Learn more', 'wp-2fa' ) )
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
					'text'        => \esc_html__( 'Disable brute force protection', 'wp-2fa' ),
					'id'          => 'wp_2fa_settings[brute_force_disable]',
					'option_name' => 'wp_2fa_settings[brute_force_disable]',
					'type'        => 'checkbox',
					'default'     => WP2FA::get_wp2fa_general_setting( 'brute_force_disable', true ),
				)
			);
			?>
		</div>
	</div>

	<div class="settings-card">
		<?php

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Limit 2FA settings access', 'wp-2fa' ),
				'id'    => 'general-settings-tab',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \wp_sprintf(
				// translators: 1. Link to documentation, 2. Link to support.
					\esc_html__( 'Use this setting to hide this plugin configuration area from all other admins.', 'wp-2fa' ),
				),
				'class' => 'description-settings-card',
				'id'    => 'general-settings-tab',
				'type'  => 'description',
			)
		);

		?>
		<div class="form-group settings-row">
			<?php

			// Settings_Builder::build_option(
			// 	array(
			// 		'text' => \esc_html__( 'Limit access to 2FA settings', 'wp-2fa' ),
			// 		'id'   => 'general-settings-tab',
			// 		'type' => 'settings-label',
			// 	)
			// );

			Settings_Builder::build_option(
				array(
					'text'        => \esc_html__( 'Hide settings from other administrators', 'wp-2fa' ),
					'id'          => 'wp_2fa_settings[limit_access]',
					'option_name' => 'wp_2fa_settings[limit_access]',
					'type'        => 'checkbox',
					'default'     => WP2FA::get_wp2fa_general_setting( 'limit_access', true ),
				)
			);

			?>
		</div>
	</div>

	<div class="settings-card">
		<?php

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Disable the REST API endpoints for 2FA', 'wp-2fa' ),
				'id'    => 'general-settings-tab',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \esc_html__( 'The WP 2FA REST API endpoints are enabled by default. They are used for integrations and do not impact your website\'s performance, functionality, or security. If you prefer, you can disable these endpoints by using this setting.', 'wp-2fa' ),
				'class' => 'description-settings-card',
				'id'    => 'general-settings-tab',
				'type'  => 'description',
			)
		);

		?>
		<div class="form-group settings-row">
			<?php

			// Settings_Builder::build_option(
			// 	array(
			// 		'text' => \esc_html__( 'Disable REST API endpoints', 'wp-2fa' ),
			// 		'id'   => 'general-settings-tab',
			// 		'type' => 'settings-label',
			// 	)
			// );

			Settings_Builder::build_option(
				array(
					'text'        => \esc_html__( 'Disable the REST API endpoints', 'wp-2fa' ),
					'id'          => 'disable_rest',
					'option_name' => 'wp_2fa_settings[disable_rest]',
					'type'        => 'checkbox',
					'default'     => Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'disable_rest' ) ),
				)
			);

			?>
		</div>
	</div>

	<div id="select_verification_method_wrap" class="settings-card<?php echo true === Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'disable_rest' ) ) ? ' rest-section-disabled' : ''; ?>">
		<?php

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Select the 2FA verification mechanism', 'wp-2fa' ),
				'id'    => 'general-settings-tab',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \esc_html__( 'Choose how WP 2FA verifies the 2FA by default. The native method works for most setups, but you can switch to REST API verification if needed. Only change this setting if you are experiencing issues with the default method.', 'wp-2fa' ),
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
					'text' => \esc_html__( 'Default 2FA verification mechanism', 'wp-2fa' ),
					'id'   => 'general-settings-tab',
					'type' => 'settings-label',
				)
			);

			Settings_Builder::build_option(
				array(
					'id'          => 'enable_rest',
					'type'        => 'radio',
					'option_name' => 'wp_2fa_settings[enable_rest]',
					// Bit complicated here - needs improvement, we need to keep the old value for backward compatibility, but we also need to convert it to a boolean for the checkbox above. For this to work, the value of the setting needs to be either 'enable_rest' or '', and not a boolean. This is because we are using the same setting for both the checkbox and the radio, and they need to have the same value.
					'default'     => ( WP2FA::get_wp2fa_general_setting( 'enable_rest', true ) ? 'enable_rest' : '' ),
					'not_bool'    => true,
					'options'     => array(
						''            => \esc_html__( 'Native', 'wp-2fa' ),
						'enable_rest' => \esc_html__( 'REST API', 'wp-2fa' ),
					),
				)
			);

			?>
		</div>
	</div>

	<style>
		#select_verification_method_wrap.rest-section-disabled {
			opacity: 0.5;
			pointer-events: none;
			user-select: none;
		}
		.plugin-uninstall-card {
			border: 2px dashed #e07b39 !important;
			border-radius: 6px;
			padding: 20px 24px;
			margin-top: 24px;
			background: #fff;
			border-bottom: 2px dashed #e07b39 !important;
		}
	</style>

	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		var disableRestCheckbox = document.getElementById( 'disable_rest' );
		var verificationWrap   = document.getElementById( 'select_verification_method_wrap' );

		if ( ! disableRestCheckbox || ! verificationWrap ) {
			return;
		}

		function toggleVerificationSection() {
			if ( disableRestCheckbox.checked ) {
				verificationWrap.classList.add( 'rest-section-disabled' );
			} else {
				verificationWrap.classList.remove( 'rest-section-disabled' );
			}
		}

		disableRestCheckbox.addEventListener( 'change', toggleVerificationSection );
	} );
	</script>

	<div class="settings-card plugin-uninstall-card">
		<?php

		Settings_Builder::build_option(
			array(
				'title' => \esc_html__( 'Plugin uninstall options', 'wp-2fa' ),
				'id'    => 'general-settings-tab',
				'type'  => 'section-title',
			)
		);

		Settings_Builder::build_option(
			array(
				'text'  => \esc_html__( 'The plugin saves its settings in the WordPress database. By default the plugin settings are kept in the database so if it is installed again, you do not have to reconfigure the plugin. Enable this setting to delete the plugin settings from the database upon uninstall.', 'wp-2fa' ),
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
					'text' => \esc_html__( 'Delete data', 'wp-2fa' ),
					'id'   => 'general-settings-tab',
					'type' => 'settings-label',
				)
			);

			Settings_Builder::build_option(
				array(
					'text'        => \esc_html__( 'Delete all plugin data upon uninstall', 'wp-2fa' ),
					'id'          => 'delete_data_upon_uninstall',
					'option_name' => 'wp_2fa_settings[delete_data_upon_uninstall]',
					'type'        => 'checkbox',
					'default'     => WP2FA::get_wp2fa_general_setting( 'delete_data_upon_uninstall' ),
				)
			);

			?>
		</div>
	</div>
</div>
