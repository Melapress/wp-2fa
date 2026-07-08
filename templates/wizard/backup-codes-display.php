<?php
/**
 * WP 2FA Wizard Template: Backup Codes Display
 *
 * Contains two template IDs:
 *   - wp2fa-backup-codes-display        — Displays generated codes with actions.
 *   - wp2fa-backup-codes-display-footer — Footer button to close wizard.
 *
 * tmpl-wp2fa-backup-codes-display data:
 *   - intro         {string}  Introductory text after generation.
 *   - codesText     {string}  Newline-separated codes string.
 *   - codesCount    {number}  Number of codes.
 *   - showCopyBtn   {bool}    Whether clipboard copy is supported.
 *   - copyLabel     {string}  "Copy" button text.
 *   - downloadLabel {string}  "Download" button text.
 *   - printLabel    {string}  "Print" button text.
 *   - emailLabel    {string}  "Send via email" button text.
 *   - emailNonce    {string}  Nonce for email sending AJAX request.
 *
 * tmpl-wp2fa-backup-codes-display-footer data:
 *   - closeLabel {string} "I'm ready, close the wizard" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-backup-codes-display">
	<p class="wp2fa-backup-codes-display-intro" id="wp2fa-backup-codes-display-intro">{{{ data.intro }}}</p>

	<textarea class="wp2fa-backup-codes-textarea" id="wp2fa-backup-codes-textarea" readonly rows="{{ data.codesCount }}" cols="50">{{ data.codesText }}</textarea>

	<div class="wp2fa-backup-codes-actions" id="wp2fa-backup-codes-actions">
		<# if ( data.showCopyBtn ) { #>
			<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-backup-codes-copy-btn" type="button">{{ data.copyLabel }}</button>
		<# } #>
		<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-backup-codes-download-btn" type="button">{{ data.downloadLabel }}</button>
		<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-backup-codes-print-btn" type="button">{{ data.printLabel }}</button>
		<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-backup-codes-email-btn" type="button" data-nonce="{{ data.emailNonce }}">{{ data.emailLabel }}</button>
	</div>

	<div class="wp2fa-backup-codes-status" id="wp2fa-backup-codes-status" style="display: none;"></div>

	<style>
		#wp2fa-wizard-title {
			display: none !important;
		}
		.wp2fa-setup-wizard-body {
			margin-top: -44px !important;
		}
	</style>
</script>

<script type="text/html" id="tmpl-wp2fa-backup-codes-display-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-backup-codes-close-btn" type="button">{{ data.closeLabel }}</button>
</script>
