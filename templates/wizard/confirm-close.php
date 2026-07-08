<?php
/**
 * WP 2FA Wizard Template: Close Confirmation Dialog
 *
 * Template ID: wp2fa-wizard-confirm-close
 *
 * Data expected:
 *   - bodyHtml    {string} HTML content for the dialog body (white-label).
 *   - yesLabel    {string} Confirm (Yes) button text.
 *   - noLabel     {string} Cancel (No) button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-wizard-confirm-close">
	<div class="wp2fa-wizard-confirm-overlay" id="wp2fa-wizard-confirm-overlay" role="alertdialog" aria-modal="true" aria-label="{{ data.yesLabel }}">
		<div class="wp2fa-wizard-confirm-dialog" id="wp2fa-wizard-confirm-dialog">
			<div class="wp2fa-wizard-confirm-body" id="wp2fa-wizard-confirm-body">{{{ data.bodyHtml }}}</div>
			<div class="wp2fa-wizard-confirm-footer" id="wp2fa-wizard-confirm-footer">
				<button class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-wizard-confirm-no" type="button">{{ data.noLabel }}</button>
				<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-wizard-confirm-yes" type="button">{{ data.yesLabel }}</button>
			</div>
		</div>
	</div>
</script>
