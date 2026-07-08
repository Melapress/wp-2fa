<?php
/**
 * WP 2FA Wizard Template: Backup Method Selection
 *
 * Shown after a primary method is validated when more than one backup
 * method is available.  The user picks which backup method to configure.
 *
 * Contains three template IDs:
 *   - wp2fa-wizard-backup-method-item      — one radio item in the backup method list.
 *   - wp2fa-wizard-backup-select-intro     — success banner + intro text.
 *   - wp2fa-wizard-backup-select-footer    — footer buttons for the selection step.
 *
 * tmpl-wp2fa-wizard-backup-method-item data:
 *   - id          {string}  Backup method identifier (e.g. 'backup_codes').
 *   - name        {string}  Display name.
 *   - description {string}  Optional description text.
 *   - checked     {bool}    Whether this radio is pre-selected.
 *
 * tmpl-wp2fa-wizard-backup-select-intro data:
 *   - title       {string}  Heading text (e.g. "Your login just got more secure").
 *   - description {string}  Paragraph below the heading.
 *   - sectionTitle {string} Section sub-heading (e.g. "Select backup method").
 *
 * tmpl-wp2fa-wizard-backup-select-footer data:
 *   - closeLaterLabel    {string} "Close wizard & configure 2FA later" button text.
 *   - configureLabel     {string} "Configure backup 2FA method" button text.
 *   - disabled           {bool}   Whether the configure button is disabled.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-wizard-backup-select-intro">
	<div class="wp2fa-wizard-backup-select-success">
		<div class="wp2fa-wizard-success-icon" aria-hidden="true">
			<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
				<circle cx="24" cy="24" r="24" fill="#edfaef"/>
				<circle cx="24" cy="24" r="18" fill="#d4edda"/>
				<path d="M16 24l6 6 10-12" stroke="#2e7d32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
			</svg>
		</div>
		<h3 class="wp2fa-wizard-backup-select-title">{{ data.title }}</h3>
		<p class="wp2fa-wizard-backup-select-description">{{{ data.description }}}</p>
	</div>
	<h4 class="wp2fa-wizard-backup-select-section-title">{{ data.sectionTitle }}</h4>
</script>

<script type="text/html" id="tmpl-wp2fa-wizard-backup-method-item">
	<li class="wp2fa-wizard-method-item" id="wp2fa-wizard-backup-method-item-{{ data.id }}">
		<label class="wp2fa-wizard-method-label" data-method-id="{{ data.id }}" id="wp2fa-wizard-backup-method-label-{{ data.id }}">
			<input type="radio"
				name="wp2fa_wizard_backup_method"
				id="wp2fa-wizard-backup-method-{{ data.id }}"
				value="{{ data.id }}"
				class="wp2fa-wizard-backup-method-radio"
				data-method-id="{{ data.id }}"
				<# if ( data.checked ) { #>checked<# } #>
			/>
			<span class="wp2fa-wizard-method-info">
				<span class="wp2fa-wizard-method-name">{{{ data.name }}}</span>
				<# if ( data.description ) { #>
					<span class="wp2fa-wizard-method-description">{{{ data.description }}}</span>
				<# } #>
			</span>
		</label>
	</li>
</script>

<script type="text/html" id="tmpl-wp2fa-wizard-backup-select-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp2fa-wizard-btn-close-later wp-2fa-button-secondary" id="wp2fa-wizard-backup-select-btn-close" type="button">{{ data.closeLaterLabel }}</button>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-wizard-backup-select-btn-configure" type="button" <# if ( data.disabled ) { #>disabled<# } #>>{{ data.configureLabel }}</button>
</script>
