<?php
/**
 * WP 2FA Wizard Template: Backup Codes Generate
 *
 * Template ID: wp2fa-backup-codes-generate
 *
 * Data expected:
 *   - intro         {string} Introductory text explaining backup codes.
 *   - generateLabel {string} "Generate list of backup codes" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-backup-codes-generate">
	<div class="wp2fa-wizard-success-icon" aria-hidden="true">
		<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
			<circle cx="24" cy="24" r="24" fill="#edfaef"/>
			<circle cx="24" cy="24" r="18" fill="#d4edda"/>
			<path d="M16 24l6 6 10-12" stroke="#2e7d32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
		</svg>
	</div>
	<div class="wp2fa-backup-codes-intro" id="wp2fa-backup-codes-intro">
		{{{ data.intro }}}
	</div>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-backup-codes-generate-btn" type="button">{{ data.generateLabel }}</button>
</script>
