<?php
/**
 * WP 2FA Wizard Template: Email Verify
 *
 * Contains two template IDs:
 *   - wp2fa-email-verify        — Verification code input and resend.
 *   - wp2fa-email-verify-footer — Footer buttons for the verify step.
 *
 * tmpl-wp2fa-email-verify data:
 *   - description {string} Introductory text.
 *   - codeLabel   {string} Label for the code input.
 *   - placeholder {string} Placeholder text (e.g. '000000').
 *   - resendLabel {string} "Send me another code" button text.
 *
 * tmpl-wp2fa-email-verify-footer data:
 *   - cancelLabel   {string} "Cancel" button text.
 *   - validateLabel {string} "Validate & Save" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-email-verify">
	<div class="wp2fa-email-verify-wrapper" id="wp2fa-email-verify-wrapper">
		<p class="wp2fa-email-verify-description" id="wp2fa-email-verify-description">{{{ data.description }}}</p>
		<label for="wp2fa-email-authcode">{{ data.codeLabel }}</label>
		<input type="tel" class="wp2fa-email-verify-input" id="wp2fa-email-authcode" name="wp-2fa-email-authcode" pattern="[0-9]*" autocomplete="off" placeholder="{{ data.placeholder }}" />
		<div class="wp2fa-wizard-verification-response" id="wp2fa-email-verification-response"></div>
		<button type="button" class="wp2fa-resend-code-link" id="wp2fa-email-resend-code">{{ data.resendLabel }}</button>
	</div>

	<style>
		#wp2fa-wizard-title {
			display: none !important;
		}
		.wp2fa-setup-wizard-body {
			margin-top: -44px !important;
		}
	</style>
</script>

<script type="text/html" id="tmpl-wp2fa-email-verify-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-email-verify-btn-cancel" type="button">{{ data.cancelLabel }}</button>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-email-btn-validate" type="button">{{ data.validateLabel }}</button>
</script>
