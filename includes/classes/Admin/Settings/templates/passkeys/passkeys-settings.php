<?php
/**
 * Passkeys Settings Template
 *
 * @package wp-2fa
 */

use WP2FA\WP2FA;
use WP2FA\Admin\Settings_Builder;
use WP2FA\Methods\Passkeys;
use WP2FA\Utils\Settings_Utils;
use WP2FA\Admin\Helpers\WP_Helper;
use WP2FA\Extensions\RoleSettings\Role_Settings_Controller;
use WP2FA\Licensing\Licensing_Factory;

$roles_controller = class_exists( Role_Settings_Controller::class, false ) && Licensing_Factory::has_active_valid_license();

// Determine current state.
$enabled       = (bool) Settings_Utils::get_setting_role( null, Passkeys::POLICY_SETTINGS_NAME );
$certain_roles = (bool) Settings_Utils::get_setting_role( null, Passkeys::POLICY_SETTINGS_NAME . '_certain_roles' );

// If "certain roles" is set, the global enable is actually off, and vice versa.
if ( ! $enabled && $certain_roles ) {
	$passkeys_on    = true;
	$all_roles_mode = false;
} elseif ( $enabled ) {
	$passkeys_on    = true;
	$all_roles_mode = true;
} else {
	$passkeys_on    = false;
	$all_roles_mode = true;
}

// Get roles for the role selector.
if ( $roles_controller ) {
	$roles         = array_flip( WP_Helper::get_roles() );
	$checked_roles = array();
	foreach ( $roles as $role_slug => $role_name ) {
		if ( Passkeys::is_enabled( $role_slug ) ) {
			$checked_roles[] = $role_slug;
		}
	}
}

$skip_2fa = Settings_Utils::string_to_bool( WP2FA::get_wp2fa_general_setting( 'skip_2fa_for_passkeys' ) );
$is_free  = ! Licensing_Factory::has_active_valid_license();
?>

<div class="passkeys-settings-main-wrapper">
<?php
Settings_Builder::build_option(
	array(
		'title' => \esc_html__( 'Passkeys', 'wp-2fa' ),
		'id'    => 'passkeys-page-title',
		'type'  => 'tab-title',
	)
);
?>

<div class="settings-card">
	<?php
	Settings_Builder::build_option(
		array(
			'text'  => \esc_html__( 'Passkeys are not a two-factor authentication (2FA) method. They are a passwordless multi-factor authentication (MFA) solution that allows users to securely log in without using a password.', 'wp-2fa' ),
			'class' => 'description-settings-card',
			'id'    => 'passkeys-description',
			'type'  => 'description',
		)
	);
	?>

	<div class="form-group settings-row">
		<?php
		Settings_Builder::build_option(
			array(
				'id'          => 'passkeys-enabled',
				'type'        => 'toggle-checkbox',
				'option_name' => 'wp_2fa_passkeys[' . Passkeys::POLICY_SETTINGS_NAME . ']',
				'default'     => $passkeys_on,
				'text'        => \esc_html__( 'Allow users to register Passkeys', 'wp-2fa' ),
			)
		);
		?>
	</div>
</div>

<?php if ( $roles_controller ) { ?>
<div class="settings-card passkeys-enforcement-card" id="passkeys-enforcement-card" style="<?php echo ! $passkeys_on ? 'display:none;' : ''; ?>">
	<?php
	Settings_Builder::build_option(
		array(
			'title' => \esc_html__( 'Enforce Passkeys on:', 'wp-2fa' ),
			'id'    => 'passkeys-enforce-title',
			'type'  => 'section-title',
		)
	);
	?>

	<div class="form-group settings-row">
		<?php
		Settings_Builder::build_option(
			array(
				'id'          => 'passkeys-enforcement-policy',
				'type'        => 'radio',
				'option_name' => 'wp_2fa_passkeys[enforcement-policy]',
				'default'     => $all_roles_mode ? 'all-roles' : 'certain-roles-only',
				'options'     => array(
					'all-roles'          => \esc_html__( 'Allow all users with any roles to setup and use Passkeys', 'wp-2fa' ),
					'certain-roles-only' => \esc_html__( 'Only for specific roles', 'wp-2fa' ),
				),
			)
		);
		?>
	</div>

	<div class="passkeys-roles-selector" id="passkeys-roles-selector" style="<?php echo $all_roles_mode ? 'display:none;' : ''; ?>">
		<?php
		Settings_Builder::build_option(
			array(
				'id'          => 'passkeys-roles',
				'type'        => 'user-roles',
				'roles'       => $roles,
				'checked'     => $checked_roles,
				'name_prefix' => 'wp_2fa_passkeys[enabled_roles]',
			)
		);
		?>
	</div>
</div>
<?php } else { ?>
<div class="settings-card passkeys-enforcement-card" id="passkeys-enforcement-card" style="<?php echo $is_free ? '' : ( ! $passkeys_on ? 'display:none;' : '' ); ?>">
	<?php
	Settings_Builder::build_option(
		array(
			'title' => \esc_html__( 'Enforce Passkeys on:', 'wp-2fa' ),
			'id'    => 'passkeys-enforce-title',
			'type'  => 'section-title',
		)
	);
	Settings_Builder::premium_gate_open( false );
	echo Settings_Builder::get_premium_badge_html( 'wp-2fa-passkeys:enforce-roles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

	<div class="form-group settings-row">
		<?php
		Settings_Builder::build_option(
			array(
				'id'          => 'passkeys-enforcement-policy-locked',
				'type'        => 'radio',
				'option_name' => 'wp_2fa_passkeys_locked[enforcement-policy]',
				'default'     => 'all-roles',
				'options'     => array(
					'all-roles'          => \esc_html__( 'Allow all users with any roles to setup and use Passkeys', 'wp-2fa' ),
					'certain-roles-only' => \esc_html__( 'Only for specific roles', 'wp-2fa' ),
				),
			)
		);
		?>
	</div>
	<?php Settings_Builder::premium_gate_close(); ?>
</div>
<?php } ?>

<?php
// @free:start
?>
<?php if ( ! Licensing_Factory::has_active_valid_license() ) : ?>
<input type="hidden" name="wp_2fa_settingspass[skip_2fa_for_passkeys]" value="1">
<?php endif; ?>
<?php
// @free:end
?>

<?php
?>

<?php if ( ! Licensing_Factory::has_active_valid_license() ) : ?>
<div class="settings-card" id="passkeys-bypass-card" style="<?php echo $is_free ? '' : ( ! $passkeys_on ? 'display:none;' : '' ); ?>">
	<?php
	Settings_Builder::premium_gate_open( false );

	?>
	<div style="margin-left:18px">
		<?php echo Settings_Builder::get_premium_badge_html( 'wp-2fa-passkeys:bypass-2fa' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<div class="form-group settings-row">
		<?php
		Settings_Builder::build_option(
			array(
				'id'          => 'skip_2fa_for_passkeys_locked',
				'type'        => 'toggle-checkbox',
				'option_name' => 'wp_2fa_settingspass_locked[skip_2fa_for_passkeys]',
				'default'     => false,
				'text'        => \esc_html__( 'Bypass 2FA after login with Passkey', 'wp-2fa' ),
			)
		);
		?>
	</div>
	<?php
	Settings_Builder::build_option(
		array(
			'text'  => \esc_html__( 'When enabled, users who have both Passkeys and 2FA configured won\'t be asked to complete an additional 2FA verification step after signing in with a Passkey. Disable this option to require both Passkey authentication and 2FA for enhanced account security.', 'wp-2fa' ),
			'class' => 'description-settings-card',
			'id'    => 'bypass-2fa-description',
			'type'  => 'description',
		)
	);
	Settings_Builder::premium_gate_close();
	?>
</div>
<?php endif; ?>
<?php
// @premium:end
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var isFree = <?php echo $is_free ? 'true' : 'false'; ?>;
	var passkeysToggle = document.getElementById('passkeys-enabled');
	var enforcementCard = document.getElementById('passkeys-enforcement-card');
	var bypassCard = document.getElementById('passkeys-bypass-card');
	var rolesSelector = document.getElementById('passkeys-roles-selector');
	var form = document.getElementById('wp-2fa-admin-settings');
	var submitBtn = form ? form.querySelector('#submit, [type="submit"]') : null;
	var noteText = form ? (form.dataset.disabledNote || '<?php echo \esc_js( \__( 'Please select at least one role to save.', 'wp-2fa' ) ); ?>') : '';
	var submitNoteEl = null;

	function ensureSubmitNote() {
		if (!submitBtn) return;
		if (!submitNoteEl) {
			submitNoteEl = document.createElement('span');
			submitNoteEl.className = 'wp2fa-submit-disabled-note description';
			submitNoteEl.style.marginLeft = '8px';
			submitNoteEl.style.color = '#646970';
			submitNoteEl.style.fontSize = '12px';
			submitNoteEl.style.fontStyle = 'italic';
			submitBtn.insertAdjacentElement('afterend', submitNoteEl);
		}
	}

	function updateSubmitState() {
		if (!submitBtn) return;
		var certainRadio = document.querySelector('input[name="wp_2fa_passkeys[enforcement-policy]"][value="certain-roles-only"]');
		var requireSelection = !!(passkeysToggle && passkeysToggle.checked && certainRadio && certainRadio.checked && rolesSelector && rolesSelector.style.display !== 'none');
		var checkedCount = 0;
		if (requireSelection) {
			checkedCount = rolesSelector.querySelectorAll('input[type="checkbox"]:checked').length;
		}
		var shouldDisable = requireSelection && checkedCount === 0;
		submitBtn.disabled = shouldDisable;
		ensureSubmitNote();
		if (submitNoteEl) {
			submitNoteEl.textContent = shouldDisable ? noteText : '';
			submitNoteEl.style.display = shouldDisable ? 'inline' : 'none';
		}
	}

	function toggleVisibility() {
		if (!passkeysToggle) return;
		var isOn = passkeysToggle.checked;

		if (!isFree) {
			if (enforcementCard) enforcementCard.style.display = isOn ? '' : 'none';
			if (bypassCard) bypassCard.style.display = isOn ? '' : 'none';
		}

		if (isOn && rolesSelector) {
			var certainRadio = document.querySelector('input[name="wp_2fa_passkeys[enforcement-policy]"][value="certain-roles-only"]');
			rolesSelector.style.display = (certainRadio && certainRadio.checked) ? '' : 'none';
		}

		updateSubmitState();
	}

	if (passkeysToggle) {
		passkeysToggle.addEventListener('change', toggleVisibility);
	}

	// Radio change toggles roles selector
	document.querySelectorAll('input[name="wp_2fa_passkeys[enforcement-policy]"]').forEach(function(radio) {
		radio.addEventListener('change', function() {
			if (rolesSelector) {
				rolesSelector.style.display = (this.value === 'certain-roles-only') ? '' : 'none';
			}
			updateSubmitState();
		});
	});

	// Role checkbox listeners
	if (rolesSelector) {
		rolesSelector.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
			cb.addEventListener('change', updateSubmitState);
		});
	}

	// Initial state
	toggleVisibility();
});
</script>
</div>
