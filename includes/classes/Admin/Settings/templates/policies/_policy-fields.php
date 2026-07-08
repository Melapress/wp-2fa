<?php
/**
 * Shared partial – 2FA policy fields (methods selection + user access).
 *
 * Reused by both sitewide-policies.php (with $policy_role = null) and
 * role-policy.php (with $policy_role = '<role_slug>') so the markup is
 * defined only once (DRY).
 *
 * Expected variables set before include:
 *   $policy_role  (string|null) – Role slug, or null for sitewide.
 *   $id_suffix    (string)      – Suffix appended to HTML IDs to keep them unique.
 *   $name_prefix  (string)      – Form field name prefix, e.g.
 *                                 "wp_2fa_policy" or "wp_2fa_policy[administrator]".
 *
 * @package wp-2fa
 *
 * @since 3.1.2
 */

use WP2FA\WP2FA;
use WP2FA\Methods\TOTP;
use WP2FA\Methods\Email;
use WP2FA\Methods\Backup_Codes;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Admin\Helpers\Methods_Helper;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Admin\Views\Grace_Period_Notifications;
use WP2FA\Admin\Views\Password_Reset_2FA;
use WP2FA\Admin\Views\Re_Login_2FA;
use WP2FA\Licensing\Licensing_Factory;

if ( ! isset( $policy_role, $id_suffix, $name_prefix ) ) {
	return;
}

/*
──────────────────────────────────────────────
 * Retrieve current setting values for $policy_role
 * ────────────────────────────────────────────── */

// Primary methods.
$totp_enabled  = ! empty( Settings_Utils::get_setting_role( $policy_role, TOTP::POLICY_SETTINGS_NAME, true ) );
$email_enabled = ! empty( Settings_Utils::get_setting_role( $policy_role, Email::POLICY_SETTINGS_NAME, true ) );

// Secondary / backup methods.
$backup_enabled              = Settings_Utils::string_to_bool( Settings_Utils::get_setting_role( $policy_role, Backup_Codes::get_settings_name(), true ) );
$email_backup_enabled        = Settings_Utils::get_setting_role( $policy_role, 'enable-email-backup', true );
$specify_email_choice        = Settings_Utils::get_setting_role( $policy_role, 'specify-email_hotp', true );
$specify_backup_email_choice = Settings_Utils::get_setting_role( $policy_role, 'specify-backup-email', true );

// User access settings (Tab 2).
$password_reset_2fa = Settings_Utils::get_setting_role( $policy_role, Password_Reset_2FA::PASSWORD_RESET_SETTINGS_NAME, true );
$re_login_2fa       = Settings_Utils::get_setting_role( $policy_role, Re_Login_2FA::RE_LOGIN_SETTINGS_NAME, true );
$hide_remove        = Settings_Utils::get_setting_role( $policy_role, 'hide_remove_button', true );

// Redirect.
$redirect_after = Settings_Utils::get_setting_role( $policy_role, 'redirect-user-custom-page-global', true );

// Grace period.
$grace_policy              = Settings_Utils::get_setting_role( $policy_role, 'grace-policy', true );
$grace_period              = (int) Settings_Utils::get_setting_role( $policy_role, 'grace-period', true );
$grace_period_denominator  = Settings_Utils::get_setting_role( $policy_role, 'grace-period-denominator', true );
$grace_after_expire_action = Settings_Utils::get_setting_role( $policy_role, 'grace-policy-after-expire-action', true );
$grace_notification        = Settings_Utils::get_setting_role( $policy_role, Grace_Period_Notifications::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME, true );
if ( ! $grace_period ) {
	$grace_period = 3;
}
if ( ! $grace_after_expire_action ) {
	$grace_after_expire_action = 'configure-right-away';
}
if ( ! $grace_notification ) {
	$grace_notification = 'after-login-notification';
}

// Custom profile page.
$create_custom_page      = Settings_Utils::get_setting_role( $policy_role, 'create-custom-user-page', true );
$custom_page_url         = Settings_Utils::get_setting_role( $policy_role, 'custom-user-page-url', true );
$custom_page_id          = Settings_Utils::get_setting_role( $policy_role, 'custom-user-page-id', true );
$redirect_custom_page    = Settings_Utils::get_setting_role( $policy_role, 'redirect-user-custom-page', true );
$separate_multisite_page = Settings_Utils::get_setting_role( $policy_role, 'separate-multisite-page-url', true );

// Trusted devices (extension – guard with class_exists).
$trusted_devices_enabled  = false;
$trusted_devices_period   = 30;
$trusted_devices_ip_match = false;
if ( \class_exists( '\WP2FA\Extensions\TrustedDevices\Settings' ) ) {
	$trusted_devices_enabled = Settings_Utils::string_to_bool(
		Settings_Utils::get_setting_role( $policy_role, \WP2FA\Extensions\TrustedDevices\Settings::OPTION_NAME_ENABLED, true )
	);
	$trusted_devices_period  = (int) Settings_Utils::get_setting_role( $policy_role, \WP2FA\Extensions\TrustedDevices\Settings::OPTION_NAME_PERIOD, true );
	if ( $trusted_devices_period < 1 ) {
		$trusted_devices_period = 30;
	}
	$trusted_devices_ip_match = Settings_Utils::string_to_bool(
		Settings_Utils::get_setting_role( $policy_role, \WP2FA\Extensions\TrustedDevices\Settings::OPTION_NAME_IP_MUST_MATCH, true )
	);
}

// Methods order – stored as an indexed array of method slugs.
$methods_order = Settings_Utils::get_setting_role( $policy_role, Methods_Helper::POLICY_SETTINGS_NAME, true );

// Premium license check – used for gating premium features throughout this template.
$has_premium_license = Licensing_Factory::has_active_valid_license();

/*
──────────────────────────────────────────────
 * Build primary methods list (sortable + checkbox)
 * ────────────────────────────────────────────── */
$all_methods = array();

/**
 * Allow extensions to register their primary methods.
 *
 * Each item must contain: id, title, enabled (bool), setting (POLICY_SETTINGS_NAME).
 *
 * @param array       $all_methods  Current methods array keyed by METHOD_NAME.
 * @param string|null $policy_role  Role slug or null for sitewide.
 *
 * @since 3.1.2
 */
$all_methods = \apply_filters( WP_2FA_PREFIX . 'methods_policy_settings_new', $all_methods, $policy_role );

// Sort by stored order.
if ( \is_array( $methods_order ) && ! empty( $methods_order ) ) {
	$ordered = array();
	foreach ( $methods_order as $slug ) {
		if ( isset( $all_methods[ $slug ] ) ) {
			$ordered[ $slug ] = $all_methods[ $slug ];
		}
	}
	foreach ( $all_methods as $slug => $m ) {
		if ( ! isset( $ordered[ $slug ] ) ) {
			$ordered[ $slug ] = $m;
		}
	}
	$all_methods = $ordered;
}

// Build items for the sortable-list component (with integrated checkboxes).
$sortable_items        = array();
$managed_checkbox_keys = array();
foreach ( $all_methods as $method ) {
	$item = array(
		'id'            => $method['setting'],
		'title'         => $method['title'],
		'order_id'      => $method['id'],
		'checkbox_name' => $name_prefix . '[' . $method['setting'] . ']',
		'checked'       => ! empty( $method['enabled'] ),
	);
	if ( ! empty( $method['extra_settings'] ) ) {
		$item['extra_settings'] = $method['extra_settings'];
	}
	if ( ! empty( $method['integration_settings'] ) ) {
		$item['integration_settings'] = $method['integration_settings'];
		$item['is_configured']        = ! empty( $method['is_configured'] );
	}
	if ( ! empty( $method['description'] ) ) {
		$item['description'] = $method['description'];
	}
	if ( ! empty( $method['title_hint'] ) ) {
		$item['title_hint'] = $method['title_hint'];
	}
	if ( isset( $method['is_configured'] ) && ! $method['is_configured'] ) {
		$item['setup_url'] = $method['setup_url'];
	}
	$sortable_items[]        = $item;
	$managed_checkbox_keys[] = $method['setting'];
}

// Also track secondary/toggle checkboxes rendered on this page.
$managed_checkbox_keys[] = Backup_Codes::get_settings_name();
$managed_checkbox_keys[] = 'enable-email-backup';
$managed_checkbox_keys[] = 'specify-backup-email';
$managed_checkbox_keys[] = Password_Reset_2FA::PASSWORD_RESET_SETTINGS_NAME;
$managed_checkbox_keys[] = Re_Login_2FA::RE_LOGIN_SETTINGS_NAME;
$managed_checkbox_keys[] = 'hide_remove_button';
$managed_checkbox_keys[] = 'create-custom-user-page';
$managed_checkbox_keys[] = 'separate-multisite-page-url';
if ( \class_exists( '\WP2FA\Extensions\TrustedDevices\Settings' ) ) {
	$managed_checkbox_keys[] = \WP2FA\Extensions\TrustedDevices\Settings::OPTION_NAME_ENABLED;
}

/**
 * Allow extensions to register additional checkbox keys managed by this form.
 *
 * @param array       $managed_checkbox_keys Current list of managed checkbox setting keys.
 * @param string|null $policy_role           Role slug or null for sitewide.
 *
 * @since 3.1.2
 */
$managed_checkbox_keys = \apply_filters( WP_2FA_PREFIX . 'managed_checkbox_keys', $managed_checkbox_keys, $policy_role );

?>

	<!-- Hidden field listing all checkbox settings rendered on this form -->
	<input type="hidden"
		name="<?php echo \esc_attr( $name_prefix ); ?>[_managed_checkboxes]"
		value="<?php echo \esc_attr( implode( ',', $managed_checkbox_keys ) ); ?>" />

	<!-- ======== Tabbed navigation ======== -->
	<?php
	Settings_Builder::build_option(
		array(
			'id'          => 'policies-tabs' . $id_suffix,
			'type'        => 'tabbed-navigation',
			'option_name' => 'policies-tab-nav' . $id_suffix,
			'values'      => array(
				array(
					'id'    => 'method-selection' . $id_suffix,
					'title' => \esc_html__( '2FA Method Selection', 'wp-2fa' ),
				),
				array(
					'id'    => 'user-access' . $id_suffix,
					'title' => \esc_html__( 'User access settings', 'wp-2fa' ),
				),
			),
		)
	);
	?>


	<!-- ══════════════════════════════════════════════════════
		TAB 1 — 2FA Method Selection
	══════════════════════════════════════════════════════════ -->
	<div class="tab-panel tab-panel-method-selection<?php echo \esc_attr( $id_suffix ); ?>">

		<p class="wp2fa-tab-intro">
			<?php
			printf(
				/* translators: %s: link to documentation */
				\esc_html__( 'When you enforce 2FA the users will be prompted to configure 2FA the next time they login. Users have a grace period for configuring 2FA. You can configure the grace period and also exclude user(s) or role(s) in this settings page. %s', 'wp-2fa' ),
				'<a href="' . \esc_url( 'https://melapress.com/support/kb/wp-2fa-configure-2fa-policies-enforce/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=enforcement_policy_help' ) . '" target="_blank">' . \esc_html__( 'Learn more.', 'wp-2fa' ) . '</a>'
			);
			?>
		</p>

		<!-- Select primary 2FA methods -->
		<div class="settings-card">
			<?php
			Settings_Builder::build_option(
				array(
					'title' => \esc_html__( 'Select primary 2FA methods', 'wp-2fa' ),
					'id'    => 'primary-methods-section' . $id_suffix,
					'type'  => 'section-title',
				)
			);

			Settings_Builder::build_option(
				array(
					'text'  => \esc_html__( 'Drag and drop to reorder how 2FA methods show for the users.', 'wp-2fa' ),
					'class' => 'description-settings-card',
					'id'    => 'primary-methods-desc' . $id_suffix,
					'type'  => 'description',
				)
			);

			Settings_Builder::build_option(
				array(
					'id'          => 'methods-order' . $id_suffix,
					'type'        => 'sortable-list',
					'items'       => $sortable_items,
					'name_prefix' => $name_prefix . '[methods_order]',
				)
			);

			/*
			 * When no valid premium license is present, show premium-only
			 * methods as static locked items so users know what's available.
			 */
			if ( ! $has_premium_license ) :
				$premium_methods_list = array(
					'authy'           => \esc_html__( 'Push notification via Authy app', 'wp-2fa' ),
					'oob'             => \esc_html__( 'Link via email', 'wp-2fa' ),
					'twilio'          => \esc_html__( 'One-time code via SMS (Twilio)', 'wp-2fa' ),
					'clickatell'      => \esc_html__( 'One-time code via SMS (Clickatell)', 'wp-2fa' ),
					'yubico'          => \esc_html__( 'One-time password via YubiKey', 'wp-2fa' ),
					'0_setup_email'   => \esc_html__( 'One-time code via email (zero-setup)', 'wp-2fa' ),
				);

				// Remove any that are already shown (in case they somehow registered).
				foreach ( $all_methods as $method ) {
					unset( $premium_methods_list[ $method['id'] ] );
				}

				if ( ! empty( $premium_methods_list ) ) :
					$premium_method_contexts = array(
						'oob'           => 'wp-2fa-policies:method-oob',
						'yubico'        => 'wp-2fa-policies:method-yubikey',
						'0_setup_email' => 'wp-2fa-policies:method-0setup',
						'twilio'        => 'wp-2fa-policies:method-sms',
						'clickatell'    => 'wp-2fa-policies:method-sms',
					);
				?>
				<div class="wp2fa-premium-gate wp2fa-premium-gate-locked">
					<ul class="wp2fa-sortable-list wp2fa-premium-methods-preview">
						<?php foreach ( $premium_methods_list as $method_id => $method_title ) :
							$badge_context = isset( $premium_method_contexts[ $method_id ] ) ? $premium_method_contexts[ $method_id ] : 'wp-2fa-policies-methods';
						?>
						<li class="wp2fa-sortable-item wp2fa-premium-method-item">
							<span class="wp2fa-sortable-handle" aria-hidden="true">&#9776;</span>
							<label class="wp2fa-sortable-checkbox toggle-switch">
								<input type="checkbox" disabled>
								<span class="toggle-slider"></span>
								<?php echo Settings_Builder::get_premium_badge_html( $badge_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span class="wp2fa-sortable-title"><?php echo \esc_html( $method_title ); ?></span>
							</label>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php
				endif;
			endif;

			/**
			 * Fires after the primary methods sortable list, allowing extensions to append items.
			 *
			 * @param string|null $policy_role Role slug or null for sitewide.
			 *
			 * @since 3.1.2
			 */
			\do_action( WP_2FA_PREFIX . 'after_primary_methods_toggles', $policy_role );
			?>
		</div>

		<!-- Select Secondary 2FA Methods -->
		<div class="settings-card">
			<?php
			Settings_Builder::build_option(
				array(
					'title' => \esc_html__( 'Select Secondary 2FA Methods', 'wp-2fa' ),
					'id'    => 'secondary-methods-section' . $id_suffix,
					'type'  => 'section-title',
				)
			);
			?>

			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => Backup_Codes::get_settings_name() . $id_suffix,
						'type'        => 'checkbox',
						'option_name' => $name_prefix . '[' . Backup_Codes::get_settings_name() . ']',
						'default'     => ( $backup_enabled ) ? 'yes' : '',
						'text'        => '<strong>' . \esc_html__( 'Backup codes', 'wp-2fa' ) . '</strong>',
						'not_bool'    => true,
						'not_id'      => true,
						'value'       => 'yes',
					)
				);
				?>
			</div>
			<div class="form-group settings-row" style="margin-top:-27px; margin-left: 35px;">
				<?php
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'Backup codes are a secondary method which you can use to log in to the website in case the primary 2FA method is unavailable. Therefore they can\'t be enabled and used as a primary method.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'backup-codes-hint' . $id_suffix,
						'type'  => 'description',
					)
				);
				?>
			</div>

			<?php
			Settings_Builder::premium_gate_open( $has_premium_license );
			?>
			<div class="form-group settings-row">
				<?php
				$premium_badge_html = ! $has_premium_license ? Settings_Builder::get_premium_badge_html( 'wp-2fa-policies:backup-email' ) : '';
				Settings_Builder::build_option(
					array(
						'id'          => 'enable-email-backup' . $id_suffix,
						'type'        => 'checkbox',
						'option_name' => $has_premium_license ? $name_prefix . '[enable-email-backup]' : '',
						'not_bool'    => true,
						'not_id'      => true,
						'value'       => 'enable-email-backup',
						'default'     => $has_premium_license ? $email_backup_enabled : '',
						'text'        => $premium_badge_html . '<strong>' . \esc_html__( 'Allow users to use email based 2FA as secondary backup method', 'wp-2fa' ) . '</strong>',
					)
				);
				?>
			</div>
			<div class="form-group settings-row" style="margin-top:-27px; margin-left: 35px;">
				<?php
				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'By enabling this feature users can also configure and use the "one-time code via email" 2FA method as a secondary backup method. This allows them to receive a one-time login code via email if they need to login to the website and cannot generate the login code from their 2FA app, lost their hardware key (Yubikey) or cannot access the SMS with the codes on their smartphones.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'email-backup-hint' . $id_suffix,
						'type'  => 'description',
					)
				);
				?>
			</div>

			<?php if ( $has_premium_license ) : ?>
			<div class="form-group settings-row" id="specify-email-choice-wrap<?php echo \esc_attr( $id_suffix ); ?>" style="<?php echo ( 'enable-email-backup' !== $email_backup_enabled ) ? 'display:none;' : ''; ?>margin-left:35px;margin-top:10px;">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'specify-backup-email' . $id_suffix,
						'type'        => 'toggle-checkbox',
						'option_name' => $name_prefix . '[specify-backup-email]',
						'not_bool'    => true,
						'not_id'      => true,
						'value'       => 'specify-backup-email',
						'default'     => $specify_backup_email_choice,
						'text'        => '<strong>' . \esc_html__( 'Allow users to specify their email address of choice', 'wp-2fa' ) . '</strong>',
					)
				);
				?>
			</div>
			<?php endif; ?>
			<?php Settings_Builder::premium_gate_close(); ?>

			<?php
			/**
			 * Fires after the secondary methods, allowing extensions to add more.
			 *
			 * @param string|null $policy_role Role slug or null for sitewide.
			 *
			 * @since 3.1.2
			 */
			\do_action( WP_2FA_PREFIX . 'after_secondary_methods_toggles', $policy_role );
			?>
		</div>

	</div><!-- /.tab-panel-method-selection -->


	<!-- ══════════════════════════════════════════════════════
		TAB 2 — User access settings
	══════════════════════════════════════════════════════════ -->
	<div class="tab-panel tab-panel-user-access<?php echo \esc_attr( $id_suffix ); ?>">

		<!-- User access settings heading -->
		<div class="settings-card">
			<?php
			Settings_Builder::build_option(
				array(
					'title' => \esc_html__( 'User access settings', 'wp-2fa' ),
					'id'    => 'user-access-section' . $id_suffix,
					'type'  => 'section-title',
				)
			);
			?>

			<?php
			?>

			<!-- Hide the 'Remove 2FA' button -->
			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'hide_remove_button' . $id_suffix,
						'type'        => 'toggle-checkbox',
						'option_name' => $name_prefix . '[hide_remove_button]',
						'default'     => Settings_Utils::string_to_bool( $hide_remove ),
						'text'        => '<strong>' . \esc_html__( 'Hide the \'Remove 2FA\' button', 'wp-2fa' ) . '</strong>',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( 'Users can configure and disable 2FA from their profile using the \'Remove 2FA\' button. Enable this option to hide the button and prevent users from disabling 2FA in their profile.', 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'hide-remove-desc' . $id_suffix,
						'type'  => 'description',
					)
				);
				?>
			</div>

			<?php if ( $has_premium_license ) : ?>
			<!-- Hidden sentinel so wp_2fa_white_label is always submitted -->
			<input type="hidden" name="wp_2fa_white_label[_submitted]" value="1" />
			<!-- Hide plugin attribution text -->
			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'hide_page_generated_by' . $id_suffix,
						'type'        => 'toggle-checkbox',
						'option_name' => 'wp_2fa_white_label[hide_page_generated_by]',
						'not_bool'    => true,
						'not_id'      => true,
						'value'       => 'hide_page_generated_by',
						'default'     => WP2FA::get_wp2fa_white_label_setting( 'hide_page_generated_by', false ),
						'text'        => '<strong>' . \esc_html__( 'Hide plugin attribution text', 'wp-2fa' ) . '</strong>',
					)
				);

				Settings_Builder::build_option(
					array(
						'text'  => \esc_html__( "Remove 'Page generated by WP 2FA plugin' text from the 2FA front-end configuration page.", 'wp-2fa' ),
						'class' => 'description-settings-card',
						'id'    => 'hide-page-generated-by-desc' . $id_suffix,
						'type'  => 'description',
					)
				);
				?>
			</div>
			<?php endif; ?>
		</div>

		<!-- Grace Period -->
		<div class="settings-card">
			<?php
			Settings_Builder::build_option(
				array(
					'title' => \esc_html__( 'Grace period', 'wp-2fa' ),
					'id'    => 'grace-period-section' . $id_suffix,
					'type'  => 'section-title',
				)
			);

			Settings_Builder::build_option(
				array(
					'text'  => \esc_html__( 'When you enforce 2FA on users they have a grace period to configure 2FA. If they fail to configure it within the configured stipulated time, their account will be locked and have to be unlocked manually. Note that user accounts cannot be unlocked automatically, even if you change the settings. As a security precaution they always have to be unlocked them manually. Maximum grace period is 90 days.', 'wp-2fa' ),
					'class' => 'description-settings-card',
					'id'    => 'grace-period-desc' . $id_suffix,
					'type'  => 'description',
				)
			);
			?>

			<!-- Instant vs grace period -->
			<div class="form-group settings-row">
				<div class="settings-control">
					<label class="radio-option">
						<input type="radio"
							name="<?php echo \esc_attr( $name_prefix ); ?>[grace-policy]"
							id="no-grace-period<?php echo \esc_attr( $id_suffix ); ?>"
							value="no-grace-period"
							<?php \checked( $grace_policy, 'no-grace-period' ); ?>>
						<span><?php \esc_html_e( 'Users have to configure 2FA straight away.', 'wp-2fa' ); ?></span>
					</label>
					<label class="radio-option">
						<input type="radio"
							name="<?php echo \esc_attr( $name_prefix ); ?>[grace-policy]"
							id="use-grace-period<?php echo \esc_attr( $id_suffix ); ?>"
							value="use-grace-period"
							<?php \checked( $grace_policy, 'use-grace-period' ); ?>
							data-toggle-target="#grace-period-sub-settings<?php echo \esc_attr( $id_suffix ); ?>">
						<span><?php \esc_html_e( 'Give users a grace period to configure 2FA', 'wp-2fa' ); ?></span>
					</label>
				</div>
			</div>

			<!-- Grace period sub-settings (shown when "use-grace-period" is selected) -->
			<div id="grace-period-sub-settings<?php echo \esc_attr( $id_suffix ); ?>" class="grace-period-sub-settings"<?php echo ( 'use-grace-period' !== $grace_policy ) ? ' style="display:none;"' : ''; ?>>

				<!-- Grace period duration -->
				<div class="form-group settings-row" style="margin-left: 35px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Grace period', 'wp-2fa' ),
							'id'   => 'grace-period-label' . $id_suffix,
							'type' => 'settings-label',
						)
					);
					?>
					<div class="settings-control inline-controls">
						<?php
						Settings_Builder::build_option(
							array(
								'id'          => 'grace-period' . $id_suffix,
								'type'        => 'number',
								'min'         => 1,
								'max'         => 90,
								'class'       => 'small-text',
								'option_name' => $name_prefix . '[grace-period]',
								'default'     => $grace_period,
							)
						);
						Settings_Builder::build_option(
							array(
								'id'          => 'grace-period-denominator' . $id_suffix,
								'type'        => 'select',
								'option_name' => $name_prefix . '[grace-period-denominator]',
								'default'     => $grace_period_denominator,
								'options'     => array(
									'hours' => \esc_html__( 'Hours', 'wp-2fa' ),
									'days'  => \esc_html__( 'Days', 'wp-2fa' ),
								),
							)
						);
						?>
					</div>
				</div>
				<!-- What happens after grace period expires -->
				<div class="form-group settings-row" style="margin-left: 35px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'What should the plugin do with users who do not configure 2FA within the grace period?', 'wp-2fa' ),
							'id'   => 'grace-expire-label' . $id_suffix,
							'type' => 'settings-label',
						)
					);
					Settings_Builder::build_option(
						array(
							'id'          => 'grace-policy-after-expire-action' . $id_suffix,
							'type'        => 'radio',
							'option_name' => $name_prefix . '[grace-policy-after-expire-action]',
							'default'     => $grace_after_expire_action,
							'options'     => array(
								'configure-right-away' => \esc_html__( 'Do not let them access the dashboard / user page once they log in until they configure 2FA', 'wp-2fa' ),
								'manual-block'         => \esc_html__( 'Lock the user (administrators have to manually unlock them)', 'wp-2fa' ),
							),
						)
					);
					?>
				</div>

				<!-- How users are informed about 2FA enforcement -->
				<div class="form-group settings-row" style="margin-left: 35px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'How do you want users to be informed they are enforced to setup 2FA?', 'wp-2fa' ),
							'id'   => 'grace-notification-label' . $id_suffix,
							'type' => 'settings-label',
						)
					);
					Settings_Builder::build_option(
						array(
							'id'          => 'grace-notification' . $id_suffix,
							'type'        => 'radio',
							'option_name' => $name_prefix . '[' . Grace_Period_Notifications::GRACE_PERIOD_NOTIFICATION_SETTINGS_NAME . ']',
							'default'     => $grace_notification,
							'options'     => array(
								'dashboard-notification'   => \esc_html__( 'Show an admin notice in the dashboard', 'wp-2fa' ),
								'after-login-notification' => \esc_html__( 'Show a notification on a page on its own after the user authenticates and before accessing the dashboard', 'wp-2fa' ),
							),
						)
					);
					?>
				</div>
			</div>
		</div>

		<!-- Custom Profile Pages -->
		<div class="settings-card">
			<?php
			Settings_Builder::build_option(
				array(
					'title' => \esc_html__( 'Frontend settings page', 'wp-2fa' ),
					'id'    => 'custom-user-page-section' . $id_suffix,
					'type'  => 'section-title',
				)
			);

			Settings_Builder::build_option(
				array(
					'text'  => \esc_html__( 'Enable this option if your users do not have full access to the WordPress dashboard, or other user roles that access your site primarily through the frontend. WP 2FA will create a dedicated frontend page where authenticated users can configure and manage their own 2FA settings.', 'wp-2fa' ) . ' <a href="https://melapress.com/support/kb/wp-2fa-configure-2fa-front-end-page-wordpress/?utm_source=plugin&utm_medium=wp2fa&utm_campaign=guide_frontend_2fa_page" target="_blank">' . \esc_html__( 'Learn more.', 'wp-2fa' ) . '</a>',
					'class' => 'description-settings-card',
					'id'    => 'custom-user-page-desc' . $id_suffix,
					'type'  => 'description',
				)
			);
			?>

			<!-- Frontend 2FA settings page toggle -->
			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'id'          => 'create-custom-user-page' . $id_suffix,
						'type'        => 'toggle-checkbox',
						'option_name' => $name_prefix . '[create-custom-user-page]',
						'not_bool'    => true,
						'not_id'      => true,
						'value'       => 'yes',
						'default'     => ( 'yes' === $create_custom_page ) ? 'yes' : '',
						'data_attrs'  => array( 'toggle-target' => '#custom-user-page-sub-settings' . \esc_attr( $id_suffix ) ),
					)
				);
				?>
				<div class="no-width">
					<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'Enable frontend 2FA settings page', 'wp-2fa' ),
						'id'   => 'custom-user-page-label' . $id_suffix,
						'type' => 'settings-label',
					)
				);
				?>
				</div>
			</div>

			<!-- Custom page sub-settings (shown when "Yes" is selected) -->
			<div id="custom-user-page-sub-settings<?php echo \esc_attr( $id_suffix ); ?>"<?php echo ( 'yes' !== $create_custom_page ) ? ' style="display:none;"' : ''; ?>>

				<!-- Frontend page URL -->
				<div class="form-group settings-row" style="margin-left: 35px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Frontend 2FA settings page URL', 'wp-2fa' ),
							'id'   => 'custom-user-page-url-label' . $id_suffix,
							'type' => 'settings-label',
						)
					);

					if ( ! empty( $custom_page_id ) ) {
						$custom_slug = \get_post_field( 'post_name', \get_post( $custom_page_id ) );
					} else {
						$custom_slug = $custom_page_url;
					}

					Settings_Builder::build_option(
						array(
							'id'          => 'custom-user-page-url' . $id_suffix,
							'type'        => 'url-prefixed-text',
							'url_prefix'  => \trailingslashit( \get_site_url() ),
							'option_name' => $name_prefix . '[custom-user-page-url]',
							'default'     => \sanitize_text_field( $custom_slug ? $custom_slug : '' ),
						)
					);

					if ( ! empty( $custom_page_id ) && ! WP_Helper::is_multisite() ) {
						$edit_post_link = \get_edit_post_link( $custom_page_id );
						$view_post_link = \get_permalink( $custom_page_id );
						?>
						<div class="url-prefix-actions">
							<a href="<?php echo \esc_url( $edit_post_link ); ?>" target="_blank" class="button button-secondary"><?php \esc_html_e( 'Edit Page', 'wp-2fa' ); ?></a>
							<a href="<?php echo \esc_url( $view_post_link ); ?>" target="_blank" class="button button-primary"><?php \esc_html_e( 'View Page', 'wp-2fa' ); ?></a>
						</div>
						<?php
					}
					?>
				</div>

				<?php if ( WP_Helper::is_multisite() ) : ?>
				<!-- Separate pages per multisite sub-site -->
				<div class="form-group settings-row" style="margin-left: 35px;">
					<?php
					Settings_Builder::build_option(
						array(
							'id'          => 'separate-multisite-page-url' . $id_suffix,
							'type'        => 'checkbox',
							'option_name' => $name_prefix . '[separate-multisite-page-url]',
							'not_bool'    => true,
							'not_id'      => true,
							'value'       => 'separate-multisite-page-url',
							'default'     => $separate_multisite_page,
							'text'        => '<strong>' . \esc_html__( 'Create User settings page separately for every site', 'wp-2fa' ) . '</strong>',
						)
					);

					Settings_Builder::build_option(
						array(
							'text'  => \esc_html__( 'When you enable this setting a page with the same slug is created on each site on the network, so the users of each sub site can use this page on their website to configure 2FA.', 'wp-2fa' ),
							'class' => 'description-settings-card',
							'id'    => 'separate-multisite-desc' . $id_suffix,
							'type'  => 'description',
						)
					);
					?>
				</div>
				<?php endif; ?>

				<!-- Redirect after 2FA setup (custom page override) -->
				<div class="form-group settings-row" style="margin-left: 35px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text' => \esc_html__( 'Redirect URL', 'wp-2fa' ),
							'id'   => 'redirect-custom-page-label' . $id_suffix,
							'type' => 'settings-label',
						)
					);
					Settings_Builder::build_option(
						array(
							'id'          => 'redirect-user-custom-page' . $id_suffix,
							'type'        => 'url-prefixed-text',
							'url_prefix'  => \trailingslashit( \get_site_url() ),
							'option_name' => $name_prefix . '[redirect-user-custom-page]',
							'default'     => \sanitize_text_field( $redirect_custom_page ? $redirect_custom_page : '' ),
						)
					);
					?>
				</div>
				<div class="form-group settings-row" style="margin-left: 35px;">
					<?php
					Settings_Builder::build_option(
						array(
							'text'  => \esc_html__( 'Specify the page where you want to redirect your users to after they complete the 2FA setup. This will override the global redirect setting.', 'wp-2fa' ),
							'class' => 'description-settings-card',
							'id'    => 'redirect-custom-page-desc' . $id_suffix,
							'type'  => 'description',
						)
					);
					?>
				</div>

			</div>
		</div>

		<!-- Redirect users after 2FA setup (global) -->
		<div class="settings-card">
			<?php
			Settings_Builder::build_option(
				array(
					'title' => \esc_html__( 'Redirect users after 2FA setup', 'wp-2fa' ),
					'id'    => 'redirect-section' . $id_suffix,
					'type'  => 'section-title',
				)
			);

			Settings_Builder::build_option(
				array(
					'text'  => \esc_html__( 'Set a redirect URL after 2FA setup. Leave blank to return users to the page they started from', 'wp-2fa' ),
					'class' => 'description-settings-card',
					'id'    => 'redirect-desc' . $id_suffix,
					'type'  => 'description',
				)
			);
			?>

			<div class="form-group settings-row">
				<?php
				Settings_Builder::build_option(
					array(
						'text' => \esc_html__( 'Redirect URL', 'wp-2fa' ),
						'id'   => 'redirect-global-label' . $id_suffix,
						'type' => 'settings-label',
					)
				);
				Settings_Builder::build_option(
					array(
						'id'          => 'redirect-user-custom-page-global' . $id_suffix,
						'type'        => 'url-prefixed-text',
						'url_prefix'  => \trailingslashit( \get_site_url() ),
						'option_name' => $name_prefix . '[redirect-user-custom-page-global]',
						'default'     => \sanitize_text_field( $redirect_after ? $redirect_after : '' ),
					)
				);
				?>
			</div>
		</div>

		<!-- Trusted Devices -->

		<?php
		/**
		 * Fires at the end of the User access settings tab.
		 *
		 * @param string|null $policy_role Role slug or null for sitewide.
		 *
		 * @since 3.1.2
		 */
		\do_action( WP_2FA_PREFIX . 'after_sitewide_policies', $policy_role );
		?>

	</div><!-- /.tab-panel-user-access -->
